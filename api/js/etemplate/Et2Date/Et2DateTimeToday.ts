/**
 * EGroupware eTemplate2 - Readonly date-time_today WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {formatDate, formatDateTime, formatTime, parseDateTime} from "./Et2Date";
import {Et2DateReadonly} from "./Et2DateReadonly";

/**
 * Widget displays date/time with different formatting relative to today
 * If the date is today, we show just the time, otherwise just the date.
 */
export class Et2DateTimeToday extends Et2DateReadonly
{
	constructor()
	{
		super();
		this.parser = parseDateTime;
		this.formatter = this.formatDateTime;
	}

	/**
	 * Format date+time relative to "now"
	 * If the date is today, we show just the time.  Otherwise, the date and time.
	 *
	 * @param {Date} date
	 * @param {import('@lion/localize/types/LocalizeMixinTypes').FormatDateOptions} [options] Intl options are available
	 * @returns {string}
	 */
	formatDateTime(date : Date, options = {dateFormat: "", timeFormat: ""}) : string
	{
		let display = "";
		// Today - just the time
		if(formatDate(date, {dateFormat: 'Y-m-d'}) == formatDate(new Date(), {dateFormat: 'Y-m-d'}))
		{
			display = formatTime(date);
		}
		// Before today - just the date
		else
		{
			display = formatDate(date);
		}
		return display;
	}
}

// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-date-time-today", Et2DateTimeToday);