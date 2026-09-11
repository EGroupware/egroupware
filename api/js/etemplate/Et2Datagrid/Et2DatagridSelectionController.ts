import type {Et2Datagrid} from "./Et2Datagrid";
import type {Et2DatagridSelectionDetail} from "./Et2Datagrid.types";

/**
 * Owns row selection state (selected ids, select-all, active/anchor row) and
 * keyboard/pointer navigation for Et2Datagrid.
 *
 * Typed against the concrete `Et2Datagrid` host (like Et2DatagridColumnResizeController)
 * rather than a narrow interface - this reaches deeply into row storage and
 * row-expansion state (both of which stay on the host), so a narrow interface
 * would just reproduce most of Et2Datagrid's surface for no real benefit.
 */
export class Et2DatagridSelectionController
{
	private host : Et2Datagrid;

	/** Set of selected row ids used to derive emitted selection payloads. */
	selectedRowIds : Set<string> = new Set();
	allSelected : boolean = false;
	/** Anchor index for shift-range selection semantics. */
	anchorRowIndex : number = -1;
	/** Keyboard/pointer active row index in currently loaded rows. */
	activeRowIndex : number = -1;
	/** Active row id mirrored from `activeRowIndex` for event payload convenience. */
	activeRowId : string | null = null;

	private _pendingOffscreenKeyboardNavigation : boolean = false;

	constructor(host : Et2Datagrid)
	{
		this.host = host;
	}

	/**
	 * Handle keyboard navigation and selection interactions.
	 */
	handleTableKeydown(event : KeyboardEvent)
	{
		if(this.host._handleRowExpanderKeydown(event))
		{
			return;
		}
		const key = event.key;
		if(key === "ArrowRight" || key === "ArrowLeft")
		{
			if(this.handleHorizontalRowNavigation(event))
			{
				return;
			}
		}
		if(!["ArrowUp", "ArrowDown", "PageUp", "PageDown", "Home", "End", " ", "a", "A"].includes(key))
		{
			return;
		}
		if(!this.host._rowsByIndex.length && this.host.total === null)
		{
			return;
		}
		if(["ArrowUp", "ArrowDown", "PageUp", "PageDown"].includes(key) &&
			this.activeRowIndex >= 0 &&
			this.hasRenderedRows() &&
			!this.isRowIndexRendered(this.activeRowIndex))
		{
			event.preventDefault();
			event.stopPropagation();
			event.stopImmediatePropagation();
			this.scrollActiveRowIntoViewThenReplayNavigation(key, event);
			return;
		}

		const pageStep = Math.max(1, Math.floor((this.host._body?.clientHeight || 0) / 44));
		let nextIndex = this.activeRowIndex >= 0 ? this.activeRowIndex : 0;
		const maxIndex = Math.max(0, (this.host.total ?? this.host._rowsByIndex.length) - 1);
		if(key === "ArrowUp") nextIndex = Math.max(0, nextIndex - 1);
		if(key === "ArrowDown")
		{
			nextIndex = Math.min(maxIndex, nextIndex + 1);
		}
		if(key === "PageUp") nextIndex = Math.max(0, nextIndex - pageStep);
		if(key === "PageDown")
		{
			nextIndex = Math.min(maxIndex, nextIndex + pageStep);
		}
		if(key === "Home") nextIndex = 0;
		if(key === "End")
		{
			nextIndex = maxIndex;
		}

		if(key === " " || key === "Spacebar")
		{
			event.preventDefault();
			event.stopPropagation();
			event.stopImmediatePropagation();
			this.toggleSelectionOnActiveRow();
			return;
		}
		if((key === "a" || key === "A") && (event.ctrlKey || event.metaKey))
		{
			if(this.host.selectionMode === "multiple")
			{
				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				this.allSelected = true;
				this.selectedRowIds = new Set(this.host.rows.map((row) => row.id));
				this.syncRowAccessibilityState();
				this.emitSelectionChanged();
			}
			return;
		}

		// Prevent native page scroll on navigation keys; grid owns row navigation.
		event.preventDefault();
		event.stopPropagation();
		event.stopImmediatePropagation();
		const previous = this.activeRowIndex;
		this.host._restoreFocusAfterRender = true;
		this.moveActiveRow(nextIndex, true);
		if(event.shiftKey && this.host.selectionMode === "multiple")
		{
			this.selectRange(this.anchorRowIndex >= 0 ? this.anchorRowIndex : previous, nextIndex);
		}
		else if(this.host.selectionMode !== "none" && !event.ctrlKey && !event.metaKey && this.activeRowId)
		{
			// Plain navigation (no modifier) replaces the selection with the newly active row.
			// same as a plain click - so anything reacting to selection (e.g. a preview pane) keeps following the keyboard cursor.
			// Must happen synchronously here, not via the capture-phase action-shortcut handler further up the dispatch chain
			// that runs *before* moveActiveRow() above and would act on the row that was active before this keypress, one step behind.
			this.allSelected = false;
			this.selectedRowIds = new Set([this.activeRowId]);
			this.anchorRowIndex = nextIndex;
			this.syncRowAccessibilityState();
			this.emitSelectionChanged(true);
		}
	}

