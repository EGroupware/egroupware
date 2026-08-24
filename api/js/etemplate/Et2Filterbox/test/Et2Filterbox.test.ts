import {assert} from "@open-wc/testing";
import {Et2Filterbox} from "../Et2Filterbox";

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

		assert.isNull(element.querySelector("#slow-template"), "stale template should not be attached");
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
		assert.isNull(element.querySelector("#active-template"), "template should be removed when cleared");
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
