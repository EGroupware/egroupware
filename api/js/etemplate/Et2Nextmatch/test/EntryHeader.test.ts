/**
 * Tests for Et2EntryFilterHeader (`et2-nextmatch-header-entry`).
 *
 * This is FilterMixin over Et2LinkEntry, and it exists purely to translate Et2LinkEntry's
 * structured `{app, id}` value into the legacy `app:id` string a nextmatch col_filter expects.
 * That translation is the whole widget, and it has a deliberately narrow escape hatch: anything
 * that is not one simple selection is passed through unchanged.
 *
 * Behaviour under test:
 * - a simple `{app, id}` selection reads back as "app:id"
 * - an incomplete selection (no app, or no id) reads back as null rather than a half-formed filter
 * - a non-simple id (eg. an array from a multiple selection) falls through to the parent's object
 *   value instead of being stringified into nonsense
 * - the value setter still accepts what Et2LinkEntry accepts, so `app:id` round-trips
 * - the FilterMixin contract still holds: a change emits `et2-nextmatch-filter` carrying the
 *   translated string
 *
 * Setup strategy:
 * Values are set through the public `value` setter (and `set_value()` for the legacy string form)
 * on a connected element, then read back through the getter under test.  The filter event is
 * captured from a throwaway host.
 *
 * Pass criteria:
 * Explicit assertions on the getter's return value for each shape, and on the emitted col_filter.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from "@open-wc/testing";
import "../Headers/EntryHeader";
import {Et2EntryFilterHeader} from "../Headers/EntryHeader";
import {ET2_NEXTMATCH_FILTER_EVENT} from "../Headers/events";
import {fakeNextmatch, inHost, installEgwStub, waitForBubblingHandlers} from "./headerHelpers";

installEgwStub();

const entryHeader = (id = "link_id") =>
{
	const header = <any>document.createElement("et2-nextmatch-header-entry");
	header.id = id;
	return header;
};

describe("Et2EntryFilterHeader", () =>
{
	it("upgrades", async() =>
	{
		const {host, element} = await inHost(entryHeader());
		try
		{
			assert.instanceOf(element, Et2EntryFilterHeader, "et2-nextmatch-header-entry did not upgrade");
		}
		finally
		{
			host.remove();
		}
	});

	it("flattens a simple selection to the legacy app:id string", async() =>
	{
		const {host, element} = await inHost(entryHeader());
		try
		{
			element.value = {app: "infolog", id: "123"};
			await element.updateComplete;

			assert.equal(
				element.value,
				"infolog:123",
				"nextmatch col_filter expects app:id, not Et2LinkEntry's object"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("round-trips a legacy app:id string", async() =>
	{
		const {host, element} = await inHost(entryHeader());
		try
		{
			element.set_value("infolog:123");
			await element.updateComplete;

			assert.equal(element.value, "infolog:123", "an app:id string should survive a round-trip");
		}
		finally
		{
			host.remove();
		}
	});

	it("reports null for a selection that has no entry yet", async() =>
	{
		const {host, element} = await inHost(entryHeader());
		try
		{
			element.value = {app: "infolog", id: ""};
			await element.updateComplete;

			assert.isNull(
				element.value,
				"an app with no entry chosen is not a filter, and must not become 'infolog:'"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("reports null when the app is missing", async() =>
	{
		const {host, element} = await inHost(entryHeader());
		try
		{
			element.value = {app: "", id: "123"};
			await element.updateComplete;

			assert.isNull(element.value, "an id without an app cannot be resolved to an entry");
		}
		finally
		{
			host.remove();
		}
	});

	it("passes a non-simple id through unchanged", async() =>
	{
		const {host, element} = await inHost(entryHeader());
		try
		{
			element.value = {app: "infolog", id: ["123", "456"]};
			await element.updateComplete;

			assert.deepEqual(
				element.value,
				{app: "infolog", id: ["123", "456"]},
				"a multiple selection must keep its structure rather than be stringified"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("emits the flattened value as a col_filter", async() =>
	{
		const {host, element} = await inHost(entryHeader("link_id"));
		try
		{
			element.value = {app: "infolog", id: "123"};
			await element.updateComplete;

			let detail : any = null;
			host.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, (event : Event) => detail = (<CustomEvent>event).detail);
			element.dispatchEvent(new Event("change", {bubbles: true}));
			await waitForBubblingHandlers();

			assert.deepEqual(
				detail?.filters,
				{col_filter: {link_id: "infolog:123"}},
				"entry header should filter by the flattened app:id string"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("applies the filter through the legacy nextmatch when unhandled", async() =>
	{
		const {host, element} = await inHost(entryHeader("link_id"));
		try
		{
			const nextmatch = fakeNextmatch();
			element.setNextmatch(nextmatch);
			element.value = {app: "infolog", id: "123"};
			await element.updateComplete;

			element.dispatchEvent(new Event("change", {bubbles: true}));
			await waitForBubblingHandlers();

			assert.deepEqual(
				nextmatch.calls.applyFilters,
				[{col_filter: {link_id: "infolog:123"}}],
				"unhandled entry filter should fall back to nextmatch.applyFilters()"
			);
		}
		finally
		{
			host.remove();
		}
	});
});
