import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../../Et2Datagrid/Et2Datagrid";
import {Et2NextmatchDataProvider} from "../../Et2Nextmatch/Et2NextmatchDataProvider";

/**
 * Contract under test:
 * - `Et2NextmatchDataProvider` pages correctly for a host that is NOT an `Et2Nextmatch` - ie. one
 *   satisfying only `NextmatchDataProviderHost` (no `settings`, no filterbox, no widget parent).
 *   `Et2Historylog` is that host.
 *
 * This is the wiring phase 2 of the history-log conversion widened, and the live symptom that
 * prompted these tests was: the first page renders, then scrolling past it leaves every further
 * row permanently blank.
 *
 * Setup strategy:
 * - A minimal host object plus the REAL provider and a REAL datagrid, with `egw`'s data layer
 *   stubbed by an in-memory store.  Driving `loadRowRange()` rather than scrolling keeps this out
 *   of the virtualizer's layout loop while exercising the same request/store bookkeeping.
 * - The grid is left unconnected, like Et2Datagrid's own loadRowRange test.
 *
 * Pass criteria: documented per test.
 */

const TOTAL = 137;
const PREFIX = "infolog";

/** Row data the "server" holds, by index */
const serverRow = (index : number) => ({
	id: 1000 + index,
	status: index % 2 ? "E" : "C",
	new_value: "new " + index,
	old_value: "old " + index,
	owner: 9,
	user_ts: "2026-01-01T00:00:00Z"
});

interface Harness
{
	grid : Et2Datagrid;
	fetches : { start : number, num_rows : number }[];
	provider : Et2NextmatchDataProvider;
}

function harness() : Harness
{
	const fetches : { start : number, num_rows : number }[] = [];
	const store = new Map<string, any>();

	const egwStub : any = {
		lang: (s : string) => s,
		debug: () => {},
		app_name: () => PREFIX,
		dataStoreUID: (uid : string, data : any) => { store.set(uid, data); },
		dataGetUIDdata: (uid : string) => store.has(uid) ? {data: store.get(uid)} : undefined,
		dataUnregisterUID: () => {},
		dataKnownUIDs: () => [],
		dataRegisterUID: (uid : string, callback : Function) =>
		{
			// Deliver whatever the fetch just stored for this uid
			callback(store.get(uid), uid);
		},
		dataFetch: (_execId : string, request : any, _filters : any, _widgetId : string, callback : Function) =>
		{
			const start = Number(request.start) || 0;
			const num = Number(request.num_rows) || 50;
			fetches.push({start, num_rows: num});
			const order : string[] = [];
			for(let i = start; i < Math.min(start + num, TOTAL); i++)
			{
				const row = serverRow(i);
				const uid = `${PREFIX}::${row.id}`;
				store.set(uid, row);
				order.push(uid);
			}
			callback({order, total: TOTAL, rows: {}});
			return {abort: () => {}};
		}
	};
	window.egw = function() { return egwStub; } as any;
	Object.assign(window.egw, egwStub);

	// Exactly what NextmatchDataProviderHost requires - deliberately no `settings`, so the
	// provider's `settings?.dataStorePrefix` / `settings?.row_id` fallbacks are exercised.
	const host : any = {
		id: "history",
		activeFilters: {col_filter: {}, record_id: 1284, appname: PREFIX},
		_filters: {col_filter: {}, record_id: 1284, appname: PREFIX},
		egw: () => egwStub,
		getInstanceManager: () => ({etemplate_exec_id: "exec-1", app: PREFIX}),
		getArrayMgr: () => ({data: {}, getEntry: () => null, getRoot: () => null}),
		getWidgetById: () => null,
		getParent: () => ({getArrayMgr: () => null}),
		closest: () => null,
		getAttribute: () => null,
		sortBy: () => false,
		refreshColumnVisibility: () => {}
	};

	const provider = new Et2NextmatchDataProvider(host);
	const grid = new Et2Datagrid();
	grid.pageSize = 50;
	grid.dataProvider = provider as any;
	return {grid, fetches, provider};
}

