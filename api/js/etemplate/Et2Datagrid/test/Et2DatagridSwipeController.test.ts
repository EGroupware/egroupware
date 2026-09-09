import {assert} from "@open-wc/testing";
import {Et2DatagridSwipeController, Et2DatagridSwipeControllerHost} from "../Et2DatagridSwipeController";

/**
 * Contract: Et2DatagridSwipeController detects a horizontal touch swipe
 * across a row and toggles that row's selection (matching legacy
 * nextmatch's mark/unmark-by-either-direction swipe gesture), while
 * ignoring mouse input, short/vertical drags, and gestures that also
 * scrolled the grid.
 *
 * Setup: a minimal fake host exposing only the controller's host surface
 * (selectionMode, a settable `_body.scrollTop`, and recording stand-ins for
 * the row-activation/selection calls a real swipe triggers), plus a real
 * detached row element carrying `data-row-id`/`data-row-index` so the
 * controller's `event.target.closest(...)` lookup works exactly as it does
 * against a live rendered row.
 *
 * Pass criteria: see each test's own assertion - together they cover the
 * three real bugs found via live mobile-emulation testing (an
 * over-strict scroll guard, and the two negative cases: mouse origin and
 * sub-threshold/vertical drags that must never toggle).
 */

function createRow(rowId : string, rowIndex : number) : HTMLElement
{
	const row = document.createElement("div");
	row.setAttribute("data-row-id", rowId);
	row.setAttribute("data-row-index", String(rowIndex));
	document.body.append(row);
	return row;
}

function createHost(overrides : Partial<Et2DatagridSwipeControllerHost> = {}) : Et2DatagridSwipeControllerHost
{
	return {
		selectionMode: "multiple",
		_body: {scrollTop: 0} as unknown as HTMLElement,
		_isInteractiveRowEventTarget: () => false,
		_isRowExpanderEventTarget: () => false,
		_moveActiveRow: () => {},
		_updateSelectionFromPointer: () => {},
		...overrides
	} as Et2DatagridSwipeControllerHost;
}

/** Attach the controller to a row exactly as Et2Datagrid's own pointer bindings do. */
function attach(controller : Et2DatagridSwipeController, row : HTMLElement) : void
{
	row.addEventListener("pointerdown", (event) => controller.handlePointerDown(event as PointerEvent));
	row.addEventListener("pointerup", (event) => controller.handlePointerUp(event as PointerEvent));
	row.addEventListener("pointercancel", (event) => controller.handlePointerCancel(event as PointerEvent));
}

function fire(
	row : HTMLElement, type : string, x : number, y : number,
	pointerType : string = "touch", pointerId : number = 1
) : void
{
	row.dispatchEvent(new PointerEvent(type, {
		bubbles: true, cancelable: true, composed: true,
		pointerId, pointerType, clientX: x, clientY: y
	}));
}

