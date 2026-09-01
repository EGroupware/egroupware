import {assert} from "@open-wc/testing";
import {html, render} from "lit";
import {repeat} from "lit/directives/repeat.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";

/**
 * Manual browser benchmark for row DOM *reuse* across re-renders.
 *
 * Behaviour under test: @lit-labs/virtualizer renders its range through
 * lit's keyed `repeat()`, and Et2Datagrid supplies a stable keyFunction
 * (Et2Datagrid._virtualRowKey) so unchanged rows should keep their DOM.
 * But _renderVirtualRow() returns `unsafeHTML(rowElement.outerHTML)`, and
 * unsafeHTML allocates a fresh `strings` array per call.  lit caches compiled
 * Templates in a WeakMap keyed by that array, so every call misses, produces a
 * new Template, and ChildPart's `committed._$template === template` reuse check
 * fails - which would mean a full teardown/rebuild of every visible row on
 * every datagrid update, stable keys notwithstanding.
 *
 * Setup: render the same keyed row list twice with identical data, once via
 * unsafeHTML(outerHTML) and once committing the element as a Node.  Capture a
 * row's DOM node before and after the second render.
 *
 * Pass criteria: none - measurement only.  `reused` reports whether the second
 * render kept the same physical node; the timings are the cost of not doing so.
 *
 * Run with:
 * npx web-test-runner --config web-test-runner.config.mjs \
 *   api/js/etemplate/Et2Datagrid/test/Et2Datagrid.rowRerender.benchmark.ts
 */

const COLUMNS = 12;
const ROW_COUNTS = [30, 100];
const SAMPLES = 7;

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

function measure(rowCount : number, mode : "outerHTML" | "node")
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

describe("Et2Datagrid row re-render benchmark", () =>
{
	it("reports whether stable keys actually preserve row DOM across re-renders", () =>
	{
		for(const rowCount of ROW_COUNTS)
		{
			const serialized = measure(rowCount, "outerHTML");
			const direct = measure(rowCount, "node");
			assert.isAbove(serialized.rerenderMs, 0, "serialized re-render should record elapsed time");
			console.log(
				`rows=${String(rowCount).padStart(3)}  ` +
				`unsafeHTML(outerHTML): ${serialized.rerenderMs.toFixed(2)}ms, node reused=${serialized.reused}  |  ` +
				`direct node: ${direct.rerenderMs.toFixed(2)}ms, node reused=${direct.reused}`
			);
		}
	});
});
