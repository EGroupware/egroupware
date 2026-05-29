import {html, LitElement, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2Datagrid} from "./Et2Datagrid";
import {Et2DatagridColumn, Et2DatagridRowCustomizeContext, Et2DatagridTemplateData} from "./Et2Datagrid.types";
import {Et2RowProvider} from "./Et2RowProvider";
import {Et2NextmatchDataProvider} from "./Et2NextmatchDataProvider";
import {EgwAction} from "../../egw_action/EgwAction";
import {Et2Filterbox} from "../Et2Filterbox/Et2Filterbox";
import {Et2Template} from "../Et2Template/Et2Template";
import {Et2NextmatchActionController} from "./Et2NextmatchActionController";
import "./Headers/Header";
import "./Headers/SortableHeader";
import {
	ET2_NEXTMATCH_FILTER_EVENT,
	ET2_NEXTMATCH_SORT_EVENT,
	Et2NextmatchFilterEventDetail,
	Et2NextmatchSortEventDetail
} from "./Headers/events";
import styles from "./Et2Nextmatch.styles";

/**
 * @summary Nextmatch shows entries with filtering and context menu
 *
 * @event et2-loading-start - Re-emitted from the inner datagrid when row fetching starts.
 * @event et2-loading-done - Re-emitted from the inner datagrid when all fetches complete.
 * @event et2-loading-error - Re-emitted from the inner datagrid when a fetch fails.
 * @event et2-search-result - Legacy-compatible event emitted after fetch completion.
 * @event et2-selection-changed - Re-emitted from the inner datagrid when row selection changes.
 * @event et2-columns-changed - Re-emitted from the inner datagrid when columns change.
 *
 * @slot header - Optional content rendered above the datagrid.
 * @slot columns - Slotted column definition used to derive datagrid columns when `template` is not set.
 * @slot row - Slotted row template used to render each datagrid row when `template` is not set.
 * @slot loader - Optional slotted loader content shown while rows are loading.
 *
 * @csspart header - Wrapper for top header slot content rendered above the grid.
 * @csspart grid - Internal `et2-datagrid` element.
 * @cssproperty [--row-height=3em] - Forwarded to internal datagrid row-height estimate.
 * @cssproperty [--meta-column-width=6px] - Width of leading metadata indicator column.
 */
@customElement("et2-nextmatch")
export class Et2Nextmatch extends Et2Widget(LitElement)
{
	/**
	 * Compose Nextmatch host styles from shared Et2Widget styles and local layout styles.
	 */
	static get styles()
	{
		return [
			super.styles,
			styles
		];
	}

	/** Initial rows data. Can be set directly or via setRows(). */
	@property({type: Array})
	rows : any[] = [];

	/** Template name used to resolve columns and row layout. */
	@property({type: String})
	template : string = "";

	/** Optional custom preference name for persisted datagrid column settings. */
	@property({type: String, attribute: "column-preference-name"})
	columnPreferenceName : string = "";

	/** Optional filter template source (template name, .xet URL, or template element). */
	@property({attribute: false})
	filterTemplate : string | Et2Template | HTMLElement | null = null;

	@state()
	private _columns : Et2DatagridColumn[] = [];

	@state()
	private _templateData : Et2DatagridTemplateData | null = null;

	@state()
	private _templateLoading : boolean = false;

	private _templateLoadToken : number = 0;
	private _templateLoadingName : string | null = null;
	private _templateLoadPromise : Promise<void> | null = null;
	private _rowProvider : Et2RowProvider;
	private _dataProvider : Et2NextmatchDataProvider;
	private _slotObserver : MutationObserver | null = null;
	private _slotApplyInFlight : Promise<void> | null = null;
	private _legacyColumnPreferenceApplied : Set<string> = new Set();
	private _filters : Record<string, any> = {col_filter: {}};
	private _filterbox : Et2Filterbox | null = null;
	private _actionController : Et2NextmatchActionController;

	/**
	 * Resolve the internal datagrid instance from shadow DOM.
	 * This is centralized so future render structure changes only need one update.
	 */
	private get _datagrid() : Et2Datagrid | null
	{
		return this.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid | null;
	}

