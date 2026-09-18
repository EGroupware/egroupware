/**
 * EGroupware eTemplate2 - history log
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {html, LitElement, nothing, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {styleMap} from "lit/directives/style-map.js";
import {Et2Widget, loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2Datagrid} from "../Et2Datagrid/Et2Datagrid";
import {Et2RowProvider} from "../Et2Datagrid/Et2RowProvider";
import {Et2NextmatchDataProvider} from "../Et2Nextmatch/Et2NextmatchDataProvider";
import type {Et2Filterbox} from "../Et2Filterbox/Et2Filterbox";
import type {
	Et2DatagridColumn,
	Et2DatagridRowCustomizeContext,
	Et2DatagridTemplateData
} from "../Et2Datagrid/Et2Datagrid.types";
import type {NextmatchActiveFilters, NextmatchInterface} from "../Et2Nextmatch/NextmatchInterfaces";
import {ET2_NEXTMATCH_FILTER_EVENT} from "../Et2Nextmatch/Headers/events";
import {Et2HistorylogWidgetRegistry} from "./Et2HistorylogWidgetRegistry";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import styles from "./Et2Historylog.styles";
import rowStyles from "./Et2Historylog.row.styles";
import {adoptDiffStyles} from "./Et2Historylog.diff.styles";

// The cells the row template uses.  Imported for the side effect of registering them.
import "./Et2HistorylogValue";
import "./Et2HistorylogStatus";
// Renders a long or multi-line change, both in its row and popped out - see showDiff()
import "../Et2Diff/Et2Diff";

/**
 * Shows the changes recorded for one entry, newest first.
 *
 * The widget is self-configuring: it needs only the record's id and app, plus the app's map of
 * fields to the widgets their values should be displayed with.
 *
 * ```xml
 * <et2-historylog id="history"/>
 * ```
 *
 * with `$content['history'] = ['id' => 123, 'app' => 'infolog', 'status-widgets' => [...]]`.
 *
 * It renders through `et2-datagrid`, but is not a nextmatch: there is nothing to select, no
 * actions, no favourites, no column preferences, and the sort order is fixed (newest first).  What
 * it does share with a nextmatch is the row-fetching plumbing - `Et2NextmatchDataProvider`, whose
 * host contract it implements - and `Et2Filterbox`, which drives it through `NextmatchInterface`.
 *
 * Initialization is deferred until the tab it sits on is first shown, so an unopened history tab
 * costs nothing.
 *
 * @summary The change history of one entry
 * @category display
 *
 * @csspart grid - the datagrid
 * @csspart filters - the filter drawer's panel
 * @csspart header - the bar above the grid, holding the filter button
 */
@customElement("et2-historylog")
export class Et2Historylog extends Et2Widget(LitElement) implements NextmatchInterface
{
	static get styles()
	{
		return [
			...(super.styles || []),
			styles
		];
	}

	/**
	 * `{app, id, status-widgets, num_rows, rows, total, ...}` - what the app put in
	 * `$content[<widget id>]`.  Same shape the legacy widget took.
	 */
	@property({type: Object})
	value : Record<string, any> = {};

	/**
	 * Id given to the widget rendering the "Changed" column.
	 *
	 * The history log's own id is traditionally `history`, and its changed-field column is named
	 * `status`; an app whose dialog already has a widget called `status` can move this one out of
	 * the way.  (Calendar does, via `<historylog options="history_status"/>`.)
	 *
	 * No explicit `attribute:` on purpose.  api/etemplate.php rewrites every `et2-*` attribute name
	 * containing `_` or `-` to camelCase before the template reaches the client, so the legacy
	 * `options="..."` arrives as `statusId="..."` - which the HTML parser lowercases to `statusid`,
	 * exactly the attribute Lit derives from this property name by default.  Declaring
	 * `attribute: "status_id"` instead would observe a name that never arrives.
	 */
	@property({type: String})
	statusId : string = "status";

