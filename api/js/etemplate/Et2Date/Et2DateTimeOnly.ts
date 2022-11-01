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

	}

	/**
	 * Override some flatpickr defaults to get things how we like it
	 *
	 * @see https://flatpickr.js.org/options/
	 * @returns {any}
	 */
	protected getOptions()
	{
		let options = super.getOptions();
		let timeFormat = ((<string>window.egw.preference("timeformat") || "24") == "24" ? "H:i" : "h:i K");
		options.altFormat = timeFormat;
		options.noCalendar = true;
		options.dateFormat = "1970-01-01TH:i:00\\Z";

		// Time only does not have year & month, which scrollPlugin blindly tries to use
		// This causes an error and interrupts the initialization
		options.plugins.push(instance =>
		{
			return {
				onReady: function()
				{
					this.yearElements = []
					this.monthElements = []
				}
			}
		});

		return options;
	}

	/**
	 * For mobile, we use a plain input of the proper type
	 * @returns {string}
	 */
	_mobileInputType() : string
	{
		return "time";
	}

	set_value(value)
	{
		let adjustedValue : Date | string = '';
		if(!value || value == 0 || value == "0")
		{
			value = '';
		}
		// Handle timezone offset, flatpickr uses local time
		if(value)
		{
			let date = new Date(value);
			adjustedValue = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);
			adjustedValue.setDate(1);
			adjustedValue.setMonth(0)
			adjustedValue.setFullYear(1970);
		}
		if(!this._instance)
		{
			this.defaultDate = adjustedValue;
		}
		else
		{
			this.setDate(adjustedValue);
		}
	}
}

// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-date-timeonly", Et2DateTimeOnly);
