import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import shoelace from "../Styles/shoelace";
import {et2_warnLegacyEventHandler, Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2Template} from "../Et2Template/Et2Template";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import styles from "./Et2Datagrid.styles";
import {virtualize, virtualizerRef} from "@lit-labs/virtualizer/virtualize.js";
import {FlowLayout} from "@lit-labs/virtualizer/layouts/flow.js";
import {grid} from "@lit-labs/virtualizer/layouts/grid.js";
import {
	Et2DatagridColumn,
	Et2DatagridDataProvider,
	Et2DatagridExpansionConfig,
	Et2DatagridPageResult,
	Et2DatagridRefreshResult,
	Et2DatagridRow,
	Et2DatagridRowCustomizer,
	Et2DatagridSelectionDetail,
	Et2DatagridSelectionMode,
	Et2DatagridTemplateData,
	Et2DatagridUpdateType,
	Et2DatagridUpdateTypes,
	Et2DatagridView
} from "./Et2Datagrid.types";
import {Et2DatagridColumnManager, Et2DatagridColumnResizeDragState} from "./Et2DatagridColumnManager";
import type {Et2DatagridColumnSelectionItem} from "./Et2DatagridColumnState";
import {Et2DatagridColumnState} from "./Et2DatagridColumnState";
import {Et2RowProvider} from "./Et2RowProvider";
import {styleMap} from "lit/directives/style-map.js";
import interact from "@interactjs/interactjs";
import type {InteractEvent} from "@interactjs/core/InteractEvent";
import {et2_arrayMgr} from "../et2_core_arrayMgr";
import {et2_compileLegacyJS} from "../et2_core_legacyJSFunctions";

interface Et2DatagridCustomfieldColumnState
{
	customfields : Record<string, any>;
	visibility : Record<string, boolean> | null;
	visibleFieldNames : string[];
}

type Et2DatagridRenderItem =
	| { type : "row"; rowIndex : number }
	| { type : "expanded"; rowIndex : number; parentRowId : string };

export type Et2DatagridRowsSnapshot = {
	rowsByIndex : Array<Et2DatagridRow | null>;
	total : number | null;
	displayedRowIds : string[];
	hasFetchedOnce : boolean;
};

const DEFAULT_TILE_LAYOUT = {
	// @lit-labs/virtualizer parses grid itemSize, gap and padding as pixel numbers internally.
	// Keep these defaults in px so spacing does not collapse when passed through the grid layout.
	width: "150px",
	height: "120px",
	gap: "4px",
	padding: "4px"
} as const;
type Et2DatagridVirtualItem = number | Et2DatagridRenderItem;
type Et2DatagridRowHeightSource = "default" | "template" | "css" | "parent" | "measured" | "api";

/**
 * Fixed-pitch flow layout with sparse overrides for expanded branches.
 *
 * All ordinary rows use `_itemSize.height`; only expanded virtual items are
 * recorded. This keeps an offscreen branch from changing FlowLayout's average
 * item size and therefore the parent scrollbar extent.
 */
class Et2DatagridSparseFlowLayout extends FlowLayout
{
	expandedItemHeights : Map<number, number> = new Map();

	private _heights() : Map<number, number>
	{
		return this.expandedItemHeights instanceof Map ? this.expandedItemHeights : new Map();
	}

	private _rowHeight() : number
	{
		return Math.max(1, this._itemSize?.height || 1);
	}

	_getSize(index : number) : number
	{
		return this._heights().get(index) ?? this._rowHeight();
	}

	_getAverageSize() : number
	{
		return this._rowHeight();
	}

	_getPosition(index : number) : number
	{
		const rowHeight = this._rowHeight();
		let position = index * rowHeight;
		for(const [expandedIndex, expandedHeight] of this._heights())
		{
			if(expandedIndex >= index)
			{
				continue;
			}
			position += expandedHeight - rowHeight;
		}
		return position;
	}

	_calculateAnchor(lower : number, upper : number) : number
	{
		if(!this.items.length)
		{
			return 0;
		}
		const target = Math.max(0, (lower + upper) / 2);
		let low = 0;
		let high = this.items.length - 1;
		while(low < high)
		{
			const middle = Math.floor((low + high + 1) / 2);
			if(this._getPosition(middle) <= target)
			{
				low = middle;
			}
			else
			{
				high = middle - 1;
			}
		}
		return low;
	}

	/**
	 * FlowLayout's default error correction compares positions to `index ×
	 * averageSize`. Sparse branch positions are already exact, so that comparison
	 * would incorrectly shift the physical range by every expanded-height delta.
	 */
	_calculateError() : number
	{
		return 0;
	}

	/**
	 * A virtualizer host with no children can report a zero-height clipping
	 * rectangle. FlowLayout normally clears its range in that case, which leaves
	 * this tbody empty forever. Render one initial overhang instead so Lit can
	 * apply the known scroll extent and ResizeObserver can establish the real
	 * viewport on the next pass.
	 */
	_getActiveItems()
	{
		if(this.items.length && this._viewDim1 === 0)
		{
			// FlowLayout keeps the raw viewport dimensions private. This temporary
			// bootstrap override is required only until its first ResizeObserver pass.
			const internalLayout = this as any;
			const viewportSize = internalLayout._viewportSize[this._sizeDim];
			internalLayout._viewportSize[this._sizeDim] = Math.min(this._scrollSize, this._overhang);
			super._getActiveItems();
			internalLayout._viewportSize[this._sizeDim] = viewportSize;
			return;
		}
		super._getActiveItems();
	}

	_updateScrollSize()
	{
		const rowHeight = this._rowHeight();
		let height = this.items.length * rowHeight;
		for(const [expandedIndex, expandedHeight] of this._heights())
		{
			if(expandedIndex < this.items.length)
			{
				height += expandedHeight - rowHeight;
			}
		}
		(this as any)._scrollSize = Math.max(1, height);
	}

	notifyExpandedItemHeightChanged()
	{
		this._scheduleReflow();
	}
}

/**
 * @summary Virtualized data grid for infinite rows with column sizing, selection, and lazy paging.
 *
 * @event et2-loading-start - Fired when one or more row fetch requests are dispatched.
 * @event et2-loading-done - Fired when row data is ready: after all in-flight fetches complete, or after setInitialRows() seeds preloaded rows.
 * @event et2-loading-error - Fired when a row fetch request fails.
 * @event et2-selection-changed - Fired when row selection changes.
 * @event et2-active-row-changed - Fired when keyboard or pointer navigation changes the active row.
 * @event et2-columns-changed - Fired when column order, width, or visibility changes.
 *
 * @slot header - Header content used when no column definitions are available.
 * @slot noResults - Optional empty-state content shown when there are no rows.
 * @slot expand-icon - Optional icon shown for collapsed expandable rows.
 * @slot collapse-icon - Optional icon shown for expanded rows.
 *
 * @csspart base - Root wrapper around the grid header and body.
 * @csspart header - Visible column header row container.
 * @csspart body - Scrollable container for state content and table.
 * @csspart state - State message container (loading, empty, template missing, or fetch error).
 * @csspart state-action-menu - Empty-state action menu button.
 * @csspart resize-helper - Helper bar shown while resizing a column.
 * @csspart table - Internal table element with ARIA grid semantics.
 * @csspart rows - Table body that hosts virtualized row content.
 * @csspart meta-column - Leading header column used for row metadata indicators.
 * @csspart row-meta - Leading per-row metadata cell (column 0), customizable by consumers.
 * @csspart row-expander - Expand/collapse button rendered in the row metadata cell.
 * @csspart row-expander-icon - Icon wrapper inside the row expander button.
 * @csspart expanded-row - Cell containing consumer-provided expanded row content.
 * @csspart column - A visible header column wrapper.
 * @csspart column-selection - Column selection action container in the header.
 *
 * @cssproperty [--row-height=44px] - Estimated row height used for spacer rendering.
 * @cssproperty [--row-cell-max-height=10em] - Maximum height for individual row cells before vertical scrolling.
 * @cssproperty [--meta-column-width=0px] - Width of leading metadata column; expandable grids calculate a width large enough for the expander when it is not supplied.
 * @cssproperty [--row-expander-size=var(--sl-spacing-large)] - Width and height of the row expand/collapse button.
 * @cssproperty [--row-expander-icon-size=0.5em] - Size of the default CSS triangle expander icon.
 * @cssproperty [--column-sizes] - Internal: automatically calculated grid-template column tracks for header and body rows.
 * @cssproperty [--column-count=1] - Internal: automatically calculated visible column count; `1` is only the CSS fallback.
 * @cssproperty [--scrollbar-space=0px] - Internal: automatically calculated header space reserved for body scrollbar alignment.
 * @cssproperty [--column-selection-width=16px] - Internal: automatically calculated width of the header column-selection action.
 * @cssproperty [--embedded-virtualized-height=auto] - Internal: automatically synchronized reserved height for an embedded virtualized grid with no own scrollbar.
 */
@customElement("et2-datagrid")
export class Et2Datagrid extends Et2Widget(LitElement)
{

	/**
	 * Compose datagrid styles from shared shoelace/widget styles and local datagrid CSS.
	 */
	static get styles()
	{
		return [
			shoelace,
			super.styles,
			styles
		];
	}

	/**
	 * True while a fetch cycle is active, including initial and incremental page loads.
	 */
	@state()
	loading : boolean = false;

	/**
	 * Guard flag used to prevent overlapping `fetchPage()` calls.
	 */
	@state()
	fetching : boolean = false;

	/**
	 * Total row count reported by provider, or `null` when unknown.
	 */
	@state()
	total : number | null = null;

	/**
	 * Rows currently materialized in the DOM/in-memory list.
	 */
	@state()
	rows : Et2DatagridRow[] = [];

	@state()
	private _rowsByIndex : Array<Et2DatagridRow | null> = [];

	private _rowRenderVersionById : Map<string, number> = new Map();
	/**
	 * Rows temporarily rendered in full for print output. Keeping this separate
	 * from `_rowsByIndex` leaves normal paging and virtualization untouched.
	 */
	private _printRows : Et2DatagridRow[] | null = null;
	/** Fixed-row-height state to restore after print rows return to virtualization. */
	private _printFixedRowHeight : boolean | null = null;
	private _refreshPulseTimersByElement : Map<HTMLElement, number> = new Map();
	private _refreshPulseDurationMs : number = 5000;
	private static _browserScrollbarSpacePx : number | null = null;
	/**
	 * Quiet period before treating shared-scroll layout as settled. 200ms is an
	 * empirically chosen debounce that has worked well for wheel and drag
	 * scrolling: short enough to repair a dropped child range promptly, while
	 * avoiding a corrective layout during the same scroll gesture.
	 */
	private static readonly _embeddedScrollSettleDelayMs : number = 200;
	private _virtualIndexes : number[] = [];
	private _virtualIndexesCount : number = -1;
	private _virtualItems : Et2DatagridVirtualItem[] = [];
	private _virtualItemsSignature : string = "";
	private _expandedVirtualItemHeights : Map<number, number> = new Map();
	private _expandedRowHeightByParentRowId : Map<string, number> = new Map();
	private _rowHeightPx : number = 44;
	private _rowHeightLocked : boolean = false;
	private _rowHeightSource : Et2DatagridRowHeightSource = "default";
	// Grids with expansion/subgrids need a deterministic row pitch. Once their
	// first upgraded row batch establishes a height, keep it stable until
	// rows/template reset so later pages and expansions do not move the scrollbar.
	private _rowHeightSettled : boolean = false;
	private _embeddedRowHeightSettled : boolean = false;
	private _sparseVirtualizerLayoutActive : boolean = false;
	private _sparseVirtualizerLayoutFrame : number | null = null;
	private _measuredRowHeightByRowId : Map<string, number> = new Map();
	private _rowWidgetsUpgradedFrame : number | null = null;
	private _rowWidgetsUpgradeSettling : boolean = false;
	private _embeddedVirtualizedHeightSyncPendingAfterRowUpgrade : boolean = false;
	private _embeddedVirtualizedMeasuredRowHeightPx : number | null = null;
	private _embeddedVirtualizedHostHeight : string | null = null;
	private _embeddedVirtualizedHeightFrame : number | null = null;
	private _embeddedVirtualizedHeightSyncPassesRemaining : number = 0;
	private _embeddedParentScrollOffsetTop : number | null = null;
	private _embeddedChildGridResizeObserver : ResizeObserver | null = null;
	private _embeddedChildGridObserverSyncFrame : number | null = null;
	private _remeasuredEmbeddedChildGridsThisFrame : WeakSet<Et2Datagrid> = new WeakSet();
	private _embeddedChildScrollSyncFrame : number | null = null;
	private _embeddedChildScrollSettleTimer : number | null = null;
	private _embeddedSelfScrollSyncFrame : number | null = null;
	private _rowsMinHeightFrame : number | null = null;
	private _virtualizerLayoutSyncFrame : number | null = null;
	private _reconnectStuckVirtualizerScheduled : boolean = false;
	private _templateHandlerListeners : Map<string, EventListener> = new Map();
	private _templateHandlerCache : Map<string, Function | false> = new Map();
	private _rowTemplateHandlerCache : WeakMap<HTMLElement, Map<string, Function | false>> = new WeakMap();
	private _loggedExpansionRowHeightWarning : boolean = false;

	/**
	 * Error state set when the latest fetch failed.
	 */
	@state()
	fetchFailed : boolean = false;

	/**
	 * Optional provider error message shown in error state.
	 */
	@state()
	fetchErrorMessage : string = "";

	/**
	 * Tracks whether at least one fetch finished (success or error) for empty-state messaging.
	 */
	@state()
	private _hasFetchedOnce : boolean = false;

	/**
	 * Number of skeleton placeholder rows reserved for in-flight requests.
	 */
	@state()
	private _pendingPlaceholderCount : number = 0;
	private _pendingPlaceholderRequests : Map<string, { start : number; requestedCount : number }> = new Map();

	@state()
	private _resizeHelperLeftPx : number | null = null;

	@state()
	private _resizeHelperWidthPx : number | null = null;

	@state()
	private _resizeLimitState : "min" | "max" | null = null;

	/**
	 * Visible column configuration, including sizing and optional hide expressions.
	 */
	@property({attribute: false})
	columns : Et2DatagridColumn[] = [];

	/**
	 * Paging adapter used by infinite scroll to fetch additional rows from the server.
	 */
	@property({attribute: false})
	dataProvider : Et2DatagridDataProvider | null = null;

	/**
	 * Optional hook invoked for each realized row to customize row/meta-cell presentation.
	 */
	@property({attribute: false})
	rowCustomizer : Et2DatagridRowCustomizer | null = null;

	/**
	 * Prepared template and metadata used to render each row.
	 */
	@property({attribute: false})
	templateData : Et2DatagridTemplateData | null = null;

	/**
	 * Visual layout mode. Row is the default table layout.
	 */
	@property({type: String, reflect: true})
	view : Et2DatagridView = "row";

	@property({type: Array, attribute: false})
	rowStylesheets : CSSStyleSheet[] = [];

	/**
	 * Maximum number of rows requested per page load.
	 */
	@property({type: Number})
	pageSize : number = 50;

	/**
	 * Row selection behavior: `none`, `single`, or `multiple`.
	 */
	@property({type: String, attribute: "selection-mode"})
	selectionMode : Et2DatagridSelectionMode = "multiple";

	/**
	 * Hide the column chooser action in the header when true.
	 */
	@property({type: Boolean})
	noColumnSelection : boolean = false;

	/**
	 * Disable loading and saving column preferences. Useful for child grids whose
	 * columns are owned by a parent grid.
	 */
	@property({type: Boolean})
	noColumnPersistence : boolean = false;

	/**
	 * Disable interactive column resizing for grids whose column sizing is owned
	 * by another component.
	 */
	@property({type: Boolean})
	noColumnResize : boolean = false;

	/**
	 * Hide only the visible header row. The table `<thead>` remains rendered for
	 * accessibility and sizing semantics.
	 */
	@property({type: Boolean, attribute: "no-visible-header"})
	noVisibleHeader : boolean = false;

	/**
	 * Let `--column-sizes` inherit from the host instead of computing it from
	 * local columns. Used by child grids whose visual tracks are owned by a
	 * parent grid, while local columns still define cell order/visibility.
	 */
	@property({type: Boolean, attribute: "inherit-column-sizes"})
	inheritColumnSizes : boolean = false;

	/**
	 * Let the grid grow to fit its rows instead of creating its own scroll body.
	 * Used for expanded child grids so the parent grid remains the only vertical
	 * scroller.
	 */
	@property({type: Boolean, attribute: "auto-height", reflect: true})
	autoHeight : boolean = false;

	/**
	 * Render as an embedded virtualized grid inside an ancestor scrollport.
	 *
	 * Unlike simple auto-height, this mode keeps lazy paging but does not create
	 * an independent scrollport. It starts with one loading row, then keeps the
	 * host, root CSS variable, and virtualizer body height synchronized to the
	 * larger of the virtualizer estimate and the actual rendered row stack.
	 */
	@property({type: Boolean, attribute: "embedded-virtualized", reflect: true})
	embeddedVirtualized : boolean = false;

	/**
	 * Reflects whether CSS rows need a fixed height: either an explicit row-height
	 * contract or an embedded subgrid's reserved virtualizer pitch. Fixed-height
	 * rows clip cell content to keep visual rows aligned with virtualizer math.
	 */
	@property({type: Boolean, attribute: "fixed-row-height", reflect: true})
	fixedRowHeight : boolean = false;

	/**
	 * Current row-height estimate used by virtualized layout and propagated to
	 * embedded child grids. Fixed template heights remain authoritative.
	 */
	get rowHeightEstimatePx() : number
	{
		return this._effectiveRowHeightPx();
	}

	/**
	 * Apply a parent row-height estimate as a floor for this grid. A locked
	 * embedded child keeps the inherited pitch; fixed-height row templates do
	 * not accept parent estimates.
	 */
	setRowHeightEstimate(rowHeightPx : number, lockToParent : boolean = false)
	{
		if(this._rowHeightSource === "template")
		{
			return;
		}
		const rowHeight = Math.ceil(Number(rowHeightPx) || 0);
		if(rowHeight <= 0)
		{
			return;
		}
		if(lockToParent)
		{
			this._setRowHeight(rowHeight, "parent");
			this._rowHeightLocked = true;
			this._rowHeightSettled = true;
			this._embeddedRowHeightSettled = true;
			this.fixedRowHeight = true;
		}
		else if(rowHeight <= this._rowHeightPx + 1)
		{
			return;
		}
		else
		{
			this._setRowHeight(rowHeight, "api");
		}
		this.requestUpdate();
		if(this.embeddedVirtualized)
		{
			this._scheduleEmbeddedVirtualizedHeightSync();
		}
		this._syncEmbeddedChildGridRowHeightEstimates();
	}

	/** Re-read a consumer-provided `--row-height` after dynamic CSS changes. */
	refreshRowHeightFromCss()
	{
		if(this._rowHeightSource !== "template")
		{
			if(this.style.getPropertyValue("--row-height") === `${this._rowHeightPx}px`)
			{
				this.style.removeProperty("--row-height");
			}
			this._rowHeightSource = "default";
		}
		this._syncTemplateRowHeightHint(true);
		this._syncFixedRowHeightMode();
		this._scheduleSparseVirtualizerLayoutActivation();
		this._scheduleVirtualizerLayoutSync();
		if(this.embeddedVirtualized)
		{
			this._scheduleEmbeddedVirtualizedHeightSync();
		}
		this._syncEmbeddedChildGridRowHeightEstimates();
	}

	/**
	 * Automatically mark the first loaded row active. Subgrids disable this so
	 * simply expanding a row does not create multiple active rows.
	 */
	@property({type: Boolean})
	autoActivateFirstRow : boolean = true;

	/**
	 * Parent row id when this grid is rendered as expanded child content.
	 */
	@property({attribute: false})
	parentRowId : string = "";

	/**
	 * Optional generic expanded-row hooks supplied by consumers such as Et2Nextmatch.
	 */
	@property({attribute: false})
	expansionConfig : Et2DatagridExpansionConfig | null = null;

	/**
	 * External loading flag for configuration/template setup before first data render.
	 */
	@property({type: Boolean, attribute: "configuration-loading"})
	configurationLoading : boolean = false;

	/**
	 * Optional replacement for the default empty-state headline text.
	 * Keeps default empty-state template structure while allowing Nextmatch-level customization.
	 */
	@property({type: String, attribute: "empty-state-text"})
	emptyStateText : string = "";

	/**
	 * Show an empty-state action menu button. The button dispatches a composed
	 * contextmenu event from the empty row so owners can use their normal row
	 * action-menu routing.
	 */
	@property({type: Boolean, attribute: "empty-state-action-menu"})
	emptyStateActionMenu : boolean = false;

	/**
	 * Optional explicit preference key for persisted column state.
	 * When omitted, datagrid derives key from owner component + row template id.
	 */
	@property({type: String, attribute: "column-preference-name"})
	columnPreferenceName : string = "";

	/** Set of row ids already added, used to avoid duplicate render on incremental fetches. */
	private displayedRowIds : Set<string> = new Set();
	/** Set of selected row ids used to derive emitted selection payloads. */
	private selectedRowIds : Set<string> = new Set();
	private allSelected : boolean = false;
	/** Anchor index for shift-range selection semantics. */
	private anchorRowIndex : number = -1;
	/** Keyboard/pointer active row index in currently loaded rows. */
	private activeRowIndex : number = -1;
	/** Active row id mirrored from `activeRowIndex` for event payload convenience. */
	private activeRowId : string | null = null;
	private _initialExportParts : string[] = [];
	private _scrollListener : (() => void) | null = null;
	private _scrollListenerBody : HTMLElement | null = null;
	private _bodyScrollVersion : number = 0;
	private _lastBodyScrollAt : number = 0;
	private _deferredEmbeddedRemeasureTimer : number | null = null;
	private _deferredEmbeddedRemeasureChildGrids : Set<Et2Datagrid> = new Set();
	private _inFlightRequestKeys : Set<string> = new Set();
	private _completedRequestKeys : Set<string> = new Set();
	private static readonly SLOW_FETCH_TIMEOUT_MS = 30000;
	/** Every fetch currently in flight for this grid, so "give up" can abort all of them
	 *  at once. Membership doubles as the "was this discarded?" signal for catch blocks -
	 *  see _giveUpOnPendingFetches(). */
	private _inFlightFetchPromises : Set<Promise<Et2DatagridPageResult> & { abort? : () => void }> = new Set();
	private _slowFetchTimers : Set<number> = new Set();
	/** At most one at a time - a later timer firing while this is set just no-ops. */
	private _slowFetchDialog : Et2Dialog | null = null;
	private _queuedRequestTimer : number | null = null;
	private _queuedRequests : Map<string, { start : number; requestedCount : number; requestKey : string }> = new Map();
	private _requestDispatchDelayMs : number = 100;
	private _rowUpgradeObserver : MutationObserver | null = null;
	private _rowUpgradeObservedRowsBody : HTMLElement | null = null;
	private _rowUpgradeRangeListener : ((event : Event) => void) | null = null;
	private _rowUpgradeQueue : HTMLElement[] = [];
	private _rowUpgradeScheduled : boolean = false;
	private _rowUpgradeFrameHandle : number | null = null;
	private _rowUpgradeBatchSize : number = 8;
	/** Per-frame time budget (ms) for row widget upgrades to avoid long tasks on the main thread. */
	private _rowUpgradeFrameBudgetMs : number = 8;
	/** Stable source-order keys from template parsing; used to map row cells after column reordering. */
	private _sourceColumnKeys : string[] = [];
	private _restoreFocusAfterRender : boolean = false;
	private _lastPointerToggleSelect : boolean = false;
	private _pendingOffscreenKeyboardNavigation : boolean = false;
	private _columnResizeDrag : Et2DatagridColumnResizeDragState | null = null;
	private _columnResizeHandles : HTMLElement[] = [];
	private _columnManager : Et2DatagridColumnManager = new Et2DatagridColumnManager();
	private _columnState : Et2DatagridColumnState = new Et2DatagridColumnState();
	private _scrollbarSpacePx : number = 0;
	private _customfieldColumnStateByKey : Map<string, Et2DatagridCustomfieldColumnState> = new Map();
	private _internalExpandedRowIds : Set<string> = new Set();
	private _loadedColumnPreferenceKey : string | null = null;
	private _postRenderStructureSyncNeeded : boolean = false;
	private _loggedMissingTemplateWarning : boolean = false;


	/**
	 * A fake list-looking SVG that looks like the grid is working
	 */
	private _et2LoadingTemplate() : TemplateResult
	{
		// Use a fake list loader
		return  html`
			<svg viewBox="0 0 100 100" width="100%" height="100%" preserveAspectRatio="none"
				 xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<style>
						.dg-loader__header {
							fill: var(--sl-color-neutral-100, #e8e8e8);
						}

						.dg-loader__body {
							fill: var(--sl-color-neutral-0, #ffffff);
						}

						.dg-loader__line {
							stroke: var(--sl-color-neutral-200, rgba(0, 0, 0, 0.08));
							stroke-width: 0.15;
							vector-effect: non-scaling-stroke;
						}
					</style>

				<!-- Wipe animation
				<defs>
					<linearGradient id="shimmer" x1="-1" y1="0" x2="0" y2="0">
						<stop offset="0%" stop-color="transparent"></stop>
						<stop offset="35%" stop-color="transparent"></stop>
						<stop offset="50%" stop-color="var(--sl-color-gray-50)"
							  stop-opacity="0.45"></stop>
						<stop offset="65%" stop-color="transparent"></stop>
						<stop offset="100%" stop-color="transparent"></stop>

						<animateTransform attributeName="gradientTransform" type="translate" from="-1 0" to="2 0"
										  dur="2.2s" repeatCount="indefinite"></animateTransform>
					</linearGradient>
				</defs>
				-->

				<!-- background -->
				<rect class="dg-loader__body" width="100%" height="100%"></rect>

				<!-- header -->
				<rect class="dg-loader__header" width="100%" height="6.5%"></rect>

				<!-- 15 row separators -->
				<g class="dg-loader__line">
					<line x1="0%" y1="12.9%" x2="100%" y2="12.9%"></line>
					<line x1="0%" y1="19.3%" x2="100%" y2="19.3%"></line>
					<line x1="0%" y1="25.7%" x2="100%" y2="25.7%"></line>
					<line x1="0%" y1="32.1%" x2="100%" y2="32.1%"></line>
					<line x1="0%" y1="38.5%" x2="100%" y2="38.5%"></line>
					<line x1="0%" y1="44.9%" x2="100%" y2="44.9%"></line>
					<line x1="0%" y1="51.3%" x2="100%" y2="51.3%"></line>
					<line x1="0%" y1="57.7%" x2="100%" y2="57.7%"></line>
					<line x1="0%" y1="64.1%" x2="100%" y2="64.1%"></line>
					<line x1="0%" y1="70.5%" x2="100%" y2="70.5%"></line>
					<line x1="0%" y1="76.9%" x2="100%" y2="76.9%"></line>
					<line x1="0%" y1="83.3%" x2="100%" y2="83.3%"></line>
					<line x1="0%" y1="89.7%" x2="100%" y2="89.7%"></line>
					<line x1="0%" y1="96.1%" x2="100%" y2="96.1%"></line>
				</g>

				<!-- shimmer overlay -->
				<rect x="0" y="6.5%" width="100%" height="93.5%" fill="url(#shimmer)"></rect>
			</svg>
		`;
	}

	/**
	 * Reuse Et2Template error visuals for consistency with the rest of eTemplate.
	 */
	private _et2ErrorTemplate(errorMessage : string) : TemplateResult
	{
		return Et2Template.prototype.errorTemplate.call(this as unknown as Et2Template, errorMessage);
	}


	/**
	 * Convenience accessor for table body element.
	 */
	private get _rowsBody() : HTMLElement | null
	{
		return this.shadowRoot?.getElementById("rows") ?? null;
	}

	/**
	 * Convenience accessor for scroll container.
	 */
	private get _body() : HTMLElement | null
	{
		return this.shadowRoot?.querySelector(".dg-body") as HTMLElement | null;
	}

	/**
	 * Convenience accessor for focus fallback target that keeps keydown events routed to the grid.
	 */
	private get _gridTable() : HTMLElement | null
	{
		return this.shadowRoot?.querySelector("[role='grid']") as HTMLElement | null;
	}

	private get _virtualize()
	{
		return this._rowsBody?.[virtualizerRef];
	}

	/**
	 * Bind event handlers once so add/remove listeners and template callbacks keep stable references.
	 */
	constructor()
	{
		super();
		this._scrollbarSpacePx = this._browserScrollbarSpace();
		this._handleTableClick = this._handleTableClick.bind(this);
		this._handleTablePointerDown = this._handleTablePointerDown.bind(this);
		this._handleTableKeydown = this._handleTableKeydown.bind(this);
		this._handleColumnResizeStart = this._handleColumnResizeStart.bind(this);
		this._handleColumnResizeMove = this._handleColumnResizeMove.bind(this);
		this._handleColumnResizeEnd = this._handleColumnResizeEnd.bind(this);
		this._scrollListener = () =>
		{
			this._bodyScrollVersion++;
			this._lastBodyScrollAt = performance.now();
			this._maybePrefetchOnScroll();
			this._scheduleEmbeddedChildScrollSync(this._body);
		};
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._syncTemplateRowHeightHint();
		this._syncTemplateHandlerListeners();
		this.addEventListener("et2-embedded-height", this._handleEmbeddedHeightEvent as EventListener);
		this._reconnectStuckVirtualizer();
	}

