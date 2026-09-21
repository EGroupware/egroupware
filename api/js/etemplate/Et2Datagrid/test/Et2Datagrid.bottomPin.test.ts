import {assert} from "@open-wc/testing";
import {Et2Datagrid} from "../Et2Datagrid";

/**
 * Contract under test:
 * - Once the reader has scrolled to the end of the list, the last row stays at the bottom
 *   even when the reserved extent afterwards grows.
 *
 * Why it matters: the extent is the row count times the average height of the rows measured
 * so far, so it keeps moving while rows are still being measured. When it grows, the browser
 * leaves scrollTop where it was - which is no longer the end - and the list silently gains
 * room below the reader, who then sees the view jump and more entries appear on the next
 * render. Reaching the bottom is an unambiguous statement about where the last row belongs,
 * whatever the rows turn out to measure.
 *
 * Setup strategy:
 * - The scroll port is faked: `_body` is stubbed with plain numbers so the test states the
 *   exact geometry it means, instead of rendering rows and hoping the browser produces the
 *   heights the case needs (see TestTimingNotes.md). `_keepBottomPinned()` and the scroll
 *   listener both work only through `scrollTop`/`scrollHeight`/`clientHeight`, so the stub
 *   exercises the real code path.
 *
 * Pass criteria:
 * - Growth after reaching the end restores the reader to the new end.
 * - Someone who is not at the end is never moved.
 * - A list that fits its viewport is not treated as "at the end".
 * - Rows arriving from a push lengthen the list without dragging the reader along.
 * - A new query forgets the old list's end.
 */

type FakeBody = { scrollTop : number, scrollHeight : number, clientHeight : number };

function createGrid(body : FakeBody) : { grid : Et2Datagrid, scroll : () => void }
{
	const grid = new Et2Datagrid();
	Object.defineProperty(grid, "_body", {get: () => body, configurable: true});
	// The real listener is bound in the constructor and installed on the rendered body;
	// calling it directly is what a "scroll" event would do.
	return {grid, scroll: () => (<any>grid)._scrollListener()};
}

function setRowCount(grid : Et2Datagrid, count : number)
{
	grid.total = count;
	(<any>grid)._rowsByIndex = new Array(count).fill(null);
}

describe("Et2Datagrid bottom pin", () =>
{
	it("puts the reader back at the end when the reserved extent grows under them", () =>
	{
		const body : FakeBody = {scrollTop: 0, scrollHeight: 2000, clientHeight: 800};
		const {grid, scroll} = createGrid(body);
		setRowCount(grid, 30);

		body.scrollTop = 1200;
		scroll();
		// More rows get measured, they turn out taller than the running average, and the
		// reserved extent grows. scrollTop is untouched by that, so it is no longer the end.
		body.scrollHeight = 2600;

		(<any>grid)._keepBottomPinned();

		assert.equal(body.scrollTop, 1800, "reaching the end must survive the extent growing afterwards");
	});

	it("leaves a reader who is not at the end alone", () =>
	{
		const body : FakeBody = {scrollTop: 400, scrollHeight: 2000, clientHeight: 800};
		const {grid, scroll} = createGrid(body);
		setRowCount(grid, 30);

		scroll();
		body.scrollHeight = 2600;

		(<any>grid)._keepBottomPinned();

		assert.equal(body.scrollTop, 400, "someone reading the middle of the list must not be scrolled to the end");
	});

	it("does not treat a list that fits its viewport as a scroll to the end", () =>
	{
		const body : FakeBody = {scrollTop: 0, scrollHeight: 600, clientHeight: 800};
		const {grid, scroll} = createGrid(body);
		setRowCount(grid, 5);

		scroll();
		// The list grows past the viewport, eg. because rows finished hydrating taller.
		body.scrollHeight = 1400;

		(<any>grid)._keepBottomPinned();

		assert.equal(body.scrollTop, 0, "a list with nowhere to scroll says nothing about where the reader wants to be");
	});

	it("does not drag the reader onto rows that arrived after they reached the end", () =>
	{
		const body : FakeBody = {scrollTop: 1200, scrollHeight: 2000, clientHeight: 800};
		const {grid, scroll} = createGrid(body);
		setRowCount(grid, 30);

		scroll();
		// A push or autorefresh appends rows: the list is longer because there is more of it,
		// not because the same rows measured taller.
		setRowCount(grid, 40);
		body.scrollHeight = 2600;

		(<any>grid)._keepBottomPinned();

		assert.equal(body.scrollTop, 1200, "new rows must not scroll the reader onto content they have not seen");
	});

	it("forgets the previous result's end when a new query starts", () =>
	{
		const body : FakeBody = {scrollTop: 1200, scrollHeight: 2000, clientHeight: 800};
		const {grid, scroll} = createGrid(body);
		setRowCount(grid, 30);
		scroll();
		assert.isTrue((<any>grid)._bodyPinnedToEnd, "precondition: the scroll landed at the end");

		grid.clear();

		assert.isFalse((<any>grid)._bodyPinnedToEnd, "a new result set has its own end, unrelated to the old one");
	});
});
