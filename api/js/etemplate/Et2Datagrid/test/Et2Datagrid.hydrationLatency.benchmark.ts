import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

/**
 * Manual browser benchmark for how long realized rows stay visibly unhydrated.
 *
 * Behaviour under test: rows are stamped with a `loading` class and hydrated by
 * Et2DatagridRowRenderer's frame-throttled queue, which processes at most
 * `_rowUpgradeBatchSize` rows per animation frame.  That class is what the user
 * sees, so "frames until no row still carries it" is the time-to-settled figure
 * the batch size actually governs.
 *
 * Setup: a grid with a realistic multi-widget row template and a full viewport of
 * preloaded rows, measured at several batch sizes by overriding the private field.
 *
 * Pass criteria: none - measurement only.  Assertions guard against an empty run.
 * Results go to the test-runner log.
 *
 * Run with:
 * npx web-test-runner --config web-test-runner.config.mjs \
 *   api/js/etemplate/Et2Datagrid/test/Et2Datagrid.hydrationLatency.benchmark.ts
 */

const WIDGETS_PER_ROW = 10;
const ROW_COUNTS = [30, 50];
const BATCH_SIZES = [8, 64];

class BenchmarkCell extends HTMLElement
{
	transformAttributes(attributes : Record<string, any>)
	{
		for(const [name, value] of Object.entries(attributes))
		{
			this.setAttribute(name, String(value ?? ""));
		}
	}
}

if(!customElements.get("et2-hydration-latency-cell"))
{
	customElements.define("et2-hydration-latency-cell", BenchmarkCell);
}

function buildGrid(rowCount : number, batchSize : number) : Et2Datagrid
{
	const grid = new Et2Datagrid();
	const columns = Array.from({length: WIDGETS_PER_ROW}, (_v, i) => ({key: `field_${i}`, title: String(i)}));
	const rowData = new Map<string, any>();
	for(let i = 0; i < rowCount; i++)
	{
		rowData.set(`row-${i}`, Object.fromEntries(
			Array.from({length: WIDGETS_PER_ROW}, (_v, f) => [`field_${f}`, `r${i}c${f}`])
		));
	}
	grid.dataProvider = {
		fetchPage: async() => ({rows: [], total: rowCount}),
		getRowData: (rowId : string) => rowData.get(rowId),
		getDataStorePrefix: () => "benchmark",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	grid.columns = columns as any;
	grid.setArrayMgr("content", new et2_arrayMgr({}));

	const rowTemplate = document.createElement("template");
	rowTemplate.innerHTML = `<tr>${Array.from({length: WIDGETS_PER_ROW}, (_v, i) =>
		`<td><et2-hydration-latency-cell data-et2nm-id="w${i}"></et2-hydration-latency-cell></td>`
	).join("")}</tr>`;
	grid.templateData = {
		columns,
		rowTemplate,
		rowTemplateXml: null,
		rowTemplateAttrMap: Object.fromEntries(Array.from({length: WIDGETS_PER_ROW}, (_v, i) => [
			`w${i}`, {"data-value": `$row_cont[field_${i}]`}
		])),
		loaderTemplate: null
	} as any;
	(grid as any)._rowRenderer._rowUpgradeBatchSize = batchSize;
	grid.style.cssText = "height:900px;display:block";
	grid.setInitialRows(Array.from({length: rowCount}, (_v, i) => ({id: `row-${i}`})));
	grid.total = rowCount;
	return grid;
}

/** Frames until no realized row still carries the `loading` class. */
async function framesUntilHydrated(grid : Et2Datagrid, maxFrames = 240) : Promise<number>
{
	for(let frame = 1; frame <= maxFrames; frame++)
	{
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		const rows = Array.from(grid.shadowRoot?.querySelectorAll("[data-row-id]:not(.dg-row-placeholder)") || []);
		if(rows.length && !rows.some((row) => row.classList.contains("loading")))
		{
			return frame;
		}
	}
	return -1;
}

describe("Et2Datagrid hydration latency benchmark", () =>
{
	// Same benign-error suppression the main Et2Datagrid suite uses: laying out a full
	// viewport of rows trips "ResizeObserver loop completed with undelivered
	// notifications", which mocha's global handler would otherwise report as a failure.
	let onError : ((event : ErrorEvent) => void) | null = null;
	let originalOnError : OnErrorEventHandler = null;
	const isResizeObserverNoise = (text : string) => text.includes("ResizeObserver loop completed with undelivered notifications");

	before(() =>
	{
		onError = (event : ErrorEvent) =>
		{
			if(isResizeObserverNoise(String(event?.message || "")))
			{
				event.preventDefault();
				event.stopImmediatePropagation?.();
			}
		};
		window.addEventListener("error", onError, true);
		// Chromium surfaces it through window.onerror rather than the capture listener.
		originalOnError = window.onerror;
		window.onerror = (message, source, lineno, colno, error) =>
		{
			if(isResizeObserverNoise(String(message || error?.message || "")))
			{
				return true;
			}
			return typeof originalOnError === "function"
				   ? originalOnError.call(window, message, source, lineno, colno, error)
				   : false;
		};
	});

	after(() =>
	{
		if(onError)
		{
			window.removeEventListener("error", onError, true);
			onError = null;
		}
		window.onerror = originalOnError;
		originalOnError = null;
	});

	it("reports frames until realized rows stop showing as loading, by batch size", async() =>
	{
		for(const rowCount of ROW_COUNTS)
		{
			const results : Record<number, number> = {};
			for(const batchSize of BATCH_SIZES)
			{
				const grid = buildGrid(rowCount, batchSize);
				document.body.appendChild(grid);
				await grid.updateComplete;
				results[batchSize] = await framesUntilHydrated(grid);
				grid.remove();
			}
			assert.isAbove(results[BATCH_SIZES[0]], 0, "benchmark should observe rows becoming hydrated");
			console.log(
				`rows=${String(rowCount).padStart(3)}  ` +
				BATCH_SIZES.map((b) => `batch=${b}: ${results[b]} frames`).join("  |  ")
			);
		}
	});
});
