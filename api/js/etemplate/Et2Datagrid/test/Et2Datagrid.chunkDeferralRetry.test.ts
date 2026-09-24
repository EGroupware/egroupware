import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract: when _requestChunkForRowIndex() turns a request away because row height has not
 * settled, the _markRowHeightUnstable() debounce MUST re-render once it expires - and must
 * NOT re-render when it turned nobody away.
 *
 * Both halves matter, and they pull in opposite directions:
 *  - Re-rendering when a request was deferred is what the deferral guard explicitly relies on
 *    ("that debounce forces a re-render once it elapses, which re-evaluates this same row and
 *    requests it for real once the guess is corrected" - see _requestChunkForRowIndex()).
 *    Dropping it strands rows past the first page: they are deferred and never retried, which
 *    would turn the double-get_rows bug f822c00d3f fixed into a missing-get_rows bug.
 *    ROW_HEIGHT_STABLE_FALLBACK_MS does not cover this - it is guarded by
 *    !_rowHeightStableSinceReload, which the debounce has already set true.
 *  - Re-rendering when nothing was deferred is what made an idle grid loop forever: the render
 *    re-observes rows, observe() delivers an immediate callback per element, and that re-arms
 *    this same debounce (see Et2Datagrid.idleSettle.test.ts).
 *
 * Setup: a counting subclass records requestUpdate() calls.  The grid is deliberately left
 * DETACHED - with nothing laid out there are no ResizeObserver callbacks and
 * _updateMeasuredAverageRowHeight() takes its "nothing measurable" path without requesting an
 * update of its own, so the only thing that can call requestUpdate() during the measured
 * window is the debounce itself.  The counter is zeroed immediately before arming the
 * debounce, so the delta is attributable to it alone.
 *
 * Pass criteria:
 *  - deferred case: _requestChunkForRowIndex() really did defer (asserted via
 *    _chunkDeferredForRowHeight, so a fixture that failed to reach the guard fails loudly
 *    instead of passing vacuously), and exactly one requestUpdate() follows the debounce.
 *  - undeferred case: no requestUpdate() follows the debounce.
 *
 * Environment: timing only in waiting past ROW_HEIGHT_STABLE_DEBOUNCE_MS (150ms); the wait is
 * several times that.
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

/** Comfortably past ROW_HEIGHT_STABLE_DEBOUNCE_MS (150ms). */
const PAST_DEBOUNCE_MS = 600;

class CountingDatagrid extends Et2Datagrid
{
	public updateRequests = 0;
	/**
	 * Stub out the measurement once the grid has settled.  _updateMeasuredAverageRowHeight()
	 * legitimately requests its own update whenever the measurement changed anything, and this
	 * test is about a different caller entirely - the debounce's conditional re-render.  Left
	 * live, its call lands in the same counter and masks the signal.  Its own behaviour is
	 * covered by the tests that actually exercise measurement.
	 */
	public stubMeasurement = false;

	// @ts-ignore - overriding to isolate the caller under test
	_updateMeasuredAverageRowHeight() : number | null
	{
		if(this.stubMeasurement)
		{
			return (<any>this)._rowHeightPx;
		}
		// @ts-ignore
		return super._updateMeasuredAverageRowHeight();
	}

	requestUpdate(name? : any, oldValue? : any, options? : any)
	{
		// Lit calls requestUpdate() during property setup, before this class's own field
		// initializers have run - so start from 0 rather than incrementing undefined.
		this.updateRequests = (this.updateRequests || 0) + 1;
		return super.requestUpdate(name, oldValue, options);
	}
}

customElements.define("test-deferral-datagrid", <CustomElementConstructor><unknown>CountingDatagrid);

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

/** Same suppressor as Et2Datagrid.idleSettle.test.ts - a transient loop during first layout
 * is legitimate and must not fail this test, which is about something else entirely. */
function suppressResizeObserverLoopErrors() : () => void
{
	const previous = window.onerror;
	window.onerror = function(message, ...rest)
	{
		if(String(message ?? "").includes("ResizeObserver loop"))
		{
			return true;
		}
		return previous ? (<any>previous).call(this, message, ...rest) : false;
	};
	return () => { window.onerror = previous; };
}

function wait(ms : number) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

const hosts : HTMLElement[] = [];

async function makeGrid() : Promise<CountingDatagrid>
{
	// Must be connected: a detached Lit element never performs its first update, so
	// updateComplete would never resolve.  Given it has to be in the document anyway, let it
	// settle fully first (as Et2Datagrid.idleSettle.test.ts does) so the measured window
	// starts from a genuinely quiet grid rather than mid-render.
	const host = document.createElement("div");
	host.style.width = "600px";
	host.style.height = "400px";
	document.body.appendChild(host);
	hosts.push(host);

	const grid = <CountingDatagrid>document.createElement("test-deferral-datagrid");
	(<any>grid).dataProvider = makeDataProvider() as any;
	grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
	grid.pageSize = 10;
	grid.total = 100;
	(<any>grid).setInitialRows(Array.from({length: 10}, (_v, i) => ({id: `row-${i}`, label: `Row ${i}`})));
	// Preconditions for the deferral branch: no scroll yet, height not yet confirmed stable.
	host.appendChild(grid);
	await grid.updateComplete;
	await wait(1200);
	// Preconditions for the deferral branch, (re)asserted after settling: the settle flips
	// _rowHeightStableSinceReload true, and the stub provider's fetch reports total: 0, which
	// would make _requestChunkForRowIndex() bail at its `rowIndex >= this.total` check long
	// before reaching the guard under test.
	grid.stubMeasurement = true;
	grid.total = 100;
	(<any>grid)._bodyScrollVersion = 0;
	(<any>grid)._rowHeightStableSinceReload = false;
	(<any>grid)._chunkDeferredForRowHeight = false;
	return grid;
}

describe("Et2Datagrid chunk-deferral retry", () =>
{
	let restoreOnError : () => void;
	beforeEach(() => { restoreOnError = suppressResizeObserverLoopErrors(); });
	afterEach(() => { restoreOnError?.(); while(hosts.length) { hosts.pop()?.remove(); } });

	it("re-renders after the debounce when a chunk request was deferred", async function()
	{
		this.timeout(15000);
		const grid = await makeGrid();

		const g = <any>grid;
		console.log(`preconditions: pageSize=${grid.pageSize} total=${grid.total} ` +
			`bodyScrollVersion=${g._bodyScrollVersion} stableSinceReload=${g._rowHeightStableSinceReload} ` +
			`usesFixedRowHeight=${g._usesFixedVirtualizerRowHeight()} ` +
			`hasProvider=${!!g.dataProvider} fetchFailed=${g.fetchFailed}`);
		// Row 50 lives in chunk 5, well past the first page - the guard's target.
		(<any>grid)._requestChunkForRowIndex(50);
		assert.isTrue((<any>grid)._chunkDeferredForRowHeight,
			"fixture never reached the deferral branch - the rest of this test would prove nothing");

		grid.updateRequests = 0;
		(<any>grid)._markRowHeightUnstable();
		await wait(PAST_DEBOUNCE_MS);

		console.log(`deferred case: requestUpdate calls after debounce = ${grid.updateRequests}`);
		assert.equal(grid.updateRequests, 1,
			"debounce did not re-render exactly once after deferring a chunk request - " +
			"those rows are stranded, or it rendered more than the one time it owed");
		assert.isFalse((<any>grid)._chunkDeferredForRowHeight,
			"the deferral should be consumed once it has been honoured");
	});

	it("does not re-render after the debounce when nothing was deferred", async function()
	{
		this.timeout(15000);
		const grid = await makeGrid();
		// The grid is live and attached, so its own upgrade/resize path keeps calling
		// _requestChunkForRowIndex() during the measured window.  Exempt it from the guard the
		// way a real user scroll does (_bodyScrollVersion > 0), so nothing can legitimately be
		// deferred here - otherwise this arm silently becomes a second copy of the first.
		(<any>grid)._bodyScrollVersion = 1;
		(<any>grid)._chunkDeferredForRowHeight = false;

		assert.isFalse((<any>grid)._chunkDeferredForRowHeight,
			"nothing should be marked deferred before any chunk request is made");

		grid.updateRequests = 0;
		(<any>grid)._markRowHeightUnstable();
		await wait(PAST_DEBOUNCE_MS);

		console.log(`undeferred case: requestUpdate calls after debounce = ${grid.updateRequests}, ` +
			`deferred flag = ${(<any>grid)._chunkDeferredForRowHeight}`);
		assert.isFalse((<any>grid)._chunkDeferredForRowHeight,
			"something deferred a chunk during the window - this arm no longer tests what it claims");
		assert.equal(grid.updateRequests, 0,
			"debounce re-rendered with nothing waiting on it - this is the idle feedback loop");
	});
});
