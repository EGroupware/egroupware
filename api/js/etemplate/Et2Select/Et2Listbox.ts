import {SlMenu} from "@shoelace-style/shoelace";
import {Et2widgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import shoelace from "../Styles/shoelace";
import {css, html, TemplateResult} from "@lion/core";
import {SelectOption} from "./FindSelectOptions";

/**
 * A selectbox that shows more than one row at a time
 *
 * Set rows attribute to adjust how many rows are visible at once
 *
 * Use Et2Selectbox in most cases, it's better.
 */
export class Et2Listbox extends RowLimitedMixin(Et2widgetWithSelectMixin(SlMenu))
{

	static get styles()
	{
		return [
			// Parent (SlMenu) returns a single cssResult, not an array
			shoelace,
			super.styles,
			css`
			:host {
				display: block;
				flex: 1 0 auto;
				--icon-width: 20px;
			}
			
			::slotted(img), img {
				vertical-align: middle;
			}
			
			.menu {
				/* Get rid of padding before/after options */
				padding: 0px;
			
				/* No horizontal scrollbar, even if options are long */
				overflow-x: clip;
			}
			/* Ellipsis when too small */
			sl-menu-item.menu-item__label {
				display: block;
    			text-overflow: ellipsis;
    			/* This is usually not used due to flex, but is the basis for ellipsis calculation */
    			width: 10ex;
			}
			
			:host([rows])::part(base) {
				height: calc(var(--rows, 5) * 1.9rem);
				overflow-y: auto;
			}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Toggle between single and multiple selection
			 */
			multiple: {
				type: Boolean,
				reflect: true,
			}
		}
	}

	private __value : String[] | null;

	constructor(...args : any[])
	{
		super();
		this.handleSelect = this.handleSelect.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		this.addEventListener("sl-select", this.handleSelect);

		this.updateComplete.then(() =>
		{
			this.addEventListener("sl-change", this._triggerChange);
		});
	}

	/**
	 * Handle an item was selected
	 *
	 * Toggle the checkmark and fire the changed event
	 *
	 * @param {MouseEvent} event
	 */
	handleSelect(event : CustomEvent)
	{
		let item = event.detail?.item;
		if(!item)
		{
			return;
		}

		if(!this.multiple)
		{
			this.getAllItems().forEach((i) => i.checked = false);
			item.checked = true;
		}
		else
		{
			item.checked = !item.checked;
		}

		this.dispatchEvent(new Event("change"));
	}

	get value()
	{
		let value = [];
		if(this.defaultSlot)
		{
			value = this.getAllItems()
				.filter((item) => item.checked)
				.map((item) => item.value);
		}
		return this.multiple ? value : value.pop();
	}

	set value(new_value : String[] | String)
	{
		const oldValue = this.value;
		if(typeof new_value == "string")
		{
			new_value = [new_value]
		}
		this.__value = <String[]>new_value;
		this.requestUpdate("value", oldValue);
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" part="icon" style="width: var(--icon-width)"
                       src="${option.icon}"></et2-image>` : "";

		let checked = this.__value == null ?
					  option.value === this.value || this.multiple && this.value.indexOf(option.value) >= 0 :
					  this.__value.indexOf(option.value) >= 0;

		// Tag used must match this.optionTag, but you can't use the variable directly.
		// Pass option along so SearchMixin can grab it if needed
		return html`
            <sl-menu-item
                    value="${option.value}"
                    title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                    class="${option.class}" .option=${option}
                    ?checked=${checked}
            >
                ${icon}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-menu-item>`;
	}
}

customElements.define("et2-listbox", Et2Listbox);