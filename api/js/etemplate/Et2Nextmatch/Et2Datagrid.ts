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
import {
	Et2DatagridColumn,
	Et2DatagridDataProvider,
	Et2DatagridRow,
	Et2DatagridSelectionDetail,
	Et2DatagridSelectionMode,
	Et2DatagridTemplateData
} from "./Et2Datagrid.types";
import {styleMap} from "lit/directives/style-map.js";

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

	private _virtualIndexes : number[] = [];
	private _virtualIndexesCount : number = -1;

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
	 * Prepared template and metadata used to render each row.
	 */
	@property({attribute: false})
	templateData : Et2DatagridTemplateData | null = null;

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
	 * External loading flag for configuration/template setup before first data render.
	 */
	@property({type: Boolean, attribute: "configuration-loading"})
	configurationLoading : boolean = false;

	/** Set of row ids already added, used to avoid duplicate render on incremental fetches. */
	private displayedRowIds : Set<string> = new Set();
	/** Set of selected row ids used to derive emitted selection payloads. */
	private selectedRowIds : Set<string> = new Set();
	/** Anchor index for shift-range selection semantics. */
	private anchorRowIndex : number = -1;
	/** Keyboard/pointer active row index in currently loaded rows. */
	private activeRowIndex : number = -1;
	/** Active row id mirrored from `activeRowIndex` for event payload convenience. */
	private activeRowId : string | null = null;
	private _scrollListener : (() => void) | null = null;
	private _inFlightRequestKeys : Set<string> = new Set();
	private _queuedRequestTimer : number | null = null;
	private _queuedRequests : Map<string, { start : number; requestedCount : number; requestKey : string }> = new Map();
	private _requestDispatchDelayMs : number = 100;
	private _rowUpgradeObserver : MutationObserver | null = null;
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
		this._onTableClick = this._onTableClick.bind(this);
		this._onTablePointerDown = this._onTablePointerDown.bind(this);
		this._onTableKeydown = this._onTableKeydown.bind(this);
		this._scrollListener = () => this._maybePrefetchOnScroll();
	}

	/**
	 * Disconnect DOM listeners and queued async work when component is detached.
	 */
	disconnectedCallback()
	{
		this._rowUpgradeObserver?.disconnect();
		this._rowUpgradeObserver = null;
		this._clearRowUpgradeQueue();
		if(this._body && this._scrollListener)
		{
			this._body.removeEventListener("scroll", this._scrollListener);
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
	}

	/**
	 * Re-render physical row DOM when structure-defining inputs change.
	 * We rebuild rows here because template/column changes alter generated markup.
	 */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has("templateData"))
		{
			// Capture source cell->column mapping before user reorders columns.
			this._sourceColumnKeys = (this.templateData?.columns || this.columns || []).map((column) => String(column.key));
			this._renderRowsIntoContainer(true);
			this._ensureTableColSizes();
		}
		if(changedProperties.has("columns"))
		{
			this._renderRowsIntoContainer(true);
			this._ensureTableColSizes();
			this._applyColumnVisibilityToRenderedRows();
		}
		this._upgradeRenderedRows();
		if(this._restoreFocusAfterRender && this.activeRowIndex >= 0)
		{
			this._focusRowByIndex(this.activeRowIndex, 10);
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
		this._pendingPlaceholderCount += requestedCount;
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
			this.requestUpdate();
		}
		}

	/**
	 * Clear rendered rows and related in-memory row id tracking.
	 */
	private _clearRows()
	{
		this.rows = [];
		this._rowsByIndex = [];
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
		this._renderRowsIntoContainer();
	}

	/**
	 * Return preferred chunk starts around viewport center: current, previous, next.
	 */
	private _preferredChunkStarts() : number[]
	{
		return [0];
		// Predictive chunk loading
		/*
		const starts : number[] = [];
		const current = this._currentViewportChunkStart();
		starts.push(current, Math.max(0, current - this.pageSize));
		if(this.total !== null)
		{
			const maxStart = Math.max(0, this.total - this.pageSize);
			starts.push(Math.min(maxStart, current + this.pageSize));
		}
		else
		{
			starts.push(current + this.pageSize);
		}
		return Array.from(new Set(starts));

		 */
	}

	/**
	 * Map current viewport center position to aligned chunk start.
	 */
	private _currentViewportChunkStart() : number
	{
		const body = this._body;
		const center = body ? Math.max(0, Math.floor((body.scrollTop + (body.clientHeight / 2)) / rowHeightPx)) : 0;
		if(this.total === null)
		{
			return Math.max(0, Math.floor(center / this.pageSize) * this.pageSize);
		}
		const maxStart = Math.max(0, this.total - this.pageSize);
		return Math.max(0, Math.min(maxStart, Math.floor(center / this.pageSize) * this.pageSize));
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
	 * Reconcile logical rows to physical DOM rows.
	 * We manually patch tbody for predictable performance with large/incremental loads.
	 */
	private _renderRowsIntoContainer(force : boolean = false)
	{
		if(this.activeRowIndex < 0 && this.rows.length)
		{
			// Keep keyboard navigation usable as soon as first row appears.
			this.activeRowIndex = 0;
			this.activeRowId = this.rows[0].id;
			this.anchorRowIndex = 0;
		}
		this.requestUpdate();
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
		if(this._queuedRequests.size)
		{
			this._scheduleQueuedRequestProcessing();
		}
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
			tr.innerHTML = this.columns
				.filter((column) => !this._isColumnHidden(column))
				.map((column) => `<td>${String(this._getFieldValue(row.data, column.key) ?? "")}</td>`)
				.join("");
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
		root.classList.add("loading");
		this._markRowElement(root, row, rowIndex);
		this._applyColumnLayoutToRowElement(root);
		return root;
	}

	/**
	 * Observe row DOM churn to upgrade widgets and recover row focus after virtualization swaps.
	 */
	private _initRowUpgradeObserver()
	{
		this._rowUpgradeObserver?.disconnect();
		const rowsBody = this._rowsBody;
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
		const activeIsRow = !!shadowActive?.matches?.("tr[data-row-index]");
		if(activeIsRow)
		{
			return;
		}
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
	 * Render one virtual row by absolute index, using placeholder+fetch when data is missing.
	 */
	private _renderVirtualRow = (rowIndex : number) : TemplateResult =>
	{
		const row = this._rowsByIndex[rowIndex];
		if(row)
		{
			const rowElement = this._buildRowElement(row, rowIndex);
			return html`${unsafeHTML(rowElement?.outerHTML || "")}`;
		}
		const chunkStart = Math.floor(rowIndex / this.pageSize) * this.pageSize;
		this._requestChunkForRowIndex(rowIndex);
		const placeholderRowId = `placeholder:${rowIndex}`;
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
                <td class="dg-placeholder-cell">
                    ${this.templateData?.loaderTemplate ? html`${unsafeHTML(this._loaderHtml())}` : html`
                        <sl-skeleton effect="sheen" style="width:100%"></sl-skeleton>`}
                </td>
            </tr>
		`;
	};

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
	 * Maintain a stable [0..rowCount) index array for virtualize() without reallocating each render.
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
	 * Provide stable keys for realized rows and deterministic keys for placeholders.
	 */
	private _virtualRowKey = (rowIndex : number) : string =>
	{
		const row = this._rowsByIndex[rowIndex];
		if(row)
		{
			return this._dataStoreRowIdFor(row.id ?? rowIndex);
		}
		const querySignature = this.dataProvider?.getQuerySignature?.() || "";
		return `placeholder:${querySignature}:${rowIndex}`;
	};

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
		rowElement.tabIndex = rowIndex === this.activeRowIndex ? 0 : -1;
	}

	private _upgradeRenderedRows()
	{
		const rowElements = Array.from(this._rowsBody?.querySelectorAll("tr[data-row-id]") || []) as HTMLElement[];
		for(const rowElement of rowElements)
		{
			// Skip already-upgraded instances for the same row identity.
			const dataRowId = rowElement.getAttribute("data-row-id") || "";
			const upgradedFor = rowElement.getAttribute("data-et2dg-upgraded-for") || "";
			if(upgradedFor === dataRowId && dataRowId)
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
			const upgradedFor = rowElement.getAttribute("data-et2dg-upgraded-for") || "";
			if(upgradedFor === dataRowId && dataRowId)
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
			if(this._upgradeRowElements(rowElement, row.data, rowIndex))
			{
				rowElement.setAttribute("data-et2dg-upgraded-for", dataRowId);
			}
			processed++;
		}
		if(this._rowUpgradeQueue.length)
		{
			this._scheduleRowUpgradeQueue();
		}
	}

	/**
	 * Resolve datastore ID prefix with provider override first, then app/name fallback.
	 */
	private _resolvedDataStorePrefix() : string
	{
		const fromProvider = this.dataProvider?.getDataStorePrefix?.()?.trim();
		if(fromProvider)
		{
			return fromProvider;
		}
		const app = this.getInstanceManager?.()?.app || this.egw?.()?.app_name?.();
		if(app)
		{
			return String(app);
		}
		const nextmatchId = (this.id || "").trim();
		if(nextmatchId)
		{
			return nextmatchId;
		}
		return "row";
	}

	/**
	 * Normalize arbitrary row identifiers for `data-row-id` usage.
	 */
	private _dataStoreRowIdFor(rowId : string | number) : string
	{
		return String(rowId ?? "");
	}

	/**
	 * Strip known datastore prefix from `data-row-id` to recover provider row id.
	 */
	private _rowIdFromDataStoreRowId(dataStoreRowId : string) : string
	{
		const doubleColonPrefix = `${this._resolvedDataStorePrefix()}::`;
		if(dataStoreRowId.startsWith(doubleColonPrefix))
		{
			return dataStoreRowId.slice(doubleColonPrefix.length);
		}
		return dataStoreRowId;
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
			let value = text.nodeValue || "";
			if(!value) continue;
			value = value.replace(/\{([^}]+)\}/g, (_match, token) => String(this._getFieldValue(row, token) ?? ""));
			value = value.replace(/\$row\.([a-zA-Z0-9_.]+)/g, (_match, token) => String(this._getFieldValue(row, token) ?? ""));
			text.nodeValue = value;
		}
	}

	/**
	 * Upgrade and configure custom child widgets after row insertion.
	 * This is deferred to keep scrolling/rendering responsive.
	 */
	private _upgradeRowElements(rowRoot : HTMLElement, rowData : any, rowIndex : number) : boolean
	{
		const toUpgrade = Array.from(rowRoot.querySelectorAll("*")) as any[];
		if(!toUpgrade.length)
		{
			rowRoot.classList.remove("loading");
			return true;
		}

		const mgrRowData = {};
		mgrRowData[rowIndex] = rowData;
		const mgr = this.getArrayMgr("content")?.openPerspective(this, mgrRowData, rowIndex);
		try
		{
			const ce = (window as any).customElements;
			// Upgrade custom elements first so widget APIs exist before we call
			// framework-specific attribute transformation hooks.
			if(ce && typeof ce.upgrade === "function")
			{
				for(const element of toUpgrade)
				{
					try
					{
						ce.upgrade(element);
					}
					catch(e)
					{
					}
				}
			}

			// Apply stored template attributes through each widget's transform hook
			// so row-scoped values ($row.*) are expanded with the current row manager.
			for(const element of toUpgrade)
			{
				try
				{
					const id = element.getAttribute?.("data-et2nm-id");
					const stored = id ? this.templateData?.rowTemplateAttrMap?.[id] : null;
					if(element.setArrayMgr && mgr)
					{
						element.setArrayMgr("content", mgr);
					}
					if(typeof element.transformAttributes === "function")
					{
						if(stored)
						{
							element.transformAttributes(stored);
						}
						else
						{
							const attrs : Record<string, string> = {};
							for(let i = 0; i < element.attributes.length; i++)
							{
								attrs[element.attributes[i].name] = element.attributes[i].value;
							}
							element.transformAttributes(attrs);
						}
					}
				}
				catch(e)
				{
				}
			}
		}
		catch(e)
		{
			rowRoot.classList.remove("loading");
			return false;
		}
		rowRoot.classList.remove("loading");
		return true;
	}

	/**
	 * Resolve stable row id from common fields with fallback index.
	 */
	private _rowIdFor(row : any, fallbackIndex : number) : string
	{
		return String(row?.uid ?? row?.id ?? row?.row_id ?? fallbackIndex);
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
	 * Evaluate whether a column should be hidden (supports boolean and expression strings).
	 */
	private _isColumnHidden(column : Et2DatagridColumn) : boolean
	{
		if(!column)
		{
			return false;
		}
		return !!column.hidden || this._isColumnDisabled(column);
	}

	/**
	 * Evaluate whether a column is disabled (not user-selectable in column chooser).
	 */
	private _isColumnDisabled(column : Et2DatagridColumn) : boolean
	{
		if(!column)
		{
			return false;
		}
		return this._resolveColumnBoolean(column.disabled);
	}

	/**
	 * Resolve Nextmatch-style boolean/boolean-expression column flags.
	 */
	private _resolveColumnBoolean(value : unknown) : boolean
	{
		if(typeof value === "boolean")
		{
			return value;
		}
		if(typeof value === "undefined" || value === null)
		{
			return false;
		}
		const expression = String(value).trim();
		if(expression === "")
		{
			return false;
		}
		try
		{
			const mgr = this.getArrayMgr && this.getArrayMgr("content");
			if(mgr && typeof mgr.parseBoolExpression === "function")
			{
				return !!mgr.parseBoolExpression(expression);
			}
		}
		catch(e)
		{
		}
		const normalized = expression.toLowerCase();
		return normalized === "true" || normalized === "1";
	}

	/**
	 * Build inline style string for width/min-width constraints.
	 */
	/**
	 * Build CSS grid track definitions from visible column widths.
	 */
	private _columnWidths(columns : Et2DatagridColumn[]) : string
	{
		let count = 0;
		let columnsWidths = [];
		columns.forEach(column => {
			let width = 'auto';
			if(column.width)
			{
				width = String(column.width);
				// Width is just a number - pixel size
				if(/^\d+$/.test(width))
				{
					width += "px";
					count += parseInt(column.width);
				}
				else
				{
					// Width is a percent, we'll let the browser allocate the leftover
					// non-pixel space with fr
					width = parseInt(width) + 'fr';
				}
			}
			columnsWidths.push(column.minWidth ? `minmax(${column.minWidth}px, ${width})` : width);
		});
		return columnsWidths.join(" ");
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
	 * Return columns that should be rendered, based on hidden/disabled state.
	 */
	private _visibleColumns() : Et2DatagridColumn[]
	{
		return (this.columns || []).filter((column) => !this._isColumnHidden(column));
	}

	/**
	 * Toggle visibility for already-rendered cells without waiting for virtualizer to recycle rows.
	 */
	private _applyColumnVisibilityToRenderedRows()
	{
		const rows = Array.from(this._rowsBody?.querySelectorAll("tr") || []) as HTMLElement[];
		if(!rows.length || !this.columns?.length)
		{
			return;
		}
		for(const row of rows)
		{
			this._applyColumnLayoutToRowElement(row);
		}
	}

	/**
	 * Align one row's cells with current column order + visibility.
	 */
	private _applyColumnLayoutToRowElement(row : HTMLElement)
	{
		if(row.classList.contains("dg-row-placeholder"))
		{
			return;
		}
		const cells = Array.from(row.children) as HTMLElement[];
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
	}

	/**
	 * Handle pointer row activation + selection.
	 */
	private _onTableClick(event : MouseEvent)
	{
		const target = event.target as HTMLElement | null;
		const row = target?.closest("tr[data-row-id]") as HTMLElement | null;
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

	private _onTablePointerDown(event : PointerEvent)
	{
		this._lastPointerToggleSelect = !!(event.ctrlKey || event.metaKey || event.getModifierState?.("Control") || event.getModifierState?.("Meta"));
	}

	/**
	 * Handle keyboard navigation and selection interactions.
	 */
	private _onTableKeydown(event : KeyboardEvent)
	{
		const key = event.key;
		if(!["ArrowUp", "ArrowDown", "PageUp", "PageDown", "Home", "End", " ", "a", "A"].includes(key))
		{
			return;
		}
		if(!this._rowsByIndex.length && this.total === null)
		{
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
			this._toggleSelectionOnActiveRow();
			return;
		}
		if((key === "a" || key === "A") && (event.ctrlKey || event.metaKey))
		{
			if(this.selectionMode === "multiple")
			{
				event.preventDefault();
				this.selectedRowIds = new Set(this.rows.map((row) => row.id));
				this._syncRowAccessibilityState();
				this._emitSelectionChanged();
			}
			return;
		}

		// Prevent native page scroll on navigation keys; grid owns row navigation.
		event.preventDefault();
		const previous = this.activeRowIndex;
		this._restoreFocusAfterRender = true;
		this._moveActiveRow(nextIndex, true);
		if(event.shiftKey && this.selectionMode === "multiple")
		{
			this._selectRange(this.anchorRowIndex >= 0 ? this.anchorRowIndex : previous, nextIndex);
		}
	}

	/**
	 * Handle column selection action from the header button.
	 */
	protected async _handleColumnSelectionClick(event : MouseEvent) : Promise<void>
	{
		event?.preventDefault();
		const columns = (this.columns || [])
			.filter((column) => !this._isColumnDisabled(column))
			.map((column) => ({
				// Target sl-menu-item can't handle spaces in value
				id: String(column.key).replaceAll(" ", "___"),
				title: column.title,
				caption: column.title,
				widget: column.header?.cloneNode?.(true),
				visibility: !this._isColumnHidden(column)
			}));

		const dialog = new Et2Dialog(this.egw());
		const selector = document.createElement("et2-nextmatch-columnselection") as any;
		selector.columns = columns;
		dialog.appendChild(selector);
		dialog.transformAttributes({
			title: this.egw().lang("Select columns"),
			template: this.egw().link(this.egw().webserverUrl + "/api/templates/default/nm_column_selection.xet"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			isModal: true
		});
		document.body.appendChild(dialog);
		const [buttonId] = await dialog.getComplete();
		if(buttonId !== Et2Dialog.OK_BUTTON)
		{
			return;
		}
		const selectedOrder = (selector.value || []).map((value) => String(value).replaceAll("___", " "));
		const selectedKeys = new Set(selectedOrder);
		const byKey = new Map((this.columns || []).map((column) => [String(column.key), column]));
		const selectedOrdered = selectedOrder
			.map((key) => byKey.get(String(key)))
			.filter(Boolean) as Et2DatagridColumn[];
		let selectedCursor = 0;
		this.columns = (this.columns || []).map((column) =>
		{
			const key = String(column.key);
			if(selectedKeys.has(key) && selectedCursor < selectedOrdered.length)
			{
				const ordered = selectedOrdered[selectedCursor++];
				return {
					...ordered,
					hidden: false
				};
			}
			return {
				...column,
				hidden: true
			};
		});
		// Apply track sizes and current rendered-row cell visibility immediately.
		this._ensureTableColSizes();
		this._applyColumnVisibilityToRenderedRows();
		this.requestUpdate();
		this.dispatchEvent(new CustomEvent("et2-columns-changed", {
			detail: {columns: this.columns},
			bubbles: true,
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
		if(this.selectionMode === "single")
		{
			this.selectedRowIds = new Set([rowId]);
			this.anchorRowIndex = rowIndex;
			this._syncRowAccessibilityState();
			this._emitSelectionChanged();
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
		this._emitSelectionChanged();
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
	}

	/**
	 * Focus row by absolute index, optionally scrolling it into view.
	 */
	private _focusRowByIndex(index : number, retries : number = 0, allowScroll : boolean = true)
	{
		const rowElement = (Array.from(this._rowsBody?.querySelectorAll("tr[data-row-index]") || []) as HTMLElement[])
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

	/**
	 * Synchronize ARIA attributes and tabindex across rendered row DOM.
	 */
	private _syncRowAccessibilityState()
	{
		const rowElements = Array.from(this._rowsBody?.querySelectorAll("tr[data-row-index]") || []) as HTMLElement[];
		rowElements.forEach((rowElement) =>
		{
			const absoluteIndex = parseInt(rowElement.getAttribute("data-row-index") || "-1", 10);
			const rowId = rowElement.getAttribute("data-row-id") || "";
			rowElement.setAttribute("role", "row");
			rowElement.setAttribute("aria-selected", this.selectedRowIds.has(rowId) ? "true" : "false");
			rowElement.setAttribute("aria-rowindex", String(Math.max(0, absoluteIndex) + 1));
			rowElement.tabIndex = absoluteIndex === this.activeRowIndex ? 0 : -1;
			rowElement.classList.toggle("dg-row-selected", this.selectedRowIds.has(rowId));
			rowElement.classList.toggle("dg-row-active", rowId === this.activeRowId);

			const cells = Array.from(rowElement.children) as HTMLElement[];
			cells.forEach((cell, cellIndex) =>
			{
				const isHeader = cell.tagName.toLowerCase() === "th";
				cell.setAttribute("role", isHeader ? "columnheader" : "gridcell");
				cell.setAttribute("aria-colindex", String(cellIndex + 1));
			});
		});
	}

	/**
	 * Emit normalized selection detail for parent listeners.
	 */
	private _emitSelectionChanged()
	{
		const selectedRows = this.rows.filter((row) => this.selectedRowIds.has(row.id)).map((row) => row.data);
		const detail : Et2DatagridSelectionDetail = {
			selectedRowIds: Array.from(this.selectedRowIds),
			selectedRows,
			activeRowId: this.activeRowId,
			activeRowIndex: this.activeRowIndex
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
		this.rows = mappedRows;
		this._rowsByIndex = mappedRows.slice();
		this.loading = false;
		this.fetching = false;
		this.displayedRowIds = new Set(mappedRows.map((row) => row.id));
		this.requestUpdate();
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
		await this.loadMore();
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
				<div class="dg-state dg-state--loading">
					${this.templateData?.loaderTemplate
			          ? html`${unsafeHTML(this._loaderHtml())}`
			          : this._et2LoadingTemplate()}
				</div>
			`;
		}
		if(fetchFailed)
		{
			const message = this.fetchErrorMessage || this.egw().lang("Unable to load rows. Please try again.");
			return html`<div class="dg-state dg-state--error">${this._et2ErrorTemplate(message)}</div>`;
		}
		if(noTemplate)
		{
			return html`
				<div class="dg-state" part="state">
					<sl-alert variant="primary" open>
						<sl-icon slot="icon" name="layout-text-window-reverse"></sl-icon>
						<strong>${this.egw().lang("No row template configured")}</strong><br/>
						${this.egw().lang("Set a template or provide row/header slots.")}
					</sl-alert>
				</div>
			`;
		}
		if(noRows)
		{
			return html`
				<div class="dg-state" part="state">
					<sl-alert variant="neutral" open>
						<sl-icon slot="icon" name="inbox"></sl-icon>
						<strong>${this.egw().lang("No entries to display")}</strong><br/>
						${this._hasFetchedOnce ? this.egw().lang("No rows were returned.") : this.egw().lang("Waiting for rows.")}
					</sl-alert>
				</div>
			`;
		}
		return null;
	}

	/**
	 * Render the visible column header row (or fallback header slot).
	 */
	protected _headerTemplate(visibleColumns:Et2DatagridColumn[])
	{
		const columnsHeaders = html`
			${visibleColumns.map((column) => html`
				<div class="dg-col" role="columnheader" title=${column.title}>
					${column.header ?? column.title}
				</div>
			`)}
			${this.noColumnSelection ? nothing : html`
				<div class="dg-colselection">
                    <et2-button-icon image="list-task" label=${this.egw().lang("select columns")}
                                     @click=${this._handleColumnSelectionClick}
									 noSubmit
					></et2-button-icon>
				</div>
			`}
		`;
		return html`
			<div class="dg-header" role="rowgroup">
				${visibleColumns.length > 0 ? columnsHeaders : 	html`<slot name="header"></slot>`}
			</div>
		`;
	}

	/**
	 * A non-visible header for accessibility at the top of the table
	 *
	 * @param {Et2DatagridColumn[]} visibleColumns
	 * @return {TemplateResult<1>}
	 * @private
	 */
	private _accessableHeaderTemplate(visibleColumns:Et2DatagridColumn[])
	{
		return html`${visibleColumns.map((column) => {
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
		const headerTemplate = this._headerTemplate(visibleColumns);
		const stateTemplate = this._stateTemplate();
		const styles = {
			'--column-count' : visibleColumns.length,
			'--column-sizes': this._columnWidths(visibleColumns)
		}
		const rowCount = this.total ?? Math.max(this._rowsByIndex.length + this._pendingPlaceholderCount, this.rows.length);
		const virtualIndexes = this._getVirtualIndexes(rowCount);
		return html`
			<div class="dg-root" style=${styleMap(styles)}>
				<!-- Visible header for users -->
				${headerTemplate}

                <div class="dg-body">
					${stateTemplate}

					<table
						role="grid"
						aria-label=${this.getAttribute("aria-label") || this.getAttribute("label") || "Data grid"}
						aria-multiselectable=${String(this.selectionMode === "multiple")}
						aria-colcount=${String(visibleColumns.length || this.columns.length || 1)}
						aria-rowcount=${String(this.total ?? this.rows.length)}
						?hidden=${!!stateTemplate}
                        @keydown=${this._onTableKeydown}
                        @pointerdown=${this._onTablePointerDown}
						@click=${this._onTableClick}
					>
						<!-- Accessible / sizing header -->
						<thead>
							${this._accessableHeaderTemplate(visibleColumns)}
						</thead>
                        <tbody id="rows" role="rowgroup">
                        ${virtualize({
                            items: virtualIndexes,
                            keyFunction: this._virtualRowKey,
                            renderItem: this._renderVirtualRow
                        })}
                        </tbody>
					</table>
				</div>
			</div>
		`;
	}
}
