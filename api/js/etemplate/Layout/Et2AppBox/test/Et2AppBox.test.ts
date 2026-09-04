import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import "../Et2AppBox";
import {Et2AppBox} from "../Et2AppBox";
import "../../../Et2Filterbox/Et2Filterbox";
import {Et2Filterbox} from "../../../Et2Filterbox/Et2Filterbox";

/**
 * Contract under test:
 * - `handleFilterChange()` re-renders the header when filters change, so the filter button icon
 *   switches between "filter-circle" (no filters) and "filter-circle-fill" (filters set) even
 *   though `rowCount` - the only reactive property the header used to depend on - never changes.
 * - Both event flavours count: "change" from the filterbox itself, and "et2-filter" bubbling up
 *   from a nextmatch (favourite, nextmatch header widget, ...).
 * - A "change" from any other widget in the application is ignored, since "change" bubbles up
 *   from every input below us.
 * - `filterInfo()` examines a copy: the filter values it is handed must come back untouched.
 *
 * Setup strategy:
 * - Mount an `et2-app-box` and stub `getNextmatch` - the filter button is only rendered when
 *   there is a nextmatch, and a real one needs a loaded etemplate.
 * - Append an `et2-filterbox` and shadow its `value` getter, so a "filter" can be set/cleared
 *   without any nextmatch data or server round trip.
 * - Read the icon back out of the rendered `et2-button-icon` in the app box's shadow root, ie.
 *   what the user actually sees, rather than the return value of `filterInfo()`.
 *
 * Pass criteria:
 * - `rowCount` is left at its initial value throughout, so any icon change proves the handler
 *   (not an incidental row-count update) triggered the re-render.
 * - Ignored events must leave both the icon and `requestUpdate()` alone.
 */
describe("Et2AppBox filter indicator", () =>
{
	let element : Et2AppBox;
	let filterbox : Et2Filterbox;
	let filterValues : { [id : string] : any };
	let sandbox : sinon.SinonSandbox;

	// The filter button is the first icon button in the header actions, followed by "reload"
	const filterIcon = () => element.shadowRoot.querySelector("[part='name'] et2-button-icon")
		?.getAttribute("name");

	// handleFilterChange() renders once immediately and again once the filterbox has caught up
	const settled = async() =>
	{
		await element.updateComplete;
		await filterbox.updateComplete;
		await element.updateComplete;
	};

	beforeEach(async() =>
	{
		sandbox = sinon.createSandbox();
		filterValues = {};

		element = await fixture<Et2AppBox>(html`
            <et2-app-box name="test-app"></et2-app-box>`);
		// A nextmatch is what makes the filter button render at all
		element.getNextmatch = () => <any>{id: "nm"};

		filterbox = <Et2Filterbox>document.createElement("et2-filterbox");
		Object.defineProperty(filterbox, "value", {get: () => filterValues, configurable: true});
		element.append(filterbox);
		await settled();
	});

	afterEach(() =>
	{
		sandbox.restore();
		element.remove();
	});

	it("shows an empty filter icon when no filters are set", async() =>
	{
		assert.equal(filterIcon(), "filter-circle", "no filters set");
	});

	it("fills the filter icon on a filterbox change, without a row count change", async() =>
	{
		const rowCount = element.rowCount;

		filterValues = {cat_id: "42"};
		filterbox.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();

		assert.equal(filterIcon(), "filter-circle-fill", "filters set");
		assert.equal(element.rowCount, rowCount, "row count must not be what triggered the update");
	});

	it("empties the filter icon again when the filters are cleared", async() =>
	{
		filterValues = {cat_id: "42"};
		filterbox.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();
		assert.equal(filterIcon(), "filter-circle-fill", "filters set");

		filterValues = {};
		filterbox.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();

		assert.equal(filterIcon(), "filter-circle", "filters cleared");
	});

	it("fills the filter icon on et2-filter from a nextmatch", async() =>
	{
		const nmNode = document.createElement("div");
		nmNode.classList.add("et2_nextmatch");
		element.append(nmNode);

		filterValues = {cat_id: "42"};
		nmNode.dispatchEvent(new CustomEvent("et2-filter", {bubbles: true}));
		await settled();

		assert.equal(filterIcon(), "filter-circle-fill", "filters set by nextmatch");
	});

	it("ignores change events from other inputs", async() =>
	{
		const other = document.createElement("input");
		element.append(other);
		await settled();

		const requestUpdate = sandbox.spy(element, "requestUpdate");

		// Filters "changed" behind our back - an ignored event must not pick that up
		filterValues = {cat_id: "42"};
		other.dispatchEvent(new Event("change", {bubbles: true}));
		await settled();

		assert.isFalse(requestUpdate.called, "change from an unrelated input must not re-render");
		assert.equal(filterIcon(), "filter-circle", "icon must not change");
	});

	it("does not modify the filter values it is given", () =>
	{
		const values = {sort: {id: "cat_id", asc: true}, cat_id: ""};

		const info = element.filterInfo(values);

		assert.deepEqual(values, {sort: {id: "cat_id", asc: true}, cat_id: ""}, "caller's values untouched");
		assert.equal(info.icon, "filter-circle", "sort alone is not a filter");
	});
});
