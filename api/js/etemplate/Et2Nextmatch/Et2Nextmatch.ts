import {html, LitElement, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import {loadStylesheet} from "../Et2Widget/cssTools";
import {Et2Datagrid} from "./Et2Datagrid";
import {
	Et2DatagridColumn,
	Et2DatagridDataProvider,
	Et2DatagridExpandedRowContext,
	Et2DatagridExpansionConfig,
	Et2DatagridRowCustomizeContext,
	Et2DatagridTemplateData,
	Et2DatagridUpdateType,
	Et2DatagridUpdateTypes,
	Et2DatagridView
} from "./Et2Datagrid.types";
import type {Et2DatagridColumnSelectionItem} from "./Et2DatagridColumnState";
import {Et2RowProvider} from "./Et2RowProvider";
import {Et2NextmatchDataProvider} from "./Et2NextmatchDataProvider";
import {EgwAction} from "../../egw_action/EgwAction";
import {Et2Filterbox} from "../Et2Filterbox/Et2Filterbox";
import {Et2Template} from "../Et2Template/Et2Template";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {Et2NextmatchActionController} from "./Et2NextmatchActionController";
import {Et2VfsUpload} from "../Et2Vfs/Et2VfsUpload";
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
import rowStyles from "./Et2Nextmatch.row.styles";
import {et2_IInput} from "../et2_core_interfaces";
import {styleMap} from "lit/directives/style-map.js";
import {ref} from "lit/directives/ref.js";

const LETTERSEARCH_SELECTION_ID = "~search_letter~";

/**
 * @summary Nextmatch shows entries with filtering and context menus.
 *
 * Et2Nextmatch uses Et2Datagrid to show application entries using a row template.
 * Rows must be read-only, we do not allow inputs in the rows.
 *
 * @event et2-loading-start - Re-emitted from the inner datagrid when row fetching starts.
 * @event et2-loading-done - Re-emitted from the inner datagrid when all fetches complete.
 * @event et2-loading-error - Re-emitted from the inner datagrid when a fetch fails.
 * @event {CustomEvent<{total: string, nextmatch: Et2Nextmatch}>} et2-search-result - Legacy-compatible event emitted after fetch completion.
 * @event {CustomEvent<{selectedRowIds?: string[], activeRowId?: string, allSelected?: boolean, replaceSelection?: boolean}>} et2-selection-changed - Re-emitted from the inner datagrid when row selection changes.
 * @event {CustomEvent<{activeRowId?: string, activeRowIndex?: number}>} et2-active-row-changed - Re-emitted from the inner datagrid when active row focus changes.
 * @event {CustomEvent<{columns: Et2DatagridColumn[]}>} et2-columns-changed - Re-emitted from the inner datagrid when columns change.
 * @event {CustomEvent<{oldFilters: Record<string, any>, activeFilters: Record<string, any>, nm: Et2Nextmatch}>} et2-filter - Cancelable event emitted before active filters are applied.
 * @event {CustomEvent<{rowUid: string, files: File[]}>} et2-filedrop - Native OS file drop onto a row (or empty area). `rowUid` is "" when dropped outside any row. Cancelable: call `event.preventDefault()` in a listener to suppress the framework default (upload + link into the row's VFS link dir) and handle it yourself (e.g. filemanager uploads into the row's folder).
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
 * @csspart subgrid - Expanded child `et2-datagrid` rendered for expandable rows.
 * @csspart footer - Wrapper for bottom slot content rendered below the grid.
 * @cssproperty [--row-height=3em] - Forwarded to internal datagrid row-height estimate.
 * @cssproperty [--row-cell-max-height=10em] - Forwarded to internal datagrid row cell max height.
 * @cssproperty [--meta-column-width=max(var(--sl-spacing-large), 6px)] - Width of leading metadata indicator/expander column.
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

	/**
	 * Defaults for settings that nextmatch expects even when the server does not provide them.
	 */
	private static readonly DEFAULT_SETTINGS : Record<string, any> = {action_var: "action"};

	/**
	 * Content from `$content[nm]` contains both nextmatch configuration
	 * and arbitrary app state. Only keep documented nextmatch settings here so
	 * `settings` remains useful for action/filter behaviour without retaining
	 * unrelated content payloads. Active fetch state like `col_filter` and
	 * `searchletter` is intentionally omitted and normalized into `_filters`.
	 */
	private static readonly ALLOWED_SETTINGS : Set<string> = new Set([
		"action_var",
		"actions",
		"columnselection_pref",
		"columns_forced",
		"dataStorePrefix",
		"default_cols",
		"extra_attributes",
		"filter_template",
		"is_parent",
		"is_parent_value",
		"lettersearch",
		"no_columnselection",
		"order",
		"parent_id",
		"placeholder",
		"placeholder_actions",
		"return",
		"row_id",
		"row_modified",
		"rows",
		"search",
		"select_all",
		"selectcols",
		"selected",
		"sort",
		"start",
		"template",
		"total",
		"view"
	]);

	/**
	 * Legacy filter controls use patterned keys such as `filter_label`,
	 * `filter2_no_lang`, and `cat_id_placeholder`. Allow these suffixes for
	 * `filter`, `filter2`, `cat`, and `cat_id` while still rejecting unknown
	 * app-specific content keys.
	 */
	private static readonly ALLOWED_SETTING_SUFFIXES : Set<string> = new Set([
		"aria_label",
		"help",
		"label",
		"no_lang",
		"onchange",
		"placeholder",
		"statustext",
		"widget"
	]);

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

	private _view : Et2DatagridView = "row";

	/**
	 * Visual layout mode for the inner datagrid. Row is the default.
	 */
	@property({type: String, reflect: true})
	set view(value : Et2DatagridView | string)
	{
		const oldValue = this._view;
		const nextValue = this._normalizeView(value);
		if(nextValue === oldValue)
		{
			return;
		}
		this._view = nextValue;
		this.requestUpdate("view", oldValue);
	}

	get view() : Et2DatagridView
	{
		return this._view;
	}

	/** Optional filter template source (template name, .xet URL, or template element). */
	@property({attribute: false})
	filterTemplate : string | Et2Template | HTMLElement | null = null;

	/**
	 * Show A-Z letter search controls for filtering by leading character.
	 * Users can still turn it off in column selection preferences
	 */
	@property({type: Boolean})
	lettersearch : boolean = false;

	/**
	 * Actual letter-search bar visibility. `lettersearch` remains the settings
	 * capability flag, while this tracks the user-controlled chooser state.
	 */
	private _lettersearchVisible : boolean = true;

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
		if(typeof settings.view !== "undefined")
		{
			settings.view = this._normalizeView(settings.view);
			this.view = settings.view;
			this._filters.view = settings.view;
		}
		if(typeof settings.col_filter !== "undefined")
		{
			this._setColFilterFilter(settings.col_filter);
			delete settings.col_filter;
		}
		if(typeof settings.searchletter !== "undefined")
		{
			this._setSearchletterFilter(settings.searchletter);
			delete settings.searchletter;
		}
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

	@state()
	private _rowStylesheets : CSSStyleSheet[] = [rowStyles.styleSheet!];

	@state()
	private _hasPlaceholderActions : boolean = false;

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
	/** Lazily-created child providers keyed by normalized parent row id. */
	private _childDataProviders : Map<string, Et2DatagridDataProvider> = new Map();
	/** Tracks expanded subgrids that have already triggered their initial load. */
	private _initializedSubgrids : WeakSet<Et2Datagrid> = new WeakSet();
	/** Controlled expansion state shared with the root datagrid. */
	private _expandedRowIds : Set<string> = new Set();
	/** Selection state per parent/child grid, merged for legacy action handling. */
	private _selectionByGridId : Map<string, { selectedRowIds : string[]; allSelected : boolean }> = new Map();
	/** Current datagrid column state after user preference/resizing changes. */
	private _datagridColumns : Et2DatagridColumn[] | null = null;
	/**
	 * Resolves once template columns are first derived (in _applyTemplateData),
	 * so consumers can await columns without polling.  Deliberately NOT tied to
	 * updateComplete: gating Lit readiness on this stalls etemplate2's load.
	 */
	private _resolveColumnsReady : () => void = () => {};
	private _columnsReady : Promise<void> = new Promise((resolve) => { this._resolveColumnsReady = resolve; });
	/** Frozen column snapshots for already-open child grids. */
	private _subgridColumnSnapshots : Map<string, {
		columns : Et2DatagridColumn[];
	}> = new Map();

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
	/** Visible column keys requested before template columns are available. */
	private _pendingVisibleColumnKeys : string[] | null = null;

	/**
	 * Active nextmatch filter payload for fetching data.
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
	 * Row element currently highlighted as a native file drop target, so we can
	 * move/clear the highlight without thrashing during dragover.
	 */
	private _dropHoverRow : HTMLElement | null = null;

	/**
	 * Count of in-flight OS-file dragenter/leave pairs.  Used to tell an OS
	 * file drag from an action-framework drag, and to keep the highlight while
	 * moving across child elements.  `dataTransfer.files` is empty during
	 * dragover (only populated on drop), so we detect via the `Files` type.
	 */
	private _osFileDragDepth = 0;

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
		return this._datagridColumns || this._templateData?.columns || [];
	}

	/**
	 * Resolves once the template columns have been derived, so consumers (e.g.
	 * filemanager tile view) can await visible columns without polling:
	 *     await nm.whenColumnsReady(); // getValue().selectcols is now populated
	 * This is a side-channel promise, independent of updateComplete, so awaiting
	 * it never blocks or stalls etemplate2's load.
	 */
	public whenColumnsReady() : Promise<void>
	{
		return this._columnsReady;
	}

	/**
	 * Native OS file drops onto a row are surfaced as the `et2-filedrop` event
	 * (`detail: { rowUid: string; files: File[] }`), dispatched on this element
	 * with `bubbles`/`composed` so apps can listen on the widget's DOM node.
	 * `rowUid` is "" when the drop landed outside any row, so the listener can
	 * fall back to the current directory.  Apps do not need to call a setter — a
	 * standard addEventListener is enough, matching the rest of the nextmatch event API.
	 */

	/**
	 * True when the drag carries OS files (as opposed to an action-framework
	 * row drag).  Note `dataTransfer.files` is empty during dragover — only the
	 * `Files` type is exposed — so we test the types, not the file list.
	 */
	private _isOsFileDrag(event : DragEvent) : boolean
	{
		return !!event.dataTransfer?.types?.includes?.("Files");
	}

	/**
	 * Allow a drop only for OS file drags; action-framework drags (no files)
	 * are left to the action system untouched.  Highlights the row under the
	 * pointer as the drop target.  Must run in the capture phase so the action
	 * controller does not swallow the drag first.
	 */
	private _handleFileDragOver = (event : DragEvent) : void =>
	{
		if(!this._isOsFileDrag(event)) return;
		// preventDefault() is what makes the browser accept the drop.
		event.preventDefault();
		const row = this._findDropRowElement(event);
		if(row && row !== this._dropHoverRow)
		{
			this._clearDropHover();
			row.classList.add("drop-hover");
			this._dropHoverRow = row;
		}
	};

	/**
	 * Track OS-file dragenter so we can clear the highlight when the drag
	 * truly leaves the nextmatch (not just moves between child elements).
	 */
	private _handleFileDragEnter = (event : DragEvent) : void =>
	{
		if(this._isOsFileDrag(event)) this._osFileDragDepth++;
	};

	/**
	 * Clear the drop-target highlight when the OS-file drag leaves the
	 * nextmatch entirely (dragleave fires on every child boundary, so we count
	 * enter/leave pairs).
	 */
	private _handleFileDragLeave = (event : DragEvent) : void =>
	{
		if(!this._isOsFileDrag(event)) return;
		this._osFileDragDepth = Math.max(0, this._osFileDragDepth - 1);
		if(this._osFileDragDepth === 0) this._clearDropHover();
	};

	private _clearDropHover() : void
	{
		this._dropHoverRow?.classList.remove("drop-hover");
		this._dropHoverRow = null;
	};

	/**
	 * Emit an `et2-filedrop` event for native OS file drops onto a row, then
	 * apply the framework default (upload + link to the row, gated on the link
	 * registry) unless a consumer cancels it with `event.preventDefault()`.
	 *
	 * The default mirrors the legacy nextmatch `handle_drop`: dropping a file on
	 * a row uploads into that entry's VFS link directory, linking the file to
	 * the entry.  Apps that need different behaviour (e.g. filemanager uploads
	 * into the row's folder instead of linking) listen for `et2-filedrop` and
	 * call `preventDefault()` to suppress the framework default.
	 */
	private _handleFileDrop = async (event : DragEvent) : Promise<void> =>
	{
		const files = event.dataTransfer?.files;
		if(!files || files.length === 0) return;
		event.preventDefault();
		event.stopPropagation();
		const rowElement = this._findDropRowElement(event);
		const rowUid = rowElement?.getAttribute("data-row-id") || "";
		this._clearDropHover();
		this._osFileDragDepth = 0;

		const dropEvent = new CustomEvent("et2-filedrop", {
			detail: {rowUid, files: Array.from(files)},
			bubbles: true,
			composed: true,
			cancelable: true
		});
		this.dispatchEvent(dropEvent);

		// Consumer cancelled (e.g. filemanager's folder upload) -> no default.
		if(dropEvent.defaultPrevented) return;

		await this._defaultFileDrop(rowUid, Array.from(files));
	};

	/**
	 * Framework default for a native OS file drop: upload the files into the
	 * target row's VFS link directory, linking them to that entry.  Only runs
	 * when the app is linkable (`link_get_registry`) and we have a row UID.
	 */
	private async _defaultFileDrop(rowUid : string, files : File[]) : Promise<void>
	{
		if(!rowUid) return;

		const split = rowUid.split("::");
		const to_app = split.shift() || "";
		const to_id = split.join("::");

		// Respect link system settings: only link when the app is registered.
		if(!this.egw().link_get_registry?.(to_app)) return;

		const path = `/apps/${to_app}/${to_id}/`;
		const link = <Et2VfsUpload>loadWebComponent("et2-vfs-upload", {path, multiple: true}, this);
		this.appendChild(link);
		await link.updateComplete;
		files.forEach(file => link.addFile(file));

		// Report results through the standard message bar, typed to upload
		// status.  We listen to the widget's per-file `et2-file-complete` event
		// (dispatched on both success and error) rather than the batch `change`,
		// because `change` fires only after the file is removed from the list
		// and can no longer be inspected.
		let done = 0;
		const total = files.length;
		link.addEventListener("et2-file-complete", (e : CustomEvent) =>
		{
			this.refresh(rowUid, Et2DatagridUpdateTypes.UPDATE_IN_PLACE);

			const file = e.detail?.file;
			const warning = file?.warning;
			if(!e.detail?.success || warning)
			{
				this.egw().message(warning ?? this.egw().lang("Failed to upload %1", file?.fileName ?? ""), "error");
			}
			else
			{
				this.egw().link_title(to_app, to_id, (title) =>
				{
					this.egw().message(this.egw().lang("%1 linked to %2", file?.fileName ?? "", title || rowUid), "success");
				});
			}

			if(++done >= total)
			{
				link.remove();
			}
		});
	}


	private _findDropRowElement(event : DragEvent) : HTMLElement | null
	{
		for(const target of (event.composedPath?.() ?? []))
		{
			if(target instanceof HTMLElement)
			{
				const row = target.closest?.("[data-row-id]") as HTMLElement | null;
				if(row) return row;
			}
		}
		return null;
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
		this.addEventListener("et2-active-row-changed", this._handleActiveRowChanged as EventListener);
		this.addEventListener("et2-columns-changed", this._handleDatagridColumnsChanged as EventListener);
		this.addEventListener("et2-column-selection-items", this._handleColumnSelectionItems as EventListener);
		this.addEventListener("et2-column-selection-apply", this._handleColumnSelectionApply as EventListener);
		this.addEventListener("contextmenu", this._handleContextMenu as EventListener, true);
		this.addEventListener("dblclick", this._handleDoubleClick as EventListener, true);
		this.addEventListener("keydown", this._handleKeydown as EventListener);
		this.addEventListener("pointerdown", this._handlePointerDown as EventListener);
		this.addEventListener("pointermove", this._handlePointerMove as EventListener);
		this.addEventListener("pointerup", this._cancelLongPress as EventListener);
		this.addEventListener("pointercancel", this._cancelLongPress as EventListener);
		this.addEventListener("et2-datagrid-enter-expanded-row", this._handleEnterExpandedRow as EventListener);
		this.addEventListener("et2-datagrid-leave-child-grid", this._handleLeaveChildGrid as EventListener);
		this.addEventListener("dragstart", this._handleDragStartCapture as EventListener, true);
		this.addEventListener("dragend", this._cancelLongPress as EventListener, true);
		// Native OS file drops (upload).  Distinct from action-framework drags,
		// which are handled by the action controller.  Captured in the capture
		// phase so the action controller does not swallow the OS file drag.
		this.addEventListener("dragenter", this._handleFileDragEnter as EventListener, true);
		this.addEventListener("dragover", this._handleFileDragOver as EventListener, true);
		this.addEventListener("drop", this._handleFileDrop as EventListener, true);
		this.addEventListener("dragleave", this._handleFileDragLeave as EventListener, true);
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
		this.removeEventListener("et2-active-row-changed", this._handleActiveRowChanged as EventListener);
		this.removeEventListener("et2-columns-changed", this._handleDatagridColumnsChanged as EventListener);
		this.removeEventListener("et2-column-selection-items", this._handleColumnSelectionItems as EventListener);
		this.removeEventListener("et2-column-selection-apply", this._handleColumnSelectionApply as EventListener);
		this.removeEventListener("contextmenu", this._handleContextMenu as EventListener, true);
		this.removeEventListener("dblclick", this._handleDoubleClick as EventListener, true);
		this.removeEventListener("keydown", this._handleKeydown as EventListener);
		this.removeEventListener("pointerdown", this._handlePointerDown as EventListener);
		this.removeEventListener("pointermove", this._handlePointerMove as EventListener);
		this.removeEventListener("pointerup", this._cancelLongPress as EventListener);
		this.removeEventListener("pointercancel", this._cancelLongPress as EventListener);
		this.removeEventListener("et2-datagrid-enter-expanded-row", this._handleEnterExpandedRow as EventListener);
		this.removeEventListener("et2-datagrid-leave-child-grid", this._handleLeaveChildGrid as EventListener);
		this.removeEventListener("dragstart", this._handleDragStartCapture as EventListener, true);
		this.removeEventListener("dragend", this._cancelLongPress as EventListener, true);
		this.removeEventListener("dragenter", this._handleFileDragEnter as EventListener, true);
		this.removeEventListener("dragover", this._handleFileDragOver as EventListener, true);
		this.removeEventListener("drop", this._handleFileDrop as EventListener, true);
		this.removeEventListener("dragleave", this._handleFileDragLeave as EventListener, true);
		this._actionController.destroy();
		super.disconnectedCallback();
	}

	transformAttributes(attrs)
	{
		// Process 'settings' into properties
		// We're before namespace creation here, so use attrs.id
		const attrSettings = this._settingsObject(attrs.settings);
		const contentSettings = this._settingsObject(this.getArrayMgr("content").getEntry(attrs.id || 'nm'));
		for(const sourceSettings of [contentSettings, attrSettings])
		{
			if(typeof sourceSettings.view !== "undefined")
			{
				sourceSettings.view = this._normalizeView(sourceSettings.view);
				this._filters.view = sourceSettings.view;
			}
			if(typeof sourceSettings.col_filter !== "undefined")
			{
				this._setColFilterFilter(sourceSettings.col_filter);
				delete sourceSettings.col_filter;
			}
			if(typeof sourceSettings.searchletter !== "undefined")
			{
				this._setSearchletterFilter(sourceSettings.searchletter);
				delete sourceSettings.searchletter;
			}
		}
		const settings = this._filterAllowedSettings(contentSettings);
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
			Object.assign(attrs, contentSettings);
		}
		if(typeof attrs.searchletter !== "undefined")
		{
			this._setSearchletterFilter(attrs.searchletter);
			delete attrs.searchletter;
		}
		if(typeof attrs.col_filter !== "undefined")
		{
			this._setColFilterFilter(attrs.col_filter);
			delete attrs.col_filter;
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
		if(typeof attrs.lettersearch !== "undefined")
		{
			attrs.lettersearch = !!attrs.lettersearch;
		}
		if(typeof attrs.view !== "undefined")
		{
			attrs.view = this._normalizeView(attrs.view);
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

	/**
	 * Normalize legacy string/object settings into a plain object.
	 */
	private _settingsObject(value : Record<string, any> | string | null | undefined) : Record<string, any>
	{
		return value && typeof value === "object" && !Array.isArray(value) ? {...value} : {};
	}

	private _normalizeView(value : string | null | undefined) : Et2DatagridView
	{
		return String(value || "").trim().toLowerCase() === "tile" ? "tile" : "row";
	}

	private _filterAllowedSettings(settings : Record<string, any>) : Record<string, any>
	{
		const allowed : Record<string, any> = {};
		for(const [key, value] of Object.entries(settings))
		{
			if(this._isAllowedSetting(key))
			{
				allowed[key] = value;
			}
		}
		return allowed;
	}

	private _isAllowedSetting(key : string) : boolean
	{
		if(Et2Nextmatch.ALLOWED_SETTINGS.has(key))
		{
			return true;
		}
		const match = key.match(/^(filter2?|cat(?:_id)?)_(.+)$/);
		return !!match && Et2Nextmatch.ALLOWED_SETTING_SUFFIXES.has(match[2]);
	}

	/**
	 * Normalize legacy settings-provided letter search into the active filters.
	 */
	private _setSearchletterFilter(value : any)
	{
		this._filters.searchletter = value && value != "false" ? value : false;
	}

	/**
	 * Normalize legacy settings-provided column filters into the active fetch filters.
	 */
	private _setColFilterFilter(value : any)
	{
		this._filters.col_filter = value && typeof value === "object" && !Array.isArray(value) ? {...value} : {};
	}

	/**
	 * Initialize legacy nextmatch actions through the action controller.
	 */
	protected _initActions(actions : EgwAction[] | { [id : string] : object })
	{
		this._actionController.initActions(actions);
		this._syncPlaceholderActionAvailability();
	}

	private _syncPlaceholderActionAvailability()
	{
		this._hasPlaceholderActions = this._actionController.hasPlaceholderActions();
	}

	/**
	 * Expose row-target resolution to the legacy action framework's AOI bridge.
	 */
	findActionTarget(event : Event)
	{
		return this._actionController.findActionTarget(event);
	}

	/**
	 * Initialize the widget from attributes/template and trigger first load.
	 * We prefer showing provided rows immediately to keep first paint fast.
	 */
	async firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);
		this._actionController.setPlaceholderActions(this.placeholderActions);
		this._syncPlaceholderActionAvailability();
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
		await this._updateRowStylesheets();
	}

	/**
	 * React to template changes after initial render.
	 * Template source is mutually exclusive: explicit template name wins over slots.
	 */
	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);
		if(changedProperties.has("settings") || changedProperties.has("lettersearch"))
		{
			this._lettersearchVisible = !this.lettersearch ||
				!!this.egw().preference(this._lettersearchPreferenceKey, this.egw().app_name());
		}
		if(this.lettersearch && !this._lettersearchVisible && this._filters.searchletter)
		{
			this._filters.searchletter = false;
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
			// Load new row CSS
			void this._updateRowStylesheets();
		}
		if(changedProperties.has("filterTemplate"))
		{
			this._applyFilterTemplate(this.filterTemplate);
		}
		if(changedProperties.has("placeholderActions"))
		{
			this._actionController.setPlaceholderActions(this.placeholderActions);
			this._syncPlaceholderActionAvailability();
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
		(this._findGridContainingRow(rowId) || this._datagrid)?.selectSingleRow(rowId);
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
	setColumns(columns : Array<string | Et2DatagridColumn>)
	{
		const currentColumns = this._currentColumns.length ? this._currentColumns : this._datagrid?.columns || [];
		const stringColumns = (columns || []).filter((column) => typeof column === "string") as string[];
		const hasOnlyStringColumns = stringColumns.length === (columns || []).length;
		if(hasOnlyStringColumns)
		{
			this._pendingVisibleColumnKeys = stringColumns.map((column) => String(column));
		}
		else
		{
			this._pendingVisibleColumnKeys = null;
		}
		const nextColumns = hasOnlyStringColumns && currentColumns.length
			? this._applyVisibleColumnKeys(currentColumns, stringColumns)
			: (columns || []).map((column, index) =>
				typeof column === "string" ? {key: "col" + index, title: column} : column
			);
		this._datagridColumns = nextColumns.map((column) => ({...column}));
		this._subgridColumnSnapshots.clear();
		if(!this._templateData)
		{
			this._templateData = {
				rowTemplate: null,
				rowTemplateXml: null,
				rowTemplateAttrMap: {},
				loaderTemplate: null,
				columns: nextColumns
			};
		}
		if(this._datagrid)
		{
			this._datagrid.columns = nextColumns.map((column) => ({...column}));
			this._datagrid.requestUpdate();
		}
		this.dispatchEvent(new CustomEvent("et2-columns-changed", {
			detail: {columns: nextColumns},
			bubbles: true,
			composed: true
		}));
		this.requestUpdate();
	}

	/**
	 * Apply a visible-column key list to the current template columns.
	 */
	private _applyVisibleColumnKeys(columns : Et2DatagridColumn[], visibleKeys : string[]) : Et2DatagridColumn[]
	{
		const selected = new Set((visibleKeys || []).map((key) => String(key)));
		return (columns || []).map((column) => ({
			...column,
			hidden: !selected.has(String(column.key || ""))
		}));
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
		if(this.lettersearch && this._lettersearchVisible)
		{
			selectcols.push("lettersearch");
		}
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
	 * Get the total number of rows
	 *
	 * @return {number}
	 */
	get totalCount() : number
	{
		return this._datagrid?.total ?? 0;
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
		this.setColumns(requestedColumns);
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

	/**
	 * Build a stable copy of filters for change comparison.
	 */
	private _filterSnapshot(filters : Record<string, any>) : Record<string, any>
	{
		return Object.entries(filters || {}).reduce((snapshot, [key, value]) =>
		{
			snapshot[key] = this._filterSnapshotValue(value);
			return snapshot;
		}, {} as Record<string, any>);
	}

	/**
	 * Normalize nested filter values for stable comparison.
	 */
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
		this.egw().debug("info", "Changed nm filters", this._filters);

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
		return this._applyTemplateFromName(template_name).then(() => this.updateComplete);
	}

	/**
	 * Switch between row and tile layout.
	 */
	setView(view : Et2DatagridView)
	{
		this.view = this._normalizeView(view);
		return this.updateComplete;
	}

	/**
	 * Switch between row and tile layout.
	 * @deprecated Use `setView()` instead.
	 */
	set_view(view : Et2DatagridView)
	{
		this._warnDeprecatedOnce("set_view", "Et2Nextmatch.set_view is deprecated, use `nm.setView(...)`");
		return this.setView(view);
	}

	/**
	 * Emit one deprecation warning per legacy API name.
	 */
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
				this._applyTemplateFromSlots().then(() => this._updateRowStylesheets());
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
		this._resetColumnsReady();

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
		const token = ++this._templateLoadToken;
		const previousTemplateData = this._templateData;
		this._resetColumnsReady();
		this._slotApplyInFlight = (async() =>
		{
			await this._waitForSlottedTemplateChildrenReady();
			const templateData = await this._rowProvider.fromSlots();
			if(token !== this._templateLoadToken)
			{
				return;
			}
			this._templateLoading = false;
			if(!templateData)
			{
				if(this._templateData === previousTemplateData)
				{
					this._templateData = null;
				}
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
		this._datagridColumns = null;
		this._subgridColumnSnapshots.clear();
		const columns = templateData.columns?.length ? templateData.columns : [];
		let nextColumns = this._applyLegacyNextmatchColumnPreferences(columns || [], templateData);
		if(this._pendingVisibleColumnKeys?.length)
		{
			nextColumns = this._applyVisibleColumnKeys(nextColumns, this._pendingVisibleColumnKeys);
		}
		this._templateData = {
			...templateData,
			sourceColumns: templateData.sourceColumns?.length ? templateData.sourceColumns : columns,
			columns: nextColumns
		};
		// Columns now exist (getValue().selectcols is populated) - let consumers proceed.
		this._resolveColumnsReady();
	}

	private _effectiveView() : Et2DatagridView
	{
		if(this.view === "tile")
		{
			return "tile";
		}
		return this._templateData?.view === "tile" &&
			(!this._templateData.rowTemplateId || this._templateData.rowTemplateId === this.template)
		       ? "tile"
		       : "row";
	}

	private _resetColumnsReady()
	{
		this._resolveColumnsReady();
		this._columnsReady = new Promise((resolve) =>
		{
			this._resolveColumnsReady = resolve;
		});
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
	 *
	 * This is only a migration seed. Once the datagrid has stored its own
	 * preference, for example after a column resize, that newer preference wins.
	 */
	private _seedDatagridColumnPreferencesFromLegacy(
		rowTemplateId : string,
		app : string,
		columns : Et2NextmatchResolvedColumn[]
	)
	{
		const key = String(this.columnPreferenceName || "").trim() || `nextmatch-${rowTemplateId}-prefs`;
		try
		{
			if(this.egw().preference(key, app))
			{
				return;
			}
		}
		catch(e)
		{
			return;
		}
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

	private async _updateRowStylesheets()
	{
		const appName = this._getAppName();
		const sheet = await loadStylesheet(this.egw().link(`/${appName}/templates/default/app.css`));
		this._rowStylesheets = sheet ? [rowStyles.styleSheet!, sheet] : [rowStyles.styleSheet!];
		await this.updateComplete;
		const datagrid = this._datagrid;
		if(datagrid)
		{
			datagrid.rowStylesheets = this._rowStylesheets;
		}
	}

	/**
	 * Build the generic datagrid expansion bridge from Nextmatch hierarchy settings.
	 */
	private _datagridExpansionConfig() : Et2DatagridExpansionConfig
	{
		return {
			isExpandable: (row) => this._isExpandableNextmatchRow(row?.data),
			renderExpandedContent: (context) => this._renderExpandedNextmatchGrid(context),
			expandedRowIds: this._expandedRowIds,
			onExpandedRowIdsChanged: (expandedRowIds) =>
			{
				for(const expandedRowId of this._expandedRowIds)
				{
					if(!expandedRowIds.has(expandedRowId))
					{
						this._subgridColumnSnapshots.delete(expandedRowId);
					}
				}
				this._expandedRowIds = new Set(expandedRowIds);
				this.requestUpdate();
			}
		};
	}

	/**
	 * Determine whether a Nextmatch row should show a child-grid expander.
	 */
	private _isExpandableNextmatchRow(rowData : Record<string, any> | null | undefined) : boolean
	{
		if(!rowData)
		{
			return false;
		}
		const isParentField = this._settings?.is_parent;
		if(isParentField && Object.prototype.hasOwnProperty.call(rowData, isParentField))
		{
			const value = rowData[isParentField];
			const expected = this._settings?.is_parent_value;
			return expected !== undefined && expected !== null
			       ? String(value) === String(expected)
			       : this._isTruthyNextmatchParentValue(value);
		}
		return rowData.is_parent === true;
	}

	/**
	 * Apply legacy truthiness for configured parent-row marker values.
	 */
	private _isTruthyNextmatchParentValue(value : any) : boolean
	{
		return value !== undefined && value !== null && value !== false && value !== 0 && value !== "0" && value !== "";
	}

	/**
	 * Render the nested datagrid used for one expanded Nextmatch parent row.
	 */
	private _renderExpandedNextmatchGrid(context : Et2DatagridExpandedRowContext)
	{
		const parentRowId = String(context.row.id || "");
		const childProvider = this._childDataProvider(parentRowId);
		const childParentRowId = typeof this._dataProvider.toProviderRowId === "function"
		                         ? this._dataProvider.toProviderRowId(parentRowId)
		                         : parentRowId;
		const columnSnapshot = this._subgridColumnSnapshot(parentRowId);

		return html`
            <et2-datagrid
                    class="nextmatch-subgrid"
                    part="subgrid"
                    embedded-virtualized
                    ._parent=${this}
                    .columns=${columnSnapshot.columns}
                    .templateData=${this._templateData}
                    .rowCustomizer=${this._customizeDatagridRow}
                    .rowStylesheets=${this._rowStylesheets}
                    .dataProvider=${childProvider}
                    .parentRowId=${childParentRowId}
                    .noVisibleHeader=${true}
                    .noColumnSelection=${true}
                    .inheritColumnSizes=${true}
                    .autoActivateFirstRow=${false}
                    .configurationLoading=${this._templateLoading}
                    .emptyStateText=${this.egw().lang("No visible children")}
                    selection-mode="multiple"
                    style=${styleMap({
                        "--meta-column-width": "6px"
                    })}
                    ${ref(this._loadExpandedGrid)}
            ></et2-datagrid>
		`;
	}

	/**
	 * Freeze child columns when a row expands so later parent column updates do
	 * not change the child grid mid-render.
	 */
	private _subgridColumnSnapshot(
		parentRowId : string
	) : { columns : Et2DatagridColumn[] }
	{
		if(!this._subgridColumnSnapshots.has(parentRowId))
		{
			this._subgridColumnSnapshots.set(parentRowId, {
				columns: (this._currentColumns || []).map((column) => ({...column}))
			});
		}
		return this._subgridColumnSnapshots.get(parentRowId)!;
	}

	/**
	 * Reuse one child data provider per parent row.
	 */
	private _childDataProvider(parentRowId : string) : Et2DatagridDataProvider
	{
		if(!this._childDataProviders.has(parentRowId))
		{
			this._childDataProviders.set(parentRowId, this._dataProvider.createChildProvider(parentRowId));
		}
		return this._childDataProviders.get(parentRowId)!;
	}

	/**
	 * Trigger the first child-grid load after Lit has connected the expanded grid.
	 */
	private _loadExpandedGrid = (element ? : Element) =>
	{
		if(!(element instanceof Et2Datagrid) || this._templateLoading)
		{
			return;
		}
			if(this._initializedSubgrids.has(element))
			{
				return;
			}
		this._initializedSubgrids.add(element);
		void element.updateComplete.then(() =>
		{
			if(element.isConnected)
			{
				element.reload();
			}
		});
	};

	/**
	 * Move keyboard focus from an expanded parent row into its child grid.
	 */
	private _handleEnterExpandedRow = (event : CustomEvent<{ parentRowId? : string }>) =>
	{
		const parentRowId = String(event.detail?.parentRowId || "");
		const childGrid = this._findChildGridForParent(parentRowId);
		if(!childGrid)
		{
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		childGrid.focusFirstRow?.();
	};

	/**
	 * Move keyboard focus from a child grid back to its parent row.
	 */
	private _handleLeaveChildGrid = (event : CustomEvent<{ parentRowId? : string }>) =>
	{
		const parentRowId = String(event.detail?.parentRowId || "");
		if(!parentRowId)
		{
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		this._datagrid?.focusRowById?.(this._dataProvider.normalizeRowId(parentRowId, true));
	};

	/**
	 * Find the rendered child grid belonging to an expanded parent row.
	 */
	private _findChildGridForParent(parentRowId : string) : Et2Datagrid | null
	{
		const parentDataStoreRowId = this._dataProvider.normalizeRowId(parentRowId, true);
		const expandedRow = this._datagrid?.shadowRoot?.querySelector(
			`[data-dg-expanded-row='1'][data-parent-row-id='${CSS.escape(parentDataStoreRowId)}']`
		) as HTMLElement | null;
		return expandedRow?.querySelector("et2-datagrid") as Et2Datagrid | null;
	}

	/**
	 * Find the parent or child grid that currently renders a row id.
	 */
	private _findGridContainingRow(rowId : string) : Et2Datagrid | null
	{
		const normalizedRowId = this._dataProvider.normalizeRowId(rowId, true);
		const grids = [this._datagrid, ...this._childGrids()].filter(Boolean) as Et2Datagrid[];
		return grids.find((grid) =>
			!!grid.shadowRoot?.querySelector(`[data-row-id='${CSS.escape(normalizedRowId)}']`)
		) || null;
	}

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
	 * Resolve app-name used for sort preference persistence.
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
	 * Emit `et2-search-result` after datagrid finishes loading.
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

	/**
	 * Track column changes from the root datagrid so child grids inherit them.
	 */
	private _handleDatagridColumnsChanged = (event : CustomEvent<{ columns? : Et2DatagridColumn[] }>) =>
	{
		const datagrid = this._datagrid;
		const sourceGrid = this._eventSourceDatagrid(event);
		if(!datagrid || sourceGrid !== datagrid || !event.detail?.columns?.length)
		{
			return;
		}
		this._datagridColumns = event.detail.columns.map((column) => ({...column}));
		this.requestUpdate();
	};

	/**
	 * Add letter search as a pseudo-column in the root column chooser.
	 */
	private _handleColumnSelectionItems = (event : CustomEvent<{ columns : Et2DatagridColumnSelectionItem[] }>) =>
	{
		if(!this.lettersearch || this._eventSourceDatagrid(event) !== this._datagrid)
		{
			return;
		}
		const caption = this.egw().lang("Search letter");
		event.detail.columns.push({
			id: LETTERSEARCH_SELECTION_ID,
			title: caption,
			caption,
			widget: null,
			visibility: this.lettersearch && this._lettersearchVisible
		});
	};

	/**
	 * Consume the letter-search pseudo-column before real grid columns are applied.
	 */
	private _handleColumnSelectionApply = (event : CustomEvent<{ selectedOrder : string[] }>) =>
	{
		if(!this.lettersearch || this._eventSourceDatagrid(event) !== this._datagrid)
		{
			return;
		}
		const selectedOrder = event.detail?.selectedOrder || [];
		const letterIndex = selectedOrder.indexOf(LETTERSEARCH_SELECTION_ID);
		const nextVisible = letterIndex >= 0;
		if(letterIndex >= 0)
		{
			selectedOrder.splice(letterIndex, 1);
		}
		this._lettersearchVisible = nextVisible;
		this.egw().set_preference(this.egw().app_name(), this._lettersearchPreferenceKey, nextVisible);
		if(!nextVisible && this._filters.searchletter)
		{
			this.applyFilters({searchletter: false});
			return;
		}
		this.requestUpdate();
	};

	private get _lettersearchPreferenceKey() : string
	{
		return `nextmatch-${this.settings.columnselection_pref || this.columnPreferenceName}-lettersearch`;
	}

	/**
	 * Merge selection events from parent and child grids for action state.
	 */
	private _handleSelectionChanged = (event : CustomEvent<{
		selectedRowIds? : string[];
		activeRowId? : string;
		allSelected? : boolean;
		replaceSelection? : boolean
	}>) =>
	{
		const datagrid = this._datagrid;
		if(!datagrid || !event.composedPath().includes(datagrid))
		{
			return;
		}
		const sourceGrid = this._eventSourceDatagrid(event) || datagrid;
		const gridId = this._selectionGridId(sourceGrid);
		if((sourceGrid.selectionMode === "single" || event.detail?.replaceSelection) && event.detail?.selectedRowIds?.length)
		{
			this._clearOtherGridSelections(sourceGrid);
		}
		this._selectionByGridId.set(gridId, {
			selectedRowIds: [...(event.detail?.selectedRowIds || [])],
			allSelected: !!event.detail?.allSelected
		});
		this._syncActiveGrid(sourceGrid);
		this._actionController.handleSelectionChanged(this._mergedSelectionDetail(event.detail || {}));
	};

	/**
	 * Keep only one active row visible across parent and child grids.
	 */
	private _handleActiveRowChanged = (event : CustomEvent<{ activeRowId? : string; activeRowIndex? : number }>) =>
	{
		const sourceGrid = this._eventSourceDatagrid(event);
		if(sourceGrid && (sourceGrid === this._datagrid || this._childGrids().includes(sourceGrid)))
		{
			this._syncActiveGrid(sourceGrid);
		}
	};

	/**
	 * Clear selection from all grids except the one that just selected a row.
	 */
	private _clearOtherGridSelections(sourceGrid : Et2Datagrid)
	{
		const grids = [
			this._datagrid,
			...this._childGrids()
		].filter((grid) : grid is Et2Datagrid => !!grid && grid !== sourceGrid);
		for(const grid of grids)
		{
			this._selectionByGridId.delete(this._selectionGridId(grid));
			grid.clearSelection?.(false);
		}
	}

	/**
	 * Resolve the datagrid instance that originated a composed event.
	 */
	private _eventSourceDatagrid(event : Event) : Et2Datagrid | null
	{
		return (event.composedPath?.() || []).find((target) => target instanceof Et2Datagrid) as Et2Datagrid | null;
	}

	/**
	 * Build a stable key for selection state in the parent grid or a child grid.
	 */
	private _selectionGridId(grid : Et2Datagrid) : string
	{
		return grid === this._datagrid ? "parent" : `child:${grid.parentRowId || ""}`;
	}

	/**
	 * Merge parent and child grid selections into the legacy Nextmatch detail shape.
	 */
	private _mergedSelectionDetail(detail : { activeRowId? : string; activeRowIndex? : number })
	{
		const selectedRowIds = Array.from(new Set(
			Array.from(this._selectionByGridId.values()).flatMap((selection) => selection.selectedRowIds)
		));
		const allSelected = Array.from(this._selectionByGridId.values()).some((selection) => selection.allSelected);
		return {
			...detail,
			selectedRowIds,
			allSelected
		};
	}

	/**
	 * Clear active-row state in sibling grids when another grid takes focus.
	 */
	private _syncActiveGrid(activeGrid : Et2Datagrid)
	{
		if(activeGrid !== this._datagrid)
		{
			this._datagrid?.clearActiveRow?.();
		}
		for(const childGrid of this._childGrids())
		{
			if(childGrid !== activeGrid)
			{
				childGrid.clearActiveRow?.();
			}
		}
	}

	/**
	 * Return all currently rendered child datagrids inside expanded rows.
	 */
	private _childGrids() : Et2Datagrid[]
	{
		const datagrid = this._datagrid;
		return Array.from(datagrid?.shadowRoot?.querySelectorAll("et2-datagrid") || []) as Et2Datagrid[];
	}

	/**
	 * Route context-menu requests to placeholder or row action popups.
	 */
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

	/**
	 * Execute the default row action on non-interactive row double-click.
	 */
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

	/**
	 * Detect row interactions that should remain native interactive element events.
	 */
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
		const currentLetterValue = this._filters.searchletter || "";
		const currentLetter = typeof currentLetterValue === "string" ? currentLetterValue : "";
		if((!this.lettersearch && !currentLetter) || (this.lettersearch && !this._lettersearchVisible))
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
	 * Open the row action popup from keyboard Enter.
	 */
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

	/**
	 * Forward pointerdown to the action controller for long-press/drag setup.
	 */
	private _handlePointerDown = (event : PointerEvent) =>
	{
		this._actionController.handlePointerDown(event);
	};

	/**
	 * Forward pointer movement to cancel long-press when needed.
	 */
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
	 * Prevent native drag-start from resize handles before the action controller sees it.
	 */
	private _handleDragStartCapture = (event : DragEvent) =>
	{
		if(!this._isColumnResizeDragStart(event))
		{
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		event.stopImmediatePropagation?.();
	};

	/**
	 * Check whether a dragstart belongs to the column resize handle.
	 */
	private _isColumnResizeDragStart(event : DragEvent) : boolean
	{
		const path = event.composedPath?.() || [];
		if(path.some((target) =>
			target instanceof HTMLElement &&
			!!target.closest?.(".dg-col-resize-handle")))
		{
			return true;
		}
		if(typeof event.clientX !== "number" || typeof event.clientY !== "number")
		{
			return false;
		}
		const deepTarget = this._deepElementFromPoint(this.shadowRoot, event.clientX, event.clientY);
		return !!deepTarget?.closest?.(".dg-col-resize-handle");
	}

	/**
	 * Resolve the deepest shadow-DOM element at a point.
	 */
	private _deepElementFromPoint(root : Document | ShadowRoot | null, x : number, y : number) : HTMLElement | null
	{
		let element = root?.elementFromPoint?.(x, y) as HTMLElement | null;
		let lastElement : HTMLElement | null = null;
		while(element && element !== lastElement)
		{
			lastElement = element;
			const next = element.shadowRoot?.elementFromPoint?.(x, y) as HTMLElement | null;
			if(!next || next === element)
			{
				break;
			}
			element = next;
		}
		return element;
	}

	/**
	 * Render the orchestration shell.
	 * We explicitly set `._parent` so Et2Datagrid can participate in Et2Widget array manager lookup.
	 */
	render()
	{
		const hasSlottedNoResults = !!this.querySelector("[slot='noResults']");
		const metaColumnWidth = "max(var(--sl-spacing-large), 6px)";
		const effectiveView = this._effectiveView();
		return html`
				<div part="header"><slot name="header"></slot></div>
                ${this._renderLetterSearch()}
				<et2-datagrid
                        part="grid"
                        exportparts="rows, row"
                        ._parent=${this}
                        .view=${effectiveView}
                        .columns=${this._currentColumns}
                        .templateData=${this._templateData}
                        .rowCustomizer=${this._customizeDatagridRow}
                        .rowStylesheets=${this._rowStylesheets}
                        .columnPreferenceName=${this.columnPreferenceName}
                        .dataProvider=${this._dataProvider}
                        .expansionConfig=${this._datagridExpansionConfig()}
                        .configurationLoading=${this._templateLoading}
                        .emptyStateText=${this.placeholder}
                        .emptyStateActionMenu=${this._hasPlaceholderActions}
                        selection-mode="multiple"
                        style=${styleMap({"--meta-column-width": metaColumnWidth})}
                >
                    ${hasSlottedNoResults
                      ? html`
                                <slot name="noResults" slot="noResults"></slot>`
                      : null}
                </et2-datagrid>
                <div part="footer">
                    <slot name="footer"></slot>
                </div>
		`;
	}
}
