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
import {Instance} from "flatpickr/dist/types/instance";


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

			  ::slotted([slot='input']) {
				flex: 1 1 auto;
				min-width: 17ex;
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
	}


	/**
	 * Override some flatpickr defaults to get things how we like it
	 *
	 * @see https://flatpickr.js.org/options/
	 * @returns {any}
	 */
	public getOptions()
	{
		let options = super.getOptions();

		let dateFormat = (this.egw()?.preference("dateformat") || "Y-m-d");
		let timeFormat = ((<string>this.egw()?.preference("timeformat") || "24") == "24" ? "H:i" : "h:i K");
		options.altFormat = dateFormat + " " + timeFormat;
		options.enableTime = true;
		options.time_24hr = this.egw()?.preference("timeformat", "common") == "24";
		options.dateFormat = "Y-m-dTH:i:00\\Z";
		options.defaultHour = new Date().getHours();

		return options;
	}

	/**
	 * Change handler setting modelValue for validation
	 *
	 * @returns
	 * @param selectedDates
	 * @param dateStr
	 * @param instance
	 */
	_updateValueOnChange(selectedDates : Date[], dateStr : string, instance : Instance)
	{
		super._updateValueOnChange(selectedDates, dateStr, instance);
		if(!this.freeMinuteEntry && dateStr && instance && instance.config.minuteIncrement > 1)
		{
			let i = instance.latestSelectedDateObj;
			const d = i ? i : new Date();
			const original = d.getMinutes();

			let bound = Math.round(original / instance.config.minuteIncrement) * instance.config.minuteIncrement;
			if(bound != original)
			{
				d.setMinutes(bound);
				instance.setDate(d, false);
			}
		}
	}

	/**
	 * For mobile, we use a plain input of the proper type
	 * @returns {string}
	 */
	_mobileInputType() : string
	{
		return "datetime-local";
	}

	/**
	 * Add "today" button below calendar
	 * @protected
	 */
	protected _buttonPlugin()
	{
		// @ts-ignore TypeScript can't find ShortcutButtonsPlugin, but rollup does
		return ShortcutButtonsPlugin({
			button: [
				{label: this.egw().lang("ok")},
				{label: this.egw().lang("Now")}
			],
			onClick: this._handleShortcutButtonClick
		})
	}

	/**
	 * Handle clicks on scroll buttons
	 *
	 * @param e
	 */
	public handleScroll(e)
	{
		if(e.target && !e.target.dataset.direction)
		{
			return;
		}
		e.stopPropagation();

		const direction = parseInt(e.target.dataset.direction, 10) || 1;
		this.increment(direction * this.getOptions().minuteIncrement, "minute", true);
	}
}

// @ts-ignore TypeScript is not recognizing that Et2DateTime is a LitElement
customElements.define("et2-date-time", Et2DateTime);
