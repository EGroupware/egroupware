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
import {css, html, render} from "lit";

export class Et2Number extends Et2Textbox
{
	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			css`
			  /* Scroll buttons */

			  :host(:hover) ::slotted(et2-button-scroll) {
				display: flex;
			  }

			  ::slotted(et2-button-scroll) {
				display: none;
			  }

			  .input--medium .input__suffix ::slotted(et2-button-scroll) {
				padding: 0px;
			  }

			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Minimum value
			 */
			min: Number,
			/**
			 * Maximum value
			 */
			max: Number,
			/**
			 * Step value
			 */
			step: Number,
			/**
			 * Precision of float number or 0 for integer
			 */
			precision: Number,
		}
	}


	constructor()
	{
		super();

		this.handleScroll = this.handleScroll.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Add spinners
		render(this._incrementButtonTemplate(), this);
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
		attrs.inputmode = "numeric";
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

	handleBlur()
	{
		this.value = this.input.value;
		super.handleBlur();
	}

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
			// Put separator back in, if different
			if(typeof val === 'string' && format && sep && sep !== '.')
			{
				val = val.replace('.', sep);
			}
		}
		super.value = val;
	}

	get value()
	{
		return super.value;
	}

	getValue() : any
	{
		// Needs to be string to pass validator
		return "" + this.valueAsNumber;
	}

	get valueAsNumber() : number
	{
		let val = super.value;

		if("" + val !== "")
		{
			// remove decimal separator from user prefs
			const format = this.egw().preference('number_format');
			const sep = format ? format[0] : '.';
			if(typeof val === 'string' && format && sep && sep !== '.')
			{
				val = val.replace(sep, '.');
			}
			if(typeof this.precision !== 'undefined')
			{
				val = parseFloat(parseFloat(val).toFixed(this.precision));
			}
			else
			{
				val = parseFloat(val);
			}
		}
		return val;
	}

	private handleScroll(e)
	{
		const old_value = this.value;
		const min = parseFloat(this.min ?? Number.MIN_SAFE_INTEGER);
		const max = parseFloat(this.max ?? Number.MAX_SAFE_INTEGER);
		this.value = "" + Math.min(Math.max(this.valueAsNumber + e.detail * (parseFloat(this.step) || 1), min), max);
		this.dispatchEvent(new CustomEvent("sl-change", {bubbles: true}));
		this.requestUpdate("value", old_value);
	}

	protected _incrementButtonTemplate()
	{
		// No increment buttons on mobile
		if(typeof egwIsMobile == "function" && egwIsMobile())
		{
			return '';
		}

		return html`
            <et2-button-scroll class="et2-number__scrollbuttons" slot="suffix"
                               part="scroll"
                               @et2-scroll=${this.handleScroll}></et2-button-scroll>`;
	}
}
// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-number", Et2Number);