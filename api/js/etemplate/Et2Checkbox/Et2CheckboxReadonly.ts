import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {css, html, LitElement} from "lit";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js"
import shoelace from "../Styles/shoelace";

/**
 * et2_checkbox_ro is the dummy readonly implementation of the checkbox
 * @augments et2_checkbox
 */
export class Et2CheckboxReadonly extends Et2InputWidget(LitElement) implements et2_IDetachedDOM
{
	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
			:host {
				margin: auto 0px;
				vertical-align: -webkit-baseline-middle;
			}
            `,
		];
	}

	/**
	 *  Checkbox is checked
	 *
	 * Internal only - not set via any .xet attribute or external JS; the
	 * detached-rendering contract (getDetachedAttributes() below) deliberately
	 * excludes it, deriving isChecked from value/selectedValue instead.
	 */
	@state()
	checked : boolean;

	/**
	 * The checkbox's value attribute
	 */
	@property({type: String})
	value : string;

	/* Value when checked */
	@property({type: String})
	selectedValue : string;

	/**
	 * What should be displayed when readonly and selected
	 */
	@property({type: String})
	roTrue : string;

	/**
	 * What should be displayed when readonly and not selected
	 */
	@property({type: String})
	roFalse : string;

	render()
	{
		const isChecked = this.checked ||
			// selectedValue is set, so only a value matching that counts as checked
			typeof this.selectedValue == "string" && this.value == this.selectedValue ||
			// selectedValue is not set, any truthy value counts as checked
			typeof this.selectedValue === "undefined" && this.value;
		let check = "";

		if(isChecked && this.roTrue)
		{
			check = this.roTrue;
		}
		else if(isChecked)
		{
			check = html`
                <sl-icon name="check"></sl-icon>`;
		}
		else if(!isChecked && this.roFalse)
		{
			check = this.roFalse;
		}

		return html`
            <label part="base" class=${classMap({
                checkbox: true,
                'checkbox--checked': this.checked,
                'checkbox--disabled': this.disabled,
                'checkbox--focused': this.hasFocus,
                'checkbox--indeterminate': this.indeterminate
            })}
            >
                <span part="control" class="checkbox__control">${check}</span>
                <span part="label" class="checkbox__label">
				  <slot>${this.label}</slot>
				</span>
            </label>
		`;
	}

	getDetachedAttributes(_attrs : string[]) : void
	{
		_attrs.push("value", "class", "statustext");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data?) : void
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}

}

customElements.define("et2-checkbox_ro", Et2CheckboxReadonly);