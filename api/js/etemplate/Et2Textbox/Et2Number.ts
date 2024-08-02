/**
 * EGroupware eTemplate2 - Number widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

import {Et2Textbox} from "./Et2Textbox";
import {css, html, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {number} from "prop-types";


/**
 * @summary Enter a numeric value.  Number formatting comes from preferences by default
 * @since 23.1
 *
 * @dependency sl-input
 *
 * @slot label - The input's label. Alternatively, you can use the `label` attribute.
 * @slot prefix - Used to prepend a presentational icon or similar element to the combobox.
 * @slot suffix - Like prefix, but after
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 *
 * @event change - Emitted when the control's value changes.
 *
 * @csspart form-control - The form control that wraps the label, input, and help text.
 * @csspart form-control-label - The label's wrapper.
 * @csspart form-control-input - The input's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 */

@customElement("et2-number")
export class Et2Number extends Et2Textbox
{
	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			css`
			  /* Scroll buttons */

				:host(:hover) et2-button-scroll {
					visibility: visible;
				}

				et2-button-scroll {
					visibility: hidden;
					padding: 0px;
					margin: 0px;
					margin-left: var(--sl-spacing-small);
				}

				.form-control-input {
					min-width: min-content;
					max-width: 7em;
				}

			`,
		];
	}

	/**
	 * Minimum value
	 */
	@property({type: Number})
	min;

	/**
	 * Maximum value
	 */
	@property({type: Number})
	max;

	/**
	 * Step value
	 */
	@property({type: Number})
	step;


	/**
	 * Precision of float number or 0 for integer
	 */
	@property({type: Number})
	precision;

	/**
	 * Thousands separator.  Defaults to user preference.
	 */
	@property()
	thousandsSeparator;

	/**
	 * Decimal separator.  Defaults to user preference.
	 */
	@property()
	decimalSeparator;

	/**
	 * Text placed before the value
	 * @type {string}
	 */
	@property()
	prefix = "";

	/**
	 * Text placed after the value
	 * @type {string}
	 */
	@property()
	suffix = "";

	inputMode = "numeric";

	get _inputNode() {return this.shadowRoot.querySelector("input");}

	constructor()
	{
		super();

		this.handleScroll = this.handleScroll.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		let numberFormat = ".";
		if(this.egw() && this.egw().preference)
		{
			numberFormat = this.egw().preference("number_format", "common") ?? ".";
		}
		const decimal = numberFormat ? numberFormat[0] : '.';
		const thousands = numberFormat ? numberFormat[1] : '';
		this.decimalSeparator = this.decimalSeparator || decimal || ".";
		this.thousandsSeparator = this.thousandsSeparator || thousands || "";
	}

	firstUpdated()
	{
		super.firstUpdated();

		// Add content to slots
		["prefix", "suffix"].forEach(slot =>
		{
			if(!this[slot])
			{
				return;
			}
			this.append(Object.assign(document.createElement("span"), {
				slot: slot,
				textContent: this[slot]
			}));
		});
	}

	transformAttributes(attrs)
	{
		if(attrs.precision === 0 && typeof attrs.step === 'undefined')
		{
			attrs.step = 1;
		}
		if(typeof attrs.validator === 'undefined')
		{
			attrs.validator = attrs.precision === 0 ? '/^-?[0-9]*$/' : '/^-?[0-9]*[,.]?[0-9]*$/';
		}
		super.transformAttributes(attrs);
	}

	/**
	 * Somehow the setter is not inherited from the parent, not defining it here leaves the validator a string!
	 *
	 * @param regexp
	 */
	set validator(regexp)
	{
		super.validator = regexp;
	}

	get validator()
	{
		return super.validator;
	}

	handleInput()
	{
		// Do nothing
	}

	@property({type: String})
	set value(val)
	{
		if("" + val !== "")
		{
			// use decimal separator from user prefs
			const format = this.egw().preference('number_format');
			const sep = format ? format[0] : '.';

			// Remove separator so parseFloat works
			if(typeof val === 'string')
			{
				val = val.replace(",", '.');
			}

			if(typeof this.precision !== 'undefined')
			{
				val = parseFloat(val).toFixed(this.precision);
			}
			else
			{
				val = parseFloat(val);
			}
		}
		super.value = val;
	}

	get value() : string
	{
		return super.value;
	}

	getValue() : any
	{
		if(this.value == "" || typeof this.value == "undefined")
		{
			return "";
		}
		// Needs to be string to pass validator
		return "" + this.valueAsNumber;
	}

	get valueAsNumber() : number
	{
		let formattedValue = this._mask?.unmaskedValue ?? this.value;
		if(typeof this.precision !== 'undefined')
		{
			formattedValue = parseFloat(parseFloat(<string>formattedValue).toFixed(this.precision));
		}
		else
		{
			formattedValue = parseFloat(<string>formattedValue);
		}
		return formattedValue;
	}

	/**
	 * Remove special formatting from a string to get just a number value
	 * @param {string | number} formattedValue
	 * @returns {number}
	 */
	stripFormat(formattedValue : string | number)
	{
		if("" + formattedValue !== "")
		{
			// remove thousands separator
			if(typeof formattedValue === "string" && this.thousandsSeparator)
			{
				formattedValue = formattedValue.replaceAll(this.thousandsSeparator, "");
			}
			// remove decimal separator
			if(typeof formattedValue === 'string' && this.decimalSeparator !== '.')
			{
				formattedValue = formattedValue.replace(this.decimalSeparator, '.');
			}
			if(typeof this.precision !== 'undefined')
			{
				formattedValue = parseFloat(parseFloat(<string>formattedValue).toFixed(this.precision));
			}
			else
			{
				formattedValue = parseFloat(<string>formattedValue);
			}
		}
		return <number>formattedValue;
	}

	/**
	 * Get the options for masking.
	 * Overridden to use number-only masking
	 *
	 * @see https://imask.js.org/guide.html#masked-number
	 */
	protected get maskOptions()
	{
		let options = {
			...super.maskOptions,
			skipInvalid: true,
			// The initial options need to match an actual number
			radix: this.decimalSeparator,
			thousandsSeparator: this.thousandsSeparator,
			mask: Number
		}
		if(typeof this.precision != "undefined")
		{
			options.scale = this.precision;
		}
		if(typeof this.min != "undefined")
		{
			options.min = this.min;
		}
		if(typeof this.max != "undefined")
		{
			options.max = this.max;
		}
		return options;
	}

	updateMaskValue()
	{
		this._mask.updateValue();
		this._mask.unmaskedValue = "" + this.value;
		this._mask.updateValue();
	}


	private handleScroll(e)
	{
		if (this.disabled) return;
		const old_value = this.value;
		let min = parseFloat(this.min ?? Number.MIN_SAFE_INTEGER);
		if(Number.isNaN(min))
		{
			min = Number.MIN_SAFE_INTEGER;
		}
		let max = parseFloat(this.max ?? Number.MAX_SAFE_INTEGER);
		if(Number.isNaN(max))
		{
			max = Number.MAX_SAFE_INTEGER;
		}
		this.value = "" + Math.min(Math.max(this.valueAsNumber + e.detail * (parseFloat(this.step) || 1), min), max);
		this.dispatchEvent(new CustomEvent("sl-change", {bubbles: true}));
		this.requestUpdate("value", old_value);
	}

	protected _incrementButtonTemplate()
	{
		// No increment buttons on mobile
		if(typeof egwIsMobile == "function" && egwIsMobile())
		{
			return nothing;
		}
		// Other reasons for no buttons
		if(this.disabled || this.readonly || !this.step)
		{
			return nothing;
		}

		return html`
            <et2-button-scroll class="et2-number__scrollbuttons" slot="suffix"
                               part="scroll"
                               @et2-scroll=${this.handleScroll}></et2-button-scroll>`;
	}

	_inputTemplate()
	{
		return html`
            <sl-input
                    part="input"
                    max=${this.max || nothing}
                    min=${this.min || nothing}
                    placeholder=${this.placeholder || nothing}
                    inputmode="numeric"
                    ?disabled=${this.disabled}
                    ?readonly=${this.readonly}
                    ?required=${this.required}
                    .value=${this.formattedValue}
                    @blur=${this.handleBlur}
            >
                <slot name="prefix" slot="prefix"></slot>
                ${this.prefix ? html`<span slot="prefix">${this.prefix}</span>` : nothing}
                ${this.suffix ? html`<span slot="suffix">${this.suffix}</span>` : nothing}
                <slot name="suffix" slot="suffix"></slot>
                ${this._incrementButtonTemplate()}
            </sl-input>
		`;
	}
}

/**
 * Format a number according to user preferences
 * @param {number} value
 * @returns {string}
 */
export function formatNumber(value : number, decimalSeparator : string = ".", thousandsSeparator : string = "") : string
{
	// Split by . because value is a number, so . is decimal separator
	let parts = ("" + value).split(".");

	parts[0] = parts[0].replace(/\B(?<!\.\d*)(?=(\d{3})+(?!\d))/g, thousandsSeparator);
	return parts.join(decimalSeparator);
}
