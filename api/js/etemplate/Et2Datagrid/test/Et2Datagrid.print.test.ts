import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Datagrid} from "../Et2Datagrid";
import {Et2DatagridPrintController} from "../Et2DatagridPrintController";

/**
 * Contract under test: beforePrint() / afterPrint() for a grid whose owner does not prepare
 * printing itself (eg. Et2Historylog, called through its Et2LazyLoadController).
 * - beforePrint() loads every row and renders all loaded rows for print, since virtualization
 *   only renders the rows in the viewport.
 * - It leaves an owner's print rows alone (Et2Nextmatch picks rows and columns itself), and
 *   afterPrint() then does not clear them either.
 *
 * Setup strategy: an unconnected grid with its loading and print rendering stubbed - what is
 * under test is which of them get called with what, not the rendering itself (see
 * Et2DatagridPrintController for that).
 */
function createDatagrid(total : number | null, ownerPrintRows : boolean = false)
{
	const grid = new Et2Datagrid();
	grid.total = total;
	const loadRowRange = sinon.stub(grid, "loadRowRange").resolves();
	const setPrintRows = sinon.stub(grid, "setPrintRows").resolves();
	const clearPrintRows = sinon.stub(grid, "clearPrintRows");
	// Rows not loaded are holes in getLoadedRowIds()
	sinon.stub(grid, "getLoadedRowIds").returns(["row::1", null, "row::3"]);
	if(ownerPrintRows)
	{
		Object.defineProperty(grid, "_printRows", {get: () => [{id: "row::1"}]});
	}
	return {grid, loadRowRange, setPrintRows, clearPrintRows};
}

describe("Et2Datagrid printing", () =>
{
	it("beforePrint() loads every row and renders the loaded ones for print", async() =>
	{
		const {grid, loadRowRange, setPrintRows} = createDatagrid(3);

		await grid.beforePrint();

		assert.isTrue(loadRowRange.calledOnceWith(0, 2), "did not load every row");
		assert.isTrue(setPrintRows.calledOnce);
		assert.deepEqual(setPrintRows.firstCall.args[0], ["row::1", "row::3"]);
	});

	it("beforePrint() waits for the first page of a load still on its way, then loads the rest", async() =>
	{
		const {grid, loadRowRange, setPrintRows} = createDatagrid(null);
		grid.pageSize = 2;
		// The first page tells the total
		loadRowRange.onFirstCall().callsFake(async() => { grid.total = 3; });

		await grid.beforePrint();

		assert.deepEqual(loadRowRange.args, [[0, 1], [0, 2]]);
		assert.isTrue(setPrintRows.calledOnce);
	});

	it("afterPrint() restores virtualized rendering", async() =>
	{
		const {grid, clearPrintRows} = createDatagrid(3);

		await grid.beforePrint();
		grid.afterPrint();

		assert.isTrue(clearPrintRows.calledOnce);
	});

	it("does not load anything for an empty grid", async() =>
	{
		const {grid, loadRowRange} = createDatagrid(0);

		await grid.beforePrint();

		assert.isTrue(loadRowRange.notCalled);
	});

	it("leaves print rows its owner set alone", async() =>
	{
		const {grid, loadRowRange, setPrintRows, clearPrintRows} = createDatagrid(3, true);

		await grid.beforePrint();
		grid.afterPrint();

		assert.isTrue(loadRowRange.notCalled);
		assert.isTrue(setPrintRows.notCalled);
		assert.isTrue(clearPrintRows.notCalled, "cleared the owner's print rows");
	});
});

/**
 * Contract under test: once printing is over the controller puts the body back where it was
 * scrolled to - printing lays the body out unscrolled - from before the first print rows only.
 */
