import {html, LitElement, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2Datagrid} from "./Et2Datagrid";
import {Et2DatagridColumn, Et2DatagridTemplateData} from "./Et2Datagrid.types";
import {Et2RowProvider} from "./Et2RowProvider";
import {Et2NextmatchDataProvider} from "./Et2NextmatchDataProvider";
import "./Headers/Header";
import "./Headers/SortableHeader";
import styles from "./Et2Nextmatch.styles";

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

	/** Public rows data. Can be set directly or via setRows(). */
	@property({type: Array})
	rows : any[] = [];

	/** Template name used to resolve columns and row layout. */
	@property({type: String})
	template : string = "";

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
		this._rowProvider = new Et2RowProvider(this as any);
		this._dataProvider = new Et2NextmatchDataProvider(this as any);
	}

	/**
	 * Attach observers once the host is connected.
	 */
	connectedCallback()
	{
		super.connectedCallback();
		this._initSlotObserver();
	}

	/**
	 * Disconnect observers to avoid stale slot reactions after the widget is detached.
	 */
	disconnectedCallback()
	{
		this._slotObserver?.disconnect();
		this._slotObserver = null;
		super.disconnectedCallback();
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
			this._applyTemplateFromSlots();
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
		this._slotObserver = new MutationObserver(() =>
		{
			if(!this.template)
			{
				this._applyTemplateFromSlots();
			}
		});
		this._slotObserver.observe(this, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ["slot"]
		});
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
	 * Slot mode is synchronous, so we can clear loading state immediately.
	 */
	private _applyTemplateFromSlots()
	{
		this._templateLoading = false;
		const templateData = this._rowProvider.fromSlots();
		if(!templateData)
		{
			this._templateData = null;
			return;
		}
		this._applyTemplateData(templateData);
	}

	/**
	 * Apply resolved template data and normalize final column source.
	 * If no columns were parsed we keep externally-set columns as fallback.
	 */
	private _applyTemplateData(templateData : Et2DatagridTemplateData)
	{
		const columns = templateData.columns?.length ? templateData.columns : this._columns;
		this._columns = columns || [];
		this._templateData = {
			...templateData,
			columns: this._columns
		};
	}

	/**
	 * Render the orchestration shell.
	 * We explicitly set `._parent` so Et2Datagrid can participate in Et2Widget array manager lookup.
	 */
	render()
	{
		return html`
				<et2-datagrid
					._parent=${this}
					.columns=${this._columns}
					.templateData=${this._templateData}
					.dataProvider=${this._dataProvider}
					.configurationLoading=${this._templateLoading}
					selection-mode="multiple"
				></et2-datagrid>
		`;
	}
}
