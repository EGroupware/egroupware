/**
 * Tests for FilterMixin and its plainest consumer Et2FilterHeader (`et2-nextmatch-header-filter`).
 *
 * FilterMixin is shared by every filtering nextmatch header (filter, account, entry, custom), so
 * its contract is pinned here once through the simplest consumer rather than repeated per header.
 *
 * Behaviour under test:
 * - the mixin synthesises an "all" empty option so a filter can always be cleared, preferring the
 *   header's own label over the generic translation, and leaving an explicitly provided empty
 *   option or emptyLabel alone
 * - a `change` emits a composed, cancelable `et2-nextmatch-filter` carrying
 *   `{col_filter: {<id>: <value>}}`
 * - the legacy direct-nextmatch fallback (`applyFilters()`) only runs when nothing handled the
 *   event, and is skipped entirely when no nextmatch was ever bound
 * - `setNextmatch()` seeds the control from the nextmatch's already-active col_filter, so a header
 *   rendered into an already-filtered list shows the filter that is in effect
 * - Et2FilterHeader itself opts into hoisting and clearing
 *
 * Setup strategy:
 * Headers are appended to a throwaway host that listens for the bubbling filter event; a
 * recording `fakeNextmatch()` double captures the legacy fallback.  `change` is dispatched
 * directly rather than driven through the Shoelace listbox, because the contract under test is
 * the mixin's handler, not Et2Select's own event plumbing.
 *
 * Pass criteria:
 * Explicit assertions on emptyLabel, on the emitted event detail, and on the recorded
 * `applyFilters()` calls.  The fallback is deferred through queueMicrotask(), so those assertions
 * await the microtask queue.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from "@open-wc/testing";
import "../Headers/FilterHeader";
import {Et2FilterHeader} from "../Headers/FilterHeader";
import {ET2_NEXTMATCH_FILTER_EVENT} from "../Headers/events";
import {fakeNextmatch, inHost, installEgwStub, waitForBubblingHandlers} from "./headerHelpers";

installEgwStub();

const OPTIONS = [
	{value: "1", label: "One"},
	{value: "2", label: "Two"}
];

const filterHeader = (id = "cat_id", options : any[] = OPTIONS) =>
{
	const header = <any>document.createElement("et2-nextmatch-header-filter");
	header.id = id;
	header.select_options = options;
	return header;
};

/**
 * Fire a change the way Et2Select does once a value is picked, and return the filter event the
 * host saw (if any).
 */
async function changeAndCaptureFilter(element : any, host : HTMLElement, value : any, handler? : (event : CustomEvent) => void)
{
	element.value = value;
	await element.updateComplete;

	let captured : CustomEvent | null = null;
	const listener = (event : Event) =>
	{
		captured = <CustomEvent>event;
		handler?.(<CustomEvent>event);
	};
	host.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, listener);
	element.dispatchEvent(new Event("change", {bubbles: true}));
	await waitForBubblingHandlers();
	host.removeEventListener(ET2_NEXTMATCH_FILTER_EVENT, listener);
	return <CustomEvent | null>captured;
}