	/**
	 * Handle treegrid-style horizontal navigation between parent rows and child grids.
	 */
	handleHorizontalRowNavigation(event : KeyboardEvent) : boolean
	{
		if(this.activeRowIndex < 0)
		{
			return false;
		}
		const row = this.host._rowsByIndex[this.activeRowIndex];
		if(event.key === "ArrowLeft" && this.host.parentRowId)
		{
			event.preventDefault();
			event.stopPropagation();
			this.host.dispatchEvent(new CustomEvent("et2-datagrid-leave-child-grid", {
				detail: {
					parentRowId: this.host.parentRowId
				},
				bubbles: true,
				composed: true
			}));
			return true;
		}
		if(!row || !this.host._isRowExpandable(row, this.activeRowIndex))
		{
			return false;
		}
		if(event.key === "ArrowRight")
		{
			event.preventDefault();
			event.stopPropagation();
			if(!this.host._isRowExpanded(row))
			{
				this.host._setRowExpanded(row, true);
				return true;
			}
			this.host.dispatchEvent(new CustomEvent("et2-datagrid-enter-expanded-row", {
				detail: {
					parentRowId: this.host._rowExpansionId(row),
					rowId: row.id,
					rowIndex: this.activeRowIndex
				},
				bubbles: true,
				composed: true
			}));
			return true;
		}
		if(event.key === "ArrowLeft" && this.host._isRowExpanded(row))
		{
			event.preventDefault();
			event.stopPropagation();
			this.host._setRowExpanded(row, false);
			return true;
		}
		return false;
	}

	/**
	 * Check whether a data row index currently has a realized DOM row.
	 */
	isRowIndexRendered(index : number) : boolean
	{
		if(index < 0)
		{
			return false;
		}
		return !!this.host._rowsBody?.querySelector(`[data-row-index="${index}"]`);
	}

	/**
	 * Check whether any data rows are currently realized in the DOM.
	 */
	hasRenderedRows() : boolean
	{
		return !!this.host._rowsBody?.querySelector("[data-row-index]");
	}

	/**
	 * Bring an off-screen active row into view, then replay the original key action.
	 */
	private async scrollActiveRowIntoViewThenReplayNavigation(key : string, sourceEvent : KeyboardEvent)
	{
		if(this._pendingOffscreenKeyboardNavigation)
		{
			return;
		}
		this._pendingOffscreenKeyboardNavigation = true;
		try
		{
			const activeIndex = this.activeRowIndex;
			if(activeIndex < 0)
			{
				return;
			}
			const body = this.host._body;
			if(body)
			{
				const rowHeight = this.host.rowHeightEstimatePx;
				const centeredTop = Math.max(0, Math.floor(activeIndex * rowHeight - body.clientHeight / 2));
				body.scrollTop = centeredTop;
			}
			for(let i = 0; i < 24; i++)
			{
				await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
				await this.host.updateComplete;
				if(this.isRowIndexRendered(activeIndex))
				{
					break;
				}
			}
			if(!this.isRowIndexRendered(activeIndex))
			{
				return;
			}
			this.handleTableKeydown(new KeyboardEvent("keydown", {
				key,
				shiftKey: sourceEvent.shiftKey,
				ctrlKey: sourceEvent.ctrlKey,
				metaKey: sourceEvent.metaKey
			}));
		}
		finally
		{
			this._pendingOffscreenKeyboardNavigation = false;
		}
	}

