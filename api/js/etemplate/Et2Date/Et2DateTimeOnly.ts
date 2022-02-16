/**
 * EGroupware eTemplate2 - Date+Time widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2DateTime} from "./Et2DateTime";


export class Et2DateTimeOnly extends Et2DateTime
{
	static get styles()
	{
		return [
			...super.styles,
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
		let timeFormat = ((<string>window.egw.preference("timeformat") || "24") == "24" ? "H:i" : "h:i K");
		this.altFormat = timeFormat;
		this.enableTime = true;
		this.noCalendar = true;
		this.time_24hr = this.egw().preference("timeformat", "common") == "24";
		this.dateFormat = "1970-01-01TH:i:00\\Z";
		this.defaultHour = new Date().getHours();
	}

	set_value(value)
	{
		if(!value || value == 0 || value == "0")
		{
			value = '';
		}
		// Handle timezone offset, flatpickr uses local time
		let date = new Date(value);
		let formatDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);
		formatDate.setDate(1);
		formatDate.setMonth(0)
		formatDate.setFullYear(1970);
		if(!this._instance)
		{
			this.defaultDate = formatDate;
		}
		else
		{
			this.setDate(formatDate);
		}
	}
}

// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-date-timeonly", Et2DateTimeOnly);
