import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {et2_checkbox} from "../et2_widget_checkbox";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {css, html, LitElement} from "lit";
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

	static get properties()
	{
		return {
			...super.properties,

			/**
			 *  Checkbox is checked
			 */
			checked: {type: Boolean},

			/**
			 * The checkbox's value attribute
			 */
			value: {type: String},

			/* Value when checked */
			selectedValue: {type: String},

			/**
			 * What should be displayed when readonly and selected
			 */
			roTrue: {type: String},
			/**
			 * What should be displayed when readonly and not selected
			 */
			roFalse: {type: String}
		};
	}

	constructor() {super();}

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