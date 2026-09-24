import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test:
 * - While the grid is not being rendered at all, the virtualizer neither re-runs its layout
 *   nor measures its rows.
 * - The row-height stability observer does not re-arm itself in that state either.
 * - Both behave exactly as before once the grid is rendered again.
 *
 * Why it matters: an EGroupware application tab that is not the active one is `display: none`,
 * as are an inactive tab panel and a switched-away app view. Everything inside has a 0x0 box,
 * and the virtualizer takes that at face value: it measures a zero-height viewport, decides no
 * row is visible, and drops every rendered row - then rebuilds the whole range from scratch when
 * the tab comes back. Its row observer separately records all those rows as 0px tall and hands
 * the layout those measurements on the first pass after the grid is visible again, which renders
 * a range several times too large for a frame before settling. Together they turned one
 * application-tab switch into ~5000 nodes of pointless DOM churn, live-measured on addressbook.
 * The row-height observer re-arming while hidden is a third leg of the same problem: re-observing
 * delivers an immediate callback per row, which re-renders, which re-observes, a cycle nothing
 * can settle while no real measurement is possible.
 *
 * Setup strategy:
 * - The grid is left unconnected (so it genuinely has no client rects, the same state a
 *   `display: none` ancestor produces) with a stub standing in for the live virtualizer, which
 *   keeps the test out of the virtualizer's own layout timing (see TestTimingNotes.md).
 * - "Rendered" is simulated by overriding the host's `getClientRects()`, the single signal both
 *   guards read, rather than by trying to lay a real grid out in the test page.
 *
 * Pass criteria:
 * - With no client rects, neither stubbed virtualizer method runs, and no row-height observer is
 *   created.
 * - With a client rect, both methods run and the observer is armed.
 * - Applying the guard twice does not stack wrappers (a second application must not make a
 *   single call count twice).
 */

type GuardedVirtualizer = {
	_hostElement : {getClientRects : () => DOMRect[]},
	_updateLayout : (...args : any[]) => void,
	_measureChildren : (...args : any[]) => void
};

/**
 * A grid whose `_virtualize` is a stub recording which of the guarded methods actually ran.
 * `rendered` flips the one signal the guards consult, standing in for the grid being inside a
 * `display: none` ancestor (no client rects) or not (one rect).
 */
function createGridWithVirtualizerStub() : {
	grid : Et2Datagrid,
	calls : string[],
	setRendered : (rendered : boolean) => void,
	virtualizer : GuardedVirtualizer
}
{
	const calls : string[] = [];
	let rendered = false;
	const virtualizer : GuardedVirtualizer = {
		_hostElement: {getClientRects: () => rendered ? [new DOMRect(0, 0, 100, 100)] : []},
		_updateLayout: () => calls.push("_updateLayout"),
		_measureChildren: () => calls.push("_measureChildren")
	};
	const grid = new Et2Datagrid();
	Object.defineProperty(grid, "_virtualize", {get: () => virtualizer, configurable: true});
	return {grid, calls, virtualizer, setRendered: (value : boolean) => void (rendered = value)};
}

describe("Et2Datagrid guards against working while it is not rendered", () =>
{
	it("skips the virtualizer's layout and row measurement while the grid has no box", () =>
	{
		const {grid, calls, virtualizer} = createGridWithVirtualizerStub();

		(<any>grid)._guardVirtualizerLayoutWhileHidden();
		virtualizer._updateLayout();
		virtualizer._measureChildren();

		assert.deepEqual(calls, [], "a hidden grid can only measure that it is hidden - neither pass has anything to act on");
	});

	it("runs both again once the grid is rendered", () =>
	{
		const {grid, calls, virtualizer, setRendered} = createGridWithVirtualizerStub();

		(<any>grid)._guardVirtualizerLayoutWhileHidden();
		setRendered(true);
		virtualizer._updateLayout();
		virtualizer._measureChildren();

		assert.deepEqual(calls, ["_updateLayout", "_measureChildren"],
			"the guard only defers work, it must not suppress it for a visible grid");
	});

	it("does not stack wrappers when applied again", () =>
	{
		const {grid, calls, virtualizer, setRendered} = createGridWithVirtualizerStub();

		// updated() applies this on every render, so re-application is the normal case
		(<any>grid)._guardVirtualizerLayoutWhileHidden();
		(<any>grid)._guardVirtualizerLayoutWhileHidden();
		setRendered(true);
		virtualizer._updateLayout();

		assert.deepEqual(calls, ["_updateLayout"], "one call through a doubly-wrapped method would run the original twice");
	});

	it("does not re-arm the row-height observer while the grid has no box", () =>
	{
		const {grid} = createGridWithVirtualizerStub();
		// A row to observe, so this can't pass for the pre-existing "nothing measurable" reason
		Object.defineProperty(grid, "_measurableRenderedRows", {
			value: () => [document.createElement("tr")],
			configurable: true
		});

		grid._observeRowHeightStability();

		// Tear down before asserting: an observer armed here would immediately start the very
		// re-render cycle this guards against, which hangs the run instead of failing it
		const armed = (<any>grid)._rowHeightResizeObserver ?? null;
		armed?.disconnect();
		window.clearTimeout((<any>grid)._rowHeightStableTimer);

		assert.isNull(armed,
			"re-observing delivers a callback per row immediately, which re-renders and lands back here - a cycle no measurement can settle while hidden");
	});

	it("arms the row-height observer once the grid is rendered", () =>
	{
		const {grid} = createGridWithVirtualizerStub();
		Object.defineProperty(grid, "_measurableRenderedRows", {
			value: () => [document.createElement("tr")],
			configurable: true
		});
		Object.defineProperty(grid, "_isRendered", {value: () => true, configurable: true});

		grid._observeRowHeightStability();

		const armed = (<any>grid)._rowHeightResizeObserver ?? null;
		armed?.disconnect();
		window.clearTimeout((<any>grid)._rowHeightStableTimer);

		assert.isNotNull(armed,
			"a rendered grid still needs its rows watched for a late height change");
	});
});
