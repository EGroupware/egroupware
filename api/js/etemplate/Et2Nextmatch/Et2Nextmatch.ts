import {html, LitElement, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2Datagrid} from "./Et2Datagrid";
import {
	Et2DatagridColumn,
	Et2DatagridRowCustomizeContext,
	Et2DatagridTemplateData,
	Et2DatagridUpdateType,
	Et2DatagridUpdateTypes
} from "./Et2Datagrid.types";
import {Et2RowProvider} from "./Et2RowProvider";
import {Et2NextmatchDataProvider} from "./Et2NextmatchDataProvider";
import {EgwAction} from "../../egw_action/EgwAction";
import {Et2Filterbox} from "../Et2Filterbox/Et2Filterbox";
import {Et2Template} from "../Et2Template/Et2Template";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {Et2NextmatchActionController} from "./Et2NextmatchActionController";
import {
	applyLegacyNextmatchColumnPreferences,
	datagridColumnPreferenceValue,
	type Et2NextmatchResolvedColumn
} from "./Et2NextmatchColumnPreferences";
import "./Headers/Header";
import "./Headers/SortableHeader";
import "./Headers/CustomfieldsHeader";
import {
	ET2_NEXTMATCH_FILTER_EVENT,
	ET2_NEXTMATCH_SORT_EVENT,
	Et2NextmatchFilterEventDetail,
	Et2NextmatchSortEventDetail
} from "./Headers/events";
import styles from "./Et2Nextmatch.styles";
import {et2_IInput} from "../et2_core_interfaces";

/**
 * @summary Nextmatch shows entries with filtering and context menus.
 *
 * Et2Nextmatch renders server-backed, virtualized list data from a named template or slotted row/column templates.
 * It preserves selected legacy nextmatch APIs for app integrations while delegating data loading to Et2Datagrid.
 *
 * @event et2-loading-start - Re-emitted from the inner datagrid when row fetching starts.
 * @event et2-loading-done - Re-emitted from the inner datagrid when all fetches complete.
 * @event et2-loading-error - Re-emitted from the inner datagrid when a fetch fails.
 * @event {CustomEvent<{total: string, nextmatch: Et2Nextmatch}>} et2-search-result - Legacy-compatible event emitted after fetch completion.
 * @event {CustomEvent<{selectedRowIds?: string[], activeRowId?: string, allSelected?: boolean}>} et2-selection-changed - Re-emitted from the inner datagrid when row selection changes.
 * @event {CustomEvent<{columns: Et2DatagridColumn[]}>} et2-columns-changed - Re-emitted from the inner datagrid when columns change.
 * @event {CustomEvent<{oldFilters: Record<string, any>, activeFilters: Record<string, any>, nm: Et2Nextmatch}>} et2-filter - Cancelable event emitted before active filters are applied.
 * @event refresh - Legacy compatibility event emitted after refresh requests are processed.
 *
 * @slot header - Optional content rendered above the datagrid.
 * @slot footer - Optional content rendered below the datagrid.
 * @slot columns - Slotted column definition used to derive datagrid columns when `template` is not set.
 * @slot row - Slotted row template used to render each datagrid row when `template` is not set.
 * @slot loader - Optional slotted loader content shown while rows are loading.
 * @slot noResults - Optional slotted empty-state content replacing default no-results alert.
 *
 * @csspart header - Wrapper for top header slot content rendered above the grid.
 * @csspart grid - Internal `et2-datagrid` element.
 * @csspart footer - Wrapper for bottom slot content rendered below the grid.
 * @cssproperty [--row-height=3em] - Forwarded to internal datagrid row-height estimate.
 * @cssproperty [--row-cell-max-height=10em] - Forwarded to internal datagrid row cell max height.
 * @cssproperty [--meta-column-width=6px] - Width of leading metadata indicator column.
 */
