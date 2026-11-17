import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test:
 * - Deleting loaded rows client-side (`refresh(ids, "delete")`) closes the gap by moving the
 *   following loaded rows up, which leaves the end of the loaded range empty. Those indexes
 *   belong to pages that were already fetched once, and must be fetched again right away -
 *   otherwise they stay loading placeholders forever, while pages that were never fetched
 *   (further down the list) still load normally.
 * - The server may be told about the delete only afterwards, and for a short time answers
 *   pages from before it. Such an outdated page must not be merged: its rows are at the
 *   indexes from before the delete, so it would bring deleted rows back, put the total
 *   back, and - for a page loaded for the first time - put rows in the wrong place.
 * - An outdated page is asked for again: at once the first time, then after a delay that
 *   doubles per attempt, only for a limited time after the delete, and not at all once
 *   the grid is cleared or reloaded.
 *
 * Setup strategy:
 * - A fake data provider serving a mutable "server" list page by page and recording each
 *   request, so the test can delete server-side too, or deliberately leave the server
 *   outdated.
 * - The grid is left unconnected and driven through `loadRowRange()`, which queues pages
 *   through the same bookkeeping the virtualizer's placeholder rows use, without depending
 *   on rendering.
 *
 * Pass criteria:
 * - After a delete, asking for the emptied indexes requests their page again and fills them.
 * - Rows deleted client-side never reappear, and the total stays at the reduced value.
 * - Once the server has caught up, every loaded index holds the row the server has there,
 *   also on a page that was first requested while the server was outdated.
 * - An outdated page is requested again at once; after a second outdated answer no request
 *   goes out during the delay, and none after the retry window or after a clear.
 */

const TOTAL = 40;
const PAGE_SIZE = 5;
const RETRY_DELAY = 40;
/** Long enough for a retry without delay to become due */
const TICK = 5;

const wait = (ms : number) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Load two pages, then delete two rows of the second page client-side only, and let the
 * grid fetch the page for the emptied indexes from the still unchanged server.
 */
async function deleteAheadOfServer(grid : Et2Datagrid, requests : number[]) : Promise<string[]>
{
	await grid.loadRowRange(0, 9);
	const deleted = ["row::6", "row::7"];
	await grid.refresh(deleted, "delete" as any);
	requests.length = 0;
	await grid.loadRowRange(0, 9);
	assert.deepEqual(requests, [5], "setup: the page was fetched once and came back outdated");
	return deleted;
}

