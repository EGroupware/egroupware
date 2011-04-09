<?php
/**
 * eGroupWare - API jsCalendar setup (set up jsCalendar with user prefs)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage tools
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp'  => 'home',
		'noheader'    => True,
		'nonavbar'    => True,
		'noappheader' => True,
		'noappfooter' => True,
		'nofooter'    => True,
		'nocachecontrol' => True			// allow cacheing
	)
);
try {
	include('../../header.inc.php');
}
catch (egw_exception_no_permission_app $e) {
	// ignore exception, if home is not allowed, eg. for sitemgr
}

header('Content-type: text/javascript; charset='.translation::charset());
translation::add_app('jscalendar');

$dateformat = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
if (empty($dateformat)) $dateformat = 'Y-m-d';
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
 *   titleFormat   | the format to show the month in the title, default '%B, %Y'
 *   singleClick   | (true/false) wether the calendar is in single click mode or not (default: true)
 *   firstDay      | numeric: 0 to 6.  "0" means display Sunday first, "1" means display Monday first, etc.
 *   disableFirstDowChange| (true/false) disables manual change of first day of week
 *   align         | alignment (default: "Br"); if you don't know what's this see the calendar documentation
 *   range         | array with 2 elements.  Default: [1900, 2999] -- the range of years available
 *   weekNumbers   | (true/false) if it's true (default) the calendar will display week numbers
 *   flat          | null or element ID; if not null the calendar will be a flat calendar having the parent with the given ID
 *   flatCallback  | function that receives a JS Date object and returns an URL to point the browser to (for flat calendar)
 *   flatWeekCallback| gets called if a weeknumber get clicked, params are the cal-object and a date-object representing the start of the week
 *   flatWeekTTip  | Tooltip for the weeknumber (shown only if flatWeekCallback is set)
 *   flatMonthCallback| gets called if a month (title) get clicked, params are the cal-object and a date-object representing the start of the month
 *   flatMonthTTip | Tooltip for the month (shown only if flatMonthCallback is set)
 *   disableFunc   | function that receives a JS Date object and should return true if that date has to be disabled in the calendar
 *   onSelect      | function that gets called when a date is selected.  You don't _have_ to supply this (the default is generally okay)
 *   onClose       | function that gets called when the calendar is closed.  [default]
 *   onUpdate      | function that gets called after the date is updated in the input field.  Receives a reference to the calendar.
 *   date          | the date that the calendar will be initially displayed to
 *   showsTime     | default: false; if true the calendar will include a time selector
 *   timeFormat    | the time format; can be "12" or "24", default is "12"
 *   electric      | if true (default) then given fields/date areas are updated for each move; otherwise they're updated only on close
 *   step          | configures the step of the years in drop-down boxes; default: 2
 *   position      | configures the calendar absolute position; default: null
 *   cache         | if "true" (but default: "false") it will reuse the same calendar object, where possible
 *   showOthers    | if "true" (but default: "false") it will show days from other months too
 *
 *  None of them is required, they all have default values.  However, if you
 *  pass none of "inputField", "displayArea" or "button" you'll get a warning
 *  saying "nothing to setup".
 */
