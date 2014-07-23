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
			"ignore": false,
			"description": "Date/Time format. Can be set as an options to date widget",
			"default": ''
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
		this.input_date.addClass("et2_date").attr("type", "text")
			.attr("size", 7)	// strlen("10:00pm")=7
			.appendTo(this.span);

		this.setDOMNode(this.span[0]);

		// jQuery-UI date picker
		if(this._type != 'date-timeonly')
		{
			this.egw().calendar(this.input_date, this._type == "date-time");
		}
		else
		{
			this.input_date.addClass("et2_time");
			this.egw().time(this.input_date);
		}
		// Update internal value when changed
		var self = this;
		this.input_date.bind('change', function(e){
			self.set_value(this.value);
			return false;
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
		if(_value === null || _value === "" || _value === undefined ||
			// allow 0 as empty-value for date and date-time widgets, as that is used a lot eg. in InfoLog
			_value == 0 && (this._type == 'date-time' || this._type == 'date'))
		{
			if(this.input_date)
			{
				this.input_date.val("");
			}
			if(this._oldValue !== et2_no_init && old_value !== _value)
			{
				this.change(this.input_date);
			}
			this._oldValue = _value;
			return;
		}

		// Handle just time as a string in the form H:i
		if(typeof _value == 'string' && isNaN(_value))
		{
			// silently fix skiped minutes or times with just one digit, as parser is quite pedantic ;-)
			var fix_reg = new RegExp((this._type == "date-timeonly"?'^':' ')+'([0-9]+)(:[0-9]*)?( ?(a|p)m?)?$','i');
			var matches = _value.match(fix_reg);
			if (matches && (matches[1].length < 2 || matches[2] === undefined || matches[2].length < 3 ||
				matches[3] && matches[3] != 'am' && matches[3] != 'pm'))
			{
				if (matches[1].length < 2 && !matches[3]) matches[1] = '0'+matches[1];
				if (matches[2] === undefined) matches[2] = ':00';
				while (matches[2].length < 3) matches[2] = ':0'+matches[2].substr(1);
				_value = _value.replace(fix_reg, (this._type == "date-timeonly"?'':' ')+matches[1]+matches[2]+matches[3]);
				if (matches[4] !== undefined) matches[3] = matches[4].toLowerCase() == 'a' ? 'am' : 'pm';
			}
			switch(this._type)
			{
				case "date-timeonly":
					var parsed = jQuery.datepicker.parseTime(this.input_date.datepicker('option', 'timeFormat'), _value);
					if (!parsed)	// parseTime returns false
					{
						this.set_validation_error(this.egw().lang("'%1' has an invalid format !!!",_value));
						return;
					}
					this.set_validation_error(false);
					this.date.setHours(parsed.hour);
					this.date.setMinutes(parsed.minute);
					this.input_date.val(_value);
					if(old_value !== this.getValue())
					{
						this.input_date.timepicker('setTime',_value);
						if (this._oldValue !== et2_no_init)
						{
							this.change(this.input_date);
						}
					}
					this._oldValue = _value;
					return;
				default:
					// Parse customfields's date with storage data_format to date object
					// Or generally any date widgets with fixed date/time format
					if (this.id.match(/^#/g) && this.options.value == _value || (this.options.data_format && this.options.value == _value))
					{
						switch (this._type)
						{
							case 'date':
								var parsed = jQuery.datepicker.parseDate(this.egw().dateTimeFormat(this.options.data_format), _value);
								break;
							case 'date-time':
								var DTformat = this.options.data_format.split(' ');
								var parsed = jQuery.datepicker.parseDateTime(this.egw().dateTimeFormat(DTformat[0]),this.egw().dateTimeFormat(DTformat[1]), _value);
						}
						this.date = new Date(parsed);
					}
					else  // Parse other date widgets date with timepicker date/time format to date onject
					{
						var parsed = jQuery.datepicker.parseDateTime(this.input_date.datepicker('option', 'dateFormat'),
								this.input_date.datepicker('option', 'timeFormat'), _value);
						if(!parsed)
						{
							this.set_validation_error(this.egw().lang("%1' han an invalid format !!!",_value));
							return;
						}
						this.date = new Date(parsed);
					}


					this.set_validation_error(false);
			}
		} else if (typeof _value == 'object' && _value.date) {
			this.date = _value.date;
		} else if (typeof _value == 'object' && _value.valueOf) {
			this.date = _value;
		} else if (typeof _value == 'number' || !isNaN(_value)) {
			// Timestamp
			// JS dates use milliseconds
			this.date.setTime(parseInt(_value)*1000);
		}

		// Update input - popups do, but framework doesn't
		_value = '';
		if(this._type != 'date-timeonly')
		{
			_value = jQuery.datepicker.formatDate(this.input_date.datepicker("option","dateFormat"),this.date);
		}
		if(this._type != 'date')
		{
			if(this._type != 'date-timeonly') _value += ' ';

			_value += jQuery.datepicker.formatTime(this.input_date.datepicker("option","timeFormat"),{
				hour: this.date.getHours(),
				minute: this.date.getMinutes(),
				seconds: 0,
				timezone: 0
			});
		}
		this.input_date.val(_value);
		if(this._oldValue !== et2_no_init && old_value != this.getValue())
		{
			this.change(this.input_date);
		}
		this._oldValue = _value;
	},

	getValue: function() {
		if(this.input_date.val() == "")
		{
			// User blanked the box
			return null;
		}
		// date-timeonly returns just the seconds, without any date!
		if (this._type == 'date-timeonly')
		{
			this.date.setDate(1);
			this.date.setMonth(0);
			this.date.setFullYear(1970);
		}
		// Convert to timestamp - no seconds
		this.date.setSeconds(0,0);
		return Math.round(this.date.valueOf() / 1000);
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
			"default": "dhm",
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
		this.node = $j(document.createElement("span"))
						.addClass('et2_date_duration');
		this.duration = $j(document.createElement("input"))
						.addClass('et2_date_duration')
						.attr({type: 'number', size: 3});
		this.node.append(this.duration);

		if(this.options.display_format.length > 1)
		{
			this.format = $j(document.createElement("select"))
							.addClass('et2_date_duration');
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
	 *
	 * @param {string} _value id to set
	 */
	set_id: function(_value) {
		this.id = _value;

		var node = this.getDOMNode(this);
		if (node)
		{
			if (_value != "")
			{
				node.setAttribute("id", this.getInstanceManager().uniqueId+'_'+this.id);
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
		if (this.options.display_format.indexOf('m') > -1 && _value && _value < 60)
		{
			_unit = 'm';
		}
		else if (this.options.display_format.indexOf('d') > -1 && _value >= 60*this.options.hours_per_day)
		{
			_unit = 'd';
		}
		_value = this.options.empty_not_0 && _value === '' || !this.options.empty_not_0 && !_value ? '' :
			(_unit == 'm' ? parseInt( _value) : (Math.round((_value / 60.0 / (_unit == 'd' ? this.options.hours_per_day : 1))*100)/100));

		if(_value === '') _unit = '';

		// use decimal separator from user prefs
		var format = this.egw().preference('number_format');
		var sep = format ? format[0] : '.';
		if (typeof _value == 'string' && format && sep && sep != '.')
		{
			_value = _value.replace('.',sep);
		}

		return {value: _value, unit:_unit};
	},

	/**
	 * Change displayed value into storage value and return
	 */
	getValue: function() {
		var value = this.duration.val().replace(',', '.');
		if(value === '')
		{
			return this.options.empty_not_0 ? '' : 0;
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
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value");
	},

	/**
	 * Returns an array of DOM nodes. The (relativly) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 *
	 * @return {array}
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
		this._labelContainer = $j(document.createElement("label"))
			.addClass("et2_label");
		this.value = "";
		this.span = $j(document.createElement(this._type == "date-since" || this._type == "date-time_today" ? "span" : "time"))
			.addClass("et2_date_ro et2_label")
			.appendTo(this._labelContainer);

		this.setDOMNode(this._labelContainer[0]);
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
			// parseDateTime to handle string PHP: DateTime local date/time format
			var parsed = (typeof jQuery.datepicker.parseDateTime("yy-mm-dd","hh:mm:ss", _value) !='undefined')?
						jQuery.datepicker.parseDateTime("yy-mm-dd","hh:mm:ss", _value):
						jQuery.datepicker.parseDateTime(this.egw().preference('dateformat'),this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a', _value);

			var text = new Date(parsed);
			// JS dates use milliseconds
			this.date.setTime(text.valueOf());
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

	set_label: function(label)
	{
		// Remove current label
		this._labelContainer.contents()
			.filter(function(){ return this.nodeType == 3; }).remove();

		var parts = et2_csvSplit(label, 2, "%s");
		this._labelContainer.prepend(parts[0]);
		this._labelContainer.append(parts[1]);
		this.label = label;
	},

	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 *
	 * @param {array} _attrs array to add further attributes to
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("label", "value","class");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 *
	 * @return {array}
	 */
	getDetachedNodes: function() {
		return [this._labelContainer[0], this.span[0]];
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
		this._labelContainer = jQuery(_nodes[0]);
		this.span = jQuery(_nodes[1]);

		this.set_value(_values["value"]);
		if(_values["label"])
		{
			this.set_label(_values["label"]);
		}
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