	/**
	 * Which columns to show, as a comma-separated list or an array.
	 *
	 * Kept for compatibility with the legacy widget.  Columns not listed are hidden but remain
	 * available in the datagrid's column chooser.
	 */
	@property({type: String})
	columns : string | string[] = "user_ts,owner,status,new_value,old_value";

	/**
	 * Wait for the tab this sits on to be shown before loading anything.  On by default - a
	 * history tab is rarely the one the user opens first.
	 */
	@property({type: Boolean})
	lazy : boolean = true;

	/** Grow to fit rows instead of scrolling internally.  See _resolveHeight(). */
	@property({type: Boolean, reflect: true, attribute: "auto-height"})
	autoHeight : boolean = false;

	/** Row template + columns, once the row template has been read */
	@state()
	private _templateData : Et2DatagridTemplateData | null = null;

	@state()
	private _columns : Et2DatagridColumn[] = [];

	@state()
	private _templateLoading : boolean = true;

	@state()
	private _filtersOpen : boolean = false;

	/**
	 * Which widget renders which field's value, built once from the app's `status-widgets` plus
	 * the custom fields and the built-in statuses.  Read per row by `et2-historylog-value`.
	 */
	public widgetRegistry : Et2HistorylogWidgetRegistry | null = null;

	private _rowProvider : Et2RowProvider;
	private _dataProvider : Et2NextmatchDataProvider;

	/**
	 * Active filter state.  `record_id`/`appname` are what scope every request; the server
	 * re-derives both from its own content and ignores what we send (see
	 * Etemplate\Widget\HistoryLog::$server_authoritative), but they still have to be sent - their
	 * presence is what marks this as a row request rather than a form submit.
	 */
	private _filters : Record<string, any> = {col_filter: {}};

	private _applyingFilters = false;

	private _filterbox : Et2Filterbox | null = null;

	private get _datagrid() : Et2Datagrid | null
	{
		return this.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid | null;
	}

	/** The filter drawer, by the name Et2Filterbox looks for when closing on Escape */
	public get filtersDrawer() : any
	{
		return this.shadowRoot?.querySelector("sl-drawer");
	}

	constructor()
	{
		super();
		this._rowProvider = new Et2RowProvider(<any>this);
		this._dataProvider = new Et2NextmatchDataProvider(<any>this);
		this._handleHeaderFilterEvent = this._handleHeaderFilterEvent.bind(this);
	}

	/**
	 * Pick our configuration up out of the content.
	 *
	 * `_createNamespace()` gives our children a content perspective scoped to our id, but nothing
	 * assigns our own `value` from it - and once that perspective is open, our content entry is no
	 * longer reachable under our id.  So it has to be read here, which runs *before* namespace
	 * creation (same reason Et2Nextmatch reads its `settings` here, from `attrs.id`).
	 */
	transformAttributes(attrs)
	{
		if(typeof attrs.value === "undefined" || attrs.value === null || attrs.value === "")
		{
			const content = this.getArrayMgr("content")?.getEntry(attrs.id || "history");
			if(content && typeof content === "object")
			{
				attrs.value = content;
			}
		}
		super.transformAttributes(attrs);
	}

	connectedCallback()
	{
		super.connectedCallback();
		// A filter widget (in the drawer, or a header one an app added) announces its change by
		// bubbling this rather than by holding a reference to us.
		this.addEventListener(ET2_NEXTMATCH_FILTER_EVENT, <EventListener>this._handleHeaderFilterEvent);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener(ET2_NEXTMATCH_FILTER_EVENT, <EventListener>this._handleHeaderFilterEvent);
		this._filterbox?.remove();
		this._filterbox = null;
	}

	/**
	 * The history log's content is its own namespace (`content['history']`), like the legacy
	 * widget's.
	 */
	_createNamespace() : boolean
	{
		return true;
	}

	/**
	 * A display widget: it never contributes to a submit.  Deliberately not an et2_IInput.
	 */
	isDirty() : boolean
	{
		return false;
	}

