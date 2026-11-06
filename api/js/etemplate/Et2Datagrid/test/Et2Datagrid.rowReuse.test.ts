import {assert} from "@open-wc/testing";
import {measure} from "./Et2Datagrid.rowRerender.measure";

/**
 * Contract: re-rendering a datagrid with unchanged data must keep the same physical row nodes.
 *
 * Et2Datagrid renders each row as `unsafeHTML(rowElement.outerHTML)`.  lit caches compiled
 * templates in a WeakMap keyed by the strings array, and unsafeHTML allocates a fresh one per
 * call - so it is not obvious that an unchanged row survives a re-render at all, and the sibling
 * Et2Datagrid.rowRerender.benchmark.ts was written to find out.  It reuses.
 *
 * That answer is load-bearing rather than incidental.  Everything about keeping a grid cheap
 * assumes an unchanged row costs nothing: it is why a row widget must not rewrite its own
 * attributes after first render (see Et2Datagrid.md and rowRenderableTests()), and why fixing
 * Et2Avatar's reflected image mattered.  If reuse ever broke, every datagrid update would rebuild
 * every visible row and re-run every widget constructor in it - and none of those other contracts
 * would help, because the row string would no longer be what decides.
 *
 * The benchmark measures this already and throws the answer away (`Pass criteria: none`), because
 * its timings vary ~2x between engines and cannot be gated.  `reused` is a boolean, identical in
 * both browsers on every run, so it can be - and this file does only that, importing the
 * measurement rather than restating it.
 *
 * Pass criteria: the unsafeHTML path reuses the node at both row counts.  The direct-node path is
 * asserted NOT to reuse, which is the documented contrast between the two modes - and doubles as
 * a check that the measurement still discriminates.  Without it, a measure() that returned false
 * for everything would look like a clean failure and one that returned true for everything would
 * look like a clean pass.
 */
describe("Et2Datagrid row reuse", () =>
{
	for(const rowCount of [30, 100])
	{
		it(`keeps the same row node across an unchanged re-render (${rowCount} rows)`, () =>
		{
			const serialized = measure(rowCount, "outerHTML");
			assert.isTrue(serialized.reused,
				`re-rendering ${rowCount} unchanged rows replaced the row node instead of reusing ` +
				`it.  Every datagrid update now rebuilds every visible row and re-runs every widget ` +
				`constructor in it - check what made the row's serialized HTML differ, or what ` +
				`stopped lit matching the committed template.`);
		});

		it(`still discriminates: committing a Node directly does NOT reuse (${rowCount} rows)`, () =>
		{
			const direct = measure(rowCount, "node");
			assert.isFalse(direct.reused,
				`the direct-node path reused its row, which is not the documented behaviour.  Both ` +
				`modes now agree, so the assertion above no longer distinguishes anything - check ` +
				`measure() before trusting it.`);
		});
	}
});
