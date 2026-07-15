import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import shoelace from "../Styles/shoelace";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2Template} from "../Et2Template/Et2Template";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import styles from "./Et2Datagrid.styles";
import {virtualize, virtualizerRef} from "@lit-labs/virtualizer/virtualize.js";
import {grid} from "@lit-labs/virtualizer/layouts/grid.js";
import {
	Et2DatagridColumn,
	Et2DatagridDataProvider,
	Et2DatagridExpansionConfig,
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
import {CUSTOMFIELD_PREFIX} from "../Et2Customfields/Et2CustomfieldsBase";
import {styleMap} from "lit/directives/style-map.js";
import interact from "@interactjs/interactjs";
import type {InteractEvent} from "@interactjs/core/InteractEvent";
import {et2_arrayMgr} from "../et2_core_arrayMgr";

interface Et2DatagridCustomfieldColumnState
{
	customfields : Record<string, any>;
	visibility : Record<string, boolean> | null;
	visibleFieldNames : string[];
}

type Et2DatagridRenderItem =
	| { type : "row"; rowIndex : number }
	| { type : "expanded"; rowIndex : number; parentRowId : string };

const DEFAULT_TILE_LAYOUT = {
	// @lit-labs/virtualizer parses grid itemSize, gap and padding as pixel numbers internally.
	// Keep these defaults in px so spacing does not collapse when passed through the grid layout.
	width: "150px",
	height: "120px",
	gap: "4px",
	padding: "4px"
} as const;
type Et2DatagridVirtualItem = number | Et2DatagridRenderItem;

/**
 * @summary Virtualized data grid for infinite rows with column sizing, selection, and lazy paging.
 *
 * @event et2-loading-start - Fired when one or more row fetch requests are dispatched.
 * @event et2-loading-done - Fired when all in-flight row fetch requests complete successfully.
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
 * @cssproperty [--row-height=3em] - Estimated row height used for spacer rendering.
 * @cssproperty [--row-cell-max-height=10em] - Maximum height for individual row cells before vertical scrolling.
 * @cssproperty [--meta-column-width=0px] - Width of leading metadata column; expandable grids default it wide enough for the expander.
 * @cssproperty [--row-expander-size=20px] - Width and height of the row expand/collapse button.
 * @cssproperty [--row-expander-icon-size=6px] - Size of the default CSS triangle expander icon.
 * @cssproperty [--column-sizes] - Grid-template column track definition used by header/body rows.
 * @cssproperty [--column-count=1] - Column count fallback when explicit track sizes are not set.
 * @cssproperty [--scrollbar-space=0px] - Reserved right-side space in header for body scrollbar alignment.
 * @cssproperty [--column-selection-width=16px] - Width of the header column selection action.
 * @cssproperty [--embedded-virtualized-height] - Synced reserved height for an embedded virtualized grid with no own scrollbar.
 */
@customElement("et2-datagrid")
export class Et2Datagrid extends Et2Widget(LitElement)
{
	private static _browserScrollbarSpacePx : number | null = null;

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
	private _refreshPulseTimersByElement : Map<HTMLElement, number> = new Map();
	private _refreshPulseDurationMs : number = 5000;

	private _virtualIndexes : number[] = [];
	private _virtualIndexesCount : number = -1;
	private _virtualItems : Et2DatagridVirtualItem[] = [];
	private _virtualItemsSignature : string = "";
	private _rowHeightPx : number = 44;
	private _embeddedVirtualizedMeasuredRowHeightPx : number | null = null;
	private _embeddedVirtualizedHostHeight : string | null = null;
	private _embeddedVirtualizedHeightFrame : number | null = null;
	private _embeddedVirtualizedRowsResizeObserver : ResizeObserver | null = null;
	private _rowsMinHeightFrame : number | null = null;

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
	 * Row-data field that contains the application row id.
	 */
	@property({type: String, attribute: "row-id-field"})
	rowIdField : string = "id";

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
	 * Visual layout mode. Row is the default datagrid table layout, tile is a
	 * non-row wrapping virtualized layout where every entry remains its own item.
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
	private _inFlightRequestKeys : Set<string> = new Set();
	private _queuedRequestTimer : number | null = null;
	private _queuedRequests : Map<string, { start : number; requestedCount : number; requestKey : string }> = new Map();
	private _requestDispatchDelayMs : number = 100;
	private _rowUpgradeObserver : MutationObserver | null = null;
	private _rowUpgradeObservedRoot : HTMLElement | null = null;
	private _rowUpgradeQueue : HTMLElement[] = [];
	private _rowUpgradeScheduled : boolean = false;
	private _rowUpgradeFrameHandle : number | null = null;
	private _rowUpgradeScanFrameHandle : number | null = null;
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
		return this._rowsBody[virtualizerRef];
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
		this._scrollListener = () => this._maybePrefetchOnScroll();
	}

	/**
	 * Disconnect DOM listeners and queued async work when component is detached.
	 */
	disconnectedCallback()
	{
		this._teardownColumnResizeInteract();
		this._clearColumnResizeDragState();
		this._rowUpgradeObserver?.disconnect();
		this._rowUpgradeObserver = null;
		this._rowUpgradeObservedRoot = null;
		this._clearRowUpgradeQueue();
		if(this._body && this._scrollListener)
		{
			this._body.removeEventListener("scroll", this._scrollListener);
		}
		this._clearRefreshPulseTimers();
		if(this._embeddedVirtualizedHeightFrame !== null)
		{
			cancelAnimationFrame(this._embeddedVirtualizedHeightFrame);
			this._embeddedVirtualizedHeightFrame = null;
		}
		this._embeddedVirtualizedRowsResizeObserver?.disconnect();
		this._embeddedVirtualizedRowsResizeObserver = null;
		if(this._rowsMinHeightFrame !== null)
		{
			cancelAnimationFrame(this._rowsMinHeightFrame);
			this._rowsMinHeightFrame = null;
		}
		if(this._rowUpgradeScanFrameHandle !== null)
		{
			cancelAnimationFrame(this._rowUpgradeScanFrameHandle);
			this._rowUpgradeScanFrameHandle = null;
		}
		super.disconnectedCallback();
	}

	/**
	 * Finish one-time setup after first paint.
	 */
	firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);
		if(this._body && this._scrollListener)
		{
			this._body.addEventListener("scroll", this._scrollListener, {passive: true});
		}
		this._initRowUpgradeObserver();
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
			changedProperties.has("rowIdField") ||
			changedProperties.has("columnPreferenceName") ||
			changedProperties.has("noColumnPersistence") ||
			changedProperties.has("noVisibleHeader")
		)
		{
			this._loadedColumnPreferenceKey = null;
		}
		this.classList.toggle("dg-has-expanders", !!this.expansionConfig);
		const columnsBeforePreferenceLoad = this.columns;
		if(
			changedProperties.has("templateData") ||
			changedProperties.has("view") ||
			changedProperties.has("rowIdField") ||
			changedProperties.has("columns") ||
			changedProperties.has("columnPreferenceName") ||
			changedProperties.has("noColumnPersistence") ||
			changedProperties.has("noVisibleHeader")
		)
		{
			this._loadColumnPreferencesIfNeeded();
		}
		const columnsChanged = changedProperties.has("columns") || this.columns !== columnsBeforePreferenceLoad;
		const structureChanged = changedProperties.has("templateData") ||
			changedProperties.has("view") ||
			changedProperties.has("rowIdField") ||
			columnsChanged;
		if(changedProperties.has("templateData"))
		{
			// Capture source cell->column mapping before user reorders columns.
			this._sourceColumnKeys = (this.templateData?.sourceColumns || this.templateData?.columns || this.columns || []).map((column) => String(column.key));
		}
		if(structureChanged)
		{
			this._clearRowUpgradeQueue();
			this._virtualIndexesCount = -1;
			this._virtualItemsSignature = "";
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
	 * Re-render physical row DOM when structure-defining inputs change.
	 * We rebuild rows here because template/column changes alter generated markup.
	 */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

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
		this._initRowUpgradeObserver();
		this._upgradeRenderedRows();
		this._scheduleRenderedRowsUpgradeScan();
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
		const renderedRowsHeight = this._embeddedVirtualizedRenderedRowsHeight();
		const height = Math.max(virtualizerHeight || 0, renderedRowsHeight || 0);
		const value = height > 0 ? `${Math.ceil(height)}px` : "";
		if(rowsBody.style.minHeight !== value)
		{
			rowsBody.style.minHeight = value;
		}
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
			this._embeddedVirtualizedRowsResizeObserver?.disconnect();
			return;
		}
		this._observeEmbeddedVirtualizedRows();
		this._embeddedVirtualizedMeasuredRowHeightPx = this._measureEmbeddedVirtualizedRowHeight();
		const height = this._embeddedVirtualizedContentHeight() ?? this._embeddedVirtualizedLoadingHeight();
		this._scheduleEmbeddedVirtualizedHeightSync();
		if(!height || this._embeddedVirtualizedHostHeight === height)
		{
			return;
		}
		this._applyEmbeddedVirtualizedHostHeight(height);
	}

	/**
	 * Watch realized child rows so late widget upgrades/content changes can grow
	 * the embedded grid host and the parent expanded row on the first expansion.
	 */
	private _observeEmbeddedVirtualizedRows()
	{
		if(!this.embeddedVirtualized)
		{
			return;
		}
		if(!this._embeddedVirtualizedRowsResizeObserver)
		{
			this._embeddedVirtualizedRowsResizeObserver = new ResizeObserver(() =>
			{
				this._scheduleEmbeddedVirtualizedHeightSync();
			});
		}
		this._embeddedVirtualizedRowsResizeObserver.disconnect();
		for(const row of this._embeddedVirtualizedRenderedRows())
		{
			this._embeddedVirtualizedRowsResizeObserver.observe(row);
		}
	}

	/**
	 * The virtualizer may write tbody height after Lit's `updated()` callback.
	 * Re-check on the next animation frame so the host and exposed CSS variable
	 * follow the final row layout instead of an early estimate.
	 */
	private _scheduleEmbeddedVirtualizedHeightSync()
	{
		if(this._embeddedVirtualizedHeightFrame !== null)
		{
			return;
		}
		this._embeddedVirtualizedHeightFrame = requestAnimationFrame(() =>
		{
			this._embeddedVirtualizedHeightFrame = null;
			if(!this.embeddedVirtualized)
			{
				return;
			}
			const height = this._embeddedVirtualizedContentHeight();
			if(!height || this._embeddedVirtualizedHostHeight === height)
			{
				return;
			}
			this._applyEmbeddedVirtualizedHostHeight(height);
		});
	}

	/**
	 * Apply the embedded host height and ask the parent virtualizer to remeasure
	 * the expanded row that contains this child grid.
	 */
	private _applyEmbeddedVirtualizedHostHeight(height : string)
	{
		this.style.height = height;
		this._embeddedVirtualizedHostHeight = height;
		this._notifyParentVirtualizerOfEmbeddedHeightChange();
		this.requestUpdate();
	}

	/**
	 * Parent datagrids usually learn child height changes via ResizeObserver, but
	 * first expansion can happen before observers have measured the expanded row.
	 */
	private _notifyParentVirtualizerOfEmbeddedHeightChange()
	{
		const root = this.getRootNode();
		const parentGrid = root instanceof ShadowRoot && root.host instanceof Et2Datagrid
		                   ? root.host
		                   : null;
		const expandedRow = this.closest("tr[data-dg-expanded-row]") as HTMLElement | null;
		const virtualizer = parentGrid?._virtualize as any;
		if(!expandedRow || !virtualizer || typeof virtualizer._childrenSizeChanged !== "function")
		{
			return;
		}
		virtualizer._childrenSizeChanged([{
			target: expandedRow,
			contentRect: expandedRow.getBoundingClientRect()
		}]);
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
		const virtualizerHeight = this._embeddedVirtualizedVirtualizerHeight();
		const renderedRowsHeight = this._embeddedVirtualizedRenderedRowsHeight();
		const height = Math.max(
			virtualizerHeight ? parseFloat(virtualizerHeight) : 0,
			renderedRowsHeight || 0
		);
		if(!height)
		{
			return null;
		}
		const value = `${Math.ceil(height)}px`;
		if(rowsBody && renderedRowsHeight && renderedRowsHeight > (virtualizerHeight ? parseFloat(virtualizerHeight) : 0))
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
		return this._renderedDataRowElements(rowsBody);
	}

	/**
	 * Measure realized child rows so the loading fallback can reuse the actual row height.
	 */
	private _measureEmbeddedVirtualizedRowHeight() : number | null
	{
		const renderedRows = this._embeddedVirtualizedRenderedRows();
		const heights = renderedRows
			.map((row) => row.getBoundingClientRect().height)
			.filter((height) => Number.isFinite(height) && height > 0);
		if(!heights.length)
		{
			return null;
		}
		return Math.ceil(heights.reduce((sum, height) => sum + height, 0) / heights.length);
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
			return;
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
		if(!entries.length)
		{
			return;
		}
		const orderByKey = new Map<string, number>();
		const byKey = new Map<string, { width? : string; hidden? : boolean; customFields? : string[] }>();
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
				return column;
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
		// Persist in legacy format, some apps look for this.
		// Keep this compatibility write before the structured preference so the
		// new datagrid preference remains the primary saved state.
		this.egw()?.set_preference?.(app, 'nextmatch-' + this._columnPreferenceTemplateId(), (this.columns || [])
			.filter(c => !c.hidden)
			.map((column) =>
			{
				const header = column.header as any;
				if(typeof header?.getCustomfieldVisibility !== "function")
				{
					return column.key;
				}
				const visibility = header.getCustomfieldVisibility();
				if(!visibility || typeof visibility !== "object")
				{
					return column.key;
				}
				return column.key + ',' + Object.keys(visibility).filter((name) => visibility[name] === true).map(k => CUSTOMFIELD_PREFIX + k).join(",");
			})
			.join(',')
		);
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
	 * Request one page from provider and merge rows preserving uniqueness.
	 */
	private async _fetchPage(start : number, requestedCount : number = 0, requestKey : string = "")
	{
		if(!this.dataProvider)
		{
			if(requestKey)
			{
				this._inFlightRequestKeys.delete(requestKey);
			}
			this._syncLoadingFromInFlight();
			return;
		}

		try
		{
			const response = await this.dataProvider.fetchPage(start, requestedCount || this.pageSize);
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
				this._rowsByIndex[index] = row;
			}
			this.rows = this._rowsByIndex.filter(Boolean) as Et2DatagridRow[];
		}
		catch(e)
		{
			this.fetchFailed = true;
			this._hasFetchedOnce = true;
			// Store message so state template can surface meaningful diagnostics.
			this.fetchErrorMessage = e?.message || "";
		}
		finally
		{
			if(requestedCount > 0)
			{
				this._pendingPlaceholderCount = Math.max(0, this._pendingPlaceholderCount - requestedCount);
			}
			if(requestKey)
			{
				this._inFlightRequestKeys.delete(requestKey);
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
		this._pendingPlaceholderCount = 0;
		this._clearQueuedRequests();
		this._clearRowUpgradeQueue();
	}

	/**
	 * Drop queued (not yet dispatched) requests and clear any scheduled dispatch timer.
	 */
	private _clearQueuedRequests()
	{
		this._queuedRequests.clear();
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
			const rowId = this._rowExpansionId(row, rowIndex);
			if(nextExpandedRowIds.has(rowId) && !this._isRowExpandable(row, rowIndex))
			{
				nextExpandedRowIds.delete(rowId);
			}
		}
		if(nextExpandedRowIds.size === expandedRowIds.size)
		{
			return;
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

		// Simple row fallback
		if(!template && !templateXml)
		{
			const tr = document.createElement("tr");
			tr.setAttribute("part", `${tr.getAttribute("part") || ""} row`.trim());
			tr.innerHTML = this.columns
				.filter((column) => !this._isColumnHidden(column))
				.map((column) => `<td>${String(this._getFieldValue(row.data, column.key) ?? "")}</td>`)
				.join("");
			this._ensureMetaCell(tr, row, rowIndex);
			this._markRowElement(tr, row, rowIndex);
			this._applyColumnLayoutToRowElement(tr);
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
		this._populateCloneWithRow(fragment, row.data);
		const root = (fragment.firstElementChild || null) as HTMLElement | null;
		if(!root)
		{
			return null;
		}
		this._populateRowRootAttributes(root, row.data);
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
			rowData: row.data,
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
	private _rowExpansionId(row : Et2DatagridRow, rowIndex : number = -1) : string
	{
		const rawId = row?.id;
		if(rawId !== undefined && rawId !== null && String(rawId) !== "")
		{
			return this._dataStoreRowIdFor(rawId);
		}
		return "";
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
			return !!this.expansionConfig.isExpandable(row, rowIndex);
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
	private _isRowExpanded(row : Et2DatagridRow, rowIndex : number = -1) : boolean
	{
		return this._expandedRowIds().has(this._rowExpansionId(row, rowIndex));
	}

	/**
	 * Update expansion state through the controlled callback or local fallback state.
	 */
	private _setRowExpanded(row : Et2DatagridRow, expanded : boolean, rowIndex : number = -1)
	{
		const rowId = this._rowExpansionId(row, rowIndex);
		if(!rowId)
		{
			return;
		}
		const nextExpandedRowIds = new Set(this._expandedRowIds());
		if(expanded)
		{
			nextExpandedRowIds.add(rowId);
		}
		else
		{
			nextExpandedRowIds.delete(rowId);
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
		const expanded = this._isRowExpanded(row, rowIndex);
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
		const observedRoot = this._body;
		if(observedRoot === this._rowUpgradeObservedRoot && this._rowUpgradeObserver)
		{
			return;
		}
		this._rowUpgradeObserver?.disconnect();
		this._rowUpgradeObservedRoot = observedRoot;
		if(!observedRoot)
		{
			return;
		}
		this._rowUpgradeObserver = new MutationObserver(() =>
		{
			this._upgradeRenderedRows();
			this._guardFocusAfterVirtualMutation();
		});
		this._rowUpgradeObserver.observe(observedRoot, {childList: true, subtree: true});
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
	private _renderVirtualRow = (item : Et2DatagridRenderItem | number) : TemplateResult =>
	{
		if(typeof item === "number")
		{
			item = {type: "row", rowIndex: item};
		}
		if(item.type === "expanded")
		{
			return this._renderExpandedRow(item);
		}
		const rowIndex = item.rowIndex;
		const row = this._rowsByIndex[rowIndex];
		if(row)
		{
			const rowElement = this._buildRowElement(row, rowIndex);
			return html`${unsafeHTML(rowElement?.outerHTML || "")}`;
		}
		const chunkStart = Math.floor(rowIndex / this.pageSize) * this.pageSize;
		this._requestChunkForRowIndex(rowIndex);
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
	private _renderExpandedRow(item : Extract<Et2DatagridRenderItem, { type : "expanded" }>) : TemplateResult
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
			row,
			rowIndex: item.rowIndex,
			parentGrid: this,
			columnSizes,
			metaColumnWidth
		});
		return html`
            <tr
                    class="dg-row-expanded"
                    data-dg-expanded-row="1"
                    data-parent-row-id=${item.parentRowId}
                    role="row"
                    aria-selected="false"
                    tabindex="-1"
            >
                <td class="dg-expanded-cell" part="expanded-row" role="gridcell">
                    <div class="dg-expanded-content" part="expanded-row-content">
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
		if(this._inFlightRequestKeys.has(requestKey) || this._queuedRequests.has(requestKey))
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
			if(row && this._isRowExpanded(row, rowIndex) && this._isRowExpandable(row, rowIndex))
			{
				items.push({
					type: "expanded",
					rowIndex,
					parentRowId: this._rowExpansionId(row, rowIndex)
				});
			}
		}
		this._virtualItems = items;
		this._virtualItemsSignature = signature;
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
		const materializedCount = Math.max(this._rowsByIndex.length + this._pendingPlaceholderCount, this.rows.length);
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
			this._rowHeightPx || this._resolveTemplateRowHeightPx() || 44,
			this._embeddedVirtualizedMeasuredRowHeightPx || 0
		);
		return `${rowHeight}px`;
	}

	/**
	 * Provide stable keys for realized rows, expanded rows, and deterministic placeholders.
	 */
	private _virtualRowKey = (item : Et2DatagridVirtualItem) : string =>
	{
		const structureSignature = this._rowRenderStructureSignature();
		if(typeof item === "number")
		{
			const row = this._rowsByIndex[item];
			if(row)
			{
				const rowId = String(row.id ?? item);
				const version = this._rowRenderVersionById.get(rowId) || 0;
				const expandedState = this._expandedRowIds().size ? `:${this._isRowExpanded(row, item) ? "expanded" : "collapsed"}` : "";
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
			const expandedState = this._isRowExpanded(row, rowIndex) ? "expanded" : "collapsed";
			return `${structureSignature}:${this._dataStoreRowIdFor(rowId)}:${version}:${expandedState}`;
		}
		const querySignature = this.dataProvider?.getQuerySignature?.() || "";
		return `${structureSignature}:placeholder:${querySignature}:${rowIndex}`;
	};

	private _rowRenderStructureSignature() : string
	{
		return [
			this._isTileView() ? "tile" : "row",
			this.templateData?.rowTemplateId || "",
			this.templateData?.templateSignature || "",
			this.templateData?.view || "",
			this.rowIdField || "id"
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
		const rowElements = this._renderedDataRowElements(this._rowsBody);
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
			const row = this._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			if(rowElement.getAttribute("data-et2dg-upgrade-queued") === "1")
			{
				continue;
			}
			rowElement.setAttribute("data-et2dg-upgrade-queued", "1");
			this._rowUpgradeQueue.push(rowElement);
		}
		this._scheduleRowUpgradeQueue();
	}

	/**
	 * The virtualizer can materialize children after Lit's `updated()` callback.
	 * Scan for a bounded number of frames so rows inserted during that handoff
	 * still get their row-scoped template attributes applied.
	 */
	private _scheduleRenderedRowsUpgradeScan(remainingFrames : number = 30)
	{
		if(this._rowUpgradeScanFrameHandle !== null)
		{
			return;
		}
		this._rowUpgradeScanFrameHandle = requestAnimationFrame(() =>
		{
			this._rowUpgradeScanFrameHandle = null;
			this._upgradeRenderedRows();
			if(remainingFrames > 1)
			{
				this._scheduleRenderedRowsUpgradeScan(remainingFrames - 1);
			}
		});
	}

	/**
	 * Return direct rendered data rows/items from the virtualizer host.
	 *
	 * `children` is used instead of a `:scope > ...` selector because tbody
	 * selector behaviour can vary while the virtualizer is actively moving rows.
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
	 * Cancel queued/in-flight frame work for row upgrades.
	 */
	private _clearRowUpgradeQueue()
	{
		this._rowUpgradeQueue.length = 0;
		this._rowsBody?.querySelectorAll("[data-et2dg-upgrade-queued='1']").forEach((rowElement) =>
		{
			(rowElement as HTMLElement).removeAttribute("data-et2dg-upgrade-queued");
		});
		this._rowUpgradeScheduled = false;
		if(this._rowUpgradeFrameHandle !== null)
		{
			cancelAnimationFrame(this._rowUpgradeFrameHandle);
			this._rowUpgradeFrameHandle = null;
		}
		if(this._rowUpgradeScanFrameHandle !== null)
		{
			cancelAnimationFrame(this._rowUpgradeScanFrameHandle);
			this._rowUpgradeScanFrameHandle = null;
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
			const row = this._rowsByIndex[rowIndex];
			if(!row)
			{
				continue;
			}
			rowElement.classList.add("loading");
			if(this._applyRowElementAttributes(rowElement, row.data, rowIndex))
			{
				rowElement.setAttribute("data-et2dg-upgraded-for", upgradeSignature);
			}
			processed++;
		}
		if(this._rowUpgradeQueue.length)
		{
			this._scheduleRowUpgradeQueue();
		}
		else if(this.embeddedVirtualized)
		{
			// Row upgrades can change child-grid row height after the normal Lit update.
			this._scheduleEmbeddedVirtualizedHeightSync();
		}
		else if(this.total == this.rows.length)
		{
			// Updates are done.  If we have all the rows, make sure the height is exactly right to avoid cutting
			// off the last rows if some are taller than expected.  Don't do this if we don't have all rows.
			this._scheduleRowsMinHeightSync();
		}
	}

	/**
	 * Normalize arbitrary row identifiers for `data-row-id` usage.
	 */
	private _dataStoreRowIdFor(rowId : string | number, ensurePrefix : boolean = false) : string
	{
		return this.dataProvider.normalizeRowId(rowId, ensurePrefix);
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

		const mgrRowData = {};
		mgrRowData[rowIndex] = rowData;
		const contentMgr = this.getArrayMgr("content") || new et2_arrayMgr(mgrRowData);
		const mgr = contentMgr.openPerspective(this as any, mgrRowData, rowIndex);
		const mgrs : any = this.getArrayMgrs?.() || {};
		mgrs.content = mgr;
		try
		{
			// Apply stored template attributes through each widget's transform hook
			// so row-scoped values ($row.*) are expanded with the current row manager.
			for(const element of toUpgrade)
			{
				try
				{
					const id = element.getAttribute?.("data-et2nm-id");
					const stored = id ? attrMap[id] : null;
					const isCustomfieldsRow = element.localName === "et2-customfields-list";
					if(isCustomfieldsRow)
					{
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
					if(typeof element.transformAttributes === "function")
					{
						if(!stored || !Object.keys(stored).length)
						{
							continue;
						}
						else
						{
							element.transformAttributes(stored);
						}
					}
					else
					{
						Object.entries(stored).forEach(([attr, value]) =>
						{
							element.setAttribute(attr, mgr.expandName(value));
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
		this._rerunRowCustomizer(rowRoot, rowData, rowIndex);
		rowRoot.classList.remove("loading");
		return true;
	}

	private _rerunRowCustomizer(rowElement : HTMLElement, rowData : any, rowIndex : number)
	{
		if(!this.rowCustomizer)
		{
			return;
		}
		const metaSelector = this._isTileView() ? ":scope > [data-dg-meta-cell='1']" : ":scope > td[data-dg-meta-cell='1']";
		const metaCell = rowElement.querySelector(metaSelector) as HTMLTableCellElement | null;
		if(!metaCell)
		{
			return;
		}
		this.rowCustomizer({
			rowElement,
			rowData,
			rowIndex,
			metaCell
		});
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
		const fieldNames = columnState?.visibleFieldNames || (
			visibility && Object.keys(visibility).length
				? Object.keys(visibility).filter((name) => visibility[name] === true)
				: Object.keys(customfields || {})
		);
		element.value = this._customfieldValuesFromRowData(
			rowData,
			fieldNames
		);
	}

	/**
	 * Extract the row value object expected by a customfields-list renderer.
	 */
	private _customfieldValuesFromRowData(
		rowData : any,
		fieldNames : string[]
	) : Record<string, any>
	{
		const value : Record<string, any> = {};
		for(const fieldName of fieldNames)
		{
			const prefixedKey = CUSTOMFIELD_PREFIX + fieldName;
			value[prefixedKey] = rowData && Object.prototype.hasOwnProperty.call(rowData, prefixedKey)
				? rowData[prefixedKey]
				: "";
		}
		return value;
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
		const rowIdField = String(this.rowIdField || "id").trim() || "id";
		if(row && row[rowIdField] !== undefined && row[rowIdField] !== null && String(row[rowIdField]) !== "")
		{
			return this._dataStoreRowIdFor(row[rowIdField], true);
		}
		return "";
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
		return this._columnManager.columnLengthToPx(raw, totalVisibleWidthPx, availableRelativeWidthPx, relativeWidthUnits);
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
		const rows = Array.from(this._rowsBody?.querySelectorAll(":scope > *") || []) as HTMLElement[];
		if(!rows.length || !this.columns?.length)
		{
			return;
		}
		for(const row of rows)
		{
			this._applyColumnLayoutToRowElement(row);
			const rowIndex = parseInt(row.getAttribute("data-row-index") || "-1", 10);
			const rowData = rowIndex >= 0 ? this._rowsByIndex[rowIndex]?.data : null;
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
		this._setRowExpanded(row, !this._isRowExpanded(row, rowIndex), rowIndex);
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
			if(!this._isRowExpanded(row, this.activeRowIndex))
			{
				this._setRowExpanded(row, true, this.activeRowIndex);
				return true;
			}
			this.dispatchEvent(new CustomEvent("et2-datagrid-enter-expanded-row", {
				detail: {
					parentRowId: this._rowExpansionId(row, this.activeRowIndex),
					rowId: row.id,
					rowIndex: this.activeRowIndex
				},
				bubbles: true,
				composed: true
			}));
			return true;
		}
		if(event.key === "ArrowLeft" && this._isRowExpanded(row, this.activeRowIndex))
		{
			event.preventDefault();
			event.stopPropagation();
			this._setRowExpanded(row, false, this.activeRowIndex);
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
				const rowHeight = this._rowHeightPx || 44;
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
		const selectedRows = this.rows.filter((row) => this.selectedRowIds.has(row.id)).map((row) => row.data);
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
			composed: true
		}));
	}

	/**
	 * Seed datagrid with preloaded rows and skip initial fetch.
	 */
	setInitialRows(rows : any[])
	{
		const mappedRows = (rows || []).map((row, index) => ({
			id: this._rowIdFor(row, index),
			data: row
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
	 * Apply a targeted row refresh without forcing a full grid reload.
	 *
	 * The provider decides which rows changed or disappeared; the datagrid only updates
	 * rows it already has materialized locally.
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
			if(this._removeRowsById(this._normalizeRefreshRowIds(row_ids)) > 0)
			{
				this._finalizeRefreshedRows();
			}
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
	 */
	private _applyRefreshedRows(result : Et2DatagridRefreshResult, type : Et2DatagridUpdateType) : boolean
	{
		let changed = false;
		const rowsById = new Map((result?.rows || []).map((row) => [row.id, row] as const));
		const insertedRows : Et2DatagridRow[] = [];
		const pulsedRowIds : string[] = [];
		if(rowsById.size)
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
				// Preserve the row's current visual position and swap only its data payload.
				this._rowsByIndex[index] = refreshedRow;
				this.displayedRowIds.add(refreshedRow.id);
				this._rowRenderVersionById.set(refreshedRow.id, (this._rowRenderVersionById.get(refreshedRow.id) || 0) + 1);
				pulsedRowIds.push(refreshedRow.id);
				changed = true;
			}
			if(type === Et2DatagridUpdateTypes.ADD)
			{
				for(const row of result.rows || [])
				{
					if(this.displayedRowIds.has(row.id))
					{
						continue;
					}
					insertedRows.push(row);
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
		this._syncRowAccessibilityState();
		this._reconcileRowRenderState();
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
		if(this._inFlightRequestKeys.has(requestKey) || this._queuedRequests.has(requestKey))
		{
			return;
		}
		this._queueRequest(start, requestedCount, requestKey);

		this._scheduleQueuedRequestProcessing();
	}

	/**
	 * Extract slot-provided loader template HTML for state rendering fallback.
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
					${this.templateData?.loaderTemplate
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
			return html`
				<div class="dg-state" part="state">
					<slot name="noResults">
						<sl-alert variant="neutral" open>
							<sl-icon slot="icon" name="inbox"></sl-icon>
							<strong>${emptyStateText}</strong><br/>
							${this._hasFetchedOnce ? this.egw().lang("No rows were returned.") : this.egw().lang("Waiting for rows.")}
						</sl-alert>
					</slot>
				</div>
			`;
		}
		this._loggedMissingTemplateWarning = false;
		return null;
	}

	private _isTileView() : boolean
	{
		return this.view === "tile" || this.templateData?.view === "tile";
	}

	private _tileLayoutConfig()
	{
		const layout = this.templateData?.tileLayout || {};
		const defaultWidth = this._lengthToPx(DEFAULT_TILE_LAYOUT.width) || 0;
		const defaultHeight = this._lengthToPx(DEFAULT_TILE_LAYOUT.height) || 0;
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
			'--embedded-virtualized-height': this._embeddedVirtualizedHostHeight ?? undefined
		}
		const rowCount = this._virtualRowCount();
		const virtualItems = this._getVirtualItems(rowCount);
		const virtualizerConfig = {
			items: virtualItems,
			keyFunction: this._virtualRowKey,
			renderItem: this._renderVirtualRow,
			...(isTileView ? {layout: this._tileLayoutConfig()} : {})
		};
		const gridAttributes = {
			"aria-label": this.getAttribute("aria-label") || this.getAttribute("label") || "Data grid",
			"aria-multiselectable": String(this.selectionMode === "multiple"),
			"aria-colcount": String((isTileView ? 1 : visibleColumns.length || this.columns.length || 1) + (isTileView ? 0 : 1)),
			"aria-rowcount": String(this.total ?? this.rows.length)
		};
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
                    ${isTileView ? html`
                        <div
                                id="rows"
                                class="dg-tile-grid"
                                part="rows"
                                role="grid"
                                tabindex="-1"
                                aria-label=${gridAttributes["aria-label"]}
                                aria-multiselectable=${gridAttributes["aria-multiselectable"]}
                                aria-colcount=${gridAttributes["aria-colcount"]}
                                aria-rowcount=${gridAttributes["aria-rowcount"]}
                                ?hidden=${!!stateTemplate}
                                @keydown=${this._handleTableKeydown}
                                @pointerdown=${this._handleTablePointerDown}
                                @click=${this._handleTableClick}
                        >
                            ${virtualize(virtualizerConfig)}
                        </div>
                    ` : html`
					<table
                            part="table"
						role="grid"
						tabindex="-1"
                            aria-label=${gridAttributes["aria-label"]}
                            aria-multiselectable=${gridAttributes["aria-multiselectable"]}
                            aria-colcount=${gridAttributes["aria-colcount"]}
                            aria-rowcount=${gridAttributes["aria-rowcount"]}
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
                        ${virtualize(virtualizerConfig)}
                        </tbody>
					</table>
                    `}
				</div>
			</div>
		`;
	}
}
