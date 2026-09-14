import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract: the grid owns row navigation from the keyboard.  Et2NextmatchActionController
 * deliberately hands these keys straight through (its GRID_OWNED_KEYS list), so this
 * handler is the only thing moving the active row, and a regression here silently costs
 * every app arrow-key navigation, Space-toggle and Ctrl+A - with no error anywhere.
 *
 * Setup: a grid fed a fixed row set directly, driving Et2Datagrid._handleTableKeydown()
 * with real KeyboardEvents.  No rendering: the handler works off the row model, and the
 * focus call it makes is a no-op while nothing is stamped.
 *
 * Pass: each key moves the active row where expected, plain navigation replaces the
 * selection while Ctrl leaves it alone and Shift extends it, and every navigation key is
 * consumed so the page does not also scroll.
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
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

function createDatagrid(rowCount : number) : Et2Datagrid
{
	const rows = Array.from({length: rowCount}, (_value, index) => ({id: `row-${index}`, label: `Row ${index}`}));
	const grid = new Et2Datagrid();
	grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
	grid.templateData = {columns: grid.columns} as any;
	grid.dataProvider = {
		fetchPage: async(start : number, pageSize : number) => ({rows: rows.slice(start, start + pageSize), total: rows.length}),
		getDataStorePrefix: () => "addressbook",
		normalizeRowId: (rowId : string | number) => String(rowId ?? ""),
		toProviderRowId: (rowId : string) => rowId,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	grid.setInitialRows(rows);
	grid.total = rows.length;
	grid.selectionMode = "multiple";
	return grid;
}

const press = (grid : Et2Datagrid, key : string, modifiers : Partial<KeyboardEventInit> = {}) =>
{
	const event = new KeyboardEvent("keydown", {key, cancelable: true, ...modifiers});
	(grid as any)._handleTableKeydown(event);
	return event;
};

const selection = (grid : Et2Datagrid) => Array.from((grid as any).selectedRowIds);

describe("Et2Datagrid keyboard navigation", () =>
{
	it("moves the active row with ArrowDown/ArrowUp and takes the selection along", () =>
	{
		const grid = createDatagrid(5);
		grid.selectSingleRow("row-1");

		press(grid, "ArrowDown");
		assert.strictEqual(grid.getActiveRowId(), "row-2", "ArrowDown should move one row down");
		assert.sameMembers(selection(grid), ["row-2"], "plain navigation replaces the selection, like a plain click");

		press(grid, "ArrowUp");
		assert.strictEqual(grid.getActiveRowId(), "row-1", "ArrowUp should move one row up");
		assert.sameMembers(selection(grid), ["row-1"], "selection should follow the keyboard cursor");
	});

	it("stops at the first and last row instead of wrapping", () =>
	{
		const grid = createDatagrid(3);
		grid.selectSingleRow("row-0");
		press(grid, "ArrowUp");
		assert.strictEqual(grid.getActiveRowId(), "row-0", "ArrowUp on the first row stays put");

		grid.selectSingleRow("row-2");
		press(grid, "ArrowDown");
		assert.strictEqual(grid.getActiveRowId(), "row-2", "ArrowDown on the last row stays put");
	});

	it("jumps to the first and last row with Home/End", () =>
	{
		const grid = createDatagrid(6);
		grid.selectSingleRow("row-3");

		press(grid, "End");
		assert.strictEqual(grid.getActiveRowId(), "row-5", "End should go to the last row");
		press(grid, "Home");
		assert.strictEqual(grid.getActiveRowId(), "row-0", "Home should go to the first row");
	});

	it("toggles the active row with Space without moving it", () =>
	{
		const grid = createDatagrid(4);
		grid.selectSingleRow("row-2");

		press(grid, " ");
		assert.sameMembers(selection(grid), [], "Space should unselect an already selected active row");
		assert.strictEqual(grid.getActiveRowId(), "row-2", "Space must not move the active row");

		press(grid, " ");
		assert.sameMembers(selection(grid), ["row-2"], "Space should select the active row again");
	});

	it("extends the selection with Shift+Arrow and leaves it alone with Ctrl+Arrow", () =>
	{
		const grid = createDatagrid(5);
		grid.selectSingleRow("row-1");

		press(grid, "ArrowDown", {shiftKey: true});
		press(grid, "ArrowDown", {shiftKey: true});
		assert.strictEqual(grid.getActiveRowId(), "row-3", "Shift+ArrowDown should still move the active row");
		assert.sameMembers(selection(grid), ["row-1", "row-2", "row-3"], "Shift should extend from the anchor row");

		press(grid, "ArrowDown", {ctrlKey: true});
		assert.strictEqual(grid.getActiveRowId(), "row-4", "Ctrl+ArrowDown should move the active row");
		assert.sameMembers(selection(grid), ["row-1", "row-2", "row-3"],
			"Ctrl+Arrow moves the cursor without disturbing an existing selection");
	});

	it("consumes navigation keys so the page does not scroll as well", () =>
	{
		const grid = createDatagrid(5);
		grid.selectSingleRow("row-1");

		for(const key of ["ArrowDown", "ArrowUp", "PageDown", "PageUp", "Home", "End", " "])
		{
			assert.isTrue(press(grid, key).defaultPrevented, `${key} should be consumed by the grid`);
		}
	});

	it("ignores navigation keys in an empty grid that has not loaded yet", () =>
	{
		const grid = createDatagrid(0);
		grid.total = null;
		assert.isFalse(press(grid, "ArrowDown").defaultPrevented,
			"with nothing loaded the key must stay available to whatever else wants it");
	});
});
