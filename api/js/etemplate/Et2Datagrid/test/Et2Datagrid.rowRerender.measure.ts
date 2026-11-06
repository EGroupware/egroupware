import {html, render} from "lit";
import {repeat} from "lit/directives/repeat.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";

/**
 * The measurement behind Et2Datagrid.rowRerender.benchmark.ts, in a module that registers NO
 * tests of its own.
 *
 * It lives here so Et2Datagrid.rowReuse.test.ts can gate on the `reused` half without importing
 * the benchmark: importing a *.benchmark.ts file executes its describe()/it(), which would drag
 * the timing run - deliberately excluded from CI - into every test run that wanted the boolean.
 */

export const COLUMNS = 12;
export const ROW_COUNTS = [30, 100];
export const SAMPLES = 7;

function median(values : number[]) : number
{
	const sorted = [...values].sort((a, b) => a - b);
	return sorted[Math.floor(sorted.length / 2)];
}

const rowTemplate = document.createElement("template");
rowTemplate.innerHTML = `<tr class="dg-row">${Array.from({length: COLUMNS}, (_v, i) =>
	`<td part="cell"><et2-description data-et2nm-id="w${i}" class="cell-${i}"><span>x</span></et2-description></td>`
).join("")}</tr>`;

/** Stand-in for Et2Datagrid._buildRowElement(): fresh element per call, same content. */
function buildRow(index : number) : HTMLElement
{
	const root = document.importNode(rowTemplate.content, true).firstElementChild as HTMLElement;
	root.setAttribute("data-row-id", `row-${index}`);
	root.setAttribute("data-row-index", String(index));
	return root;
}

/** Mirrors Et2Datagrid._virtualRowKey(): stable per row across renders. */
const rowKey = (index : number) => `row:${index}:0`;

function renderPass(container : HTMLElement, indexes : number[], mode : "outerHTML" | "node")
{
	render(html`<table><tbody>${repeat(indexes, rowKey, (index) => mode === "outerHTML"
		? html`${unsafeHTML(buildRow(index).outerHTML)}`
		: html`${buildRow(index)}`)}</tbody></table>`, container);
}

/**
 * Exported so Et2Datagrid.rowReuse.test.ts can gate on the `reused` half of this measurement
 * without duplicating how it is taken.  The timings stay measurement-only (they vary ~2x between
 * engines); `reused` is a boolean and identical everywhere, so only that is asserted on.
 */
export function measure(rowCount : number, mode : "outerHTML" | "node")
{
	const container = document.createElement("div");
	container.style.cssText = "position:absolute;left:-9999px;top:0;width:800px";
	document.body.appendChild(container);
	const indexes = Array.from({length: rowCount}, (_v, i) => i);

	renderPass(container, indexes, mode);
	const before = container.querySelector('[data-row-id="row-0"]');

	const samples : number[] = [];
	for(let i = 0; i < SAMPLES; i++)
	{
		const start = performance.now();
		renderPass(container, indexes, mode);
		samples.push(performance.now() - start);
	}
	const after = container.querySelector('[data-row-id="row-0"]');
	container.remove();
	return {rerenderMs: median(samples), reused: before === after && before !== null};
}