describe("Et2Historylog paging (real provider, non-nextmatch host)", () =>
{
	/**
	 * The plain case, to prove the harness itself pages: nothing seeded, load a range that spans
	 * two pages.
	 *
	 * Pass criteria: both pages are requested and every row in the range materializes.
	 */
	it("pages for a host that is not an Et2Nextmatch", async() =>
	{
		const {grid, fetches} = harness();

		await grid.loadRowRange(0, 60);

		const ids = grid.getLoadedRowIds();
		const missing : number[] = [];
		for(let i = 0; i <= 60; i++)
		{
			if(!ids[i]) missing.push(i);
		}
		assert.deepEqual(missing, [], "every row in the range must be loaded");
		assert.isAtLeast(fetches.length, 2, "a 61-row range at pageSize 50 needs more than one page");
	});

	/**
	 * The live failure: the first page is seeded from the server's initial exec (what
	 * Et2Historylog does with `value.rows`), and only then is a later range asked for.
	 *
	 * Pass criteria: the later page is fetched and its rows materialize.  The bug was that
	 * everything past the seeded rows stayed empty forever.
	 */
	it("pages past rows seeded by setInitialRows()", async() =>
	{
		const {grid, fetches, provider} = harness();

		// What the owner does with the rows the initial exec already carried
		const initial = Array.from({length: 26}, (_v, i) => serverRow(i));
		provider.storeRows(initial);
		grid.total = TOTAL;
		grid.setInitialRows(initial);

		const seededFetches = fetches.length;
		await grid.loadRowRange(50, 80);

		const ids = grid.getLoadedRowIds();
		const missing : number[] = [];
		for(let i = 50; i <= 80; i++)
		{
			if(!ids[i]) missing.push(i);
		}
		assert.isAbove(fetches.length, seededFetches,
			"a range past the seeded rows must actually be requested from the server");
		assert.deepEqual(missing, [],
			"every row past the seeded ones must materialize - the live bug left them blank forever");
	});

	/**
	 * `total` has to be known for the grid to reserve the rows it has not loaded.  Et2Nextmatch
	 * applies it *before* seeding; this pins that the history log's ordering works too.
	 *
	 * Pass criteria: the grid keeps the server's total, not the seeded row count.
	 */
	it("keeps the server total when fewer rows are seeded", async() =>
	{
		const {grid, provider} = harness();
		const initial = Array.from({length: 26}, (_v, i) => serverRow(i));

		provider.storeRows(initial);
		grid.setInitialRows(initial);
		grid.total = TOTAL;

		assert.equal(grid.total, TOTAL,
			"seeding 26 rows must not reduce the grid's idea of how many there are");
	});
});

/**
 * Contract under test:
 * - The row-template expressions the User column relies on to swap an account for a share's
 *   recorded address resolve the way the template assumes.
 *
 * Why here: `historylog.rows.xet` writes `hidden="$row_cont[share_email]"` on the account widget
 * and `hidden="!$row_cont[share_email]"` on the address, and nothing else in the test suite would
 * notice if Et2Datagrid's boolean row-expression resolver stopped handling that form - the column
 * would silently show both widgets, or neither.
 *
 * Pass criteria: documented per assertion.
 */
describe("Et2Historylog row-template share_email expressions", () =>
{
	const resolve = (expression : string, rowData : any) =>
	{
		const grid = new Et2Datagrid();
		return (grid as any)._directBooleanRowValue(expression, rowData, "row::1");
	};

	it("hides the account exactly when share_email is set", () =>
	{
		assert.isTrue(resolve("$row_cont[share_email]", {owner: 9, share_email: "someone@example.org"}),
			"a share row must hide the account widget");
		assert.isFalse(resolve("$row_cont[share_email]", {owner: 9, share_email: ""}),
			"an empty share_email must leave the account visible");
		assert.isFalse(resolve("$row_cont[share_email]", {owner: 9, share_email: null}),
			"a null share_email must leave the account visible");
		assert.isFalse(resolve("$row_cont[share_email]", {owner: 9}),
			"a row without the field at all must leave the account visible");
	});

	it("shows the recorded address exactly when the account is hidden", () =>
	{
		// The two expressions must be exact complements, or a row shows both or neither
		for(const row of [
			{owner: 9, share_email: "someone@example.org"},
			{owner: 9, share_email: ""},
			{owner: 9, share_email: null},
			{owner: 9}
		])
		{
			assert.notEqual(resolve("$row_cont[share_email]", row), resolve("!$row_cont[share_email]", row),
				"the account and the address must never be hidden or shown together: " + JSON.stringify(row));
		}
	});

	/**
	 * `$row_cont[field]` and `$[field]` are the same thing here - _canonicalRowExpression()
	 * rewrites the former into the latter before matching.  What the resolver *does* ignore is a
	 * bare `$row_cont` with no field, which returns undefined (not false), so a template writing
	 * that would fall through to an entirely different code path rather than simply not hiding.
	 */
	it("treats $row_cont[field] and $[field] as the same expression", () =>
	{
		const row = {share_email: "x@y.z"};
		assert.equal(resolve("$row_cont[share_email]", row), resolve("$[share_email]", row));
		assert.isUndefined(resolve("$row_cont", row),
			"a bare $row_cont has no field to read and is deliberately ignored");
	});
});
