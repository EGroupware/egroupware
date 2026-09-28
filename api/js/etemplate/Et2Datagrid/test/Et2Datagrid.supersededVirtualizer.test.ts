import {assert} from "@open-wc/testing";
import {virtualizerRef} from "@lit-labs/virtualizer/virtualize.js";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test: once the virtualize() directive has replaced a virtualizer, nothing that
 * replaced instance still has queued can change which rows the grid shows.
 *
 * Why it matters: the directive cannot change an existing virtualizer's layout type, so a layout
 * swap (a fixed-row-height grid moving from the bootstrap FlowLayout to
 * Et2DatagridSparseFlowLayout) throws the virtualizer away and builds a new one.  Its config
 * check is async, so two host renders landing before it resolves both see the OLD instance,
 * both decide the layout changed, and two replacements get built - the second disconnecting the
 * first.  The first one has already queued a DOM update, though, and @lit-labs/virtualizer runs
 * it regardless: detached, it computes an empty range (-1..-1) and announces that on the same
 * tbody the live virtualizer uses.  The directive renders whichever range it heard last, so the
 * grid goes empty, and the live virtualizer never repeats its own range because, as far as it
 * knows, nothing changed.  Permanent, with no user action able to recover it short of a reload.
 * On a quiet machine the two renders rarely overlap; under load they do - this is what made
 * Et2Datagrid.idleSettle.test.ts's control arm fail with "rendered no rows" in CI.
 *
 * Setup strategy: a real, laid-out grid in fixed-row-height mode (the only mode that swaps
 * layouts).  After its first render, the layout swap is triggered by hand and the host is made
 * to render twice synchronously, which reproduces the two-renders-before-the-check-resolves
 * overlap deterministically instead of relying on CPU contention.
 *
 * Pass criteria: the precondition holds (the tbody really did end up on a different virtualizer
 * instance), and after settling, the grid shows its rows.
 */

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url
};
// @ts-ignore
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

// A subclass under its own tag, which also keeps the Et2Datagrid import a runtime one.
class SupersededVirtualizerDatagrid extends Et2Datagrid {}
customElements.define("test-superseded-virtualizer-datagrid", <CustomElementConstructor><unknown>SupersededVirtualizerDatagrid);

function makeDataProvider(prefix = "addressbook")
{
	return {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => prefix,
		normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
		toProviderRowId: (rowId : string) => rowId.replace(new RegExp(`^${prefix}::`), ""),
		refresh: async() => ({rows: [], removedRowIds: []})
	};
}

function wait(ms : number) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

describe("Et2Datagrid ignores a virtualizer it has already replaced", function()
{
	this.timeout(10000);

	let host : HTMLElement;

	afterEach(() => host?.remove());

	it("keeps its rows when a layout swap overlaps a second render", async function()
	{
		host = document.createElement("div");
		host.style.width = "600px";
		host.style.height = "400px";
		document.body.appendChild(host);

		const grid = <Et2Datagrid>document.createElement("test-superseded-virtualizer-datagrid");
		(<any>grid).dataProvider = makeDataProvider();
		grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
		// Fixed row height: the mode that starts on FlowLayout and swaps to the sparse layout.
		(<any>grid)._rowHeightLocked = true;
		const rows = Array.from({length: 24}, (_v, i) => ({id: `row-${i}`, label: `Row ${i}`}));
		(<any>grid).setInitialRows(rows);
		grid.total = rows.length;
		host.appendChild(grid);
		await grid.updateComplete;

		const tbody = <any>(<any>grid)._rowsBody;
		const bootstrapVirtualizer = tbody[virtualizerRef];
		assert.exists(bootstrapVirtualizer, "precondition: the first render created a virtualizer");

		// What _scheduleSparseVirtualizerLayoutActivation() does one frame later, done now so the
		// two renders below are guaranteed to both run against the bootstrap instance.
		(<any>grid)._sparseVirtualizerLayoutActive = true;
		grid.requestUpdate();
		(<any>grid).performUpdate();
		grid.requestUpdate();
		(<any>grid).performUpdate();

		await wait(500);

		assert.notStrictEqual(tbody[virtualizerRef], bootstrapVirtualizer,
			"precondition: the layout swap replaced the bootstrap virtualizer");
		const renderedRows = grid.shadowRoot!.querySelectorAll("[data-row-id]").length;
		assert.isAbove(renderedRows, 0,
			"the grid went empty - a replaced virtualizer's stale range overrode the live one's");
	});
});
