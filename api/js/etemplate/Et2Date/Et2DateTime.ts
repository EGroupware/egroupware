/**
 * EGroupware eTemplate2 - Date+Time widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "@lion/core";
import {Et2Date, formatDateTime, parseDateTime} from "./Et2Date";
import {Unparseable} from "@lion/form-core";


export class Et2DateTime extends Et2Date
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host([focused]) ::slotted(button), :host(:hover) ::slotted(button) {
				display: inline-block;
			}
            ::slotted(.calendar_button) {
            	border: none;
            	background: transparent;
            	margin-left: -20px;
                display: none;
			}
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties
		}
	}

	constructor()
	{
		super();
		this.parser = parseDateTime;
		this.formatter = formatDateTime;
	}

	getValue()
	{
		if(this.readOnly)
		{
			return null;
		}

		// The supplied value was not understandable, return null
		if(this.modelValue instanceof Unparseable || !this.modelValue)
		{
			return null;
		}

		return this.modelValue.toJSON();
	}
}

// @ts-ignore TypeScript is not recognizing that Et2DateTime is a LitElement
customElements.define("et2-date-time", Et2DateTime);