describe("Et2DatagridPrintController", () =>
{
	const frames = async(count = 3) =>
	{
		for(let frame = 0; frame < count; frame++)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		}
	};

	function createHost(scrollTop = 0)
	{
		const host = <any>document.createElement("div");
		host.attachShadow({mode: "open"}).innerHTML = `<div class="dg-body"><table><tbody id="rows"></tbody><tbody id="print-rows"></tbody></table></div>`;
		Object.assign(host, {
			fixedRowHeight: true,
			requestUpdate: () => {},
			updateComplete: Promise.resolve(true),
			_body: {scrollTop},
			_syncRowsMinHeight: () => {},
			_scheduleVirtualizerLayoutSync: () => {},
			_waitForRowUpgradesToFinish: sinon.spy(async() => {})
		});
		document.body.append(host);
		return host;
	}

	it("puts the body back where it was scrolled to after printing", async() =>
	{
		const host = createHost(1234);
		const controller = new Et2DatagridPrintController(host);

		try
		{
			await controller.setPrintRows([]);
			// Printing lays the body out unscrolled
			host._body.scrollTop = 0;
			controller.clearPrintRows();
			await host.updateComplete;
			await frames();

			assert.strictEqual(host._body.scrollTop, 1234);
		}
		finally
		{
			host.remove();
		}
	});

	it("keeps the scroll position from before the first of several setPrintRows()", async() =>
	{
		const host = createHost(500);
		const controller = new Et2DatagridPrintController(host);

		try
		{
			await controller.setPrintRows([]);
			host._body.scrollTop = 0;
			await controller.setPrintRows([]);
			controller.clearPrintRows();
			await host.updateComplete;
			await frames();

			assert.strictEqual(host._body.scrollTop, 500);
		}
		finally
		{
			host.remove();
		}
	});
	it("restores the position remembered before the owner changed the layout", async() =>
	{
		const host = createHost(700);
		const controller = new Et2DatagridPrintController(host);

		try
		{
			controller.rememberScrollPosition();
			// The owner styles itself for print first, which resets the scroll position
			host._body.scrollTop = 0;
			await controller.setPrintRows([]);
			controller.clearPrintRows();
			await host.updateComplete;
			await frames();

			assert.strictEqual(host._body.scrollTop, 700);
		}
		finally
		{
			host.remove();
		}
	});

	it("forgets the remembered position when printing was cancelled before there were print rows", async() =>
	{
		const host = createHost(700);
		const controller = new Et2DatagridPrintController(host);

		try
		{
			controller.rememberScrollPosition();
			controller.clearPrintRows();

			host._body.scrollTop = 40;
			await controller.setPrintRows([]);
			controller.clearPrintRows();
			await host.updateComplete;
			await frames();

			assert.strictEqual(host._body.scrollTop, 40, "restored the position of an earlier, cancelled print");
		}
		finally
		{
			host.remove();
		}
	});

	it("does not move a body the user has already scrolled", async() =>
	{
		const host = createHost(800);
		const controller = new Et2DatagridPrintController(host);

		try
		{
			await controller.setPrintRows([]);
			host._body.scrollTop = 0;
			controller.clearPrintRows();
			await host.updateComplete;
			// The user scrolled before the position was put back
			host._body.scrollTop = 120;
			await frames();

			assert.strictEqual(host._body.scrollTop, 120);
		}
		finally
		{
			host.remove();
		}
	});

	it("waits for the upgrade of all rows of a large print, not a fixed time", async() =>
	{
		const host = createHost();
		const controller = new Et2DatagridPrintController(host);
		const ids = (count : number) => Array.from({length: count}, (_value, index) => `row::${index}`);

		try
		{
			await controller.setPrintRows(ids(10));
			controller.clearPrintRows();
			await controller.setPrintRows(ids(1552));
			controller.clearPrintRows();
			await controller.setPrintRows(ids(100000));
			controller.clearPrintRows();

			const waits = host._waitForRowUpgradesToFinish.args.map((args : any[]) => args[0]);
			assert.deepEqual(waits, [15000, 776000, 1800000],
				"a few rows keep the usual allowance, a large print waits in proportion to its rows, up to a ceiling");
		}
		finally
		{
			host.remove();
		}
	});
});
