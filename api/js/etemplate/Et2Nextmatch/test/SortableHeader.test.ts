/**
 * Tests for Et2NextmatchSortableHeader (`et2-nextmatch-sortheader`).
 *
 * Behaviour under test:
 * - a click emits a composed, cancelable `et2-nextmatch-sort` event carrying the column id and the
 *   *next* sort direction
 * - the direction cycle depends on `sortmode`: default ASC cycles none -> asc -> desc -> none,
 *   `sortmode="DESC"` cycles none -> desc -> asc -> none.  A click does NOT advance the header's
 *   own state - the owner echoes the applied mode back via `setSortmode()`, so the tests drive the
 *   cycle the same way nextmatch does.
 * - the legacy direct-nextmatch fallback only runs when nothing handled the event
 *   (`preventDefault()` suppresses it), and calls `resetSort()` rather than `sortBy()` when
 *   clearing
 * - `set_sortmode()` is ignored once bound to a nextmatch, per the legacy semantics the docblock
 *   documents
 * - the rendered caret reflects the current mode
 *
 * Setup strategy:
 * Headers are appended to a throwaway host which listens for the bubbling sort event; a recording
 * `fakeNextmatch()` double captures what the legacy fallback did.
 *
 * Pass criteria:
 * Explicit assertions on the emitted event detail, on the recorded nextmatch calls, and on the
 * rendered indicator class.  The fallback is dispatched from a queueMicrotask(), so assertions
 * about it are made after awaiting the microtask queue.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from "@open-wc/testing";
import "../Headers/SortableHeader";
import {Et2NextmatchSortableHeader} from "../Headers/SortableHeader";
import {ET2_NEXTMATCH_SORT_EVENT} from "../Headers/events";
import {fakeNextmatch, inHost, installEgwStub, waitForBubblingHandlers} from "./headerHelpers";

installEgwStub();

const sortHeader = (id = "n_family", attributes : Record<string, string> = {}) =>
{
	const header = <Et2NextmatchSortableHeader>document.createElement("et2-nextmatch-sortheader");
	header.id = id;
	Object.entries(attributes).forEach(([name, value]) => header.setAttribute(name, value));
	return header;
};

/**
 * Click the header and return the detail of the sort event the host saw, plus a promise-free
 * record of whether anything was emitted at all.
 */
async function clickAndCaptureSort(element : Et2NextmatchSortableHeader, host : HTMLElement, handler? : (event : CustomEvent) => void)
{
	let captured : CustomEvent | null = null;
	const listener = (event : Event) =>
	{
		captured = <CustomEvent>event;
		handler?.(<CustomEvent>event);
	};
	host.addEventListener(ET2_NEXTMATCH_SORT_EVENT, listener);
	element.click();
	await waitForBubblingHandlers();
	host.removeEventListener(ET2_NEXTMATCH_SORT_EVENT, listener);
	return <CustomEvent | null>captured;
}

const indicator = (element : Et2NextmatchSortableHeader) =>
	element.shadowRoot?.querySelector(".nextmatch_sortheader--marker")?.className ?? "";

const captionClasses = (element : Et2NextmatchSortableHeader) =>
	element.shadowRoot?.querySelector(".nextmatch_sortheader")?.className ?? "";

