import {Et2WidgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import shoelace from "../Styles/shoelace";
import {css, html, LitElement, TemplateResult} from "lit";
import {SelectOption} from "./FindSelectOptions";
import {repeat} from "lit/directives/repeat.js";
import {property} from "lit/decorators/property.js";
import {SlMenuItem} from "@shoelace-style/shoelace";

/**
 * A selectbox that shows more than one row at a time
 *
 * Set rows attribute to adjust how many rows are visible at once
 *
 * Use Et2Selectbox in most cases, it's better.
 */
export class Et2Listbox extends RowLimitedMixin(Et2WidgetWithSelectMixin(LitElement))
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

			  sl-option.option__label {
				display: block;
    			text-overflow: ellipsis;
    			/* This is usually not used due to flex, but is the basis for ellipsis calculation */
    			width: 10ex;
			}

			  :host([rows]) .menu {
				height: calc(var(--rows, 5) * 1.9rem);
				overflow-y: auto;
			}
			`
		];
	}

	@property({type: Boolean, reflect: true}) multiple = false;

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

	private getAllItems() : SlMenuItem[]
	{
		return <SlMenuItem[]>Array.from(this.shadowRoot?.querySelectorAll('sl-menu-item')) ?? [];
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

	@property()
	get value()
	{
		let value = this.hasUpdated ? this.getAllItems()
				.filter((item) => item.checked)
			.map((item) => item.value) : this.__value ?? []
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
                    type="checkbox"
                    ?checked=${checked}
            >
                ${icon}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-menu-item>`;
	}

	render()
	{
		return html`
            <sl-menu class="menu">
                ${repeat(this.select_options, (o) => o.value, (option : SelectOption) => this._optionTemplate(option))}
            </sl-menu>
		`
	}
}

customElements.define("et2-listbox", Et2Listbox);