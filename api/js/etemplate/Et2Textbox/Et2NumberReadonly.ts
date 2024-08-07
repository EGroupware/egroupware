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
import {formatNumber} from "./Et2Number";
import {property} from "lit/decorators/property.js";
import {css} from "lit";

export class Et2NumberReadonly extends Et2TextboxReadonly
{
	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			css`
				::slotted(*) {
					flex: 1 1 auto;
					text-align: right;
					padding-right: var(--sl-spacing-small);
				}
			`,
		];
	}

	/**
	 * Precision of float number or 0 for integer
	 */
	@property({type: Number})
	precision;

	set_value(val)
	{
		if(val === null)
		{
			val = "";
		}
		else if("" + val !== "")
		{
			// use decimal separator from user prefs
			const format = this.egw().preference('number_format') ?? ".";
			val = formatNumber(parseFloat(val), format[0], format[1], this.precision);
		}

		// can not call super.set_value(), as it does not call the setter for value
		super.value = val;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Textbox is a LitElement
customElements.define("et2-number_ro", Et2NumberReadonly);