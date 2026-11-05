import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// Et2Datagrid only names these in its template, so nothing registers them in a test bundle
import "@shoelace-style/shoelace/dist/components/dropdown/dropdown.js";
import "@shoelace-style/shoelace/dist/components/menu/menu.js";
import "@shoelace-style/shoelace/dist/components/menu-item/menu-item.js";
import "@shoelace-style/shoelace/dist/components/icon/icon.js";
import "@shoelace-style/shoelace/dist/components/popup/popup.js";
import {Et2Datagrid} from "../Et2Datagrid";
import {assertNoElement} from "../../test/assertDom";

/**
 * Contract under test: the header's column action offers the owner's filters.
 *
 * - Without `filterAction` it stays the plain "select columns" button it always was, and
 *   `filterActive` has no effect.
 * - With `filterAction` it is a menu holding "select columns" (unless `noColumnSelection`) and
 *   "Filters"; choosing "Filters" fires `et2-datagrid-filters`, choosing "select columns" opens the
 *   column selection.
 * - With `filterAction` and `filterActive` the action carries a "filters are set" dot.
 *
 * Setup strategy: an empty datagrid with one column, so its header renders the column action.
 * Menu choices are made by clicking the menu items, which is what makes sl-menu emit `sl-select`.
 *
 * Pass criteria are the rendered header elements and the events / calls each choice produces.
 */

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url
};
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

async function createDatagrid(properties : Partial<Et2Datagrid> = {}) : Promise<Et2Datagrid>
{
	const el = new Et2Datagrid();
	el.dataProvider = {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => "addressbook",
		normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
		toProviderRowId: (rowId : string) => rowId,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	el.columns = [{key: "name", title: "Name"}] as any;
	el.templateData = {columns: el.columns} as any;
	Object.assign(el, properties);
	document.body.append(el);
	await el.updateComplete;
	return el;
}

const columnAction = (el : Et2Datagrid) => el.shadowRoot!.querySelector(".dg-colselection") as HTMLElement | null;
const menuItem = (el : Et2Datagrid, value : string) =>
	el.shadowRoot!.querySelector(`.dg-colselection sl-menu-item[value='${value}']`) as HTMLElement | null;
const indicator = (el : Et2Datagrid) => el.shadowRoot!.querySelector(".dg-colselection__indicator");

describe("Et2Datagrid column action filters entry", () =>
{
	it("stays a plain column selection button without filterAction", async() =>
	{
		const el = await createDatagrid({filterActive: true});

		const action = columnAction(el);
		assert.isNotNull(action, "column action should render");
		assert.isNotNull(action!.querySelector(":scope > et2-button-icon"), "column action should be a plain button");
		assertNoElement(action!.querySelector("sl-dropdown"), "column action should not be a menu without filterAction");
		assertNoElement(indicator(el), "filterActive alone should not mark the column action");
		el.remove();
	});

	it("offers columns and filters, and marks active filters, with filterAction", async() =>
	{
		const el = await createDatagrid({filterAction: true});

		assert.isNotNull(columnAction(el)?.querySelector("sl-dropdown"), "column action should be a menu");
		assert.equal(columnAction(el)?.querySelector("et2-button-icon[slot='trigger']")?.getAttribute("image"),
			"three-dots-vertical", "the menu opens from ⋮, not the column selection icon");
		assert.isNotNull(menuItem(el, "columns"), "menu should offer the column selection");
		assert.isNotNull(menuItem(el, "filters"), "menu should offer the filters");
		assertNoElement(indicator(el), "no filters set, so no mark");

		el.filterActive = true;
		await el.updateComplete;
		assert.isNotNull(indicator(el), "filters set, so the column action should be marked");

		el.filterActive = false;
		await el.updateComplete;
		assertNoElement(indicator(el), "mark should go away once the filters are cleared");
		el.remove();
	});

	it("offers only the filters when column selection is off", async() =>
	{
		const el = await createDatagrid({filterAction: true, noColumnSelection: true});

		assertNoElement(menuItem(el, "columns"), "column selection is off, so the menu should not offer it");
		assert.isNotNull(menuItem(el, "filters"), "menu should still offer the filters");

		el.filterAction = false;
		await el.updateComplete;
		assertNoElement(columnAction(el), "with neither, there is no column action at all");
		el.remove();
	});

	it("fires et2-datagrid-filters for Filters and opens the column selection for Columns", async() =>
	{
		const el = await createDatagrid({filterAction: true});
		const openColumnSelection = sinon.stub(el, "openColumnSelection").resolves();
		let filterEvents = 0;
		el.addEventListener("et2-datagrid-filters", () => filterEvents++);
		let selectsOutside = 0;
		const countOutside = () => selectsOutside++;
		document.addEventListener("sl-select", countOutside);

		try
		{
			const dropdown = columnAction(el)!.querySelector("sl-dropdown") as any;
			await dropdown.show();
			menuItem(el, "filters")!.click();
			await dropdown.updateComplete;
			assert.isFalse(dropdown.open, "choosing an entry should close the menu");
			assert.equal(filterEvents, 1, "Filters should fire et2-datagrid-filters");
			assert.isFalse(openColumnSelection.called, "Filters should not open the column selection");

			menuItem(el, "columns")!.click();
			assert.isTrue(openColumnSelection.calledOnce, "select columns should open the column selection");
			assert.equal(filterEvents, 1, "select columns should not fire et2-datagrid-filters");
			assert.equal(selectsOutside, 0, "the menu's sl-select should not leave the datagrid");
		}
		finally
		{
			document.removeEventListener("sl-select", countOutside);
			openColumnSelection.restore();
			el.remove();
		}
	});
});