describe("Et2NextmatchSortableHeader", () =>
{
	it("upgrades and inherits the plain header's label rendering", async() =>
	{
		const {host, element} = await inHost(sortHeader(), e => e.label = "Last name");
		try
		{
			assert.instanceOf(element, Et2NextmatchSortableHeader, "et2-nextmatch-sortheader did not upgrade");
			assert.include(
				element.shadowRoot?.querySelector(".nextmatch_sortheader")?.textContent?.trim(),
				"Last name",
				"sortable header should still render its caption"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("emits a composed sort event on click", async() =>
	{
		const {host, element} = await inHost(sortHeader("n_family"));
		try
		{
			const event = await clickAndCaptureSort(element, host);

			assert.isNotNull(event, "clicking should emit " + ET2_NEXTMATCH_SORT_EVENT);
			assert.isTrue(event!.composed, "event must cross the shadow boundary to reach nextmatch");
			assert.isTrue(event!.bubbles, "event must bubble to the nextmatch host");
			assert.isTrue(event!.cancelable, "event must be cancelable so a modern handler can suppress the legacy path");
			assert.deepEqual(
				{id: event!.detail.id, asc: event!.detail.asc, clear: event!.detail.clear},
				{id: "n_family", asc: true, clear: false},
				"first click on an unsorted ASC-default header should request ascending"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("falls back to the DOM id attribute when no id property is set", async() =>
	{
		const header = <Et2NextmatchSortableHeader>document.createElement("et2-nextmatch-sortheader");
		header.setAttribute("id", "cat_id");
		const {host, element} = await inHost(header);
		try
		{
			const event = await clickAndCaptureSort(element, host);
			assert.equal(event!.detail.id, "cat_id", "sort id should fall back to the attribute");
		}
		finally
		{
			host.remove();
		}
	});

	it("cycles none -> asc -> desc -> none for an ASC-default header", async() =>
	{
		const {host, element} = await inHost(sortHeader());
		try
		{
			const seen : any[] = [];
			for(const mode of <("none" | "asc" | "desc")[]>["none", "asc", "desc"])
			{
				element.setSortmode(mode);
				await element.updateComplete;
				const event = await clickAndCaptureSort(element, host);
				seen.push({asc: event!.detail.asc, clear: event!.detail.clear});
			}

			assert.deepEqual(
				seen,
				[
					{asc: true, clear: false},
					{asc: false, clear: false},
					{asc: undefined, clear: true}
				],
				"ASC-default cycle should be ascending, descending, then clear"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("cycles none -> desc -> asc -> none when sortmode is DESC", async() =>
	{
		const {host, element} = await inHost(sortHeader("n_family", {sortmode: "DESC"}));
		try
		{
			const seen : any[] = [];
			for(const mode of <("none" | "desc" | "asc")[]>["none", "desc", "asc"])
			{
				element.setSortmode(mode);
				await element.updateComplete;
				const event = await clickAndCaptureSort(element, host);
				seen.push({asc: event!.detail.asc, clear: event!.detail.clear});
			}

			assert.deepEqual(
				seen,
				[
					{asc: false, clear: false},
					{asc: true, clear: false},
					{asc: undefined, clear: true}
				],
				"DESC-default cycle should be descending, ascending, then clear"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("renders a caret matching the applied sort mode", async() =>
	{
		const {host, element} = await inHost(sortHeader());
		try
		{
			assert.notInclude(indicator(element), "bi-caret", "an unsorted header should show no caret");

			element.setSortmode("asc");
			await element.updateComplete;
			assert.include(indicator(element), "bi-caret-up-fill", "ascending should render an up caret");
			assert.include(captionClasses(element), "asc", "caption should carry the mode class nextmatch CSS uses");

			element.setSortmode("desc");
			await element.updateComplete;
			assert.include(indicator(element), "bi-caret-down-fill", "descending should render a down caret");

			element.setSortmode("none");
			await element.updateComplete;
			assert.notInclude(indicator(element), "bi-caret", "clearing should remove the caret again");
		}
		finally
		{
			host.remove();
		}
	});

	it("does not emit while disabled", async() =>
	{
		const {host, element} = await inHost(sortHeader(), e => (<any>e).disabled = true);
		try
		{
			const event = await clickAndCaptureSort(element, host);
			assert.isNull(event, "a disabled header must not request a sort");
		}
		finally
		{
			host.remove();
		}
	});

	it("applies the legacy nextmatch sort when nothing handles the event", async() =>
	{
		const {host, element} = await inHost(sortHeader("n_family"));
		try
		{
			const nextmatch = fakeNextmatch();
			element.setNextmatch(nextmatch);

			await clickAndCaptureSort(element, host);

			assert.deepEqual(
				nextmatch.calls.sortBy,
				[{id: "n_family", asc: true, update: undefined}],
				"unhandled sort event should fall back to nextmatch.sortBy()"
			);
			assert.equal(nextmatch.calls.resetSort, 0, "a directional sort should not reset");
		}
		finally
		{
			host.remove();
		}
	});

	it("resets instead of sorting when the cycle reaches clear", async() =>
	{
		const {host, element} = await inHost(sortHeader("n_family"));
		try
		{
			const nextmatch = fakeNextmatch();
			element.setNextmatch(nextmatch);
			element.setSortmode("desc");
			await element.updateComplete;

			await clickAndCaptureSort(element, host);

			assert.equal(nextmatch.calls.resetSort, 1, "clearing should call nextmatch.resetSort()");
			assert.isEmpty(nextmatch.calls.sortBy, "clearing should not also call sortBy()");
		}
		finally
		{
			host.remove();
		}
	});

	it("skips the legacy fallback when a modern handler cancels the event", async() =>
	{
		const {host, element} = await inHost(sortHeader("n_family"));
		try
		{
			const nextmatch = fakeNextmatch();
			element.setNextmatch(nextmatch);

			await clickAndCaptureSort(element, host, event => event.preventDefault());

			assert.isEmpty(
				nextmatch.calls.sortBy,
				"preventDefault() must suppress the legacy direct-nextmatch sort, or it would sort twice"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("does nothing beyond emitting when no nextmatch is bound", async() =>
	{
		const {host, element} = await inHost(sortHeader("n_family"));
		try
		{
			// No setNextmatch() - the modern et2-nextmatch never calls it
			const event = await clickAndCaptureSort(element, host);
			assert.isNotNull(event, "the event is the only contract when unbound");
		}
		finally
		{
			host.remove();
		}
	});

	it("persists the resulting sort order as a user preference", async() =>
	{
		const preferences : any[] = [];
		installEgwStub({
			set_preference: (...args : any[]) => preferences.push(args)
		});
		const {host, element} = await inHost(sortHeader("n_family"));
		try
		{
			const nextmatch = fakeNextmatch({sort: {id: "n_family", asc: true}});
			element.setNextmatch(nextmatch);

			await clickAndCaptureSort(element, host);

			assert.lengthOf(preferences, 1, "sorting should store a preference");
			assert.deepEqual(
				preferences[0].slice(0, 2),
				["addressbook", "addressbook.index.rows_sort"],
				"preference should be keyed by app and the nextmatch template name"
			);
		}
		finally
		{
			host.remove();
			installEgwStub();
		}
	});

	it("ignores set_sortmode() once bound to a nextmatch", async() =>
	{
		const {host, element} = await inHost(sortHeader());
		try
		{
			element.set_sortmode("asc");
			await element.updateComplete;
			assert.include(indicator(element), "bi-caret-up-fill", "unbound set_sortmode() should apply");

			element.setNextmatch(fakeNextmatch());
			element.set_sortmode("desc");
			await element.updateComplete;
			assert.include(
				indicator(element),
				"bi-caret-up-fill",
				"once bound, nextmatch owns the sort mode and set_sortmode() is a no-op"
			);
		}
		finally
		{
			host.remove();
		}
	});
});
