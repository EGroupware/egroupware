import {assert} from "@open-wc/testing";
import {
	Et2DatagridColumnManager,
	Et2DatagridColumnResizeDragState
} from "../Et2DatagridColumnManager";
import {Et2DatagridColumn} from "../Et2Datagrid.types";

describe("Et2DatagridColumnManager", () =>
{
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
});