	/**
	 * A caller reparenting an ancestor of this element (eg. an anonymous filemanager
	 * share moving its containing form) can leave the virtualizer's AsyncDirective
	 * disconnected without this host ever receiving a matching reconnect - confirmed
	 * live against a real anonymous filemanager share. The virtualizer's own
	 * _connected flag (and the ResizeObserver it tore down in disconnected()) never
	 * come back on their own, so every later data arrival is silently dropped by
	 * _updateLayout()'s `this._layout && this._connected` guard, and #rows stays
	 * empty forever - not fixable by a window resize, since the virtualizer is
	 * asleep rather than mismeasuring. Detect that specific stuck state and wake it
	 * back up directly. Checked from both connectedCallback() (a real host
	 * disconnect/reconnect) and updated() (the confirmed failure path: the stuck
	 * disconnect happens at the directive/part level, without this host ever
	 * reconnecting again, so connectedCallback() alone never gets another chance).
	 *
	 * Deferred to a microtask: calling virtualizer.connected() synchronously from
	 * inside updated() re-enters the virtualizer's own update cascade while Lit is
	 * still mid-commit, which can leave it perpetually scheduling another update
	 * instead of settling. requestAnimationFrame would also defer past that, but a
	 * backgrounded/non-rendering tab can leave it unfired indefinitely (confirmed
	 * live); a microtask always runs promptly regardless of tab visibility.
	 */
	private _reconnectStuckVirtualizer() : void
	{
		if(this._reconnectStuckVirtualizerScheduled)
		{
			return;
		}
		this._reconnectStuckVirtualizerScheduled = true;
		// A microtask, not requestAnimationFrame: this only needs to happen after the
		// current synchronous update finishes (to avoid re-entering the virtualizer's
		// own update cascade while Lit is still mid-commit - see class doc above). A
		// rAF callback can go unfired indefinitely in a backgrounded/non-rendering tab
		// (confirmed live: zero rAF callbacks fired for several seconds on a real
		// anonymous-share tab), which a microtask is never subject to.
		void Promise.resolve().then(() =>
		{
			this._reconnectStuckVirtualizerScheduled = false;
			// Print rows intentionally stop rendering via virtualize() (render()'s
			// `this._printRows ? ... : virtualize({...})` branch), which legitimately
			// disconnects the directive - that must stay disconnected while printing,
			// not get healed back to life here.
			if(this._printRows)
			{
				return;
			}
			const virtualizer = this._virtualize as any;
			if(this.isConnected && virtualizer?._connected === false && typeof virtualizer.connected === "function")
			{
				virtualizer.connected();
			}
		});
	}

	/**
	 * Disconnect DOM listeners and queued async work when component is detached.
	 */
	disconnectedCallback()
	{
		this._syncTemplateHandlerListeners(new Set());
		this.removeEventListener("et2-embedded-height", this._handleEmbeddedHeightEvent as EventListener);
		this._teardownColumnResizeInteract();
		this._clearColumnResizeDragState();
		this._rowUpgradeObserver?.disconnect();
		this._rowUpgradeObserver = null;
		if(this._rowUpgradeRangeListener)
		{
			this._rowUpgradeObservedRowsBody?.removeEventListener("rangeChanged", this._rowUpgradeRangeListener);
		}
		this._rowUpgradeRangeListener = null;
		this._rowUpgradeObservedRowsBody = null;
		this._clearRowUpgradeQueue();
		if(this._rowWidgetsUpgradedFrame !== null)
		{
			cancelAnimationFrame(this._rowWidgetsUpgradedFrame);
			this._rowWidgetsUpgradedFrame = null;
		}
		this._rowWidgetsUpgradeSettling = false;
		this._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade = false;
		if(this._scrollListenerBody && this._scrollListener)
		{
			this._scrollListenerBody.removeEventListener("scroll", this._scrollListener);
			this._scrollListenerBody = null;
		}
		this._clearRefreshPulseTimers();
		if(this._embeddedVirtualizedHeightFrame !== null)
		{
			cancelAnimationFrame(this._embeddedVirtualizedHeightFrame);
			this._embeddedVirtualizedHeightFrame = null;
		}
		this._embeddedVirtualizedHeightSyncPassesRemaining = 0;
		this._embeddedChildGridResizeObserver?.disconnect();
		this._embeddedChildGridResizeObserver = null;
		if(this._embeddedChildGridObserverSyncFrame !== null)
		{
			cancelAnimationFrame(this._embeddedChildGridObserverSyncFrame);
			this._embeddedChildGridObserverSyncFrame = null;
		}
		if(this._embeddedChildScrollSyncFrame !== null)
		{
			cancelAnimationFrame(this._embeddedChildScrollSyncFrame);
			this._embeddedChildScrollSyncFrame = null;
		}
		if(this._embeddedChildScrollSettleTimer !== null)
		{
			clearTimeout(this._embeddedChildScrollSettleTimer);
			this._embeddedChildScrollSettleTimer = null;
		}
		if(this._embeddedSelfScrollSyncFrame !== null)
		{
			cancelAnimationFrame(this._embeddedSelfScrollSyncFrame);
			this._embeddedSelfScrollSyncFrame = null;
		}
		if(this._deferredEmbeddedRemeasureTimer !== null)
		{
			clearTimeout(this._deferredEmbeddedRemeasureTimer);
			this._deferredEmbeddedRemeasureTimer = null;
		}
		this._deferredEmbeddedRemeasureChildGrids.clear();
		if(this._rowsMinHeightFrame !== null)
		{
			cancelAnimationFrame(this._rowsMinHeightFrame);
			this._rowsMinHeightFrame = null;
		}
		if(this._virtualizerLayoutSyncFrame !== null)
		{
			cancelAnimationFrame(this._virtualizerLayoutSyncFrame);
			this._virtualizerLayoutSyncFrame = null;
		}
		if(this._sparseVirtualizerLayoutFrame !== null)
		{
			cancelAnimationFrame(this._sparseVirtualizerLayoutFrame);
			this._sparseVirtualizerLayoutFrame = null;
		}
		for(const timer of this._slowFetchTimers)
		{
			window.clearTimeout(timer);
		}
		this._slowFetchTimers.clear();
		this._slowFetchDialog?.destroy?.();
		this._slowFetchDialog = null;
		this._inFlightFetchPromises.clear();
		super.disconnectedCallback();
	}

