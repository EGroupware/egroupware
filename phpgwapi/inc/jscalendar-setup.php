<?php
/**************************************************************************\
* eGroupWare - API jsCalendar setup (set up jsCalendar with user prefs)    *
* http://www.eGroupWare.org                                                *
* Modified by Ralf Becker <RalfBecker@outdoor-training.de>                 *
* This file is derived from jscalendar's calendar-setup.js file and the    *
* english translation in lang/calendar-en.js.                              *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

$GLOBALS['phpgw_info']['flags'] = Array(
	'currentapp'  => 'calendar',		// can't be phpgwapi
	'noheader'    => True,
	'nonavbar'    => True,
	'noappheader' => True,
	'noappfooter' => True,
	'nofooter'    => True,
	'nocachecontrol' => True			// allow cacheing
);

include('../../header.inc.php');

$dateformat = $GLOBALS['phpgw_info']['user']['preferences']['common']['dateformat'];
$jsDateFormat = str_replace(array('Y','d','m','M'),array('%Y','%d','%m','%b'),$dateformat);
$dayFirst = strpos($dateformat,'d') < strpos($dateformat,'m');
$jsLongDateFormat = '%a, '.($dayFirst ? '%e' : '%b').($dateformat[1] == '.' ? '. ' : ' ').($dayFirst ? '%b' : '%e');

/*  Copyright Mihai Bazon, 2002, 2003  |  http://dynarch.com/mishoo/
 * ---------------------------------------------------------------------------
 *
 * The DHTML Calendar
 *
 * Details and latest version at:
 * http://dynarch.com/mishoo/calendar.epl
 *
 * This script is distributed under the GNU Lesser General Public License.
 * Read the entire license text here: http://www.gnu.org/licenses/lgpl.html
 *
 * This file defines helper functions for setting up the calendar.  They are
 * intended to help non-programmers get a working calendar on their site
 * quickly.  This script should not be seen as part of the calendar.  It just
 * shows you what one can do with the calendar, while in the same time
 * providing a quick and simple method for setting it up.  If you need
 * exhaustive customization of the calendar creation process feel free to
 * modify this code to suit your needs (this is recommended and much better
 * than modifying calendar.js itself).
 */

// $Id$

/**
 *  This function "patches" an input field (or other element) to use a calendar
 *  widget for date selection.
 *
 *  The "params" is a single object that can have the following properties:
 *
 *    prop. name   | description
 *  -------------------------------------------------------------------------------------------------
 *   inputField    | the ID of an input field to store the date
 *   displayArea   | the ID of a DIV or other element to show the date
 *   button        | ID of a button or other element that will trigger the calendar
 *   eventName     | event that will trigger the calendar, without the "on" prefix (default: "click")
 *   ifFormat      | date format that will be stored in the input field
 *   daFormat      | the date format that will be used to display the date in displayArea
 *   singleClick   | (true/false) wether the calendar is in single click mode or not (default: true)
 *   mondayFirst   | (true/false) if true Monday is the first day of week, Sunday otherwise (default: true)
 *   align         | alignment (default: "Bl"); if you don't know what's this see the calendar documentation
 *   range         | array with 2 elements.  Default: [1900, 2999] -- the range of years available
 *   weekNumbers   | (true/false) if it's true (default) the calendar will display week numbers
 *   flat          | null or element ID; if not null the calendar will be a flat calendar having the parent with the given ID
 *   flatCallback  | function that receives a JS Date object and returns an URL to point the browser to (for flat calendar)
 *   disableFunc   | function that receives a JS Date object and should return true if that date has to be disabled in the calendar
 *   onSelect      | function that gets called when a date is selected.  You don't _have_ to supply this (the default is generally okay)
 *   onClose       | function that gets called when the calendar is closed.  [default]
 *   onUpdate      | function that gets called after the date is updated in the input field.  Receives a reference to the calendar.
 *   date          | the date that the calendar will be initially displayed to
 *   showsTime     | default: false; if true the calendar will include a time selector
 *   timeFormat    | the time format; can be "12" or "24", default is "12"
 *
 *  None of them is required, they all have default values.  However, if you
 *  pass none of "inputField", "displayArea" or "button" you'll get a warning
 *  saying "nothing to setup".
 */
