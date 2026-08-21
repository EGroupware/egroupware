import interact from "@interactjs/interactjs";
import type {InteractEvent} from "@interactjs/core/InteractEvent";
import type {ReactiveController} from "lit";
import type {Et2Datagrid} from "./Et2Datagrid";
import type {Et2DatagridColumnResizeDragState} from "./Et2DatagridColumnManager";

/**
 * Header column drag-to-resize for Et2Datagrid.
 *
 * Binds interact.js draggable listeners to the currently-rendered
 * `.dg-col-resize-handle` elements. Those elements are replaced by Lit on every
 * render, so `hostUpdated()` re-binds them after each one rather than trying to
 * track handle identity across re-renders - re-binding is a cheap no-op when the
 * handle set hasn't actually changed.
 *
 * `helperLeftPx`/`helperWidthPx` position the host's floating resize-guide
 * overlay while dragging (read directly from this controller in the host's
 * `render()`); since they're plain fields rather than Lit `@state()` properties,
 * `host.requestUpdate()` is called explicitly whenever they change. The
 * `dg-resizing`/`dg-resize-limit-min`/`dg-resize-limit-max` classes are toggled
 * directly on the host element instead, since nothing else in the template needs
 * to react to them.
 */
export class Et2DatagridColumnResizeController implements ReactiveController
{
	private host : Et2Datagrid;
	private handles : HTMLElement[] = [];
	private drag : Et2DatagridColumnResizeDragState | null = null;

	helperLeftPx : number | null = null;
	helperWidthPx : number | null = null;

	constructor(host : Et2Datagrid)
	{
		this.host = host;
		host.addController(this);
	}

	hostConnected() : void
	{
	}

	hostDisconnected() : void
	{
		this.teardownInteract();
		this.clearDragState();
	}

	/**
	 * Re-bind resize handles after every render - matches the unconditional call
	 * this replaced from the host's own `updated()`, since `setupInteract()`
	 * already no-ops when the handle set hasn't changed.
	 */
	hostUpdated() : void
	{
		if(this.host._isColumnResizeDisabled())
		{
			this.teardownInteract();
		}
		else
		{
			this.setupInteract();
		}
	}

	private teardownInteract() : void
	{
		for(const handle of this.handles)
		{
			interact(handle).unset();
		}
		this.handles = [];
	}

	private setupInteract() : void
	{
		const handles = Array.from(this.host.shadowRoot?.querySelectorAll(".dg-col-resize-handle") || []) as HTMLElement[];
		if(!handles.length)
		{
			this.teardownInteract();
			return;
		}
		const sameHandles =
			handles.length === this.handles.length &&
			handles.every((handle, index) => handle === this.handles[index]);
		if(sameHandles)
		{
			return;
		}
		this.teardownInteract();
		for(const handle of handles)
		{
			interact(handle)
				.styleCursor(false)
				.draggable({
					startAxis: "x",
					lockAxis: "x",
					listeners: {
						start: this.handleStart,
						move: this.handleMove,
						end: this.handleEnd
					}
				});
		}
		this.handles = handles;
	}

	/**
	 * Reset drag-resize temporary state.
	 */
	private clearDragState() : void
	{
		this.drag = null;
		this.helperLeftPx = null;
		this.helperWidthPx = null;
		this.host.classList.remove("dg-resizing");
		this.host.classList.remove("dg-resize-limit-min");
		this.host.classList.remove("dg-resize-limit-max");
		this.host.requestUpdate();
	}

