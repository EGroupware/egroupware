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
			'dateformat': dateformat,
			'firstDay': first_day_pref ? first_day[first_day_pref] : 0
		}
	}

	function setupCalendar(_egw, _input, _time, _callback, _context)
	{
		var prefs = calendarPreferences(_egw);

		var params = {
			dateFormat: prefs.dateformat,
			firstDay: prefs.firstDay,

			autoSize: true,
			showButtonPanel: true, // Today, Done buttons
			showOtherMonths: true,
			selectOtherMonths: true,
			showWeek: true, // Week numbers
			changeMonth: true, // Month selectbox
			changeYear: true, // Year selectbox

			// Trigger button
			showOn:		"both",
			buttonImage:	_egw.image('datepopup','phpgwapi'),
			buttonImageOnly: true,

			nextText: _egw.lang('Next'),
			currentText: _egw.lang('today'),
			prevText: _egw.lang('Prev'),
			closeText: _egw.lang('Done'),

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

			// Translate (after initialize has its way)
			var translate_fields = {
				"dayNames":	false, 
				"dayNamesShort":3,
				"dayNamesMin":	2,
				"monthNames":	false,
				"monthNamesShort":	3
			}
			var full = [];
			for(var i in translate_fields)
			{
				var trans = this.input_date.datepicker("option",i);
				// Keep the full one for missing short ones
				for(var key in trans) {
					if(translate_fields[i] === false)
					{
						trans[key] = this.egw().lang(trans[key]);
					}
					else
					{
						trans[key] = full[key].substr(0,translate_fields[i]);
					}
				}
				if(translate_fields[i] === false) full = trans;
				node.datepicker("option",i,trans);
			}*/
	}

	return {

		calendar: function(_input, _time, _callback, _context) {
			setupCalendar(this, _input, _time, _callback, _context);
		}

	}

});