?>
Calendar.setup = function (params) {
	function param_default(pname, def) { if (typeof params[pname] == "undefined") { params[pname] = def; } };

	param_default("inputField",     null);
	param_default("displayArea",    null);
	param_default("button",         null);
	param_default("eventName",      "click");
//	param_default("ifFormat",       "%Y/%m/%d");
	param_default("ifFormat",      "<?php echo $jsDateFormat; ?>");
//	param_default("daFormat",       "%Y/%m/%d");
	param_default("daFormat",      "<?php echo $jsDateFormat; ?>");
	param_default("singleClick",    true);
	param_default("disableFunc",    null);
	param_default("dateStatusFunc", params["disableFunc"]);	// takes precedence if both are defined
//	param_default("mondayFirst",    true);
	param_default("mondayFirst",   <?php echo $GLOBALS['phpgw_info']['user']['preferences']['common']['weekdaysstarts'] != 'sunday' ? 'true' : 'false'; ?>);
	param_default("align",          "Bl");
	param_default("range",          [1900, 2999]);
	param_default("weekNumbers",    true);
	param_default("flat",           null);
	param_default("flatCallback",   null);
	param_default("onSelect",       null);
	param_default("onClose",        null);
	param_default("onUpdate",       null);
	param_default("date",           null);
	param_default("showsTime",      false);
//	param_default("timeFormat",     "24");
	param_default("timeFormat",     "<?php echo $GLOBALS['phpgw_info']['user']['preferences']['common']['timeformat']; ?>");

	var tmp = ["inputField", "displayArea", "button"];
	for (var i in tmp) {
		if (typeof params[tmp[i]] == "string") {
			params[tmp[i]] = document.getElementById(params[tmp[i]]);
		}
	}
	if (!(params.flat || params.inputField || params.displayArea || params.button)) {
		alert("Calendar.setup:\n  Nothing to setup (no fields found).  Please check your code");
		return false;
	}

	function onSelect(cal) {
		if (cal.params.flat) {
			if (typeof cal.params.flatCallback == "function") {
				cal.params.flatCallback(cal);
			} else {
				alert("No flatCallback given -- doing nothing.");
			}
			return false;
		}
		if (cal.params.inputField) {
			cal.params.inputField.value = cal.date.print(cal.params.ifFormat);
		}
		if (cal.params.displayArea) {
			cal.params.displayArea.innerHTML = cal.date.print(cal.params.daFormat);
		}
		if (cal.params.singleClick && cal.dateClicked) {
			cal.callCloseHandler();
		}
		if (typeof cal.params.onUpdate == "function") {
			cal.params.onUpdate(cal);
		}
	};

	if (params.flat != null) {
		params.flat = document.getElementById(params.flat);
		if (!params.flat) {
			alert("Calendar.setup:\n  Flat specified but can't find parent.");
			return false;
		}
		var cal = new Calendar(params.mondayFirst, params.date, params.onSelect || onSelect);
		cal.showsTime = params.showsTime;
		cal.time24 = (params.timeFormat == "24");
		cal.params = params;
		cal.weekNumbers = params.weekNumbers;
		cal.setRange(params.range[0], params.range[1]);
		cal.setDateStatusHandler(params.dateStatusFunc);
		cal.create(params.flat);
		cal.show();
		return false;
	}

	var triggerEl = params.button || params.displayArea || params.inputField;
	triggerEl["on" + params.eventName] = function() {
		var dateEl = params.inputField || params.displayArea;
		var dateFmt = params.inputField ? params.ifFormat : params.daFormat;
		var mustCreate = false;
		var cal = window.calendar;
		if (!window.calendar) {
			window.calendar = cal = new Calendar(params.mondayFirst,
							     params.date,
							     params.onSelect || onSelect,
							     params.onClose || function(cal) { cal.hide(); });
			cal.showsTime = params.showsTime;
			cal.time24 = (params.timeFormat == "24");
			cal.weekNumbers = params.weekNumbers;
			mustCreate = true;
		} else {
			cal.hide();
		}
		cal.setRange(params.range[0], params.range[1]);
		cal.params = params;
		cal.setDateStatusHandler(params.dateStatusFunc);
		cal.setDateFormat(dateFmt);
		if (mustCreate)
			cal.create();
		cal.parseDate(dateEl.value || dateEl.innerHTML);
		cal.refresh();
		cal.showAtElement(params.displayArea || params.inputField, params.align);
		return false;
	};
};

// eGroupWare translations, are read from the database

// ** I18N

// Calendar EN language
// Author: Mihai Bazon, <mishoo@infoiasi.ro>
// Encoding: any
// Distributed under the same terms as the calendar itself.

