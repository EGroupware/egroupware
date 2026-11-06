import {assert} from "@open-wc/testing";
import {COLUMNS, measure, ROW_COUNTS} from "./Et2Datagrid.rowRerender.measure";

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
