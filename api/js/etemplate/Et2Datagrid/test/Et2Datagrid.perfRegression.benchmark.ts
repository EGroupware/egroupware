import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

/**
 * Manual browser benchmark for two performance claims that shipped without direct
 * measurement.
 *
 * 1. Row-upgrade queue scaling. upgradeRenderedRows() used Array.includes() per
 *    candidate and processRowUpgradeQueue() drained with shift(), both O(n^2) in the
 *    number of queued rows. Irrelevant at viewport size; the print path realizes every
 *    row at once. Measured as scan+drain wall time at growing row counts - the check is
 *    the *shape*: doubling the rows should roughly double the time, not quadruple it.
 *
 * 2. First-request dispatch latency. Et2DatagridRequestQueue.scheduleProcessing() sends
 *    the first request out of an idle queue immediately instead of waiting out
 *    _requestDispatchDelayMs, but embedded grids are still carved out. Measured as
 *    reload() -> first fetchPage() for both, which quantifies both the win and what the
 *    remaining carve-out costs.
 *
 * Pass criteria: none - measurement only. Assertions guard against an empty run.
 *
 * Run with:
 * npx web-test-runner --config web-test-runner.config.mjs \
 *   api/js/etemplate/Et2Datagrid/test/Et2Datagrid.perfRegression.benchmark.ts
 */

const COLUMNS = [{key: "label", title: "Label", width: "1fr"}] as any;
const QUEUE_SIZES = [500, 1000, 2000, 4000];

class PerfCell extends HTMLElement
{
	transformAttributes(attributes : Record<string, any>)
	{
		for(const [name, value] of Object.entries(attributes))
		{
			this.setAttribute(name, String(value ?? ""));
		}
	}
}

if(!customElements.get("et2-perf-regression-cell"))
{
	customElements.define("et2-perf-regression-cell", PerfCell);
}

