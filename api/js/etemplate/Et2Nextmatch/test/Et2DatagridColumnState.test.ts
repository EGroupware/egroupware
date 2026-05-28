import {assert} from "@open-wc/testing";
import {Et2DatagridColumnState} from "../Et2DatagridColumnState";
import {Et2DatagridColumn} from "../Et2Datagrid.types";

describe("Et2DatagridColumnState", () =>
{
	it("maps chooser ids with space-safe encoding", () =>
	{
		const state = new Et2DatagridColumnState();
		const key = "Project Name";
		const encoded = state.encodeSelectionId(key);
		const decoded = state.decodeSelectionId(encoded);

		assert.equal(encoded, "Project___Name", "spaces should be encoded for chooser");
		assert.equal(decoded, key, "encoded chooser id should decode to original key");
	});

	it("filters visible columns from hidden and disabled flags", () =>
	{
		const state = new Et2DatagridColumnState();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A"},
			{key: "b", title: "B", hidden: true},
			{key: "c", title: "C", disabled: "true"},
			{key: "d", title: "D", disabled: "@rule"}
		];
		const visible = state.visibleColumns(columns, (expression) =>
		{
			if(expression === "@rule")
			{
				return true;
			}
			throw new Error("fallback to local boolean handling");
		});

		assert.deepEqual(visible.map((column) => column.key), ["a"], "only non-hidden and non-disabled columns should remain visible");
	});

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
