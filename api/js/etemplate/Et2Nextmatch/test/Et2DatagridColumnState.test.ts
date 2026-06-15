import {assert} from "@open-wc/testing";
import {Et2DatagridColumnState} from "../Et2DatagridColumnState.ts";
import {Et2DatagridColumn} from "../Et2Datagrid.types.ts";

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

	/**
	 * Contract under test:
	 * - Customfield chooser ids are plain customfield names.
	 *
	 * Setup strategy:
	 * - Build selection metadata from one customfields-capable header.
	 *
	 * Pass criteria:
	 * - Child customfield id equals the customfield name.
	 */
	it("uses plain customfield names for chooser ids", () =>
	{
		const state = new Et2DatagridColumnState();
		const header = {
			getCustomfieldSelectionItems: () => [
				{name: "cf_text", label: "Text", visible: true}
			]
		};
		const items = state.toSelectionItems([
			{key: "customfields", title: "Custom fields", header: header as any}
		]);
		assert.equal(items[0].customFields?.[0]?.id, "cf_text", "customfield id should be the field name");
	});

	/**
	 * Contract under test:
	 * - Column selection metadata includes nested customfields from header providers.
	 *
	 * Setup strategy:
	 * - Provide a customfields-capable header stub exposing selection items.
	 *
	 * Pass criteria:
	 * - Selection item marks column as customfields and includes child field entries.
	 */
	it("includes nested customfield selection items from header", () =>
	{
		const state = new Et2DatagridColumnState();
		const header = {
			getCustomfieldSelectionItems: () => [
				{name: "cf_text", label: "Text", visible: true},
				{name: "cf_private", label: "Private", visible: false}
			]
		};
		const items = state.toSelectionItems([
			{key: "customfields", title: "Custom fields", header: header as any}
		]);
		assert.equal(items.length, 1, "one column should produce one top-level chooser row");
		assert.isTrue(items[0].isCustomfields, "column should be marked as customfields-aware");
		assert.deepEqual(
			(items[0].customFields || []).map((field) => field.name),
			["cf_text", "cf_private"],
			"customfield chooser entries should be derived from header selection items"
		);
	});

	/**
	 * Contract under test:
	 * - Selecting child customfields auto-selects the customfields column and applies
	 *   per-field visibility back to header state.
	 *
	 * Setup strategy:
	 * - Apply selection containing only one child customfield id.
	 *
	 * Pass criteria:
	 * - Parent customfields column is visible.
	 * - Header receives a complete field visibility map.
	 */
	it("applies child customfield selection to parent column visibility and header map", () =>
	{
		const state = new Et2DatagridColumnState();
		const headerState = {
			visibility: {cf_text: true, cf_private: true}
		};
		const header = {
			getCustomfieldSelectionItems: () => [
				{name: "cf_text", label: "Text", visible: headerState.visibility.cf_text},
				{name: "cf_private", label: "Private", visible: headerState.visibility.cf_private}
			],
			setCustomfieldVisibility: (visibility : Record<string, boolean>) =>
			{
				headerState.visibility = {...visibility};
			}
		};

		const columns : Et2DatagridColumn[] = [
			{key: "subject", title: "Subject"},
			{key: "customfields", title: "Custom fields", header: header as any}
		];
		const selected = [
			"cf_private"
		];
		const next = state.applySelectionOrder(columns, selected);
		const byKey = new Map(next.map((column) => [String(column.key), column]));

		assert.isFalse(byKey.get("customfields")?.hidden, "customfields column should remain visible when one child field is selected");
		assert.isTrue(byKey.get("subject")?.hidden, "unselected regular columns should be hidden");
		assert.deepEqual(
			headerState.visibility,
			{cf_text: false, cf_private: true},
			"header should receive field-level visibility map from chooser selection"
		);
	});
});
