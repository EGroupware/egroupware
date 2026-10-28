import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract: before the user has scrolled, the virtualizer overhang is never more than the
 * library's own 1000px default, however tall the measured rows are.
 *
 * The overhang is pixels, but it is filled with whatever rows are rendered when the list reloads,
 * and that is 35px placeholders until the first page arrives.  Sized from a long description's
 * 3800px row it asked for ~95000px: ~2700 placeholders, each requesting its page, so a filter
 * change on a few thousand rows sent about a hundred ajax_get_rows at once.
 *
 * Setup: a detached grid with its body's height stubbed, so only _tableOverhangPx() is exercised.
 *
 * Pass criteria:
 *  - tall rows: the overhang equals 1000 (the cap) - it would be ~95000 uncapped.
 *  - ordinary rows keep their half-a-page budget, unchanged by the cap.
 *  - once the user has scrolled the library default is used, as before.
 */

const egw = {
	debug: () => {},
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url
};
// @ts-ignore
window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);

class OverhangDatagrid extends Et2Datagrid {}
customElements.define("test-overhang-datagrid", <CustomElementConstructor><unknown>OverhangDatagrid);

const VIEWPORT_PX = 762;

function makeGrid(rowHeightPx : number) : Et2Datagrid
{
	const grid = <Et2Datagrid>document.createElement("test-overhang-datagrid");
	Object.defineProperty(grid, "_body", {get: () => ({clientHeight: VIEWPORT_PX})});
	grid.pageSize = 50;
	(<any>grid).setRowHeightEstimate(rowHeightPx);
	return grid;
}

describe("Et2Datagrid virtualizer overhang", () =>
{
	it("does not size the overhang from a tall row estimate", () =>
	{
		const grid = makeGrid(3845);
		assert.equal(grid.rowHeightEstimatePx, 3845, "fixture should reach the tall estimate");
		assert.equal((<any>grid)._tableOverhangPx(), 1000);
	});

	it("keeps half a page of ordinary rows", () =>
	{
		const grid = makeGrid(44);
		assert.equal((<any>grid)._tableOverhangPx(), 25 * 44 - VIEWPORT_PX);
	});

	it("uses the library default once the user has scrolled", () =>
	{
		const grid = makeGrid(3845);
		(<any>grid)._bodyScrollVersion = 1;
		assert.equal((<any>grid)._tableOverhangPx(), 1000);
	});
});