	getValue() : null
	{
		return null;
	}

	// --- NextmatchInterface, so Et2Filterbox and header filter widgets can drive us ---

	public get activeFilters() : NextmatchActiveFilters
	{
		return <NextmatchActiveFilters>this._filters;
	}

	/**
	 * The sort order is fixed (newest first) and the server rejects a client-supplied one, so
	 * these exist only to satisfy the interface - and because Et2NextmatchDataProvider calls
	 * sortBy() when a response echoes an `order`.
	 */
	public sortBy(_id : string, _asc? : boolean, _update? : boolean)
	{
		return false;
	}

	public resetSort()
	{
		return false;
	}

	public refreshColumnVisibility()
	{
		// No expression-driven columns; nothing to re-evaluate.
	}

	public getWidgetById(id : string) : any
	{
		if(id === this.id)
		{
			return this;
		}
		return this._filterbox?.getWidgetById?.(id) ?? super.getWidgetById?.(id) ?? null;
	}

	public getDOMNode(_sender? : any) : HTMLElement
	{
		return this;
	}

	/**
	 * Merge the given filters into the active ones and reload.
	 *
	 * The reentrancy guard is the same one Et2Nextmatch needs: dispatching the filter event runs
	 * listeners synchronously, and a filter widget whose value setter re-fires "change" on a
	 * programmatic set would otherwise come straight back in here before this call returned.
	 */
	public applyFilters(set? : Record<string, any>, options? : { reload? : boolean })
	{
		if(this._applyingFilters)
		{
			this.egw().debug("warn", "Et2Historylog.applyFilters() called reentrantly - ignoring", set);
			return false;
		}
		if(!this._filters || typeof this._filters !== "object")
		{
			this._filters = {col_filter: {}};
		}
		if(typeof set === "object" && set !== null)
		{
			for(const key of Object.keys(set))
			{
				if(key === "col_filter")
				{
					const incoming = set.col_filter || {};
					const col_filter = {...(this._filters.col_filter || {})};
					for(const column of Object.keys(incoming))
					{
						const value = incoming[column];
						if(value === "" || value === null || typeof value === "undefined" ||
							(Array.isArray(value) && !value.length))
						{
							delete col_filter[column];
						}
						else
						{
							col_filter[column] = value;
						}
					}
					this._filters.col_filter = col_filter;
					continue;
				}
				this._filters[key] = set[key];
			}
		}

		this._applyingFilters = true;
		try
		{
			this.dispatchEvent(new CustomEvent("et2-filter", {
				bubbles: true,
				composed: true,
				detail: {activeFilters: this._filters}
			}));
		}
		finally
		{
			this._applyingFilters = false;
		}
		// The header renders from _filterInfo(), which reads _filters - a plain field, not a
		// reactive property - so nothing re-renders on its own when filters change.  Without this
		// the funnel icon never fills and the drawer's "Clear filters" action never appears.
		this.requestUpdate();
		if(options?.reload !== false)
		{
			// typeof, not just ?.: the element exists in our shadow DOM from the first render, but
			// has no methods until the custom element upgrades - a filter applied in between
			// (a favourite, an app seeding a filter in et2_ready) would otherwise throw.
			if(typeof this._datagrid?.reload === "function")
			{
				void this._datagrid.reload();
			}
		}
		return true;
	}

	/**
	 * A filter widget changed.  Its event carries the filters it wants applied.
	 */
	private _handleHeaderFilterEvent(event : CustomEvent)
	{
		if(!event.detail?.filters)
		{
			return;
		}
		event.preventDefault();
		this.applyFilters(event.detail.filters);
	}

	// --- setup ---

	/**
	 * Which record this is, and the widget's own request scoping.
	 */
	private _seedFilters()
	{
		this._filters = {
			col_filter: {},
			record_id: this.value?.id,
			appname: this.value?.app
		};
	}

