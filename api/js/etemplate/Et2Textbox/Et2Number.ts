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

export class Et2Number extends Et2Textbox
{
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

	transformAttributes(attrs)
	{
		if (attrs.precision === 0 && typeof attrs.step === 'undefined')
		{
			attrs.step = 1;
		}
		if (typeof attrs.validator === 'undefined')
		{
			attrs.validator = attrs.precision === 0 ? '/^-?[0-9]*$/' : '/^-?[0-9]*[,.]?[0-9]*$/';
		}
		attrs.type = 'number';
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

	set_value(val)
	{
		if (""+val !== "")
		{
			if (typeof this.precision !== 'undefined')
			{
				val = parseFloat(val).toFixed(this.precision);
			}
			else
			{
				val = parseFloat(val);
			}
			// use decimal separator from user prefs
			const format = this.egw().preference('number_format');
			const sep = format ? format[0] : '.';
			if(typeof val === 'string' && format && sep && sep !== '.')
			{
				val = val.replace('.', sep);
			}
		}
		this.value = val;
	}

	getValue()
	{
		let val = this.value;

		if (""+val !== "")
		{
			if (typeof this.precision !== 'undefined')
			{
				val = parseFloat(val).toFixed(this.precision);
			}
			else
			{
				val = parseFloat(val);
			}
		}
		return val;
	}
}
// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-number", Et2Number);