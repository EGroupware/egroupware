/**
 * EGroupware clientside API object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

"use strict";

/*egw:uses
	egw_core;
	egw_preferences;
	egw_jquery;
	egw_css;
*/

egw.extend('calendar', egw.MODULE_WND_LOCAL, function(_app, _wnd) {

	function calendarPreferences(_egw)
	{
		// Date format in jQuery UI date format
		var dateformat = _egw.preference("dateformat")
			.replace("Y","yy")
			.replace("d","dd")
			.replace("m","mm")
			.replace("M", "M");

		// First day of the week
		var first_day = {"Monday": 1, "Sunday": 0, "Saturday": 6};
		var first_day_pref = _egw.preference("weekdaystarts","calendar");

		return {
			'dateFormat': dateformat,
			'firstDay': first_day_pref ? first_day[first_day_pref] : 0
		};
	};

	function setupCalendar(_egw, _input, _time, _callback, _context)
	{
		var prefs = calendarPreferences(_egw);

		var params = {
			dateFormat: prefs.dateFormat,
			firstDay: prefs.firstDay,

			autoSize: true,
			showButtonPanel: true, // Today, Done buttons
			showOtherMonths: true,
			selectOtherMonths: true,
			showWeek: true, // Week numbers
			changeMonth: true, // Month selectbox
			changeYear: true // Year selectbox
		}

		// Get the preferences
		_egw.$j(_input).datepicker(params);
/*
				onClose:	function(date_text, picker) {
					// Only update if there's a change - "" if no date selected
					if(date_text != "") self.set_value(new Date(
						picker.selectedYear, 
						picker.selectedMonth, 
						picker.selectedDay,
						self.input_hours ? self.input_hours.val() : 0,
						self.input_minutes ? self.input_minutes.val() : 0,
						0,0
					));
				},
			});

*/
	};

	/**
	 * Translate, and set as default values
	 *
	 */
	function translate() {
		var translate_fields = {
			// These ones are simple strings
			"nextText": false,
			"currentText": false,
			"prevText": false,
			"closeText": false,
			
			// These ones are arrays.  
			// Integers are length.  If lang() has no short translation, just trim full
			"dayNames":	false, 
			"dayNamesShort":3,
			"dayNamesMin":	2,
			"monthNames":	false,
			"monthNamesShort":	3
		}
		var regional = {};
		var full = [];
		for(var i in translate_fields)
		{
			var trans = jQuery.datepicker._defaults[i];
			if(typeof trans === 'string')
			{
				trans = egw().lang(trans);
			}
			else
			{
				for(var key in trans) {
					if(translate_fields[i] === false)
					{
						trans[key] = egw().lang(trans[key]);
					}
					else
					{
						trans[key] = full[key].substr(0,translate_fields[i]);
					}
				}
				// Keep the full one for missing short ones
				if(translate_fields[i] === false) full = trans;
			}
			regional[i] = trans;
		}

		// Set some non-lang defaults too
/*
		var prefs = calendarPreferences(egw());
		for(var i in prefs)
		{
			regional[i] = prefs[i];
		}
*/

		jQuery.datepicker.setDefaults(regional);
	};

	/** 
	This should be global, static, run once, but adding this part breaks egw's js juggling
	*/
	var css = this.module('css',_wnd);
	css.css(".et2_date input.et2_date", "background-image: url(" + egw().image('datepopup') + ")");
	/*
        var ready = this.module('ready', _wnd);
	ready.ready(translate,this);
	*/
	
	return {

		calendar: function(_input, _time, _callback, _context) {
			setupCalendar(this, _input, _time, _callback, _context);
		}

	}

});

