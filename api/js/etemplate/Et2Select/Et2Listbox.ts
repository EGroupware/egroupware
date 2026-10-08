import {Et2WidgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {RowLimitedMixin} from "../Layout/RowLimitedMixin";
import shoelace from "../Styles/shoelace";
import {html, LitElement, nothing, TemplateResult} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {SelectOption} from "./FindSelectOptions";
import {repeat} from "lit/directives/repeat.js";
import {property} from "lit/decorators/property.js";
import {SlMenuItem} from "@shoelace-style/shoelace";

import styles from "./Et2Listbox.styles";
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
			styles
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
		// Which input was used last decides if the focused item gets the primary highlight, see the styles.
		// Focus alone cannot: the menu focuses whatever the pointer passes over, and that focus stays when it leaves.
		this.addEventListener("keydown", this._markKeyboard, true);
		this.addEventListener("pointermove", this._markPointer, true);
		this.addEventListener("pointerdown", this._markPointer, true);

		this.updateComplete.then(() =>
		{
			this.addEventListener("sl-change", this._triggerChange);
		});
	}

	private _markKeyboard = () => this.setAttribute("keyboard", "");
	private _markPointer = () => this.removeAttribute("keyboard");

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("keydown", this._markKeyboard, true);
		this.removeEventListener("pointermove", this._markPointer, true);
		this.removeEventListener("pointerdown", this._markPointer, true);
	}

	private getAllItems(): SlMenuItem[]
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
		if(typeof new_value == "string")
		{
			new_value = [new_value]
		}
		this.__value = <String[]>new_value;
		// Not requestUpdate("value", oldValue): once hasUpdated, the getter derives its result
		// from the rendered <sl-menu-item>s' checked state, which hasn't re-rendered yet at this
		// point - so this.value (used for oldValue) still reads the pre-update result, equal to
		// itself, and Lit's hasChanged() sees "no change" and skips the render that would
		// actually update the checked items. An unconditional requestUpdate() always schedules it.
		this.requestUpdate();
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" part="icon"
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
                    ?disabled=${this.disabled}
            >
                ${icon}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-menu-item>`;
	}

	render()
	{
		const label = this._labelTemplate();
		const help = this._helpTextTemplate();

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        "form-control": true,
                        "form-control--medium": true,
                        "form-control--has-label": label !== nothing,
                        "form-control--has-help-text": help !== nothing
                    })}
            >
                ${label}
                <div part="form-control-input" class="form-control-input">
                    <sl-menu class="menu"
                             aria-labelledby=${label !== nothing ? "label" : nothing}
                             aria-describedby=${help !== nothing ? "help-text" : nothing}>
                        ${repeat(this.select_options, (o) => o.value, (option : SelectOption) => this._optionTemplate(option))}
                    </sl-menu>
                </div>
                ${help}
            </div>
		`
	}
}

customElements.define("et2-listbox", Et2Listbox);