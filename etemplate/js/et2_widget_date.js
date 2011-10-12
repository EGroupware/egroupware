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

		this.input_date = $j(document.createElement("input"));

		var type=(this.type == "date-timeonly" ? "time" : "text");
		this.input_date.addClass("et2_date").attr("type", type).attr("size", 5);

		var node = this.input_date;

		// Add a button
		if(this.type == "date" || this.type == "date-time") {
			this.span = $j(document.createElement("span"));
			this.button = $j(document.createElement("span"));
			this.button.attr("id", this.options.id + "-trigger");
			this.span.append(this.input_date).append(this.button);

			// Icon could be done in CSS file
			var button_image = egw.image('datepopup','phpgwapi');
			if(button_image) 
			{
				this.button.css("background-image","url("+button_image+")");
			}

			node = this.span;

			node.addClass("et2_date");

			var _this = this;
			var dateformat = egw.preference("dateformat");
			if (!dateformat) dateformat = "Y-m-d";
			dateformat = dateformat.replace("Y","%Y").replace("d","%d").replace("m","%m").replace("M", "%b");

			var setup = {
				inputField: this.options.id,
				button: this.button.attr("id"),
				showsTime: false,
				onUpdate: function(_value) {_this.set_value(_value)},
				daFormat: dateformat,
				firstDay: egw.preference("weekdaystarts","calendar")
			};
			window.setTimeout(function() {
				Calendar.setup(setup);
			}, 500);
		}

		// If date also has a time, or browser doesn't support HTML5 time type 
		if(this.type == "date-time" || this.type == "date-timeonly" && this.input_date.attr("type") == 'text')
		{
			if(!this.span)
			{
				this.span = $j(document.createElement("span"));
				node = this.span;
			}
			switch(this.type)
			{
				case "date-time":
					var input_time = $j(document.createElement("input")).attr("type", "time");
					if(input_time.attr("type") == "time")
					{
						this.input_time = input_time;
						this.input_time.appendTo(this.span).attr("size", 5);
						break;
					}
					// Fall through
				default:
					this._make_time_selects(this.span);
					break;
			}
		}
		else if (this.type =="date-timeonly")
		{
			// Update internal value if control changes
			this.input_date.change(this,function(e){e.data.set_value($j(e.target).val());});
		}
		this.setDOMNode(node[0]);
	},

	_make_time_selects: function (node) {
		var timeformat = egw.preference("timeformat");
		this.input_hours = $j(document.createElement("select"));
		for(var i = 0; i < 24; i++)
		{
			var time = i;
			if(timeformat == 12)
			{
				switch(i)
				{
					case 0:
						time = "12 am";
						break;
					case 12: time = "12 pm";
						break;
					default: 
						time = i % 12 + " " + (i < 12 ? "am" : "pm");
				}
			}
			else if (time < 10) 
			{
				time = "0" + time;
			}
			var option = $j(document.createElement("option")).attr("value", i).text(time);
			option.appendTo(this.input_hours);
		}
		this.input_hours.appendTo(node).change(this, function(e) {
			if(e.data.type == "date-timeonly")
			{
				e.data.set_value(e.target.options[e.target.selectedIndex].value + ":" + $j('option:selected',e.data.input_minutes).text());
			}
			else
			{
				e.data.date.setHours(e.target.options[e.target.selectedIndex].value);
				e.data.set_value(e.data.date.valueOf());
			}
		});
		node.append(":");
		this.input_minutes = $j(document.createElement("select"));
		for(var i = 0; i < 60; i+=5)
		{
			var time = i;
			if(time < 10)
			{
				time = "0"+time;
			}
			var option = $j(document.createElement("option")).attr("value", time).text(time);
			option.appendTo(this.input_minutes);
		}
		this.input_minutes.appendTo(node).change(this, function(e) {
			if(e.data.type == "date-timeonly")
			{
				e.data.set_value($j('option:selected',e.data.input_hours).val() + ":" + e.target.options[e.target.selectedIndex].text);
			}
			else
			{
				e.data.date.setMinutes(e.target.options[e.target.selectedIndex].value);
				e.data.set_value(e.data.date.valueOf());
			}
		});
	},
	set_type: function(_type) {
		this.type = _type;
		this.createInputWidget();
	},

	set_value: function(_value) {
		if(_value == null)
		{
			this.value = _value;

			if(this.input_date)
			{
				this.input_date.val("");
			}
			if(this.input_time)
			{
				this.input_time.val("");
			}
			return;
		}

		// Handle just time as a string in the form H:i
		if(typeof _value == 'string' && isNaN(_value)) {
			if(_value.indexOf(":") > 0 && this.type == "date-timeonly") {
				this.value = _value;
				// HTML5
				if(!this.input_hours)
				{
					return this._super.apply(this, [_value]);
				}
				else
				{
					var parts = _value.split(":");
					$j("option[value='"+parts[0]+"']",this.input_hours).attr("selected","selected");
					if($j("option[value='"+parseInt(parts[1])+"']",this.input_minutes).length == 0)
					{
						// Selected an option that isn't in the list
						var i = parseInt(parts[1]);
						var option = $j(document.createElement("option")).attr("value", i).text(i < 10 ? "0"+i : i).attr("selected","selected");
						option.appendTo(this.input_minutes);
					}
					else
					{
						$j("option[value='"+parts[1]+"']",this.input_minutes).attr("selected","selected");
					}
					return;
				}
			} else {
				_value = Date.parse(_value);
				// JS dates use milliseconds
				this.date.setTime(parseInt(_value)*1000);
			}
		} else if (typeof _value == 'integer') {
			// JS dates use milliseconds
			this.date.setTime(parseInt(_value)*1000);
		} else if (typeof _value == 'object' && _value.date) {
			this.date = _value.date;
			_value = this.date.valueOf();
		}
		this.value = _value;

		if(this.input_date)
		{
			this.input_date.val(date(egw.preference('dateformat'), this.date));
		}
		if(this.input_time)
		{
			this.input_time.val(date("H:i", this.date));
		}
		if(this.input_hours)
		{
			$j("option[value='"+date("H",this.date)+"']",this.input_hours).attr("selected","selected");
		}
		if(this.input_minutes)
		{
			if($j("option[value='"+parseInt(date("i",this.date))+"']",this.input_minutes).length == 0)
			{
				// Selected an option that isn't in the list
				var i = date("i",this.date);
				var option = $j(document.createElement("option")).attr("value", i).text(i).attr("selected","selected");
				option.appendTo(this.input_minutes);
			} else {
				$j("option[value='"+date("i",this.date)+"']",this.input_minutes).attr("selected","selected");
			}
		}
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

	time_formats: {"d":"d","h":"h","m":"m"},

	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		// Legacy option put percent in with display format
		if(this.options.display_format.indexOf("%") != -1)
		{
			this.options.percent_allowed = true;
			this.options.display_format = this.options.display_format.replace("%","");
		}

		// Get translations
		this.time_formats = {
			"d": this.options.short_labels ? egw.lang("m") : egw.lang("Days"),
			"h": this.options.short_labels ? egw.lang("h") : egw.lang("Hours"),
			"m": this.options.short_labels ? egw.lang("m") : egw.lang("Minutes")
		},
		this.createInputWidget();
	},
	
	createInputWidget: function() {
		// Create nodes
		this.node = $j(document.createElement("span"));
		this.duration = $j(document.createElement("input")).attr("size", "2");
		this.node.append(this.duration);

		if(this.options.display_format.length > 1)
		{
			this.format = $j(document.createElement("select"));
			this.node.append(this.format);

			for(var i = 0; i < this.options.display_format.length; i++) {
				this.format.append("<option value='"+this.options.display_format[i]+"'>"+this.time_formats[this.options.display_format[i]]+"</option>");
			}
		} else {
			this.format = $j(document.createElement("<span>"+this.time_formats[this.options.display_format])+"</span>").appendTo(this.node);
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

		var display = this._convert_to_display(_value);

		// Set display
		if(this.duration[0].nodeName == "INPUT")
		{
			this.duration.val(display.value);
		}
		else
		{
			this.duration.text(display.value + " ");
		}

		// Set unit as figured for display
		if(display.unit != this.options.display_format)
		{
			if(this.format.children().length > 1) {
				$j("option[value='"+display.unit+"']",this.format).attr('selected','selected');
			}
			else
			{
				this.format.text(this.time_formats[display.unit]);
			}
		}
	},

	/**
	 * Converts the value in data format into value in display format.
	 *
	 * @param _value int/float Data in data format
	 *
	 * @return Object {value: Value in display format, unit: unit for display}
	 */
	_convert_to_display: function(_value) {
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

		if(_value === '') _unit = '';

                // use decimal separator from user prefs
		var sep = '.';
		var format = egw.preference('number_format');
                if (format && (sep = format[0]) && sep != '.')
                {
                        _value = _value.replace('.',sep);
                }

		return {value: _value, unit:_unit};
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


var et2_date_duration_ro = et2_date_duration.extend([et2_IDetachedDOM],{
	createInputWidget: function() {

		this.node = $j(document.createElement("span"));
		var display = this._convert_to_display(this.options.value);
		this.duration = $j(document.createElement("span")).appendTo(this.node);
		this.format = $j(document.createElement("span")).appendTo(this.node);
	},

	/**
	 * Code for implementing et2_IDetachedDOM 
	 * Fast-clonable read-only widget that only deals with DOM nodes, not the widget tree
	 */

	/**
	 * Build a list of attributes which can be set when working in the
	 * "detached" mode in the _attrs array which is provided
	 * by the calling code.
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value");
	},

	/**
	 * Returns an array of DOM nodes. The (relativly) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes: function() {
		return [this.duration[0], this.format[0]];
	},

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which has to be in the same order as
	 *      the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 *      returned by the "getDetachedAttributes" function and sets them to the
	 *      given values.
	 */
	setDetachedAttributes: function(_nodes, _values) {
		for(var i = 0; i < _nodes.length; i++) {
			// Clear the node
			for (var j = _nodes[i].childNodes.length - 1; j >= 0; j--)
			{
				_nodes[i].removeChild(_nodes[i].childNodes[j]);
			}
		}
		var display = this._convert_to_display(_values.value);
		_nodes[0].appendChild(document.createTextNode(display.value));
		_nodes[1].appendChild(document.createTextNode(display.unit));
	}

});
et2_register_widget(et2_date_duration_ro, ["date-duration_ro"]);

/**
 * et2_date_ro is the readonly implementation of some date widget.
 */
var et2_date_ro = et2_valueWidget.extend([et2_IDetachedDOM], {

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
		if(typeof _value == 'undefined') _value = 0;

		this.value = _value;

		if(_value == 0)
		{
			this.span.attr("datetime", "");
			return;
		}

		// JS dates use milliseconds
		this.date.setTime(parseInt(_value)*1000);
		var display = this.date.toString();

		switch(this._type) {
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
					's': 'seconds'
				};
				var unit2s = {
					'Y': 31536000,
					'm': 2628000,
					'd': 86400,
					'H': 3600,
					'i': 60,
					's': 1
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
	},

	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value", "class");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes: function() {
		return [this.span[0]];
	},

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which have to be in the same order as
	 *      the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 *      returned by the "getDetachedAttributes" function and sets them to the
	 *      given values.
	 */
	setDetachedAttributes: function(_nodes, _values) {
		this.span = jQuery(_nodes[0]);
		this.set_value(_values["value"]);

		if(_values["class"]) 
		{
			this.span.addClass(_values["class"]);
		}
	}


});

et2_register_widget(et2_date_ro, ["date_ro", "date-time_ro", "date-since"]);


var et2_date_timeonly_ro = et2_date_ro.extend({

	attributes: {
		"value": {
			"type": "string"
		}
	},
	set_value: function(_value) {
		if(egw.preference("timeformat") == "12" && _value.indexOf(":") > 0) {
			var parts = _value.split(":");
			if(parts[0] >= 12) {
				this.span.text((parts[0] == "12" ? "12" : parseInt(parts[0])-12)+":"+parts[1]+" pm");
			}
			else
			{
				this.span.text(_value + " am");
			}
		}
		else
		{
			this.span.text(_value);
		}
	}
});
et2_register_widget(et2_date_timeonly_ro, ["date-timeonly_ro"]);