	/**
	 * Begin header column resize drag by caching current column sizing context.
	 */
	private handleStart = (event : InteractEvent) : void =>
	{
		const handle = event.target as HTMLElement | null;
		const headerColumn = handle?.closest(".dg-col") as HTMLElement | null;
		const root = this.host.shadowRoot?.querySelector(".dg-root") as HTMLElement | null;
		const columnIndexRaw = handle?.getAttribute("data-column-index") || "";
		const columnIndex = parseInt(columnIndexRaw, 10);
		if(!handle || !headerColumn || !root || Number.isNaN(columnIndex) || !this.host.columns[columnIndex])
		{
			return;
		}
		const visibleColumns = this.host._visibleColumns();
		const metrics = this.host._visibleColumnWidthMetrics(visibleColumns);
		const availableRelativeWidthPx = Math.max(0, metrics.totalVisibleWidthPx - metrics.fixedWidthPx);
		const column = this.host.columns[columnIndex];
		const parsedWidth = this.host._columnWidthDescriptor(column.width);
		const rootRect = root.getBoundingClientRect();
		const headerColumnRect = headerColumn.getBoundingClientRect();
		const startWidthPx = Math.max(1, headerColumnRect.width);
		const minWidthPx = this.host._columnLengthToPx(
			column.minWidth,
			metrics.totalVisibleWidthPx,
			availableRelativeWidthPx,
			metrics.relativeWidthUnits
		);
		const maxWidthPx = this.host._columnLengthToPx(
			column.maxWidth,
			metrics.totalVisibleWidthPx,
			availableRelativeWidthPx,
			metrics.relativeWidthUnits
		);
		const min = Math.max(1, this.host._columnResizeFloorPx(), minWidthPx ?? 1);
		const max = Math.max(min, maxWidthPx ?? Number.POSITIVE_INFINITY);
		this.drag = {
			columnIndex,
			columnKey: String(column.key || ""),
			startWidthPx,
			currentWidthPx: startWidthPx,
			totalVisibleWidthPx: metrics.totalVisibleWidthPx,
			fixedWidthPx: metrics.fixedWidthPx,
			relativeWidthUnits: metrics.relativeWidthUnits,
			minWidthPx: min,
			maxWidthPx: max,
			widthKind: parsedWidth.kind,
			widthUnit: parsedWidth.unit
		};
		this.helperLeftPx = headerColumnRect.left - rootRect.left;
		this.helperWidthPx = startWidthPx;
		this.host.classList.remove("dg-resize-limit-min");
		this.host.classList.remove("dg-resize-limit-max");
		this.host.classList.add("dg-resizing");
		this.host.requestUpdate();
	};

	/**
	 * Update helper position while dragging without applying live column size changes.
	 */
	private handleMove = (event : InteractEvent) : void =>
	{
		const drag = this.drag;
		if(!drag)
		{
			return;
		}
		const requestedWidthPx = drag.currentWidthPx + event.dx;
		const nextWidthPx = this.host._clamp(requestedWidthPx, drag.minWidthPx, drag.maxWidthPx);
		drag.currentWidthPx = nextWidthPx;
		this.helperWidthPx = nextWidthPx;
		const limitState = requestedWidthPx < drag.minWidthPx ? "min"
		                                                      : requestedWidthPx > drag.maxWidthPx ? "max"
		                                                                                           : null;
		this.host.classList.toggle("dg-resize-limit-min", limitState === "min");
		this.host.classList.toggle("dg-resize-limit-max", limitState === "max");
		this.host.requestUpdate();
	};

	/**
	 * Commit resized width at drag end and preserve original width unit type.
	 */
	private handleEnd = (_event : InteractEvent) : void =>
	{
		const drag = this.drag;
		if(!drag)
		{
			return;
		}
		const committed = this.host._columnManager.commitResize(
			this.host.columns || [],
			this.host._visibleColumns(),
			drag,
			this.host._columnResizeFloorPx()
		);
		if(committed)
		{
			this.host.columns = committed.columns;
			this.host.dispatchEvent(new CustomEvent("et2-columns-changed", {
				detail: {
					columns: this.host.columns,
					column: committed.resizedColumn
				},
				bubbles: true,
				composed: true
			}));
			this.host._persistColumnPreferences();
		}
		this.clearDragState();
	};
}
