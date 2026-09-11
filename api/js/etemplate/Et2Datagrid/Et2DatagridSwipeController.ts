export interface Et2DatagridSwipeControllerHost extends HTMLElement
{
	selectionMode : string;
	_body : HTMLElement | null;
	_isInteractiveRowEventTarget(event : Event) : boolean;
	_isRowExpanderEventTarget(event : Event) : boolean;
	_moveActiveRow(index : number, focus : boolean) : void;
	_updateSelectionFromPointer(rowId : string, rowIndex : number, event : MouseEvent, toggleFromPointer? : boolean) : void;
}

/**
 * Detects a horizontal touch swipe across a row and toggles that row's
 * selection, matching legacy nextmatch's mobile swipe-to-mark/unmark gesture
 * (`et2_dataview_view_aoi.ts`) - either direction toggles the row into/out of
 * the current selection, it does not distinguish mark vs. unmark by direction.
 *
 * Stays passive like the legacy implementation: it never calls
 * preventDefault(), so native vertical scrolling is untouched. A gesture that
 * meaningfully scrolled the grid (checked via `_body.scrollTop` at pointerdown
 * vs. pointerup, past SCROLL_TOLERANCE_PX) is discarded instead of treated as
 * a swipe - a real finger swipe on a touchscreen is never purely horizontal,
 * so a couple of stray px of vertical rubber-band scroll must not cancel an
 * otherwise clear horizontal gesture.
 */
export class Et2DatagridSwipeController
{
	private host : Et2DatagridSwipeControllerHost;

	/** Minimum horizontal travel, in px, before a touch drag counts as a swipe. */
	private static readonly SWIPE_THRESHOLD_PX = 60;

	/** scrollTop drift, in px, tolerated before a gesture is treated as a scroll instead of a swipe. */
	private static readonly SCROLL_TOLERANCE_PX = 4;

	private _pointerId : number | null = null;
	private _rowId : string | null = null;
	private _rowIndex : number = -1;
	private _startX : number = 0;
	private _startY : number = 0;
	private _startScrollTop : number = 0;

	constructor(host : Et2DatagridSwipeControllerHost)
	{
		this.host = host;
	}

	handlePointerDown(event : PointerEvent)
	{
		if(event.pointerType !== "touch" || this.host.selectionMode === "none")
		{
			return;
		}
		if(this.host._isInteractiveRowEventTarget(event) || this.host._isRowExpanderEventTarget(event))
		{
			return;
		}
		const row = (event.target as HTMLElement | null)?.closest("[data-row-id]") as HTMLElement | null;
		const rowIndex = parseInt(row?.getAttribute("data-row-index") || "-1", 10);
		if(!row || rowIndex < 0)
		{
			return;
		}
		this._pointerId = event.pointerId;
		this._rowId = row.getAttribute("data-row-id");
		this._rowIndex = rowIndex;
		this._startX = event.clientX;
		this._startY = event.clientY;
		this._startScrollTop = this.host._body?.scrollTop ?? 0;
	}

	/**
	 * @return the id of the row toggled by a swipe, or null if this gesture wasn't one.
	 */
	handlePointerUp(event : PointerEvent) : string | null
	{
		if(this._pointerId === null || event.pointerId !== this._pointerId)
		{
			return null;
		}
		const rowId = this._rowId;
		const rowIndex = this._rowIndex;
		const deltaX = event.clientX - this._startX;
		const deltaY = event.clientY - this._startY;
		const scrolled = Math.abs((this.host._body?.scrollTop ?? 0) - this._startScrollTop) > Et2DatagridSwipeController.SCROLL_TOLERANCE_PX;
		this._reset();
		if(scrolled || !rowId || rowIndex < 0)
		{
			return null;
		}
		if(Math.abs(deltaX) < Et2DatagridSwipeController.SWIPE_THRESHOLD_PX || Math.abs(deltaX) <= Math.abs(deltaY))
		{
			return null;
		}
		this.host._moveActiveRow(rowIndex, true);
		this.host._updateSelectionFromPointer(rowId, rowIndex, event, true);
		return rowId;
	}

	handlePointerCancel(_event : PointerEvent)
	{
		this._reset();
	}

	private _reset()
	{
		this._pointerId = null;
		this._rowId = null;
		this._rowIndex = -1;
	}
}
