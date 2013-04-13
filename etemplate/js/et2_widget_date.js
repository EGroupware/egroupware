/**
 * EGroupware eTemplate2 - JS Date object
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
	jquery.jquery-ui;
	lib/date;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "date" XET-Tag
 * 
 * @augments et2_inputWidget
 */ 
var et2_date = et2_inputWidget.extend(
{
	attributes: {
		"value": {
			"type": "any"
		},
		"type": {
			"ignore": false
		},
		"data_format": {
			"ignore": true,
			"description": "Format data is in.  This is not used client-side because it's always a timestamp client side."
		}
	},

	legacyOptions: ["data_format"],

	/**
	 * Constructor
	 * 
	 * @memberOf et2_date
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.date = new Date();
		this.date.setHours(0);
		this.date.setMinutes(0);
		this.date.setSeconds(0);
		this.input = null;

		this.createInputWidget();
	},

	createInputWidget: function() {

		this.span = $j(document.createElement("span")).addClass("et2_date");

		this.input_date = $j(document.createElement("input"));
		var type=(this._type == "date-timeonly" ? "time" : "text");
		this.input_date.addClass("et2_date").attr("type", type).attr("size", 5)
			.appendTo(this.span);

		this.setDOMNode(this.span[0]);

		// jQuery-UI date picker
		if(this._type != 'date-timeonly')
		{
			this.egw().calendar(this.input_date, this._type == "date-time");
		}
		else
		{
			this.egw().time(this.input_date);
		}
		// Update internal value when changed
		var self = this;
		this.input_date.datepicker("option","onSelect", function(text,inst) {
			var d = new Date();
			var date_inst = null;
			if(inst.inst && inst.inst.selectedYear)
			{
				date_inst = inst.inst;
			}
			else if (inst.selectedYear)
			{
				date_inst = inst;
			}
			// Date could be in different places, if it's a datetime or just date
			if(date_inst)
			{
				d.setYear(date_inst.selectedYear);
				d.setMonth(date_inst.selectedMonth);
				d.setDate(date_inst.selectedDay);
			}
			if(inst && inst.hour)
			{
				d.setHours(inst.hour);
				d.setMinutes(inst.minute);
			}
			self.set_value(d);
		});

		// Framewok skips nulls, but null needs to be processed here
		if(this.options.value == null)
		{
			this.set_value(null);
		}
	},

	set_type: function(_type) {
		if(_type != this._type)
		{
			this._type = _type;
			this.createInputWidget();
		}
	},

	set_value: function(_value) {
		var old_value = this.getValue();
		if(_value == null || _value == 0)
		{
			this.value = _value;

			if(this.input_date)
			{
				this.input_date.val("");
			}
			if(old_value !== this.value)
			{
				this.change(this.input_date);
			}
			return;
		}

		// Handle just time as a string in the form H:i
		if(typeof _value == 'string' && isNaN(_value)) {
			if(_value.indexOf(":") > 0 && this._type == "date-timeonly") {
				this.value = _value;
				this.input_date.timepicker('setTime',_value);
				if(old_value !== this.value)
				{
					this.change(this.input_date);
				}
				return;
			} else {
				var text = new Date(_value);

				// Handle timezone offset - times are already in user time
				var localOffset = text.getTimezoneOffset() * 60000;
				this.date.setTime(text.valueOf()+localOffset);
				_value = Math.round(this.date.valueOf() / 1000);
			}
		} else if (typeof _value == 'number') {
			// Timestamp
			// JS dates use milliseconds
			this.date.setTime(parseInt(_value)*1000);
		} else if (typeof _value == 'object' && _value.date) {
			this.date = _value.date;
		} else if (typeof _value == 'object' && _value.valueOf) {
			this.date = _value;
		}

		// Update input - popups do, but framework doesn't
		if(this._type != 'date-timeonly')
		{
			this.input_date.val(jQuery.datepicker.formatDate(this.input_date.datepicker("option","dateFormat"),this.date));
		}
		if(this._type != 'date')
		{
			var current = this.input_date.val();
			if(this._type != 'date-timeonly')
			{
				current += " ";
			}
			this.input_date.val(current + jQuery.datepicker.formatTime(this.input_date.datepicker("option","timeFormat"),{
				hour: this.date.getHours(),
				minute: this.date.getMinutes(),
				seconds: this.date.getSeconds(),
				timezone: this.date.getTimezoneOffset()
			}));
		}
		if(old_value != this.getValue())
		{
			this.change(this.input_date);
		}
	},

	getValue: function() {
		if(this.input_date.val() == "")
		{
			// User blanked the box
			return null;
		}
		if(this._type == "date-timeonly")
		{ 
			return this.value;
		}
		else
		{
			// Convert to timestamp
			return Math.round(this.date.valueOf() / 1000);
		}
	}
});
et2_register_widget(et2_date, ["date", "date-time", "date-timeonly"]);

/**
 * @augments et2_date
 */
var et2_date_duration = et2_date.extend(
{
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

	/**
	 * Constructor
	 * 
	 * @memberOf et2_date_duration
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		// Legacy option put percent in with display format
		if(this.options.display_format.indexOf("%") != -1)
		{
			this.options.percent_allowed = true;
			this.options.display_format = this.options.display_format.replace("%","");
		}

		// Clean formats
		this.options.display_format = this.options.display_format.replace(/[^dhm]/,'');
		if(!this.options.display_format)
		{
			this.options.display_format = this.attributes.display_format["default"];
		}

		// Get translations
		this.time_formats = {
			"d": this.options.short_labels ? this.egw().lang("m") : this.egw().lang("Days"),
			"h": this.options.short_labels ? this.egw().lang("h") : this.egw().lang("Hours"),
			"m": this.options.short_labels ? this.egw().lang("m") : this.egw().lang("Minutes")
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
		}
		else if (this.time_formats[this.options.display_format])
		{
			this.format = $j("<span>"+this.time_formats[this.options.display_format]+"</span>").appendTo(this.node);
		}
		else
		{
			this.format = $j("<span>"+this.time_formats["m"]+"</span>").appendTo(this.node);
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
			if(this.format && this.format.children().length > 1) {
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
		var format = this.egw().preference('number_format');
		if (typeof _value == 'string' && format && (sep = format[0]) && sep != '.')
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
			return this.options.empty_not_0 ? null : 0;
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
 * @augments et2_date_duration
 */
var et2_date_duration_ro = et2_date_duration.extend([et2_IDetachedDOM],
{
	/**
	 * @memberOf et2_date_duration_ro
	 */
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
		if(typeof _values.value !== 'undefined')
		{ 
			_values.value = parseFloat(_values.value);
		}
		if(_values.value)
		{
			var display = this._convert_to_display(_values.value);
			_nodes[0].appendChild(document.createTextNode(display.value));
			_nodes[1].appendChild(document.createTextNode(display.unit));
		}
	}

});
et2_register_widget(et2_date_duration_ro, ["date-duration_ro"]);

/**
 * et2_date_ro is the readonly implementation of some date widget.
 * @augments et2_valueWidget
 */
var et2_date_ro = et2_valueWidget.extend([et2_IDetachedDOM], 
{
	/**
	 * Ignore all more advanced attributes.
	 */
	attributes: {
		"value": {
			"type": "integer"
		},
		"type": {
			"ignore": false
		},
		"data_format": {
			"ignore": true,
			"description": "Format data is in.  This is not used client-side because it's always a timestamp client side."
		}
	},

	legacyOptions: ["data_format"],

	/**
	 * Internal container for working easily with dates
	 */
	date: new Date(),

	/**
	 * Constructor
	 * 
	 * @memberOf et2_date_ro
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement(this._type == "date-since" || this._type == "date-time_today" ? "span" : "time"))
			.addClass("et2_date_ro et2_label");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		if(typeof _value == 'undefined') _value = 0;

		this.value = _value;

		if(_value == 0 || _value == null)
		{
			this.span.attr("datetime", "").text("");
			return;
		}

		if(typeof _value == 'string' && isNaN(_value))
		{
			var text = new Date(_value);
			// Handle timezone offset - times are already in user time, but parse does UTC
			var localOffset = text.getTimezoneOffset() * 60000;
			// JS dates use milliseconds
			this.date.setTime(text.valueOf()+localOffset);
		}
		else
		{
			// JS dates use milliseconds
			this.date.setTime(parseInt(_value)*1000);
		}
		var display = this.date.toString();

		switch(this._type) {
			case "date-time_today":
				// Today - just the time
				if(this.date.toDateString() == new Date().toDateString())
				{
					display = date(this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a', this.date);
				}
				// Before today - just the date
				else
				{
					display = date(this.egw().preference('dateformat'), this.date);
				}
				break;
			case "date":
				display = date(this.egw().preference('dateformat'), this.date);
				break;
			case "date-timeonly":
				display = date(this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a', this.date);
				break;
			case "date-time":
				display = date(this.egw().preference('dateformat') + " " +  
					(this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a'), this.date);
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
						display = Math.round(diff/unit_s,1)+' '+this.egw().lang(unit2label[unit]);
						break;
					}
				}
				break;
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
et2_register_widget(et2_date_ro, ["date_ro", "date-time_ro", "date-since", "date-time_today"]);

/**
 * @augments et2_date_ro
 */
var et2_date_timeonly_ro = et2_date_ro.extend(
{
	attributes: {
		"value": {
			"type": "string"
		}
	},
	/**
	 * Construtor
	 * 
	 * @param _value
	 * @memberOf et2_date_timeonly_ro
	 */
	set_value: function(_value) {
		if(this.egw().preference("timeformat") == "12" && _value.indexOf(":") > 0) {
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