?>
//<pre>
Calendar.setup = function (params) {
	function param_default(pname, def) { if (typeof params[pname] == "undefined") { params[pname] = def; } };

	param_default("inputField",     null);
	param_default("displayArea",    null);
	param_default("button",         null);
	param_default("eventName",      "click");
	param_default("ifFormat",      "<?php /* was "%Y/%m/%d" */ echo $jsDateFormat; ?>");
	param_default("daFormat",      "<?php /* was "%Y/%m/%d" */ echo $jsDateFormat; ?>");
	param_default("titleFormat",    "%B %Y");
	param_default("singleClick",    true);
	param_default("disableFunc",    null);
	param_default("dateStatusFunc", params["disableFunc"]);	// takes precedence if both are defined
	param_default("disableFirstDowChange", true);
	param_default("firstDay",       <?php // was 0 defaults to "Sunday" first
	$day2int = array('Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6);
	echo (int) @$day2int[$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts']]; ?>); // <?php echo $GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts']."\n"; ?>
	param_default("align",          "Bl");
	param_default("range",          [1900, 2999]);
	param_default("weekNumbers",    true);
	param_default("flat",           null);
	param_default("flatCallback",   null);
	param_default("flatWeekCallback",null);
	param_default("flatWeekTTip",   null);
	param_default("flatmonthCallback",null);
	param_default("flatmonthTTip",  null);
	param_default("onSelect",       null);
	param_default("onClose",        null);
	param_default("onUpdate",       null);
	param_default("date",           null);
	param_default("showsTime",      false);
	param_default("timeFormat",     "<?php /* was 24 */ echo $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] ? $GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] : 24; ?>");
	param_default("electric",       true);
	param_default("step",           2);
	param_default("position",       null);
	param_default("cache",          true);
	param_default("showOthers",     true); <?php /* was false */ ?>

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
		var p = cal.params;
		var update = (cal.dateClicked || p.electric);
		if (update && p.flat) {
			if (typeof p.flatCallback == "function")
				p.flatCallback(cal);
			else
				alert("No flatCallback given -- doing nothing.");
			return false;
		}
		if (update && p.inputField) {
			p.inputField.value = cal.date.print(p.ifFormat);
			if (typeof p.inputField.onchange == "function")
				p.inputField.onchange();
		}
		if (update && p.displayArea)
			p.displayArea.innerHTML = cal.date.print(p.daFormat);
		if (update && p.singleClick && cal.dateClicked)
			cal.callCloseHandler();
		if (update && typeof p.onUpdate == "function")
			p.onUpdate(cal);
	};

	if (params.flat != null) {
		if (typeof params.flat == "string")
			params.flat = document.getElementById(params.flat);
		if (!params.flat) {
			alert("Calendar.setup:\n  Flat specified but can't find parent.");
			return false;
		}
		var cal = new Calendar(params.firstDay, params.date, params.onSelect || onSelect);
		cal.showsTime = params.showsTime;
		cal.time24 = (params.timeFormat == "24");
		cal.params = params;
		cal.weekNumbers = params.weekNumbers;
		cal.setRange(params.range[0], params.range[1]);
		cal.setDateStatusHandler(params.dateStatusFunc);
		cal.showsOtherMonths = params.showOthers;
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
		if (!(cal && params.cache)) {
			window.calendar = cal = new Calendar(params.firstDay,
							     params.date,
							     params.onSelect || onSelect,
							     params.onClose || function(cal) { cal.hide(); });
			cal.showsTime = params.showsTime;
			cal.time24 = (params.timeFormat == "24");
			cal.weekNumbers = params.weekNumbers;
			mustCreate = true;
		} else {
			if (params.date)
				cal.setDate(params.date);
			cal.hide();
		}
		cal.showsOtherMonths = params.showOthers;
		cal.yearStep = params.step;
		cal.setRange(params.range[0], params.range[1]);
		cal.params = params;
		cal.setDateStatusHandler(params.dateStatusFunc);
		cal.setDateFormat(dateFmt);
		if (mustCreate)
			cal.create();
		cal.parseDate(dateEl.value || dateEl.innerHTML);
		cal.refresh();
		if (!params.position)
			cal.showAtElement(params.button || params.displayArea || params.inputField, params.align);
		else
			cal.showAt(params.position[0], params.position[1]);
		return false;
	};
};

// eGroupWare translations, are read from the database

// ** I18N

// Calendar EN language
// Author: Mihai Bazon, <mishoo@infoiasi.ro>
// Encoding: any
// Distributed under the same terms as the calendar itself.

Calendar._DN = new Array
(<?php // full day names
foreach($day2int as $name => $n)
{
	echo "\n \"".lang($name).'"'.($n < 6 ? ',' : '');
}
?>);

Calendar._SDN = new Array
(<?php // short day names
static $substr;
if(is_null($substr)) $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
$chars_shortcut = (int) lang('3 number of chars for day-shortcut');		// < 0 to take the chars from the end
$markuntranslated = translation::$markuntranslated;
translation::$markuntranslated = true;		// otherwise we can not detect NOT translated phrases!
foreach($day2int as $name => $n)
{
	$short = lang($m = substr($name,0,3));	// test if our lang-file have a translation for the english short with 3 chars
	if ($substr($short,-1) == '*')			// else create one by truncating the full translation to x chars
	{
		$short = $chars_shortcut > 0 ? $substr(lang($name),0,$chars_shortcut) : $substr(lang($name),$chars_shortcut);
	}
	echo "\n \"".$short.'"'.($n < 6 ? ',' : '');
}
?>);
Calendar._SDN_len = <?php echo abs((int) lang('3 number of chars for day-shortcut')); ?>;

Calendar._MN = new Array
(<?php // full month names
translation::$markuntranslated = $markuntranslated;
$monthnames = array('January','February','March','April','May','June','July','August','September','October','November','December');
foreach($monthnames as $n => $name)
{
	echo "\n \"".lang($name).'"'.($n < 11 ? ',' : '');
}
?>);

Calendar._SMN = new Array
(<?php // short month names
translation::$markuntranslated = true;		// otherwise we can not detect NOT translated phrases!
$chars_shortcut = (int)lang('3 number of chars for month-shortcut');	// < 0 to take the chars from the end
foreach($monthnames as $n => $name)
{
	$short = lang($m = substr($name,0,3));	// test if our lang-file have a translation for the english short with 3 chars
	if ($substr($short,-1) == '*')			// else create one by truncating the full translation to x chars
	{
        $short = $chars_shortcut > 0 ? $substr(lang($name),0,$chars_shortcut) : $substr(lang($name),$chars_shortcut);
	}
	echo "\n \"".$short.'"'.($n < 11 ? ',' : '');
}
translation::$markuntranslated = $markuntranslated;
?>);
Calendar._SMN_len = <?php echo abs((int) lang('3 number of chars for month-shortcut')); ?>;

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

// the following is to inform that "%s" is to be the first day of week
// %s will be replaced with the day name.
Calendar._TT["DAY_FIRST"] = "<?php echo lang('Display %s first'); ?>";

// This may be locale-dependent.  It specifies the week-end days, as an array
// of comma-separated numbers.  The numbers are from 0 to 6: 0 means Sunday, 1
// means Monday, etc.
Calendar._TT["WEEKEND"] = "0,6";

Calendar._TT["CLOSE"] = "<?php echo lang('Close'); ?>";
Calendar._TT["TODAY"] = "<?php echo lang('Today'); ?>";
Calendar._TT["TIME_PART"] = "<?php echo lang('(Shift-)Click or drag to change value'); ?>";

// date formats
//Calendar._TT["DEF_DATE_FORMAT"] = "%Y-%m-%d";
Calendar._TT["DEF_DATE_FORMAT"] = "<?php echo $jsDateFormat; ?>";
//Calendar._TT["TT_DATE_FORMAT"] = "%a, %b %e";
Calendar._TT["TT_DATE_FORMAT"] = "<?php echo $jsLongDateFormat; ?>";

Calendar._TT["WK"] = "<?php echo lang('Wk'); ?>";
Calendar._TT["TIME"] = "<?php echo lang('Time'); ?>:";
