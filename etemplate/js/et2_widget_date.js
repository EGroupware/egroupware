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

		var type="text";
		switch(this.type) {
			case "date-timeonly":
				type = "time";
				break;
		}
		this.input.addClass("et2_date").attr("type", type);

		var node = this.input;

		// Add a button
		if(this.type == "date" || this.type == "date-time") {
			this.span = $j(document.createElement("span"));
			this.button = $j(document.createElement("button"));
			this.button.attr("id", this.options.id + "_button");
			this.span.append(this.input).append(this.button);
			node = this.span;

			node.addClass("et2_date");

			var this_id = this.options.id;
			var this_button_id = this.button.attr("id");
			var this_showsTime = this.type == "date-time";
			
			window.setTimeout(function() {
				Calendar.setup({
					inputField: this_id,
					button: this_button_id,
					showsTime: this_showsTime,
					timeFormat: egw.preference("timeformat"),
					onUpdate: this.set_value
				});
			}, 500);
		}
		this.setDOMNode(node[0]);
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
		this.input.val(display);
	},

	getValue: function() {
		return this.value;
	}
});

et2_register_widget(et2_date, ["date", "date-time", "date-timeonly"]);

var et2_date_duration = et2_date.extend({
	attributes: {
		"data_format": {
			"name": "Data format",
			"default": "m",
			"type": "string",
			"description": "Units to read/store the data.  'd' = days (float), 'h' = hours (float), 'm' = minutes (int)."
		},
		"display_format": {
			"name": "Display format",
			"default": "dh",
			"type": "string",
			"description": "Permitted units for displaying the data.  'd' = days, 'h' = hours, 'm' = minutes.  Use combinations to give a choice.  Default is 'dh' = days or hours with selectbox."
		},
		"percent_allowed": {
			"name": "Percent allowed",
			"default": false,
			"type": "boolean",
			"description": "Allows to enter a percentage."
		},
		"hours_per_day": {
			"name": "Hours per day",
			"default": 8,
			"type": "integer",
			"description": "Number of hours in a day, for converting between hours and (working) days."
		},
		"empty_not_0": {
			"name": "0 or empty",
			"default": false,
			"type": "boolean",
			"description": "Should the widget differ between 0 and empty, which get then returned as NULL"
		},
		"short_labels": {
			"name": "Short labels",
			"default": false,
			"type": "boolean",
			"description": "use d/h/m instead of day/hour/minute"
		}
	},

	legacyOptions: ["data_format","display_format", "hours_per_day", "empty_not_0", "short_labels"],

	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		// Legacy option put percent in with display format
		if(this.options.display_format.indexOf("%") != -1)
		{
			this.options.percent_allowed = true;
			this.options.display_format = this.options.display_format.replace("%","");
		}
		this.createInputWidget();
	},
	
	createInputWidget: function() {
		// Create nodes
		this.node = $j(document.createElement("span"));
		this.duration = $j(document.createElement("input")).attr("size", "5");
		this.node.append(this.duration);

		// Time format labels
		var time_formats = {
			"d": this.options.short_labels ? egw.lang("m") : egw.lang("Days"),
			"h": this.options.short_labels ? egw.lang("h") : egw.lang("Hours"),
			"m": this.options.short_labels ? egw.lang("m") : egw.lang("Minutes")
		};
		if(this.options.display_format.length > 1)
		{
			this.format = $j(document.createElement("select"));
			this.node.append(this.format);

			for(var i = 0; i < this.options.display_format.length; i++) {
				this.format.append("<option value='"+this.options.display_format[i]+"'>"+time_formats[this.options.display_format[i]]+"</option>");
			}
		} else {
			this.node.append(time_formats[this.options.display_format]);
		}
	},
	attachToDOM: function() {
		var node = this.getInputNode();
                if (node)
                {
                        $j(node).bind("change.et2_inputWidget", this, function(e) {
                                e.data.change(this);
                        });
                }
		et2_DOMWidget.prototype.attachToDOM.apply(this, arguments);
	},
	getDOMNode: function() {
		return this.node[0];
	},
	getInputNode: function() {
		return this.duration[0];
	},

	/**
	 * Use id on node, same as DOMWidget
	 */
	set_id: function(_value) {
		this.id = _value;

                var node = this.getDOMNode(this);
                if (node)
                {
                        if (_value != "")
                        {
                                node.setAttribute("id", _value);
                        }
                        else
                        {
                                node.removeAttribute("id");
                        }
                }
	},
	set_value: function(_value) {
		this.options.value = _value;
		if (_value)
                {
			// Put value into minutes for further processing
                        switch(this.options.data_format)
			{
				case 'd':
					_value *= this.options.hours_per_day;
					// fall-through
				case 'h':
					_value *= 60;
					break;
			}
                }


		// Figure out best unit for display
		var _unit = this.options.display_format == "d" ? "d" : "h";
		if (this.options.data_format.indexOf('m') > -1 && _value && _value < 60)
                {
                        _unit = 'm';
                }
                else if (this.options.data_format.indexOf('d') > -1 && _value >= 60*this.options.hours_per_day)
                {
                        _unit = 'd';
                }
		_value = this.options.empty_not_0 && _value === '' || !this.options.empty_not_0 && !_value ? '' :
                        (_unit == 'm' ? parseInt( _value) : (Math.round((_value / 60.0 / (_unit == 'd' ? this.options.hours_per_day : 1))*100)/100));
                // use decimal separator from user prefs
		var sep = '.';
		var format = egw.preference('number_format');
                if (format && (sep = format[0]) && sep != '.')
                {
                        _value = _value.replace('.',sep);
                }

		// Set unit as figured above
		if(_unit != this.options.display_format && this.format)
		{
			$j("option[value='"+_unit+"']",this.format).attr('selected','selected');
		}

		// Set display
		this.duration.val(_value);
	},
	
	/**
	 * Change displayed value into storage value and return
	 */
	getValue: function() {
		var value = this.duration.val();
		if(value === '')
		{
			return this.options.empty_not_0 ? null : '';
		}
		// Put value into minutes for further processing
		switch(this.format ? this.format.val() : this.options.display_format)
		{
			case 'd':
				value *= this.options.hours_per_day;
				// fall-through
			case 'h':
				value *= 60;
				break;
		}
		switch(this.options.data_format)
		{
			case 'd':
				value /= this.options.hours_per_day;
				// fall-through
			case 'h':
				value /= 60.0;
				break;
		}
		return value;
	}
});
et2_register_widget(et2_date_duration, ["date-duration"]);

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
		this.span = $j(document.createElement(this.type == "date-since" ? "span" : "time"))
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
				break;
			case "date-since":
				var unit2label = {
					'Y': 'years',
					'm': 'month',
					'd': 'days',
					'H': 'hours',
					'i': 'minutes',
					's': 'seconds',
				};
				var unit2s = {
					'Y': 31536000,
					'm': 2628000,
					'd': 86400,
					'H': 3600,
					'i': 60,
					's': 1,
				};
				var d = new Date();
				var diff = Math.round(d.valueOf() / 1000) - Math.round(this.date.valueOf()/1000);
				display = '';

				for(var unit in unit2s)
				{
					var unit_s = unit2s[unit];
					if (diff >= unit_s || unit == 's')
					{
						display = Math.round(diff/unit_s,1)+' '+egw.lang(unit2label[unit]);
						break;
					}
				}
				break
		}
		this.span.attr("datetime", date("Y-m-d H:i:s",this.date)).text(display);
	}

});

et2_register_widget(et2_date_ro, ["date_ro", "date-time_ro", "date-since"]);