	/**
	 * Build the status registry and push its labels at the "Changed" column and the filter.
	 */
	private _buildRegistry()
	{
		const customfields = this.getArrayMgr("modifications")?.getRoot?.()
			?.getEntry("~custom_fields~", true)?.customfields ?? null;
		this.widgetRegistry = new Et2HistorylogWidgetRegistry(
			this.value?.["status-widgets"],
			customfields,
			(s : string) => this.egw().lang(s)
		);
		// The server sends the app's own field labels for the status column, under this widget's
		// namespace (see HistoryLog::beforeSendToClient()); they are better than our fallbacks.
		const serverLabels = this.getArrayMgr("sel_options")?.getEntry(this.statusId)
			?? this.getArrayMgr("sel_options")?.getEntry("status");
		if(serverLabels)
		{
			this.widgetRegistry.mergeLabels(serverLabels);
		}
	}

	/** The app's status-widgets map, read by et2-historylog-value when warning about a gap */
	public get statusWidgets() : Record<string, any> | null
	{
		return this.value?.["status-widgets"] ?? null;
	}

	/**
	 * Resolve immediately unless `lazy` is set and we are inside an inactive `<et2-tab-panel>`.
	 *
	 * Same approach as Et2Nextmatch's own lazy handling, and as the legacy history log's - which
	 * is where it started.
	 */
	private async _whenLazyVisible() : Promise<void>
	{
		if(!this.lazy)
		{
			return;
		}
		const panel = this.closest("et2-tab-panel");
		const panelName = panel?.getAttribute("name");
		if(!panel || !panelName || panel.hasAttribute("active"))
		{
			return;
		}
		const group = panel.closest("et2-tabbox");
		if(!group)
		{
			return;
		}
		return new Promise<void>(resolve =>
		{
			const handler = (e : CustomEvent) =>
			{
				if(e.detail?.name !== panelName)
				{
					return;
				}
				group.removeEventListener("sl-tab-show", <EventListener>handler);
				resolve();
			};
			group.addEventListener("sl-tab-show", <EventListener>handler);
		});
	}

	async firstUpdated(changed : PropertyValues)
	{
		super.firstUpdated(changed);

		// Nothing to show without an entry
		if(!this.value?.id)
		{
			this._templateLoading = false;
			return;
		}
		if(this.statusId === this.id)
		{
			this.egw().debug("warn", "Et2Historylog: status_id must not be the same as the widget's own id" +
				" - the 'Changed' column and the widget would collide");
		}

		this._seedFilters();
		this._buildRegistry();

		await this._whenLazyVisible();

		// try/finally around the load: if the row template cannot be read the grid must stop
		// claiming to be loading, or it sits on its spinner for the life of the dialog with no
		// indication of what went wrong.
		try
		{
			const templateData = await this._rowProvider.fromTemplate("api.historylog.rows");
			this._templateData = templateData;
			this._columns = this._applyColumnVisibility(templateData?.columns || []);
			this._applyStatusColumn();
		}
		catch(e)
		{
			this.egw().debug("error", "Et2Historylog: could not load its row template", e);
		}
		finally
		{
			this._templateLoading = false;
		}
		if(!this._templateData)
		{
			// Nothing to render rows with; don't ask the server for rows we cannot show.
			return;
		}

		await this.updateComplete;

		// Rows the initial exec already carried, rather than fetching the first page again
		const rows = this.value?.rows;
		const total = typeof this.value?.total !== "undefined" ? this.value.total : 0;
		if(rows && this.value?.num_rows)
		{
			this._dataProvider.storeRows(Object.values(rows));
			this._datagrid?.setInitialRows(Object.values(rows));
			if(this._datagrid)
			{
				this._datagrid.total = total;
			}
			// Don't keep them - they would be re-applied on every later update
			delete this.value.rows;
		}
		else
		{
			await this._datagrid?.reload();
		}
	}

