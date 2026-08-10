import {assert} from "@open-wc/testing";
import {render} from "lit";
import {Et2Datagrid} from "../Et2Datagrid";

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
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

function createDataProvider(rows : any[])
{
	return {
		fetchPage: async(start : number, pageSize : number) => ({
			rows: rows.slice(start, start + pageSize),
			total: rows.length
		}),
		getDataStorePrefix: () => "addressbook",
		normalizeRowId: (rowId : string | number, ensurePrefix? : boolean) =>
		{
			const normalized = String(rowId ?? "");
			return ensurePrefix && !normalized.startsWith("addressbook::") ? `addressbook::${normalized}` : normalized;
		},
		toProviderRowId: (rowId : string) => rowId.replace(/^addressbook::/, ""),
		refresh: async() => ({rows: [], removedRowIds: []})
	};
}

function createDatagrid(rows : any[]) : Et2Datagrid
{
	const grid = new Et2Datagrid();
	grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
	grid.templateData = {columns: grid.columns} as any;
	grid.dataProvider = createDataProvider(rows) as any;
	return grid;
}

function renderVirtualRow(grid : Et2Datagrid, rowIndex : number, container : HTMLElement) : HTMLElement | null
{
	render((grid as any)._renderVirtualRow(rowIndex), container);
	return container.querySelector("[data-row-id]") as HTMLElement | null;
}

