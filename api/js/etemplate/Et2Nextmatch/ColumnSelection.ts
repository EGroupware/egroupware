/**
 * Column selector for nextmatch
 */
import {classMap, css, html, LitElement, PropertyValues, repeat, TemplateResult} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {et2_nextmatch_customfields} from "../et2_extension_nextmatch";
import shoelace from "../Styles/shoelace";
import {et2_dataview_column} from "../et2_dataview_model_columns";
import {et2_customfields_list} from "../et2_extension_customfields";
import Sortable from "sortablejs/modular/sortable.complete.esm";
import {cssImage} from "../Et2Widget/Et2Widget";
import {SlMenuItem} from "@shoelace-style/shoelace";
import {Et2Select} from "../Et2Select/Et2Select";

export class Et2ColumnSelection extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			// Parent (SlSelect) returns a single cssResult, not an array
			super.styles,
			shoelace,
			css`
			:host {
				max-height: inherit;
				display: flex;
				flex-direction: column;
				flex: 1 1 auto;
				--icon-width: 20px;
			}
			.title {
				font-size: var(--sl-font-size-large);
				color: var(--sl-color-neutral-0);
				background-color: var(--sl-color-primary-600);
				flex: 1 1 auto;
			}
			.title sl-icon {
				vertical-align: middle;
				cursor: pointer;
			}
			sl-menu {
				flex: 1 10 auto;
				overflow-y: auto;
			}
			/* Drag handle on columns (not individual custom fields or search letter) */
			sl-menu > .select_row::part(base) {
				padding-left: 10px;
			}
			sl-menu > .column::part(base) {
				background-image: ${cssImage("splitter_vert")};
				background-position: 3px 1.5ex;
				background-repeat: no-repeat;
				cursor: grab;
			}
			/* Change vertical alignment of CF checkbox line to up with title, not middle */
			.custom_fields::part(base) {
				align-items: baseline;
			}
			`
		]
	}

	static get properties()
	{
		return {
			/**
			 * List of currently selected columns
			 */
			value: {type: Object},

			autoRefresh: {type: Number}
		}
	}

	private __columns = [];
	private __autoRefresh : number | false = false;
	private sort : Sortable;

	constructor(...args : any[])
	{
		super(...args);

		this.columnClickHandler = this.columnClickHandler.bind(this);
		this.handleSelectAll = this.handleSelectAll.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		this.updateComplete.then(() =>
		{
			this.sort = Sortable.create(this.shadowRoot.querySelector('sl-menu'), {
				ghostClass: 'ui-fav-sortable-placeholder',
				draggable: 'sl-menu-item.column',
				dataIdAttr: 'value',
				direction: 'vertical',
				delay: 25
			});
		});
	}

	protected firstUpdated(_changedProperties : PropertyValues)
	{
		super.firstUpdated(_changedProperties);

		if(this._autoRefreshNode)
		{
			this._autoRefreshNode.select_options = {
				// Cause [unknown] problems with mail
				30: "30 seconds",
				//60: "1 Minute",
				180: "3 Minutes",
				300: "5 Minutes",
				900: "15 Minutes",
				1800: "30 Minutes"
			};
		}

		if(this._preferenceNode)
		{
			this._preferenceNode.select_options = [
				{value: 'default', label: 'Default', title: 'Set these columns as the default'},
				{value: 'reset', label: 'Reset', title: "Reset all user's column preferences"},
				{value: 'force', label: 'Force', title: 'Force column preference so users cannot change it'}
			];
		}
	}

	protected render() : TemplateResult
	{
		return html`${this.headerTemplate()}
        <sl-menu @sl-select="${this.columnClickHandler}" part="columns">
            ${repeat(this.__columns, (column) => column.id, (column) => this.rowTemplate(column))}
        </sl-menu>
        <et2-hbox class="dialogFooterToolbar">
            <slot name="buttons"></slot>
            ${this.footerTemplate()}
        </et2-hbox>`;
	}

	protected headerTemplate()
	{
		return html`
            <div class="title">
                <sl-icon name="check-all" @click=${this.handleSelectAll}></sl-icon>
                ${this.egw().lang("Select columns")}
            </div>
		`;
	}

	protected footerTemplate()
	{
		let autoRefresh = html`
            <et2-select id="nm_autorefresh" empty_label="Refresh" statustext="Automatically refresh list"
                        value="${this.__autoRefresh}">
            </et2-select>
		`;
		// Add default checkbox for admins
		const apps = this.egw().user('apps');

		return html`
            ${this.__autoRefresh !== false ? autoRefresh : ''}
            ${!apps['admin'] ? '' : html`
                <et2-select id="default_preference" empty_label="${this.egw().lang("Preference")}">
                </et2-select>`
            }
		`;
	}

	/**
	 * Template for each individual column
	 *
	 * @param column
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected rowTemplate(column) : TemplateResult
	{
		let isCustom = column.widget?.instanceOf(et2_nextmatch_customfields) || false;
		/*     ?disabled=${column.visibility == et2_dataview_column.ET2_COL_VISIBILITY_DISABLED} */
		return html`
            <sl-menu-item
                    value="${column.id}"
                    ?checked=${column.visibility == et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE}

                    title="${column.title}"
                    class="${classMap({
                        select_row: true,
                        custom_fields: isCustom,
                        column: column.widget
                    })}">
                ${column.caption}
                <!-- Custom fields get listed separately -->
                ${isCustom ? this.customFieldsTemplate(column) : ''}
            </sl-menu-item>`;
	}

	/**
	 * Template for all custom fields
	 * Does not include "Custom fields", it's done as a regular column
	 *
	 * @param column
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected customFieldsTemplate(column) : TemplateResult
	{
		// Custom fields get listed separately
		let widget = column.widget;
		if(jQuery.isEmptyObject((<et2_nextmatch_customfields><unknown>widget).customfields))
		{
			// No customfields defined, don't show column
			return html``;
		}
		return html`
            <sl-divider></sl-divider>
            ${repeat(Object.values(widget.customfields), (field) => field.name, (field) =>
            {
                return this.rowTemplate({
                    id: et2_customfields_list.PREFIX + field.name,
                    caption: field.label,
                    visibility: (widget.fields[field.name] ? et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE : false)

                });
            })}
            <sl-divider></sl-divider>`;
	}

	columnClickHandler(event)
	{
		const item = event.detail.item;

		// Toggle checked state
		item.checked = !item.checked;
	}

	handleSelectAll(event)
	{
		let checked = (<SlMenuItem>this.shadowRoot.querySelector("sl-menu-item")).checked || false;
		this.shadowRoot.querySelectorAll('sl-menu-item').forEach((item) => {item.checked = !checked});
	}

	set columns(new_columns)
	{
		this.__columns = new_columns;
		this.requestUpdate();
	}

	get value()
	{
		let value = [];

		this.sort.toArray().forEach((val) =>
		{
			let column = this.__columns.find((col) => col.id == val);
			let menuItem = <SlMenuItem>this.shadowRoot.querySelector("[value='" + val + "']");
			if(column && menuItem)
			{
				if(menuItem.checked)
				{
					value.push(val);
				}
				if(column.widget?.customfields)
				{
					menuItem.querySelectorAll("[value][checked]").forEach((cf : SlMenuItem) =>
					{
						value.push(cf.value);
					})
				}
			}
		});
		return value;
	}

	private get _autoRefreshNode() : Et2Select
	{
		return (<Et2Select>this.shadowRoot?.querySelector("#nm_autorefresh"));
	}

	private get _preferenceNode() : Et2Select
	{
		return (<Et2Select>this.shadowRoot.querySelector("#default_preference"))
	}

	get autoRefresh() : number
	{
		return parseInt(this._autoRefreshNode?.value.toString()) || 0;
	}

	set autoRefresh(new_value : number)
	{
		this.__autoRefresh = new_value;
		this.requestUpdate("autoRefresh");
	}
}

customElements.define("et2-nextmatch-columnselection", Et2ColumnSelection);