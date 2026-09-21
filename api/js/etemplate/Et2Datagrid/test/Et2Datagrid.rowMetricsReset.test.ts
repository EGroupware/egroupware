import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test:
 * - Starting a new query drops the virtualizer's per-row height measurements.
 *
 * Why it matters: `@lit-labs/virtualizer`'s FlowLayout caches measured row heights by *index*
 * and discards them only when the viewport width changes. The next result set puts entirely
 * different rows at those same indices, so the scroll extent for the new query ends up derived
 * from the previous query's row heights - live-observed reserving 96px for 258px of real
 * content right after a 259-row result was filtered down to 2, with 78 measurements from the
 * old query still cached. The list then cannot be scrolled to its own end until enough of the
 * new rows have been measured to outweigh the old ones.
 *
 * The clear is deliberately deferred to the first render of the replacement rows rather than
 * done when the query starts: the previous query's rows stay in the DOM until its replacement
 * arrives, and the virtualizer goes on measuring them the whole time - live-observed refilling
 * a cache cleared at reload time with 100 entries for a result that turned out to hold 2 rows.
 *
 * Setup strategy:
 * - The grid is left unconnected with a stub standing in for the live virtualizer, which keeps
 *   the test out of the virtualizer's layout timing (see TestTimingNotes.md). `updated()` is
 *   invoked directly with the property change Lit would report, since that is where the deferred
 *   clear is consumed.
 *
 * Pass criteria:
 * - Starting a new query does not clear on its own - the old rows are still on screen.
 * - The first render carrying replacement rows clears, exactly once.
 * - The empty `rows` that _clearRows() itself passes through is not mistaken for that render.
 * - A grid whose virtualizer has not rendered yet, or a future library version without the
 *   cache, does not throw.
 */

function createGridWithLayoutStub() : { grid : Et2Datagrid, clears : string[] }
{
	const clears : string[] = [];
	const grid = new Et2Datagrid();
	const layout = {
		_metricsCache: {clear: () => clears.push("cleared")},
		_scheduleReflow: () => clears.push("reflow")
	};
	Object.defineProperty(grid, "_virtualize", {get: () => ({_layout: layout}), configurable: true});
	return {grid, clears};
}

describe("Et2Datagrid row-height measurement reset", () =>
{
	it("does not clear while the previous query's rows are still on screen", () =>
	{
		const {grid, clears} = createGridWithLayoutStub();

		grid.clear();

		assert.deepEqual(clears, [], "clearing at reload time only throws the cache to the old rows, which are still being measured");
	});

	it("drops the previous result's row measurements once the replacement rows render", () =>
	{
		const {grid, clears} = createGridWithLayoutStub();
		grid.clear();

		grid.rows = [{id: "row::1"}, {id: "row::2"}] as any;
		(<any>grid).updated(new Map([["rows", []]]));

		assert.deepEqual(clears, ["cleared", "reflow"], "the first render of the new rows must clear the cache and reflow");
	});

	it("clears only once per query", () =>
	{
		const {grid, clears} = createGridWithLayoutStub();
		grid.clear();
		grid.rows = [{id: "row::1"}] as any;
		(<any>grid).updated(new Map([["rows", []]]));

		grid.rows = [{id: "row::1"}, {id: "row::2"}] as any;
		(<any>grid).updated(new Map([["rows", []]]));

		assert.deepEqual(clears, ["cleared", "reflow"], "later renders of the same query must keep the measurements they have gathered");
	});

	it("does not mistake the empty rows of the reload itself for the replacement", () =>
	{
		const {grid, clears} = createGridWithLayoutStub();

		grid.clear();
		(<any>grid).updated(new Map([["rows", [{id: "row::old"}]]]));

		assert.deepEqual(clears, [], "an empty result set is _clearRows() passing through, not the new query's rows arriving");
	});

	it("drops them for preloaded rows too", () =>
	{
		const {grid, clears} = createGridWithLayoutStub();

		grid.setInitialRows([{id: "row::1"}, {id: "row::2"}] as any);
		(<any>grid).updated(new Map([["rows", []]]));

		assert.include(clears, "cleared", "seeding a new result set must not keep the old result's row heights");
	});

	it("does nothing when the virtualizer has not rendered yet", () =>
	{
		const grid = new Et2Datagrid();
		grid.clear();
		grid.rows = [{id: "row::1"}] as any;

		// No _rowsBody, so no layout to reach - must be a no-op rather than a throw.
		assert.doesNotThrow(() => (<any>grid).updated(new Map([["rows", []]])),
			"rendering before the virtualizer exists must not throw");
	});

	it("does nothing when the layout has no measurement cache", () =>
	{
		const grid = new Et2Datagrid();
		Object.defineProperty(grid, "_virtualize", {get: () => ({_layout: {}}), configurable: true});
		grid.clear();
		grid.rows = [{id: "row::1"}] as any;

		// Guards the undocumented internals this reaches into against a library version
		// that renames or removes them.
		assert.doesNotThrow(() => (<any>grid).updated(new Map([["rows", []]])),
			"an unfamiliar layout must be left alone, not crash the grid");
	});
});
