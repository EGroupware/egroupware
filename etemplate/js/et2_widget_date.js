/**
 * eGroupWare eTemplate2 - JS Date object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	jscalendar.calendar-setup;
	jscalendar.calendar;
	/phpgwapi/js/jscalendar/lang/calendar-en.js;
	lib/date;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "date" XET-Tag
 */ 
var et2_date = et2_inputWidget.extend({

	attributes: {
		"value": {
			"type": "any"
		},
		"type": {
			"ignore": false
		}
	},

	/**
	 * Internal container for working easily with dates
	 */
	date: new Date(),

	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		this.createInputWidget();
	},

	createInputWidget: function() {

		this.input = $j(document.createElement("input"));
		this.button = $j(document.createElement("img"));
		this.button.attr("id", this.options.id + "_button");

		var type="text";
		switch(this.type) {
			case "date-timeonly":
				type = "time";
				break;
		}
		this.input.addClass("et2_date").attr("type", type);

		this.setDOMNode(this.input[0]);

		var this_id = this.options.id;
		var this_button_id = this.button.attr("id");
		var this_showsTime = this.type == "date-time";
		

		if(this.type == "date" || this.type == "date-time") {
			window.setTimeout(function() {
				Calendar.setup({
					inputField: this_id,
					button: this_button_id,
					showsTime: this_showsTime,
					timeFormat: egw.preference("timeformat")
				});
			}, 500);
		}
	},

	set_type: function(_type) {
		this.type = _type;
		this.createInputWidget();
	},

	set_value: function(_value) {
		if(typeof _value == 'string' && isNaN(_value)) {
			if(_value.indexOf(":") > 0 && this.type == "date-timeonly") {
				return this._super.apply(this, [_value]);
			} else {
				_value = Date.parse(_value);
				// JS dates use milliseconds
				this.date.setTime(parseInt(_value)*1000);
			}
		} else {
			// JS dates use milliseconds
			this.date.setTime(parseInt(_value)*1000);
		}
		this.value = _value;

		var display = this.date.toString();

		switch(this.type) {
			case "date":
				display = date(egw.preference('dateformat'), this.date);
				break;
			case "date-timeonly":
				display = date(egw.preference('timeformat') == '24' ? 'H:i' : 'g:i a', this.date);
				break;
			case "date-time":
				display = date(egw.preference('dateformat') + " " + 
					(egw.preference('timeformat') == '24' ? 'H:i' : 'g:i a'), this.date);
				break;
		}
		this._super.apply(this, [display]);
	}
});

et2_register_widget(et2_date, ["date", "date-time", "date-timeonly"]);

/**
 * et2_date_ro is the dummy readonly implementation of the date widget.
 */
var et2_date_ro = et2_valueWidget.extend({

	/**
	 * Ignore all more advanced attributes.
	 */
	attributes: {
		"value": {
			"type": "integer"
		},
		"type": {
			"ignore": false
		}
	},

	/**
	 * Internal container for working easily with dates
	 */
	date: new Date(),

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("time"))
			.addClass("et2_date_ro et2_label");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		this.value = _value;

		// JS dates use milliseconds
		if(isNaN(_value) && _value.indexOf(":") > 0 && this.type == "date-timeonly") {
			this.span.text(_value);
		}
		this.date.setTime(parseInt(_value)*1000);
		var display = this.date.toString();

		// TODO: Use user's preference, not browser's locale
		switch(this.type) {
			case "date":
				display = date(egw.preference('dateformat'), this.date);
				break;
			case "date-timeonly":
				display = date(egw.preference('timeformat') == '24' ? 'H:i' : 'g:i a', this.date);
				break;
			case "date-time":
				display = date(egw.preference('dateformat') + " " +  
					(egw.preference('timeformat') == '24' ? 'H:i' : 'g:i a'), this.date);
		}
		this.span.attr("datetime", date("Y-m-d H:i:s",this.date)).text(display);
	}

});

et2_register_widget(et2_date_ro, ["date_ro", "date-time_ro"]);

