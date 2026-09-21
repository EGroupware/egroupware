import {assert, fixture} from "@open-wc/testing";
import {html} from "lit/static-html.js";
import {Et2Filterbox} from "../Et2Filterbox";
import {widgetSlotTests} from "../../Et2Widget/test/WidgetSlotTests";
import "../../Et2Textbox/Et2Textbox";
import "../../Et2Textbox/Et2Searchbox";
import "../../Et2Select/Select/Et2SelectPriority";
import "../../Layout/Et2Box/Et2Box";
import {assertNoElement} from "../../test/assertDom";

/**
 * Contract under test:
 * - `setFilterTemplate()` swaps template content without timer-based races.
 *
 * Setup strategy:
 * - Render a plain `et2-filterbox`.
 * - Use lightweight HTMLElement templates with async `load()` methods.
 *
 * Pass criteria:
 * - Latest assigned template is the only one attached.
 * - Clearing with `setFilterTemplate(null)` removes the active template.
 *
 * Environment note:
 * - Delay values are small and deterministic; tests wait slightly longer
 *   than the longest configured load delay.
 */

const wait = (ms : number) => new Promise((resolve) => setTimeout(resolve, ms));

describe("Et2Filterbox setFilterTemplate", () =>
{
	it("keeps only the most recent template when loads resolve out of order", async() =>
	{
		const element = new Et2Filterbox();
		document.body.append(element);
		await element.updateComplete;

		const slowTemplate = document.createElement("div") as any;
		slowTemplate.id = "slow-template";
		slowTemplate.load = () => wait(30);

		const fastTemplate = document.createElement("div") as any;
		fastTemplate.id = "fast-template";
		fastTemplate.load = () => wait(1);

		element.setFilterTemplate(slowTemplate);
		element.setFilterTemplate(fastTemplate);
		await wait(50);

		assertNoElement(element.querySelector("#slow-template"), "stale template should not be attached");
		assert.isNotNull(element.querySelector("#fast-template"), "latest template should be attached");
		element.remove();
	});

	it("removes template when template source is cleared", async() =>
	{
		const element = new Et2Filterbox();
		document.body.append(element);
		await element.updateComplete;

		const template = document.createElement("div") as any;
		template.id = "active-template";
		template.load = () => Promise.resolve();

		element.setFilterTemplate(template);
		await wait(5);
		assert.isNotNull(element.querySelector("#active-template"), "template should be attached after load");

		element.setFilterTemplate(null);
		await wait(5);
		assertNoElement(element.querySelector("#active-template"), "template should be removed when cleared");
		element.remove();
	});
});

/**
 * Contract under test:
 * - filter-template.php has no way to know the nextmatch's real current sort, so it
 *   generates `sort[id]`/`sort[asc]` widgets with no value. `setFilterTemplate()` must
 *   seed them from `this._nextmatch.activeFilters.sort` once the template loads, so a
 *   filterbox autoapply doesn't send back a sort object missing its `id`.
 *
 * Setup strategy:
 * - Fake `_nextmatch` exposing only `activeFilters.sort` (the real accessor on both
 *   `Et2Nextmatch` and the legacy `et2_nextmatch` widget).
 * - Fake template element with an `iterateOver()` that hands back widgets by id,
 *   recording whatever value `set_value()` receives.
 *
 * Pass criteria:
 * - `sort[id]`/`sort[asc]` widgets receive the nextmatch's actual id/asc.
 * - Unrelated widgets (e.g. a column filter) are left untouched.
 */
describe("Et2Filterbox sort sync", () =>
{
	it("seeds sort[id]/sort[asc] from the nextmatch's current sort when the filter template loads", async() =>
	{
		const element = new Et2Filterbox();
		document.body.append(element);
		await element.updateComplete;

		(element as any)._nextmatch = {
			activeFilters: {sort: {id: "ts_start", asc: false}},
			getDOMNode : () => null
		};

		const seen : Record<string, any> = {};
		const template = document.createElement("div") as any;
		template.id = "sort-template";
		template.load = () => Promise.resolve();
		template.iterateOver = (callback : Function) =>
		{
			["sort[id]", "sort[asc]", "col_filter[cat_id]"].forEach((id) =>
			{
				callback({id, set_value : (value : any) => { seen[id] = value; }});
			});
		};

		element.setFilterTemplate(template);
		await wait(5);

		assert.equal(seen["sort[id]"], "ts_start", "sort id widget should be seeded from the nextmatch's active sort");
		assert.equal(seen["sort[asc]"], false, "sort direction widget should be seeded from the nextmatch's active sort");
		assert.isUndefined(seen["col_filter[cat_id]"], "unrelated widgets must not be touched by the sort sync");
		element.remove();
	});

	/**
	 * Contract under test:
	 * - Sorting by clicking a column header dispatches `et2-filter` with the new
	 *   `activeFilters.sort`; the filterbox's existing `handleNextmatchFilter` must keep
	 *   the drawer's `sort[id]`/`sort[asc]` widgets in sync with it, not just on initial load.
	 *
	 * Setup strategy:
	 * - Append a fake already-loaded filter template (bypassing setFilterTemplate/load).
	 * - Invoke `handleNextmatchFilter` directly with an `et2-filter`-shaped event detail,
	 *   as the nextmatch itself would dispatch after a column-header sort.
	 *
	 * Pass criteria:
	 * - Both sort widgets receive the new id/asc from `detail.activeFilters.sort`.
	 */
	it("re-syncs sort[id]/sort[asc] via handleNextmatchFilter when the nextmatch re-sorts", async() =>
	{
		const element = new Et2Filterbox();
		document.body.append(element);
		await element.updateComplete;

		const seen : Record<string, any> = {};
		const template = document.createElement("div") as any;
		template.id = "sort-template";
		template.iterateOver = (callback : Function) =>
		{
			["sort[id]", "sort[asc]"].forEach((id) =>
			{
				callback({id, set_value : (value : any) => { seen[id] = value; }});
			});
		};
		element.append(template);

		(element as any).handleNextmatchFilter({detail: {activeFilters: {sort: {id: "ts_id", asc: true}}}});

		assert.equal(seen["sort[id]"], "ts_id", "column-header sort should update the sort id widget");
		assert.equal(seen["sort[asc]"], true, "column-header sort should update the sort direction widget");
		element.remove();
	});
});

