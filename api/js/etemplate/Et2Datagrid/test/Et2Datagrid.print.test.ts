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
		assert.isTrue(grid.classList.contains("print-self"), "no print-self class, prints a page of padding");
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
		assert.isFalse(grid.classList.contains("print-self"));
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
 * Contract under test: while print rows are rendered, a `rangeChanged` from the virtualizer
 * that was replaced by them (a layout message already on its way still fires it) must not
 * reach the virtualize() directive's listener, which would render the virtualized range over
 * the print rows.  After clearPrintRows() it reaches it again.
 */
describe("Et2DatagridPrintController", () =>
{
	function createHost()
	{
		const host = <any>document.createElement("div");
		host.attachShadow({mode: "open"}).innerHTML = `<div class="dg-body"><table><tbody id="rows"></tbody></table></div>`;
		Object.assign(host, {
			fixedRowHeight: true,
			requestUpdate: () => {},
			updateComplete: Promise.resolve(true),
			_syncRowsMinHeight: () => {},
			_scheduleVirtualizerLayoutSync: () => {},
			_sparseVirtualizerLayoutActive: false,
			_waitForRowUpgradesToFinish: async() => {}
		});
		document.body.append(host);
		return host;
	}

	it("keeps the replaced virtualizer's rangeChanged from the directive while printing", async() =>
	{
		const host = createHost();
		const rows = host.shadowRoot.getElementById("rows");
		// Stands in for the virtualize() directive's listener
		const directive = sinon.spy();
		rows.addEventListener("rangeChanged", directive);
		const controller = new Et2DatagridPrintController(host);

		try
		{
			await controller.setPrintRows([]);
			rows.dispatchEvent(new Event("rangeChanged"));
			assert.isTrue(directive.notCalled, "the directive re-rendered over the print rows");

			controller.clearPrintRows();
			rows.dispatchEvent(new Event("rangeChanged"));
			assert.isTrue(directive.calledOnce, "the directive is still blocked after printing");
		}
		finally
		{
			host.remove();
		}
	});
});
