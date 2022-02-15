/**
 * EGroupware eTemplate2 - Date+Time widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css} from "@lion/core";
import {Et2Date} from "./Et2Date";


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

		// Configure flatpickr
		let dateFormat = (this.egw().preference("dateformat") || "Y-m-d");
		let timeFormat = ((<string>window.egw.preference("timeformat") || "24") == "24" ? "H:i" : "h:i K");
		this.altFormat = dateFormat + " " + timeFormat;
		this.enableTime = true;
		this.time_24hr = this.egw().preference("timeformat", "common") == "24";
		this.dateFormat = "Y-m-dTH:i:00\\Z";
		this.defaultHour = new Date().getHours();
	}
}

// @ts-ignore TypeScript is not recognizing that Et2DateTime is a LitElement
customElements.define("et2-date-time", Et2DateTime);