describe("Et2DatagridSwipeController", () =>
{
	afterEach(() =>
	{
		document.querySelectorAll("[data-row-id]").forEach((row) => row.remove());
	});

	it("toggles selection on a horizontal touch swipe past the threshold", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({
			_updateSelectionFromPointer: (rowId, rowIndex, _event, toggleFromPointer) => calls.push({rowId, rowIndex, toggleFromPointer})
		});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50);
		fire(row, "pointerup", 10, 50);

		assert.deepEqual(calls, [{rowId: "row-1", rowIndex: 0, toggleFromPointer: true}],
			"a >60px horizontal touch drag should toggle the swiped row into the selection");
	});

	it("toggles on a right swipe the same as a left swipe", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({_updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 10, 50);
		fire(row, "pointerup", 100, 50);

		assert.deepEqual(calls, ["row-1"], "swipe direction should not matter - both mark/unmark the same way");
	});

	it("ignores mouse-originated drags", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({_updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50, "mouse");
		fire(row, "pointerup", 10, 50, "mouse");

		assert.deepEqual(calls, [], "only touch input should trigger the swipe gesture");
	});

	it("ignores a drag below the swipe distance threshold", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({_updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50);
		fire(row, "pointerup", 85, 50); // 15px, below the 60px threshold

		assert.deepEqual(calls, [], "a short drag should be left as a plain tap, not a swipe");
	});

	it("ignores a vertical-dominant drag", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({_updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 5);
		fire(row, "pointerup", 130, 100); // deltaX=30, deltaY=95

		assert.deepEqual(calls, [], "a mostly-vertical drag is a scroll gesture, not a swipe");
	});

	it("ignores selectionMode 'none'", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({selectionMode: "none", _updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50);
		fire(row, "pointerup", 10, 50);

		assert.deepEqual(calls, [], "selectionMode 'none' should never toggle a row");
	});

	it("does not toggle when the gesture starts on an interactive element or row expander", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({
			_isInteractiveRowEventTarget: () => true,
			_updateSelectionFromPointer: (rowId) => calls.push(rowId)
		});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50);
		fire(row, "pointerup", 10, 50);

		assert.deepEqual(calls, [], "a swipe starting on interactive row content should not also toggle selection");
	});

	/**
	 * Contract: a real finger swipe is never purely horizontal, so a couple
	 * of stray px of vertical rubber-band scroll must not cancel an
	 * otherwise clear horizontal gesture (bug found live: the original
	 * strict scrollTop-equality check silently ate every real swipe).
	 */
	it("tolerates a small amount of incidental scroll drift during a swipe", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const bodyState = {scrollTop: 0};
		const host = createHost({
			_body: bodyState as unknown as HTMLElement,
			_updateSelectionFromPointer: (rowId) => calls.push(rowId)
		});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50);
		bodyState.scrollTop = 2; // within SCROLL_TOLERANCE_PX
		fire(row, "pointerup", 10, 50);

		assert.deepEqual(calls, ["row-1"], "a couple of px of incidental scroll drift should not cancel the swipe");
	});

	it("discards a swipe that meaningfully scrolled the grid", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const bodyState = {scrollTop: 0};
		const host = createHost({
			_body: bodyState as unknown as HTMLElement,
			_updateSelectionFromPointer: (rowId) => calls.push(rowId)
		});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50);
		bodyState.scrollTop = 40; // a real scroll, well past SCROLL_TOLERANCE_PX
		fire(row, "pointerup", 10, 50);

		assert.deepEqual(calls, [], "a gesture that actually scrolled the grid should be treated as a scroll, not a swipe");
	});

	/**
	 * Contract: handlePointerUp()'s return value is what Et2Datagrid uses to
	 * suppress the browser's trailing click for the same gesture - it must
	 * report the toggled row's id on a real swipe and null otherwise.
	 */
	it("returns the toggled row id on a real swipe, null otherwise", () =>
	{
		const row = createRow("row-1", 0);
		const host = createHost();
		const controller = new Et2DatagridSwipeController(host);

		const down = new PointerEvent("pointerdown", {pointerId: 1, pointerType: "touch", clientX: 100, clientY: 50});
		Object.defineProperty(down, "target", {value: row, configurable: true});
		controller.handlePointerDown(down);
		const shortDrag = new PointerEvent("pointerup", {pointerId: 1, pointerType: "touch", clientX: 90, clientY: 50});
		assert.isNull(controller.handlePointerUp(shortDrag), "a non-swipe gesture should return null");

		const down2 = new PointerEvent("pointerdown", {pointerId: 2, pointerType: "touch", clientX: 100, clientY: 50});
		Object.defineProperty(down2, "target", {value: row, configurable: true});
		controller.handlePointerDown(down2);
		const realSwipe = new PointerEvent("pointerup", {pointerId: 2, pointerType: "touch", clientX: 10, clientY: 50});
		assert.equal(controller.handlePointerUp(realSwipe), "row-1", "a real swipe should return the toggled row's id");
	});

	it("ignores pointerup from a different pointer than the one that started the gesture", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({_updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		attach(new Et2DatagridSwipeController(host), row);

		fire(row, "pointerdown", 100, 50, "touch", 1);
		fire(row, "pointerup", 10, 50, "touch", 2); // a different pointer id

		assert.deepEqual(calls, [], "pointerup for an unrelated pointer must not resolve a pending gesture");
	});

	it("resets pending gesture state on pointercancel", () =>
	{
		const row = createRow("row-1", 0);
		const calls : any[] = [];
		const host = createHost({_updateSelectionFromPointer: (rowId) => calls.push(rowId)});
		const controller = new Et2DatagridSwipeController(host);
		attach(controller, row);

		fire(row, "pointerdown", 100, 50);
		row.dispatchEvent(new PointerEvent("pointercancel", {bubbles: true, cancelable: true, composed: true, pointerId: 1, pointerType: "touch"}));
		fire(row, "pointerup", 10, 50);

		assert.deepEqual(calls, [], "a cancelled gesture must not resolve on a later pointerup");
	});
});
