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

	jscalendar.calendar;
	/phpgwapi/js/jscalendar/lang/calendar-en.js;

*/

egw.extend('calendar', egw.MODULE_WND_LOCAL, function(_app, _wnd) {

	// Instanciate the calendar for this window
	calendar_closure(_wnd, _wnd.document);

	// Load the translation
	calendar_lang_closure(_wnd);

	function calendarPreferences()
	{
		// Load the date format from the preferences
		var dateformat = egw.preference("dateformat");
		if (!dateformat)
		{
			dateformat = "Y-m-d";
		}

		// Transform the given format to the correct date format
		dateformat = dateformat
				.replace("Y","%Y")
				.replace("d","%d")
				.replace("m","%m")
				.replace("M", "%b");

		// Load the first weekday from the calendar application preferences
		var firstDay = egw.preference("weekdaystarts","calendar");

		return {
			"format": dateformat,
			"firstDay": firstDay,
			"ifFormat": "%Y/%m/%d",
			"daFormat": "%Y/%m/%d",
			"titleFormat": "%B, %Y"
		}
	}

	function calendarPopup(_input, _button, _callback, _context)
	{

		function calendarUpdate(_cal)
		{
			// Update the input value
			_input.value = _cal.date.print(_cal.params.format);

			// Close the popup if a date has been clicked
			if (_cal.dateClicked)
			{
				cal.callCloseHandler();
			}

			// Call the callback
			_callback.call(_context, _cal);
		}

		function calendarHide(_cal)
		{
			_cal.hide();
		}

		// Read the calendar parameters
		var params = calendarPreferences();

		// Destroy any existing calendar
		if (_wnd.calendar)
		{
			_wnd.calendar.destroy();
		}

		// Create a new calendar instance
		_wnd.calendar = new Calendar(
			params.firstDay,
			null,
			calendarUpdate,
			calendarHide
		);
		_wnd.calendar.showsTime = false;
		_wnd.calendar.weekNumbers = true;
		_wnd.calendar.yearStep = 2;
		_wnd.calendar.setRange(1900, 2999);
		_wnd.calendar.params = params;

		_wnd.calendar.create();

		_wnd.calendar.setDateFormat(params.format);
		_wnd.calendar.parseDate($j(_input).val());

		_wnd.calendar.refresh();
		_wnd.calendar.showAtElement(_button || _input);
	}

	return {

		/**
		 * Transforms either the given input element into a date input or
		 * displays the calendar when clicking on the given input button. When
		 * the date changes, the given callback function is called.
		 */
		calendar: function(_input, _button, _callback, _context) {
/*			$j([_input, _button]).bind('click.calendar', function() {
				calendarPopup(_input, _button, _callback, _context);
			})*/
			$j(_input).bind('click', function() {
				calendarPopup(_input, _button, _callback, _context);
			});
			$j(_button).bind('click', function() {
				calendarPopup(_input, _button, _callback, _context);
			});
		}

	}

});

