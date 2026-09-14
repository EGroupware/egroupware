import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import "../../Et2Datagrid/Et2Datagrid";

/**
 * Contract under test:
 * The row-lookup/row-enumeration/range-fetch surface consumers such as
 * `Expose/ExposeMixin.ts` (filemanager's image gallery) need to keep a gallery in step
 * with the rows the grid is showing.
 *
 * - `getRowByNode()` resolves the row containing an arbitrary DOM node - including nodes
 *   inside nested shadow roots, which is where every row widget actually lives - and
 *   reports how deep in expanded child grids that row sits.
 * - `getLoadedRowIds()` and `loadRowRange()` delegate to the grid.
 * - `isLoading` reflects the grid's fetch state.
 *
 * Setup strategy:
 * - Rows are built as a DOM chain that mirrors the real one (`et2-nextmatch` shadow root >
 *   `et2-datagrid` > grid shadow root > `tr[data-row-id]` > row widget shadow root), using
 *   real shadow roots so the walk is genuinely crossing shadow boundaries rather than
 *   walking one flat tree.  A plain `closest()` cannot make that walk, which is the whole
 *   reason this method exists.
 * - Grid-delegating methods use a stubbed `_datagrid`, keeping the assertions about
 *   delegation rather than about the grid's own paging (covered by
 *   `Et2Datagrid.loadRowRange.test.ts`).
 *
 * Pass criteria:
 * - The row id and depth reported match the row the target node is in.
 * - Nodes outside the nextmatch report no row at all.
 * - Delegating members return/forward exactly what the grid gives them.
 */

/** A shadow host standing in for one shadow boundary in the real row DOM. */
function shadowHost(parent : ParentNode) : HTMLElement
{
	const host = document.createElement("div");
	host.attachShadow({mode: "open"});
	parent.append(host);
	return host;
}

/**
 * Build `grid > (shadow) [expanded row >] tr[data-row-id] > (shadow) span` and return the
 * innermost span, ie. the node a click on a row widget would be reported against.
 */
function addRow(grid : Element, rowId : string) : { row : HTMLElement, target : HTMLElement }
{
	const gridShadow = shadowHost(grid);
	const row = document.createElement("tr");
	row.setAttribute("data-row-id", rowId);
	gridShadow.shadowRoot!.append(row);
	const widget = shadowHost(row);
	const target = document.createElement("span");
	widget.shadowRoot!.append(target);
	return {row, target};
}

describe("Et2Nextmatch gallery support API", () =>
{
	it("finds the row a node is in across shadow boundaries", async() =>
	{
		const nextmatch = new Et2Nextmatch();
		document.body.append(nextmatch);
		await nextmatch.updateComplete;

		const grid = document.createElement("et2-datagrid");
		nextmatch.shadowRoot!.append(grid);
		const {target} = addRow(grid, "filemanager::/home/demo/cat.jpg");

		assert.deepEqual(
			nextmatch.getRowByNode(target),
			{id: "filemanager::/home/demo/cat.jpg", depth: 0},
			"a node inside a top-level row must resolve to that row at depth 0"
		);

		nextmatch.remove();
	});

	it("reports rows of an expanded child grid as deeper than top-level rows", async() =>
	{
		const nextmatch = new Et2Nextmatch();
		document.body.append(nextmatch);
		await nextmatch.updateComplete;

		const grid = document.createElement("et2-datagrid");
		nextmatch.shadowRoot!.append(grid);
		const {row} = addRow(grid, "filemanager::/home/demo");
		// An expanded branch renders as a sibling row with no id of its own, hosting a
		// second datagrid - that nesting is what "depth" counts.
		const expandedRow = document.createElement("tr");
		row.parentNode!.append(expandedRow);
		const childGrid = document.createElement("et2-datagrid");
		expandedRow.append(childGrid);
		const {target} = addRow(childGrid, "filemanager::/home/demo/cat.jpg");

		assert.deepEqual(
			nextmatch.getRowByNode(target),
			{id: "filemanager::/home/demo/cat.jpg", depth: 1},
			"a row inside one expanded child grid must report depth 1"
		);

		nextmatch.remove();
	});

	it("reports no row for a node that is not inside this nextmatch", async() =>
	{
		const nextmatch = new Et2Nextmatch();
		document.body.append(nextmatch);
		await nextmatch.updateComplete;

		const elsewhere = document.createElement("div");
		elsewhere.setAttribute("data-row-id", "infolog::1");
		document.body.append(elsewhere);

		assert.isNull(nextmatch.getRowByNode(elsewhere), "a row belonging to some other widget must not be claimed");
		assert.isNull(nextmatch.getRowByNode(null), "a missing node must not throw");

		elsewhere.remove();
		nextmatch.remove();
	});

	it("delegates row enumeration, range loading and loading state to the datagrid", async() =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		let requested : number[] | null = null;
		Object.defineProperty(nextmatch, "_datagrid", {
			value: {
				loading: true,
				getLoadedRowIds: () => ["row::0", null, "row::2"],
				loadRowRange: async(start : number, end : number) => { requested = [start, end]; }
			}
		});

		assert.deepEqual(
			nextmatch.getLoadedRowIds(),
			["row::0", null, "row::2"],
			"holes for unloaded rows must be preserved, not compacted away"
		);
		assert.isTrue(nextmatch.isLoading, "isLoading must follow the grid's fetch state");

		await nextmatch.loadRowRange(3, 9);
		assert.deepEqual(requested, [3, 9], "the requested range must be passed through unchanged");
	});

	it("reports an empty row list and no loading before a datagrid exists", () =>
	{
		const nextmatch = new Et2Nextmatch();

		assert.deepEqual(nextmatch.getLoadedRowIds(), [], "no grid means no rows, not a crash");
		assert.isFalse(nextmatch.isLoading, "no grid means nothing is loading");
	});
});
