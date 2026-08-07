/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_core';

export interface CalendarModule
{
	/**
	 * setup a calendar / date-selection
	 *
	 * @member of egw
	 * @param _input
	 * @param _time
	 * @param _callback
	 * @param _context
	 */
	calendar(_input : any, _time : any, _callback : any, _context : any) : void;

	/**
	 * setup a time-selection
	 *
	 * @param _input
	 * @param _callback
	 * @param _context
	 */
	time(_input : any, _callback : any, _context : any) : void;

	/**
	 * transform PHP date/time-format to jQuery date/time-format
	 *
	 * @param _php_format
	 */
	dateTimeFormat(_php_format : string) : string;

	/**
	 * Get timezone offset of user in seconds
	 *
	 * If browser / OS is configured correct, identical to: (new Date()).getTimezoneOffset()
	 *
	 * @return offset to UTC in minutes
	 */
	getTimezoneOffset() : number;

	/**
	 * Calculate the start of the week, according to user's preference
	 *
	 * @param date
	 */
	week_start(date : string) : Date;

	/**
	 * Get a list of holidays for the given year
	 *
	 * Returns a promise that resolves with a list of holidays indexed by date, in Ymd format:
	 * {20001225: [{day: 14, month: 2, occurence: 2021, name: "Valentinstag"}]}
	 *
	 * No need to cache the results, we do it here.
	 *
	 * @param year
	 */
	holidays(year : number) : Promise<{ [key : string] : Array<object> }>;
}

declare global
{
	interface IegwGlobal extends CalendarModule
	{
	}
}

/**
 * Date and timepicker
 *
 * @augments Class
 * @param {string} _app application name object is instanced for
 * @param {object} _wnd window object is instanced for
 */
egw.extend('calendar', egw.MODULE_GLOBAL, function (_app : string, _wnd : Window) : CalendarModule
{
	"use strict";

	let _holiday_cache : {[year : number] : any} = {};

	/**
	 * transform PHP date/time-format to jQuery date/time-format
	 *
	 * @param {string} _php_format
	 * @returns {string}
	 */
	function dateTimeFormat(_php_format : string) : string
	{
		return _php_format
			.replace("Y","yy")
			.replace("d","dd")
			.replace("m","mm")
			.replace("M", "M")
			.replace('H', 'hh')
			.replace('i', 'mm')	// datepicker uses mm for month and minutes, depending on where in format it's written!
			.replace('s', 'ss');
	}

	return {
		/**
		 * setup a calendar / date-selection
		 *
		 * @member of egw
		 * @param _input
		 * @param _time
		 * @param _callback
		 * @param _context
		 * @returns
		 */
		calendar: function(_input : any, _time : any, _callback : any, _context : any) : void
		{
			alert('jQueryUI datepicker is no longer supported!');
		},
		/**
		 * setup a time-selection
		 *
		 * @param _input
		 * @param _callback
		 * @param _context
		 * @returns
		 */
		time: function(_input : any, _callback : any, _context : any) : void
		{
			alert('jQueryUI datepicker is no longer supported!');
		},
		/**
		 * transform PHP date/time-format to jQuery date/time-format
		 *
		 * @param {string} _php_format
		 * @returns {string}
		 */
		dateTimeFormat: function(_php_format : string) : string
		{
			return dateTimeFormat(_php_format);
		},
		/**
		 * Get timezone offset of user in seconds
		 *
		 * If browser / OS is configured correct, identical to: (new Date()).getTimezoneOffset()
		 *
		 * @return {number} offset to UTC in minutes
		 */
		getTimezoneOffset: function() : number {
			return isNaN(<any>egw.preference('timezoneoffset')) ? (new Date()).getTimezoneOffset() : parseInt(<any>egw.preference('timezoneoffset'));
		},
		/**
		 * Calculate the start of the week, according to user's preference
		 *
		 * @param {string} date
		 * @return {Date}
		 */
		week_start: function(date : string) : Date {
			var d = new Date(date);
			var day = d.getUTCDay();
			var diff = 0;
			switch(egw.preference('weekdaystarts','calendar'))
			{
				case 'Saturday':
					diff = day === 6 ? 0 : day === 0 ? -1 : -(day + 1);
					break;
				case 'Monday':
					diff = day === 0 ? -6 : 1 - day;
					break;
				case 'Sunday':
				default:
					diff = -day;
			}
			d.setUTCDate(d.getUTCDate() + diff);
			return d;
		},
		/**
		 * Get a list of holidays for the given year
		 *
		 * Returns a promise that resolves with a list of holidays indexed by date, in Ymd format:
		 * {20001225: [{day: 14, month: 2, occurence: 2021, name: "Valentinstag"}]}
		 *
		 * No need to cache the results, we do it here.
		 *
		 * @param year
		 * @returns Promise<{[key: string]: Array<object>}>
		 */
		holidays: function holidays(year : number) : Promise<any>
		{
			// No country selected causes error, so skip if it's missing
			if (!egw || !egw.preference('country', 'common'))
			{
				return Promise.resolve({});
			}

			if (typeof _holiday_cache[year] === 'undefined')
			{
				// Fetch with json instead of jsonq because there may be more than
				// one widget listening for the response by the time it gets back,
				// and we can't do that when it's queued.
				_holiday_cache[year] = window.fetch(
					egw.link('/calendar/holidays.php', {year: year, url: this.config('ical_holiday_url') || ''})
				).then((response) =>
				{
					return _holiday_cache[year] = response.json();
				});
			}
			return Promise.resolve(_holiday_cache[year]);
		}
	};
});
