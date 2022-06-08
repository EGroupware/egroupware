/**
 * EGroupware eTemplate2 - Select WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, PropertyValues, TemplateResult} from "@lion/core";
import {cssImage} from "../Et2Widget/Et2Widget";
import {StaticOptions} from "./StaticOptions";
import {Et2widgetWithSelectMixin} from "./Et2WidgetWithSelectMixin";
import {SelectOption} from "./FindSelectOptions";
import {Et2InvokerMixin} from "../Et2Url/Et2InvokerMixin";
import {SlSelect} from "@shoelace-style/shoelace";
import {egw} from "../../jsapi/egw_global";
import shoelace from "../Styles/shoelace";
import {Et2WithSearchMixin} from "./SearchMixin";

// export Et2WidgetWithSelect which is used as type in other modules
export class Et2WidgetWithSelect extends Et2widgetWithSelectMixin(SlSelect)
{
};

export class Et2Select extends Et2WithSearchMixin(Et2InvokerMixin(Et2WidgetWithSelect))
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			css`
			:host {
				display: block;
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
			
			/* This is the drop-down arrow on the right */
			::slotted([slot="suffix"]) {
				font-size: 120% !important;
				font-weight: bold;
				color: gray !important;
			}

			select:hover {
				box-shadow: 1px 1px 1px rgb(0 0 0 / 60%);
			}`
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
			},
			/**
			 * Add a button to switch to multiple with given number of rows
			 */
			expand_multiple_rows: {
				type: Number,
			}
		}
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

	constructor()
	{
		super();
		this._triggerChange = this._triggerChange.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		//MOVE options that were set as children inside SELECT:
		//this.querySelector('select').append(...this.querySelectorAll('option'));

		this.getUpdateComplete().then(() =>
		{
			this.addEventListener("sl-clear", this._triggerChange)
			this.addEventListener("sl-change", this._triggerChange);
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this.removeEventListener("sl-clear", this._triggerChange)
		this.removeEventListener("sl-change", this._triggerChange);
	}

	firstUpdated(changedProperties)
	{
		super.firstUpdated(changedProperties);

		// If no value is set, choose the first option
		// Only do this on firstUpdated() otherwise it is impossible to clear the field
		const valueArray = Array.isArray(this.value) ? this.value : (!this.value ? [] : this.value.toString().split(','));
		// value not in options AND NOT (having an empty label and value)
		if(!this.multiple && this.select_options.length > 0 && this.select_options.filter((option) => valueArray.find(val => val == option.value)).length === 0 &&
			!(typeof this.empty_label !== 'undefined' && (this.value || "") === ""))
		{
			// --> use first option
			this.value = "" + this.select_options[0]?.value;	// ""+ to cast value of 0 to "0", to not replace with ""
		}
	}

	_triggerChange(e)
	{
		this.dispatchEvent(new Event("change"));
	}

	/**
	 * Get the node where we're putting the selection options
	 *
	 * @returns {HTMLElement}
	 */
	get _optionTargetNode() : HTMLElement
	{
		return <HTMLElement><unknown>this;
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

	set_value(val : string | string[])
	{
		this.value = val;
	}

	/**
	 * Reimplemented to allow/keep string[] as value
	 *
	 * @param value string|string[]
	 */
	_callParser(value = this.formattedValue)
	{
		if(this.multiple && Array.isArray(value))
		{
			return value;
		}
		return super._callParser(value);
	}

	private _set_invoker(rows)
	{
		this._invokerAction = () => {
			this.multiple = true;
			this._inputNode.size = parseInt(rows) || 4;
			this._invokerNode.style.display = 'none';
		}
		this._invokerTitle = egw.lang('Switch to multiple');
		this._invokerLabel = '+';
	}

	transformAttributes(attrs)
	{
		if (attrs.expand_multiple_rows)
		{
			this._set_invoker(attrs.expand_multiple_rows);
		}
		super.transformAttributes(attrs);
	}

	set expand_multiple_rows(rows)
	{
		if (rows && !this.multiple)
		{
			this._set_invoker(rows);
		}
		else
		{
			this._invokerLabel = undefined;
		}
		this.__expand_multiple_rows = rows;
	}

	get expand_multiple_rows()
	{
		return this.__expand_multiple_rows;
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
			// Re-set value, the option for it may have just shown up
			this.value = this.value || "";
		}

		// propagate multiple to selectbox
		if (changedProperties.has('multiple'))
		{
			// switch the expand button off
			if (this.multiple)
			{
				this.expand_multiple_rows = 0;
			}
		}
	}

	_emptyLabelTemplate() : TemplateResult
	{
		if(!this.empty_label || this.multiple)
		{
			return html``;
		}
		return html`
            <sl-menu-item value="">${this.empty_label}</sl-menu-item>`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" src="${option.icon}"></et2-image>` : "";

		return html`
            <sl-menu-item value="${option.value}" title="${option.title}" class="${option.class}">
                ${icon}
                ${option.label}
            </sl-menu-item>`;
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
	constructor()
	{
		super();

		this.select_options = so.app(this, {other: this.other || []});
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
		let options = this.select_options;
		for(let index in options)
		{
			let right = parseInt(options[index].value);
			if(!!(new_value & right))
			{
				expanded_value.push(right);
			}
		}
		super.value = expanded_value;

		this.requestUpdate("value", oldValue);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bitwise", Et2SelectBitwise);

export class Et2SelectBool extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.bool(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-bool", Et2SelectBool);

export class Et2SelectCategory extends Et2Select
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				::slotted(*) {
					border-left: 3px solid transparent;
					}
			`
		]
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Include global categories
			 */
			global_categories: {type: Boolean},
			/**
			 * Show categories from this application.  If not set, will be the current application
			 */
			application: {type: String},
			/**
			 * Show categories below this parent category
			 */
			parent_cat: {type: Number}
		}
	}

	constructor()
	{
		super();

		this.select_options = so.cat(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-cat", Et2SelectCategory);

export class Et2SelectCountry extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.country(this, {});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-country", Et2SelectCountry);

export class Et2SelectDay extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.day(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-day", Et2SelectDay);

export class Et2SelectDayOfWeek extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.dow(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-dow", Et2SelectDayOfWeek);

export class Et2SelectHour extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.hour(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-hour", Et2SelectHour);

export class Et2SelectMonth extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.month(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-month", Et2SelectMonth);

export class Et2SelectNumber extends Et2Select
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Step between numbers
			 */
			interval: {type: Number},
			min: {type: Number},
			max: {type: Number},

			/**
			 * Add one or more leading zeros
			 * Set to how many zeros you want (000)
			 */
			leading_zero: {type: String},
			/**
			 * Appended after every number
			 */
			suffix: {type: String}
		}
	}

	constructor()
	{
		super();
		this.min = 1;
		this.max = 10;
		this.interval = 1;
		this.leading_zero = "";
		this.suffix = "";
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') || changedProperties.has('suffix'))
		{
			this.select_options = so.number(this);
		}
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-number", Et2SelectNumber);

export class Et2SelectPercent extends Et2SelectNumber
{
	constructor()
	{
		super();
		this.min = 0;
		this.max = 100;
		this.interval = 10;
		this.suffix = "%%";
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-percent", Et2SelectPercent);

export class Et2SelectPriority extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.priority(this);
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-priority", Et2SelectPriority);

export class Et2SelectState extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.state(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-state", Et2SelectState);

export class Et2SelectTimezone extends Et2Select
{
	constructor()
	{
		super();

		this.select_options = so.timezone(this, {other: this.other || []});
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-timezone", Et2SelectTimezone);

export class Et2SelectYear extends Et2SelectNumber
{
	constructor()
	{
		super();
		this.min = -3;
		this.max = 2;
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') || changedProperties.has('suffix'))
		{
			this.select_options = so.year(this);
		}
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-select-year", Et2SelectYear);