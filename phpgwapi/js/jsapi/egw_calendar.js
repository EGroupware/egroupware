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
	jquery.jquery-ui;
	jquery.jquery-ui-timepicker-addon;

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

	function timePreferences(_egw)
	{
		return {
			"timeFormat": egw.preference("timeformat") == 12 ? "h:mm tt" : "hh:mm",
			"ampm": (egw.preference("timeformat") == "12"),
			"hourGrid": 4,
			"minuteGrid": 10
		}
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
		if(_time)
		{
			params = jQuery.extend(params, timePreferences(_egw));
			_wnd.jQuery(_input).datetimepicker(params);
		}
		else
		{
			_wnd.$j(_input).datepicker(params);
		}
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
	 * Set up an input to have a time selection popup
	 */
	function setupTime(_egw, _input, _callback, _context)
	{
		_wnd.jQuery(_input).timepicker(timePreferences(_egw));
	}

	/**
	 * Translate, and set as default values
	 *
	 */
	function translateCalendar() {
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
			var trans = _wnd.jQuery.datepicker._defaults[i];
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

		_wnd.jQuery.datepicker.setDefaults(regional);
	};

	function translateTimepicker()
	{
		var translate_fields = {
			// These ones are simple strings
			"timeOnlyTitle": false,
			"timeText": false,
			"hourText": false,
			"minuteText": false,
			"currentText": false,
			"closeText": false
		};
		var regional = {};
		var full = [];
		for(var i in translate_fields)
		{
			var trans = _wnd.jQuery.timepicker._defaults[i];
			if(typeof trans === 'string')
			{
				trans = egw().lang(trans);
			}
			regional[i] = trans;
		}
		_wnd.jQuery.timepicker.setDefaults(regional);
	};

	// Static initialization

	// Set template's icon for date popup - could probably use jquery-ui icons
	var css = this.module('css',_wnd);
	css.css(".et2_date input.hasDatepicker:hover", "background-image: url(" + egw().image('datepopup') + ")");

	// Translate only once
        var ready = this.module('ready', _wnd);
	ready.ready(translateCalendar,this);
	ready.ready(translateTimepicker,this);
	
	return {

		calendar: function(_input, _time, _callback, _context) {
			setupCalendar(this, _input, _time, _callback, _context);
		},
		time: function(_input, _callback, _context) {
			setupTime(this, _input, _callback, _context);
		}
	}

});