describe("Et2FilterHeader", () =>
{
	it("upgrades and opts into hoisting and clearing", async() =>
	{
		const {host, element} = await inHost(filterHeader());
		try
		{
			assert.instanceOf(element, Et2FilterHeader, "et2-nextmatch-header-filter did not upgrade");
			assert.isTrue(element.hoist, "a header filter must hoist, its dropdown escapes the header row otherwise");
			assert.isTrue(element.clearable, "a filter must be clearable");
		}
		finally
		{
			host.remove();
		}
	});

	describe("empty option", () =>
	{
		it("adds a generic 'All' entry when there is nothing to clear to", async() =>
		{
			const {host, element} = await inHost(filterHeader());
			try
			{
				assert.equal(element.emptyLabel, "All", "an unlabelled filter should offer 'All'");
			}
			finally
			{
				host.remove();
			}
		});

		it("prefers the header's own label over the generic one", async() =>
		{
			const {host, element} = await inHost(filterHeader(), e => e.label = "Category");
			try
			{
				assert.equal(element.emptyLabel, "Category", "a labelled filter should use its label as empty option");
			}
			finally
			{
				host.remove();
			}
		});

		it("leaves an explicitly configured empty option alone", async() =>
		{
			const withEmpty = filterHeader("cat_id", [{value: "", label: "Any category"}, ...OPTIONS]);
			const {host, element} = await inHost(withEmpty);
			try
			{
				assert.equal(element.emptyLabel, "", "an existing empty option must not be duplicated");
			}
			finally
			{
				host.remove();
			}
		});

		it("leaves an explicitly configured emptyLabel alone", async() =>
		{
			const {host, element} = await inHost(filterHeader(), e => e.emptyLabel = "- no category -");
			try
			{
				assert.equal(element.emptyLabel, "- no category -", "a configured emptyLabel must win");
			}
			finally
			{
				host.remove();
			}
		});
	});

	describe("filter event", () =>
	{
		it("emits a composed filter event keyed by the header id", async() =>
		{
			const {host, element} = await inHost(filterHeader("cat_id"));
			try
			{
				const event = await changeAndCaptureFilter(element, host, "2");

				assert.isNotNull(event, "change should emit " + ET2_NEXTMATCH_FILTER_EVENT);
				assert.isTrue(event!.composed, "event must cross the shadow boundary");
				assert.isTrue(event!.bubbles, "event must bubble to the nextmatch host");
				assert.isTrue(event!.cancelable, "event must be cancelable so a modern handler can suppress the legacy path");
				assert.deepEqual(
					event!.detail.filters,
					{col_filter: {cat_id: "2"}},
					"filter event should carry a col_filter keyed by the header id"
				);
			}
			finally
			{
				host.remove();
			}
		});

		it("reports an emptied filter rather than omitting it", async() =>
		{
			const {host, element} = await inHost(filterHeader("cat_id"));
			try
			{
				const event = await changeAndCaptureFilter(element, host, "");

				assert.deepEqual(
					event!.detail.filters,
					{col_filter: {cat_id: ""}},
					"clearing must send an empty value, or the previous filter would stay applied"
				);
			}
			finally
			{
				host.remove();
			}
		});

		it("stops emitting once disconnected", async() =>
		{
			const {host, element} = await inHost(filterHeader("cat_id"));
			try
			{
				let emitted = 0;
				element.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, () => emitted++);

				element.remove();
				element.value = "1";
				element.dispatchEvent(new Event("change"));
				await waitForBubblingHandlers();

				assert.equal(emitted, 0, "a detached header must not keep filtering the list it left");
			}
			finally
			{
				host.remove();
			}
		});
	});

	describe("legacy nextmatch fallback", () =>
	{
		it("applies the filter directly when nothing handles the event", async() =>
		{
			const {host, element} = await inHost(filterHeader("cat_id"));
			try
			{
				const nextmatch = fakeNextmatch();
				element.setNextmatch(nextmatch);

				await changeAndCaptureFilter(element, host, "2");

				assert.deepEqual(
					nextmatch.calls.applyFilters,
					[{col_filter: {cat_id: "2"}}],
					"unhandled filter event should fall back to nextmatch.applyFilters()"
				);
			}
			finally
			{
				host.remove();
			}
		});

		it("skips the fallback when a modern handler cancels the event", async() =>
		{
			const {host, element} = await inHost(filterHeader("cat_id"));
			try
			{
				const nextmatch = fakeNextmatch();
				element.setNextmatch(nextmatch);

				await changeAndCaptureFilter(element, host, "2", event => event.preventDefault());

				assert.isEmpty(
					nextmatch.calls.applyFilters,
					"preventDefault() must suppress the legacy path, or the list would reload twice"
				);
			}
			finally
			{
				host.remove();
			}
		});

		it("emits without error when no nextmatch was bound", async() =>
		{
			const {host, element} = await inHost(filterHeader("cat_id"));
			try
			{
				const event = await changeAndCaptureFilter(element, host, "2");
				assert.isNotNull(event, "the event is the only contract under et2-nextmatch");
			}
			finally
			{
				host.remove();
			}
		});
	});

	it("seeds itself from the filter already active on the nextmatch", async() =>
	{
		const {host, element} = await inHost(filterHeader("cat_id"));
		try
		{
			element.setNextmatch(fakeNextmatch({col_filter: {cat_id: "2"}}));
			await element.updateComplete;

			assert.equal(
				element.value,
				"2",
				"a header rendered into an already-filtered list must show the active filter"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("leaves the value alone when the nextmatch has no filter for it", async() =>
	{
		const {host, element} = await inHost(filterHeader("cat_id"));
		try
		{
			element.value = "1";
			await element.updateComplete;
			element.setNextmatch(fakeNextmatch({col_filter: {other_col: "9"}}));
			await element.updateComplete;

			assert.equal(element.value, "1", "an unrelated col_filter must not clear this header");
		}
		finally
		{
			host.remove();
		}
	});
});