	/**
	 * Toggle selected state for active row according to current selection mode.
	 */
	toggleSelectionOnActiveRow()
	{
		if(this.host.selectionMode === "none" || this.activeRowIndex < 0)
		{
			return;
		}
		const row = this.host._rowsByIndex[this.activeRowIndex];
		if(!row)
		{
			return;
		}

		this.allSelected = false;
		if(this.host.selectionMode === "single")
		{
			this.selectedRowIds = new Set([row.id]);
		}
		else
		{
			const next = new Set(this.selectedRowIds);
			if(next.has(row.id))
			{
				next.delete(row.id);
			}
			else
			{
				next.add(row.id);
			}
			this.selectedRowIds = next;
		}
		this.syncRowAccessibilityState();
		this.emitSelectionChanged();
	}

	/**
	 * Update selection model from pointer gesture semantics.
	 */
	updateSelectionFromPointer(rowId : string, rowIndex : number, event : MouseEvent, toggleFromPointer : boolean = false)
	{
		if(this.host.selectionMode === "none")
		{
			return;
		}
		this.allSelected = false;
		if(this.host.selectionMode === "single")
		{
			this.selectedRowIds = new Set([rowId]);
			this.anchorRowIndex = rowIndex;
			this.syncRowAccessibilityState();
			this.emitSelectionChanged(true);
			return;
		}

		if(event.shiftKey && this.anchorRowIndex >= 0)
		{
			this.selectRange(this.anchorRowIndex, rowIndex);
			return;
		}

		const toggle = event.ctrlKey || event.metaKey || toggleFromPointer;
		if(toggle)
		{
			const next = new Set(this.selectedRowIds);
			if(next.has(rowId))
			{
				next.delete(rowId);
			}
			else
			{
				next.add(rowId);
			}
			this.selectedRowIds = next;
		}
		else
		{
			this.selectedRowIds = new Set([rowId]);
		}

		this.anchorRowIndex = rowIndex;
		this.syncRowAccessibilityState();
		this.emitSelectionChanged(!toggle);
	}

	/**
	 * Select inclusive row range, used for shift-selection.
	 */
	selectRange(startIndex : number, endIndex : number)
	{
		if(this.host.selectionMode !== "multiple")
		{
			return;
		}
		this.allSelected = false;
		const start = Math.min(startIndex, endIndex);
		const end = Math.max(startIndex, endIndex);
		const next = new Set<string>();
		for(let i = start; i <= end; i++)
		{
			if(this.host._rowsByIndex[i])
			{
				next.add(this.host._rowsByIndex[i].id);
			}
		}
		this.selectedRowIds = next;
		this.syncRowAccessibilityState();
		this.emitSelectionChanged();
	}

	/**
	 * Move active row and optionally focus corresponding DOM row.
	 */
	moveActiveRow(index : number, focus : boolean)
	{
		const maxIndex = Math.max(0, (this.host.total ?? this.host._rowsByIndex.length) - 1);
		if(index < 0 || index > maxIndex)
		{
			return;
		}
		const previousActiveRowId = this.activeRowId;
		this.activeRowIndex = index;
		this.activeRowId = this.host._rowsByIndex[index]?.id ?? null;
		if(this.anchorRowIndex < 0)
		{
			this.anchorRowIndex = index;
		}
		this.syncRowAccessibilityState();

		if(focus)
		{
			this.focusRowByIndex(index, 10);
		}
		if(this.activeRowId !== previousActiveRowId)
		{
			this.host.dispatchEvent(new CustomEvent("et2-active-row-changed", {
				detail: {
					activeRowId: this.activeRowId,
					activeRowIndex: this.activeRowIndex
				},
				bubbles: true,
				composed: true
			}));
		}
	}

