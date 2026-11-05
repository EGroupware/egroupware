import {assert} from "@open-wc/testing";
// Et2Nextmatch / Et2Datagrid only name these in their templates, so nothing registers them in a test bundle
import "@shoelace-style/shoelace/dist/components/drawer/drawer.js";
import "@shoelace-style/shoelace/dist/components/dropdown/dropdown.js";
import "@shoelace-style/shoelace/dist/components/menu/menu.js";
import "@shoelace-style/shoelace/dist/components/menu-item/menu-item.js";
import "@shoelace-style/shoelace/dist/components/icon/icon.js";
import "@shoelace-style/shoelace/dist/components/popup/popup.js";
import {Et2Nextmatch} from "../Et2Nextmatch";
// Et2Nextmatch only uses it as a type; etemplate2.ts is what registers it in the real page
import "../../Et2Filterbox/Et2Filterbox";
import type {Et2Datagrid} from "../../Et2Datagrid/Et2Datagrid";

/**
 * Contract under test: where a nextmatch's filterbox goes, and how it is opened.
 *
 * - With an ancestor offering a filter slot (egw-app's drawer) the filterbox goes there, and the
 *   nextmatch adds nothing of its own.
 * - Without one (a popup) the filterbox goes into the nextmatch's own contained drawer; the
 *   datagrid's column action gets a "Filters" entry (`filterAction`) that toggles it, Escape
 *   inside it closes it, and the column action is marked (`filterActive`) while a filter other
 *   than sort is set.  While one is, the drawer header offers "Clear filters", which blanks every
 *   filter by name (keeping sort) - the filterbox has no Clear button of its own.
 *
 * Setup strategy: a real et2-filterbox fed a ready template element (so nothing is fetched), with
 * the nextmatch either in a plain div or inside a host whose shadow root has a filter slot.
 * The datagrid's "Filters" choice is simulated by dispatching its `et2-datagrid-filters` event -
 * the menu itself is covered by Et2Datagrid.filterAction.test.ts.
 *
 * Pass criteria are the filterbox's parent, the datagrid's `filterAction` / `filterActive` and the
 * drawer's `open`.
 */

const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: () => "",
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	dataFetch: (_execId, _request, _filters, _widgetId, callback) => callback({order: [], total: 0}),
	dataRegisterUID: (_uid, callback) => callback({}, "row::1"),
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

/** Stands in for egw-app: a shadow root offering slot="filter" */
class FilterSlotHost extends HTMLElement
{
	constructor()
	{
		super();
		this.attachShadow({mode: "open"}).innerHTML = "<slot></slot><slot name='filter'></slot>";
	}
}
customElements.define("test-filter-slot-host", FilterSlotHost);

const ticks = async(count = 4) =>
{
	for(let i = 0; i < count; i++)
	{
		await Promise.resolve();
	}
};

async function createNextmatch(container : HTMLElement) : Promise<Et2Nextmatch>
{
	const el = new Et2Nextmatch();
	const template = document.createElement("div") as any;
	template.id = "nm-filter-template";
	template.load = () => Promise.resolve();
	el.filterTemplate = template;
	container.append(el);
	await el.updateComplete;
	await ticks();
	await el.updateComplete;
	return el;
}

const datagridOf = (el : Et2Nextmatch) => el.shadowRoot!.querySelector("et2-datagrid") as Et2Datagrid;
const filterboxOf = () => document.querySelector("et2-filterbox") as HTMLElement & {
	clearable : boolean, value : any, applyFilters() : void
} | null;
const clearActionOf = (el : Et2Nextmatch) =>
	el.shadowRoot!.querySelector(".et2-filter-drawer [slot='header-actions'][name='x-circle-fill']") as HTMLElement | null;