	/**
	 * Finish one-time setup after first paint.
	 */
	firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);
		this._syncDomEventTargets();
		this._scheduleSparseVirtualizerLayoutActivation();
		this._setupColumnResizeInteract();
	}

	/**
	 * Apply structure-affecting state before render so Lit can absorb it in the current cycle.
	 */
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);
		if(this.hasAttribute("exportparts") && this._initialExportParts.length == 0)
		{
			this._initialExportParts = this.getAttribute("exportparts")?.split(",").map(p => p.trim());
		}
		if(
			changedProperties.has("templateData") ||
			changedProperties.has("view") ||
			changedProperties.has("columnPreferenceName") ||
			changedProperties.has("noColumnPersistence") ||
			changedProperties.has("noVisibleHeader")
		)
		{
			this._loadedColumnPreferenceKey = null;
		}
		this.classList.toggle("dg-has-expanders", !!this.expansionConfig);
		if(changedProperties.has("expansionConfig"))
		{
			this._syncFixedRowHeightMode();
		}
		const columnsBeforePreferenceLoad = this.columns;
		if(
			changedProperties.has("templateData") ||
			changedProperties.has("view") ||
			changedProperties.has("columns") ||
			changedProperties.has("columnPreferenceName") ||
			changedProperties.has("noColumnPersistence") ||
			changedProperties.has("noVisibleHeader")
		)
		{
			this._loadColumnPreferencesIfNeeded();
		}
		const columnsChanged = changedProperties.has("columns") || this.columns !== columnsBeforePreferenceLoad;
		const structureChanged = changedProperties.has("templateData") || changedProperties.has("view") || columnsChanged;
		if(changedProperties.has("templateData"))
		{
			this._syncTemplateRowHeightHint();
			this._templateHandlerCache.clear();
			this._rowTemplateHandlerCache = new WeakMap();
			this._syncTemplateHandlerListeners();
			// Capture source cell->column mapping before user reorders columns.
			this._sourceColumnKeys = (this.templateData?.sourceColumns || this.templateData?.columns || this.columns || []).map((column) => String(column.key));
		}
		if(structureChanged)
		{
			this._prepareVisibleHeaders();
			this._updateExportParts();
			this._reconcileRowRenderState(false);
			this._postRenderStructureSyncNeeded = true;
		}
		if(columnsChanged)
		{
			this._rebuildCustomfieldColumnStateCache();
		}
	}

	/**
	 * Attach one capturing listener per event type used by the row template.
	 * Row widgets are virtualized, so binding handlers on the grid keeps listener
	 * count independent from both the number of rows and their redraws.
	 */
	private _syncTemplateHandlerListeners(eventTypes? : Set<string>)
	{
		const required = eventTypes ?? new Set(
			Object.values(this.templateData?.rowTemplateHandlerMap || {})
				.flatMap((handlers) => Object.keys(handlers))
				.filter((attribute) => attribute.startsWith("on"))
				.map((attribute) => attribute.substring(2))
		);
		for(const [eventType, listener] of this._templateHandlerListeners)
		{
			if(!required.has(eventType))
			{
				this.removeEventListener(eventType, listener, true);
				this._templateHandlerListeners.delete(eventType);
			}
		}
		for(const eventType of required)
		{
			if(this._templateHandlerListeners.has(eventType))
			{
				continue;
			}
			const listener = (event : Event) => this._handleTemplateHandlerEvent(event);
			this.addEventListener(eventType, listener, true);
			this._templateHandlerListeners.set(eventType, listener);
		}
	}

	private _handleTemplateHandlerEvent(event : Event)
	{
		const handlerMap = this.templateData?.rowTemplateHandlerMap || {};
		for(const node of event.composedPath())
		{
			if(node === this)
			{
				break;
			}
			if(!(node instanceof HTMLElement))
			{
				continue;
			}
			if(node.hasAttribute("data-et2nm-id"))
			{
				const id = node.getAttribute("data-et2nm-id")!;
				const source = handlerMap[id]?.["on" + event.type];
				if(source)
				{
					const rowElement = node.closest?.("[data-row-index]") as HTMLElement | null;
					const rowIndex = parseInt(rowElement?.getAttribute("data-row-index") || "-1", 10);
					const row = rowIndex >= 0 ? this._rowsByIndex[rowIndex] : null;
					const handlerSource = row
						? String(this._resolveRowExpression(source, this._rowDataFor(row), String(row.id ?? rowIndex)).value)
						: source;
					const handler = this._templateHandler(node, id, event.type, handlerSource, source.includes("$") || source.includes("@"));
					const result = handler && handler(event, node);
					if(result === false)
					{
						event.preventDefault();
						event.stopPropagation();
						return;
					}
					if(event.cancelBubble)
					{
						return;
					}
				}
			}
			if(node.hasAttribute("data-row-id"))
			{
				return;
			}
		}
	}

	private _templateHandler(widget : HTMLElement, id : string, eventType : string, source : string, rowScoped : boolean = false) : Function | false
	{
		const hasRowContext = rowScoped || source.includes("$") || source.includes("@");
		const handlerKey = id + ":on" + eventType;
		let handlers : Map<string, Function | false>;
		if(hasRowContext)
		{
			const rowHandlers = this._rowTemplateHandlerCache.get(widget);
			if(rowHandlers)
			{
				handlers = rowHandlers;
			}
			else
			{
				handlers = new Map();
				this._rowTemplateHandlerCache.set(widget, handlers);
			}
		}
		else
		{
			handlers = this._templateHandlerCache;
		}
		let handler : Function | false;
		if(handlers.has(handlerKey))
		{
			handler = handlers.get(handlerKey)!;
		}
		else
		{
			et2_warnLegacyEventHandler(widget, source);
			handler = et2_compileLegacyJS(source, widget as any, widget as any);
			handlers.set(handlerKey, handler);
		}
		return handler;
	}

	/**
	 * Convert legacy row syntax to the compact form used by direct row
	 * hydration.  This mirrors Et2RowProvider's preparation-time conversion so
	 * manually supplied template data remains compatible too.
	 */
	private _canonicalRowExpression(value : string) : string
	{
		let normalized = value;
		normalized = normalized.replace(/\$row_cont((?:\[[^\]]+\])+)/g, (_match, fields) =>
			"$[" + fields.slice(1, -1).split("][").join(".") + "]"
		);
		normalized = normalized.replace(/\$\{row\}((?:\[[^\]]+\])+)/g, (_match, fields) =>
			"$[" + fields.slice(1, -1).split("][").join(".") + "]"
		);
		normalized = normalized.replace(/\{\$row\}((?:\[[^\]]+\])+)/g, (_match, fields) =>
			"$[" + fields.slice(1, -1).split("][").join(".") + "]"
		);
		normalized = normalized.replace(/\$row\.([a-zA-Z0-9_.]+)/g, (_match, field) => "$[" + field + "]");
		normalized = normalized.replace(/\$\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g, (_match, token) => token === "row" ? "${row}" : "$" + token);
		return normalized;
	}

	/**
	 * Resolve row tokens without opening an ArrayMgr perspective.  Non-row
	 * tokens such as $cont and @labels are deliberately left for the widget's
	 * normal content manager.
	 */
	private _resolveRowExpression(value : string, rowData : any, rowId : string) : {value : any; rowValue? : any; fallback : boolean}
	{
		const normalized = this._canonicalRowExpression(value);
		const exact = normalized.match(/^\$\[([^\]]+)\]$/) || normalized.match(/^\$([a-zA-Z_][a-zA-Z0-9_]*)$/);
		if(exact && !["row", "row_cont", "cont", "_cont"].includes(exact[1]))
		{
			const field = exact[1];
			const rowValue = this._getFieldValue(rowData, field) ?? "";
			return {value: rowValue, rowValue, fallback: false};
		}
		if(normalized === "$row" || normalized === "${row}")
		{
			return {value: rowId, rowValue: rowId, fallback: false};
		}

		let fallback = /\$row_cont|\$\{row\}\[|\{\$row\}\[/.test(normalized);
		const resolved = normalized
			.replace(/\$\[([^\]]+)\]/g, (_match, field) => String(this._getFieldValue(rowData, field) ?? ""))
			.replace(/\$row\b/g, rowId)
			.replace(/\$([a-zA-Z_][a-zA-Z0-9_]*)\b/g, (match, field) =>
			{
				if(["row", "row_cont", "cont", "_cont"].includes(field))
				{
					return match;
				}
				return String(this._getFieldValue(rowData, field) ?? "");
			});
		return {value: resolved, fallback};
	}

	private _rowAttributePropertyType(element : any, attribute : string) : any
	{
		const property = element.constructor?.getPropertyOptions?.(attribute === "select_options" || attribute.indexOf("_") === -1
			? attribute
			: attribute.replace(/_([a-z])/g, (_match, letter) => letter.toUpperCase()));
		return typeof property === "object" ? property?.type : property;
	}

	private _directBooleanRowValue(value : string, rowData : any, rowId : string) : boolean | undefined
	{
		const normalized = this._canonicalRowExpression(value);
		const match = normalized.match(/^(!)?(?:\$\[([^\]]+)\]|\$([a-zA-Z_][a-zA-Z0-9_]*))$/);
		if(!match || ["row_cont", "cont", "_cont"].includes(match[3]))
		{
			return undefined;
		}
		const raw = match[2] ? this._getFieldValue(rowData, match[2]) : match[3] === "row" ? rowId : this._getFieldValue(rowData, match[3]);
		const truthy = !(raw === false || raw === null || typeof raw === "undefined" || raw === "" || raw === 0 || raw === "0" || raw === "false");
		return match[1] ? !truthy : truthy;
	}

	/**
	 * Re-render physical row DOM when structure-defining inputs change.
	 * We rebuild rows here because template/column changes alter generated markup.
	 */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		this._reconnectStuckVirtualizer();

		// Include new row stylesheet(s)
		if(changedProperties.has("rowStylesheets"))
		{
			this.shadowRoot!.adoptedStyleSheets = [
				...(this.constructor as typeof Et2Datagrid).elementStyles.map(s => s instanceof CSSStyleSheet ? s : s.styleSheet),
				...this.rowStylesheets
			];
		}
		if(this._postRenderStructureSyncNeeded)
		{
			this._ensureTableColSizes();
			this._applyColumnVisibilityToRenderedRows();
			this._postRenderStructureSyncNeeded = false;
		}
		this._syncDomEventTargets();
		if(changedProperties.has("rows") || changedProperties.has("expansionConfig"))
		{
			this._scheduleEmbeddedChildGridObserverSync();
		}
		this._upgradeRenderedRows();
		if(this._restoreFocusAfterRender && this.activeRowIndex >= 0)
		{
			this._focusRowByIndex(this.activeRowIndex, 10);
		}
		if(this._isColumnResizeDisabled())
		{
			this._teardownColumnResizeInteract();
		}
		else
		{
			this._setupColumnResizeInteract();
		}
		this._syncEmbeddedVirtualizedHostHeight();
		if(this.embeddedVirtualized)
		{
			const scrollport = this._scrollportForEmbeddedSelf();
			this._syncEmbeddedParentScrollOffset(scrollport);
			this._syncEmbeddedVirtualizerScrollport(scrollport);
		}
		if(
			!this.embeddedVirtualized &&
			!this._isTileView() &&
			(
				changedProperties.has("view") ||
				changedProperties.has("templateData")
			)
		)
		{
			// Row/tile switches replace #rows. Seed row height before the
			// virtualizer's scheduled layout reads the new empty tbody as a
			// zero-height viewport and decides there is no visible range.
			this._syncRowsMinHeight();
			this._scheduleVirtualizerLayoutSync();
		}
	}

	/**
	 * The virtualizer reserves tbody height from row estimates before row widgets
	 * finish upgrading. Re-measure after layout settles and keep tbody tall enough
	 * for the actual rendered row stack so the last realized rows are not clipped.
	 */
	private _scheduleRowsMinHeightSync()
	{
		if(this._rowsMinHeightFrame !== null)
		{
			return;
		}
		this._rowsMinHeightFrame = requestAnimationFrame(() =>
		{
			this._rowsMinHeightFrame = null;
			this._syncRowsMinHeight();
		});
	}

	/**
	 * Correct the virtualizer spacer upward when upgraded rows are taller than
	 * the estimate used for tbody height. Height remains virtualizer-owned; this
	 * method only supplies a min-height floor.
	 */
	private _syncRowsMinHeight()
	{
		if(this._printRows)
		{
			const rowsBody = this._rowsBody as HTMLElement | null;
			if(rowsBody)
			{
				rowsBody.style.height = "";
				rowsBody.style.minHeight = "";
			}
			return;
		}
		if(this.embeddedVirtualized)
		{
			return;
		}
		const rowsBody = this._rowsBody as HTMLElement | null;
		if(!rowsBody)
		{
			return;
		}
		const explicitHeight = rowsBody.style.height || "";
		const virtualizerHeight = /^\d+(\.\d+)?px$/.test(explicitHeight) ? parseFloat(explicitHeight) : 0;
		const deterministicVirtualHeight = this._usesFixedVirtualizerRowHeight()
		                                  ? this._fixedVirtualItemsHeight()
		                                  : 0;
		const renderedRowsHeight = deterministicVirtualHeight > 0 ? 0 : this._embeddedVirtualizedRenderedRowsHeight();
		// On a freshly recreated row tbody there may be no rendered rows and no
		// virtualizer-owned height yet. Use the existing row-height estimate once
		// so the first layout pass has a concrete viewport to render into.
		const estimatedVirtualizerHeight = virtualizerHeight || renderedRowsHeight
		                                  ? 0
		                                  : this._virtualRowCount() * this.rowHeightEstimatePx;
		const height = Math.max(
			virtualizerHeight || 0,
			deterministicVirtualHeight,
			renderedRowsHeight || 0,
			estimatedVirtualizerHeight || 0,
			this._cachedExpandedRowsMinHeightFloor(Math.max(virtualizerHeight || 0, estimatedVirtualizerHeight || 0))
		);
		const value = height > 0 ? `${Math.ceil(height)}px` : "";
		if(rowsBody.style.minHeight !== value)
		{
			rowsBody.style.minHeight = value;
		}
	}

	/**
	 * Exact parent scroll extent for fixed-pitch virtual rows. Expanded branches
	 * are sparse overrides; realized DOM rows must not influence this value.
	 */
	private _fixedVirtualItemsHeight() : number
	{
		const rowHeight = this.rowHeightEstimatePx;
		const items = this._getVirtualItems(this._virtualRowCount());
		let height = items.length * rowHeight;
		for(const item of items)
		{
			if(typeof item === "number" || item.type !== "expanded")
			{
				continue;
			}
			height += Math.max(0, (this._expandedRowHeightByParentRowId.get(item.parentRowId) || rowHeight) - rowHeight);
		}
		return Math.ceil(height);
	}

	/**
	 * Preserve expanded branch height while a virtualized expanded row is
	 * recycled out of DOM. Lit Virtualizer still counts the expanded row as one
	 * normal estimated item, so add only the measured extra height.
	 */
	private _cachedExpandedRowsMinHeightFloor(baseHeight : number) : number
	{
		if(!this._expandedRowHeightByParentRowId.size)
		{
			return 0;
		}
		const expandedRowIds = this._expandedRowIds();
		const rowHeight = this.rowHeightEstimatePx;
		let extraHeight = 0;
		for(const [parentRowId, height] of this._expandedRowHeightByParentRowId)
		{
			if(expandedRowIds.has(parentRowId))
			{
				extraHeight += Math.max(0, height - rowHeight);
			}
		}
		return extraHeight > 0 ? baseHeight + extraHeight : 0;
	}

	/**
	 * Ask the virtualizer for one more layout pass after structural row-host
	 * sizing changes. This mirrors the resize/scroll callback users triggered
	 * manually by nudging the scroll position, but keeps it scoped to view /
	 * template switches and does not schedule a Lit render.
	 */
	private _scheduleVirtualizerLayoutSync()
	{
		if(this._virtualizerLayoutSyncFrame !== null)
		{
			return;
		}
		this._virtualizerLayoutSyncFrame = requestAnimationFrame(() =>
		{
			this._virtualizerLayoutSyncFrame = null;
			const virtualizer = this._rowsBody?.[virtualizerRef] as any;
			if(typeof virtualizer?._hostElementSizeChanged === "function")
			{
				virtualizer._hostElementSizeChanged();
			}
		});
	}

	/**
	 * Replace the bootstrap FlowLayout only after it has rendered the tbody once.
	 * A new virtualizer uses its host bounds to determine its viewport; replacing
	 * it while an empty tbody has no bounds creates a zero-viewport feedback loop
	 * that clears every virtual row.
	 */
	private _scheduleSparseVirtualizerLayoutActivation()
	{
		if(this._sparseVirtualizerLayoutActive || this._sparseVirtualizerLayoutFrame !== null || !this._usesFixedVirtualizerRowHeight())
		{
			return;
		}
		this._sparseVirtualizerLayoutFrame = requestAnimationFrame(() =>
		{
			this._sparseVirtualizerLayoutFrame = null;
			if(!this.isConnected || this._sparseVirtualizerLayoutActive || !this._usesFixedVirtualizerRowHeight())
			{
				return;
			}
			this._syncRowsMinHeight();
			this._sparseVirtualizerLayoutActive = true;
			this.requestUpdate();
			this._scheduleVirtualizerLayoutSync();
		});
	}

	/**
	 * Keep an embedded virtualized grid's host height aligned with its tbody.
	 *
	 * Embedded grids do not own a scrollport, so the parent grid needs the child
	 * host to reserve exactly the height occupied by virtualized child rows.
	 */
	private _syncEmbeddedVirtualizedHostHeight()
	{
		if(!this.embeddedVirtualized)
		{
			if(this._embeddedVirtualizedHostHeight !== null)
			{
				this.style.height = "";
			}
			this._embeddedVirtualizedHostHeight = null;
			if(this._embeddedVirtualizedHeightFrame !== null)
			{
				cancelAnimationFrame(this._embeddedVirtualizedHeightFrame);
				this._embeddedVirtualizedHeightFrame = null;
			}
			this._embeddedVirtualizedHeightSyncPassesRemaining = 0;
			return;
		}
		this._embeddedVirtualizedMeasuredRowHeightPx = this._measureEmbeddedVirtualizedRowHeight();
		const height = this._embeddedVirtualizedContentHeight() ?? this._embeddedVirtualizedLoadingHeight();
		if(!height || this._embeddedVirtualizedHostHeight === height)
		{
			return;
		}
		this._applyEmbeddedVirtualizedHostHeight(height);
	}

	/**
	 * Observe direct embedded child grids hosted by this grid's expanded rows.
	 * This replaces the previous per-rendered-row observer and keeps height
	 * remeasurement scoped to the immediate parent/child grid boundary.
	 */
	private _syncEmbeddedChildGridObservers()
	{
		if(!this._embeddedChildGridResizeObserver)
		{
			this._embeddedChildGridResizeObserver = new ResizeObserver((entries) =>
			{
				for(const entry of entries)
				{
					const childGrid = entry.target;
					if(childGrid instanceof Et2Datagrid)
					{
						this._remeasureObservedEmbeddedChildGrid(childGrid);
					}
				}
			});
		}
		this._embeddedChildGridResizeObserver.disconnect();
		for(const childGrid of this._directEmbeddedChildGrids())
		{
			childGrid.setRowHeightEstimate(this.rowHeightEstimatePx, childGrid.embeddedVirtualized);
			const scrollport = this._scrollportForEmbeddedChild(childGrid);
			const logicalOffsetTop = this._embeddedChildLogicalScrollOffsetTop(childGrid);
			if(logicalOffsetTop !== null)
			{
				childGrid._embeddedParentScrollOffsetTop = logicalOffsetTop;
			}
			else
			{
				childGrid._syncEmbeddedParentScrollOffset(scrollport);
			}
			childGrid._syncEmbeddedVirtualizerScrollport(scrollport);
			if(scrollport)
			{
				childGrid._handleEmbeddedParentScroll(scrollport);
			}
			this._embeddedChildGridResizeObserver.observe(childGrid);
		}
	}

	/**
	 * Expanded-row content is stamped by Lit/virtualizer across frame
	 * boundaries. Re-sync once after render so newly upgraded/recycled embedded
	 * child grids receive parent scroll and row-height state.
	 */
	private _scheduleEmbeddedChildGridObserverSync()
	{
		if(this._embeddedChildGridObserverSyncFrame !== null)
		{
			return;
		}
		this._embeddedChildGridObserverSyncFrame = requestAnimationFrame(() =>
		{
			this._embeddedChildGridObserverSyncFrame = null;
			if(this.isConnected)
			{
				this._syncEmbeddedChildGridObservers();
			}
		});
	}

	/**
	 * Push this grid's current row-height estimate into direct embedded child
	 * grids. Child grids treat the value as a floor unless their row template
	 * declares a fixed height.
	 */
	private _syncEmbeddedChildGridRowHeightEstimates()
	{
		for(const childGrid of this._directEmbeddedChildGrids())
		{
			childGrid.setRowHeightEstimate(this.rowHeightEstimatePx, childGrid.embeddedVirtualized);
		}
	}

	/**
	 * Return child datagrids directly hosted by this grid's expanded rows.
	 */
	private _directEmbeddedChildGrids() : Et2Datagrid[]
	{
		const expandedRows = Array.from(this.shadowRoot?.querySelectorAll("tr[data-dg-expanded-row]") || []) as HTMLElement[];
		return expandedRows
			.map((expandedRow) => expandedRow.querySelector("et2-datagrid"))
			.filter((grid) : grid is Et2Datagrid => grid instanceof Et2Datagrid);
	}

	/**
	 * Remeasure from ResizeObserver only when the child was not already handled
	 * by its explicit height event in the same frame.
	 */
	private _remeasureObservedEmbeddedChildGrid(childGrid : Et2Datagrid) : boolean
	{
		if(this._remeasuredEmbeddedChildGridsThisFrame.has(childGrid))
		{
			return false;
		}
		return this._remeasureDirectEmbeddedChildGrid(childGrid);
	}

	/**
	 * Return true while the owning body is actively scrolling. Parent virtualizer
	 * size corrections during this window change its internal scroll math and
	 * visibly move the scrollbar.
	 */
	private _isBodyScrollActive() : boolean
	{
		return this._lastBodyScrollAt > 0 && performance.now() - this._lastBodyScrollAt < Et2Datagrid._embeddedScrollSettleDelayMs;
	}

	/**
	 * Defer parent virtualizer remeasurement until scroll settles. The expanded
	 * row's explicit height has already been synced, so this only delays the
	 * virtualizer's private layout correction.
	 */
	private _deferEmbeddedChildGridRemeasure(childGrid : Et2Datagrid)
	{
		this._deferredEmbeddedRemeasureChildGrids.add(childGrid);
		if(this._deferredEmbeddedRemeasureTimer !== null)
		{
			return;
		}
		this._deferredEmbeddedRemeasureTimer = window.setTimeout(() =>
		{
			this._deferredEmbeddedRemeasureTimer = null;
			if(this._isBodyScrollActive())
			{
				this._deferEmbeddedChildGridRemeasure(childGrid);
				return;
			}
			const childGrids = Array.from(this._deferredEmbeddedRemeasureChildGrids);
			this._deferredEmbeddedRemeasureChildGrids.clear();
			for(const deferredChildGrid of childGrids)
			{
				this._remeasureDirectEmbeddedChildGrid(deferredChildGrid, true);
			}
		}, Et2Datagrid._embeddedScrollSettleDelayMs);
	}

	/**
	 * Find the nearest ancestor that owns vertical scrolling, crossing shadow
	 * roots as required by recursively embedded grids.
	 */
	private _nearestScrollableAncestor(node : HTMLElement | null) : HTMLElement | null
	{
		while(node)
		{
			if(getComputedStyle(node).overflow !== "visible")
			{
				return node;
			}
			const parent = node.parentElement;
			if(parent)
			{
				node = parent;
				continue;
			}
			const root = node.getRootNode();
			node = root instanceof ShadowRoot ? root.host as HTMLElement : null;
		}
		return null;
	}

	/**
	 * Find the real scrollport shared by an embedded child grid. Nested embedded
	 * grids can have visible-overflow grid bodies between themselves and the root
	 * scroll body, so resolve this from DOM/style.
	 */
	private _scrollportForEmbeddedChild(childGrid : Et2Datagrid) : HTMLElement | null
	{
		return this._nearestScrollableAncestor(childGrid.parentElement as HTMLElement | null);
	}

	/**
	 * Resolve this embedded grid's external scrollport. Embedded grids keep
	 * their own body overflow visible, so late page loads must re-run layout
	 * against the ancestor that owns scrolling.
	 */
	private _scrollportForEmbeddedSelf() : HTMLElement | null
	{
		if(!this.embeddedVirtualized)
		{
			return null;
		}
		return this._nearestScrollableAncestor(this.parentElement as HTMLElement | null);
	}

	/**
	 * Private @lit-labs/virtualizer integration boundary for embedded grids.
	 *
	 * The library has no public API for changing a virtualizer's clipping
	 * scrollport. Embedded grids therefore require these compatible internals:
	 * `_clippingAncestors`, `_correctScrollError`, and `_updateView`. Keep all
	 * such integration here so a library upgrade has one small compatibility
	 * surface to verify rather than private-property writes spread across layout.
	 */
	private _syncEmbeddedVirtualizerScrollport(scrollport : HTMLElement | null)
	{
		if(!this.embeddedVirtualized || !scrollport)
		{
			return;
		}
		const virtualizer = this._virtualize as any;
		if(!virtualizer)
		{
			return;
		}
		virtualizer._clippingAncestors = [scrollport];
		if(!virtualizer._et2EmbeddedScrollErrorPatched)
		{
			// The parent owns this scrollport. A child layout may reflow its local
			// range, but must never correct the shared scroll position.
			virtualizer._correctScrollError = () => {};
			virtualizer._et2EmbeddedScrollErrorPatched = true;
		}
		if(!virtualizer._et2EmbeddedUpdateViewPatched && typeof virtualizer._updateView === "function")
		{
			const originalUpdateView = virtualizer._updateView.bind(virtualizer);
			virtualizer._updateView = () =>
			{
				originalUpdateView();
				this._syncEmbeddedVirtualizerViewport(scrollport, virtualizer);
			};
			virtualizer._et2EmbeddedUpdateViewPatched = true;
		}
	}

	/**
	 * Complete the private virtualizer integration by restoring the child grid's
	 * logical viewport after every internal view update. The virtualizer measures
	 * a deeply nested host from its recycled physical DOM position, which is not
	 * its position in the shared root scrollport. Also provide a real viewport
	 * fallback when all clipping ancestors temporarily report zero height for an
	 * offscreen nested branch.
	 */
	private _syncEmbeddedVirtualizerViewport(scrollport : HTMLElement, virtualizer : any = this._virtualize)
	{
		const logicalOffsetTop = this._embeddedParentScrollOffsetTop;
		const layout = virtualizer?._layout;
		if(logicalOffsetTop === null || !layout)
		{
			return;
		}
		const viewportSize = layout.viewportSize || {width: 0, height: 0};
		const localScrollTop = Math.max(0, scrollport.scrollTop - logicalOffsetTop);
		if(!(viewportSize.height > 0))
		{
			const size = {
				...viewportSize,
				height: Math.max(1, scrollport.clientHeight)
			};
			layout.viewportSize = size;
		}
		const viewportScroll = layout.viewportScroll || {top: 0, left: 0};
		const scroll = {
			...viewportScroll,
			top: localScrollTop
		};
		layout.viewportScroll = scroll;
		if(layout.offsetWithinScroller)
		{
			layout.offsetWithinScroller = {
				...layout.offsetWithinScroller,
				top: logicalOffsetTop
			};
		}
		// Virtualizer calls `reflowIfNeeded()` immediately after `_updateView()`.
		// Mark the corrected logical viewport pending so that existing normal pass
		// performs it once, rather than forcing a second reflow from this callback.
		layout._scheduleReflow?.();
	}

	/**
	 * Cache this embedded grid's absolute offset inside the parent scrollport.
	 * Parent virtualizer recycling can keep the DOM node near the viewport while
	 * its logical content position is much farther down the scroll range, so
	 * scroll math cannot depend only on current bounding rects.
	 */
	private _syncEmbeddedParentScrollOffset(scrollport : HTMLElement | null)
	{
		if(!this.embeddedVirtualized || !scrollport)
		{
			return;
		}
		const scrollportRect = scrollport.getBoundingClientRect();
		const ownRect = this.getBoundingClientRect();
		const offsetTop = Math.max(0, scrollport.scrollTop + ownRect.top - scrollportRect.top);
		if(this._embeddedParentScrollOffsetTop === null || scrollport.scrollTop <= offsetTop + scrollport.clientHeight)
		{
			this._embeddedParentScrollOffsetTop = offsetTop;
		}
	}

	/**
	 * Calculate a direct child grid's logical top in the shared parent scroll
	 * range. This avoids using recycled DOM rects from the parent virtualizer.
	 *
	 * Invariants: virtual item order matches the current expansion state; the
	 * sparse layout's expanded-item map contains the direct-child reservations;
	 * and an embedded parent offset is already relative to the same scrollport.
	 * If sparse layout is unavailable, the fallback reconstructs that same order
	 * from the settled normal-row pitch and cached expanded-row heights.
	 */
	private _embeddedChildLogicalScrollOffsetTop(childGrid : Et2Datagrid) : number | null
	{
		const expandedRow = childGrid.closest("tr[data-dg-expanded-row]") as HTMLElement | null;
		if(!expandedRow || expandedRow.getRootNode() !== this.shadowRoot)
		{
			return null;
		}
		const parentRowId = expandedRow.getAttribute("data-parent-row-id") || "";
		if(!parentRowId)
		{
			return null;
		}
		const virtualItems = this._getVirtualItems(this._virtualRowCount());
		const expandedItemIndex = virtualItems.findIndex((item) =>
			typeof item !== "number" && item.type === "expanded" && item.parentRowId === parentRowId
		);
		const layout = this._virtualize?._layout;
		// The sparse layout is the source of truth for the parent expanded row's
		// rendered position. Reconstructing that position from row indexes and
		// cached branch heights can become stale while multiple nested expansions
		// are recycled, leaving a child virtualizer to render the wrong window.
		if(expandedItemIndex >= 0 && layout instanceof Et2DatagridSparseFlowLayout)
		{
			return (this.embeddedVirtualized ? this._embeddedParentScrollOffsetTop || 0 : 0) + layout._getPosition(expandedItemIndex);
		}
		const parentIndex = this._rowsByIndex.findIndex((row) => row ? this._rowExpansionId(row) === parentRowId : false);
		if(parentIndex < 0)
		{
			return null;
		}
		const rowHeight = this.rowHeightEstimatePx;
		let offsetTop = (this.embeddedVirtualized ? this._embeddedParentScrollOffsetTop || 0 : 0) + ((parentIndex + 1) * rowHeight);
		const expandedRowIds = this._expandedRowIds();
		for(let index = 0; index < parentIndex; index++)
		{
			const row = this._rowsByIndex[index];
			if(!row)
			{
				continue;
			}
			const rowId = this._rowExpansionId(row);
			if(expandedRowIds.has(rowId))
			{
				offsetTop += Math.max(0, (this._expandedRowHeightByParentRowId.get(rowId) || 0) - rowHeight);
			}
		}
		return offsetTop;
	}

	/**
	 * Propagate parent scroll movement to embedded descendants whose virtualizer
	 * uses the same ancestor scrollport.
	 */
	private _notifyEmbeddedChildGridsOfParentScroll(scrollport : HTMLElement | null)
	{
		if(!scrollport)
		{
			return;
		}
		for(const childGrid of this._directEmbeddedChildGrids())
		{
			const logicalOffsetTop = this._embeddedChildLogicalScrollOffsetTop(childGrid);
			if(logicalOffsetTop !== null)
			{
				childGrid._embeddedParentScrollOffsetTop = logicalOffsetTop;
			}
			childGrid._handleEmbeddedParentScroll(scrollport);
		}
	}

	/**
	 * Defer embedded child scroll sync until the parent virtualizer has applied
	 * its own scroll-position layout update.
	 */
	private _scheduleEmbeddedChildScrollSync(scrollport : HTMLElement | null)
	{
		if(!scrollport)
		{
			return;
		}
		// Rapid scroll events can coalesce while a nested virtualizer is applying
		// an earlier range. Do a final normal layout pass when scrolling stops so
		// the last shared-scroll position cannot leave a child range incomplete.
		if(this._embeddedChildScrollSettleTimer !== null)
		{
			clearTimeout(this._embeddedChildScrollSettleTimer);
		}
		this._embeddedChildScrollSettleTimer = window.setTimeout(() =>
		{
			this._embeddedChildScrollSettleTimer = null;
			if(this.isConnected)
			{
				this._notifyEmbeddedChildGridsOfParentScroll(scrollport);
			}
		}, Et2Datagrid._embeddedScrollSettleDelayMs);
		if(this._embeddedChildScrollSyncFrame !== null)
		{
			return;
		}
		this._embeddedChildScrollSyncFrame = requestAnimationFrame(() =>
		{
			this._embeddedChildScrollSyncFrame = null;
			this._notifyEmbeddedChildGridsOfParentScroll(scrollport);
		});
	}

	/**
	 * Re-sync this embedded grid after data for the current parent-scroll range
	 * arrives. Without this, a fetched later page can remain invisible until the
	 * next user scroll event.
	 */
	private _scheduleEmbeddedSelfScrollSync(force : boolean = false)
	{
		if(!this.embeddedVirtualized || this._embeddedSelfScrollSyncFrame !== null)
		{
			return;
		}
		if(!force && !this._hasRenderedMaterializedPlaceholder())
		{
			return;
		}
		this._embeddedSelfScrollSyncFrame = requestAnimationFrame(() =>
		{
			this._embeddedSelfScrollSyncFrame = null;
			const scrollport = this._scrollportForEmbeddedSelf();
			if(scrollport)
			{
				this._handleEmbeddedParentScroll(scrollport);
			}
		});
	}

	/**
	 * Return true only when a currently rendered placeholder now has backing row
	 * data. This keeps post-fetch self-sync bounded to placeholder replacement
	 * and prevents recursive fetch/layout cascades.
	 */
	private _hasRenderedMaterializedPlaceholder() : boolean
	{
		for(const placeholder of Array.from(this._rowsBody?.querySelectorAll("[data-et2dg-placeholder][data-row-index]") || []) as HTMLElement[])
		{
			const rowIndex = parseInt(placeholder.getAttribute("data-row-index") || "-1", 10);
			if(rowIndex >= 0 && !!this._rowsByIndex[rowIndex])
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Check whether a fetched absolute row range intersects this embedded grid's
	 * current parent-scroll viewport.
	 */
	private _embeddedFetchedRangeOverlapsViewport(start : number, requestedCount : number) : boolean
	{
		if(!this.embeddedVirtualized || requestedCount <= 0)
		{
			return false;
		}
		const scrollport = this._scrollportForEmbeddedSelf();
		const rowsBody = this._rowsBody;
		if(!scrollport || !rowsBody)
		{
			return false;
		}
		const rowHeight = Math.max(this.rowHeightEstimatePx, this._embeddedVirtualizedMeasuredRowHeightPx || 0, 1);
		const viewportTop = this._embeddedParentScrollOffsetTop !== null
		                    ? Math.max(0, scrollport.scrollTop - this._embeddedParentScrollOffsetTop)
		                    : rowsBody.scrollTop;
		const firstVisible = Math.max(0, Math.floor(viewportTop / rowHeight) - this.pageSize);
		const lastVisible = Math.ceil((viewportTop + scrollport.clientHeight) / rowHeight) + this.pageSize;
		const requestEnd = start + requestedCount;
		return start < lastVisible && requestEnd > firstVisible;
	}

	/**
	 * Embedded row grids are driven by an ancestor scrollport. Queue provider
	 * chunks from that shared scroll position directly, so loading does not
	 * depend on the virtualizer first stamping placeholder rows for the range.
	 */
	private _requestEmbeddedVisibleChunks(scrollport : HTMLElement)
	{
		if(!this.embeddedVirtualized || !this.dataProvider || this._isTileView())
		{
			return;
		}
		const rowCount = this._virtualRowCount();
		if(rowCount <= 0)
		{
			return;
		}
		const rowHeight = Math.max(this.rowHeightEstimatePx, this._embeddedVirtualizedMeasuredRowHeightPx || 0, 1);
		const localScrollTop = this._embeddedParentScrollOffsetTop !== null
		                       ? Math.max(0, scrollport.scrollTop - this._embeddedParentScrollOffsetTop)
		                       : (this._rowsBody?.scrollTop || 0);
		const firstVisible = Math.max(0, Math.floor(localScrollTop / rowHeight));
		const lastVisible = Math.min(rowCount - 1, Math.ceil((localScrollTop + scrollport.clientHeight) / rowHeight));
		this._requestChunkForRowIndex(firstVisible);
		this._requestChunkForRowIndex(lastVisible);
	}

	/**
	 * Embedded row grids use the ancestor scrollport. On ancestor scroll, fetch
	 * the visible chunks and reflow this grid's virtualizer against that same
	 * scrollport; never mutate the child tbody's own scroll position.
	 */
	private _handleEmbeddedParentScroll(scrollport : HTMLElement)
	{
		if(!this.isConnected)
		{
			return;
		}
		if(this._embeddedParentScrollOffsetTop === null)
		{
			this._syncEmbeddedParentScrollOffset(scrollport);
		}
		this._syncEmbeddedVirtualizerScrollport(scrollport);
		this._requestEmbeddedVisibleChunks(scrollport);
		const virtualizer = this._virtualize as any;
		if(typeof virtualizer?._updateLayout === "function")
		{
			virtualizer._updateLayout();
		}
		else if(typeof virtualizer?._handleScrollEvent === "function")
		{
			virtualizer._handleScrollEvent();
		}
		this._notifyEmbeddedChildGridsOfParentScroll(scrollport);
	}

	/**
	 * The virtualizer may write tbody height after Lit's `updated()` callback.
	 * Re-check on the next animation frame so the host and exposed CSS variable
	 * follow the final row layout instead of an early estimate.
	 */
	private _scheduleEmbeddedVirtualizedHeightSync = () =>
	{
		if(this._rowUpgradeScheduled || this._rowUpgradeQueue.length || this._rowWidgetsUpgradeSettling)
		{
			this._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade = true;
			return;
		}
		const fullRenderHeight = this._embeddedFullRenderContentHeight();
		this._embeddedVirtualizedHeightSyncPassesRemaining = Math.max(
			this._embeddedVirtualizedHeightSyncPassesRemaining,
			fullRenderHeight > 0 ? 1 : 3
		);
		if(this._embeddedVirtualizedHeightFrame !== null)
		{
			return;
		}
		this._embeddedVirtualizedHeightFrame = requestAnimationFrame(() => this._runEmbeddedVirtualizedHeightSyncPass());
	};

	/**
	 * Run a bounded settle pass for embedded height. Nested row widgets can
	 * upgrade over consecutive frames, so one post-update measurement is not
	 * enough to keep parent reservations accurate.
	 */
	private _runEmbeddedVirtualizedHeightSyncPass()
	{
		this._embeddedVirtualizedHeightFrame = null;
		if(!this.embeddedVirtualized)
		{
			this._embeddedVirtualizedHeightSyncPassesRemaining = 0;
			return;
		}
		this._embeddedVirtualizedMeasuredRowHeightPx = this._measureEmbeddedVirtualizedRowHeight();
		const height = this._embeddedVirtualizedContentHeight();
		if(height && this._embeddedVirtualizedHostHeight !== height)
		{
			this._applyEmbeddedVirtualizedHostHeight(height);
		}
		this._embeddedVirtualizedHeightSyncPassesRemaining--;
		if(this._embeddedVirtualizedHeightSyncPassesRemaining > 0)
		{
			this._embeddedVirtualizedHeightFrame = requestAnimationFrame(() => this._runEmbeddedVirtualizedHeightSyncPass());
		}
	}

	/**
	 * Apply the embedded host height and ask the parent virtualizer to remeasure
	 * the expanded row that contains this child grid.
	 */
	private _applyEmbeddedVirtualizedHostHeight(height : string)
	{
		if(this._embeddedVirtualizedHostHeight === height && this.style.height === height)
		{
			return;
		}
		this.style.height = height;
		this._embeddedVirtualizedHostHeight = height;
		this.shadowRoot?.querySelector<HTMLElement>(".dg-root")?.style.setProperty("--embedded-virtualized-height", height);
		this._notifyParentVirtualizerOfEmbeddedHeightChange(height);
	}

	/**
	 * Dispatch a composed event so the direct parent grid can remeasure the
	 * expanded row that hosts this embedded grid.
	 */
	private _notifyParentVirtualizerOfEmbeddedHeightChange(height : string)
	{
		this.dispatchEvent(new CustomEvent("et2-embedded-height", {
			bubbles: true,
			composed: true,
			detail: {
				height: parseFloat(height) || 0
			}
		}));
	}

	/**
	 * Handle height changes from a direct embedded child grid only. Descendant
	 * events are ignored here and handled by their nearest parent grid.
	 */
	private _handleEmbeddedHeightEvent = (event : CustomEvent<{ height? : number }>) =>
	{
		const childGrid = event.composedPath?.()[0] as EventTarget | null;
		if(!(childGrid instanceof Et2Datagrid) || childGrid === this)
		{
			return;
		}
		// The parent pitch is authoritative once it has settled. Lock the child
		// before its reported total is converted into an expanded-row height.
		childGrid.setRowHeightEstimate(this.rowHeightEstimatePx, childGrid.embeddedVirtualized);
		// The child emits this after applying the same value to its host, so it is
		// the authoritative reservation for this event. Observer/deferred paths
		// still derive height from the child when no event value is available.
		const reportedHeight = Number(event.detail?.height);
		if(!this._remeasureDirectEmbeddedChildGrid(
			childGrid,
			false,
			Number.isFinite(reportedHeight) && reportedHeight > 0 ? reportedHeight : undefined
		))
		{
			return;
		}
		event.stopPropagation();
		if(this.embeddedVirtualized)
		{
			this._scheduleEmbeddedVirtualizedHeightSync();
		}
	};

	/**
	 * Remeasure the expanded row containing one direct child grid.
	 *
	 * @lit-labs/virtualizer has no public "remeasure this child" API for the
	 * virtualize() directive. Keep the private `_childrenSizeChanged` call
	 * contained here so the rest of the recursive height flow uses DOM events.
	 */
	private _remeasureDirectEmbeddedChildGrid(
		childGrid : Et2Datagrid,
		forceVirtualizerRemeasure : boolean = false,
		reportedHeight? : number
	) : boolean
	{
		const expandedRow = childGrid.closest("tr[data-dg-expanded-row]") as HTMLElement | null;
		if(!expandedRow || expandedRow.getRootNode() !== this.shadowRoot)
		{
			return false;
		}
		const heightChanged = this._syncExpandedRowHeightFromChildGrid(expandedRow, childGrid, reportedHeight);
		if(!heightChanged && !forceVirtualizerRemeasure)
		{
			return false;
		}
		const virtualizer = this._virtualize as any;
		if(!virtualizer || typeof virtualizer._childrenSizeChanged !== "function")
		{
			if(this.embeddedVirtualized)
			{
				this._scheduleEmbeddedVirtualizedHeightSync();
			}
			return true;
		}
		if(!forceVirtualizerRemeasure && this._isBodyScrollActive())
		{
			this._deferEmbeddedChildGridRemeasure(childGrid);
			return true;
		}
		virtualizer._childrenSizeChanged([{
			target: expandedRow,
			contentRect: {
				...expandedRow.getBoundingClientRect(),
				height: parseFloat(expandedRow.style.height || expandedRow.style.minHeight || "") || expandedRow.getBoundingClientRect().height
			}
		}]);
		this._remeasuredEmbeddedChildGridsThisFrame.add(childGrid);
		requestAnimationFrame(() =>
		{
			this._remeasuredEmbeddedChildGridsThisFrame.delete(childGrid);
		});
		return true;
	}

	/**
	 * Make the expanded-row host contribute the embedded child's full reserved
	 * height to parent virtualizer measurement. Grid/table layout can otherwise
	 * measure only the rendered child-row stack while the child host itself has a
	 * much larger virtualized height.
	 */
	private _syncExpandedRowHeightFromChildGrid(
		expandedRow : HTMLElement,
		childGrid : Et2Datagrid,
		reportedHeight? : number
	) : boolean
	{
		// Once the parent pitch and child total are known, branch height is pure
		// data: it must never depend on which child rows happen to be realized at
		// the current scroll position.
		const childHeight = reportedHeight ?? (childGrid._usesFixedVirtualizerRowHeight() &&
		                                    childGrid.embeddedVirtualized &&
		                                    childGrid.total !== null
			? childGrid._fixedVirtualItemsHeight()
			: Math.ceil(
				childGrid._embeddedFullRenderContentHeight() ||
				parseFloat(childGrid.style.height || "") ||
				childGrid.getBoundingClientRect().height ||
				0
			));
		if(childHeight <= 0)
		{
			return false;
		}
		const value = `${childHeight}px`;
		const parentRowId = expandedRow.getAttribute("data-parent-row-id") || "";
		const syncedHeight = parseFloat(expandedRow.getAttribute("data-dg-synced-child-height") || "") || 0;
		if(Math.abs(syncedHeight - childHeight) <= 1)
		{
			return false;
		}
		if(parentRowId)
		{
			this._expandedRowHeightByParentRowId.set(parentRowId, childHeight);
			this._syncExpandedVirtualItemHeight(parentRowId, childHeight);
		}
		expandedRow.setAttribute("data-dg-synced-child-height", String(childHeight));
		expandedRow.style.height = value;
		expandedRow.style.minHeight = value;
		const expandedCell = expandedRow.querySelector<HTMLElement>(".dg-expanded-cell");
		const expandedContent = expandedRow.querySelector<HTMLElement>(".dg-expanded-content");
		expandedCell?.style.setProperty("height", value);
		expandedCell?.style.setProperty("min-height", value);
		expandedContent?.style.setProperty("height", value);
		expandedContent?.style.setProperty("min-height", value);
		this._syncRowsMinHeightForExpandedRow();
		return true;
	}

	private _syncExpandedVirtualItemHeight(parentRowId : string, height : number)
	{
		const virtualItemIndex = this._getVirtualItems(this._virtualRowCount()).findIndex((item) =>
			typeof item !== "number" && item.type === "expanded" && item.parentRowId === parentRowId
		);
		if(virtualItemIndex < 0)
		{
			return;
		}
		this._expandedVirtualItemHeights.set(virtualItemIndex, height);
		const layout = this._virtualize?._layout;
		if(layout instanceof Et2DatagridSparseFlowLayout)
		{
			layout.notifyExpandedItemHeightChanged();
		}
	}

	/**
	 * Resolve the full normal-flow embedded content height. Used while embedded
	 * self-virtualization is disabled so the parent reserves the child rows plus
	 * spacer ranges instead of the child's currently painted bounding box.
	 */
	private _embeddedFullRenderContentHeight() : number
	{
		if(!this.embeddedVirtualized || this._isTileView())
		{
			return 0;
		}
		const rowsBody = this._rowsBody as HTMLElement | null;
		const styleHeight = parseFloat(rowsBody?.style.height || rowsBody?.style.minHeight || "") || 0;
		const rowHeight = Math.max(this.rowHeightEstimatePx, this._embeddedVirtualizedMeasuredRowHeightPx || 0, 1);
		if(this.total !== null && (this._usesFixedVirtualizerRowHeight() || this._expandedRowHeightByParentRowId.size > 0))
		{
			return this._fixedVirtualItemsHeight();
		}
		const rowSlots = this.total !== null ? Math.max(0, this.total) : this._virtualRowCount();
		const baseRowsHeight = rowSlots * rowHeight;
		const reservedRowsHeight = Math.max(
			baseRowsHeight,
			this._cachedExpandedRowsMinHeightFloor(baseRowsHeight)
		);
		return Math.ceil(this.total !== null ? reservedRowsHeight : Math.max(styleHeight, reservedRowsHeight));
	}

	/**
	 * Keep the parent virtualizer host tall enough for a large embedded branch
	 * even if the virtualizer rewrites its own explicit height during relayout.
	 */
	private _syncRowsMinHeightForExpandedRow()
	{
		const rowsBody = this._rowsBody;
		if(!rowsBody)
		{
			return;
		}
		const explicitHeight = parseFloat(rowsBody.style.height || rowsBody.style.minHeight || "") || 0;
		const estimatedHeight = this._virtualRowCount() * this.rowHeightEstimatePx;
		const requiredHeight = Math.ceil(this._cachedExpandedRowsMinHeightFloor(Math.max(explicitHeight, estimatedHeight)));
		const currentMinHeight = parseFloat(rowsBody.style.minHeight || "") || 0;
		if(requiredHeight > currentMinHeight)
		{
			rowsBody.style.minHeight = `${requiredHeight}px`;
		}
	}

	/**
	 * Restore scrollTop after Lit virtualizer has had a chance to settle layout.
	 */
	private _restoreBodyScrollTopAfterLayout(
		scrollTop : number | null,
		scrollVersion : number = this._bodyScrollVersion
	)
	{
		if(scrollTop === null)
		{
			return;
		}
		requestAnimationFrame(() =>
		{
			requestAnimationFrame(() =>
			{
				const body = this._body;
				if(this._bodyScrollVersion !== scrollVersion)
				{
					return;
				}
				if(body && body.scrollTop > scrollTop + 1)
				{
					return;
				}
				if(body && Math.abs(body.scrollTop - scrollTop) > 1)
				{
					body.scrollTop = scrollTop;
				}
			});
		});
	}

	/**
	 * Read the virtualizer-owned tbody height when it has written a concrete pixel value.
	 */
	private _embeddedVirtualizedVirtualizerHeight() : string | null
	{
		const rowsBody = this._rowsBody as HTMLElement | null;
		const height = rowsBody?.style.height || rowsBody?.style.minHeight || "";
		return /^\d+(\.\d+)?px$/.test(height) && parseFloat(height) > 0 ? height : null;
	}

	/**
	 * Resolve the post-load embedded height. The virtualizer spacer is the base,
	 * but the measured row stack wins when multi-line rows exceed the estimate.
	 * When correcting upward, update tbody too so all height owners agree.
	 */
	private _embeddedVirtualizedContentHeight() : string | null
	{
		const rowsBody = this._rowsBody as HTMLElement | null;
		const fullRenderHeight = this._embeddedFullRenderContentHeight();
		if(fullRenderHeight > 0)
		{
			const value = `${Math.ceil(fullRenderHeight)}px`;
			if(rowsBody)
			{
				if(rowsBody.style.height !== value)
				{
					rowsBody.style.height = value;
				}
				if(rowsBody.style.minHeight !== value)
				{
					rowsBody.style.minHeight = value;
				}
			}
			return value;
		}
		const virtualizerHeight = this._embeddedVirtualizedVirtualizerHeight();
		const renderedRowsHeight = this._embeddedVirtualizedRenderedRowsHeight();
		const rowHeight = Math.max(
			this._embeddedVirtualizedMeasuredRowHeightPx || 0,
			this.rowHeightEstimatePx
		);
		const reservedRowsHeight = this._virtualRowCount() * rowHeight;
		const virtualizerHeightPx = virtualizerHeight ? parseFloat(virtualizerHeight) : 0;
		const canShrinkStaleVirtualizerHeight = this.total !== null && this.rows.length >= this.total;
		const effectiveVirtualizerHeight = canShrinkStaleVirtualizerHeight && reservedRowsHeight > 0
		                                  ? Math.min(virtualizerHeightPx, reservedRowsHeight)
		                                  : virtualizerHeightPx;
		const baseHeight = Math.max(
			effectiveVirtualizerHeight,
			reservedRowsHeight || 0
		);
		const height = Math.max(
			effectiveVirtualizerHeight,
			renderedRowsHeight || 0,
			reservedRowsHeight || 0,
			this._cachedExpandedRowsMinHeightFloor(baseHeight)
		);
		if(!height)
		{
			return null;
		}
		const value = `${Math.ceil(height)}px`;
		if(rowsBody && Math.abs(height - virtualizerHeightPx) > 1)
		{
			rowsBody.style.height = value;
		}
		return value;
	}

	/**
	 * Calculate the actual rendered row stack height. This can exceed the
	 * virtualizer spacer when rows contain multi-line content, so embedded grids
	 * use it to correct the reserved height and prevent clipping.
	 */
	private _embeddedVirtualizedRenderedRowsHeight() : number | null
	{
		const rowsBody = this._rowsBody as HTMLElement | null;
		if(!rowsBody)
		{
			return null;
		}
		const rows = this._embeddedVirtualizedRenderedRows();
		if(!rows.length)
		{
			return null;
		}
		const bodyRect = rowsBody.getBoundingClientRect();
		const rowBounds = rows
			.map((row) => row.getBoundingClientRect())
			.filter((rect) => Number.isFinite(rect.top) && Number.isFinite(rect.bottom) && rect.height > 0);
		if(!rowBounds.length)
		{
			return null;
		}
		const top = Math.min(...rowBounds.map((rect) => rect.top));
		const bottom = Math.max(...rowBounds.map((rect) => rect.bottom));
		return Math.ceil(bottom - Math.min(top, bodyRect.top));
	}

	/**
	 * Return realized data rows that contribute to embedded grid height.
	 */
	private _embeddedVirtualizedRenderedRows() : HTMLElement[]
	{
		const rowsBody = this._rowsBody as HTMLElement | null;
		if(this._isTileView())
		{
			return this._renderedDataRowElements(rowsBody);
		}
		return Array.from(rowsBody?.querySelectorAll(":scope > tr[data-row-id]:not([data-et2dg-placeholder])") || []) as HTMLElement[];
	}

	/**
	 * Update sampled row-height average from the currently realized, upgraded
	 * data rows. Samples are retained by row id so scrolling through mixed-height
	 * rows converges instead of replacing the estimate with only the current
	 * viewport slice.
	 */
	private _updateMeasuredAverageRowHeight() : number | null
	{
		if(this._rowHeightLocked)
		{
			return this._rowHeightPx;
		}
		if(this._usesFixedVirtualizerRowHeight())
		{
			return this._rowHeightPx;
		}
		const rows = this._measurableRenderedRows();
		for(const row of rows)
		{
			const rowId = row.getAttribute("data-row-id") || "";
			const height = Math.ceil(row.getBoundingClientRect().height);
			if(rowId && Number.isFinite(height) && height > 0)
			{
				this._measuredRowHeightByRowId.set(rowId, height);
			}
		}
		if(!this._measuredRowHeightByRowId.size)
		{
			return null;
		}
		const heights = Array.from(this._measuredRowHeightByRowId.values());
		const average = Math.ceil(heights.reduce((sum, height) => sum + height, 0) / heights.length);
		const shouldSettle = average > 0 && this.embeddedVirtualized;
		const rowHeightChanged = Math.abs(average - this._rowHeightPx) > 1;
		// The first upgraded batch establishes the fixed pitch for an expandable
		// grid. Afterwards widgets can be refreshed without asking Lit to recreate
		// the virtualizer range again: the pitch is already deterministic, and an
		// extra render can replace freshly realized rows after the upgrade observer
		// has run.
		const needsLayoutUpdate = rowHeightChanged || (shouldSettle && !this._rowHeightSettled);
		if(!this._rowHeightLocked && average > 0 && needsLayoutUpdate)
		{
			this._setRowHeight(average, "measured");
			this._syncEmbeddedChildGridRowHeightEstimates();
			// Changing to the sparse layout recreates Lit's virtualizer. Seed the
			// current tbody before that swap: an empty grid host otherwise clips its
			// own viewport to zero, clears its range, and can never render a row to
			// recover a height from.
			this._syncRowsMinHeight();
			this.requestUpdate();
			this._scheduleVirtualizerLayoutSync();
			if(this.embeddedVirtualized)
			{
				this._embeddedRowHeightSettled = true;
				this._scheduleEmbeddedVirtualizedHeightSync();
			}
		}
		if(average > 0)
		{
			if(shouldSettle)
			{
				this._rowHeightSettled = true;
				this._embeddedRowHeightSettled = true;
				this._syncFixedRowHeightMode();
				this._scheduleSparseVirtualizerLayoutActivation();
				this._logExpansionRowHeightWarning();
			}
		}
		return average;
	}

	/**
	 * Return realized data rows that should contribute to row-height sampling.
	 */
	private _measurableRenderedRows() : HTMLElement[]
	{
		const rowsBody = this._rowsBody as HTMLElement | null;
		if(this._isTileView())
		{
			return this._renderedDataRowElements(rowsBody);
		}
		return Array.from(rowsBody?.querySelectorAll(":scope > tr[data-row-id]:not([data-et2dg-placeholder]):not(.dg-row-placeholder)") || []) as HTMLElement[];
	}

	/**
	 * Measure realized child rows so the loading fallback can reuse the sampled
	 * upgraded average row height.
	 */
	private _measureEmbeddedVirtualizedRowHeight() : number | null
	{
		if(this._rowHeightLocked || this._usesFixedVirtualizerRowHeight() || this._embeddedRowHeightSettled)
		{
			return this._rowHeightPx;
		}
		const sampledHeights = Array.from(this._measuredRowHeightByRowId.values()).filter((height) => Number.isFinite(height) && height > 0);
		if(sampledHeights.length)
		{
			return Math.ceil(sampledHeights.reduce((sum, height) => sum + height, 0) / sampledHeights.length);
		}
		const rows = this._measurableRenderedRows();
		const measuredHeights = rows
			.map((row) => Math.ceil(row.getBoundingClientRect().height))
			.filter((height) => Number.isFinite(height) && height > 0);
		if(!measuredHeights.length)
		{
			return null;
		}
		return Math.ceil(measuredHeights.reduce((sum, height) => sum + height, 0) / measuredHeights.length);
	}

	/**
	 * Resolve owning component prefix for generated preference keys.
	 */
	private _columnPreferenceOwnerPrefix() : string | null
	{
		const rootHost = (this.getRootNode() as ShadowRoot)?.host as HTMLElement | undefined;
		const ownerTag = String(rootHost?.localName || "").toLowerCase();
		if(ownerTag.startsWith("et2-") && ownerTag !== "et2-datagrid")
		{
			return ownerTag.replace(/^et2-/, "");
		}
		return null;
	}

	/**
	 * Resolve source row-template id used in generated preference keys.
	 */
	private _columnPreferenceTemplateId() : string | null
	{
		const fromTemplateData = String(this.templateData?.rowTemplateId || "").trim();
		if(fromTemplateData)
		{
			return fromTemplateData;
		}
		const fromTemplateXml = String(this.templateData?.rowTemplateXml?.getAttribute?.("id") || this.templateData?.rowTemplateXml?.id || "").trim();
		if(fromTemplateXml)
		{
			return fromTemplateXml;
		}
		const fromParentTemplate = String((this as any)._parent?.template || "").trim();
		return fromParentTemplate || null;
	}

	/**
	 * Resolve final preference key (custom override or generated default).
	 */
	private _columnPreferenceName() : string | null
	{
		const customKey = String(this.columnPreferenceName || "").trim();
		if(customKey)
		{
			return customKey;
		}
		const ownerPrefix = this._columnPreferenceOwnerPrefix();
		const rowTemplateId = this._columnPreferenceTemplateId();
		if(!ownerPrefix || !rowTemplateId)
		{
			return null;
		}
		return `${ownerPrefix}-${rowTemplateId}-prefs`;
	}

	/**
	 * Check whether column preference load/save should be skipped for this grid.
	 */
	private _isColumnPersistenceDisabled() : boolean
	{
		return this.noColumnPersistence || this.noVisibleHeader;
	}

	/**
	 * Apply columns provided by an external caller (Et2Nextmatch.setColumns()/
	 * set_columns(), e.g. favorites or app state restore).
	 *
	 * Marks the current columnPreferenceName key as already loaded so the
	 * willUpdate cycle this triggers doesn't immediately merge the persisted
	 * (or template-default) column state back over the caller's explicit
	 * selection - this path is an override, not a preference change, and
	 * must not read from or write to the stored preference.
	 */
	applyExternalColumns(columns : Et2DatagridColumn[])
	{
		this.columns = columns;
		this._loadedColumnPreferenceKey = this._columnPreferenceName();
	}

	/**
	 * Check whether interactive column resizing should be disabled for this grid.
	 */
	private _isColumnResizeDisabled() : boolean
	{
		return this.noColumnResize || this.noVisibleHeader;
	}

	/**
	 * Apply persisted column state once per resolved key.
	 */
	private _loadColumnPreferencesIfNeeded()
	{
		if(this._isColumnPersistenceDisabled())
		{
			this._loadedColumnPreferenceKey = this._columnPreferenceName();
			return;
		}
		if(!this.columns?.length)
		{
			return;
		}
		const key = this._columnPreferenceName();
		const app = this.getInstanceManager?.()?.app || this.egw()?.app_name?.();
		if(!key || !app || this._loadedColumnPreferenceKey === key)
		{
			return;
		}
		let stored = null;
		try
		{
			stored = this.egw()?.preference?.(key, app);
		}
		catch(e)
		{
			this._loadedColumnPreferenceKey = key;
			return;
		}
		this._loadedColumnPreferenceKey = key;
		if(!stored)
		{
			// No preference saved under this key - fall through with empty
			// entries so the merge below resets columns to default visibility,
			// rather than inheriting `hidden` left over from a different key.
			stored = [];
		}
		if(typeof stored === "string")
		{
			try
			{
				stored = JSON.parse(stored);
			}
			catch(e)
			{
				return;
			}
		}
		const rawEntries = Array.isArray(stored?.columns) ? stored.columns : stored;
		const entries = Array.isArray(rawEntries)
			? rawEntries
			: rawEntries && typeof rawEntries === "object"
				? Object.keys(rawEntries)
					.sort((left, right) =>
					{
						const leftNum = Number(left);
						const rightNum = Number(right);
						if(Number.isFinite(leftNum) && Number.isFinite(rightNum))
						{
							return leftNum - rightNum;
						}
						return left.localeCompare(right);
					})
					.map((key) => rawEntries[key])
				: [];
		const orderByKey = new Map<string, number>();
		const byKey = new Map<string, { width? : string; hidden? : boolean; customFields? : string[] }>();
		const templateDefaultHiddenByKey = new Map<string, boolean>();
		for(const column of (this.templateData?.sourceColumns || this.templateData?.columns || this.columns || []))
		{
			templateDefaultHiddenByKey.set(String(column.key), !!column.hidden);
		}
		const normalizeVisibleCustomfields = (source : any) : string[] | undefined =>
		{
			if(Array.isArray(source))
			{
				return source
					.map((name) => String(name || "").trim())
					.filter(Boolean);
			}
			return undefined;
		};
		for(let i = 0; i < entries.length; i++)
		{
			const entry = entries[i];
			const keyValue = String(entry?.key || "");
			if(!keyValue)
			{
				continue;
			}
			orderByKey.set(keyValue, i);
			byKey.set(keyValue, {
				width: typeof entry?.width === "string" ? entry.width : undefined,
				hidden: typeof entry?.hidden === "boolean" ? entry.hidden : undefined,
				customFields: normalizeVisibleCustomfields(entry?.customFields)
			});
		}
		const nextColumns = [...this.columns].sort((left, right) =>
		{
			const leftIndex = orderByKey.get(String(left.key));
			const rightIndex = orderByKey.get(String(right.key));
			if(typeof leftIndex === "number" && typeof rightIndex === "number")
			{
				return leftIndex - rightIndex;
			}
			if(typeof leftIndex === "number")
			{
				return -1;
			}
			if(typeof rightIndex === "number")
			{
				return 1;
			}
			return 0;
		}).map((column) =>
		{
			const persisted = byKey.get(String(column.key));
			if(!persisted)
			{
				// Not mentioned by this preference - fall back to the template's
				// authored default rather than inheriting a `hidden` left over
				// from a previously loaded columnPreferenceName.
				const templateHidden = templateDefaultHiddenByKey.get(String(column.key)) ?? false;
				return column.hidden !== templateHidden ? {...column, hidden: templateHidden} : column;
			}
			const header = column.header as any;
			if(persisted.customFields)
			{
				this._applyCustomfieldPreferenceToHeader(header, persisted.customFields);
			}
			return {
				...column,
				width: persisted.width ?? column.width,
				hidden: typeof persisted.hidden === "boolean" ? persisted.hidden : column.hidden
			};
		});
		this.columns = nextColumns;
		this.dispatchEvent(new CustomEvent("et2-columns-changed", {
			detail: {columns: this.columns},
			bubbles: true,
			composed: true
		}));
	}

	private _applyCustomfieldPreferenceToHeader(
		header : any,
		customFields : string[]
	)
	{
		if(!header)
		{
			return;
		}
		if(typeof header.setCustomfieldVisibility === "function")
		{
			header.setCustomfieldVisibility(customFields.reduce((fields, name) =>
			{
				fields[name] = true;
				return fields;
			}, {} as Record<string, boolean>));
			return;
		}
		header.setAttribute?.("fields", customFields.join(","));
	}


	/**
	 * Persist current column state for later restore.
	 */
	private _persistColumnPreferences()
	{
		if(this._isColumnPersistenceDisabled())
		{
			return;
		}
		const key = this._columnPreferenceName();
		const app = this.getInstanceManager?.()?.app || this.egw()?.app_name?.();
		if(!key || !app)
		{
			return;
		}
		const value = (this.columns || []).map((column) => ({
			key: String(column.key),
			width: typeof column.width === "string" ? column.width : undefined,
			hidden: !!column.hidden,
			/**
			 * Persist only selected customfield names for clarity and compactness.
			 */
			customFields: (() =>
			{
				const header = column.header as any;
				if(typeof header?.getCustomfieldVisibility !== "function")
				{
					return undefined;
				}
				const visibility = header.getCustomfieldVisibility();
				if(!visibility || typeof visibility !== "object")
				{
					return undefined;
				}
				return Object.keys(visibility).filter((name) => visibility[name] === true);
			})()
		}));
		try
		{
			this.egw()?.set_preference?.(app, key, value);
		}
		catch(e)
		{
		}
	}

	/**
	 * Queue a chunk request once and reserve placeholder capacity for its expected rows.
	 */
	private _queueRequest(start : number, requestedCount : number, requestKey : string)
	{
		if(this._queuedRequests.has(requestKey) || this._inFlightRequestKeys.has(requestKey))
		{
			return;
		}
		this._queuedRequests.set(requestKey, {start, requestedCount, requestKey});
		this._pendingPlaceholderRequests.set(requestKey, {start, requestedCount});
		this._pendingPlaceholderCount += this._isEmbeddedInitialLoading() ? Math.min(requestedCount, 1) : requestedCount;
		this.requestUpdate();
	}

	/**
	 * Debounce queued-request processing so fast scrolling can coalesce bursts.
	 */
	private _scheduleQueuedRequestProcessing()
	{
		if(this._queuedRequestTimer !== null)
		{
			window.clearTimeout(this._queuedRequestTimer);
		}
		this._queuedRequestTimer = window.setTimeout(() =>
		{
			this._processQueuedRequests();
		}, this._requestDispatchDelayMs);
	}

	/**
	 * Build a deterministic key for one fetch request using range + provider query signature.
	 */
	private _requestKey(start : number, requestedCount : number) : string
	{
		const querySignature = this.dataProvider?.getQuerySignature?.() || "";
		return `${start}:${requestedCount}:${querySignature}`;
	}

	/**
	 * Starts this fetch's own 60s timer. If it fires and the fetch is still pending,
	 * show the shared dialog - but only if none is already showing (a second slow
	 * fetch's timer firing while one dialog is already up just no-ops; that fetch is
	 * still in _inFlightFetchPromises, so the existing dialog's "give up" covers it too).
	 */
	private _armSlowFetchTimer(fetchPromise : Promise<Et2DatagridPageResult> & { abort? : () => void }) : void
	{
		const timer = window.setTimeout(() =>
		{
			this._slowFetchTimers.delete(timer);
			if(!this._inFlightFetchPromises.has(fetchPromise) || this._slowFetchDialog)
			{
				return;	// already settled/discarded, or another dialog is already asking
			}
			this._slowFetchDialog = this._showSlowFetchDialog(() => this._giveUpOnPendingFetches());
		}, Et2Datagrid.SLOW_FETCH_TIMEOUT_MS);
		this._slowFetchTimers.add(timer);
	}

	/**
	 * "Give up": abort everything currently in flight and forget about it immediately -
	 * no separate bookkeeping of what was aborted, just remove it so it's plain
	 * untracked state, exactly as if it had never been requested. Any other fetch's
	 * still-pending timer will find it's no longer tracked when it fires and no-op.
	 */
	private _giveUpOnPendingFetches() : void
	{
		const pending = Array.from(this._inFlightFetchPromises);
		this._inFlightFetchPromises.clear();
		for(const fetchPromise of pending)
		{
			fetchPromise.abort?.();
		}
	}

	/**
	 * Normal settle path (success or a real error) - a no-op if this fetch was already
	 * removed by _giveUpOnPendingFetches(). If this was the last fetch pending and a
	 * dialog is still up (unanswered), its question is now moot - dismiss it.
	 */
	private _untrackInFlightFetch(fetchPromise : Promise<Et2DatagridPageResult> & { abort? : () => void }) : void
	{
		this._inFlightFetchPromises.delete(fetchPromise);
		if(this._inFlightFetchPromises.size === 0 && this._slowFetchDialog)
		{
			this._slowFetchDialog.destroy();
			this._slowFetchDialog = null;
		}
	}

	/**
	 * Show a "still waiting?" confirmation after a slow fetch. Yes/dismiss = keep
	 * waiting, No = give up and abort every fetch currently in flight for this grid.
	 *
	 * Answered via getComplete() rather than the constructor `callback` param: callback
	 * only fires on an actual button click, but _slowFetchDialog must be cleared on
	 * EVERY dismissal path (X, Escape, backdrop click too) or the single-dialog guard
	 * in _armSlowFetchTimer() would permanently block any future dialog for this grid
	 * the first time someone dismisses one without clicking Yes/No. getComplete()
	 * resolves on every close path, covering all of them.
	 */
	private _showSlowFetchDialog(onGiveUp : () => void) : Et2Dialog
	{
		const dialog = Et2Dialog.show_dialog(
			undefined,
			this.egw().lang("This request is taking longer than expected. Keep waiting?"),
			this.egw().lang("Still working"),
			{},
			Et2Dialog.BUTTONS_YES_NO,
			Et2Dialog.WARNING_MESSAGE,
			undefined,
			this.egw()
		);
		dialog.getComplete().then(([button_id] : [number, object]) =>
		{
			this._slowFetchDialog = null;
			if(button_id === Et2Dialog.NO_BUTTON)
			{
				onGiveUp();
			}
			// YES_BUTTON, or dismissed via X/escape/backdrop (button_id null): keep
			// waiting - the safe default, no-op.
		});
		return dialog;
	}

	/**
	 * Request one page from provider and merge rows preserving uniqueness.
	 */
	private async _fetchPage(start : number, requestedCount : number = 0, requestKey : string = "")
	{
		if(!this.dataProvider)
		{
			if(requestKey)
			{
				this._inFlightRequestKeys.delete(requestKey);
				this._pendingPlaceholderRequests.delete(requestKey);
			}
			this._syncLoadingFromInFlight();
			return;
		}

		// Capture the query this request was issued for. A response that arrives after
		// the query has since changed (the user switched mail folders, or any other
		// filter change, while this fetch was still in flight) must be detected and
		// discarded here instead of being merged into what is by then a different
		// query's grid.
		const dispatchQuerySignature = this.dataProvider?.getQuerySignature?.() || "";
		let stale = false;
		let discarded = false;
		let fetchPromise : (Promise<Et2DatagridPageResult> & { abort? : () => void }) | undefined;

		try
		{
			fetchPromise = this.dataProvider.fetchPage(start, requestedCount || this.pageSize) as any;
			this._inFlightFetchPromises.add(fetchPromise);
			this._armSlowFetchTimer(fetchPromise);

			const response = await fetchPromise;
			stale = (this.dataProvider?.getQuerySignature?.() || "") !== dispatchQuerySignature;
			if(stale)
			{
				console.warn("Stale fetch discarded",{fetched: dispatchQuerySignature, current: this.dataProvider?.getQuerySignature?.() || ""});
				return;
			}
			this.fetchFailed = false;
			this.fetchErrorMessage = "";
			this._hasFetchedOnce = true;
			if(typeof response.total !== "undefined")
			{
				this.total = response.total ?? null;
			}

			for(let rowOffset = 0; rowOffset < (response.rows || []).length; rowOffset++)
			{
				const row = response.rows[rowOffset];
				if(this.displayedRowIds.has(row.id))
				{
					continue;
				}
				this.displayedRowIds.add(row.id);
				const index = start + rowOffset;
				this._rowsByIndex[index] = this.dataProvider?.getRowData ? {id: row.id} : row;
			}
			this.rows = this._rowsByIndex.filter(Boolean) as Et2DatagridRow[];
		}
		catch(e)
		{
			if(fetchPromise && this._inFlightFetchPromises.has(fetchPromise))
			{
				this.fetchFailed = true;
				this._hasFetchedOnce = true;
				// Store message so state template can surface meaningful diagnostics.
				this.fetchErrorMessage = e?.message || "";
			}
			else
			{
				// Already removed by a deliberate "give up" (see _giveUpOnPendingFetches())
				// - discard silently.  Leave fetchFailed/fetchErrorMessage untouched, and
				// don't let requestKey be marked completed below, so this range is retried
				// whenever it's next requested.
				discarded = true;
			}
		}
		finally
		{
			if(fetchPromise)
			{
				this._untrackInFlightFetch(fetchPromise);
			}
			if(requestedCount > 0)
			{
				this._pendingPlaceholderCount = Math.max(0, this._pendingPlaceholderCount - requestedCount);
			}
			if(requestKey)
			{
				// A discarded stale/given-up response must NOT be marked completed - it
				// never contributed rows to the (now current) query, so a later request for
				// the same requestKey (e.g. switching back to this folder) must still fetch it.
				if(!this.fetchFailed && !stale && !discarded)
				{
					this._completedRequestKeys.add(requestKey);
				}
				this._inFlightRequestKeys.delete(requestKey);
				this._pendingPlaceholderRequests.delete(requestKey);
			}
			this._syncLoadingFromInFlight();
			if(this.fetchFailed)
			{
				this.dispatchEvent(new CustomEvent("et2-loading-error", {bubbles: true, composed: true}));
			}
			else if(!this.fetching)
			{
				this.dispatchEvent(new CustomEvent("et2-loading-done", {bubbles: true, composed: true}));
			}
			this._reconcileRowRenderState();
			if(this.embeddedVirtualized)
			{
				this._scheduleVirtualizerLayoutSync();
				this._scheduleEmbeddedVirtualizedHeightSync();
				void this.updateComplete.then(() =>
				{
					if(this.isConnected)
					{
						this._scheduleEmbeddedSelfScrollSync(
							this._embeddedFetchedRangeOverlapsViewport(start, requestedCount)
						);
					}
				});
			}
		}
	}

	/**
	 * Clear rendered rows and related in-memory row id tracking.
	 */
	private _clearRows()
	{
		this.rows = [];
		this._rowsByIndex = [];
		this._rowRenderVersionById.clear();
		this.displayedRowIds.clear();
		this._completedRequestKeys.clear();
		this._expandedRowHeightByParentRowId.clear();
		this._expandedVirtualItemHeights.clear();
		this._virtualItems = [];
		this._virtualItemsSignature = "";
		this._measuredRowHeightByRowId.clear();
		this._rowHeightSettled = false;
		this._embeddedRowHeightSettled = false;
		this._sparseVirtualizerLayoutActive = false;
		if(this._deferredEmbeddedRemeasureTimer !== null)
		{
			clearTimeout(this._deferredEmbeddedRemeasureTimer);
			this._deferredEmbeddedRemeasureTimer = null;
		}
		this._deferredEmbeddedRemeasureChildGrids.clear();
		this._clearQueuedRequests();
		this._clearRowUpgradeQueue();
	}

	/**
	 * Drop queued (not yet dispatched) requests and clear any scheduled dispatch timer.
	 */
	private _clearQueuedRequests()
	{
		this._queuedRequests.clear();
		this._pendingPlaceholderRequests.clear();
		this._pendingPlaceholderCount = 0;
		if(this._queuedRequestTimer !== null)
		{
			window.clearTimeout(this._queuedRequestTimer);
			this._queuedRequestTimer = null;
		}
	}

	/**
	 * Keep loading flags consistent with in-flight request count.
	 */
	private _syncLoadingFromInFlight()
	{
		const hasInFlight = this._inFlightRequestKeys.size > 0;
		this.fetching = hasInFlight;
		this.loading = hasInFlight;
	}

	/**
	 * Dispatch all queued chunk requests in FIFO snapshot order.
	 */
	private _processQueuedRequests()
	{
		this._queuedRequestTimer = null;
		if(!this._queuedRequests.size)
		{
			return;
		}
		const selected = Array.from(this._queuedRequests.values());
		for(const entry of selected)
		{
			this._queuedRequests.delete(entry.requestKey);
			this._inFlightRequestKeys.add(entry.requestKey);
			this.fetching = true;
			this.loading = true;
			this.dispatchEvent(new CustomEvent("et2-loading-start", {bubbles: true, composed: true}));
			this._fetchPage(entry.start, entry.requestedCount, entry.requestKey);
		}
		this._reconcileRowRenderState();
	}

	/**
	 * Check whether at least one row in a chunk has not been materialized yet.
	 */
	private _hasMissingRowsInChunk(start : number) : boolean
	{
		if(this.total === null)
		{
			return !this._rowsByIndex[start];
		}
		const end = Math.min(this.total, start + this.pageSize);
		for(let index = start; index < end; index++)
		{
			if(!this._rowsByIndex[index])
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Reconcile grid-level row render state with current data.
	 * Ensures an initial active row exists once rows are available, optionally
	 * scheduling a Lit render cycle when this runs outside the normal lifecycle.
	 */
	private _reconcileRowRenderState(requestRender : boolean = true)
	{
		this._pruneLoadedNonExpandableExpandedRows();
		if(this.autoActivateFirstRow && this.activeRowIndex < 0 && this.rows.length)
		{
			// Keep keyboard navigation usable as soon as first row appears.
			this.activeRowIndex = 0;
			this.activeRowId = this.rows[0].id;
			this.anchorRowIndex = 0;
		}
		if(requestRender)
		{
			this.requestUpdate();
		}
	}

	/**
	 * Collapse expanded rows that become non-expandable after refreshed data loads.
	 */
	private _pruneLoadedNonExpandableExpandedRows()
	{
		if(!this.expansionConfig)
		{
			return;
		}
		const expandedRowIds = this._expandedRowIds();
		if(!expandedRowIds.size)
		{
			return;
		}
		const nextExpandedRowIds = new Set(expandedRowIds);
		for(let rowIndex = 0; rowIndex < this._rowsByIndex.length; rowIndex++)
		{
			const row = this._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			const rowId = this._rowExpansionId(row);
			if(nextExpandedRowIds.has(rowId) && !this._isRowExpandable(row, rowIndex))
			{
				nextExpandedRowIds.delete(rowId);
			}
		}
		if(nextExpandedRowIds.size === expandedRowIds.size)
		{
			return;
		}
		for(const rowId of expandedRowIds)
		{
			if(!nextExpandedRowIds.has(rowId))
			{
				this._expandedRowHeightByParentRowId.delete(rowId);
			}
		}
		if(this.expansionConfig.onExpandedRowIdsChanged)
		{
			this.expansionConfig.onExpandedRowIdsChanged(nextExpandedRowIds);
		}
		else if(this.expansionConfig.expandedRowIds)
		{
			this.expansionConfig.expandedRowIds.clear();
			nextExpandedRowIds.forEach((id) => this.expansionConfig!.expandedRowIds!.add(id));
		}
		else
		{
			this._internalExpandedRowIds = nextExpandedRowIds;
		}
	}

	/**
	 * Determine initial row height from template hints (`height`, `min-height`, inline style).
	 */
	private _resolveTemplateRowHeightPx() : number | null
	{
		const template = this.templateData?.rowTemplate;
		const row = template?.content?.firstElementChild as HTMLElement | null;
		const candidate =
			row?.style?.height ||
			row?.style?.minHeight ||
			row?.getAttribute?.("height") ||
			row?.getAttribute?.("data-row-height") ||
			null;
		if(!candidate)
		{
			return null;
		}
		return this._lengthToPx(candidate);
	}

	private _resolveExternalCssRowHeightPx() : number | null
	{
		const candidate = this.style.getPropertyValue("--row-height") || getComputedStyle(this).getPropertyValue("--row-height");
		return candidate ? this._lengthToPx(candidate.trim()) : null;
	}

	/**
	 * Keep the virtualizer's numeric pitch current. Only explicit and embedded
	 * fixed-height contracts are reflected into CSS; a measured main-row average
	 * is an estimate, not a minimum visual row height.
	 */
	private _setRowHeight(rowHeight : number, source : Et2DatagridRowHeightSource)
	{
		const previousRowHeight = this._rowHeightPx;
		this._rowHeightPx = rowHeight;
		this._rowHeightSource = source;
		if(source === "template" || source === "parent" || source === "api")
		{
			this.style.setProperty("--row-height", `${rowHeight}px`);
		}
		else if(source === "default" && this.style.getPropertyValue("--row-height") === `${previousRowHeight}px`)
		{
			this.style.removeProperty("--row-height");
		}
	}

	/**
	 * Resolve the row-height estimate shared by CSS, virtualizer reservations,
	 * and embedded child grids.
	 */
	private _effectiveRowHeightPx() : number
	{
		return Math.max(
			this._rowHeightPx || 0,
			this._resolveTemplateRowHeightPx() || 0,
			44
		);
	}

	/**
	 * Only embedded subgrids need a fixed virtualizer pitch for their reserved
	 * height. Main grids keep Lit's variable-height layout so their rows can grow
	 * with content. An explicit row height remains fixed anywhere it is used.
	 */
	private _usesFixedVirtualizerRowHeight() : boolean
	{
		return this._rowHeightLocked ||
		       (this.embeddedVirtualized && this._embeddedRowHeightSettled);
	}

	private _syncFixedRowHeightMode()
	{
		// A root grid with expandable rows needs a stable virtualizer pitch, but
		// its normal rows must still grow with their content. Only embedded grids
		// need CSS-level clipping to match their reserved subgrid height. An
		// explicit template/CSS/parent height remains an intentional fixed-row
		// contract regardless of where the grid is rendered.
		const fixed = this._rowHeightLocked ||
		              (this.embeddedVirtualized && this._embeddedRowHeightSettled);
		if(this.fixedRowHeight !== fixed)
		{
			this.fixedRowHeight = fixed;
			this.requestUpdate();
		}
	}

	private _logExpansionRowHeightWarning()
	{
		if(!this.expansionConfig?.rendersSubgrid || this._rowHeightSource === "template" || this._rowHeightSource === "css" || this._rowHeightSource === "parent" || this._loggedExpansionRowHeightWarning)
		{
			return;
		}
		this._loggedExpansionRowHeightWarning = true;
		this.egw().debug(
			"warn",
			"Et2Datagrid expansion uses a fixed row height measured from the first rendered batch. Set an explicit row template height or --row-height for deterministic layout."
		);
	}

	/**
	 * Treat row-template height hints as authoritative. When present, skip
	 * sampled row-height averaging while still allowing row-upgrade events to
	 * fire after widgets settle.
	 */
	private _syncTemplateRowHeightHint(refreshCss : boolean = false)
	{
		const templateRowHeight = this._resolveTemplateRowHeightPx();
		const cssRowHeight = templateRowHeight || (!refreshCss && this._rowHeightSource !== "default" && this._rowHeightSource !== "css")
		                     ? null
		                     : this._resolveExternalCssRowHeightPx();
		const rowHeight = templateRowHeight || cssRowHeight;
		if(rowHeight && rowHeight > 0)
		{
			this._setRowHeight(rowHeight, templateRowHeight ? "template" : "css");
			this._rowHeightLocked = true;
			this._rowHeightSettled = true;
			this._embeddedRowHeightSettled = true;
			this.fixedRowHeight = true;
			this._measuredRowHeightByRowId.clear();
			this._syncEmbeddedChildGridRowHeightEstimates();
			return;
		}
		if(this._rowHeightSource === "template" || this._rowHeightSource === "css" || refreshCss)
		{
			this._rowHeightLocked = false;
			this._rowHeightSettled = false;
			this._embeddedRowHeightSettled = false;
			this.fixedRowHeight = false;
			this._setRowHeight(44, "default");
		}
	}

	/**
	 * Convert simple CSS lengths to pixels for row-height calculation.
	 */
	private _lengthToPx(length : string) : number | null
	{
		const value = String(length || "").trim().toLowerCase();
		if(!value)
		{
			return null;
		}
		if(/^\d+(\.\d+)?$/.test(value))
		{
			return parseFloat(value);
		}
		if(value.endsWith("px"))
		{
			return parseFloat(value);
		}
		if(value.endsWith("rem"))
		{
			return parseFloat(value) * parseFloat(getComputedStyle(document.documentElement).fontSize || "16");
		}
		if(value.endsWith("em"))
		{
			const base = parseFloat(getComputedStyle(this).fontSize || "16");
			return parseFloat(value) * base;
		}
		return null;
	}

	/**
	 * Prefetch when user is close to the end so additional rows appear without a visible wait at bottom.
	 */
	private _maybePrefetchOnScroll()
	{
		this._restoreRowFocusAfterScroll();
		if(this._queuedRequests.size)
		{
			this._scheduleQueuedRequestProcessing();
		}
	}

	/**
	 * Keep keyboard navigation working when browser focus jumps to page container during mouse-wheel scrolling.
	 */
	private _restoreRowFocusAfterScroll()
	{
		if(this.activeRowIndex < 0)
		{
			return;
		}
		const shadowActive = this.shadowRoot?.activeElement as HTMLElement | null;
		if(shadowActive)
		{
			return;
		}
		const active = document.activeElement as HTMLElement | null;
		const tag = active?.tagName?.toLowerCase?.() || "";
		if(active && active !== document.body && active !== this && tag !== "egw-app")
		{
			return;
		}
		this._focusRowByIndex(this.activeRowIndex, 2, false);
	}

	/**
	 * Build one row element from prepared template data or fallback plain cells.
	 */
	private _buildRowElement(row : Et2DatagridRow, rowIndex : number) : HTMLElement | null
	{
		const template = this.templateData?.rowTemplate;
		const templateXml = this.templateData?.rowTemplateXml;
		const rowData = this._rowDataFor(row);

		// Simple row fallback
		if(!template && !templateXml)
		{
			const tr = document.createElement(this._isTileView() ? "div" : "tr");
			tr.setAttribute("part", `${tr.getAttribute("part") || ""} row`.trim());
			tr.innerHTML = this.columns
				.filter((column) => !this._isColumnHidden(column))
				.map((column) => this._isTileView()
				                 ? `<div>${String(this._getFieldValue(rowData, column.key) ?? "")}</div>`
				                 : `<td>${String(this._getFieldValue(rowData, column.key) ?? "")}</td>`)
				.join("");
			this._ensureMetaCell(tr, row, rowIndex);
			this._markRowElement(tr, row, rowIndex);
			if(!this._isTileView())
			{
				this._applyColumnLayoutToRowElement(tr);
			}
			return tr;
		}

		let fragment : DocumentFragment | null = null;
		if(template)
		{
			fragment = document.importNode(template.content, true);
		}
		else if(templateXml)
		{
			const templateNode = document.createElement("template");
			templateNode.content.appendChild(templateXml.cloneNode(true));
			fragment = templateNode.content.cloneNode(true) as DocumentFragment;
		}
		if(!fragment)
		{
			return null;
		}

		// Fast, simple replacements
		this._populateCloneWithRow(fragment, rowData);
		const root = (fragment.firstElementChild || null) as HTMLElement | null;
		if(!root)
		{
			return null;
		}
		this._populateRowRootAttributes(root, rowData);
		root.setAttribute("part", `${root.getAttribute("part") || ""} row`.trim());
		this._ensureMetaCell(root, row, rowIndex);
		root.classList.add("loading");
		this._markRowElement(root, row, rowIndex);
		if(!this._isTileView())
		{
			this._applyColumnLayoutToRowElement(root);
		}
		return root;
	}

	/**
	 * Ensure the leading metadata cell exists and contains the row expander when needed.
	 */
	private _ensureMetaCell(rowElement : HTMLElement, row : Et2DatagridRow, rowIndex : number)
	{
		const metaSelector = this._isTileView() ? ":scope > [data-dg-meta-cell='1']" : ":scope > td[data-dg-meta-cell='1']";
		let metaCell = rowElement.querySelector(metaSelector) as HTMLTableCellElement | null;
		if(!metaCell)
		{
			metaCell = document.createElement(this._isTileView() ? "div" : "td") as HTMLTableCellElement;
			metaCell.setAttribute("data-dg-meta-cell", "1");
			metaCell.setAttribute("part", "row-meta");
			metaCell.setAttribute("aria-hidden", "true");
			rowElement.insertBefore(metaCell, rowElement.firstChild);
		}
		this._syncRowExpander(rowElement, metaCell, row, rowIndex);
		this.rowCustomizer?.({
			rowElement,
			rowData: this._rowDataFor(row),
			rowIndex,
			metaCell
		});
	}

	/**
	 * Resolve the expansion state set, using consumer-owned state when provided.
	 */
	private _expandedRowIds() : Set<string>
	{
		return this.expansionConfig?.expandedRowIds ?? this._internalExpandedRowIds;
	}

	/**
	 * Normalize a data row id for use as a stable expansion key.
	 */
	private _rowExpansionId(row : Et2DatagridRow) : string
	{
		return this._dataStoreRowIdFor(row.id);
	}

	/**
	 * Ask the consumer expansion hook whether a realized data row can expand.
	 */
	private _isRowExpandable(row : Et2DatagridRow, rowIndex : number) : boolean
	{
		if(!this.expansionConfig?.isExpandable)
		{
			return false;
		}
		try
		{
			return !!this.expansionConfig.isExpandable(this._rowForCallback(row), rowIndex);
		}
		catch(e)
		{
			this.egw()?.debug?.("error", "Et2Datagrid: expansion isExpandable hook failed", e);
			return false;
		}
	}

	/**
	 * Check whether a data row currently has an expanded detail row.
	 */
	private _isRowExpanded(row : Et2DatagridRow) : boolean
	{
		return this._expandedRowIds().has(this._rowExpansionId(row));
	}

	/**
	 * Update expansion state through the controlled callback or local fallback state.
	 */
	private _setRowExpanded(row : Et2DatagridRow, expanded : boolean)
	{
		const scrollTop = expanded ? null : this._body?.scrollTop ?? null;
		const scrollVersion = this._bodyScrollVersion;
		const rowId = this._rowExpansionId(row);
		const nextExpandedRowIds = new Set(this._expandedRowIds());
		if(expanded)
		{
			nextExpandedRowIds.add(rowId);
		}
		else
		{
			nextExpandedRowIds.delete(rowId);
			this._expandedRowHeightByParentRowId.delete(rowId);
		}
		if(this.expansionConfig?.onExpandedRowIdsChanged)
		{
			this.expansionConfig.onExpandedRowIdsChanged(nextExpandedRowIds);
		}
		else if(this.expansionConfig?.expandedRowIds)
		{
			this.expansionConfig.expandedRowIds.clear();
			nextExpandedRowIds.forEach((id) => this.expansionConfig!.expandedRowIds!.add(id));
		}
		else
		{
			this._internalExpandedRowIds = nextExpandedRowIds;
		}
		this.requestUpdate();
		if(!expanded)
		{
			this._restoreBodyScrollTopAfterLayout(scrollTop, scrollVersion);
		}
	}

	/**
	 * Synchronize the expander button and row ARIA state for one rendered row.
	 */
	private _syncRowExpander(
		rowElement : HTMLElement,
		metaCell : HTMLTableCellElement,
		row : Et2DatagridRow,
		rowIndex : number
	)
	{
		const existing = metaCell.querySelector(":scope > .dg-row-expander") as HTMLButtonElement | null;
		if(!this._isRowExpandable(row, rowIndex))
		{
			existing?.remove();
			metaCell.setAttribute("aria-hidden", "true");
			rowElement.removeAttribute("aria-expanded");
			return;
		}
		const expanded = this._isRowExpanded(row);
		const expander = existing ?? document.createElement("button");
		if(!existing)
		{
			expander.type = "button";
			expander.className = "dg-row-expander";
			expander.setAttribute("part", "row-expander");
			expander.setAttribute("data-dg-row-expander", "1");
			expander.innerHTML = `
				<span class="dg-row-expander__icon" part="row-expander-icon" aria-hidden="true">
					<slot name="expand-icon">
						<span class="dg-row-expander__chevron"></span>
					</slot>
					<slot name="collapse-icon">
						<span class="dg-row-expander__chevron"></span>
					</slot>
				</span>
			`;
			metaCell.insertBefore(expander, metaCell.firstChild);
		}
		metaCell.removeAttribute("aria-hidden");
		expander.classList.toggle("dg-row-expander--expanded", expanded);
		expander.setAttribute("aria-expanded", String(expanded));
		expander.setAttribute("aria-label", this.egw().lang(expanded ? "Collapse row" : "Expand row"));
		rowElement.setAttribute("aria-expanded", String(expanded));
	}

	/**
	 * Observe row DOM churn to upgrade widgets and recover row focus after virtualization swaps.
	 */
	private _initRowUpgradeObserver()
	{
		const rowsBody = this._rowsBody;
		if(this._rowUpgradeObservedRowsBody === rowsBody && this._rowUpgradeObserver && this._rowUpgradeRangeListener)
		{
			return;
		}
		this._rowUpgradeObserver?.disconnect();
		if(this._rowUpgradeRangeListener)
		{
			this._rowUpgradeObservedRowsBody?.removeEventListener("rangeChanged", this._rowUpgradeRangeListener);
		}
		this._rowUpgradeObserver = null;
		this._rowUpgradeRangeListener = null;
		this._rowUpgradeObservedRowsBody = null;
		if(!rowsBody)
		{
			return;
		}
		this._rowUpgradeObserver = new MutationObserver(() =>
		{
			this._upgradeRenderedRows();
			this._guardFocusAfterVirtualMutation();
		});
		this._rowUpgradeObserver.observe(rowsBody, {childList: true, subtree: true});
		// The virtualizer emits this after selecting a new realized range. Queue the
		// existing hydration pass after its directive has applied that range; this
		// covers a DOM mutation missed while the virtualizer host is recreated.
		this._rowUpgradeRangeListener = () =>
		{
			queueMicrotask(() =>
			{
				if(this._rowUpgradeObservedRowsBody !== rowsBody)
				{
					return;
				}
				this._upgradeRenderedRows();
				this._guardFocusAfterVirtualMutation();
			});
		};
		rowsBody.addEventListener("rangeChanged", this._rowUpgradeRangeListener);
		this._rowUpgradeObservedRowsBody = rowsBody;
	}

	/**
	 * Keep imperative listeners/observers attached to the current rendered nodes.
	 * Row and tile modes render different #rows elements, so firstUpdated() alone
	 * is not enough after a view switch.
	 */
	private _syncDomEventTargets()
	{
		const body = this._body;
		if(this._scrollListenerBody !== body && this._scrollListener)
		{
			this._scrollListenerBody?.removeEventListener("scroll", this._scrollListener);
			this._scrollListenerBody = null;
			if(body)
			{
				body.addEventListener("scroll", this._scrollListener, {passive: true});
				this._scrollListenerBody = body;
			}
		}
		this._initRowUpgradeObserver();
		this._syncEmbeddedChildGridObservers();
	}

	/**
	 * Virtualizer can remove the currently focused row before the replacement row is mounted.
	 * When that happens, keyboard events stop because focus leaves the grid entirely.
	 * Keep focus anchored to `activeRowIndex` after DOM churn.
	 */
	private _guardFocusAfterVirtualMutation()
	{
		if(this.activeRowIndex < 0)
		{
			return;
		}
		const shadowActive = this.shadowRoot?.activeElement as HTMLElement | null;
		const activeIsRow = !!shadowActive?.matches?.("[data-row-index]");
		if(activeIsRow)
		{
			return;
		}
		// Do not steal focus if the user intentionally moved to another interactive control.
		const active = document.activeElement as HTMLElement | null;
		const activeTag = active?.tagName?.toLowerCase?.() || "";
		if(active && active !== document.body && active !== this && activeTag !== "egw-app")
		{
			return;
		}
		this._focusGridFallback();
		this._restoreFocusAfterRender = true;
		requestAnimationFrame(() =>
		{
			if(!this._restoreFocusAfterRender || this.activeRowIndex < 0)
			{
				return;
			}
			this._focusRowByIndex(this.activeRowIndex, 10, false);
		});
	}

	/**
	 * Keep focus on the grid while virtualizer swaps row DOM so keyboard navigation remains active.
	 */
	private _focusGridFallback()
	{
		const table = this._gridTable;
		if(!table)
		{
			return;
		}
		try
		{
			table.focus({preventScroll: true});
		}
		catch(e)
		{
			table.focus();
		}
	}

	/**
	 * Render one virtual item by absolute data row index, using placeholder+fetch when data is missing.
	 */
	private _renderVirtualRow = (
		item : Et2DatagridRenderItem | number | undefined,
		rowStyleOrIndex? : Record<string, string | undefined> | number,
		requestMissingChunk : boolean = true
	) : TemplateResult =>
	{
		const rowStyle = typeof rowStyleOrIndex === "object" && rowStyleOrIndex !== null ? rowStyleOrIndex : undefined;
		// Lit Virtualizer can deliver one stale range slot after an expansion
		// changes the virtual item count. Render that slot as its absolute-index
		// placeholder instead of throwing and aborting the whole range update.
		if(typeof item === "undefined")
		{
			item = typeof rowStyleOrIndex === "number" ? rowStyleOrIndex : 0;
		}
		if(typeof item === "number")
		{
			item = {type: "row", rowIndex: item};
		}
		if(item.type === "expanded")
		{
			return this._renderExpandedRow(item, rowStyle);
		}
		const rowIndex = item.rowIndex;
		const row = this._printRows?.[rowIndex] || this._rowsByIndex[rowIndex];
		if(row)
		{
			const rowElement = this._buildRowElement(row, rowIndex);
			for(const [name, value] of Object.entries(rowStyle || {}))
			{
				if(value)
				{
					rowElement?.style.setProperty(name, value);
				}
			}
			return html`${unsafeHTML(rowElement?.outerHTML || "")}`;
		}
		if(requestMissingChunk)
		{
			this._requestChunkForRowIndex(rowIndex);
		}
		const placeholderRowId = `placeholder:${rowIndex}`;
		if(this._isTileView())
		{
			return html`
                <div
                        class="dg-row-placeholder dg-tile-placeholder"
                        data-et2dg-placeholder="1"
                        data-row-index=${String(rowIndex)}
                        data-row-id=${placeholderRowId}
                        role="row"
                        aria-rowindex=${String(rowIndex + 1)}
                        aria-selected="false"
                        tabindex=${rowIndex === this.activeRowIndex ? "0" : "-1"}
                >
                    ${this.templateData?.loaderTemplate ? html`${unsafeHTML(this._loaderHtml())}` : html`
                        <sl-skeleton effect="sheen" style="width:100%;height:100%"></sl-skeleton>`}
                </div>
			`;
		}
		return html`
            <tr
                    class="dg-row-placeholder"
                    data-et2dg-placeholder="1"
                    data-row-index=${String(rowIndex)}
                    data-row-id=${placeholderRowId}
                    role="row"
                    aria-rowindex=${String(rowIndex + 1)}
                    aria-selected="false"
                    tabindex=${rowIndex === this.activeRowIndex ? "0" : "-1"}
                    style=${styleMap(rowStyle || {})}
            >
                <td data-dg-meta-cell="1" part="row-meta" aria-hidden="true"></td>
                <td class="dg-placeholder-cell">
                    ${this.templateData?.loaderTemplate ? html`${unsafeHTML(this._loaderHtml())}` : html`
                        <sl-skeleton effect="sheen" style="width:100%"></sl-skeleton>`}
                </td>
            </tr>
		`;
	};

	/**
	 * Render the extra virtual item that hosts consumer-provided expanded content.
	 */
	private _renderExpandedRow(
		item : Extract<Et2DatagridRenderItem, { type : "expanded" }>,
		rowStyle : Record<string, string | undefined> = {}
	) : TemplateResult
	{
		const row = this._rowsByIndex[item.rowIndex];
		if(!row || !this.expansionConfig?.renderExpandedContent)
		{
			return html``;
		}
		const visibleColumns = this._visibleColumns();
		const columnSizes = this._columnWidths(visibleColumns);
		const metaColumnWidth = this._effectiveMetaColumnWidth();
		const content = this.expansionConfig.renderExpandedContent({
			row: this._rowForCallback(row),
			rowIndex: item.rowIndex,
			parentGrid: this,
			columnSizes,
			metaColumnWidth
		});
		const cachedHeight = this._expandedRowHeightByParentRowId.get(item.parentRowId);
		const cachedHeightValue = cachedHeight && cachedHeight > 0 ? `${Math.ceil(cachedHeight)}px` : undefined;
		const cachedHeightStyle = {height: cachedHeightValue, minHeight: cachedHeightValue};
		// Declare the parent's settled pitch before consumer content is stamped.
		// Embedded child grids inherit and lock this value during connection, before
		// their first render or data request can produce a competing measurement.
		const expandedContentStyle = {
			...cachedHeightStyle,
			"--row-height": `${this.rowHeightEstimatePx}px`
		};
		return html`
            <tr
                    class="dg-row-expanded"
                    data-dg-expanded-row="1"
                    data-parent-row-id=${item.parentRowId}
                    role="row"
                    aria-selected="false"
                    tabindex="-1"
                    style=${styleMap({...rowStyle, ...cachedHeightStyle})}
            >
                <td class="dg-expanded-cell" part="expanded-row" role="gridcell" style=${styleMap(cachedHeightStyle)}>
                    <div class="dg-expanded-content" part="expanded-row-content" style=${styleMap(expandedContentStyle)}>
                        ${content as any}
                    </div>
                </td>
            </tr>
		`;
	}

	/**
	 * Ensure the chunk owning `rowIndex` is queued for loading when rendered as a placeholder.
	 */
	private _requestChunkForRowIndex(rowIndex : number)
	{
		if(!this.dataProvider || this.fetchFailed || rowIndex < 0)
		{
			return;
		}
		if(this.total !== null && rowIndex >= this.total)
		{
			return;
		}
		const chunkStart = Math.floor(rowIndex / this.pageSize) * this.pageSize;
		if(!this._hasMissingRowsInChunk(chunkStart))
		{
			return;
		}
		const requestedCount = this.total !== null
		                       ? Math.max(0, Math.min(this.pageSize, this.total - chunkStart))
		                       : this.pageSize;
		if(requestedCount <= 0)
		{
			return;
		}
		const requestKey = this._requestKey(chunkStart, requestedCount);
		if(this._completedRequestKeys.has(requestKey) || this._inFlightRequestKeys.has(requestKey) || this._queuedRequests.has(requestKey))
		{
			return;
		}
		this._queueRequest(chunkStart, requestedCount, requestKey);
		this._scheduleQueuedRequestProcessing();
	}

	/**
	 * Maintain stable render items for virtualize() without confusing data row indexes
	 * with extra expansion rows.
	 */
	private _getVirtualIndexes(rowCount : number) : number[]
	{
		if(this._virtualIndexesCount !== rowCount)
		{
			this._virtualIndexes = Array.from({length: rowCount}, (_v, index) => index);
			this._virtualIndexesCount = rowCount;
		}
		return this._virtualIndexes;
	}

	/**
	 * Build virtualizer items, inserting expanded rows immediately after parents.
	 */
	private _getVirtualItems(rowCount : number) : Et2DatagridVirtualItem[]
	{
		if(this._isTileView())
		{
			return this._getVirtualIndexes(rowCount);
		}
		const expandedSignature = Array.from(this._expandedRowIds()).sort().join(",");
		if(!expandedSignature)
		{
			return this._getVirtualIndexes(rowCount);
		}
		const querySignature = this.dataProvider?.getQuerySignature?.() || "";
		const columnSignature = this._columnWidths(this._visibleColumns());
		const rowSignature = this._rowsByIndex
			.slice(0, rowCount)
			.map((row) => row ? String(row.id) : "")
			.join("|");
		const signature = `${rowCount}:${rowSignature}:${expandedSignature}:${querySignature}:${columnSignature}`;
		if(this._virtualItemsSignature === signature)
		{
			return this._virtualItems;
		}
		const items : Et2DatagridVirtualItem[] = [];
		for(let rowIndex = 0; rowIndex < rowCount; rowIndex++)
		{
			items.push(rowIndex);
			const row = this._rowsByIndex[rowIndex];
			if(row && this._isRowExpanded(row) && this._isRowExpandable(row, rowIndex))
			{
				items.push({
					type: "expanded",
					rowIndex,
					parentRowId: this._rowExpansionId(row)
				});
			}
		}
		this._virtualItems = items;
		this._virtualItemsSignature = signature;
		this._expandedVirtualItemHeights.clear();
		items.forEach((item, index) =>
		{
			if(typeof item !== "number" && item.type === "expanded")
			{
				const height = this._expandedRowHeightByParentRowId.get(item.parentRowId);
				if(height)
				{
					this._expandedVirtualItemHeights.set(index, height);
				}
			}
		});
		return this._virtualItems;
	}

	/**
	 * Resolve the number of data-row slots exposed to the virtualizer.
	 */
	private _virtualRowCount() : number
	{
		if(this._isEmbeddedInitialLoading())
		{
			return 1;
		}
		const pendingPlaceholderExtent = Math.max(
			0,
			...Array.from(this._pendingPlaceholderRequests.values()).map((request) => request.start + request.requestedCount)
		);
		const materializedCount = Math.max(this._rowsByIndex.length + this._pendingPlaceholderCount, this.rows.length, pendingPlaceholderExtent);
		return this.total === null ? materializedCount : Math.max(this.total, materializedCount);
	}

	/**
	 * Embedded child grids intentionally expose only one loader row until their
	 * first data page materializes, even when the provider reports a larger total.
	 */
	private _isEmbeddedInitialLoading() : boolean
	{
		return this.embeddedVirtualized && this.rows.length === 0 && this._rowsByIndex.every((row) => row === null);
	}

	/**
	 * Before the first child page arrives, embedded grids should show only a
	 * single loading row. Once rows render, `_embeddedVirtualizedContentHeight()`
	 * takes over with measured content.
	 */
	private _embeddedVirtualizedLoadingHeight() : string | null
	{
		if(!this.embeddedVirtualized)
		{
			return null;
		}
		const rowHeight = Math.max(
			this.rowHeightEstimatePx,
			this._embeddedVirtualizedMeasuredRowHeightPx || 0
		);
		return `${rowHeight}px`;
	}

	/**
	 * Provide stable keys for realized rows, expanded rows, and deterministic placeholders.
	 */
	private _virtualRowKey = (item : Et2DatagridVirtualItem | undefined, itemIndex : number = 0) : string =>
	{
		const structureSignature = this._rowRenderStructureSignature();
		// `rangeChanged` can be queued with the previous item count while an
		// expand/collapse render has already supplied the shorter item array.
		// Keep the keyed repeat stable for that one stale slot; the next layout
		// pass replaces it with the current item at this absolute index.
		if(typeof item === "undefined")
		{
			const querySignature = this.dataProvider?.getQuerySignature?.() || "";
			return `${structureSignature}:placeholder:${querySignature}:${itemIndex}`;
		}
		if(typeof item === "number")
		{
			const row = this._rowsByIndex[item];
			if(row)
			{
				const rowId = String(row.id ?? item);
				const version = this._rowRenderVersionById.get(rowId) || 0;
				const expandedState = this._expandedRowIds().size ? `:${this._isRowExpanded(row) ? "expanded" : "collapsed"}` : "";
				return `${structureSignature}:${this._dataStoreRowIdFor(rowId)}:${version}${expandedState}`;
			}
			const querySignature = this.dataProvider?.getQuerySignature?.() || "";
			return `${structureSignature}:placeholder:${querySignature}:${item}`;
		}
		if(item.type === "expanded")
		{
			const querySignature = this.dataProvider?.getQuerySignature?.() || "";
			const columnSignature = this._columnWidths(this._visibleColumns());
			return `${structureSignature}:expanded:${item.parentRowId}:${querySignature}:${columnSignature}`;
		}
		const rowIndex = item.rowIndex;
		const row = this._rowsByIndex[rowIndex];
		if(row)
		{
			const rowId = String(row.id ?? rowIndex);
			const version = this._rowRenderVersionById.get(rowId) || 0;
			const expandedState = this._isRowExpanded(row) ? "expanded" : "collapsed";
			return `${structureSignature}:${this._dataStoreRowIdFor(rowId)}:${version}:${expandedState}`;
		}
		const querySignature = this.dataProvider?.getQuerySignature?.() || "";
		return `${structureSignature}:placeholder:${querySignature}:${rowIndex}`;
	};

	private _rowRenderStructureSignature() : string
	{
		return [
			this.view,
			this.templateData?.rowTemplateId || "",
			this.templateData?.view || ""
		].join(":");
	}

	private _rowUpgradeSignature(dataRowId : string) : string
	{
		return `${this._rowRenderStructureSignature()}:${dataRowId}`;
	}

	/**
	 * Stamp row-level accessibility and identity attributes.
	 */
	private _markRowElement(rowElement : HTMLElement, row : Et2DatagridRow, rowIndex : number)
	{
		const dataStoreRowId = this._dataStoreRowIdFor(row.id ?? rowIndex);
		rowElement.classList.toggle("dg-row-active", row.id == this.activeRowId);
		// Set alongside aria-selected below, not just left for the next deferred _syncRowAccessibilityState() pass:
		// otherwise a row rebuilt by a render-version bump (eg. Et2Datagrid.refresh() applying an in-place update)mounts without its highlight for 1 frame, and visibly flashes it back in once that pass catches up.
		rowElement.classList.toggle("dg-row-selected", this.allSelected || this.selectedRowIds.has(row.id));
		rowElement.setAttribute("role", "row");
		rowElement.setAttribute("data-row-id", dataStoreRowId);
		rowElement.setAttribute("data-row-index", String(rowIndex));
		rowElement.setAttribute("aria-rowindex", String(rowIndex + 1));
		rowElement.setAttribute("aria-selected", this.selectedRowIds.has(row.id) ? "true" : "false");
		if(this.allSelected && !this.selectedRowIds.has(row.id))
		{
			rowElement.setAttribute("aria-selected", "true");
		}
		rowElement.tabIndex = rowIndex === this.activeRowIndex ? 0 : -1;
	}

	/**
	 * Clear refresh pulse timers tied to physical row elements.
	 */
	private _clearRefreshPulseTimers()
	{
		for(const timerId of this._refreshPulseTimersByElement.values())
		{
			window.clearTimeout(timerId);
		}
		this._refreshPulseTimersByElement.clear();
	}

	/**
	 * Pulse only the rows that are currently rendered after a refresh merge completes.
	 *
	 * We intentionally do not persist this state by row id. If a row is off-screen when the
	 * refresh happens, replaying the effect later would not reflect when the change occurred.
	 */
	private _pulseRenderedRows(rowIds : string[]) : void
	{
		const normalizedRowIds = Array.from(new Set((rowIds || []).filter(Boolean)));
		if(!normalizedRowIds.length)
		{
			return;
		}
		for(const rowId of normalizedRowIds)
		{
			const renderedRow = this._findRenderedRowElement(rowId);
			if(!renderedRow)
			{
				continue;
			}
			const existingTimer = this._refreshPulseTimersByElement.get(renderedRow);
			if(existingTimer)
			{
				window.clearTimeout(existingTimer);
			}
			renderedRow.classList.remove("dg-row--refreshed");
			// Restart the CSS animation when the same visible row refreshes repeatedly.
			void renderedRow.offsetWidth;
			renderedRow.classList.add("dg-row--refreshed");
			this._refreshPulseTimersByElement.set(renderedRow, window.setTimeout(() =>
			{
				renderedRow.classList.remove("dg-row--refreshed");
				this._refreshPulseTimersByElement.delete(renderedRow);
			}, this._refreshPulseDurationMs));
		}
	}

	/**
	 * Defer refreshed-row pulse effects until Lit has rendered the merged rows.
	 */
	private _scheduleRenderedRowPulse(rowIds : string[])
	{
		const normalizedRowIds = Array.from(new Set((rowIds || []).filter(Boolean)));
		if(!normalizedRowIds.length)
		{
			return;
		}
		void this.updateComplete.then(() => this._pulseRenderedRows(normalizedRowIds));
	}

	/**
	 * Find the currently realized DOM row for a provider row id.
	 */
	private _findRenderedRowElement(rowId : string) : HTMLElement | null
	{
		const dataStoreRowId = this._dataStoreRowIdFor(rowId);
		return this._rowsBody?.querySelector(`[data-row-id="${CSS.escape(dataStoreRowId)}"]`) as HTMLElement | null;
	}

	/**
	 * Queue realized rows for post-render widget binding.
	 *
	 * Row templates are stamped as inert DOM strings for virtualizer throughput.
	 * This method finds newly realized physical rows, avoids duplicate work for
	 * the same row identity, and hands them to the batched upgrade queue where
	 * row-scoped array managers and template attributes are applied.
	 */
	private _upgradeRenderedRows()
	{
		const rowElements = this._isTileView()
		                    ? this._renderedDataRowElements(this._rowsBody)
		                    : Array.from(this._rowsBody?.querySelectorAll("[data-row-id]:not(.dg-row-placeholder)") || []) as HTMLElement[];
		for(const rowElement of rowElements)
		{
			// Skip already-upgraded instances for the same row identity.
			const dataRowId = rowElement.getAttribute("data-row-id") || "";
			const upgradeSignature = this._rowUpgradeSignature(dataRowId);
			const upgradedFor = rowElement.getAttribute("data-et2dg-upgraded-for") || "";
			if(upgradedFor === upgradeSignature && dataRowId)
			{
				continue;
			}
			const rowIndex = parseInt(rowElement.getAttribute("data-row-index") || "-1", 10);
			if(rowIndex < 0)
			{
				continue;
			}
			// Print rows live in `_printRows`, not `_rowsByIndex` (see _renderVirtualRow) -
			// without this fallback, rows rendered only for printing never get queued for
			// upgrade, leaving `$row_cont[...]` template placeholders unresolved forever.
			const row = this._printRows?.[rowIndex] || this._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			if(rowElement.getAttribute("data-et2dg-upgrade-queued") === "1")
			{
				if(this._rowUpgradeQueue.includes(rowElement))
				{
					continue;
				}
				rowElement.removeAttribute("data-et2dg-upgrade-queued");
			}
			rowElement.setAttribute("data-et2dg-upgrade-queued", "1");
			this._rowUpgradeQueue.push(rowElement);
		}
		if(this._rowUpgradeQueue.length)
		{
			this._scheduleRowUpgradeQueue();
		}
	}

	/**
	 * Cancel queued/in-flight frame work for row upgrades.
	 */
	private _clearRowUpgradeQueue()
	{
		this._rowUpgradeQueue.length = 0;
		this._rowUpgradeScheduled = false;
		if(this._rowUpgradeFrameHandle !== null)
		{
			cancelAnimationFrame(this._rowUpgradeFrameHandle);
			this._rowUpgradeFrameHandle = null;
		}
	}

	/**
	 * Schedule batched row upgrades on next frame to avoid long main-thread stalls.
	 */
	private _scheduleRowUpgradeQueue()
	{
		if(this._rowUpgradeScheduled)
		{
			return;
		}
		this._rowUpgradeScheduled = true;
		this._rowUpgradeFrameHandle = requestAnimationFrame(() =>
		{
			this._rowUpgradeScheduled = false;
			this._rowUpgradeFrameHandle = null;
			this._processRowUpgradeQueue();
		});
	}

	/**
	 * Process a bounded number of row upgrades per frame so scroll/input remain responsive.
	 */
	private _processRowUpgradeQueue()
	{
		// Keep upgrade work under roughly half a 60fps frame (~16.7ms) so scrolling,
		// input, and paint can still run in the same frame on typical hardware.
		// 8ms is a pragmatic balance between throughput and UI responsiveness.
		const budgetUntil = performance.now() + this._rowUpgradeFrameBudgetMs;
		let processed = 0;
		while(this._rowUpgradeQueue.length && processed < this._rowUpgradeBatchSize && performance.now() < budgetUntil)
		{
			const rowElement = this._rowUpgradeQueue.shift();
			if(!rowElement || !rowElement.isConnected)
			{
				continue;
			}
			rowElement.removeAttribute("data-et2dg-upgrade-queued");
			const dataRowId = rowElement.getAttribute("data-row-id") || "";
			const upgradeSignature = this._rowUpgradeSignature(dataRowId);
			const upgradedFor = rowElement.getAttribute("data-et2dg-upgraded-for") || "";
			if(upgradedFor === upgradeSignature && dataRowId)
			{
				continue;
			}
			const rowIndex = parseInt(rowElement.getAttribute("data-row-index") || "-1", 10);
			if(rowIndex < 0)
			{
				continue;
			}
			// Same print-rows fallback as _upgradeRenderedRows() above - this is where
			// the skip would otherwise silently repeat forever for print-only rows.
			const row = this._printRows?.[rowIndex] || this._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			rowElement.classList.add("loading");
			if(this._applyRowElementAttributes(rowElement, this._rowDataFor(row), rowIndex))
			{
				rowElement.setAttribute("data-et2dg-upgraded-for", upgradeSignature);
			}
			processed++;
		}
		if(this._rowUpgradeQueue.length)
		{
			this._scheduleRowUpgradeQueue();
		}
		else
		{
			this._scheduleRowsUpgradedSettle();
		}
	}

	/**
	 * Wait for upgraded row widgets to paint, update the measured row-height
	 * average, then notify height consumers that row layout is stable enough for
	 * reservation calculations.
	 */
	private _scheduleRowsUpgradedSettle()
	{
		if(this._rowWidgetsUpgradedFrame !== null)
		{
			return;
		}
		this._rowWidgetsUpgradeSettling = true;
		this._rowWidgetsUpgradedFrame = requestAnimationFrame(() =>
		{
			this._rowWidgetsUpgradedFrame = requestAnimationFrame(() =>
			{
				this._rowWidgetsUpgradedFrame = null;
				this._updateMeasuredAverageRowHeight();
				this._rowWidgetsUpgradeSettling = false;
				this.dispatchEvent(new CustomEvent("et2-row-widgets-upgraded", {
					bubbles: true,
					composed: true,
					detail: {
						averageRowHeight: this._rowHeightPx,
						rowHeightLocked: this._rowHeightLocked
					}
				}));
				if(this.embeddedVirtualized || this._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade)
				{
					this._embeddedVirtualizedHeightSyncPendingAfterRowUpgrade = false;
					this._scheduleEmbeddedVirtualizedHeightSync();
				}
				else if(this.total == this.rows.length)
				{
					// Updates are done. If all rows are loaded, ensure height covers
					// upgraded content. Do not do this for partial data sets.
					this._scheduleRowsMinHeightSync();
				}
			});
		});
	}

	/**
	 * Normalize arbitrary row identifiers for `data-row-id` usage.
	 */
	private _dataStoreRowIdFor(rowId : string | number, ensurePrefix : boolean = false) : string
	{
		return this.dataProvider.normalizeRowId(rowId, ensurePrefix);
	}

	/**
	 * Return direct rendered tile rows/items from the virtualizer host.
	 */
	private _renderedDataRowElements(rowsBody : HTMLElement | null) : HTMLElement[]
	{
		return Array.from(rowsBody?.children || [])
			.filter((element) =>
				element instanceof HTMLElement &&
				element.hasAttribute("data-row-id") &&
				!element.hasAttribute("data-et2dg-placeholder")
			) as HTMLElement[];
	}

	/**
	 * Strip known datastore prefix from `data-row-id` to recover provider row id.
	 */
	private _rowIdFromDataStoreRowId(dataStoreRowId : string) : string
	{
		return this.dataProvider.toProviderRowId(dataStoreRowId);
	}

	/**
	 * Replace simple row placeholders in text nodes.
	 */
	private _populateCloneWithRow(fragment : DocumentFragment, row : any)
	{
		const walker = document.createTreeWalker(fragment, NodeFilter.SHOW_TEXT, null);
		const texts : Text[] = [];
		let node : Node | null = null;
		while((node = walker.nextNode()) !== null)
		{
			texts.push(node as Text);
		}
		for(const text of texts.filter(t => t.nodeValue.trim()))
		{
			text.nodeValue = Et2RowProvider.resolveSimpleRowPlaceholders(
				text.nodeValue || "",
				row,
				(rowData, key) => this._getFieldValue(rowData, key)
			);
		}
	}

	/**
	 * Resolve placeholder expressions on the row root element only.
	 */
	private _populateRowRootAttributes(rowRoot : HTMLElement, row : any)
	{
		Et2RowProvider.customizeRowRootAttributes(
			rowRoot,
			row,
			(rowData, key) => this._getFieldValue(rowData, key)
		);
	}

	/**
	 * Apply row-scoped template attributes to child widgets after row insertion.
	 * This is deferred to keep scrolling/rendering responsive.
	 */
	private _applyRowElementAttributes(rowRoot : HTMLElement, rowData : any, rowIndex : number) : boolean
	{
		const attrMap = this.templateData?.rowTemplateAttrMap || {};
		const toUpgrade = [
			...(rowRoot.hasAttribute("data-et2nm-id") ? [rowRoot] : []),
			...Array.from(rowRoot.querySelectorAll("[data-et2nm-id]"))
		] as any[];
		if(!toUpgrade.length)
		{
			rowRoot.classList.remove("loading");
			return true;
		}

		const contentMgr = this.getArrayMgr("content") || new et2_arrayMgr({});
		const mgrs : any = this.getArrayMgrs?.() || {};
		let mgr = contentMgr;
		mgrs.content = contentMgr;
		const rowId = String(this._rowsByIndex[rowIndex]?.id ?? this._rowIdFor(rowData, rowIndex));
		const usePerspectiveFallback = () =>
		{
			if(mgr !== contentMgr)
			{
				return;
			}
			const mgrRowData = {};
			mgrRowData[rowIndex] = rowData;
			mgr = contentMgr.openPerspective(this as any, mgrRowData, rowIndex);
			mgrs.content = mgr;
		};
		try
		{
			// Resolve row values directly first.  The ArrayMgr perspective remains a
			// compatibility fallback for row expressions this resolver cannot handle.
			for(const element of toUpgrade)
			{
				try
				{
					const id = element.getAttribute?.("data-et2nm-id");
					const stored = id ? attrMap[id] : null;
					const handlerSources = id ? this.templateData?.rowTemplateHandlerMap?.[id] : null;
					if(handlerSources && Object.values(handlerSources).some((source) => source.includes("$") || source.includes("@")))
					{
						// Virtualized widgets are reused. These handlers are compiled with
						// the current array-manager perspective, so discard their previous
						// row-specific function before applying a new row.
						this._rowTemplateHandlerCache.delete(element);
					}
					const isCustomfieldsRow = element.localName === "et2-customfields-list";
					if(isCustomfieldsRow)
					{
						// Customfields use id="$row" for their row object, but any
						// other row-bound attribute follows normal row hydration.
						const customfieldAttributes : Record<string, any> = {};
						for(const [attribute, value] of Object.entries(stored || {}))
						{
							if(attribute === "id")
							{
								continue;
							}
							const resolved = this._resolveRowExpression(value, rowData, rowId);
							if(resolved.fallback)
							{
								usePerspectiveFallback();
							}
							const booleanValue = this._rowAttributePropertyType(element, attribute) === Boolean
								? this._directBooleanRowValue(value, rowData, rowId)
								: undefined;
							customfieldAttributes[attribute] = typeof booleanValue === "undefined" ? resolved.value : booleanValue;
						}
						if(element.setArrayMgrs)
						{
							element.setArrayMgrs(mgrs);
						}
						if(element.setArrayMgr && mgr)
						{
							element.setArrayMgr("content", mgr);
						}
						if(Object.keys(customfieldAttributes).length)
						{
							if(typeof element.transformAttributes === "function")
							{
								element.transformAttributes(customfieldAttributes);
							}
							else
							{
								Object.entries(customfieldAttributes).forEach(([attribute, value]) => element.setAttribute(attribute, String(value ?? "")));
							}
						}
						this._applyCustomfieldRowState(element, rowData);
						continue;
					}
					if(element === rowRoot)
					{
						if(stored && Object.keys(stored).length)
						{
							this._applyRowRootStoredAttributes(rowRoot, stored, rowData);
						}
						continue;
					}
					if(element.setArrayMgrs)
					{
						element.setArrayMgrs(mgrs);
					}
					if(element.setArrayMgr && mgr)
					{
						element.setArrayMgr("content", mgr);
					}
					const attributes : Record<string, any> = {};
					let hasDirectValue = false;
					let directValue : any;
					for(const [attribute, value] of Object.entries(stored || {}))
					{
						const resolved = this._resolveRowExpression(value, rowData, rowId);
						if(resolved.fallback)
						{
							usePerspectiveFallback();
						}
						const booleanValue = this._rowAttributePropertyType(element, attribute) === Boolean
							? this._directBooleanRowValue(value, rowData, rowId)
							: undefined;
						attributes[attribute] = typeof booleanValue === "undefined" ? resolved.value : booleanValue;
						if(attribute === "value" && resolved.rowValue !== undefined)
						{
							hasDirectValue = true;
							directValue = resolved.rowValue;
						}
					}
					// Row-bound ids conventionally mean "the value at this row key".
					// VFS row renderers use $row for the complete row object.
					const idBinding = stored?.id ? this._resolveRowExpression(stored.id, rowData, rowId) : null;
					const isRowObjectBinding = stored?.id === "$row" || stored?.id === "${row}";
					if(stored?.value === undefined && (idBinding?.rowValue !== undefined || isRowObjectBinding) &&
						(this._rowAttributePropertyType(element, "value") || typeof element.set_value === "function"))
					{
						attributes.value = isRowObjectBinding ? rowData : idBinding!.rowValue;
						hasDirectValue = true;
						directValue = attributes.value;
						delete attributes.id;
					}
					else if(!stored?.value && !stored?.id && typeof element.id === "string" && /^[a-zA-Z_][a-zA-Z0-9_]*$/.test(element.id) &&
						Object.prototype.hasOwnProperty.call(rowData || {}, element.id) &&
						(this._rowAttributePropertyType(element, "value") || typeof element.set_value === "function"))
					{
						attributes.value = rowData[element.id];
						hasDirectValue = true;
						directValue = attributes.value;
					}
					if(element.setArrayMgrs)
					{
						element.setArrayMgrs(mgrs);
					}
					if(element.setArrayMgr && mgr)
					{
						element.setArrayMgr("content", mgr);
					}
					if(typeof element.transformAttributes === "function")
					{
						if(!Object.keys(attributes).length)
						{
							continue;
						}
						else
						{
							element.transformAttributes(attributes);
							if(hasDirectValue)
							{
								if(typeof element.set_value === "function")
								{
									element.set_value(directValue);
								}
								else
								{
									element.value = directValue;
								}
							}
						}
					}
					else
					{
						Object.entries(attributes).forEach(([attr, value]) =>
						{
							element.setAttribute(attr, mgr.expandName(String(value)));
						});
					}
				}
				catch(e)
				{
					this.egw()?.debug?.("error", "Et2Datagrid: failed to apply row element attributes", {
						rowIndex,
						element: element?.tagName || "",
						error: e
					});
				}
			}
		}
		catch(e)
		{
			this.egw()?.debug?.("error", "Et2Datagrid: row attribute application failed", {
				rowIndex,
				error: e
			});
			rowRoot.classList.remove("loading");
			return false;
		}
		rowRoot.classList.remove("loading");
		return true;
	}

	/**
	 * Apply deferred row-root attributes through the same resolver used when the
	 * template clone is first built, preserving category class normalization.
	 *
	 * Row-root attributes are not widget attributes. In particular, class values
	 * like `$row_cont[cat_id]` must become `row_category cat_#`, not the raw
	 * category id returned by generic array-manager expansion.
	 */
	private _applyRowRootStoredAttributes(rowRoot : HTMLElement, stored : Record<string, string>, rowData : any)
	{
		Object.entries(stored).forEach(([attr, value]) =>
		{
			rowRoot.setAttribute(attr, value);
		});
		this._populateRowRootAttributes(rowRoot, rowData);
	}

	/**
	 * Apply customfields row state directly from row data and the owning header.
	 *
	 * Object properties are not preserved when the row template is cloned, so each
	 * physical row renderer needs its current value assigned. The expensive state
	 * (metadata + selected field names) is cached per customfield column and reused
	 * for every row to avoid header scans or generic array-manager transforms.
	 */
	private _applyCustomfieldRowState(element : any, rowData : any)
	{
		const columnState = this._customfieldColumnStateForRowElement(element);
		const fallback = !columnState?.customfields
			? this.getArrayMgr("modifications")?.getRoot?.()?.getEntry("~custom_fields~", true)
			: null;
		const customfields = columnState?.customfields || fallback?.customfields || element.customfields || {};
		const visibility = columnState?.visibility || fallback?.fields || null;
		if(customfields)
		{
			element.customfields = customfields;
		}
		if(visibility)
		{
			element.fields = visibility;
		}
		// No labels in rows
		element.noLabel = true;
		// Et2CustomfieldsList reads only the visible field keys, so the complete
		// row can be reused without allocating a filtered value object per row.
		element.value = rowData || {};
	}

	/**
	 * Resolve cached customfield state for the renderer's column.
	 *
	 * The fallback to the first cached customfield column supports legacy row
	 * templates where the source cell does not expose a column key.
	 */
	private _customfieldColumnStateForRowElement(element : HTMLElement) : Et2DatagridCustomfieldColumnState | null
	{
		if(!this._customfieldColumnStateByKey.size)
		{
			this._rebuildCustomfieldColumnStateCache();
		}
		const cell = element.closest("td,th") as HTMLElement | null;
		const columnKey = cell?.getAttribute("data-col-key") || "";
		if(columnKey && this._customfieldColumnStateByKey.has(columnKey))
		{
			return this._customfieldColumnStateByKey.get(columnKey) || null;
		}
		return this._customfieldColumnStateByKey.values().next().value || null;
	}

	/**
	 * Cache customfield metadata and selected field names from customfield headers.
	 *
	 * Rebuilt only when column/header state changes; row binding reads from this
	 * map instead of recomputing visibility for every row.
	 */
	private _rebuildCustomfieldColumnStateCache()
	{
		this._customfieldColumnStateByKey.clear();
		for(const column of this.columns || [])
		{
			const header = column.header as any;
			if(!header || typeof header.getCustomfieldVisibility !== "function")
			{
				continue;
			}
			const customfields = header.customfields && typeof header.customfields === "object" ? header.customfields : {};
			const visibility = header.getCustomfieldVisibility();
			const visibleFieldNames = visibility && typeof visibility === "object"
				? Object.keys(visibility).filter((name) => visibility[name] === true)
				: Object.keys(customfields);
			this._customfieldColumnStateByKey.set(String(column.key), {
				customfields,
				visibility: visibility && typeof visibility === "object" ? visibility : null,
				visibleFieldNames
			});
		}
	}

	/**
	 * Resolve stable row id from common fields with fallback index.
	 */
	private _rowIdFor(row : any, fallbackIndex : number) : string
	{
		const providerRowIdForData = (this.dataProvider as any)?.rowIdForData;
		if(typeof providerRowIdForData === "function")
		{
			return String(providerRowIdForData.call(this.dataProvider, row, fallbackIndex));
		}
		return String(row?.uid ?? row?.id ?? row?.row_id ?? fallbackIndex);
	}

	/**
	 * Resolve row payload from the provider.  Datagrid keeps row identity and
	 * index state; data ownership remains with the provider / datastore.
	 */
	private _rowDataFor(row : Et2DatagridRow | string | null | undefined) : any
	{
		if(!row)
		{
			return null;
		}
		const rowId = typeof row === "string" ? row : row.id;
		const providerRowData = this.dataProvider?.getRowData?.(rowId);
		if(typeof providerRowData !== "undefined" && providerRowData !== null)
		{
			return providerRowData;
		}
		return typeof row === "string" ? null : row.data ?? null;
	}

	/**
	 * Build the row shape expected by customization / expansion callbacks
	 * without storing that data in the datagrid's row indexes.
	 */
	private _rowForCallback(row : Et2DatagridRow) : Et2DatagridRow
	{
		return {
			id: row.id,
			data: this._rowDataFor(row)
		};
	}

	/**
	 * Resolve a field value, including dot-path lookup.
	 */
	private _getFieldValue(row : any, key : string)
	{
		if(!row || !key)
		{
			return "";
		}
		if(key.indexOf(".") > -1)
		{
			return key.split(".").reduce((acc, part) => acc && typeof acc[part] !== "undefined" ? acc[part] : "", row);
		}
		return typeof row[key] !== "undefined" ? row[key] : "";
	}

	/**
	 * Evaluate a Nextmatch boolean expression against current row/content context.
	 *
	 * Why this helper exists:
	 * Column state logic is centralized in `Et2DatagridColumnState`, but expression
	 * parsing still depends on the widget runtime (`getArrayMgr("content")`).
	 */
	private _parseColumnBooleanExpression(expression : string) : boolean
	{
		const mgr = this.getArrayMgr && this.getArrayMgr("content");
		if(mgr && typeof mgr.parseBoolExpression === "function")
		{
			return !!mgr.parseBoolExpression(expression);
		}
		return false;
	}

	/**
	 * Evaluate whether a column should be hidden (supports boolean and expression strings).
	 */
	private _isColumnHidden(column : Et2DatagridColumn) : boolean
	{
		return this._columnState.isColumnHidden(column, this._parseColumnBooleanExpression.bind(this));
	}

	/**
	 * Evaluate whether a column is disabled (not user-selectable in column chooser).
	 */
	private _isColumnDisabled(column : Et2DatagridColumn) : boolean
	{
		return this._columnState.isColumnDisabled(column, this._parseColumnBooleanExpression.bind(this));
	}

	/**
	 * Build CSS grid track definitions from visible column widths.
	 */
	private _columnWidthDescriptor(raw? : string) : {
		kind : "pixel" | "relative";
		unit : "px" | "%" | "fr";
		value : number | null
	}
	{
		return this._columnManager.columnWidthDescriptor(raw);
	}

	/**
	 * Normalize width to CSS grid units.
	 */
	private _normalizeColumnWidth(raw? : string) : string
	{
		return this._columnManager.normalizeColumnWidth(raw);
	}

	/**
	 * Normalize min/max width constraints to CSS lengths.
	 */
	private _normalizeColumnLength(raw? : string) : string
	{
		return this._columnManager.normalizeColumnLength(raw);
	}

	/**
	 * Clamp a numeric value between min and max boundaries.
	 */
	private _clamp(value : number, min : number, max : number) : number
	{
		return this._columnManager.clamp(value, min, max);
	}

	/**
	 * Convert a column length to pixels using current grid context.
	 */
	private _columnLengthToPx(
		raw : string | undefined,
		totalVisibleWidthPx : number,
		availableRelativeWidthPx : number,
		relativeWidthUnits : number
	) : number | null
	{
		return this._columnManager.columnLengthToPx(
			raw, totalVisibleWidthPx, availableRelativeWidthPx, relativeWidthUnits, this._columnResizeFloorPx()
		);
	}

	/**
	 * Build aggregate width metrics for visible columns.
	 */
	private _visibleColumnWidthMetrics(visibleColumns : Et2DatagridColumn[]) : {
		totalVisibleWidthPx : number;
		fixedWidthPx : number;
		relativeWidthUnits : number;
	}
	{
		const headerColumns = Array.from(this.shadowRoot?.querySelectorAll(".dg-header .dg-col") || []) as HTMLElement[];
		const totalVisibleWidthPx = headerColumns.reduce((sum, element) => sum + element.getBoundingClientRect().width, 0);
		return this._columnManager.visibleColumnWidthMetrics(visibleColumns, totalVisibleWidthPx);
	}

	/**
	 * Convert a numeric width into compact string representation.
	 */
	private _formatColumnWidthValue(value : number, unit : "px" | "%" | "fr") : string
	{
		return this._columnManager.formatColumnWidthValue(value, unit);
	}

	/**
	 * Hard lower bound for interactive column resizing/stealing.
	 * This does not change configured minWidth semantics.
	 */
	private _columnResizeFloorPx() : number
	{
		const fontSizePx = parseFloat(getComputedStyle(this).fontSize || "16");
		return Number.isFinite(fontSizePx) && fontSizePx > 0 ? fontSizePx : 16;
	}

	/**
	 * Build the CSS grid-template-columns value for the given columns.
	 */
	private _columnWidths(columns : Et2DatagridColumn[]) : string
	{
		return this._columnManager.columnWidths(columns);
	}

	/**
	 * Measure platform scrollbar width once so columns do not resize as rows
	 * cross the vertical overflow threshold.
	 */
	private _browserScrollbarSpace() : number
	{
		if(Et2Datagrid._browserScrollbarSpacePx !== null)
		{
			return Et2Datagrid._browserScrollbarSpacePx;
		}
		const measurementRoot = document.body || document.documentElement;
		if(!measurementRoot)
		{
			return 0;
		}
		const container = document.createElement("div");
		container.style.position = "absolute";
		container.style.top = "-9999px";
		container.style.width = "100px";
		container.style.height = "100px";
		container.style.overflow = "scroll";
		container.style.visibility = "hidden";
		measurementRoot.appendChild(container);

		const gutter = container.offsetWidth - container.clientWidth;
		container.remove();

		Et2Datagrid._browserScrollbarSpacePx = Number.isFinite(gutter) && gutter > 0 ? gutter : 0;
		return Et2Datagrid._browserScrollbarSpacePx;
	}

	/**
	 * Resolve the leading metadata column width, reserving expander space when needed.
	 */
	private _effectiveMetaColumnWidth() : string
	{
		const configured = getComputedStyle(this).getPropertyValue("--meta-column-width").trim();
		if(configured)
		{
			return configured;
		}
		return this.expansionConfig ? "calc(var(--row-expander-size, 20px) + var(--sl-spacing-2x-small))" : "0px";
	}

	/**
	 * Keep table columns aligned with currently visible columns.
	 */
	private _ensureTableColSizes()
	{
		const visibleColumns = this._visibleColumns();
		if(this._body)
		{
			this._body.style["--column-sizes"] = this._columnWidths(visibleColumns);
		}
	}

	/**
	 * Gather all exportparts from row contents so they can be passed up outside the shadowRoot
	 *
	 * @private
	 */
	private _updateExportParts()
	{
		const childParts = Array.from(this.templateData?.rowTemplate?.content?.querySelectorAll("[exportparts]") ?? [])
			.map(e =>
			{
				return e.getAttribute("exportparts")
					.split(",")
					.map(p => p.trim().split(":").pop())
					.filter(p => p);
			})
		this.setAttribute("exportparts", [...this._initialExportParts, ...childParts].join(", "));
	}

	/**
	 * Remove interact.js handlers attached to previous resize handles.
	 */
	private _teardownColumnResizeInteract()
	{
		for(const handle of this._columnResizeHandles)
		{
			interact(handle).unset();
		}
		this._columnResizeHandles = [];
	}

	/**
	 * Bind interact.js draggable listeners for current header resize handles.
	 */
	private _setupColumnResizeInteract()
	{
		if(this._isColumnResizeDisabled())
		{
			this._teardownColumnResizeInteract();
			return;
		}
		const handles = Array.from(this.shadowRoot?.querySelectorAll(".dg-col-resize-handle") || []) as HTMLElement[];
		if(!handles.length)
		{
			this._teardownColumnResizeInteract();
			return;
		}
		const sameHandles =
			handles.length === this._columnResizeHandles.length &&
			handles.every((handle, index) => handle === this._columnResizeHandles[index]);
		if(sameHandles)
		{
			return;
		}
		this._teardownColumnResizeInteract();
		for(const handle of handles)
		{
			interact(handle)
				.styleCursor(false)
				.draggable({
					startAxis: "x",
					lockAxis: "x",
					listeners: {
						start: this._handleColumnResizeStart,
						move: this._handleColumnResizeMove,
						end: this._handleColumnResizeEnd
					}
				});
		}
		this._columnResizeHandles = handles;
	}

	/**
	 * Reset drag-resize temporary state.
	 */
	private _clearColumnResizeDragState()
	{
		this._columnResizeDrag = null;
		this._resizeHelperLeftPx = null;
		this._resizeHelperWidthPx = null;
		this._resizeLimitState = null;
		this.classList.remove("dg-resizing");
		this.classList.remove("dg-resize-limit-min");
		this.classList.remove("dg-resize-limit-max");
	}

	/**
	 * Begin header column resize drag by caching current column sizing context.
	 */
	private _handleColumnResizeStart(event : InteractEvent)
	{
		const handle = event.target as HTMLElement | null;
		const headerColumn = handle?.closest(".dg-col") as HTMLElement | null;
		const root = this.shadowRoot?.querySelector(".dg-root") as HTMLElement | null;
		const columnIndexRaw = handle?.getAttribute("data-column-index") || "";
		const columnIndex = parseInt(columnIndexRaw, 10);
		if(!handle || !headerColumn || !root || Number.isNaN(columnIndex) || !this.columns[columnIndex])
		{
			return;
		}
		const visibleColumns = this._visibleColumns();
		const metrics = this._visibleColumnWidthMetrics(visibleColumns);
		const availableRelativeWidthPx = Math.max(0, metrics.totalVisibleWidthPx - metrics.fixedWidthPx);
		const column = this.columns[columnIndex];
		const parsedWidth = this._columnWidthDescriptor(column.width);
		const rootRect = root.getBoundingClientRect();
		const headerColumnRect = headerColumn.getBoundingClientRect();
		const startWidthPx = Math.max(1, headerColumnRect.width);
		const minWidthPx = this._columnLengthToPx(
			column.minWidth,
			metrics.totalVisibleWidthPx,
			availableRelativeWidthPx,
			metrics.relativeWidthUnits
		);
		const maxWidthPx = this._columnLengthToPx(
			column.maxWidth,
			metrics.totalVisibleWidthPx,
			availableRelativeWidthPx,
			metrics.relativeWidthUnits
		);
		const min = Math.max(1, this._columnResizeFloorPx(), minWidthPx ?? 1);
		const max = Math.max(min, maxWidthPx ?? Number.POSITIVE_INFINITY);
		this._columnResizeDrag = {
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
		this._resizeHelperLeftPx = headerColumnRect.left - rootRect.left;
		this._resizeHelperWidthPx = startWidthPx;
		this._resizeLimitState = null;
		this.classList.remove("dg-resize-limit-min");
		this.classList.remove("dg-resize-limit-max");
		this.classList.add("dg-resizing");
	}

	/**
	 * Update helper position while dragging without applying live column size changes.
	 */
	private _handleColumnResizeMove(event : InteractEvent)
	{
		const drag = this._columnResizeDrag;
		if(!drag)
		{
			return;
		}
		const requestedWidthPx = drag.currentWidthPx + event.dx;
		const nextWidthPx = this._clamp(requestedWidthPx, drag.minWidthPx, drag.maxWidthPx);
		drag.currentWidthPx = nextWidthPx;
		this._resizeHelperWidthPx = nextWidthPx;
		const limitState = requestedWidthPx < drag.minWidthPx ? "min"
		                                                      : requestedWidthPx > drag.maxWidthPx ? "max"
		                                                                                           : null;
		this._resizeLimitState = limitState;
		this.classList.toggle("dg-resize-limit-min", limitState === "min");
		this.classList.toggle("dg-resize-limit-max", limitState === "max");
	}

	/**
	 * Commit resized width at drag end and preserve original width unit type.
	 */
	private _handleColumnResizeEnd(_event : InteractEvent)
	{
		const drag = this._columnResizeDrag;
		if(!drag)
		{
			return;
		}
		const committed = this._columnManager.commitResize(
			this.columns || [],
			this._visibleColumns(),
			drag,
			this._columnResizeFloorPx()
		);
		if(committed)
		{
			this.columns = committed.columns;
			this.dispatchEvent(new CustomEvent("et2-columns-changed", {
				detail: {
					columns: this.columns,
					column: committed.resizedColumn
				},
				bubbles: true,
				composed: true
			}));
			this._persistColumnPreferences();
		}
		this._clearColumnResizeDragState();
	}

	/**
	 * Return columns that should be rendered, based on hidden/disabled state.
	 */
	private _visibleColumns() : Et2DatagridColumn[]
	{
		return this._columnState.visibleColumns(this.columns || [], this._parseColumnBooleanExpression.bind(this));
	}

	/**
	 * Toggle visibility for already-rendered cells without waiting for virtualizer to recycle rows.
	 */
	private _applyColumnVisibilityToRenderedRows()
	{
		if(this._isTileView())
		{
			return;
		}
		const rows = Array.from(this._rowsBody?.querySelectorAll(":scope > *") || []) as HTMLElement[];
		if(!rows.length || !this.columns?.length)
		{
			return;
		}
		for(const row of rows)
		{
			this._applyColumnLayoutToRowElement(row);
			const rowIndex = parseInt(row.getAttribute("data-row-index") || "-1", 10);
			const rowData = rowIndex >= 0 ? this._rowDataFor(this._rowsByIndex[rowIndex]) : null;
			row.querySelectorAll("et2-customfields-list").forEach((element) =>
			{
				this._applyCustomfieldRowState(element as any, rowData);
			});
		}
	}

	/**
	 * Align one row's cells with current column order + visibility.
	 */
	private _applyColumnLayoutToRowElement(row : HTMLElement)
	{
		if(this._isTileView())
		{
			return;
		}
		if(row.classList.contains("dg-row-placeholder"))
		{
			return;
		}
		const allCells = Array.from(row.children) as HTMLElement[];
		const metaCell = allCells.find((cell) => cell.getAttribute("data-dg-meta-cell") === "1") as HTMLElement | undefined;
		const cells = allCells.filter((cell) => cell.getAttribute("data-dg-meta-cell") !== "1");
		if(!cells.length)
		{
			return;
		}
		const sourceKeys = this._sourceColumnKeys.length
		                   ? this._sourceColumnKeys
		                   : (this.columns || []).map((column) => String(column.key));
		cells.forEach((cell, cellIndex) =>
		{
			const fallbackKey = sourceKeys[cellIndex] ?? "";
			const key = cell.getAttribute("data-col-key") || fallbackKey;
			if(key)
			{
				cell.setAttribute("data-col-key", key);
			}
		});
		const keyToCells = new Map<string, HTMLElement[]>();
		for(const cell of cells)
		{
			const key = cell.getAttribute("data-col-key") || "";
			if(!keyToCells.has(key))
			{
				keyToCells.set(key, []);
			}
			keyToCells.get(key)!.push(cell);
		}
		const orderedCells : HTMLElement[] = [];
		const usedCells = new Set<HTMLElement>();
		for(const column of this.columns || [])
		{
			const key = String(column.key);
			const columnCells = keyToCells.get(key);
			if(!columnCells?.length)
			{
				continue;
			}
			for(const cell of columnCells)
			{
				usedCells.add(cell);
				if(this._isColumnHidden(column))
				{
					cell.remove();
					continue;
				}
				orderedCells.push(cell);
			}
		}
		// Drop unmatched cells for performance; row rebuild on column changes
		// restores them when needed.
		for(const cell of cells)
		{
			if(usedCells.has(cell))
			{
				continue;
			}
			cell.remove();
		}
		for(const cell of orderedCells)
		{
			row.appendChild(cell);
		}
		if(metaCell)
		{
			row.insertBefore(metaCell, row.firstChild);
		}
	}

	/**
	 * Handle pointer row activation + selection.
	 */
	private _handleTableClick(event : MouseEvent)
	{
		if(this._handleRowExpanderClick(event))
		{
			return;
		}
		if(this._isInteractiveRowEventTarget(event))
		{
			return;
		}
		const target = event.target as HTMLElement | null;
		const row = target?.closest("[data-row-id]") as HTMLElement | null;
		if(!row)
		{
			return;
		}
		const rowIndex = parseInt(row.getAttribute("data-row-index") || "-1", 10);
		if(rowIndex < 0)
		{
			return;
		}
		const rowData = this._rowsByIndex[rowIndex];
		if(!rowData)
		{
			return;
		}
		const rowId = rowData.id;
		this._moveActiveRow(rowIndex, true);
		const toggleFromPointer = this._lastPointerToggleSelect;
		this._lastPointerToggleSelect = false;
		this._updateSelectionFromPointer(rowId, rowIndex, event, toggleFromPointer);
	}

	/**
	 * Capture pointer modifier state before click handlers normalize selection.
	 */
	private _handleTablePointerDown(event : PointerEvent)
	{
		if(this._isRowExpanderEventTarget(event))
		{
			this._lastPointerToggleSelect = false;
			return;
		}
		if(this._isInteractiveRowEventTarget(event))
		{
			this._lastPointerToggleSelect = false;
			return;
		}
		this._lastPointerToggleSelect = !!(event.ctrlKey || event.metaKey || event.getModifierState?.("Control") || event.getModifierState?.("Meta"));
	}

	/**
	 * Detect row clicks that should be left to links or legacy clickable widgets.
	 */
	private _isInteractiveRowEventTarget(event : Event) : boolean
	{
		const path = event.composedPath?.() || [];
		let rowElement : HTMLElement | null = null;
		for(const node of path)
		{
			if(node instanceof HTMLElement && node.closest?.("[data-row-id]"))
			{
				rowElement = node.closest("[data-row-id]") as HTMLElement;
				break;
			}
		}
		if(!rowElement)
		{
			return false;
		}

		const interactiveSelector = [
			"a[href]",
			"[role='link']",
			".et2_clickable"
		].join(",");
		for(const node of path)
		{
			if(node === rowElement)
			{
				return false;
			}
			if(node instanceof HTMLElement && node.matches?.(interactiveSelector))
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect whether an event originated from a row expander control.
	 */
	private _isRowExpanderEventTarget(event : Event) : boolean
	{
		const target = event.target as HTMLElement | null;
		return !!target?.closest?.(".dg-row-expander");
	}

	/**
	 * Toggle expansion from pointer activation without also selecting the row.
	 */
	private _handleRowExpanderClick(event : MouseEvent) : boolean
	{
		const expander = (event.target as HTMLElement | null)?.closest?.(".dg-row-expander") as HTMLElement | null;
		if(!expander)
		{
			return false;
		}
		event.preventDefault();
		event.stopPropagation();
		this._toggleRowExpansionFromElement(expander);
		return true;
	}

	/**
	 * Toggle expansion from keyboard activation on the expander button.
	 */
	private _handleRowExpanderKeydown(event : KeyboardEvent) : boolean
	{
		const expander = (event.target as HTMLElement | null)?.closest?.(".dg-row-expander") as HTMLElement | null;
		if(!expander || !["Enter", " ", "Spacebar"].includes(event.key))
		{
			return false;
		}
		event.preventDefault();
		event.stopPropagation();
		this._toggleRowExpansionFromElement(expander);
		return true;
	}

	/**
	 * Resolve the owning data row for an expander element and flip its state.
	 */
	private _toggleRowExpansionFromElement(expander : HTMLElement)
	{
		const rowElement = expander.closest("[data-row-index]") as HTMLElement | null;
		const rowIndex = parseInt(rowElement?.getAttribute("data-row-index") || "-1", 10);
		const row = rowIndex >= 0 ? this._rowsByIndex[rowIndex] : null;
		if(!row || !this._isRowExpandable(row, rowIndex))
		{
			return;
		}
		this._setRowExpanded(row, !this._isRowExpanded(row));
	}

	/**
	 * Handle keyboard navigation and selection interactions.
	 */
	private _handleTableKeydown(event : KeyboardEvent)
	{
		if(this._handleRowExpanderKeydown(event))
		{
			return;
		}
		const key = event.key;
		if(key === "ArrowRight" || key === "ArrowLeft")
		{
			if(this._handleHorizontalRowNavigation(event))
			{
				return;
			}
		}
		if(!["ArrowUp", "ArrowDown", "PageUp", "PageDown", "Home", "End", " ", "a", "A"].includes(key))
		{
			return;
		}
		if(!this._rowsByIndex.length && this.total === null)
		{
			return;
		}
		if(["ArrowUp", "ArrowDown", "PageUp", "PageDown"].includes(key) &&
			this.activeRowIndex >= 0 &&
			this._hasRenderedRows() &&
			!this._isRowIndexRendered(this.activeRowIndex))
		{
			event.preventDefault();
			event.stopPropagation();
			event.stopImmediatePropagation();
			this._scrollActiveRowIntoViewThenReplayNavigation(key, event);
			return;
		}

		const pageStep = Math.max(1, Math.floor((this._body?.clientHeight || 0) / 44));
		let nextIndex = this.activeRowIndex >= 0 ? this.activeRowIndex : 0;
		const maxIndex = Math.max(0, (this.total ?? this._rowsByIndex.length) - 1);
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
			this._toggleSelectionOnActiveRow();
			return;
		}
		if((key === "a" || key === "A") && (event.ctrlKey || event.metaKey))
		{
			if(this.selectionMode === "multiple")
			{
				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				this.allSelected = true;
				this.selectedRowIds = new Set(this.rows.map((row) => row.id));
				this._syncRowAccessibilityState();
				this._emitSelectionChanged();
			}
			return;
		}

		// Prevent native page scroll on navigation keys; grid owns row navigation.
		event.preventDefault();
		event.stopPropagation();
		event.stopImmediatePropagation();
		const previous = this.activeRowIndex;
		this._restoreFocusAfterRender = true;
		this._moveActiveRow(nextIndex, true);
		if(event.shiftKey && this.selectionMode === "multiple")
		{
			this._selectRange(this.anchorRowIndex >= 0 ? this.anchorRowIndex : previous, nextIndex);
		}
		else if(this.selectionMode !== "none" && !event.ctrlKey && !event.metaKey && this.activeRowId)
		{
			// Plain navigation (no modifier) replaces the selection with the newly active row.
			// same as a plain click - so anything reacting to selection (e.g. a preview pane) keeps following the keyboard cursor.
			// Must happen synchronously here, not via the capture-phase action-shortcut handler further up the dispatch chain
			// that runs *before* _moveActiveRow() above and would act on the row that was active before this keypress, one step behind.
			this.allSelected = false;
			this.selectedRowIds = new Set([this.activeRowId]);
			this.anchorRowIndex = nextIndex;
			this._syncRowAccessibilityState();
			this._emitSelectionChanged(true);
		}
	}

	/**
	 * Handle treegrid-style horizontal navigation between parent rows and child grids.
	 */
	private _handleHorizontalRowNavigation(event : KeyboardEvent) : boolean
	{
		if(this.activeRowIndex < 0)
		{
			return false;
		}
		const row = this._rowsByIndex[this.activeRowIndex];
		if(event.key === "ArrowLeft" && this.parentRowId)
		{
			event.preventDefault();
			event.stopPropagation();
			this.dispatchEvent(new CustomEvent("et2-datagrid-leave-child-grid", {
				detail: {
					parentRowId: this.parentRowId
				},
				bubbles: true,
				composed: true
			}));
			return true;
		}
		if(!row || !this._isRowExpandable(row, this.activeRowIndex))
		{
			return false;
		}
		if(event.key === "ArrowRight")
		{
			event.preventDefault();
			event.stopPropagation();
			if(!this._isRowExpanded(row))
			{
				this._setRowExpanded(row, true);
				return true;
			}
			this.dispatchEvent(new CustomEvent("et2-datagrid-enter-expanded-row", {
				detail: {
					parentRowId: this._rowExpansionId(row),
					rowId: row.id,
					rowIndex: this.activeRowIndex
				},
				bubbles: true,
				composed: true
			}));
			return true;
		}
		if(event.key === "ArrowLeft" && this._isRowExpanded(row))
		{
			event.preventDefault();
			event.stopPropagation();
			this._setRowExpanded(row, false);
			return true;
		}
		return false;
	}

	/**
	 * Check whether a data row index currently has a realized DOM row.
	 */
	private _isRowIndexRendered(index : number) : boolean
	{
		if(index < 0)
		{
			return false;
		}
		return !!this._rowsBody?.querySelector(`[data-row-index="${index}"]`);
	}

	/**
	 * Check whether any data rows are currently realized in the DOM.
	 */
	private _hasRenderedRows() : boolean
	{
		return !!this._rowsBody?.querySelector("[data-row-index]");
	}

	/**
	 * Bring an off-screen active row into view, then replay the original key action.
	 */
	private async _scrollActiveRowIntoViewThenReplayNavigation(key : string, sourceEvent : KeyboardEvent)
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
			const body = this._body;
			if(body)
			{
				const rowHeight = this.rowHeightEstimatePx;
				const centeredTop = Math.max(0, Math.floor(activeIndex * rowHeight - body.clientHeight / 2));
				body.scrollTop = centeredTop;
			}
			for(let i = 0; i < 24; i++)
			{
				await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
				await this.updateComplete;
				if(this._isRowIndexRendered(activeIndex))
				{
					break;
				}
			}
			if(!this._isRowIndexRendered(activeIndex))
			{
				return;
			}
			this._handleTableKeydown(new KeyboardEvent("keydown", {
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
	 * Open the column selection dialog.
	 *
	 * This is public so containers can expose the same column chooser outside the
	 * datagrid header.
	 */
	async openColumnSelection(event? : Event) : Promise<void>
	{
		event?.preventDefault();
		if(this.noColumnSelection || this._isColumnPersistenceDisabled())
		{
			return;
		}
		const columns = this._columnState.toSelectionItems(
			this.columns || [],
			this._parseColumnBooleanExpression.bind(this)
		);
		this.dispatchEvent(new CustomEvent<{ columns : Et2DatagridColumnSelectionItem[] }>("et2-column-selection-items", {
			detail: {columns},
			bubbles: true,
			composed: true
		}));

		const dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
			title: this.egw().lang("Select columns"),
			template: this.egw().link(this.egw().webserverUrl + "/api/templates/default/nm_column_selection.xet"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			isModal: true,
			value: {
				modifications: {
					columns: {
						columns: columns
					}
				}
			}
		});
		document.body.appendChild(dialog);
		const [buttonId, value] = await dialog.getComplete();
		if(buttonId !== Et2Dialog.OK_BUTTON)
		{
			return;
		}
		const selectedOrder = ((value as any)?.columns || [])
			.map((value) => this._columnState.decodeSelectionId(String(value)));
		const applyDetail = {selectedOrder};
		this.dispatchEvent(new CustomEvent<{ selectedOrder : string[] }>("et2-column-selection-apply", {
			detail: applyDetail,
			bubbles: true,
			composed: true
		}));
		this.columns = this._columnState.applySelectionOrder(this.columns || [], applyDetail.selectedOrder);
		this._rebuildCustomfieldColumnStateCache();
		// Apply track sizes and current rendered-row cell visibility immediately.
		this._ensureTableColSizes();
		this._applyColumnVisibilityToRenderedRows();
		this.requestUpdate();
		this.dispatchEvent(new CustomEvent("et2-columns-changed", {
			detail: {columns: this.columns},
			bubbles: true,
			composed: true
		}));
		this._persistColumnPreferences();
	}

	/**
	 * Handle column selection action from the header button.
	 */
	protected async _handleColumnSelectionClick(event : MouseEvent) : Promise<void>
	{
		await this.openColumnSelection(event);
	}

	/**
	 * Ask the owner widget to open its normal context menu from the empty row.
	 */
	private _handleEmptyStateActionMenuClick(event : MouseEvent)
	{
		event.preventDefault();
		event.stopPropagation();
		const target = event.currentTarget as HTMLElement | null;
		const row = target?.closest?.(".dg-empty-row") as HTMLElement | null;
		(row || target || this).dispatchEvent(new MouseEvent("contextmenu", {
			bubbles: true,
			cancelable: true,
			clientX: event.clientX,
			clientY: event.clientY,
			composed: true
		}));
	}

	/**
	 * Toggle selected state for active row according to current selection mode.
	 */
	private _toggleSelectionOnActiveRow()
	{
		if(this.selectionMode === "none" || this.activeRowIndex < 0)
		{
			return;
		}
		const row = this._rowsByIndex[this.activeRowIndex];
		if(!row)
		{
			return;
		}

		this.allSelected = false;
		if(this.selectionMode === "single")
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
		this._syncRowAccessibilityState();
		this._emitSelectionChanged();
	}

	/**
	 * Update selection model from pointer gesture semantics.
	 */
	private _updateSelectionFromPointer(rowId : string, rowIndex : number, event : MouseEvent, toggleFromPointer : boolean = false)
	{
		if(this.selectionMode === "none")
		{
			return;
		}
		this.allSelected = false;
		if(this.selectionMode === "single")
		{
			this.selectedRowIds = new Set([rowId]);
			this.anchorRowIndex = rowIndex;
			this._syncRowAccessibilityState();
			this._emitSelectionChanged(true);
			return;
		}

		if(event.shiftKey && this.anchorRowIndex >= 0)
		{
			this._selectRange(this.anchorRowIndex, rowIndex);
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
		this._syncRowAccessibilityState();
		this._emitSelectionChanged(!toggle);
	}

	/**
	 * Select inclusive row range, used for shift-selection.
	 */
	private _selectRange(startIndex : number, endIndex : number)
	{
		if(this.selectionMode !== "multiple")
		{
			return;
		}
		this.allSelected = false;
		const start = Math.min(startIndex, endIndex);
		const end = Math.max(startIndex, endIndex);
		const next = new Set<string>();
		for(let i = start; i <= end; i++)
		{
			if(this._rowsByIndex[i])
			{
				next.add(this._rowsByIndex[i].id);
			}
		}
		this.selectedRowIds = next;
		this._syncRowAccessibilityState();
		this._emitSelectionChanged();
	}

	/**
	 * Move active row and optionally focus corresponding DOM row.
	 */
	private _moveActiveRow(index : number, focus : boolean)
	{
		const maxIndex = Math.max(0, (this.total ?? this._rowsByIndex.length) - 1);
		if(index < 0 || index > maxIndex)
		{
			return;
		}
		const previousActiveRowId = this.activeRowId;
		this.activeRowIndex = index;
		this.activeRowId = this._rowsByIndex[index]?.id ?? null;
		if(this.anchorRowIndex < 0)
		{
			this.anchorRowIndex = index;
		}
		this._syncRowAccessibilityState();

		if(focus)
		{
			this._focusRowByIndex(index, 10);
		}
		if(this.activeRowId !== previousActiveRowId)
		{
			this.dispatchEvent(new CustomEvent("et2-active-row-changed", {
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
	private _focusRowByIndex(index : number, retries : number = 0, allowScroll : boolean = true)
	{
		const rowElement = (Array.from(this._rowsBody?.querySelectorAll("[data-row-index]") || []) as HTMLElement[])
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
			if(this.shadowRoot?.activeElement === rowElement)
			{
				this._restoreFocusAfterRender = false;
				return;
			}
			if(retries > 0)
			{
				requestAnimationFrame(() => this._focusRowByIndex(index, retries - 1, allowScroll));
			}
			return;
		}
		if(retries <= 0)
		{
			return;
		}
		requestAnimationFrame(() => this._focusRowByIndex(index, retries - 1, allowScroll));
	}

	focusFirstRow()
	{
		if(!this._rowsByIndex.length)
		{
			return;
		}
		this._moveActiveRow(0, true);
	}

	focusRowById(rowId : string)
	{
		const rowIndex = this._rowsByIndex.findIndex((row) => row?.id === rowId);
		if(rowIndex < 0)
		{
			return;
		}
		this._moveActiveRow(rowIndex, true);
	}

	/**
	 * Read-only accessor for the row keyboard/pointer navigation currently considers
	 * "active" (focused). Callers that need to reconcile focus against selection -
	 * e.g. before executing a keyboard shortcut - use this instead of reaching into
	 * the private `activeRowIndex`/`activeRowId` bookkeeping directly.
	 */
	getActiveRowId() : string | null
	{
		return this.activeRowId;
	}

	clearActiveRow()
	{
		this.activeRowIndex = -1;
		this.activeRowId = null;
		this._syncRowAccessibilityState();
		this.requestUpdate();
	}

	clearSelection(emitSelectionChanged : boolean = true)
	{
		if(!this.selectedRowIds.size && !this.allSelected)
		{
			return;
		}
		this.selectedRowIds.clear();
		this.allSelected = false;
		this._syncRowAccessibilityState();
		this.requestUpdate();
		if(emitSelectionChanged)
		{
			this._emitSelectionChanged();
		}
	}

	/**
	 * Synchronize ARIA attributes and tabindex across rendered row DOM.
	 */
	private _syncRowAccessibilityState()
	{
		const rowElements = Array.from(this._rowsBody?.querySelectorAll("[data-row-index]") || []) as HTMLElement[];
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
				if(cell.getAttribute("data-dg-meta-cell") === "1" && this._isTileView())
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
	private _emitSelectionChanged(replaceSelection : boolean = false)
	{
		const selectedRows = this.rows.filter((row) => this.selectedRowIds.has(row.id)).map((row) => this._rowDataFor(row));
		const detail : Et2DatagridSelectionDetail = {
			selectedRowIds: Array.from(this.selectedRowIds),
			allSelected: this.allSelected,
			selectedRows,
			activeRowId: this.activeRowId,
			activeRowIndex: this.activeRowIndex,
			replaceSelection
		};
		this.dispatchEvent(new CustomEvent("et2-selection-changed", {
			detail,
			bubbles: true,
			composed: true,
			cancelable: true
		}));
	}

	/**
	 * Seed datagrid with preloaded rows and skip initial fetch.
	 */
	setInitialRows(rows : any[])
	{
		const mappedRows = (rows || []).map((row, index) => ({
			id: this._rowIdFor(row, index),
			...(this.dataProvider?.getRowData ? {} : {data: row})
		}));
		this._clearRows();
		this.selectedRowIds.clear();
		this.allSelected = false;
		this.anchorRowIndex = -1;
		this.activeRowIndex = -1;
		this.activeRowId = null;
		this.rows = mappedRows;
		this._rowsByIndex = mappedRows.slice();
		this.loading = false;
		this.fetching = false;
		this.displayedRowIds = new Set(mappedRows.map((row) => row.id));
		this._pruneLoadedNonExpandableExpandedRows();
		this.requestUpdate();
		// Symmetric with the fetch path (see et2-loading-done above): preloaded
		// rows are "loaded" too, so consumers (search-result total, drag/drop
		// registration) get the same signal regardless of load path.
		this.dispatchEvent(new CustomEvent("et2-loading-done", {bubbles: true, composed: true}));
	}

	/**
	 * Render already-fetched rows without virtualization for print output.
	 * The caller owns fetching and must call clearPrintRows() after printing.
	 */
	async setPrintRows(rowIds : string[]) : Promise<void>
	{
		if(this._printFixedRowHeight === null)
		{
			this._printFixedRowHeight = this.fixedRowHeight;
		}
		this.fixedRowHeight = false;
		this._printRows = (rowIds || []).map((rowId) => ({
			id: this.dataProvider?.normalizeRowId?.(rowId, true) || String(rowId)
		}));
		this.classList.add("print");
		this.requestUpdate();
		await this.updateComplete;
		this._syncRowsMinHeight();
		for(let frame = 0; frame < 3; frame++)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
			if((this.shadowRoot?.querySelectorAll("#rows > tr[data-row-id]").length || 0) >= rowIds.length) break;
		}
		const updates = Array.from(this.shadowRoot?.querySelectorAll("*") || [])
			.map((element : any) => element.updateComplete)
			.filter((updateComplete) => updateComplete && typeof updateComplete.then === "function");
		await Promise.allSettled(updates);
		// Must happen before _waitForPrintImages(): until a row's child widgets are
		// upgraded, an avatar's `image` attribute is still the literal unresolved
		// `$row_cont[photo]` placeholder, not a real URL - waiting for "images" to
		// load before that resolves would just be watching the wrong src.
		await this._waitForRowUpgradesToFinish();
		await this._waitForPrintImages();
		await this._waitForPrintRowsToSettle();
		this.syncPrintFlowHeight();
	}

	/**
	 * Wait for the batched row-upgrade queue to fully drain for print rows.
	 *
	 * Child-widget attributes (eg. an avatar's `fname`/`lname`, or its `image` src)
	 * are resolved from `$row_cont[...]` template placeholders by a frame-throttled
	 * queue (`_processRowUpgradeQueue`) designed to keep normal scrolling responsive -
	 * by design it only processes a small batch per animation frame. A large print
	 * job can need many frames to fully drain, so this explicitly waits for that
	 * queue to empty rather than assuming any fixed delay covers it.
	 */
	private async _waitForRowUpgradesToFinish(maxWaitMs = 15000) : Promise<void>
	{
		this._upgradeRenderedRows();
		const start = performance.now();
		while((this._rowUpgradeQueue.length || this._rowUpgradeScheduled || this._rowWidgetsUpgradeSettling)
			&& performance.now() - start < maxWaitMs)
		{
			await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		}
	}

	/**
	 * Wait for every `<img>` inside the print rows (including ones nested in other
	 * components' shadow roots, eg. avatar photos) to finish loading.
	 *
	 * Print renders rows far outside the real screen viewport (that's the whole
	 * point - the user asked for more rows than fit on screen), but native
	 * lazy-loading (eg. the addressbook row template's `<et2-lavatar loading="lazy">`)
	 * never starts a fetch for an image the browser doesn't consider near-visible.
	 * Left alone, those images simply never load - printing without a photo isn't
	 * a settling problem, it's images that were never going to load on their own.
	 * Force eager loading before waiting on them.
	 *
	 * The wait is bounded, scaled to how many images are actually pending, so a
	 * large print job gets a proportionally longer allowance instead of timing out
	 * on images that just haven't gotten their turn yet behind the browser's
	 * per-host connection limit - but one genuinely broken/404 image still can't
	 * hang printing forever.
	 */
	private async _waitForPrintImages(maxWaitMs? : number) : Promise<void>
	{
		const root = this.shadowRoot?.querySelector(".dg-body #rows");
		if(!root)
		{
			return;
		}
		const collectImages = (node : ParentNode) : HTMLImageElement[] =>
		{
			const images = Array.from(node.querySelectorAll("img"));
			for(const element of Array.from(node.querySelectorAll("*")))
			{
				if(element.shadowRoot)
				{
					images.push(...collectImages(element.shadowRoot));
				}
			}
			return images;
		};
		const allImages = collectImages(root);
		for(const img of allImages)
		{
			if(img.loading === "lazy")
			{
				img.loading = "eager";
			}
		}

		const pending = allImages.filter((img) => !img.complete);
		if(!pending.length)
		{
			return;
		}
		// The browser's per-host connection limit (typically ~6) means a large batch
		// of images queues rather than loading in parallel, so the wait must scale
		// with how many are pending, not just cap at a flat ceiling - a job of 1000
		// rows is expected to take meaningfully longer than one of 200, and giving up
		// early just means some rows print with a blank photo instead of taking the
		// extra time to get it right. The 30-minute ceiling exists only to bound a
		// truly pathological case (eg. a permanently stalled request), not as a limit
		// any real print job should come close to hitting.
		const bound = maxWaitMs ?? Math.min(1800000, Math.max(5000, pending.length * 750));
		const waits = pending.map((img) => new Promise<void>((resolve) =>
		{
			img.addEventListener("load", () => resolve(), {once: true});
			img.addEventListener("error", () => resolve(), {once: true});
		}));
		await Promise.race([
			Promise.all(waits),
			new Promise<void>((resolve) => window.setTimeout(resolve, bound))
		]);
	}

	/**
	 * Wait until the print rows' rendered extent stops changing.
	 *
	 * Row content (eg. customfield or category labels resolving async) can keep
	 * growing for a while after every component's own `updateComplete` has already
	 * resolved, since that only covers Lit's reactive update cycle, not whatever
	 * caused a later, separate update to be scheduled. Printing before that settles
	 * makes Chromium paginate against a still-changing layout, which is what produced
	 * both inconsistent page counts and a truncated tail row between otherwise
	 * identical print attempts. A fixed delay here previously guessed at "long enough",
	 * which is exactly as fragile as it sounds - poll for real stability instead.
	 */
	private async _waitForPrintRowsToSettle(maxWaitMs = 5000, requiredStableChecks = 3, intervalMs = 150) : Promise<void>
	{
		const tbody = this.shadowRoot?.querySelector<HTMLElement>(".dg-body #rows");
		if(!tbody)
		{
			return;
		}
		const start = performance.now();
		let lastHeight = -1;
		let stableCount = 0;
		while(performance.now() - start < maxWaitMs)
		{
			await new Promise<void>((resolve) => window.setTimeout(resolve, intervalMs));
			const height = tbody.scrollHeight;
			if(height === lastHeight)
			{
				stableCount++;
				if(stableCount >= requiredStableChecks)
				{
					return;
				}
			}
			else
			{
				stableCount = 0;
				lastHeight = height;
			}
		}
	}

	/** Reserve the full rendered row extent for print fragmentation. */
	public syncPrintFlowHeight() : void
	{
		if(!this._printRows)
		{
			return;
		}
		const tbody = this.shadowRoot?.querySelector<HTMLTableSectionElement>(".dg-body #rows");
		const height = tbody?.scrollHeight || 0;
		if(!height)
		{
			return;
		}
		// The print body and table use natural flow.  Pinning either to the
		// current row extent clips rows whose descendants settle afterwards.
		tbody.style.height = `${height}px`;
	}

	/** Restore normal virtualized row rendering after printing. */
	clearPrintRows() : void
	{
		if(!this._printRows)
		{
			return;
		}
		this._printRows = null;
		this.fixedRowHeight = this._printFixedRowHeight ?? this.fixedRowHeight;
		this._printFixedRowHeight = null;
		const printContainers = this.shadowRoot?.querySelectorAll<HTMLElement>(".dg-body, .dg-body table, .dg-body #rows");
		for(const element of Array.from(printContainers || []))
		{
			element.style.height = "";
		}

		this.classList.remove("print");
		// The virtualize() directive is being re-attached to the tbody fresh (it wasn't
		// present while _printRows rendered statically), so this is the same "new
		// virtualizer, host has no bounds yet" bootstrap case firstUpdated()/
		// refreshRowHeightFromCss() guard against - reuse that instead of sizing the
		// virtualizer immediately, which risks the zero-viewport feedback loop.
		this._sparseVirtualizerLayoutActive = false;
		this.requestUpdate();
		void this.updateComplete.then(() =>
		{
			if(this.isConnected && this._printRows === null)
			{
				this._syncRowsMinHeight();
				this._scheduleVirtualizerLayoutSync();
			}
		});
	}

	/**
	 * Select exactly one row by id and synchronize visual/accessibility state.
	 */
	selectSingleRow(rowId : string)
	{
		if(!rowId || this.selectionMode === "none")
		{
			return;
		}
		const rowIndex = this._rowsByIndex.findIndex((row) => row?.id === rowId);
		if(rowIndex < 0)
		{
			return;
		}
		this.selectedRowIds = new Set([rowId]);
		this.allSelected = false;
		this.activeRowIndex = rowIndex;
		this.activeRowId = rowId;
		this.anchorRowIndex = rowIndex;
		this._syncRowAccessibilityState();
		this._emitSelectionChanged();
	}

	/**
	 * Reset all grid runtime state including selection and fetch markers.
	 */
	clear()
	{
		this._clearQueuedRequests();
		this._clearRows();
		this.total = null;
		this.loading = false;
		this.fetching = false;
		this.fetchFailed = false;
		this.fetchErrorMessage = "";
		this._hasFetchedOnce = false;
		this._pendingPlaceholderCount = 0;
		this.selectedRowIds.clear();
		this.allSelected = false;
		this.anchorRowIndex = -1;
		this.activeRowIndex = -1;
		this.activeRowId = null;
	}

	/**
	 * Clear current rows and load from first page.
	 */
	async reload() : Promise<void>
	{
		this._clearQueuedRequests();
		this._clearRows();
		this.total = null;
		this.fetchFailed = false;
		this.fetchErrorMessage = "";
		this._hasFetchedOnce = false;
		this._pendingPlaceholderCount = 0;
		this.allSelected = false;
		await this.loadMore();
	}

	/**
	 * Capture loaded row slots so a virtualized embedded grid can be restored
	 * immediately if its host expanded row is recycled and later rendered again.
	 */
	rowsSnapshot() : Et2DatagridRowsSnapshot
	{
		return {
			rowsByIndex: this._rowsByIndex.slice(),
			total: this.total,
			displayedRowIds: Array.from(this.displayedRowIds),
			hasFetchedOnce: this._hasFetchedOnce
		};
	}

	/**
	 * Restore row data for a recycled embedded child grid without changing its
	 * selection or expansion state. Request/loading/error state is deliberately
	 * transient and is reset rather than restored from the snapshot.
	 */
	restoreRowsSnapshot(snapshot : Et2DatagridRowsSnapshot)
	{
		this._clearQueuedRequests();
		this._clearRows();
		this._rowsByIndex = snapshot.rowsByIndex.slice();
		this.rows = this._rowsByIndex.filter(Boolean) as Et2DatagridRow[];
		this.total = snapshot.total;
		this.displayedRowIds = new Set(snapshot.displayedRowIds);
		this._hasFetchedOnce = snapshot.hasFetchedOnce;
		this.loading = false;
		this.fetching = false;
		this.fetchFailed = false;
		this.fetchErrorMessage = "";
		this.requestUpdate();
		this.dispatchEvent(new CustomEvent("et2-loading-done", {bubbles: true, composed: true}));
	}

	/**
	 * Apply a targeted row refresh without forcing a full grid reload.
	 *
	 * The provider decides which rows changed or disappeared; the datagrid only updates
	 * rows it already has materialized locally. `edit` is the exception - see below.
	 */
	async refresh(row_ids : string[], type : Et2DatagridUpdateType) : Promise<void>
	{
		if(!this.dataProvider || this.fetchFailed)
		{
			return;
		}
		if(type === Et2DatagridUpdateTypes.DELETE)
		{
			// Delete is the one case we can satisfy entirely client-side.
			const deletedRowIds = this._normalizeRefreshRowIds(row_ids);
			const neighbours = this.getNeighbours(deletedRowIds);
			// _removeRowsById() silently drops removed ids from selectedRowIds without
			// telling anyone - capture whether a selected/active row is being removed so
			// listeners (e.g. mail's onselect-driven preview pane) can be notified below.
			const selectionAffected = deletedRowIds.some((id) => this.selectedRowIds.has(id) || id === this.activeRowId);
			if(this._removeRowsById(deletedRowIds) > 0)
			{
				this.rows = this._rowsByIndex.filter(Boolean) as Et2DatagridRow[];
				this._finalizeRefreshedRows();
				if(selectionAffected)
				{
					this._emitSelectionChanged();
				}
				this.dispatchEvent(new CustomEvent("et2-rows-deleted", {
					detail: {
						rowIds: deletedRowIds,
						previousRowId: neighbours.previousRowId,
						nextRowId: neighbours.nextRowId
					},
					bubbles: true,
					composed: true
				}));
			}
			return;
		}
		if(type === Et2DatagridUpdateTypes.EDIT)
		{
			// An edit can change the fields a filter checks (eg. a status change matching the
			// active status filter) and can move the row to a new sorted position - both
			// unknown client-side, so always fall back to a full reload rather than a targeted
			// single-row refresh.
			await this.reload();
			return;
		}
		try
		{
			const response = await this.dataProvider.refresh(row_ids, type);
			const changedRows = this._applyRefreshedRows(response, type);
			if(changedRows)
			{
				this._finalizeRefreshedRows();
			}
		}
		catch(e)
		{
			this.egw().debug("error", e.message);
		}
	}

	/**
	 * Normalize refresh ids to the same datastore uid format used internally by rendered rows.
	 */
	private _normalizeRefreshRowIds(rowIds : string[]) : string[]
	{
		return Array.from(new Set((rowIds || []).map((rowId) =>
		{
			return this._dataStoreRowIdFor(rowId, true);
		}).filter(Boolean)));
	}

	/**
	 * Merge provider refresh results into the currently loaded row set.
	 *
	 * Refresh updates replace loaded rows in place. `add` refreshes may also prepend newly
	 * visible rows because Nextmatch semantics place new rows at the top of the grid.
	 *
	 * `update` only ever reaches here when the grid IS sorted by the modified-date field -
	 * `Et2Nextmatch.refresh()` converts it to `update-in-place` or `edit` otherwise - so a
	 * matched row is moved to the top (same as `add`) rather than updated at its old index.
	 *
	 * `edit` never reaches here - `refresh()` always routes it to a full `reload()` instead,
	 * since an edit can change the fields a filter checks and can move the row to a new sorted
	 * position, neither of which is knowable client-side from a targeted single-row refresh.
	 */
	private _applyRefreshedRows(result : Et2DatagridRefreshResult, type : Et2DatagridUpdateType) : boolean
	{
		let changed = false;
		const rowsById = new Map((result?.rows || []).map((row) => [row.id, row] as const));
		const insertedRows : Et2DatagridRow[] = [];
		const pulsedRowIds : string[] = [];
		if(rowsById.size)
		{
			if(type === Et2DatagridUpdateTypes.UPDATE)
			{
				const movedRowIds = new Set(rowsById.keys());
				this._rowsByIndex = this._rowsByIndex.filter((row) => !row || !movedRowIds.has(row.id));
				const topRows = (result.rows || []).map((row) => this.dataProvider?.getRowData ? {id: row.id} : row);
				this._rowsByIndex.unshift(...topRows);
				topRows.forEach((row) =>
				{
					if(!this.displayedRowIds.has(row.id))
					{
						if(this.total !== null)
						{
							this.total += 1;
						}
						if(this.anchorRowIndex >= 0)
						{
							this.anchorRowIndex += 1;
						}
					}
					this.displayedRowIds.add(row.id);
					this._rowRenderVersionById.set(row.id, (this._rowRenderVersionById.get(row.id) || 0) + 1);
				});
				pulsedRowIds.push(...topRows.map((row) => row.id));
				changed = true;
			}
			else
			{
				for(let index = 0; index < this._rowsByIndex.length; index++)
				{
					const currentRow = this._rowsByIndex[index];
					if(!currentRow)
					{
						continue;
					}
					const refreshedRow = rowsById.get(currentRow.id);
					if(!refreshedRow)
					{
						continue;
					}
					// Preserve the row's current visual position; data was refreshed in the provider/datastore.
					this._rowsByIndex[index] = this.dataProvider?.getRowData ? {id: refreshedRow.id} : refreshedRow;
					this.displayedRowIds.add(refreshedRow.id);
					this._rowRenderVersionById.set(refreshedRow.id, (this._rowRenderVersionById.get(refreshedRow.id) || 0) + 1);
					pulsedRowIds.push(refreshedRow.id);
					changed = true;
				}
			}
			if(type === Et2DatagridUpdateTypes.ADD)
			{
				for(const row of result.rows || [])
				{
					if(this.displayedRowIds.has(row.id))
					{
						continue;
					}
					insertedRows.push(this.dataProvider?.getRowData ? {id: row.id} : row);
				}
				if(insertedRows.length)
				{
					this._rowsByIndex.unshift(...insertedRows);
					insertedRows.forEach((row) =>
					{
						this.displayedRowIds.add(row.id);
						this._rowRenderVersionById.set(row.id, (this._rowRenderVersionById.get(row.id) || 0) + 1);
					});
					pulsedRowIds.push(...insertedRows.map((row) => row.id));
					if(this.total !== null)
					{
						this.total += insertedRows.length;
					}
					if(this.anchorRowIndex >= 0)
					{
						this.anchorRowIndex += insertedRows.length;
					}
					changed = true;
				}
			}
		}
		if(this._removeRowsById(result?.removedRowIds || []) > 0)
		{
			changed = true;
		}
		if(changed)
		{
			this.rows = this._rowsByIndex.filter(Boolean) as Et2DatagridRow[];
			this._scheduleRenderedRowPulse(pulsedRowIds);
		}
		return changed;
	}

	/**
	 * Remove loaded rows by datastore uid and keep local row/selection counts consistent.
	 */
	private _removeRowsById(rowIds : string[]) : number
	{
		const ids = new Set((rowIds || []).filter(Boolean));
		if(!ids.size)
		{
			return 0;
		}
		let removedCount = 0;
		for(let index = this._rowsByIndex.length - 1; index >= 0; index--)
		{
			const row = this._rowsByIndex[index];
			if(!row || !ids.has(row.id))
			{
				continue;
			}
			// Remove from all local row/selection indexes before the next render pass.
			this._rowsByIndex.splice(index, 1);
			this.displayedRowIds.delete(row.id);
			this._rowRenderVersionById.delete(row.id);
			this.selectedRowIds.delete(row.id);
			removedCount++;
		}
		if(removedCount > 0 && this.total !== null)
		{
			this.total = Math.max(0, this.total - removedCount);
		}
		return removedCount;
	}

	/**
	 * Find the closest displayed rows before and after the supplied rows.  This
	 * is useful for application-specific selection policies and other operations
	 * that need to preserve a user's position without exposing the grid's row
	 * collection.
	 */
	getNeighbours(rowIds : string[]) : {previousRowId : string | null; nextRowId : string | null}
	{
		const deleted = new Set(rowIds);
		const indexes = this._rowsByIndex.reduce((result, row, index) =>
		{
			if(row && deleted.has(row.id))
			{
				result.push(index);
			}
			return result;
		}, [] as number[]);
		if(!indexes.length)
		{
			return {previousRowId: null, nextRowId: null};
		}

		const rowBefore = (start : number, step : number) : string | null =>
		{
			for(let index = start; index >= 0 && index < this._rowsByIndex.length; index += step)
			{
				const row = this._rowsByIndex[index];
				if(row && !deleted.has(row.id))
				{
					return row.id;
				}
			}
			return null;
		};

		return {
			previousRowId: rowBefore(Math.min(...indexes) - 1, -1),
			nextRowId: rowBefore(Math.max(...indexes) + 1, 1)
		};
	}

	/**
	 * Reconcile selection, active row, and accessibility state after local row updates/removals.
	 */
	private _finalizeRefreshedRows()
	{
		if(this.activeRowId)
		{
			const activeIndex = this._rowsByIndex.findIndex((row) => row?.id === this.activeRowId);
			if(activeIndex >= 0)
			{
				this.activeRowIndex = activeIndex;
			}
			else if(this._rowsByIndex.length)
			{
				// Active row disappeared; clamp to a nearby surviving row.
				this.activeRowIndex = Math.min(Math.max(this.activeRowIndex, 0), this._rowsByIndex.length - 1);
				this.activeRowId = this._rowsByIndex[this.activeRowIndex]?.id ?? null;
			}
			else
			{
				this.activeRowIndex = -1;
				this.activeRowId = null;
			}
		}
		if(this.anchorRowIndex >= this._rowsByIndex.length)
		{
			this.anchorRowIndex = this._rowsByIndex.length ? this._rowsByIndex.length - 1 : -1;
		}
		this._reconcileRowRenderState();
		// Rows whose render version just bumped get a fresh DOM node once the virtualizer's own
		// layout pass mounts it - a separate, later async cycle than Lit's updateComplete (the
		// version is part of the row's stable key, so the virtualizer treats it as a new node).
		// Wait for that pass before syncing selection/accessibility classes, or they land on the
		// outgoing node instead of the one that actually stays.
		void Promise.resolve(this._virtualize?.layoutComplete ?? this.updateComplete)
			.then(() => this._syncRowAccessibilityState());
	}

	selectAllRows()
	{
		if(this.selectionMode !== "multiple")
		{
			return;
		}
		this.allSelected = true;
		this.selectedRowIds = new Set(this.rows.map((row) => row.id));
		this._syncRowAccessibilityState();
		this._emitSelectionChanged();
	}

	/**
	 * Trigger next page load when allowed by current state.
	 */
	loadMore()
	{
		if(!this.dataProvider || this.fetchFailed)
		{
			return;
		}
		if(this.fetching)
		{
			return;
		}
		const start = 0;
		if(this.total !== null && start >= this.total)
		{
			return;
		}
		if(!this._hasMissingRowsInChunk(start))
		{
			return;
		}
		const requestedCount = this.total !== null
		                       ? Math.max(0, Math.min(this.pageSize, this.total - start))
		                       : this.pageSize;
		if(requestedCount <= 0)
		{
			return;
		}
		const requestKey = this._requestKey(start, requestedCount);
		if(this._completedRequestKeys.has(requestKey) || this._inFlightRequestKeys.has(requestKey) || this._queuedRequests.has(requestKey))
		{
			return;
		}
		this._queueRequest(start, requestedCount, requestKey);

		this._scheduleQueuedRequestProcessing();
	}

	/**
	 * Extract template-provided loader content for state rendering.
	 */
	private _loaderHtml() : string
	{
		const loaderTemplate = this.templateData?.loaderTemplate;
		if(!loaderTemplate)
		{
			return "";
		}
		return loaderTemplate.innerHTML || "";
	}

	/**
	 * Extract template-provided no-results content for state rendering.
	 */
	private _noResultsHtml() : string
	{
		const noResultsTemplate = this.templateData?.noResultsTemplate;
		if(!noResultsTemplate)
		{
			return "";
		}
		return noResultsTemplate.innerHTML || "";
	}


	/**
	 * Resolve high-level visual state (loading, error, missing template, empty).
	 */
	private _stateTemplate() : TemplateResult | null
	{
		const hasTemplate = !!this.templateData?.rowTemplate || this.columns.length > 0;
		const hasRows = this.rows.length > 0 || this._pendingPlaceholderCount > 0 || (this.total !== null && this.total > 0);
		const initialLoading = this.configurationLoading || (this.fetching && !hasRows);
		const noTemplate = !this.configurationLoading && !hasTemplate;
		const fetchFailed = this.fetchFailed;
		const noRows = !hasRows && !this.fetching && !fetchFailed && !noTemplate;

		if(initialLoading)
		{
			return html`
                <div class="dg-state dg-state--loading" part="state">
					${!this.configurationLoading && this.templateData?.loaderTemplate
			          ? html`${unsafeHTML(this._loaderHtml())}`
			          : this._et2LoadingTemplate()}
				</div>
			`;
		}
		if(fetchFailed)
		{
			const message = this.fetchErrorMessage || this.egw().lang("Unable to load rows. Please try again.");
			return html`
                <div class="dg-state dg-state--error" part="state">${this._et2ErrorTemplate(message)}</div>`;
		}
		if(noTemplate)
		{
			if(!this._loggedMissingTemplateWarning)
			{
				this._loggedMissingTemplateWarning = true;
				this.egw()?.debug?.("warn", "Et2Datagrid: No row template configured", {
					templateData: !!this.templateData,
					rowTemplate: !!this.templateData?.rowTemplate,
					columnCount: this.columns?.length || 0
				});
			}
			return html`
				<div class="dg-state" part="state">
					<sl-alert variant="primary" open>
						<sl-icon slot="icon" name="layout-text-window-reverse"></sl-icon>
						<strong>${this.egw().lang("No row template configured")}</strong><br/>
                        ${this.egw().lang("Set a template or provide row/columns slots.")}
					</sl-alert>
				</div>
			`;
		}
		if(noRows)
		{
			const emptyStateText = this.emptyStateText || this.egw().lang("No entries to display");
			if(this.templateData?.noResultsTemplate)
			{
				return html`
					<div class="dg-state dg-state--empty" part="state">
						${unsafeHTML(this._noResultsHtml())}
					</div>
				`;
			}
			return html`
				<div class="dg-state dg-state--empty" part="state">
					<slot name="noResults">
						<div class="dg-empty-row" role="row">
							<div class="dg-empty-cell" role="gridcell">
								${emptyStateText}
							</div>
							${this.emptyStateActionMenu ? html`
								<et2-button-icon
										class="dg-empty-action-menu"
										part="state-action-menu"
										image="three-dots-vertical"
										label=${this.egw().lang("Actions")}
										noSubmit
										@click=${this._handleEmptyStateActionMenuClick}
								></et2-button-icon>
							` : nothing}
						</div>
					</slot>
				</div>
			`;
		}
		this._loggedMissingTemplateWarning = false;
		return null;
	}

	private _isTileView() : boolean
	{
		return this.view === "tile";
	}

	private _tileLayoutConfig()
	{
		const layout = this.templateData?.tileLayout || {};
		const defaultWidth = this._lengthToPx(DEFAULT_TILE_LAYOUT.width) || 150;
		const defaultHeight = this._lengthToPx(DEFAULT_TILE_LAYOUT.height) || 120;
		const width = this._lengthToPx(layout.width || DEFAULT_TILE_LAYOUT.width) || defaultWidth;
		const height = this._lengthToPx(layout.height || DEFAULT_TILE_LAYOUT.height) || defaultHeight;
		return grid(<any>{
			itemSize: {
				width: `${width}px`,
				height: `${height}px`
			},
			gap: layout.gap || DEFAULT_TILE_LAYOUT.gap,
			padding: layout.padding || DEFAULT_TILE_LAYOUT.padding,
			flex: {preserve: "height"},
			justify: "start"
		});
	}

	/**
	 * Keep a fixed implicit pitch for normal rows and sparse, explicit branch
	 * heights for expanded items. This avoids FlowLayout's average-based scroll
	 * estimates while retaining normal DOM recycling.
	 */
	private _tableLayoutConfig()
	{
		if(!this._usesFixedVirtualizerRowHeight() || !this._sparseVirtualizerLayoutActive)
		{
			return undefined;
		}
		const rowHeight = Math.max(1, this.rowHeightEstimatePx);
		// @lit-labs/virtualizer does not expose a public type for custom FlowLayout
		// configuration. Keep this cast localized until that integration is typed.
		return <any>{
			type: Et2DatagridSparseFlowLayout,
			_itemSize: {
				width: 100,
				height: rowHeight
			},
			// Lit needs a measurement callback to initialize its viewport and keep
			// ResizeObserver delivery active. The sparse layout ignores Flow's
			// resulting averages; this supplies only stable row measurements.
			_measureChildren: (element : Element) =>
			{
				const rect = element.getBoundingClientRect();
				return {
					width: rect.width,
					height: element.classList.contains("dg-row-expanded") ? rect.height : rowHeight,
					marginTop: 0,
					marginRight: 0,
					marginBottom: 0,
					marginLeft: 0
				};
			},
			expandedItemHeights: this._expandedVirtualItemHeights
		};
	}

	/**
	 * Render the visible column header row (or fallback header slot).
	 */
	protected _headerTemplate(visibleColumns:Et2DatagridColumn[])
	{
		const columnsHeaders = html`
            ${visibleColumns.map((column, visibleIndex) =>
            {
                const columnIndex = this.columns.indexOf(column);
                return html`
                    <div class="dg-col ${visibleIndex === 0 ? "dg-col--lead" : ""}" part="column"
						 role="columnheader" title=${column.title}
                         data-column-key=${column.key}>
                        <div class="dg-col-inner">
					${column.header ?? column.title}
                        </div>
                        ${this._isColumnResizeDisabled() ? nothing : html`
                            <div
                                    class="dg-col-resize-handle"
                                    data-column-index=${String(columnIndex)}
                                    role="separator"
                                    aria-orientation="vertical"
                                    aria-label=${this.egw().lang("Resize column")}
                            ></div>
                        `}
                    </div>
                `
            })}
			${this.noColumnSelection ? nothing : html`
                <div class="dg-colselection" part="column-selection">
                    <et2-button-icon image="list-task" label=${this.egw().lang("select columns")}
                                     @click=${this._handleColumnSelectionClick}
									 noSubmit
					></et2-button-icon>
				</div>
			`}
		`;
		return html`
            <div class="dg-header" part="header" role="rowgroup">
				${visibleColumns.length > 0 ? columnsHeaders : 	html`<slot name="header"></slot>`}
			</div>
		`;
	}

	/**
	 * Normalize visible custom header nodes once after structure changes.
	 * Template parsing can provide non-HTMLElement XML nodes, so this phase also
	 * normalizes them to real HTML elements before datagrid renders headers.
	 */
	private _prepareVisibleHeaders()
	{
		for(const column of this.columns || [])
		{
			const prepared = this._prepareHeaderNode(column.header);
			if(prepared && prepared !== column.header)
			{
				column.header = prepared;
			}
		}
	}

	/**
	 * Ensure headers use the widget creation pipeline when coming from XML nodes.
	 */
	private _prepareHeaderNode(header? : Element) : Element | null
	{
		if(!header)
		{
			return null;
		}
		if(header instanceof HTMLElement)
		{
			return header;
		}
		// Legacy widget snuck in?
		const domNode = (header as any).getDOMNode?.(this);
		return domNode instanceof HTMLElement
		       ? domNode
		       : this.createElementFromNode(header, header.tagName?.toLowerCase()) as unknown as Element;
	}

	/**
	 * A non-visible header for accessibility at the top of the table
	 *
	 * @param {Et2DatagridColumn[]} visibleColumns
	 * @return {TemplateResult<1>}
	 * @private
	 */
	private _accessibleHeaderTemplate(visibleColumns:Et2DatagridColumn[])
	{
		return html`
			<td aria-hidden="true"></td>
			${visibleColumns.map((column) => {
			return html`
				<td>
					<div data-id=${column.key}>
						${column.title}
					</div>
				</td>`
		})}`;
	}

	/**
	 * Render datagrid chrome, state messages, and row table.
	 */
	render()
	{
		const visibleColumns = this._visibleColumns();
		const isTileView = this._isTileView();
		const headerTemplate = this.noVisibleHeader || isTileView ? nothing : this._headerTemplate(visibleColumns);
		const stateTemplate = this._stateTemplate();
		const styles = {
			'--column-count' : visibleColumns.length,
			'--column-sizes': this.inheritColumnSizes ? "inherit" : this._columnWidths(visibleColumns),
			'--scrollbar-space': `${this._scrollbarSpacePx}px`,
			// A measured main-row pitch is only virtualizer state. Publish the value
			// to row templates only when CSS must enforce a fixed-height contract.
			'--row-height': this._usesFixedVirtualizerRowHeight() ? `${this.rowHeightEstimatePx}px` : undefined,
			'--embedded-virtualized-height': this._embeddedVirtualizedHostHeight ?? undefined
		}
		const rowCount = this._printRows?.length ?? this._virtualRowCount();
		const embeddedRowCount = this.embeddedVirtualized && !isTileView && this.total !== null ? Math.max(0, this.total) : rowCount;
		const virtualItems = this._getVirtualItems(embeddedRowCount);
		if(isTileView)
		{
			return html`
	            <div class="dg-root" part="base" style=${styleMap(styles)}>
					<!-- Visible header for users -->
					${headerTemplate}
	                ${this._resizeHelperLeftPx === null || this._resizeHelperWidthPx === null ? nothing : html`
	                    <div class="dg-resize-helper" part="resize-helper" style=${styleMap({
	                        left: `${this._resizeHelperLeftPx}px`,
	                        width: `${this._resizeHelperWidthPx}px`
	                    })}></div>
	                `}

                <div class="dg-body" part="body">
						${stateTemplate}
	                    <div
	                            id="rows"
	                            class="dg-tile-grid"
	                            part="rows"
	                            role="grid"
	                            tabindex="-1"
	                            aria-label=${this.getAttribute("aria-label") || this.getAttribute("label") || "Data grid"}
	                            aria-multiselectable=${String(this.selectionMode === "multiple")}
	                            aria-colcount=${String(1)}
	                            aria-rowcount=${String(this.total ?? this.rows.length)}
	                            ?hidden=${!!stateTemplate}
	                            @keydown=${this._handleTableKeydown}
	                            @pointerdown=${this._handleTablePointerDown}
	                            @click=${this._handleTableClick}
	                    >
	                        ${this._printRows
	                          ? this._printRows.map((_row, index) => this._renderVirtualRow(index, undefined, false))
	                          : virtualize({
		                            items: virtualItems,
		                            keyFunction: this._virtualRowKey,
		                            renderItem: this._renderVirtualRow,
		                            layout: this._tileLayoutConfig()
		                        })}
	                    </div>
					</div>
				</div>
			`;
		}
		const tableLayout = this._tableLayoutConfig();
		const tableVirtualizerConfig : any = {
			items: virtualItems,
			keyFunction: this._virtualRowKey,
			renderItem: this._renderVirtualRow
		};
		if(tableLayout)
		{
			tableVirtualizerConfig.layout = tableLayout;
		}
		return html`
            <div class="dg-root" part="base" style=${styleMap(styles)}>
				<!-- Visible header for users -->
				${headerTemplate}
                ${this._resizeHelperLeftPx === null || this._resizeHelperWidthPx === null ? nothing : html`
                    <div class="dg-resize-helper" part="resize-helper" style=${styleMap({
                        left: `${this._resizeHelperLeftPx}px`,
                        width: `${this._resizeHelperWidthPx}px`
                    })}></div>
                `}

	                <div class="dg-body" part="body">
					${stateTemplate}
					<table
						part="table"
						role="grid"
						tabindex="-1"
						aria-label=${this.getAttribute("aria-label") || this.getAttribute("label") || "Data grid"}
						aria-multiselectable=${String(this.selectionMode === "multiple")}
						aria-colcount=${String((visibleColumns.length || this.columns.length || 1) + 1)}
						aria-rowcount=${String(this.total ?? this.rows.length)}
						?hidden=${!!stateTemplate}
						@keydown=${this._handleTableKeydown}
						@pointerdown=${this._handleTablePointerDown}
						@click=${this._handleTableClick}
					>
						<!-- Accessible / sizing header -->
						<thead>
							${this._accessibleHeaderTemplate(visibleColumns)}
						</thead>
						<tbody id="rows" part="rows" role="rowgroup">
							${this._printRows
								? this._printRows.map((_row, index) => this._renderVirtualRow(index, undefined, false))
								: virtualize(tableVirtualizerConfig)}
						</tbody>
					</table>
				</div>
			</div>
		`;
	}
}
