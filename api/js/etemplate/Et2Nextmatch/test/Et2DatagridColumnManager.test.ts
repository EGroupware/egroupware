import {assert} from "@open-wc/testing";
import {
	Et2DatagridColumnManager,
	Et2DatagridColumnResizeDragState
} from "../Et2DatagridColumnManager";
import {Et2DatagridColumn} from "../Et2Datagrid.types";

describe("Et2DatagridColumnManager", () =>
{
	/**
	 * Contract under test:
	 * - Grid track string normalizes relative and pixel widths consistently.
	 *
	 * Setup strategy:
	 * - Build track widths from percentage, pixel, and unitless+minWidth columns.
	 *
	 * Pass criteria:
	 * - Relative widths render as `fr`, pixel widths stay `px`, and minWidth is normalized.
	 */
	it("normalizes relative and pixel track widths", () =>
	{
		const manager = new Et2DatagridColumnManager();
		const trackString = manager.columnWidths([
			{key: "a", title: "A", width: "30%"},
			{key: "b", title: "B", width: "240px"},
			{key: "c", title: "C", width: "120", minWidth: "90"}
		] as Et2DatagridColumn[]);

		assert.include(trackString, "30fr", "percentage widths should normalize to fr grid tracks");
		assert.include(trackString, "240px", "pixel widths should stay pixel");
		assert.include(trackString, "minmax(90px, 120px)", "unitless minWidth should normalize to px");
	});

	/**
	 * Contract under test:
	 * - Decimal width definitions are rejected by width normalization.
	 *
	 * Setup strategy:
	 * - Normalize decimal `px`, `%`, and `fr` width strings.
	 *
	 * Pass criteria:
	 * - Each invalid decimal width maps to `auto`.
	 */
	it("treats decimal width inputs as invalid and normalizes to auto", () =>
	{
		const manager = new Et2DatagridColumnManager();
		assert.equal(manager.normalizeColumnWidth("10.5px"), "auto", "decimal pixel widths should be rejected");
		assert.equal(manager.normalizeColumnWidth("12.5%"), "auto", "decimal percentage widths should be rejected");
		assert.equal(manager.normalizeColumnWidth("2.2fr"), "auto", "decimal fr widths should be rejected");
	});

	/**
	 * Contract under test:
	 * - Growing a column steals width proportionally from eligible right-side donors.
	 *
	 * Setup strategy:
	 * - Resize first column wider with two equal pixel donors to the right.
	 *
	 * Pass criteria:
	 * - Resized column grows to target; donors shrink evenly.
	 */
	it("distributes growth stealing across all right-side donor columns", () =>
	{
		const manager = new Et2DatagridColumnManager();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A", width: "100px"},
			{key: "b", title: "B", width: "100px"},
			{key: "c", title: "C", width: "100px"}
		];
		const drag : Et2DatagridColumnResizeDragState = {
			columnIndex: 0,
			columnKey: "a",
			startWidthPx: 100,
			currentWidthPx: 140,
			totalVisibleWidthPx: 300,
			fixedWidthPx: 300,
			relativeWidthUnits: 0,
			minWidthPx: 16,
			maxWidthPx: 1000,
			widthKind: "pixel",
			widthUnit: "px"
		};

		const committed = manager.commitResize(columns, columns, drag, 16);
		assert.isNotNull(committed, "resize should commit");
		assert.equal(committed!.columns[0].width, "140px", "resized column should grow to target");
		assert.equal(committed!.columns[1].width, "80px", "first right donor should share shrink");
		assert.equal(committed!.columns[2].width, "80px", "second right donor should share shrink");
	});

	/**
	 * Contract under test:
	 * - Rightmost growth is allowed without donor redistribution.
	 *
	 * Setup strategy:
	 * - Resize last column wider where no right-side donors exist.
	 *
	 * Pass criteria:
	 * - Target column grows; unaffected columns stay unchanged.
	 */
	it("allows rightmost column growth when there are no right-side donors", () =>
	{
		const manager = new Et2DatagridColumnManager();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A", width: "100px"},
			{key: "b", title: "B", width: "100px"}
		];
		const drag : Et2DatagridColumnResizeDragState = {
			columnIndex: 1,
			columnKey: "b",
			startWidthPx: 100,
			currentWidthPx: 160,
			totalVisibleWidthPx: 200,
			fixedWidthPx: 200,
			relativeWidthUnits: 0,
			minWidthPx: 16,
			maxWidthPx: 1000,
			widthKind: "pixel",
			widthUnit: "px"
		};

		const committed = manager.commitResize(columns, columns, drag, 16);
		assert.isNotNull(committed, "resize should commit");
		assert.equal(committed!.columns[1].width, "160px", "rightmost column should grow without donors");
		assert.equal(committed!.columns[0].width, "100px", "left column should remain unchanged");
	});

	/**
	 * Contract under test:
	 * - Resize commit is rejected when drag metadata does not match target column identity.
	 *
	 * Setup strategy:
	 * - Use a drag state with mismatched `columnKey` for the indexed column.
	 *
	 * Pass criteria:
	 * - `commitResize()` returns `null`.
	 */
	it("returns null when drag key does not match target column", () =>
	{
		const manager = new Et2DatagridColumnManager();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A", width: "100px"},
			{key: "b", title: "B", width: "100px"}
		];
		const drag : Et2DatagridColumnResizeDragState = {
			columnIndex: 1,
			columnKey: "wrong-key",
			startWidthPx: 100,
			currentWidthPx: 130,
			totalVisibleWidthPx: 200,
			fixedWidthPx: 200,
			relativeWidthUnits: 0,
			minWidthPx: 16,
			maxWidthPx: 1000,
			widthKind: "pixel",
			widthUnit: "px"
		};

		const committed = manager.commitResize(columns, columns, drag, 16);
		assert.isNull(committed, "resize should be rejected when drag key does not match indexed column");
	});

	/**
	 * Contract under test:
	 * - Resized widths are clamped to drag min/max boundaries.
	 *
	 * Setup strategy:
	 * - Perform one undersized shrink and one oversized growth using the same base columns.
	 *
	 * Pass criteria:
	 * - Shrink result equals min bound; growth result equals max bound.
	 */
	it("clamps resized column width to drag min/max bounds", () =>
	{
		const manager = new Et2DatagridColumnManager();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A", width: "100px"},
			{key: "b", title: "B", width: "100px"}
		];

		const shrinkDrag : Et2DatagridColumnResizeDragState = {
			columnIndex: 0,
			columnKey: "a",
			startWidthPx: 100,
			currentWidthPx: 5,
			totalVisibleWidthPx: 200,
			fixedWidthPx: 200,
			relativeWidthUnits: 0,
			minWidthPx: 40,
			maxWidthPx: 1000,
			widthKind: "pixel",
			widthUnit: "px"
		};
		const shrunk = manager.commitResize(columns, columns, shrinkDrag, 16);
		assert.isNotNull(shrunk, "shrink resize should commit");
		assert.equal(shrunk!.columns[0].width, "40px", "shrink should clamp at minWidthPx");

		const growDrag : Et2DatagridColumnResizeDragState = {
			...shrinkDrag,
			currentWidthPx: 9999,
			maxWidthPx: 130
		};
		const grown = manager.commitResize(columns, columns, growDrag, 16);
		assert.isNotNull(grown, "grow resize should commit");
		assert.equal(grown!.columns[0].width, "130px", "growth should clamp at maxWidthPx");
	});

	/**
	 * Contract under test:
	 * - Relative-unit columns preserve relative units after resize and donor redistribution.
	 *
	 * Setup strategy:
	 * - Resize an `fr` column in a mixed `fr`/`px` set.
	 *
	 * Pass criteria:
	 * - Resized relative column and relative donor remain `fr`; pixel donor remains `px`.
	 */
	it("preserves relative units when resizing relative columns and donors", () =>
	{
		const manager = new Et2DatagridColumnManager();
		const columns : Et2DatagridColumn[] = [
			{key: "a", title: "A", width: "1fr"},
			{key: "b", title: "B", width: "1fr"},
			{key: "c", title: "C", width: "100px"}
		];
		const drag : Et2DatagridColumnResizeDragState = {
			columnIndex: 0,
			columnKey: "a",
			startWidthPx: 100,
			currentWidthPx: 160,
			totalVisibleWidthPx: 300,
			fixedWidthPx: 100,
			relativeWidthUnits: 2,
			minWidthPx: 16,
			maxWidthPx: 1000,
			widthKind: "relative",
			widthUnit: "fr"
		};

		const committed = manager.commitResize(columns, columns, drag, 16);
		assert.isNotNull(committed, "relative resize should commit");
		assert.match(String(committed!.columns[0].width), /fr$/, "resized relative column should remain fr");
		assert.match(String(committed!.columns[1].width), /fr$/, "relative donor should remain fr");
		assert.match(String(committed!.columns[2].width), /px$/, "pixel donor should remain px");
	});
});