@customElement("et2-nextmatch")
export class Et2Nextmatch extends Et2Widget(LitElement) implements et2_IInput
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

	private static readonly DEFAULT_SETTINGS : Record<string, any> = {action_var: "action"};

	/**
	 * Deduplicates deprecation warnings so each legacy API warns only once per session.
	 */
	private static _deprecationWarnings : Set<string> = new Set();

	/** Initial rows data. Can be set directly or via setRows(). */
	@property({type: Array})
	rows : any[] = [];

	/**
	 * Template name used to resolve columns and row layout.
	 *
	 * This uses a custom accessor instead of Lit's generated setter so template
	 * changes can mark configuration loading synchronously before the next render.
	 * Without that early flag, the child datagrid can render once with no
	 * template data and log a false missing-template warning during initial load.
	 */
	@property({type: String})
	set template(value : string)
	{
		const oldValue = this.template;
		const nextValue = value || "";
		if(nextValue === oldValue)
		{
			return;
		}
		this._template = nextValue;
		this._templateLoading = true;
		this.requestUpdate("template", oldValue);
	}

	get template() : string
	{
		return this._template;
	}

	/** Optional custom preference name for persisted datagrid column settings. */
	@property({type: String, attribute: "column-preference-name"})
	columnPreferenceName : string = "";

	/** Optional filter template source (template name, .xet URL, or template element). */
	@property({attribute: false})
	filterTemplate : string | Et2Template | HTMLElement | null = null;

	/**
	 * Show A-Z letter search controls for filtering by leading character.
	 * Mirrors legacy `lettersearch` nextmatch setting.
	 */
	@property({type: Boolean})
	lettersearch : boolean = false;

	/**
	 * Current active letter search filter value.
	 * `false` / empty means "all letters".
	 */
	@property({attribute: false})
	searchletter : string | false = false;

	/**
	 * Field / column that holds Modified date for entries.
	 * Used for smart refresh.
	 *
	 * @type {string}
	 */
	@property({attribute: false})
	modifiedDateField : string = "";

	/**
	 * Optional override for empty-state headline text.
	 */
	@property({type: String})
	placeholder : string = "";

	/**
	 * Optional list of action ids allowed for placeholder context menu.
	 */
	@property({attribute: false, type: Array})
	placeholderActions : string[] = [];

	/**
	 * Optional list of custom filter attributes that should round-trip through nextmatch fetches.
	 * Mirrors legacy `extra_attributes` setting.
	 */
	@property({attribute: false, type: Array})
	extraAttributes : string[] = [];

	/**
	 * Additional nextmatch settings
	 *
	 * Additional customized settings for applications that can't follow the defaults.
	 * Keep this available for action handlers that still use `nextmatch..settings`,
	 * especially the server-defined action variable used by submit actions.
	 */
	@property({attribute: false, type: Object})
	set settings(value : Record<string, any> | string | null | undefined)
	{
		const oldValue = this.settings;
		const settings = this._settingsObject(value);
		delete settings.rows;
		this._settings = {
			...Et2Nextmatch.DEFAULT_SETTINGS,
			...settings
		};
		this.requestUpdate("settings", oldValue);
	}

	get settings() : Record<string, any>
	{
		return this._settings;
	}

	/**
	 * Prepared row template and metadata currently bound into the datagrid.
	 */
	@state()
	private _templateData : Et2DatagridTemplateData | null = null;

	private _template : string = "";

	/**
	 * True while a named template is being resolved asynchronously.
	 */
	@state()
	private _templateLoading : boolean = true;

	/**
	 * Monotonic token used to ignore stale async template-load completions.
	 */
	private _templateLoadToken : number = 0;

	/**
	 * Template name currently associated with `_templateLoadPromise`.
	 */
	private _templateLoadingName : string | null = null;

	/**
	 * Shared in-flight promise for the current named-template resolution.
	 */
	private _templateLoadPromise : Promise<void> | null = null;

	/**
	 * Builds datagrid row/column configuration from template names or slots.
	 */
	private _rowProvider : Et2RowProvider;

	/**
	 * Translates current filter/template state into datagrid fetch requests.
	 */
	private _dataProvider : Et2NextmatchDataProvider;

	/**
	 * Watches slot content so slot-driven templates can be reparsed on changes.
	 */
	private _slotObserver : MutationObserver | null = null;

	/**
	 * Shared in-flight promise for slot-based template parsing.
	 */
	private _slotApplyInFlight : Promise<void> | null = null;

	/**
	 * Tracks which legacy column-preference keys were already migrated once.
	 */
	private _legacyColumnPreferenceApplied : Set<string> = new Set();

	/**
	 * Active nextmatch filter payload mirrored for legacy integrations and fetches.
	 */
	private _filters : Record<string, any> = {col_filter: {}};

	/**
	 * Lazily created shared filterbox instance attached near the host app shell.
	 */
	private _filterbox : Et2Filterbox | null = null;

	/**
	 * Bridges selection, context actions, and drag/drop into the egw_action system.
	 */
	private _actionController : Et2NextmatchActionController;

	/**
	 * Backing store for the public `settings` property.
	 */
	private _settings : Record<string, any> = {...Et2Nextmatch.DEFAULT_SETTINGS};

	/**
	 * Resolve the internal datagrid instance from shadow DOM.
	 * This is centralized so future render structure changes only need one update.
	 */
	private get _datagrid() : Et2Datagrid | null
	{
		return this.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid | null;
	}

	/**
	 * Current column definitions passed through to the datagrid.
	 * Before a real template is parsed this can come from a placeholder templateData.
	 */
	private get _currentColumns() : Et2DatagridColumn[]
	{
		return this._templateData?.columns || [];
	}

	constructor()
	{
		super();
		// Keep a runtime reference so module import stays
		void Et2Datagrid;
		if(!this._filters.col_filter || typeof this._filters.col_filter !== "object")
		{
			this._filters.col_filter = {};
		}
		/**
		 * Build helper collaborators once.
		 * They are stateful and reused for the lifetime of the component.
		 */
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
		this.addEventListener("contextmenu", this._handleContextMenu as EventListener, true);
		this.addEventListener("dblclick", this._handleDoubleClick as EventListener, true);
		this.addEventListener("keydown", this._handleKeydown as EventListener);
		this.addEventListener("pointerdown", this._handlePointerDown as EventListener);
		this.addEventListener("pointermove", this._handlePointerMove as EventListener);
		this.addEventListener("pointerup", this._cancelLongPress as EventListener);
		this.addEventListener("pointercancel", this._cancelLongPress as EventListener);
		this.addEventListener("dragend", this._cancelLongPress as EventListener, true);
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
		this.removeEventListener("contextmenu", this._handleContextMenu as EventListener, true);
		this.removeEventListener("dblclick", this._handleDoubleClick as EventListener, true);
		this.removeEventListener("keydown", this._handleKeydown as EventListener);
		this.removeEventListener("pointerdown", this._handlePointerDown as EventListener);
		this.removeEventListener("pointermove", this._handlePointerMove as EventListener);
		this.removeEventListener("pointerup", this._cancelLongPress as EventListener);
		this.removeEventListener("pointercancel", this._cancelLongPress as EventListener);
		this.removeEventListener("dragend", this._cancelLongPress as EventListener, true);
		this._actionController.destroy();
		super.disconnectedCallback();
	}

	transformAttributes(attrs)
	{
		// Process 'settings' into properties
		// We're before namespace creation here, so use attrs.id
		const attrSettings = this._settingsObject(attrs.settings);
		const settings = this._settingsObject(this.getArrayMgr("content").getEntry(attrs.id || 'nm'));
		const mergedSettings = {
			...settings,
			...attrSettings
		};
		if(Object.keys(mergedSettings).length > 0)
		{
			const retainedSettings = {...mergedSettings};
			// Rows and actions are initialized through their own attributes.
			delete retainedSettings.rows;
			delete retainedSettings.actions;
			attrs.settings = retainedSettings;
			Object.assign(attrs, settings);
		}
		// Normalize legacy snake_case settings to modern Et2Nextmatch properties.
		for(const [modernKey, legacyKey] of [
			["placeholderActions", "placeholder_actions"],
			["extraAttributes", "extra_attributes"]
		] as const)
		{
			const value = attrs[modernKey] ?? attrs[legacyKey];
			if(typeof value !== "undefined")
			{
				attrs[modernKey] = this._toStringArray(value);
				delete attrs[legacyKey];
			}
		}
		if(typeof attrs.searchletter !== "undefined")
		{
			attrs.searchletter = attrs.searchletter || false;
		}
		if(typeof attrs.lettersearch !== "undefined")
		{
			attrs.lettersearch = !!attrs.lettersearch;
		}
		const rowsSource = typeof attrs.rows !== "undefined" ? attrs.rows : this.getAttribute("rows");
		if(typeof rowsSource === "string")
		{
			try
			{
				attrs.rows = JSON.parse(rowsSource);
			}
			catch(e)
			{
			}
		}
		super.transformAttributes(attrs);
	}

	private _settingsObject(value : Record<string, any> | string | null | undefined) : Record<string, any>
	{
		return value && typeof value === "object" && !Array.isArray(value) ? {...value} : {};
	}

	protected _initActions(actions : EgwAction[] | { [id : string] : object })
	{
		this._actionController.initActions(actions);
	}

	/**
	 * Initialize the widget from attributes/template and trigger first load.
	 * We prefer showing provided rows immediately to keep first paint fast.
	 */
	async firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);
		this._actionController.setPlaceholderActions(this.placeholderActions);
		this._initializeExtraAttributeFilters();

		if(this.template)
		{
			await this._applyTemplateFromName(this.template);
		}
		else
		{
			await this._applyTemplateFromSlots();
		}
		this._initializeSettingsSort();

		if(this.rows.length)
		{
			this._datagrid?.setInitialRows(this.rows);
			// No need to keep them
			this.rows = [];
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
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);
		if(changedProperties.has("searchletter"))
		{
			const nextValue = this.searchletter || false;
			if(this._filters.searchletter !== nextValue)
			{
				this._filters.searchletter = nextValue;
			}
		}
	}

	/**
	 * React to template changes after initial render.
	 * Template source is mutually exclusive: explicit template name wins over slots.
	 */
	updated(changedProperties : PropertyValues)
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
		if(changedProperties.has("placeholderActions"))
		{
			this._actionController.setPlaceholderActions(this.placeholderActions);
		}
		this._actionController.syncDragDropRegistration();
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
	 * Return the current selection tracked by the action controller.
	 */
	getSelection() : { ids : string[]; all : boolean }
	{
		return this._actionController.getSelection();
	}

	/**
	 * Execute a registered nextmatch action against the supplied or current selection.
	 */
	executeAction(
		actionId : string,
		selection : { ids? : string[]; all? : boolean } = this.getSelection(),
		options? : { nmAction? : string }
	) : boolean
	{
		return this._actionController.executeAction(actionId, selection, options);
	}

	/**
	 * Fetch every id matching the current filter, showing a cancelable wait dialog.
	 */
	async fetchAllIds(pageSize : number = 200) : Promise<string[]>
	{
		const ids : string[] = [];
		let start = 0;
		let total : number | null = null;
		let cancelled = false;
		const dialog = Et2Dialog.show_dialog(
			() =>
			{
				cancelled = true;
			},
			this.egw().lang("Loading"),
			this.egw().lang("please wait..."),
			{},
			[{
				"button_id": Et2Dialog.CANCEL_BUTTON,
				label: this.egw().lang("Cancel"),
				id: "dialog[cancel]",
				image: "cancel"
			}],
			Et2Dialog.INFORMATION_MESSAGE,
			undefined,
			this.egw()
		);
		try
		{
			do
			{
				if(cancelled)
				{
					throw new DOMException("Canceled", "AbortError");
				}
				const page = await this._dataProvider.fetchPage(start, pageSize);
				if(cancelled)
				{
					throw new DOMException("Canceled", "AbortError");
				}
				ids.push(...page.rows.map((row) => this._dataProvider.toProviderRowId(row.id)));
				total = typeof page.total === "number" ? page.total : ids.length;
				start += pageSize;
			}
			while(ids.length < total);
			return Array.from(new Set(ids));
		}
		finally
		{
			dialog.destroy();
		}
	}

	selectSingleRow(rowId : string)
	{
		this._datagrid?.selectSingleRow(rowId);
	}

	selectAllRows()
	{
		this._datagrid?.selectAllRows();
	}

	/**
	 * Open the column selection dialog from outside the datagrid header.
	 */
	async openColumnSelection(event? : Event) : Promise<void>
	{
		event?.preventDefault();
		await this.updateComplete;
		await this._datagrid?.openColumnSelection(event);
	}

	/**
	 * Public API to override visible columns programmatically.
	 * Accepts legacy string arrays and normalizes them for datagrid consumption.
	 */
	setColumns(columns : Array<string | { key : string; title : string }>)
	{
		const nextColumns = (columns || []).map((column, index) =>
			typeof column === "string" ? {key: "col" + index, title: column} : column
		);
		this._templateData = this._templateData
			? {
				...this._templateData,
				columns: nextColumns
			}
			: {
				rowTemplate: null,
				rowTemplateXml: null,
				rowTemplateAttrMap: {},
				loaderTemplate: null,
				columns: nextColumns
			};
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
	 * Nextmatch value used by submits, favourites, and app state.
	 */
	get value() : Record<string, any>
	{
		const value = {
			...this._filters
		};
		const selectcols = this._currentColumns
			.filter((column) => !column.hidden)
			.map((column) => String(column.key || ""))
			.filter(Boolean);
		if(selectcols.length)
		{
			value["selectcols"] = selectcols;
		}
		return {
			...value,
			...(this._actionController.getActionSubmitValue() || {})
		};
	}

	/**
	 * et2_IInput implementation used by eTemplate submit value collection.
	 */
	getValue() : Record<string, any>
	{
		return this.value;
	}

	isDirty() : boolean
	{
		return false;
	}

	resetDirty()
	{
	}

	isValid() : boolean
	{
		return true;
	}

	/**
	 * Legacy visible-column setter used by favorites and app state restore.
	 * @deprecated Use `setColumns()` instead.
	 */
	set_columns(column_list : string[], _trigger_update = false)
	{
		this._warnDeprecatedOnce("set_columns", "Et2Nextmatch.set_columns() is deprecated, use `setColumns()` instead");
		const requestedColumns = this._toStringArray(column_list);
		if(!requestedColumns.length)
		{
			return;
		}
		const currentColumns = this._currentColumns;
		if(!currentColumns.length)
		{
			return;
		}
		const requestedKeys = new Set(requestedColumns);
		const nextColumns = currentColumns.map((column) => ({
			...column,
			hidden: !requestedKeys.has(String(column.key || ""))
		}));
		this.setColumns(nextColumns);
	}

	/**
	 * Refresh given rows for specified change
	 *
	 * Change type parameters allows for quicker refresh then complete server side reload:
	 * - update: request modified data from given rows.  May be moved.
	 * - update-in-place: update row, but do NOT move it, or refresh if uid does not exist
	 * - edit: rows changed, but sorting may be affected.  Full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: put the new row in at the top, unless app says otherwise
	 *
	 * What actually happens also depends on a general preference "lazy-update":
	 *	default/lazy:
	 *  - add always on top
	 *	- updates on top, if sorted by last modified, otherwise update-in-place
	 *	- update-in-place is always in place!
	 *
	 *	exact:
	 *	- add and update on top if sorted by last modified, otherwise full refresh
	 *	- update-in-place is always in place!
	 *
	 * Nextmatch checks the application callback nm_refresh_index, which has a default implementation
	 * in egw_app.nm_refresh_index().
	 *
	 * @param {string[]|string} _row_ids rows to refresh
	 * @param {?string} _type "update-in-place", "update", "edit", "delete" or "add"
	 *
	 * @see jsapi.egw_refresh()
	 * @see egw_app.nm_refresh_index()
	 * @fires refresh
	 */
	refresh(_row_ids : string[] | string, _type? : Et2DatagridUpdateType)
	{
		// Framework trying to refresh, but nextmatch not fully initialized
		if(!this._datagrid || !this._dataProvider)
		{
			return;
		}
		// No specific rows, just refresh everything
		if(typeof _row_ids == "undefined" || _row_ids === null)
		{
			// applyFilters() will dispatch events as appropriate
			this.applyFilters();

			// Trigger an event so Mail app code can act on it - only Mail listens for this
			this.dispatchEvent(new CustomEvent("refresh", {
				composed: true,
				bubbles: true,
			}));

			return;
		}

		// Make sure we're dealing with arrays
		_row_ids = this._toStringArray(_row_ids);

		// Make some changes in what we're doing based on preference
		let update_pref = this.egw().preference("lazy-update") || 'lazy';
		if(_type == Et2DatagridUpdateTypes.UPDATE && !this._isSortedByModified())
		{
			_type = update_pref == "lazy" ? Et2DatagridUpdateTypes.UPDATE_IN_PLACE : Et2DatagridUpdateTypes.EDIT;
		}
		else if(update_pref == "exact" && _type == Et2DatagridUpdateTypes.ADD && !this._isSortedByModified())
		{
			_type = Et2DatagridUpdateTypes.EDIT;
		}
		if(_type == Et2DatagridUpdateTypes.ADD && !(update_pref == "lazy" || update_pref == "exact" && this._isSortedByModified()))
		{
			_type = Et2DatagridUpdateTypes.EDIT;
		}
		if(typeof _type == 'undefined')
		{
			_type = Et2DatagridUpdateTypes.EDIT;
		}
		if(update_pref == "exact" && !this._isSortedByModified())
		{
			_type = Et2DatagridUpdateTypes.EDIT;
		}

		this._datagrid.refresh(_row_ids, _type).then(() =>
		{
			// Trigger an event so Mail app code can act on it - only Mail listens for this
			this.dispatchEvent(new CustomEvent("refresh", {
				composed: true,
				bubbles: true
			}));
		});
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
	 * Active filter snapshot used by header/filter integrations and action URL expansion.
	 * Use applyFilters() to update filter state.
	 */
	get activeFilters() : Record<string, any>
	{
		return this._filterSnapshot(this._filters);
	}

	private _filterSnapshot(filters : Record<string, any>) : Record<string, any>
	{
		return Object.entries(filters || {}).reduce((snapshot, [key, value]) =>
		{
			snapshot[key] = this._filterSnapshotValue(value);
			return snapshot;
		}, {} as Record<string, any>);
	}

	private _filterSnapshotValue(value : any) : any
	{
		if(Array.isArray(value))
		{
			return value.map((entry) => this._filterSnapshotValue(entry));
		}
		if(value && typeof value === "object" && Object.getPrototypeOf(value) === Object.prototype)
		{
			return this._filterSnapshot(value);
		}
		return value;
	}

	/**
	 * Legacy-compatible filter application entry point.
	 * Merges updates into `activeFilters`, emits cancelable `et2-filter`, and reloads rows by default.
	 */
	applyFilters(set? : Record<string, any>, options? : { reload? : boolean })
	{
		let changed = typeof set == "undefined";
		if(!this._filters || typeof this._filters !== "object")
		{
			this._filters = {col_filter: {}};
		}
		if(!this._filters.col_filter || typeof this._filters.col_filter !== "object")
		{
			this._filters.col_filter = {};
		}
		if(typeof set !== "undefined" && typeof set === "object" && Object.keys(set).length === 0)
		{
			this._filters = {col_filter: {}};
			changed = true;
		}
		const activeFilters = this._filters;
		const previousFilters = {
			...activeFilters,
			col_filter: {...(activeFilters.col_filter || {})},
			sort: activeFilters.sort ? {...activeFilters.sort} : undefined
		};

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
					if(key === "searchletter")
					{
						this.searchletter = set[key] || false;
					}
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
	 * Seed additional custom attributes into active filters to preserve legacy request payloads.
	 */
	private _initializeExtraAttributeFilters()
	{
		for(const attribute of this.extraAttributes || [])
		{
			if(!attribute || typeof this._filters[attribute] !== "undefined")
			{
				continue;
			}
			const value = (this as any)[attribute];
			if(typeof value !== "undefined")
			{
				this._filters[attribute] = value;
			}
		}
		if(this.searchletter)
		{
			this._filters.searchletter = this.searchletter;
		}
	}

	/**
	 * Seed the default sort settings into active filters for the first load
	 * and header state reflection.
	 */
	private _initializeSettingsSort()
	{
		if(this._filters.sort || !this.settings.order || !this.settings.sort)
		{
			return;
		}
		this.sortBy(
			String(this.settings.order),
			String(this.settings.sort).toUpperCase() === "ASC",
			false
		);
	}

	/**
	 * Normalize CSV/array/string values into a compact array of string ids.
	 */
	private _toStringArray(value : unknown) : string[]
	{
		if(Array.isArray(value))
		{
			return value.map((item) => String(item || "").trim()).filter(Boolean);
		}
		if(typeof value === "string")
		{
			return value.split(",").map((item) => item.trim()).filter(Boolean);
		}
		return [];
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
	 * Change the row template
	 * @param {string} template_name
	 * @deprecated Set `.template` instead, wait for updateComplete
	 */
	set_template(template_name : string)
	{
		this.template = template_name;
		this._warnDeprecatedOnce("set_template", "Et2Nextmatch.set_template is deprecated, use `nm.template='...'`");
		return this.updateComplete;
	}

	private _warnDeprecatedOnce(method : string, message : string)
	{
		if(Et2Nextmatch._deprecationWarnings.has(method))
		{
			return;
		}
		Et2Nextmatch._deprecationWarnings.add(method);
		console.warn(message);
	}

	/**
	 * Watch slot mutations and re-resolve template data when no explicit template name is set.
	 * We observe child-list changes because slotted template content can be added dynamically.
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
				if(["header", "columns", "row", "loader", "noResults"].includes(slotName))
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
		const columns = templateData.columns?.length ? templateData.columns : this._currentColumns;
		const nextColumns = this._applyLegacyNextmatchColumnPreferences(columns || [], templateData);
		this._templateData = {
			...templateData,
			sourceColumns: templateData.sourceColumns?.length ? templateData.sourceColumns : columns,
			columns: nextColumns
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
	 *
	 * TODO: When things stabilize, we can delete the old preferences
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

		const hasLegacyVisibility = typeof storedVisibility === "string" &&
			!storedVisibility.trim().startsWith("[") &&
			!storedVisibility.trim().startsWith("{") &&
			!!storedVisibility.trim();
		const hasLegacySizes = (typeof storedSizes === "string" && !!storedSizes.trim()) ||
			(!!storedSizes && typeof storedSizes === "object" && !!Object.keys(storedSizes).length);
		if(!hasLegacyVisibility && !hasLegacySizes)
		{
			return columns;
		}

		const nextColumns = applyLegacyNextmatchColumnPreferences(columns, storedVisibility, storedSizes);
		this._seedDatagridColumnPreferencesFromLegacy(rowTemplateId, app, nextColumns);

		return nextColumns.map((column) =>
		{
			if(!column.customFields)
			{
				return column;
			}
			const {customFields: _customFields, ...datagridColumn} = column;
			return datagridColumn;
		});
	}

	/**
	 * Store legacy Nextmatch preferences in Datagrid's structured preference
	 * shape, so Datagrid receives a single resolved source of truth.
	 */
	private _seedDatagridColumnPreferencesFromLegacy(
		rowTemplateId : string,
		app : string,
		columns : Et2NextmatchResolvedColumn[]
	)
	{
		const key = String(this.columnPreferenceName || "").trim() || `nextmatch-${rowTemplateId}-prefs`;
		const value = datagridColumnPreferenceValue(columns);
		try
		{
			this.egw().set_preference(app, key, value);
		}
		catch(e)
		{
		}
	}

	/**
	 * Is this nextmatch currently sorted by "modified" date
	 *
	 * This is decided by the row_modified options passed from the server and the current sort order
	 */
	private _isSortedByModified()
	{
		let sort = this._filters.sort || "";
		return sort?.id && sort.id == this.modifiedDateField && sort.asc == false;
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
		this._actionController.customizeRowElement(context.rowElement);
	};

	/**
	 * Keep slotted sort headers in sync with currently active sort filter.
	 */
	private _updateSortHeaderState()
	{
		const sort = this._filters.sort;
		const mode = sort?.asc === true ? "asc" : sort?.asc === false ? "desc" : "none";
		const roots = [this.shadowRoot, this._datagrid?.shadowRoot].filter(Boolean) as ShadowRoot[];
		const sortHeaders = roots.reduce((headers, root) =>
			headers.concat(Array.from(root.querySelectorAll("et2-nextmatch-sortheader"))), [] as Element[]
		);
		sortHeaders.forEach((header : any) =>
		{
			const headerId = String(header.id || header.getAttribute?.("id") || "");
			const headerMode = sort?.id && headerId === sort.id ? mode : "none";
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
	_getAppName() : string
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
			if(detail.clear)
			{
				if(detail.update === false)
				{
					this._filters.sort = undefined;
					this._updateSortHeaderState();
				}
				else
				{
					this.resetSort();
				}
			}
			else
			{
				this.sortBy(detail.id, detail.asc, detail.update);
			}
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
		this._actionController.syncDragDropRegistration();
	};

	private _handleSelectionChanged = (event : CustomEvent<{ selectedRowIds? : string[]; activeRowId? : string; allSelected? : boolean }>) =>
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
		// Developer abort context menu
		if(event.ctrlKey)
		{
			return;
		}
		const placeholderStateElement = this._getPlaceholderStateElement(event);
		const rowElement = this._getContextMenuRowElement(event);
		if(!placeholderStateElement && !rowElement)
		{
			return;
		}
		// Capture-phase intercept to ensure exactly one popup flow runs.
		event.preventDefault();
		event.stopPropagation();
		if(placeholderStateElement)
		{
			const configuredActions = this._actionController.getPlaceholderActionIds();
			if(this._actionController.triggerPlaceholderPopup(event, configuredActions, placeholderStateElement))
			{
				return;
			}
		}
		if(this._actionController.triggerPopupForRow(event))
		{
			return;
		}
	};

	private _handleDoubleClick = (event : MouseEvent) =>
	{
		if(event.defaultPrevented || event.button !== 0)
		{
			return;
		}
		if(this._isInteractiveRowEventTarget(event))
		{
			return;
		}
		const rowElement = this._getContextMenuRowElement(event);
		if(!rowElement)
		{
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		window.getSelection?.()?.removeAllRanges?.();
		this._actionController.triggerDefaultActionForRow(event);
	};

	/**
	 * Resolve row element for context-menu event from composed path.
	 */
	private _getContextMenuRowElement(event : MouseEvent) : HTMLElement | null
	{
		const path = event.composedPath?.() || [];
		for(const node of path)
		{
			if(node instanceof HTMLElement && node.closest?.("[data-row-id]"))
			{
				return node.closest("[data-row-id]") as HTMLElement;
			}
		}
		return null;
	}

	private _isInteractiveRowEventTarget(event : MouseEvent) : boolean
	{
		const rowElement = this._getContextMenuRowElement(event);
		if(!rowElement)
		{
			return false;
		}
		const interactiveSelector = [
			"a[href]",
			"[role='link']",
			".et2_clickable"
		].join(",");
		const path = event.composedPath?.() || [];
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
	 * True when interaction target belongs to datagrid empty-state placeholder container.
	 */
	private _getPlaceholderStateElement(event : MouseEvent) : HTMLElement | null
	{
		const path = event.composedPath?.() || [];
		for(const node of path)
		{
			if(node instanceof HTMLElement && node.classList.contains("dg-state"))
			{
				return node;
			}
		}
		return null;
	}

	/**
	 * Render A-Z letter controls when explicitly enabled or when a letter filter is currently active.
	 */
	private _renderLetterSearch()
	{
		const currentLetterValue = this._filters.searchletter || this.searchletter || "";
		const currentLetter = typeof currentLetterValue === "string" ? currentLetterValue : "";
		if(!this.lettersearch && !currentLetter)
		{
			return null;
		}
		const letters = this.egw().lang("ABCDEFGHIJKLMNOPQRSTUVWXYZ").split("");
		return html`
            <div class="nextmatch_lettersearch">
                ${letters.map((letter) => html`
                    <button
                            type="button"
                            class=${`lettersearch ${letter === currentLetter ? "lettersearch_active" : ""}`}
                            @click=${() => this.applyFilters({searchletter: letter || false})}
                    >${letter}
                    </button>
                `)}
                <button
                        type="button"
                        class=${`lettersearch ${!currentLetter ? "lettersearch_active" : ""}`}
                        @click=${() => this.applyFilters({searchletter: false})}
                >${this.egw().lang("all")}
                </button>
            </div>
		`;
	}

	/**
	 * Render Nextmatch default no-results template into datagrid's `noResults` slot.
	 */
	private _renderDefaultNoResults(actions : EgwAction[])
	{
		return html`
            <sl-alert slot="noResults" variant="neutral" open>
                <sl-icon slot="icon" name="inbox"></sl-icon>
                <strong>${this.placeholder || this.egw().lang("No entries to display")}</strong>
                ${actions.length > 0 ? html`
                    <div class="nextmatch_placeholder_actions">
                        ${actions.map((action) => html`
                            <et2-button
                                    class="nextmatch_placeholder_action"
                                    noSubmit
                                    .image=${action.iconUrl || action.id}
                                    .label=${action.caption || action.id}
                                    @click=${(event : MouseEvent) => this._handlePlaceholderActionClick(event, String(action.id))}
                            ></et2-button>
                        `)}
                    </div>
                ` : null}
            </sl-alert>
		`;
	}

	/**
	 * Execute one placeholder action from inline loader-slot buttons.
	 */
	private _handlePlaceholderActionClick(event : MouseEvent, actionId : string)
	{
		if(!actionId)
		{
			return;
		}
		const stateElement = (event.currentTarget as HTMLElement | null)?.closest(".dg-state") as HTMLElement | null;
		if(this._actionController.executePlaceholderAction(actionId, stateElement || this))
		{
			event.preventDefault();
			event.stopPropagation();
		}
	}

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

	/**
	 * Reset any pending touch long-press state and clear rows that were armed for drag.
	 */
	private _cancelLongPress = (_event? : Event) =>
	{
		this._actionController.clearPreparedDragRow();
		this._actionController.cancelLongPress();
	};

	/**
	 * Render the orchestration shell.
	 * We explicitly set `._parent` so Et2Datagrid can participate in Et2Widget array manager lookup.
	 */
	render()
	{
		const hasSlottedNoResults = !!this.querySelector("[slot='noResults']");
		const inlinePlaceholderActions : EgwAction[] = this._actionController
			.getInlinePlaceholderActions();
		return html`
				<div part="header"><slot name="header"></slot></div>
                ${this._renderLetterSearch()}
				<et2-datagrid
                        part="grid"
					._parent=${this}
					.columns=${this._currentColumns}
					.templateData=${this._templateData}
					.rowCustomizer=${this._customizeDatagridRow}
					.columnPreferenceName=${this.columnPreferenceName}
					.dataProvider=${this._dataProvider}
					.configurationLoading=${this._templateLoading}
                        .emptyStateText=${this.placeholder}
					selection-mode="multiple"
                >
                    ${hasSlottedNoResults
                      ? html`
                                <slot name="noResults" slot="noResults"></slot>`
                      : this._renderDefaultNoResults(inlinePlaceholderActions)}
                </et2-datagrid>
                <div part="footer">
                    <slot name="footer"></slot>
                </div>
		`;
	}
}