	/**
	 * Hide the columns the `columns` attribute leaves out, without making them unavailable.
	 */
	private _applyColumnVisibility(columns : Et2DatagridColumn[]) : Et2DatagridColumn[]
	{
		const wanted = typeof this.columns === "string" ? this.columns.split(",") : (this.columns || []);
		const visible = wanted.map(c => String(c).trim()).filter(c => c !== "");
		if(!visible.length)
		{
			return columns;
		}
		return columns.map(column => ({
			...column,
			hidden: !visible.includes(column.key)
		}));
	}

	/**
	 * Give the "Changed" column's select the id the template asked for.
	 *
	 * Only the id: its *options* come from sel_options, which HistoryLog::beforeSendToClient()
	 * fills (including the built-in ~link~/~file~/user_agent_action statuses and the app's custom
	 * fields).  Setting them on the template element instead does not survive - Et2Select resolves
	 * its options from the sel_options array manager during row hydration, which silently replaces
	 * whatever the element carried.
	 */
	private _applyStatusColumn()
	{
		if(this.statusId === "status" || !this._templateData?.rowTemplate)
		{
			return;
		}
		const select = this._templateData.rowTemplate.content.querySelector('[id$="status"]');
		select?.setAttribute("id", this.statusId);
	}

	// --- filters ---

	/**
	 * Create the filterbox that lives in our drawer.
	 *
	 * Ours rather than the shared one Et2Nextmatch attaches to the app shell: a history log sits
	 * in an edit dialog, which has no `egw-app` and no `slot="filter"` to attach to.
	 */
	private _ensureFilterbox() : Et2Filterbox | null
	{
		if(this._filterbox)
		{
			return this._filterbox;
		}
		const body = this.shadowRoot?.querySelector(".historylog__filters");
		if(!body)
		{
			return null;
		}
		this._filterbox = <Et2Filterbox><unknown>loadWebComponent("et2-filterbox", {
			id: "historylog-filters",
			exportparts: "filters",
			autoapply: true,
			// No footer Clear button: the drawer header carries the clear action, the way
			// egw-app's does, and two of them in one drawer is just confusing.
			clearable: false
		}, this);
		this._filterbox.nextmatch = <any>this;
		body.appendChild(this._filterbox);
		// The status filter offers exactly the "Changed" column's own option list, so a value the
		// rows carry (~file~ for attachments, ~link~, a custom field) is filterable without
		// inventing a second name for it.
		this._filterbox.updateComplete.then(() =>
		{
			const status = this._filterbox?.getWidgetById?.("col_filter[status]");
			const labels = this.widgetRegistry?.labels() || [];
			if(status && labels.length)
			{
				status.set_select_options(labels);
			}
		});
		(<any>this._filterbox).setFilterTemplate?.("api.historylog.filters");
		return this._filterbox;
	}

	private _toggleFilters()
	{
		this._filtersOpen = !this._filtersOpen;
		if(this._filtersOpen)
		{
			this._ensureFilterbox();
		}
	}

	/**
	 * Icon + tooltip for the filter button: filled while something is filtered, so it is visible
	 * at a glance that the list is not showing everything.
	 */
	private _filterInfo() : { active : boolean, icon : string, tooltip : string }
	{
		const values = {...(this._filters || {})};
		delete values.record_id;
		delete values.appname;
		const nonEmpty = (value : any) : boolean =>
		{
			if(value === "" || value === null || typeof value === "undefined")
			{
				return false;
			}
			if(Array.isArray(value))
			{
				return value.length > 0;
			}
			if(typeof value === "object")
			{
				return Object.values(value).some(nonEmpty);
			}
			return true;
		};
		const active = Object.values(values).some(nonEmpty);
		return {
			active: active,
			icon: active ? "filter-circle-fill" : "filter-circle",
			tooltip: this.egw().lang("Filters")
		};
	}

	/**
	 * Empty every filter, from the drawer header - same action egw-app offers.
	 *
	 * Clears the filterbox's widgets and applies, rather than calling applyFilters({}) here: the
	 * widgets have to end up empty too, or the next change would re-send whatever they still hold.
	 */
	private _clearFilters = () =>
	{
		if(!this._filterbox)
		{
			this.applyFilters({});
			return;
		}
		this._filterbox.value = {};
		this._filterbox.applyFilters();
	};

