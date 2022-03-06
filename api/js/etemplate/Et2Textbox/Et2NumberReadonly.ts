/**
 * EGroupware eTemplate2 - Number widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

import {Et2TextboxReadonly} from "./Et2TextboxReadonly";

export class Et2NumberReadonly extends Et2TextboxReadonly
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Precision of float number or 0 for integer
			 */
			precision: Number,
		}
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
		}
		// use decimal separator from user prefs
		const format = this.egw().preference('number_format');
		const sep = format ? format[0] : '.';
		if(typeof val === 'string' && format && sep && sep !== '.')
		{
			val = val.replace('.', sep);
		}
		// can not call super.set_value(), as it does not call the setter for value
		super.value = val;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-number_ro", Et2NumberReadonly);