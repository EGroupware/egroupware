import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

/**
 * Manual browser benchmark for nextmatch row hydration.
 *
 * Run with:
 * npx web-test-runner --config web-test-runner.config.mjs \
 *   api/js/etemplate/Et2Nextmatch/test/Et2Datagrid.rowHydration.benchmark.ts
 *
 * It intentionally is not named *.test.ts, so it is excluded from the normal
 * suite.  The benchmark compares a direct row-data pass with the same template
 * forced through the legacy ArrayMgr perspective path.  Results are written to
 * the browser/test-runner log; do not use one run as a release gate.
 */

class Et2RowHydrationBenchmarkWidget extends HTMLElement
{
	private _mgr : any;

	setArrayMgr(_name : string, mgr : any)
	{
		this._mgr = mgr;
	}

	transformAttributes(attributes : Record<string, any>)
	{
		for(const [name, value] of Object.entries(attributes))
		{
			const resolved = typeof value === "string" && value.includes("$")
				? this._mgr?.expandName(value)
				: value;
			this.setAttribute(name, String(resolved ?? ""));
		}
	}
}

if(!customElements.get("et2-row-hydration-benchmark"))
{
	customElements.define("et2-row-hydration-benchmark", Et2RowHydrationBenchmarkWidget);
}

const WIDGETS_PER_ROW = 12;
const ROW_COUNTS = [100, 500, 1000];
const SAMPLES = 7;

function median(values : number[]) : number
{
	const sorted = [...values].sort((a, b) => a - b);
	return sorted[Math.floor(sorted.length / 2)];
}

function createGrid() : Et2Datagrid
{
	const grid = new Et2Datagrid();
	grid.dataProvider = {
		fetchPage: async() => ({rows: [], total: 0}),
		getDataStorePrefix: () => "benchmark",
		normalizeRowId: (id : string | number) => String(id),
		toProviderRowId: (id : string) => id,
		refresh: async() => ({rows: [], removedRowIds: []})
	} as any;
	grid.columns = Array.from({length: WIDGETS_PER_ROW}, (_value, index) => ({key: `field_${index}`, title: String(index)})) as any;
	grid.setArrayMgr("content", new et2_arrayMgr({}));

	const rowTemplate = document.createElement("template");
	rowTemplate.innerHTML = `<tr>${Array.from({length: WIDGETS_PER_ROW}, (_value, index) =>
		`<td><et2-row-hydration-benchmark data-et2nm-id="w${index}"></et2-row-hydration-benchmark></td>`
	).join("")}</tr>`;
	grid.templateData = {
		columns: grid.columns,
		rowTemplate,
		rowTemplateXml: null,
		rowTemplateAttrMap: Object.fromEntries(Array.from({length: WIDGETS_PER_ROW}, (_value, index) => [
			`w${index}`, {"data-value": `$row_cont[field_${index}]`}
		])),
		loaderTemplate: null
	} as any;
	return grid;
}

function sample(grid : Et2Datagrid, rowCount : number, forcePerspective : boolean) : number
{
	const resolver = (grid as any)._resolveRowExpression;
	if(forcePerspective)
	{
		(grid as any)._resolveRowExpression = (value : string, rowData : any, rowId : string) => ({
			...resolver.call(grid, value, rowData, rowId),
			value,
			fallback: true
		});
	}
	const rows = Array.from({length: rowCount}, (_value, index) =>
	{
		const data = Object.fromEntries(Array.from({length: WIDGETS_PER_ROW}, (_value, field) => [`field_${field}`, `row-${index}-${field}`]));
		const row = {id: `row-${index}`, data};
		return {data, element: (grid as any)._buildRowElement(row, index) as HTMLElement, index};
	});
	const start = performance.now();
	for(const row of rows)
	{
		(grid as any)._applyRowElementAttributes(row.element, row.data, row.index);
	}
	const elapsed = performance.now() - start;
	(grid as any)._resolveRowExpression = resolver;
	return elapsed;
}

describe("Et2Datagrid row hydration benchmark", () =>
{
	it("reports direct and forced-perspective hydration medians", () =>
	{
		const grid = createGrid();
		// Warm custom-element construction and JIT compilation before sampling.
		const results = ROW_COUNTS.map((rowCount) =>
		{
			// Warm custom-element construction and JIT compilation before sampling.
			sample(grid, rowCount, false);
			sample(grid, rowCount, true);
			const directMedian = median(Array.from({length: SAMPLES}, () => sample(grid, rowCount, false)));
			const perspectiveMedian = median(Array.from({length: SAMPLES}, () => sample(grid, rowCount, true)));
			assert.isAbove(directMedian, 0, "benchmark should record elapsed direct hydration time");
			assert.isAbove(perspectiveMedian, 0, "benchmark should record elapsed perspective hydration time");
			return {
				rows: rowCount,
				dynamicWidgets: rowCount * WIDGETS_PER_ROW,
				directMedianMs: Number(directMedian.toFixed(2)),
				perspectiveMedianMs: Number(perspectiveMedian.toFixed(2)),
				speedup: Number((perspectiveMedian / directMedian).toFixed(2))
			};
		});
		console.warn("Row hydration benchmark", {widgetsPerRow: WIDGETS_PER_ROW, samples: SAMPLES, results});
	});
});
