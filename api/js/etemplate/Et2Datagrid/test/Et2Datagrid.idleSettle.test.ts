import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract: a rendered Et2Datagrid that nobody is touching must go quiet.
 *
 * Behaviour under test: _observeRowHeightStability() watches every realized row with a
 * ResizeObserver whose callback is _markRowHeightUnstable(), which arms a 150ms debounce
 * (ROW_HEIGHT_STABLE_DEBOUNCE_MS) and, when it expires, calls requestUpdate() unconditionally.
 * That update re-renders rows (_renderItem -> _buildRowElement), the new rows are re-observed,
 * and per the ResizeObserver spec observe() delivers an immediate callback per newly observed
 * element - calling _markRowHeightUnstable() again.  Nothing in that chain compares the new
 * measurement against the old, so it can run forever with no user input: a profile captured
 * from an idle tab on a live instance shows exactly that, ~150ms timer callbacks each doing
 * ~150ms of row rebuilding, saturating a core.
 *
 * Setup: a counting subclass records Lit update cycles and ResizeObserver-driven
 * _markRowHeightUnstable() calls.  The grid is mounted in the document with a real laid-out
 * box - a detached or zero-size grid gets no layout and therefore no resize callbacks, which
 * would make the test vacuously quiet - and given rows of deliberately uneven text length so
 * their heights differ.  Counters are zeroed only after the initial render has settled, so
 * first-paint work is not counted; the question is exclusively what happens afterwards.
 *
 * Two arms isolate the mechanism rather than just observing that something is busy:
 *   - "observed"  - normal grid, row-height ResizeObserver active
 *   - "locked"    - identical, but _rowHeightLocked makes _observeRowHeightStability()
 *                   disconnect the observer and return early
 * If only the observed arm keeps working, the loop is in the row-height-stability path and
 * not merely a busy fixture.
 *
 * The browser's own feedback-loop detector ("ResizeObserver loop completed with undelivered
 * notifications") is captured as a signal instead of being allowed to fail the test as an
 * uncaught error - it is the most direct evidence available that a loop occurred at all.
 *
 * Pass criteria: during the idle window the grid performs no more than IDLE_CYCLE_BUDGET
 * further update cycles and the browser reports no ResizeObserver loop.  The per-interval
 * breakdown is logged so a steady drumbeat (a loop) is distinguishable from one late
 * straggler.  Each arm first asserts rows actually rendered, so a grid that drew nothing
 * cannot pass by being trivially quiet.
 *
 * Environment: headless is fine - measured in this runner, a headless tab reports
 * visibilityState "visible" with ResizeObserver and rAF both firing, and the loop reproduces
 * normally.  What would break this test is a BACKGROUNDED tab, where rAF pauses and the observer
 * stops: the grid then does no work for the same reason a fixed grid does none, and this test
 * would pass while the bug is present (verified by stubbing both APIs - 24 rows still render, so
 * the rows>0 check above does not catch it).  That is what web-test-runner.config.mjs's
 * concurrency: 1 protects; see the comment on the launchers before raising it.
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

/** Idle observation window, many debounce periods long (the debounce is 150ms). */
const IDLE_MS = 2400;
const SLICE_MS = 800;
/**
 * A settled grid should do nothing at all.  2 tolerates a single straggling late measurement
 * without tolerating a 150ms drumbeat, which would produce roughly 16 cycles in this window.
 */
const IDLE_CYCLE_BUDGET = 2;

class CountingDatagrid extends Et2Datagrid
{
	public updateCycles = 0;
	public unstableMarks = 0;

	updated(changedProperties)
	{
		this.updateCycles++;
		return super.updated(changedProperties);
	}

	// @ts-ignore - private in the base class, instrumented here deliberately
	_markRowHeightUnstable()
	{
		this.unstableMarks++;
		// @ts-ignore
		return super._markRowHeightUnstable();
	}
}

customElements.define("test-idle-datagrid", <CustomElementConstructor><unknown>CountingDatagrid);

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

/**
 * Swallow the browser's ResizeObserver-loop error and count it instead.  Registered in the
 * capture phase so it runs before the test runner's own uncaught-error handler, which would
 * otherwise fail the test before any measurement could be taken.
 */