	/**
	 * Build helper collaborators once.
	 * They are stateful and reused for the lifetime of the component.
	 */
	constructor()
	{
		super();
		// Keep a runtime reference so module import stays
		void Et2Datagrid;
		if(!this._filters.col_filter || typeof this._filters.col_filter !== "object")
		{
			this._filters.col_filter = {};
		}
		this._rowProvider = new Et2RowProvider(this as any);
		this._dataProvider = new Et2NextmatchDataProvider(this as any);
		this._actionController = new Et2NextmatchActionController(this as any);
	}

	/**
	 * Attach observers once the host is connected.
	 */
	connectedCallback()
	{
		super.connectedCallback();
		this._initSlotObserver();
		this.addEventListener(ET2_NEXTMATCH_SORT_EVENT, this._handleHeaderSortEvent as EventListener);
		this.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, this._handleHeaderFilterEvent as EventListener);
		this.addEventListener("et2-loading-done", this._handleLoadingDone as EventListener);
		this.addEventListener("et2-selection-changed", this._handleSelectionChanged as EventListener);
		this.addEventListener("contextmenu", this._handleContextMenu as EventListener);
		this.addEventListener("keydown", this._handleKeydown as EventListener);
		this.addEventListener("pointerdown", this._handlePointerDown as EventListener);
		this.addEventListener("pointermove", this._handlePointerMove as EventListener);
		this.addEventListener("pointerup", this._cancelLongPress as EventListener);
		this.addEventListener("pointercancel", this._cancelLongPress as EventListener);
	}

	/**
	 * Disconnect observers to avoid stale slot reactions after the widget is detached.
	 */
	disconnectedCallback()
	{
		this._slotObserver?.disconnect();
		this._slotObserver = null;
		this._filterbox?.remove();
		this._filterbox = null;
		this.removeEventListener(ET2_NEXTMATCH_SORT_EVENT, this._handleHeaderSortEvent as EventListener);
		this.removeEventListener(ET2_NEXTMATCH_FILTER_EVENT, this._handleHeaderFilterEvent as EventListener);
		this.removeEventListener("et2-loading-done", this._handleLoadingDone as EventListener);
		this.removeEventListener("et2-selection-changed", this._handleSelectionChanged as EventListener);
		this.removeEventListener("contextmenu", this._handleContextMenu as EventListener);
		this.removeEventListener("keydown", this._handleKeydown as EventListener);
		this.removeEventListener("pointerdown", this._handlePointerDown as EventListener);
		this.removeEventListener("pointermove", this._handlePointerMove as EventListener);
		this.removeEventListener("pointerup", this._cancelLongPress as EventListener);
		this.removeEventListener("pointercancel", this._cancelLongPress as EventListener);
		this._actionController.destroy();
		super.disconnectedCallback();
	}

	transformAttributes(attrs)
	{
		// Process legacy 'settings' into properties
		// We're before namespace creation here, so use attrs.id
		const settings = this.getArrayMgr("content").getEntry(attrs.id || 'nm');
		if(settings && Object.keys(settings).length > 0)
		{
			Object.assign(attrs, settings);
		}
		super.transformAttributes(attrs);
	}

	protected _initActions(actions : EgwAction[] | { [id : string] : object })
	{
		this._actionController.initActions(actions);
	}

	getSelection() : { ids : string[]; all : boolean }
	{
		return this._actionController.getSelection();
	}

	selectSingleRow(rowId : string)
	{
		this._datagrid?.selectSingleRow(rowId);
	}

	/**
	 * Initialize the widget from attributes/template and trigger first load.
	 * We prefer showing provided rows immediately to keep first paint fast.
	 */
	async firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);
		this._loadRowsAttribute();

		if(this.template)
		{
			await this._applyTemplateFromName(this.template);
		}
		else
		{
			await this._applyTemplateFromSlots();
		}

		if(this.rows.length)
		{
			this._datagrid?.setInitialRows(this.rows);
		}
		else
		{
			await this._datagrid?.reload();
		}
	}

	/**
	 * React to template changes after initial render.
	 * Template source is mutually exclusive: explicit template name wins over slots.
	 */
	protected updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has("template"))
		{
			if(this.template)
			{
				this._applyTemplateFromName(this.template);
			}
			else
			{
				this._applyTemplateFromSlots();
			}
		}
		if(changedProperties.has("filterTemplate"))
		{
			this._applyFilterTemplate(this.filterTemplate);
		}
	}

	/**
	 * Force namespace creation for nested widgets.
	 * Nextmatch behaves as a container and must always scope children.
	 */
	_createNamespace() : boolean
	{
		return true;
	}

	/**
	 * Public API to override visible columns programmatically.
	 * Accepts legacy string arrays and normalizes them for datagrid consumption.
	 */
	setColumns(columns : Array<string | { key : string; title : string }>)
	{
		this._columns = (columns || []).map((column, index) =>
			typeof column === "string" ? {key: "col" + index, title: column} : column
		);
		if(this._templateData)
		{
			this._templateData = {
				...this._templateData,
				columns: this._columns
			};
		}
	}

	/**
	 * Public API to inject already-fetched rows.
	 * This bypasses first server fetch and is used for fast preloaded lists.
	 */
	setRows(rows : any[])
	{
		this.rows = rows || [];
		this._datagrid?.setInitialRows(this.rows);
	}

	/**
	 * Create/attach filterbox once and return it.
	 */
	private _ensureFilterbox() : Et2Filterbox
	{
		if(this._filterbox)
		{
			return this._filterbox;
		}
		this._filterbox = <Et2Filterbox><unknown>loadWebComponent("et2-filterbox", {
			exportparts: "filters",
			slot: "filter",
			autoapply: true,
			originalWidgets: this.egw().preference("keep_nm_header", "common") || "replace"
		}, this);
		this._filterbox.nextmatch = <any>this;

		const root = this.getRootNode() as ShadowRoot | Document;
		let host : HTMLElement | null = root instanceof ShadowRoot ? root.host as HTMLElement : this.parentElement;
		let filterParent : HTMLElement | null = host;
		while(filterParent)
		{
			if(filterParent.shadowRoot?.querySelector("slot[name='filter']"))
			{
				break;
			}
			filterParent = filterParent.parentElement;
		}
		(filterParent || host?.closest("egw-app") || this.parentElement || this).append(this._filterbox);
		return this._filterbox;
	}

	/**
	 * Apply current `filterTemplate` source to the shared filterbox instance.
	 */
	private _applyFilterTemplate(template : string | Et2Template | HTMLElement | null)
	{
		if(!template && !this._filterbox)
		{
			return;
		}
		const filterbox = this._ensureFilterbox();
		if(typeof (filterbox as any).setFilterTemplate === "function")
		{
			filterbox.setFilterTemplate(template);
		}
		else
		{
			// Compatibility fallback for stale generated JS not yet rebuilt.
			(filterbox as any).filterTemplate = template;
		}
	}

	/**
	 * Legacy-compatible active filters accessor used by header/filter integrations.
	 *
	 * @deprecated Accessing filter state via this accessor is legacy compatibility.
	 * Prefer event-driven updates (`applyFilters`, header filter/sort events) and
	 * treat filter state as internal.
	 */
	get activeFilters() : Record<string, any>
	{
		return this._filters;
	}

	/**
	 * Legacy-compatible filter application entry point.
	 * Merges updates into `activeFilters`, emits cancelable `et2-filter`, and reloads rows by default.
	 */
	applyFilters(set? : Record<string, any>, options? : { reload? : boolean })
	{
		if(!this._filters || typeof this._filters !== "object")
		{
			this._filters = {col_filter: {}};
		}
		if(!this._filters.col_filter || typeof this._filters.col_filter !== "object")
		{
			this._filters.col_filter = {};
		}
		const activeFilters = this._filters;
		const previousFilters = {
			...activeFilters,
			col_filter: {...(activeFilters.col_filter || {})},
			sort: activeFilters.sort ? {...activeFilters.sort} : undefined
		};
		let changed = false;
		if(typeof set !== "undefined" && typeof set === "object" && Object.keys(set).length === 0)
		{
			this._filters = {col_filter: {}};
			activeFilters.col_filter = this._filters.col_filter;
			changed = true;
		}

		if(typeof set === "object" && set !== null)
		{
			for(const key of Object.keys(set))
			{
				if(key === "col_filter")
				{
					const colFilter = set.col_filter;
					if(colFilter === undefined || colFilter === null)
					{
						if(Object.keys(activeFilters.col_filter || {}).length)
						{
							changed = true;
						}
						activeFilters.col_filter = {};
						continue;
					}
					for(const columnId of Object.keys(colFilter))
					{
						const nextValue = colFilter[columnId];
						if(activeFilters.col_filter[columnId] !== nextValue)
						{
							changed = true;
							if(nextValue)
							{
								activeFilters.col_filter[columnId] = nextValue;
							}
							else
							{
								delete activeFilters.col_filter[columnId];
							}
						}
					}
					continue;
				}
				if(activeFilters[key] !== set[key])
				{
					activeFilters[key] = set[key];
					changed = true;
				}
			}
		}
		if(!changed)
		{
			return false;
		}

		const changeEvent = new CustomEvent("et2-filter", {
			bubbles: true,
			cancelable: true,
			detail: {
				oldFilters: previousFilters,
				activeFilters,
				nm: this
			}
		});
		this.dispatchEvent(changeEvent);
		if(changeEvent.defaultPrevented)
		{
			return false;
		}
		const eventFilters = changeEvent.detail?.activeFilters;
		if(eventFilters && typeof eventFilters === "object" && eventFilters !== this._filters)
		{
			this._filters = eventFilters;
		}
		if(!this._filters.col_filter || typeof this._filters.col_filter !== "object")
		{
			this._filters.col_filter = {};
		}

		this._updateSortHeaderState();
		this._actionController.clearRowActionObjects();
		if(options?.reload !== false)
		{
			this._datagrid?.reload();
		}

		return true;
	}

	/**
	 * Legacy-compatible sorting helper used by sort headers and filterbox.
	 */
	sortBy(id : string, asc? : boolean, update : boolean = true)
	{
		if(!id)
		{
			return;
		}
		if(typeof asc === "undefined")
		{
			const filters = this._filters;
			if(filters.sort?.id === id)
			{
				asc = !filters.sort.asc;
			}
			else
			{
				asc = true;
			}
		}
		if(update !== false)
		{
			this.applyFilters({sort: {id, asc}});
		}
		else
		{
			this._filters.sort = {id, asc};
			this._updateSortHeaderState();
		}
	}

	resetSort()
	{
		if(typeof this._filters.sort !== "undefined")
		{
			this.applyFilters({sort: undefined});
		}
	}

	/**
	 * Parse optional JSON rows attribute.
	 * This keeps backwards compatibility with existing template-driven usage.
	 */
	private _loadRowsAttribute()
	{
		const rowsAttribute = this.getAttribute("rows");
		if(!rowsAttribute)
		{
			return;
		}
		try
		{
			this.rows = JSON.parse(rowsAttribute);
		}
		catch(e)
		{
		}
	}

	/**
	 * Watch slot mutations and re-resolve template data when no explicit template name is set.
	 * We observe subtree+slot attributes because slotted content can be reparented dynamically.
	 */
	private _initSlotObserver()
	{
		this._slotObserver?.disconnect();
		this._slotObserver = new MutationObserver((records) =>
		{
			if(!this.template && this._hasAddedTemplateSlotNode(records))
			{
				this._applyTemplateFromSlots();
			}
		});
		this._slotObserver.observe(this, {
			childList: true,
			subtree: true
		});
	}

	/**
	 * Return true only when an added node has one of the template-input slots.
	 */
	private _hasAddedTemplateSlotNode(records : MutationRecord[]) : boolean
	{
		for(const record of records)
		{
			if(record.type !== "childList")
			{
				continue;
			}
			for(const node of Array.from(record.addedNodes))
			{
				if(!(node instanceof Element))
				{
					continue;
				}
				const slotName = String(node.getAttribute("slot") || "").trim();
				if(["header", "columns", "row", "loader"].includes(slotName))
				{
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Resolve row/column configuration from a named Et2Template.
	 * Concurrent calls are de-duplicated for the same template and guarded by token checks.
	 */
	private async _applyTemplateFromName(templateName : string)
	{
		if(this._templateLoading && this._templateLoadingName === templateName && this._templateLoadPromise)
		{
			return this._templateLoadPromise;
		}

		const token = ++this._templateLoadToken;
		this._templateLoading = true;
		this._templateLoadingName = templateName;

		const loadPromise = (async() =>
		{
			try
			{
				// Row provider performs async XML/template resolution; token guards prevent stale writes.
				const templateData = await this._rowProvider.fromTemplate(templateName);
				if(token !== this._templateLoadToken)
				{
					return;
				}
				if(!templateData)
				{
					this._templateData = null;
					return;
				}
				this._applyTemplateData(templateData);
			}
			finally
			{
				// Keep loading indicator tied to the most recent request only.
				if(token === this._templateLoadToken)
				{
					this._templateLoading = false;
					this._templateLoadingName = null;
				}
			}
		})().finally(() =>
		{
			// Clear in-flight handle only for the active token to avoid dropping newer promises.
			if(token === this._templateLoadToken)
			{
				this._templateLoadPromise = null;
			}
		});

		this._templateLoadPromise = loadPromise;
		return loadPromise;
	}

	/**
	 * Resolve row/column configuration from slotted markup.
	 * Wait for slotted children to finish upgrades before parsing.
	 */
	private async _applyTemplateFromSlots() : Promise<void>
	{
		if(this._slotApplyInFlight)
		{
			return this._slotApplyInFlight;
		}
		this._slotApplyInFlight = (async() =>
		{
			await this._waitForSlottedTemplateChildrenReady();
			this._templateLoading = false;
			const templateData = this._rowProvider.fromSlots();
			if(!templateData)
			{
				this._templateData = null;
				return;
			}
			this._applyTemplateData(templateData);
		})().finally(() =>
		{
			this._slotApplyInFlight = null;
		});
		return this._slotApplyInFlight;
	}

	/**
	 * Wait for slotted template nodes and their descendants to finish update cycles before parsing.
	 */
	private async _waitForSlottedTemplateChildrenReady() : Promise<void>
	{
		const slotNodes = Array.from(this.querySelectorAll("[slot='columns'], [slot='row'], [slot='loader']")) as Element[];
		if(!slotNodes.length)
		{
			return;
		}
		const waitables : Promise<any>[] = [];
		for(const node of slotNodes)
		{
			const withUpdateComplete = node as any;
			if(withUpdateComplete?.updateComplete && typeof withUpdateComplete.updateComplete.then === "function")
			{
				waitables.push(withUpdateComplete.updateComplete);
			}
			for(const child of Array.from(node.querySelectorAll("*")))
			{
				const updatable = child as any;
				if(updatable?.updateComplete && typeof updatable.updateComplete.then === "function")
				{
					waitables.push(updatable.updateComplete);
				}
			}
		}
		if(waitables.length)
		{
			await Promise.allSettled(waitables);
		}
	}

	/**
	 * Apply resolved template data and normalize final column source.
	 * If no columns were parsed we keep externally-set columns as fallback.
	 */
	private _applyTemplateData(templateData : Et2DatagridTemplateData)
	{
		const columns = templateData.columns?.length ? templateData.columns : this._columns;
		this._columns = this._applyLegacyNextmatchColumnPreferences(columns || [], templateData);
		this._templateData = {
			...templateData,
			columns: this._columns
		};
	}

	/**
	 * Apply legacy Nextmatch column preferences once per row-template id.
	 *
	 * Legacy keys:
	 * - `nextmatch-<row_ID>` -> CSV of visible column keys
	 * - `nextmatch-<row_ID>-size` -> JSON/object map `{ column_key: size }`
	 *
	 * This migration is legacy Nextmatch-specific compatibility behaviour
	 */
	private _applyLegacyNextmatchColumnPreferences(
		columns : Et2DatagridColumn[],
		templateData : Et2DatagridTemplateData
	) : Et2DatagridColumn[]
	{
		const rowTemplateId = String(templateData?.rowTemplateId || "").trim();
		if(!rowTemplateId || !columns.length)
		{
			return columns;
		}
		const preferenceBase = `nextmatch-${rowTemplateId}`;
		if(this._legacyColumnPreferenceApplied.has(preferenceBase))
		{
			return columns;
		}
		this._legacyColumnPreferenceApplied.add(preferenceBase);

		const app = String(this.getInstanceManager()?.app || this.egw()?.app_name?.() || "").trim();
		if(!app)
		{
			return columns;
		}

		let storedVisibility : any = null;
		let storedSizes : any = null;
		try
		{
			storedVisibility = this.egw().preference(preferenceBase, app);
			storedSizes = this.egw().preference(`${preferenceBase}-size`, app);
		}
		catch(e)
		{
			return columns;
		}

		const isLegacyVisibilityCsv = typeof storedVisibility === "string" &&
			!storedVisibility.trim().startsWith("[") &&
			!storedVisibility.trim().startsWith("{");
		const visibleKeys = isLegacyVisibilityCsv
		                    ? storedVisibility.split(",").map((value) => String(value).trim()).filter(Boolean)
		                    : [];
		const mappedVisibleKeys = this._mapLegacyVisibleKeysToCurrentColumns(visibleKeys, columns);

		let nextColumns = [...columns];
		if(mappedVisibleKeys.length)
		{
			nextColumns = this._applyLegacySelectionOrder(nextColumns, mappedVisibleKeys);
		}

		let widthMap : Record<string, any> = {};
		if(typeof storedSizes === "string")
		{
			try
			{
				widthMap = JSON.parse(storedSizes);
			}
			catch(e)
			{
				widthMap = {};
			}
		}
		else if(storedSizes && typeof storedSizes === "object")
		{
			widthMap = storedSizes;
		}
		nextColumns = nextColumns.map((column) =>
		{
			const key = String(column.key);
			if(typeof widthMap[key] === "undefined")
			{
				return column;
			}
			return {
				...column,
				width: String(widthMap[key])
			};
		});

		// Seed the new-format preference for datagrid persistence path.
		/*
		// TODO: When things stabilize, we can do this an delete the old preferences
		if(visibleKeys.length || Object.keys(widthMap).length)
		{
			try
			{
				this.egw().set_preference(app, preferenceBase, nextColumns.map((column) => ({
					key: String(column.key),
					width: typeof column.width === "string" ? column.width : undefined,
					hidden: !!column.hidden
				})));
			}
			catch(e)
			{
			}
		}

		 */
		return nextColumns;
	}

	/**
	 * Apply selected-order visibility semantics used by legacy Nextmatch CSV preferences.
	 */
	private _applyLegacySelectionOrder(columns : Et2DatagridColumn[], selectedKeysInOrder : string[]) : Et2DatagridColumn[]
	{
		const selectedKeys = new Set(selectedKeysInOrder);
		const byKey = new Map((columns || []).map((column) => [String(column.key), column]));
		const selectedOrdered = selectedKeysInOrder
			.map((key) => byKey.get(String(key)))
			.filter(Boolean) as Et2DatagridColumn[];
		let selectedCursor = 0;
		return (columns || []).map((column) =>
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
	}

	/**
	 * Map legacy Nextmatch visibility keys onto current datagrid column keys.
	 *
	 * Older preferences can contain expanded/duplicated composite keys or historic
	 * custom-field markers (eg `#text`) that no longer match current column keys.
	 */
	private _mapLegacyVisibleKeysToCurrentColumns(legacyKeys : string[], columns : Et2DatagridColumn[]) : string[]
	{
		const currentKeys = (columns || []).map((column) => String(column.key || "")).filter(Boolean);
		if(!legacyKeys.length || !currentKeys.length)
		{
			return [];
		}
		const currentKeySet = new Set(currentKeys);
		const used = new Set<string>();
		const mapped : string[] = [];
		const currentNormalized = new Map<string, string[]>();
		for(const key of currentKeys)
		{
			currentNormalized.set(key, this._normalizeLegacyColumnKeyTokens(key));
		}

		for(const legacyKeyRaw of legacyKeys)
		{
			const legacyKey = String(legacyKeyRaw || "").trim();
			if(!legacyKey)
			{
				continue;
			}
			// Exact key match first.
			if(currentKeySet.has(legacyKey) && !used.has(legacyKey))
			{
				mapped.push(legacyKey);
				used.add(legacyKey);
				continue;
			}
			// Historic custom-field placeholders map to `customfields` when available.
			if(legacyKey.startsWith("#") && currentKeySet.has("customfields") && !used.has("customfields"))
			{
				mapped.push("customfields");
				used.add("customfields");
				continue;
			}

			const legacyTokens = this._normalizeLegacyColumnKeyTokens(legacyKey);
			if(!legacyTokens.length)
			{
				continue;
			}
			let bestKey : string | null = null;
			let bestScore = 0;
			for(const currentKey of currentKeys)
			{
				if(used.has(currentKey))
				{
					continue;
				}
				const currentTokens = currentNormalized.get(currentKey) || [];
				const score = this._legacyColumnKeySimilarityScore(legacyTokens, currentTokens);
				if(score > bestScore)
				{
					bestScore = score;
					bestKey = currentKey;
				}
			}
			// Threshold tuned to prefer clearly-related composites only.
			if(bestKey && bestScore >= 0.6)
			{
				mapped.push(bestKey);
				used.add(bestKey);
			}
		}
		return mapped;
	}

	/**
	 * Tokenize and normalize legacy/current column keys for fuzzy matching.
	 */
	private _normalizeLegacyColumnKeyTokens(key : string) : string[]
	{
		const tokens = String(key || "")
			.toLowerCase()
			.replace(/^#+/, "")
			.split(/[^a-z0-9]+/)
			.filter(Boolean);
		// Dedupe repeated runs and global repeats while preserving order.
		const normalized : string[] = [];
		for(const token of tokens)
		{
			if(normalized[normalized.length - 1] === token)
			{
				continue;
			}
			if(normalized.includes(token))
			{
				continue;
			}
			normalized.push(token);
		}
		return normalized;
	}

	/**
	 * Similarity score for legacy/current key token lists.
	 */
	private _legacyColumnKeySimilarityScore(legacyTokens : string[], currentTokens : string[]) : number
	{
		if(!legacyTokens.length || !currentTokens.length)
		{
			return 0;
		}
		const currentSet = new Set(currentTokens);
		let overlap = 0;
		for(const token of legacyTokens)
		{
			if(currentSet.has(token))
			{
				overlap++;
			}
		}
		const overlapRatio = overlap / Math.max(legacyTokens.length, currentTokens.length);
		const legacyContainsCurrent = currentTokens.every((token) => legacyTokens.includes(token)) ? 0.35 : 0;
		const currentContainsLegacy = legacyTokens.every((token) => currentTokens.includes(token)) ? 0.25 : 0;
		return overlapRatio + legacyContainsCurrent + currentContainsLegacy;
	}


	/**
	 * Nextmatch-specific row metadata styling hook.
	 * Maps category classes to a style-ready `--category-color` on datagrid meta cell.
	 */
	private _customizeDatagridRow = (context : Et2DatagridRowCustomizeContext) =>
	{
		const categoryClass = Array.from(context.rowElement.classList).find((name) => /^cat_\d+$/.test(name));
		if(categoryClass)
		{
			const categoryId = categoryClass.slice(4);
			context.metaCell.style.setProperty("--category-color", `var(--cat-${categoryId}-color)`);
		}
		else
		{
			context.metaCell.style.removeProperty("--category-color");
		}
		context.metaCell.setAttribute(
			"part",
			context.rowElement.classList.contains("row_category")
			? "row-meta row-meta-category"
			: "row-meta"
		);
	};

	/**
	 * Keep slotted sort headers in sync with currently active sort filter.
	 */
	private _updateSortHeaderState()
	{
		const sort = this._filters.sort;
		const mode = sort?.asc === true ? "asc" : sort?.asc === false ? "desc" : "none";
		Array.from(this.shadowRoot.querySelectorAll("et2-nextmatch-sortheader")).forEach((header : any) =>
		{
			const headerMode = sort?.id && header.id === sort.id ? mode : "none";
			if(typeof header.setSortmode === "function")
			{
				header.setSortmode(headerMode);
			}
			else if(typeof header.set_sortmode === "function")
			{
				header.set_sortmode(headerMode);
			}
		});
	}

	/**
	 * Resolve app-name used for legacy sort preference persistence.
	 */
	private _getAppName() : string
	{
		return String(this.getInstanceManager?.()?.app || this.egw()?.app_name?.() || "");
	}

	/**
	 * Apply sort event from header widget after event bubbling is complete.
	 */
	private _handleHeaderSortEvent = (event : CustomEvent<Et2NextmatchSortEventDetail>) =>
	{
		queueMicrotask(() =>
		{
			if(event.defaultPrevented)
			{
				return;
			}
			const detail = event.detail || <Et2NextmatchSortEventDetail>{};
			if(!detail.id)
			{
				return;
			}
			this.sortBy(detail.id, detail.asc, detail.update);
			const appName = this._getAppName();
			if(!appName || !this.template)
			{
				return;
			}
			try
			{
				this.egw().set_preference(appName, this.template + "_sort", this._filters.sort);
			}
			catch(e)
			{
			}
		});
	};

	/**
	 * Apply filter event from header widget after bubbling so listeners can cancel.
	 */
	private _handleHeaderFilterEvent = (event : CustomEvent<Et2NextmatchFilterEventDetail>) =>
	{
		queueMicrotask(() =>
		{
			if(event.defaultPrevented)
			{
				return;
			}
			const filters = event.detail?.filters;
			if(!filters)
			{
				return;
			}
			this.applyFilters(filters);
		});
	};

	/**
	 * Emit legacy `et2-search-result` after datagrid finishes loading.
	 */
	private _handleLoadingDone = (event : Event) =>
	{
		const datagrid = this._datagrid;
		if(!datagrid || !event.composedPath().includes(datagrid))
		{
			return;
		}
		this.dispatchEvent(new CustomEvent("et2-search-result", {
			detail: {
				total: String(datagrid.total ?? ""),
				nextmatch: this
			},
			bubbles: true
		}));
	};

	private _handleSelectionChanged = (event : CustomEvent<{ selectedRowIds? : string[]; activeRowId? : string }>) =>
	{
		const datagrid = this._datagrid;
		if(!datagrid || !event.composedPath().includes(datagrid))
		{
			return;
		}
		this._actionController.handleSelectionChanged(event.detail || {});
	};

	private _handleContextMenu = (event : MouseEvent) =>
	{
		if(this._actionController.triggerPopupForRow(event))
		{
			event.preventDefault();
		}
	};

	private _handleKeydown = (event : KeyboardEvent) =>
	{
		if(event.key !== "Enter")
		{
			return;
		}
		if(this._actionController.triggerPopupForRow(event))
		{
			event.preventDefault();
			event.stopPropagation();
		}
	};

	private _handlePointerDown = (event : PointerEvent) =>
	{
		this._actionController.handlePointerDown(event);
	};

	private _handlePointerMove = (event : PointerEvent) =>
	{
		this._actionController.handlePointerMove(event);
	};

	private _cancelLongPress = (_event? : PointerEvent) =>
	{
		this._actionController.cancelLongPress();
	};

	/**
	 * Render the orchestration shell.
	 * We explicitly set `._parent` so Et2Datagrid can participate in Et2Widget array manager lookup.
	 */
	render()
	{
		return html`
				<div part="header"><slot name="header"></slot></div>
				<et2-datagrid
                        part="grid"
					._parent=${this}
					.columns=${this._columns}
					.templateData=${this._templateData}
					.rowCustomizer=${this._customizeDatagridRow}
					.columnPreferenceName=${this.columnPreferenceName}
					.dataProvider=${this._dataProvider}
					.configurationLoading=${this._templateLoading}
					selection-mode="multiple"
				></et2-datagrid>
		`;
	}
}
