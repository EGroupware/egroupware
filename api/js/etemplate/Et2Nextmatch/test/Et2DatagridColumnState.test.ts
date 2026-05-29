import {assert} from "@open-wc/testing";
import {Et2DatagridColumnState} from "../Et2DatagridColumnState";
import {Et2DatagridColumn} from "../Et2Datagrid.types";

describe("Et2DatagridColumnState", () =>
{
	/**
	 * Contract under test:
	 * - Column selection ids preserve keys containing spaces via reversible encoding.
	 *
	 * Setup strategy:
	 * - Encode then decode a key containing spaces.
	 *
	 * Pass criteria:
	 * - Encoded id replaces spaces.
	 * - Decoded id matches original key exactly.
	 */
	it("maps chooser ids with space-safe encoding", () =>
	{
		const state = new Et2DatagridColumnState();
		const key = "Project Name";
		const encoded = state.encodeSelectionId(key);
		const decoded = state.decodeSelectionId(encoded);

		assert.equal(encoded, "Project___Name", "spaces should be encoded for chooser");
		assert.equal(decoded, key, "encoded chooser id should decode to original key");
	});

	/**
	 * Contract under test:
	 * - Visible column filtering excludes hidden and disabled columns.
	 *
	 * Setup strategy:
	 * - Provide columns with hidden flag and multiple disabled value styles.
	 *
	 * Pass criteria:
	 * - Only non-hidden, non-disabled column keys remain visible.
	 */
	it("filters visible columns from hidden and disabled flags", () =>
	{
		const state = new Et2DatagridColumnState();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A"},
			{key: "b", title: "B", hidden: true},
			{key: "c", title: "C", disabled: "true"},
			{key: "d", title: "D", disabled: true},
			{key: "f", title: "F", disabled: "1"}
		];
		const visible = state.visibleColumns(columns);

		assert.deepEqual(
			visible.map((column) => column.key),
			["a"],
			"visible columns should exclude hidden and disabled columns"
		);
	});

	/**
	 * Contract under test:
	 * - Selection order mapping keeps selected keys in chooser order and hides unselected columns.
	 *
	 * Setup strategy:
	 * - Apply a selection order that reorders two keys and omits one key.
	 *
	 * Pass criteria:
	 * - Selected keys are visible in selected order slots.
	 * - Unselected keys remain in-place but hidden.
	 */
	it("applies chooser order and hides unselected columns", () =>
	{
		const state = new Et2DatagridColumnState();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A"},
			{key: "b", title: "B"},
			{key: "c", title: "C"}
		];
		const next = state.applySelectionOrder(columns, ["c", "a"]);

		assert.equal(next[0].key, "c", "first selected key should move to first selected slot");
		assert.equal(next[0].hidden, false, "selected column should be visible");
		assert.equal(next[1].key, "b", "unselected middle slot should keep original column");
		assert.equal(next[1].hidden, true, "unselected column should be hidden");
		assert.equal(next[2].key, "a", "second selected key is applied at the next selected slot");
		assert.equal(next[2].hidden, false, "selected column should be visible");
	});
});