	/**
	 * Focus row by absolute index, optionally scrolling it into view.
	 */
	focusRowByIndex(index : number, retries : number = 0, allowScroll : boolean = true)
	{
		const rowElement = (Array.from(this.host._rowsBody?.querySelectorAll("[data-row-index]") || []) as HTMLElement[])
			.find((row) => parseInt(row.getAttribute("data-row-index") || "-1", 10) === index) || null;
		if(rowElement)
		{
			// Use preventScroll so mutation-recovery focus does not hijack scrollbar drag.
			// Explicit scrollIntoView stays opt-in via `allowScroll`.
			rowElement.focus({preventScroll: true});
			if(allowScroll)
			{
				rowElement.scrollIntoView({block: "nearest"});
			}
			if(this.host.shadowRoot?.activeElement === rowElement)
			{
				this.host._restoreFocusAfterRender = false;
				return;
			}
			if(retries > 0)
			{
				requestAnimationFrame(() => this.focusRowByIndex(index, retries - 1, allowScroll));
			}
			return;
		}
		if(retries <= 0)
		{
			return;
		}
		requestAnimationFrame(() => this.focusRowByIndex(index, retries - 1, allowScroll));
	}

	/**
	 * Synchronize ARIA attributes and tabindex across rendered row DOM.
	 */
	syncRowAccessibilityState()
	{
		const rowElements = Array.from(this.host._rowsBody?.querySelectorAll("[data-row-index]") || []) as HTMLElement[];
		rowElements.forEach((rowElement) =>
		{
			const absoluteIndex = parseInt(rowElement.getAttribute("data-row-index") || "-1", 10);
			const rowId = rowElement.getAttribute("data-row-id") || "";
			rowElement.setAttribute("role", "row");
			rowElement.setAttribute("aria-selected", this.selectedRowIds.has(rowId) ? "true" : "false");
			if(this.allSelected && !this.selectedRowIds.has(rowId))
			{
				rowElement.setAttribute("aria-selected", "true");
			}
			rowElement.setAttribute("aria-rowindex", String(Math.max(0, absoluteIndex) + 1));
			rowElement.tabIndex = absoluteIndex === this.activeRowIndex ? 0 : -1;
			rowElement.classList.toggle("dg-row-selected", this.allSelected || this.selectedRowIds.has(rowId));
			rowElement.classList.toggle("dg-row-active", rowId === this.activeRowId);

			const cells = Array.from(rowElement.children) as HTMLElement[];
			cells.forEach((cell, cellIndex) =>
			{
				if(cell.getAttribute("data-dg-meta-cell") === "1" && this.host._isTileView())
				{
					cell.setAttribute("aria-hidden", "true");
					return;
				}
				const isHeader = cell.tagName.toLowerCase() === "th";
				cell.setAttribute("role", isHeader ? "columnheader" : "gridcell");
				cell.setAttribute("aria-colindex", String(cellIndex + 1));
			});
		});
	}

	/**
	 * Emit normalized selection detail for parent listeners.
	 */
	emitSelectionChanged(replaceSelection : boolean = false)
	{
		const selectedRows = this.host.rows.filter((row) => this.selectedRowIds.has(row.id)).map((row) => this.host._rowDataFor(row));
		const detail : Et2DatagridSelectionDetail = {
			selectedRowIds: Array.from(this.selectedRowIds),
			allSelected: this.allSelected,
			selectedRows,
			activeRowId: this.activeRowId,
			activeRowIndex: this.activeRowIndex,
			replaceSelection
		};
		this.host.dispatchEvent(new CustomEvent("et2-selection-changed", {
			detail,
			bubbles: true,
			composed: true,
			cancelable: true
		}));
	}
}