	/**
	 * Re-render the filter button when a filter changes.
	 *
	 * The filterbox's value is neither a reactive property nor reachable by Lit, so without this
	 * the button's icon keeps showing "filtered" after the filters are cleared.
	 */
	private _handleFilterboxChange(event : Event)
	{
		if(event.target !== <any>this._filterbox)
		{
			return;
		}
		this._filterbox?.updateComplete.then(() => this.requestUpdate());
	}

	/**
	 * The diff a row asked to have popped out, or null.
	 */
	@state()
	private _diff : string | null = null;

	/**
	 * Show one row's diff full-size, in a dialog.
	 *
	 * Called by `et2-historylog-value` instead of letting et2-diff open its own dialog, because
	 * that one is rendered inside the datagrid's virtualized row and comes out unusable there -
	 * `Et2HistorylogValue._handleDiffClick()` has the details.  Here the dialog is a child of the
	 * history log's shadow root, which is above the virtualizer, so it sizes to the window.
	 */
	public showDiff(value : string)
	{
		if(typeof value !== "string" || value === "")
		{
			return;
		}
		// The diff markup goes in et2-diff's light DOM, which is this shadow root
		adoptDiffStyles(this.shadowRoot);
		this._diff = value;
	}

	render()
	{
		const info = this._filterInfo();
		return html`
            <div part="header" class="historylog__header">
                <et2-button-icon
                        nosubmit
                        name=${info.icon}
                        label=${info.tooltip}
                        statustext=${info.tooltip}
                        @click=${this._toggleFilters}
                ></et2-button-icon>
            </div>
            <sl-drawer
                    part="filters"
                    exportparts="panel:filters__panel"
                    label=${info.tooltip}
                    contained
                    ?open=${this._filtersOpen}
                    @sl-after-hide=${() => { this._filtersOpen = false; }}
            >
                ${info.active ? html`
                    <et2-button-icon
                            nosubmit
                            slot="header-actions"
                            name="x-circle-fill"
                            label=${this.egw().lang("Clear filters")}
                            statustext=${this.egw().lang("Clear filters")}
                            @click=${this._clearFilters}
                    ></et2-button-icon>` : nothing}
                <div class="historylog__filters" @change=${this._handleFilterboxChange}></div>
            </sl-drawer>
            <et2-datagrid
                    part="grid"
                    exportparts="rows, row, header"
                    ._parent=${this}
                    .columns=${this._columns}
                    .templateData=${this._templateData}
                    .dataProvider=${this._dataProvider}
                    .rowStylesheets=${[rowStyles.styleSheet!]}
                    .rowCustomizer=${this._customizeRow}
                    .configurationLoading=${this._templateLoading}
                    .autoHeight=${this.autoHeight}
                    .noColumnSelection=${true}
                    .noColumnPersistence=${true}
                    row-id-field="id"
                    selection-mode="none"
                    auto-activate-first-row="false"
                    style=${styleMap({"--meta-column-width": "0px"})}
            ></et2-datagrid>
            ${this._diff === null ? nothing : html`
                <et2-dialog
                        part="diff-dialog"
                        class="historylog__diff"
                        label=${this.egw().lang("Diff")}
                        open
                        .buttons=${Et2Dialog.BUTTONS_OK}
                        @close=${() => { this._diff = null; }}
                >
                    <et2-diff .noDialog=${true} .value=${this._diff}></et2-diff>
                </et2-dialog>`}
		`;
	}

	/**
	 * Rows are top-aligned: a diff or a multi-part value is much taller than a timestamp, and
	 * centering those against it reads badly.
	 */
	private _customizeRow = (context : Et2DatagridRowCustomizeContext) =>
	{
		context.rowElement.style.alignItems = "start";
	};
}