// full day names
Calendar._DN = new Array
("<?php echo lang('Sunday') ?>",
 "<?php echo lang('Monday'); ?>",
 "<?php echo lang('Tuesday'); ?>",
 "<?php echo lang('Wednesday'); ?>",
 "<?php echo lang('Thursday'); ?>",
 "<?php echo lang('Friday'); ?>",
 "<?php echo lang('Saturday'); ?>",
 "<?php echo lang('Sunday'); ?>");

// please note eGW does NOT use the short month-names atm.

// full month names
Calendar._MN = new Array
("<?php echo lang('January'); ?>",
 "<?php echo lang('February'); ?>",
 "<?php echo lang('March'); ?>",
 "<?php echo lang('April'); ?>",
 "<?php echo lang('May'); ?>",
 "<?php echo lang('June'); ?>",
 "<?php echo lang('July'); ?>",
 "<?php echo lang('August'); ?>",
 "<?php echo lang('September'); ?>",
 "<?php echo lang('October'); ?>",
 "<?php echo lang('November'); ?>",
 "<?php echo lang('December'); ?>");

// tooltips
Calendar._TT = {};
Calendar._TT["INFO"] = "<?php echo lang('About the calendar'); ?>";

Calendar._TT["ABOUT"] =
"DHTML Date/Time Selector\n" +
"(c) dynarch.com 2002-2003\n" + // don't translate this this ;-)
"For latest version visit: http://dynarch.com/mishoo/calendar.epl\n" +
"Distributed under GNU LGPL.  See http://gnu.org/licenses/lgpl.html for details." +
"\n\n" +
"<?php echo lang('Date selection:'); ?>\n" +
"<?php echo lang('- Use the %1, %2 buttons to select year','\xab','\xbb'); ?>\n" +
"<?php echo lang('- Use the %1, %2 buttons to select month','" + String.fromCharCode(0x2039) + "','" + String.fromCharCode(0x203a) + "'); ?>\n" +
"<?php echo lang('- Hold mouse button on any of the above buttons for faster selection.'); ?>";
Calendar._TT["ABOUT_TIME"] = "\n\n" +
"<?php echo lang('Time selection:'); ?>\n" +
"<?php echo lang('- Click on any of the time parts to increase it'); ?>\n" +
"<?php echo lang('- or Shift-click to decrease it'); ?>\n" +
"<?php echo lang('- or click and drag for faster selection.'); ?>";

Calendar._TT["TOGGLE"] = "<?php echo lang('Toggle first day of week'); ?>";
Calendar._TT["PREV_YEAR"] = "<?php echo lang('Prev. year (hold for menu)'); ?>";
Calendar._TT["PREV_MONTH"] = "<?php echo lang('Prev. month (hold for menu)'); ?>";
Calendar._TT["GO_TODAY"] = "<?php echo lang('Go Today'); ?>";
Calendar._TT["NEXT_MONTH"] = "<?php echo lang('Next month (hold for menu)'); ?>";
Calendar._TT["NEXT_YEAR"] = "<?php echo lang('Next year (hold for menu)'); ?>";
Calendar._TT["SEL_DATE"] = "<?php echo lang('Select date'); ?>";
Calendar._TT["DRAG_TO_MOVE"] = "<?php echo lang('Drag to move'); ?>";
Calendar._TT["PART_TODAY"] = " (<?php echo lang('today'); ?>)";
Calendar._TT["MON_FIRST"] = "<?php echo lang('Display Monday first'); ?>";
Calendar._TT["SUN_FIRST"] = "<?php echo lang('Display Sunday first'); ?>";
Calendar._TT["CLOSE"] = "<?php echo lang('Close'); ?>";
Calendar._TT["TODAY"] = "<?php echo lang('Today'); ?>";
Calendar._TT["TIME_PART"] = "<?php echo lang('(Shift-)Click or drag to change value'); ?>";

// date formats
//Calendar._TT["DEF_DATE_FORMAT"] = "%Y-%m-%d";
Calendar._TT["DEF_DATE_FORMAT"] = "<?php echo $jsDateFormat; ?>";
//Calendar._TT["TT_DATE_FORMAT"] = "%a, %b %e";
Calendar._TT["TT_DATE_FORMAT"] = "<?php echo $jsLongDateFormat; ?>";

Calendar._TT["WK"] = "<?php echo lang('Wk'); ?>";