function createDatagrid() : { grid : Et2Datagrid, requests : number[], server : string[] }
{
	const requests : number[] = [];
	const server = Array.from({length: TOTAL}, (_value, index) => `row::${index}`);
	const grid = new Et2Datagrid();
	grid.pageSize = PAGE_SIZE;
	(grid as any)._requestDispatchDelayMs = 0;
	(grid as any)._deleteRefillBackoffMs = RETRY_DELAY;
	grid.dataProvider = {
		fetchPage: async(start : number, count : number) =>
		{
			requests.push(start);
			const rows = server.slice(start, start + count).map((id) => ({id, data: {id}}));
			return {rows, total: server.length};
		},
		getDataStorePrefix: () => "row",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	return {grid, requests, server};
}

describe("Et2Datagrid delete then refill", () =>
{
	it("fetches an already loaded page again after a delete emptied part of it", async() =>
	{
		const {grid, requests, server} = createDatagrid();
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [0, 5], "setup: the first two pages are loaded");

		// Delete 4 of the 10 loaded rows, on the server too
		const deleted = ["row::1", "row::2", "row::3", "row::4"];
		deleted.forEach((id) => server.splice(server.indexOf(id), 1));
		await grid.refresh(deleted, "delete" as any);
		assert.equal(grid.total, TOTAL - 4, "total follows the delete");

		requests.length = 0;
		await grid.loadRowRange(0, 9);

		const ids = grid.getLoadedRowIds().slice(0, 10);
		assert.deepEqual(ids, server.slice(0, 10), "the emptied indexes are filled with the rows that moved up");
		assert.include(requests, 5, "the page holding the emptied indexes is requested again");
	});

	it("does not bring back a deleted row from a page the server answered before deleting", async() =>
	{
		const {grid} = createDatagrid();
		await grid.loadRowRange(0, 9);

		// Server left untouched: the page fetched again for the emptied indexes still
		// returns the deleted rows
		const deleted = ["row::6", "row::7"];
		await grid.refresh(deleted, "delete" as any);
		await grid.loadRowRange(0, 9);

		const ids = grid.getLoadedRowIds().filter(Boolean);
		assert.notIncludeMembers(ids, deleted, "deleted rows stay deleted");
		assert.equal(new Set(ids).size, ids.length, "no row is listed twice");
		assert.equal(grid.total, TOTAL - 2, "the outdated total is not taken over");
	});

	it("does not misplace rows of a page first loaded while the server was outdated", async() =>
	{
		const {grid, requests, server} = createDatagrid();
		const deleted = await deleteAheadOfServer(grid, requests);

		// A page never loaded before, answered from before the delete
		await grid.loadRowRange(10, 14);
		assert.deepEqual(requests, [5, 10], "setup: the further page was requested from the outdated server");

		deleted.forEach((id) => server.splice(server.indexOf(id), 1));
		// The further page was the second outdated answer, so its retry waits for the delay
		await wait(RETRY_DELAY * 1.5);
		await grid.loadRowRange(0, 14);

		assert.deepEqual(grid.getLoadedRowIds().slice(0, 15), server.slice(0, 15), "every index holds the row the server has there");
		assert.equal(grid.total, server.length, "and the total is the server's");
	});

	it("asks again for a page that left indexes empty without looking outdated", async() =>
	{
		const {grid, requests, server} = createDatagrid();
		await grid.loadRowRange(0, 9);
		// Rows of the first page are deleted while a new row arrives at the end, so the
		// outdated second page has no deleted row and not the total from before the delete
		const deleted = ["row::1", "row::2"];
		server.push("row::new");
		await grid.refresh(deleted, "delete" as any);
		requests.length = 0;
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5], "setup: the page came back with rows that are all shown already");

		deleted.forEach((id) => server.splice(server.indexOf(id), 1));
		await wait(TICK);
		await grid.loadRowRange(0, 9);

		assert.deepEqual(requests, [5, 5], "the page is requested again");
		assert.deepEqual(grid.getLoadedRowIds().slice(0, 10), server.slice(0, 10), "the emptied indexes are filled");
	});

	it("asks again at once for an outdated page", async() =>
	{
		const {grid, requests, server} = createDatagrid();
		const deleted = await deleteAheadOfServer(grid, requests);

		// The server has processed the delete while the outdated answer was on its way
		deleted.forEach((id) => server.splice(server.indexOf(id), 1));
		await wait(TICK);
		await grid.loadRowRange(0, 9);

		assert.deepEqual(requests, [5, 5], "the page is requested again without delay");
		assert.deepEqual(grid.getLoadedRowIds().slice(0, 10), server.slice(0, 10), "the emptied indexes are filled");
	});

	it("waits after a second outdated answer, doubling the delay each time", async() =>
	{
		const {grid, requests} = createDatagrid();
		await deleteAheadOfServer(grid, requests);

		// Retry without delay, the server still answers with the outdated page
		await wait(TICK);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5], "setup: first retry sent at once");

		await wait(TICK);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5], "no request before the delay has passed");

		await wait(RETRY_DELAY * 1.5);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5, 5], "second retry after the delay");

		// The first delay has passed again, the doubled one has not
		await wait(RETRY_DELAY * 1.3);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5, 5], "no request before the doubled delay has passed");

		await wait(RETRY_DELAY * 1.5);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5, 5, 5], "third retry after the doubled delay");
	});

	it("gives up once the retry window after the delete has passed", async() =>
	{
		const {grid, requests} = createDatagrid();
		await deleteAheadOfServer(grid, requests);
		// Shorten the window so only the retry without delay fits into it
		(grid as any)._deleteRefillDeadline = Date.now() + RETRY_DELAY / 2;

		await wait(TICK);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5], "setup: the one retry inside the window");

		await wait(RETRY_DELAY * 5);
		await grid.loadRowRange(0, 9);
		assert.deepEqual(requests, [5, 5], "no further retry after the window");
	});

	it("abandons the retry when the grid is cleared", async() =>
	{
		const {grid, requests} = createDatagrid();
		await deleteAheadOfServer(grid, requests);
		await wait(TICK);
		await grid.loadRowRange(0, 9);
		assert.isNotNull((grid as any)._deleteRefillTimer, "setup: a retry is waiting");

		grid.clear();
		assert.isNull((grid as any)._deleteRefillTimer, "the waiting retry is cancelled");

		// A page loaded after the clear is complete, and must not be retried
		requests.length = 0;
		await grid.loadRowRange(0, 4);
		await wait(RETRY_DELAY * 3);
		await grid.loadRowRange(0, 4);
		assert.deepEqual(requests, [0], "only the one fetch for the reloaded page");
	});
});
