import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test:
 * - `loadRowRange(start, end)` loads every row in the inclusive index range, even when the
 *   range is nowhere near what the virtualizer has rendered, and resolves only once they
 *   are all materialized.
 * - It gives up instead of hanging when the server cannot supply the range.
 * - `getLoadedRowIds()` reports row ids by index, with holes for rows not loaded.
 *
 * Setup strategy:
 * - A fake data provider serving a fixed result set page by page, recording each request
 *   so the test can tell whether a page was fetched once, repeatedly, or not at all.
 * - `pageSize` is deliberately smaller than the requested range so more than one page has
 *   to be fetched and chained - that is the case filemanager's image gallery hits when it
 *   pages past the initially loaded rows.
 * - The grid is left unconnected: this path is pure request/store bookkeeping and must not
 *   depend on rendering, which also keeps the test out of the virtualizer's layout loop.
 *
 * Pass criteria:
 * - After the returned promise resolves, no index in the requested range is still empty.
 * - Only the pages covering the range are requested.
 * - A range the provider will not fill resolves (does not hang) and leaves the hole.
 */

const TOTAL = 40;

function createDatagrid(options : { short? : boolean } = {}) : { grid : Et2Datagrid, requests : number[] }
{
	const requests : number[] = [];
	const grid = new Et2Datagrid();
	grid.pageSize = 5;
	grid.dataProvider = {
		fetchPage: async(start : number, count : number) =>
		{
			requests.push(start);
			// A "short" provider answers with the page's first row only, standing in for a
			// server that reports a larger total than it can actually deliver.
			const rowCount = options.short ? 1 : Math.min(count, TOTAL - start);
			const rows = Array.from({length: Math.max(0, rowCount)}, (_value, offset) => ({
				id: `row::${start + offset}`,
				data: {id: `row::${start + offset}`}
			}));
			return {rows, total: TOTAL};
		},
		getDataStorePrefix: () => "range-test",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	return {grid, requests};
}

describe("Et2Datagrid.loadRowRange()", () =>
{
	it("fetches every page covering the range and resolves once the range is complete", async() =>
	{
		const {grid, requests} = createDatagrid();

		await grid.loadRowRange(7, 18);

		const ids = grid.getLoadedRowIds();
		const missing : number[] = [];
		for(let index = 7; index <= 18; index++)
		{
			if(!ids[index])
			{
				missing.push(index);
			}
		}
		assert.deepEqual(missing, [], "every row in the requested range must be loaded when loadRowRange() resolves");
		assert.equal(ids[7], "row::7", "rows must land at their own index, not packed from 0");
		assert.includeMembers(requests, [5, 10, 15], "each page covering the range must be requested");
		assert.notInclude(requests, 25, "pages past the requested range must not be fetched");
	});

	it("resolves without fetching anything when the range is already loaded", async() =>
	{
		const {grid, requests} = createDatagrid();
		await grid.loadRowRange(0, 4);
		const firstPass = requests.length;

		await grid.loadRowRange(0, 4);

		assert.equal(requests.length, firstPass, "a range that is already loaded must not be re-requested");
	});

	it("gives up instead of hanging when the provider cannot fill the range", async() =>
	{
		const {grid} = createDatagrid({short: true});

		// This would never settle if loadRowRange() waited for rows the provider will not
		// send; the assertions below only run because it returns.
		await grid.loadRowRange(0, 9);

		assert.equal(grid.getLoadedRowIds()[0], "row::0", "what the provider did send must still be stored");
		assert.isNotOk(grid.getLoadedRowIds()[1], "the unfilled part of the range stays empty rather than being faked");
	});
});
