import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test:
 * - The number of row slots handed to the virtualizer never exceeds the known total just
 *   because a page is still in flight. A pending page inside the already-indexed range
 *   (below `_rowsByIndex.length`) must not be counted a second time on top of that length.
 *
 * Why it matters:
 * - Jumping straight to the end of a long list requests the last two pages at once. When
 *   the tail page lands first it extends `_rowsByIndex` to the full total while the
 *   page before it is still pending. Counting that pending page again inflated the row
 *   count past the total (269 slots for 219 rows, seen live in Addressbook), so the list
 *   scrolled into gray placeholder rows past the real end until the slower page landed.
 *
 * Setup strategy:
 * - A fake data provider that holds every page back until the test releases it, so the
 *   pages can be answered out of order.
 * - The grid is left unconnected: the row count is pure request/store bookkeeping and the
 *   test stays out of the virtualizer's layout loop.
 *
 * Pass criteria:
 * - With the tail page answered and the page before it still pending, the row count
 *   equals the total.
 */

const TOTAL = 219;
const PAGE_SIZE = 50;

function createDatagrid()
{
	const pending = new Map<number, () => void>();
	const grid = new Et2Datagrid();
	grid.pageSize = PAGE_SIZE;
	grid.dataProvider = {
		fetchPage: (start : number, count : number) => new Promise((resolve) =>
		{
			pending.set(start, () =>
			{
				pending.delete(start);
				const rows = Array.from({length: Math.max(0, Math.min(count, TOTAL - start))}, (_value, offset) => ({
					id: `row::${start + offset}`,
					data: {id: `row::${start + offset}`}
				}));
				resolve({rows, total: TOTAL});
			});
		}),
		getDataStorePrefix: () => "pending-count-test",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	return {grid, pending};
}

async function waitFor(condition : () => boolean, message : string)
{
	const deadline = Date.now() + 5000;
	while(!condition())
	{
		if(Date.now() > deadline)
		{
			assert.fail(message);
		}
		await new Promise((resolve) => setTimeout(resolve, 10));
	}
}

describe("Et2Datagrid pending row count", () =>
{
	it("does not count a pending page inside the indexed range twice", async() =>
	{
		const {grid, pending} = createDatagrid();

		// First page, so the total is known
		const firstPage = grid.loadRowRange(0, PAGE_SIZE - 1);
		await waitFor(() => pending.has(0), "first page was never requested");
		pending.get(0)();
		await firstPage;
		assert.equal((grid as any)._virtualRowCount(), TOTAL, "row count after the first page");

		// Jump to the end: the last two pages are requested together
		const tail = grid.loadRowRange(150, TOTAL - 1);
		await waitFor(() => pending.has(150) && pending.has(200), "the last two pages were never both requested");

		// The tail page lands first, the one before it is still pending
		pending.get(200)();
		await waitFor(() => !!(grid as any)._rowsByIndex[TOTAL - 1], "tail page never landed");
		assert.equal((grid as any)._rowsByIndex.length, TOTAL, "tail page should index up to the total");
		assert.equal(
			(grid as any)._virtualRowCount(), TOTAL,
			"a still-pending page below the indexed length must not add row slots past the total"
		);

		pending.get(150)();
		await tail;
		assert.equal((grid as any)._virtualRowCount(), TOTAL, "row count once every page has landed");
	});
});