function captureResizeObserverLoops() : {count : () => number, stop : () => void}
{
	let seen = 0;
	const previous = window.onerror;
	// window.onerror rather than addEventListener('error', ..., true): the test runner
	// installs its own window.onerror, and for an event whose target IS window the capture
	// flag does not reorder anything - listeners run in registration order, and the runner
	// registered first.  Chaining the handler is the only way to see the error before it is
	// treated as an uncaught failure.  Returning true marks it handled and suppresses that.
	window.onerror = function(message, ...rest)
	{
		if(String(message ?? "").includes("ResizeObserver loop"))
		{
			seen++;
			return true;
		}
		return previous ? (<any>previous).call(this, message, ...rest) : false;
	};
	return {
		count: () => seen,
		stop: () => { window.onerror = previous; }
	};
}

async function measureIdle(lockRowHeight : boolean, loops : {count : () => number})
{
	const host = document.createElement("div");
	host.style.width = "600px";
	host.style.height = "400px";
	document.body.appendChild(host);

	const grid = <CountingDatagrid>document.createElement("test-idle-datagrid");
	(<any>grid).dataProvider = makeDataProvider() as any;
	grid.columns = [{key: "label", title: "Label", width: "1fr"}] as any;
	if(lockRowHeight)
	{
		// Makes _observeRowHeightStability() disconnect the observer and return early.
		(<any>grid)._rowHeightLocked = true;
	}
	const rows = Array.from({length: 24}, (_v, i) => ({
		id: `row-${i}`,
		label: "Row " + i + " " + "wrap ".repeat((i % 7) + 1)
	}));
	(<any>grid).setInitialRows(rows);
	grid.total = rows.length;
	host.appendChild(grid);

	await grid.updateComplete;
	await wait(1200);          // let first paint, row upgrade and height settle finish

	const renderedRows = grid.shadowRoot?.querySelectorAll("[data-row-id]").length ?? 0;

	// Zero everything only now: a transient ResizeObserver loop while the grid performs its
	// FIRST layout is expected and legitimate (rows genuinely are still changing size).  The
	// contract under test is that it goes quiet afterwards, so both the cycle counters and the
	// loop-error baseline are taken here, after the settle.
	grid.updateCycles = 0;
	grid.unstableMarks = 0;
	const loopsBefore = loops.count();

	const slices : number[] = [];
	for(let i = 0; i < Math.ceil(IDLE_MS / SLICE_MS); i++)
	{
		const before = grid.updateCycles;
		await wait(SLICE_MS);
		slices.push(grid.updateCycles - before);
	}

	const result = {
		renderedRows,
		cycles: grid.updateCycles,
		marks: grid.unstableMarks,
		slices,
		loops: loops.count() - loopsBefore
	};
	host.remove();
	return result;
}

describe("Et2Datagrid idle settling", function()
{
	// Each arm deliberately spends ~1.2s settling plus IDLE_MS observing, well past mocha's
	// 3s default.  A plain arrow function here would not bind `this`, so this stays a
	// function expression.
	this.timeout(30000);

	let loops : ReturnType<typeof captureResizeObserverLoops>;

	beforeEach(() => { loops = captureResizeObserverLoops(); });
	afterEach(() => { loops.stop(); });

	it("stops re-rendering when left alone", async function()
	{
		const r = await measureIdle(false, loops);
		console.log(`[observed] rows=${r.renderedRows} idle cycles=${r.cycles} ` +
			`marks=${r.marks} per-${SLICE_MS}ms=[${r.slices.join(", ")}] ` +
			`RO-loop errors(idle)=${r.loops}`);

		assert.isAbove(r.renderedRows, 0,
			"grid rendered no rows - an empty grid would be trivially quiet and prove nothing");
		assert.equal(r.loops, 0,
			`browser reported ${r.loops} ResizeObserver feedback loop(s) while idle`);
		assert.isAtMost(r.cycles, IDLE_CYCLE_BUDGET,
			`grid kept re-rendering while idle: ${r.cycles} update cycles, ${r.marks} resize marks ` +
			`in ${IDLE_MS}ms (per ${SLICE_MS}ms: ${r.slices.join(", ")})`);
	});

	it("is quiet when the row-height observer is disabled (control)", async function()
	{
		const r = await measureIdle(true, loops);
		console.log(`[locked]   rows=${r.renderedRows} idle cycles=${r.cycles} ` +
			`marks=${r.marks} per-${SLICE_MS}ms=[${r.slices.join(", ")}] ` +
			`RO-loop errors(idle)=${r.loops}`);

		assert.isAbove(r.renderedRows, 0, "control grid rendered no rows");
		assert.isAtMost(r.cycles, IDLE_CYCLE_BUDGET,
			`control grid was not quiet either - the fixture itself is churning, so the other ` +
			`arm's result cannot be attributed to the row-height path ` +
			`(${r.cycles} cycles, per ${SLICE_MS}ms: ${r.slices.join(", ")})`);
	});
});
