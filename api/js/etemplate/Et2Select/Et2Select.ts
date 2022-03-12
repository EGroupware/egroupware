/**
 * EGroupware eTemplate2 - Select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {LionSelect} from "@lion/select";
import {css, html, PropertyValues, TemplateResult} from "@lion/core";
import {cssImage} from "../Et2Widget/Et2Widget";
import {StaticOptions} from "./StaticOptions";
import {Et2widgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {SelectOption} from "./FindSelectOptions";
import {Et2InvokerMixin} from "../Et2Url/Et2InvokerMixin";

// export Et2WidgetWithSelect which is used as type in other modules
export class Et2WidgetWithSelect extends Et2widgetWithSelectMixin(LionSelect){};

export class Et2Select extends Et2InvokerMixin(Et2WidgetWithSelect)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				display: inline-block;
			}
			select {
				width: 100%
				color: var(--input-text-color, #26537c);
				border-radius: 3px;
				flex: 1 0 auto;
				padding-top: 4px;
				padding-bottom: 4px;
				padding-right: 20px;
				border-width: 1px;
				border-style: solid;
				border-color: #e6e6e6;
				-webkit-appearance: none;
				-moz-appearance: none;
				margin: 0;
				background: #fff no-repeat center right;
				background-image: ${cssImage('arrow_down')};
				background-size: 8px auto;
				background-position-x: calc(100% - 8px);
				text-indent: 5px;
			}
			
			::slotted([slot="suffix"]) {
				font-size: 120% !important;
				font-weight: bold;
				color: gray !important;
				position: relative;
				left: -2px;
				top: -2px;
			}

			select:hover {
				box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
			}`
		];
	}

	get slots()
	{
		return {
			...super.slots,
			input: () =>
			{
				return document.createElement("select");
			}
		}
	}

	connectedCallback()
	{
		super.connectedCallback();
		//MOVE options that were set as children inside SELECT:
		this.querySelector('select').append(...this.querySelectorAll('option'));

		// if _inputNode was not available by the time set_value() got called
		if(this.getValue() !== this.modelValue)
		{
			this.set_value(this.modelValue);
		}
	}

	/**
	 * Get the node where we're putting the selection options
	 *
	 * @returns {HTMLElement}
	 */
	get _optionTargetNode() : HTMLElement
	{
		return this._inputNode;
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
			},
			/**
			 * Add a button to switch to multiple with given number of rows
			 */
			expand_multiple_rows: {
				type: Number,
			}
		}
	}

	/**
	 * @deprecated use this.multiple = multi
	 *
	 * @param multi
	 */
	set_multiple(multi)
	{
		this.multiple = multi;
	}

	set expand_multiple_rows(rows)
	{
		if (rows && !this.multiple)
		{
			this._invokerAction = () => {
				this.multiple = true;
				this._inputNode.size = parseInt(rows) || 4;
				this._invokerNode.style.display = 'none';
			}
			this._invokerTitle = egw.lang('Switch to multiple');
			this._invokerLabel = '+';
		}
		else
		{
			this._invokerLabel = undefined;
		}
	}

	/**
	 * Method to check if invoker can be activated: not disabled, empty or invalid
	 *
	 * Overwritten to NOT disable if empty.
	 *
	 * @protected
	 * */
	_toggleInvokerDisabled()
	{
		if (this._invokerNode)
		{
			const invokerNode = /** @type {HTMLElement & {disabled: boolean}} */ (this._invokerNode);
			invokerNode.disabled = this.disabled || this.hasFeedbackFor.length > 0;
		}
	}

	/** @param {import('@lion/core').PropertyValues } changedProperties */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('select_options') || changedProperties.has("value") || changedProperties.has('empty_label'))
		{
			// value not in options AND NOT (having an empty label and value)
			if(this.get_select_options().length > 0 && this.get_select_options().filter((option) => option.value == this.modelValue).length === 0 &&
				!(typeof this.empty_label !== 'undefined' && (this.modelValue || "") === ""))
			{
				// --> use first option
				this.modelValue = "" + this.get_select_options()[0]?.value;	// ""+ to cast value of 0 to "0", to not replace with ""
			}
			// Re-set value, the option for it may have just shown up
			this._inputNode.value = this.modelValue || "";
		}

		// propagate multiple to selectbox
		if (changedProperties.has('multiple'))
		{
			this._inputNode.multiple = this.multiple;
			// switch the expand button off
			if (this.multiple)
			{
				this.expand_multiple_rows = 0;
			}
		}
	}

	_emptyLabelTemplate() : TemplateResult
	{
		if(!this.empty_label)
		{
			return html``;
		}
		return html`
            <option value="" ?selected=${!this.modelValue}>${this.empty_label}</option>`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		return html`
            <option value="${option.value}" title="${option.title}" ?selected=${option.value == this.modelValue}>
                ${option.label}
            </option>`;
	}
}

/**
 * Use a single StaticOptions, since it should have no state
 * @type {StaticOptions}
 */
const so = new StaticOptions();

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select", Et2Select);

export class Et2SelectApp extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.app(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-app", Et2SelectApp);

export class Et2SelectBitwise extends Et2Select
{
	set value(new_value)
	{
		let oldValue = this._value;
		let expanded_value = [];
		let options = this.get_select_options();
		for(let index in options)
		{
			let right = parseInt(options[index].value);
			if(!!(new_value & right))
			{
				expanded_value.push(right);
			}
		}
		this.modelValue = expanded_value;

		this.requestUpdate("value", oldValue);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bitwise", Et2SelectBitwise);

export class Et2SelectBool extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.bool(this);
	}

}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bool", Et2SelectBool);

export class Et2SelectCategory extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.cat(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-cat", Et2SelectCategory);

export class Et2SelectPercent extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.percent(this, {});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-percent", Et2SelectPercent);

export class Et2SelectCountry extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.country(this, {});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-country", Et2SelectCountry);

export class Et2SelectDay extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.day(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-day", Et2SelectDay);

export class Et2SelectDayOfWeek extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.dow(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-dow", Et2SelectDayOfWeek);

export class Et2SelectHour extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.hour(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-hour", Et2SelectHour);

export class Et2SelectMonth extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.month(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-month", Et2SelectMonth);

export class Et2SelectNumber extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.number(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-number", Et2SelectNumber);

export class Et2SelectPriority extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.priority(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-priority", Et2SelectPriority);

export class Et2SelectState extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.state(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-state", Et2SelectState);

export class Et2SelectTimezone extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.timezone(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-timezone", Et2SelectTimezone);

export class Et2SelectYear extends Et2Select
{
	get_select_options() : SelectOption[]
	{
		return so.year(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-year", Et2SelectYear);