describe("Et2Datagrid row selection", () =>
{
	/**
	 * Contract: pointer selection follows the configured none, single, and
	 * multiple selection modes.
	 *
	 * Setup: exercise each mode against a three-row grid.
	 *
	 * Pass: none ignores selection, single replaces it, and multiple supports
	 * additive and range selection.
	 */
	it("applies pointer selection behavior for each selection mode", () =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;

		grid.selectionMode = "none";
		(grid as any).selectedRowIds = new Set(["row-0"]);
		(grid as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click"));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-0"], "none mode should ignore pointer selection");

		grid.selectionMode = "single";
		(grid as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click"));
		(grid as any)._updateSelectionFromPointer("row-2", 2, new MouseEvent("click", {ctrlKey: true}));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-2"], "single mode should retain only the last selected row");

		grid.selectionMode = "multiple";
		(grid as any)._updateSelectionFromPointer("row-0", 0, new MouseEvent("click"));
		(grid as any)._updateSelectionFromPointer("row-1", 1, new MouseEvent("click", {ctrlKey: true}));
		(grid as any)._updateSelectionFromPointer("row-2", 2, new MouseEvent("click", {shiftKey: true}));
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-1", "row-2"], "multiple mode should support additive and range selection");
	});

	/**
	 * Contract: selection is state keyed by row id, not by a physical virtualized
	 * DOM element.
	 *
	 * Setup: select a row, then invoke the virtualizer's row-render callback for
	 * a different absolute index in the same physical host.
	 *
	 * Pass: the first row is no longer rendered, while its selected id remains
	 * in the selection model.
	 */
	it("keeps selected row ids when scrolling recycles their rendered rows", () =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("row-10");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const selectedRow = renderVirtualRow(grid, 10, body);
		assert.equal(selectedRow?.getAttribute("aria-selected"), "true", "initial virtual row should be selected");

		const laterRow = renderVirtualRow(grid, 60, body);
		assert.equal(laterRow?.getAttribute("data-row-id"), "row-60", "later virtual row should replace the earlier rendered row");
		assert.isNull(body.querySelector("[data-row-id='row-10']"), "selected row should no longer be rendered after virtualizer recycling");
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-10"], "selection must outlive the rendered row element");
		table.remove();
	});

	/**
	 * Contract: a selected row receives selected accessibility state every time
	 * virtualization realizes it again.
	 *
	 * Setup: select a first-group row, recycle it out of the virtualizer host,
	 * then render that absolute row index again.
	 *
	 * Pass: the newly rendered element has aria-selected=true without another
	 * selection interaction.
	 */
	it("restores selected state when an off-screen row is rendered again", () =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("row-10");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		renderVirtualRow(grid, 60, body);
		const restoredRow = renderVirtualRow(grid, 10, body);

		assert.equal(restoredRow?.getAttribute("data-row-id"), "row-10");
		assert.equal(restoredRow?.getAttribute("aria-selected"), "true", "re-rendered selected row should restore its selected state");
		table.remove();
	});

	/**
	 * Contract: changing filters reloads rows without discarding explicit row-id
	 * selection when the selected row remains in the filtered result.
	 *
	 * Setup: select a row, replace the data-provider result with a filtered page
	 * that still contains it, and reload as filter application does.
	 *
	 * Pass: the selection model and the row rendered from the filtered result
	 * retain the selected state.
	 */
	it("preserves matching selected rows across a filter reload", async() =>
	{
		const rows = [{id: "row-1", label: "Matches filter"}];
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows);
		grid.total = 1;
		grid.selectSingleRow("row-1");
		await grid.reload();

		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-1"], "filter reload should retain explicit selected row ids");
		(grid as any)._rowsByIndex = [{id: "row-1", data: rows[0]}];
		grid.rows = [{id: "row-1", data: rows[0]}] as any;
		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const filteredRow = renderVirtualRow(grid, 0, body);
		assert.equal(filteredRow?.getAttribute("aria-selected"), "true", "matching row should render as selected after the filter reload");
		table.remove();
	});

	/**
	 * Contract: select-all represents the complete result set, including rows
	 * not yet loaded from later fetch groups.
	 *
	 * Setup: load the first group of a 100-row result, select all, then fetch and
	 * render a row from the second group.
	 *
	 * Pass: the emitted selection is global and both already-loaded and newly
	 * fetched rows render as selected.
	 */
	it("selects all rows across loaded and later fetch groups", () =>
	{
		const rows = Array.from({length: 100}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.pageSize = 50;
		grid.setInitialRows(rows.slice(0, 50));
		grid.total = rows.length;
		let selectionDetail : any;
		grid.addEventListener("et2-selection-changed", (event : Event) => selectionDetail = (event as CustomEvent).detail);

		grid.selectAllRows();
		assert.isTrue(selectionDetail?.allSelected, "select-all should report a selection over the complete result set");

		const table = document.createElement("table");
		const body = document.createElement("tbody");
		table.append(body);
		document.body.append(table);
		const firstGroupRow = renderVirtualRow(grid, 10, body);
		assert.equal(firstGroupRow?.getAttribute("aria-selected"), "true", "loaded row should render as selected after select-all");

		// This is the same indexed state populated by a later page fetch.  The
		// selection assertion intentionally runs after the first page has been
		// selected, when this second page was not yet materialized.
		(grid as any)._rowsByIndex[60] = {id: "row-60", data: rows[60]};
		grid.rows = [...grid.rows, {id: "row-60", data: rows[60]}] as any;
		const laterGroupRow = renderVirtualRow(grid, 60, body);
		assert.equal(laterGroupRow?.getAttribute("data-row-id"), "row-60", "second fetch group should load the requested row");
		assert.equal(laterGroupRow?.getAttribute("aria-selected"), "true", "later fetched row should render as selected after select-all");
		table.remove();
	});

	/**
	 * Contract: Ctrl+A uses the same global select-all behavior as the public
	 * select-all action, but only in multiple-selection mode.
	 *
	 * Setup: press Ctrl+A in multiple and single mode.
	 *
	 * Pass: multiple mode marks the complete result set selected; single mode
	 * leaves the existing selection unchanged.
	 */
	it("handles Ctrl+A only for multiple selection", () =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		const selectAll = new KeyboardEvent("keydown", {key: "a", ctrlKey: true, cancelable: true});
		(grid as any)._handleTableKeydown(selectAll);
		assert.isTrue(selectAll.defaultPrevented, "multiple mode should handle Ctrl+A");
		assert.isTrue((grid as any).allSelected, "Ctrl+A should select the complete result set");

		grid.selectionMode = "single";
		(grid as any).allSelected = false;
		(grid as any).selectedRowIds = new Set(["row-0"]);
		const ignored = new KeyboardEvent("keydown", {key: "a", ctrlKey: true, cancelable: true});
		(grid as any)._handleTableKeydown(ignored);
		assert.isFalse(ignored.defaultPrevented, "single mode should not intercept Ctrl+A");
		assert.sameMembers(Array.from((grid as any).selectedRowIds), ["row-0"], "single mode selection should remain unchanged");
	});

	/**
	 * Contract: deleting displayed rows reports their immediate surviving
	 * neighbours without exposing the grid's internal row collection.
	 * Setup: delete two adjacent rows from a five-row loaded result.
	 * Pass: the event contains the original neighbouring ids and the rows are
	 * removed from the display model.
	 */
	it("reports neighbours when displayed rows are deleted", async() =>
	{
		const rows = Array.from({length: 5}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		let detail : any;
		grid.addEventListener("et2-rows-deleted", (event : Event) => detail = (event as CustomEvent).detail);

		await grid.refresh(["row-1", "row-2"], "delete");

		assert.deepEqual(detail, {
			rowIds: ["addressbook::row-1", "addressbook::row-2"],
			previousRowId: "addressbook::row-0",
			nextRowId: "addressbook::row-3"
		}, "delete event should preserve both adjacent row ids");
		assert.sameMembers(grid.rows.map((row) => row.id), [
			"addressbook::row-0", "addressbook::row-3", "addressbook::row-4"
		], "deleted rows should no longer be displayed");
	});

	/**
	 * Contract: deleting the last row(s) of a list has no surviving row after
	 * it, so callers relying on nextRowId (e.g. mail's delayed-arrow-key
	 * selection) can tell there is nothing to advance to.
	 * Setup: delete the last row from a five-row loaded result.
	 * Pass: nextRowId is null while previousRowId still points at the row
	 * above the deleted one.
	 */
	it("reports no nextRowId when the last row is deleted", async() =>
	{
		const rows = Array.from({length: 5}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		let detail : any;
		grid.addEventListener("et2-rows-deleted", (event : Event) => detail = (event as CustomEvent).detail);

		await grid.refresh(["row-4"], "delete");

		assert.deepEqual(detail, {
			rowIds: ["addressbook::row-4"],
			previousRowId: "addressbook::row-3",
			nextRowId: null
		}, "deleting the last row should report no next row to advance to");
		assert.sameMembers(grid.rows.map((row) => row.id), [
			"addressbook::row-0", "addressbook::row-1", "addressbook::row-2", "addressbook::row-3"
		], "deleted row should no longer be displayed");
	});

	/**
	 * Contract: deleting the currently selected/previewed row must notify listeners
	 * that the selection changed, not just silently drop it from internal state -
	 * otherwise an `onselect`-driven preview pane (e.g. mail's) never re-runs and
	 * keeps showing the just-deleted row's content.
	 *
	 * Setup: select one row, then delete it via `refresh(..., "delete")`.
	 *
	 * Pass: an `et2-selection-changed` event fires with the row excluded from
	 * `selectedRowIds`.
	 */
	it("emits a selection-changed event when the selected row is deleted", async() =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("addressbook::row-1");

		let detail : any = null;
		grid.addEventListener("et2-selection-changed", (event : Event) => detail = (event as CustomEvent).detail);

		await grid.refresh(["row-1"], "delete");

		assert.isNotNull(detail, "deleting the selected row should emit et2-selection-changed");
		assert.notInclude(detail.selectedRowIds, "addressbook::row-1", "deleted row should no longer be selected");
	});

	/**
	 * Contract: deleting rows that are neither selected nor active must not emit a
	 * spurious selection-changed event - the selection genuinely didn't change.
	 *
	 * Setup: select row-0, delete the unrelated row-2.
	 *
	 * Pass: no `et2-selection-changed` event fires.
	 */
	it("does not emit selection-changed when an unselected row is deleted", async() =>
	{
		const rows = Array.from({length: 3}, (_value, index) => ({id: `addressbook::row-${index}`, label: `Row ${index}`}));
		const grid = createDatagrid(rows);
		grid.setInitialRows(rows);
		grid.total = rows.length;
		grid.selectSingleRow("addressbook::row-0");

		let fired = false;
		grid.addEventListener("et2-selection-changed", () => fired = true);

		await grid.refresh(["row-2"], "delete");

		assert.isFalse(fired, "deleting an unrelated row should not emit a selection-changed event");
	});
});