/**
 * Contract under test:
 * - `clearable` shows a clear button when, and only when, at least one filter has something in
 *   it, and the button empties those filters.
 *
 * Setup strategy:
 * - Slot real input widgets in as markup, which is what a hand-written filterbox looks like
 *   (see Et2Filterbox.md).  Deliberately no eTemplate around them: this content has no instance
 *   manager and no widget tree, which is the case `value` cannot see and the clear button used to
 *   fall through on, leaving the button permanently hidden.
 *
 * Pass criteria:
 * - No button while every filter is empty, button once one has a value, gone again once cleared.
 * - Clicking it actually empties the widgets.
 * - The nextmatch's hidden sort widgets are neither counted as a filter nor wiped by a clear.
 */
describe("Et2Filterbox clear button", () =>
{
	const clearButton = (element : Et2Filterbox) : any =>
		Array.from(element.shadowRoot.querySelectorAll("et2-button")).find((button : any) => button.label == "Clear");

	it("stays hidden while every filter is empty", async() =>
	{
		const element : Et2Filterbox = await fixture(html`
            <et2-filterbox clearable>
                <et2-textbox id="search"></et2-textbox>
            </et2-filterbox>`);
		await element.updateComplete;

		assertNoElement(clearButton(element), "nothing set, so there is nothing to clear");
	});

	it("appears for a filter that came in with a value", async() =>
	{
		const element : Et2Filterbox = await fixture(html`
            <et2-filterbox clearable>
                <et2-textbox id="search" value="hello"></et2-textbox>
            </et2-filterbox>`);
		await element.updateComplete;

		assert.isDefined(clearButton(element), "a filter has a value, so it can be cleared");
	});

	it("empties the filters and goes away again when clicked", async() =>
	{
		const element : Et2Filterbox = await fixture(html`
            <et2-filterbox clearable>
                <et2-textbox id="search" value="hello"></et2-textbox>
                <et2-textbox id="other" value="world"></et2-textbox>
            </et2-filterbox>`);
		await element.updateComplete;

		clearButton(element).click();
		await element.updateComplete;

		assert.equal((<any>element.querySelector("#search")).get_value(), "", "clear should empty the filter");
		assert.equal((<any>element.querySelector("#other")).get_value(), "", "clear should empty every filter");
		assertNoElement(clearButton(element), "with nothing left set, the button should go away");
	});

	/**
	 * The "Clearable" example in Et2Filterbox.md, exactly as a reader gets it.  A select needs an
	 * emptyLabel to have an empty value to go back to (without one Et2Select falls back to its
	 * first option instead of clearing), which is the part of that example most easily lost.
	 */
	it("behaves as the documented example says it does", async() =>
	{
		const element : Et2Filterbox = await fixture(html`
            <et2-filterbox id="filterbox-clearable" clearable>
                <et2-vbox>
                    <et2-searchbox id="search" label="Search" class="et2-fixed-label"></et2-searchbox>
                    <et2-select-priority id="priority" label="Priority" emptyLabel="Any" value="3"
                                         class="et2-fixed-label"></et2-select-priority>
                </et2-vbox>
            </et2-filterbox>`);
		await element.updateComplete;

		assert.isDefined(clearButton(element), "the example starts with priority set, so it shows the button");

		clearButton(element).click();
		await element.updateComplete;

		assert.equal((<any>element.querySelector("#priority")).get_value(), "",
			"an emptyLabel gives the select an empty value to clear to");
		assertNoElement(clearButton(element), "cleared, so the button goes away again");
	});

	it("leaves the nextmatch's sort out of it", async() =>
	{
		const element : Et2Filterbox = await fixture(html`
            <et2-filterbox clearable>
                <et2-textbox id="sort[id]" value="ts_start"></et2-textbox>
                <et2-textbox id="search" value="hello"></et2-textbox>
            </et2-filterbox>`);
		await element.updateComplete;

		clearButton(element).click();
		await element.updateComplete;

		assert.equal((<any>element.querySelector("#search")).get_value(), "", "clear should empty the filter");
		assert.equal((<any>element.querySelector("[id='sort[id]']")).get_value(), "ts_start",
			"sort order is not a filter - clearing must not wipe the ORDER BY");
		assertNoElement(clearButton(element), "a sort on its own is not something to clear");
	});
});

// value is only meaningful once a real filter template (with a live et2 instance manager) is
// attached - the fake-template setup used above doesn't give it one - so the full
// inputBasicTests() value/required contract doesn't apply here. Its label/help-text/prefix/suffix
// slots (the exact 4 names in its own hasSlotController) still follow the normal part= convention
// and are worth checking on their own.
// skipLabelFixed: Et2Filterbox.styles.ts sets its own --label-width (min(20rem, 30%), 100% on
// narrow screens) for its drawer layout, overriding .et2-label-fixed's generic 8em default - the
// CSS hook itself still works, this widget just legitimately customizes the variable it reads.
widgetSlotTests(async() =>
{
	const element = new Et2Filterbox();
	document.body.append(element);
	await element.updateComplete;
	return element;
}, ["label", "help-text", "prefix", "suffix"], {skipLabelFixed: true});