function makeGrid(rowCount : number) : Et2Datagrid
{
	const grid = new Et2Datagrid();
	const rows = Array.from({length: rowCount}, (_v, i) => ({id: `row-${i}`, label: `Row ${i}`}));
	const byId = new Map(rows.map((r) => [r.id, r]));
	grid.dataProvider = {
		fetchPage: async() => ({rows, total: rowCount}),
		getRowData: (id : string) => byId.get(id),
		getDataStorePrefix: () => "perf",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	grid.columns = COLUMNS;
	grid.setArrayMgr("content", new et2_arrayMgr({}));
	const rowTemplate = document.createElement("template");
	rowTemplate.innerHTML = `<tr><td><et2-perf-regression-cell data-et2nm-id="w0"></et2-perf-regression-cell></td></tr>`;
	grid.templateData = {
		columns: COLUMNS,
		rowTemplate,
		rowTemplateXml: null,
		rowTemplateAttrMap: {w0: {"data-value": "$row_cont[label]"}},
		loaderTemplate: null
	} as any;
	grid.setInitialRows(rows);
	grid.total = rowCount;
	return grid;
}

/**
 * Realize every row as print rows do, then time one full scan + drain of the queue.
 *
 * Rows go into a scratch tbody that _rowsBody is redirected to, never the real one:
 * clearing the virtualizer's own tbody ejects Lit's marker nodes and throws
 * "ChildPart has no parentNode" from whichever test happens to be running.
 */
function timeQueueScanAndDrain(grid : Et2Datagrid, rowCount : number) : number
{
	const renderer = (grid as any)._rowRenderer;
	let rowsBody = (grid as any).__perfScratchBody as HTMLElement | undefined;
	if(!rowsBody)
	{
		const table = document.createElement("table");
		rowsBody = document.createElement("tbody");
		table.appendChild(rowsBody);
		document.body.appendChild(table);
		(grid as any).__perfScratchTable = table;
		(grid as any).__perfScratchBody = rowsBody;
		Object.defineProperty(grid, "_rowsBody", {get: () => rowsBody, configurable: true});
	}
	rowsBody.textContent = "";
	for(let i = 0; i < rowCount; i++)
	{
		const tr = document.createElement("tr");
		tr.setAttribute("data-row-id", `perf::row-${i}`);
		tr.setAttribute("data-row-index", String(i));
		tr.innerHTML = `<td><et2-perf-regression-cell data-et2nm-id="w0"></et2-perf-regression-cell></td>`;
		rowsBody.appendChild(tr);
	}
	// Unbounded drain, as _waitForRowUpgradesToFinish() effectively does for print.
	renderer._rowUpgradeBatchSize = Number.MAX_SAFE_INTEGER;
	renderer._rowUpgradeFrameBudgetMs = Number.MAX_SAFE_INTEGER;
	const start = performance.now();
	renderer.upgradeRenderedRows();
	renderer.processRowUpgradeQueue();
	return performance.now() - start;
}

describe("Et2Datagrid performance regression benchmark", () =>
{
	it("reports row-upgrade queue scaling", async() =>
	{
		const results : { rows : number; ms : number; msPerRow : number }[] = [];
		for(const rowCount of QUEUE_SIZES)
		{
			const host = document.createElement("div");
			host.style.cssText = "position:absolute;left:-9999px;width:800px;height:400px";
			document.body.appendChild(host);
			const grid = makeGrid(rowCount);
			host.appendChild(grid);
			await grid.updateComplete;
			timeQueueScanAndDrain(grid, Math.min(rowCount, 200));	// warm
			const ms = timeQueueScanAndDrain(grid, rowCount);
			results.push({rows: rowCount, ms, msPerRow: ms / rowCount});
			(grid as any).__perfScratchTable?.remove();
			host.remove();
		}
		assert.isAbove(results[0].ms, 0, "benchmark should record elapsed queue time");
		for(const r of results)
		{
			console.log(`queue rows=${String(r.rows).padStart(5)}  ${r.ms.toFixed(1)}ms  ${(r.msPerRow * 1000).toFixed(1)}us/row`);
		}
		// Linear => us/row flat as n grows. Quadratic => it climbs with n.
		const first = results[0].msPerRow;
		const last = results[results.length - 1].msPerRow;
		console.log(`queue per-row cost ${QUEUE_SIZES[0]} -> ${QUEUE_SIZES[QUEUE_SIZES.length - 1]} rows: x${(last / first).toFixed(2)} (1.0 = linear)`);
	});

	it("reports first-request dispatch latency, top-level vs embedded", async() =>
	{
		const measure = async(embedded : boolean) =>
		{
			const host = document.createElement("div");
			host.style.cssText = "position:absolute;left:-9999px;width:800px;height:400px";
			document.body.appendChild(host);
			const grid = new Et2Datagrid();
			grid.embeddedVirtualized = embedded;
			grid.columns = COLUMNS;
			grid.templateData = {columns: COLUMNS} as any;
			let dispatchedAt = 0;
			let start = 0;
			grid.dataProvider = {
				fetchPage: async() =>
				{
					if(!dispatchedAt)
					{
						dispatchedAt = performance.now() - start;
					}
					return {rows: [{id: "row-0", label: "Row 0"}], total: 1};
				},
				getRowData: () => ({id: "row-0", label: "Row 0"}),
				getDataStorePrefix: () => "perf",
				normalizeRowId: (id : string | number) => String(id),
				toProviderRowId: (id : string) => id,
				refresh: async() => ({rows: [], removedRowIds: []})
			} as any;
			host.appendChild(grid);
			await grid.updateComplete;
			start = performance.now();
			await grid.reload();
			for(let i = 0; i < 60 && !dispatchedAt; i++)
			{
				await new Promise<void>((resolve) => setTimeout(resolve, 10));
			}
			host.remove();
			return Math.round(dispatchedAt);
		};

		const topLevel = await measure(false);
		const embedded = await measure(true);
		assert.isAbove(embedded, 0, "benchmark should observe an embedded dispatch");
		console.log(`dispatch latency  top-level=${topLevel}ms  embedded(carve-out)=${embedded}ms  configured delay=100ms`);
	});
});