describe("Et2Nextmatch own filter drawer", () =>
{
	let container : HTMLElement;

	beforeEach(() =>
	{
		container = document.createElement("div");
		document.body.append(container);
	});

	afterEach(() =>
	{
		container.remove();
	});

	it("leaves the filterbox to an ancestor's filter slot", async() =>
	{
		const host = document.createElement("test-filter-slot-host");
		container.append(host);
		const el = await createNextmatch(host);

		assert.strictEqual(filterboxOf()?.parentElement, host, "filterbox should go to the ancestor offering a filter slot");
		assert.isFalse(datagridOf(el).filterAction, "no Filters entry when the app shell has the filters");
		assert.isNull(el.filtersDrawer, "no own drawer when the app shell has the filters");
	});

	it("keeps the filterbox in its own drawer, opened from the column action, without a filter slot", async() =>
	{
		const el = await createNextmatch(container);
		const filterbox = filterboxOf();

		assert.strictEqual(filterbox?.parentElement, el, "filterbox should stay with the nextmatch");
		assert.equal(filterbox?.getAttribute("slot"), "filter", "filterbox should be slotted into the drawer");
		assert.isFalse(filterbox?.clearable, "the drawer header has the Clear action, so the filterbox needs none");
		assert.isTrue(datagridOf(el).filterAction, "column action should offer the filters");

		const drawer = el.filtersDrawer;
		assert.isNotNull(drawer, "nextmatch should render its own drawer");
		assert.isFalse(drawer.open, "drawer should start closed");

		datagridOf(el).dispatchEvent(new CustomEvent("et2-datagrid-filters"));
		await el.updateComplete;
		assert.isTrue(drawer.open, "Filters should open the drawer");

		filterbox!.dispatchEvent(new KeyboardEvent("keydown", {key: "Escape", bubbles: true, composed: true}));
		await el.updateComplete;
		assert.isFalse(drawer.open, "Escape inside the filters should close the drawer");

		datagridOf(el).dispatchEvent(new CustomEvent("et2-datagrid-filters"));
		await el.updateComplete;
		datagridOf(el).dispatchEvent(new CustomEvent("et2-datagrid-filters"));
		await el.updateComplete;
		assert.isFalse(drawer.open, "Filters again should close the drawer");
	});

	it("marks the column action while a filter other than sort is set", async() =>
	{
		const el = await createNextmatch(container);
		const datagrid = datagridOf(el);
		assert.isFalse(datagrid.filterActive, "nothing filtered yet");

		el.applyFilters({sort: {id: "name", asc: true}}, {reload: false});
		await el.updateComplete;
		assert.isFalse(datagrid.filterActive, "sorting is not filtering");

		el.applyFilters({search: "smith"}, {reload: false});
		await el.updateComplete;
		assert.isTrue(datagrid.filterActive, "a search should mark the column action");

		el.applyFilters({search: ""}, {reload: false});
		await el.updateComplete;
		assert.isFalse(datagrid.filterActive, "clearing the search should remove the mark");

		el.applyFilters({col_filter: {owner: "5"}}, {reload: false});
		await el.updateComplete;
		assert.isTrue(datagrid.filterActive, "a column filter should mark the column action");
	});

	it("offers Clear filters in the drawer header while a filter is set", async() =>
	{
		const el = await createNextmatch(container);
		assert.isNull(clearActionOf(el), "nothing filtered, nothing to clear");

		// user_ts stands in for a date range: emptied, it is missing from the filterbox's value
		// rather than "", so clearing has to name it
		el.applyFilters({search: "smith", col_filter: {user_ts: {from: "2026-10-06"}}, sort: {id: "name", asc: true}},
			{reload: false});
		await el.updateComplete;
		const clear = clearActionOf(el);
		assert.isNotNull(clear, "a filter should offer Clear filters in the drawer header");

		clear!.click();
		await el.updateComplete;
		const filters = el.activeFilters as any;
		assert.isNotOk(filters.search, "Clear filters should empty the search");
		assert.isUndefined(filters.col_filter?.user_ts, "Clear filters should drop a column filter the filterbox does not report");
		assert.deepEqual(filters.sort, {id: "name", asc: true}, "Clear filters should keep the sort order");
		assert.isFalse(datagridOf(el).filterActive, "nothing should be marked as filtered any more");
		assert.isNull(clearActionOf(el), "and there should be nothing left to clear");
	});
});
