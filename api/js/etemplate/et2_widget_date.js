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

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	/vendor/bower-asset/jquery-ui/jquery-ui.js;
	lib/date;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "date" XET-Tag
 *
 * Dates are passed to the server in ISO8601 format ("Y-m-d\TH:i:sP"), and data_format is
 * handled server-side.
 *
 * Widgets uses jQuery date- and time-picker for desktop browsers and
 * HTML5 input fields for mobile devices to get their native UI for date/time entry.
 *
 * @augments et2_inputWidget
 */
var et2_date = (function(){ "use strict"; return et2_inputWidget.extend(
{
	attributes: {
		"value": {
			"type": "any"
		},
		"type": {
			"ignore": false
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": "",
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
		"data_format": {
			"ignore": true,
			"description": "Date/Time format. Can be set as an options to date widget",
			"default": ''
		},
		year_range: {
			name: "Year range",
			type: "string",
			default: "c-10:c+10",
			description: "The range of years displayed in the year drop-down: either relative to today's year (\"-nn:+nn\"), relative to the currently selected year (\"c-nn:c+nn\"), absolute (\"nnnn:nnnn\"), or combinations of these formats (\"nnnn:-nn\"). Note that this option only affects what appears in the drop-down, to restrict which dates may be selected use the min and/or max options."
		},
		min: {
			"name": "Minimum",
			"type": "any",
			"default": et2_no_init,
			"description": 'Minimum allowed date.  Multiple types supported:\
Date: A date object containing the minimum date.\
Number: A number of days from today. For example 2 represents two days from today and -1 represents yesterday.\
String: A string in the user\'s date format, or a relative date. Relative dates must contain value and period pairs; valid periods are "y" for years, "m" for months, "w" for weeks, and "d" for days. For example, "+1m +7d" represents one month and seven days from today.'
		},
		max: {
			"name": "Maximum",
			"type": "any",
			"default": et2_no_init,
			"description": 'Maximum allowed date.   Multiple types supported:\
Date: A date object containing the maximum date.\
Number: A number of days from today. For example 2 represents two days from today and -1 represents yesterday.\
String: A string in the user\'s date format, or a relative date. Relative dates must contain value and period pairs; valid periods are "y" for years, "m" for months, "w" for weeks, and "d" for days. For example, "+1m +7d" represents one month and seven days from today.'
		},
		inline: {
			"name": "Inline",
			"type": "boolean",
			"default": false,
			"description": "Instead of an input field with a popup calendar, the calendar is displayed inline, with no input field"
		}
	},

	legacyOptions: ["data_format"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_date
	 */
	init: function()
	{
		this._super.apply(this, arguments);

		this.date = new Date();
		this.date.setUTCHours(0);
		this.date.setMinutes(0);
		this.date.setSeconds(0);
		this.input = null;

		this.createInputWidget();
	},

	createInputWidget: function()
	{
		this.span = jQuery(document.createElement(this.options.inline ? 'div' : "span")).addClass("et2_date");

		this.input_date = jQuery(document.createElement(this.options.inline ? "div" : "input"));
		if (this.options.blur) this.input_date.attr('placeholder', this.egw().lang(this.options.blur));
		this.input_date.addClass("et2_date").attr("type", "text")
			.attr("size", 7)	// strlen("10:00pm")=7
			.appendTo(this.span);

		this.setDOMNode(this.span[0]);

		// inline calendar is not existing in html5, so allways use datepicker instead
		this.is_mobile = egwIsMobile() && !this.options.inline;

		if (this.is_mobile)
		{
			this.dateFormat = 'yy-mm-dd';
			this.timeFormat = 'HH:mm';
			switch(this._type)
			{
				case 'date':
					this.input_date.attr('type', 'date');
					break;
				case 'date-time':
					this.input_date.attr('type', 'datetime-local');
					break;
				case 'date-timeonly':
					this.input_date.addClass("et2_time");
					this.input_date.attr('type', 'time');
					break;
			}
		}
		else
		{
			this.dateFormat = this.egw().dateTimeFormat(this.egw().preference("dateformat"));
			this.timeFormat = this.egw().preference("timeformat") == 12 ? "h:mmtt" : "HH:mm";
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

			// Avoid collision of datepicker dialog with input field
			var widget = this;
			this.input_date.datepicker('option', 'beforeShow', function(input, inst){
				var cal = inst.dpDiv;
				setTimeout(function () {
					var $input = jQuery(input);
					var inputOffset = $input.offset();
					// position the datepicker in freespace zone
					// avoid datepicker calendar collision with input field
					if (cal.height() + inputOffset.top > window.innerHeight)
					{
						cal.position({
							my: "left center",
							at: 'right bottom',
							collision: 'flip fit',
							of: input
						});
					}
					// Add tooltip to Today/Now button
					jQuery('[data-handler="today"]',cal).attr('title',
						widget._type == 'date' ? egw.lang('Today') : egw.lang('Now')
					);

				},0);
			})
			.datepicker('option','onClose', function(dateText, inst) {
				// Lose focus, avoids an issue with focus
				// not allowing datepicker to re-open
				inst.input.blur();
			});
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

	/**
	 * Dynamic disable or enable datepicker
	 *
	 * @param {boolean} _ro
	 */
	set_readonly: function(_ro)
	{
		if (this.input_date && !this.input_date.attr('disabled') != !_ro)
		{
			this.input_date.attr('disabled', !!_ro)
				.datepicker('option', 'disabled', !!_ro);
		}
	},

	/**
	 * Set (full) year of current date
	 *
	 * @param {number} _value 4-digit year
	 */
	set_year: function(_value)
	{
		this.date.setUTCFullYear(_value);
		this.set_value(this.date);
	},
	/**
	 * Set month (1..12) of current date
	 *
	 * @param {number} _value 1..12
	 */
	set_month: function(_value)
	{
		this.date.setUTCMonth(_value-1);
		this.set_value(this.date);
	},
	/**
	 * Set day of current date
	 *
	 * @param {number} _value 1..31
	 */
	set_date: function(_value)
	{
		this.date.setUTCDate(_value);
		this.set_value(this.date);
	},
	/**
	 * Set hour (0..23) of current date
	 *
	 * @param {number} _value 0..23
	 */
	set_hours: function(_value)
	{
		this.date.setUTCHours(_value);
		this.set_value(this.date);
	},
	/**
	 * Set minute (0..59) of current date
	 *
	 * @param {number} _value 0..59
	 */
	set_minutes: function(_value)
	{
		this.date.setUTCMinutes(_value);
		this.set_value(this.date);
	},
	/**
	 * Get (full) year of current date
	 *
	 * @return {number|null} 4-digit year or null for empty
	 */
	get_year: function()
	{
		return this.input_date.val() == "" ? null : this.date.getUTCFullYear();
	},
	/**
	 * Get month (1..12) of current date
	 *
	 * @return {number|null} 1..12 or null for empty
	 */
	get_month: function()
	{
		return this.input_date.val() == "" ? null : this.date.getUTCMonth()+1;
	},
	/**
	 * Get day of current date
	 *
	 * @return {number|null} 1..31 or null for empty
	 */
	get_date: function()
	{
		return this.input_date.val() == "" ? null : this.date.getUTCDate();
	},
	/**
	 * Get hour (0..23) of current date
	 *
	 * @return {number|null} 0..23 or null for empty
	 */
	get_hours: function()
	{
		return this.input_date.val() == "" ? null : this.date.getUTCHours();
	},
	/**
	 * Get minute (0..59) of current date
	 *
	 * @return {number|null} 0..59 or null for empty
	 */
	get_minutes: function()
	{
		return this.input_date.val() == "" ? null : this.date.getUTCMinutes();
	},
	/**
	 * Get timestamp
	 *
	 * You can use set_value to set a timestamp.
	 *
	 * @return {number|null} timestamp (seconds since 1970-01-01)
	 */
	get_time: function()
	{
		return this.input_date.val() == "" ? null : this.date.getTime();
	},

	/**
	 * The range of years displayed in the year drop-down: either relative
	 * to today's year ("-nn:+nn"), relative to the currently selected year
	 * ("c-nn:c+nn"), absolute ("nnnn:nnnn"), or combinations of these formats
	 * ("nnnn:-nn"). Note that this option only affects what appears in the
	 * drop-down, to restrict which dates may be selected use the min_date
	 * and/or max_date options.
	 * @param {string} _value
	 */
	set_year_range: function(_value)
	{
		if(this.input_date && this._type == 'date' && !this.is_mobile)
		{
			this.input_date.datepicker('option','yearRange',_value);
		}
		this.options.year_range = _value;
	},

	/**
	 * Set the minimum allowed date
	 *
	 * The minimum selectable date. When set to null, there is no minimum.
	 *	Multiple types supported:
	 *	Date: A date object containing the minimum date.
	 *	Number: A number of days from today. For example 2 represents two days
	 *		from today and -1 represents yesterday.
	 *	String: A string in the format defined by the dateFormat option, or a
	 *		relative date. Relative dates must contain value and period pairs;
	 *		valid periods are "y" for years, "m" for months, "w" for weeks, and
	 *		"d" for days. For example, "+1m +7d" represents one month and seven
	 *		days from today.
	 * @param {Date|Number|String} _value
	 */
	set_min: function(_value)
	{
		if(this.input_date)
		{
			if (this.is_mobile)
			{
				this.input_date.attr('min', this._relativeDate(_value));
			}
			else
			{
				// Check for full timestamp
				if(typeof _value == 'string' && _value.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})(?:\.\d{3})?(?:Z|[+-](\d{2})\:(\d{2}))/))
				{
					_value = new Date(_value);
					// Add timezone offset back in, or formatDate will lose those hours
					var formatDate = new Date(_value.valueOf() + this.date.getTimezoneOffset() * 60 * 1000);
					if(this._type == 'date')
					{
						_value = jQuery.datepicker.formatDate(this.dateFormat, formatDate);
					}
				}
				this.input_date.datepicker('option','minDate',_value);
			}
		}
		this.options.min = _value;
	},

	/**
	 * Convert non html5 min or max attributes described above to timestamps
	 *
	 * @param {string|Date} _value
	 */
	_relativeDate: function(_value)
	{
		if (typeof _value == 'string' && _value.match(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/)) return _value;

		return jQuery.datepicker._determineDate(jQuery.datepicker, _value, this.date).toJSON();
	},

	/**
	 * Set the maximum allowed date
	 *
	 * The maximum selectable date. When set to null, there is no maximum.
	 *	Multiple types supported:
	 *	Date: A date object containing the maximum date.
	 *	Number: A number of days from today. For example 2 represents two days
	 *		from today and -1 represents yesterday.
	 *	String: A string in the format defined by the dateFormat option, or a
	 *		relative date. Relative dates must contain value and period pairs;
	 *		valid periods are "y" for years, "m" for months, "w" for weeks, and
	 *		"d" for days. For example, "+1m +7d" represents one month and seven
	 *		days from today.
	 * @param {Date|Number|String} _value
	 */
	set_max: function(_value)
	{
		if(this.input_date)
		{
			if (this.is_mobile)
			{
				this.input_date.attr('max', this._relativeDate(_value));
			}
			else
			{
				// Check for full timestamp
				if(typeof _value == 'string' && _value.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})(?:\.\d{3})?(?:Z|[+-](\d{2})\:(\d{2}))/))
				{
					_value = new Date(_value);
					// Add timezone offset back in, or formatDate will lose those hours
					var formatDate = new Date(_value.valueOf() + this.date.getTimezoneOffset() * 60 * 1000);
					if(this._type == 'date')
					{
						_value = jQuery.datepicker.formatDate(this.dateFormat, formatDate);
					}
				}
				this.input_date.datepicker('option','maxDate',_value);
			}
		}
		this.options.max = _value;
	},

	/**
	 * Setting date
	 *
	 * @param {string|number|Date} _value supported are the following formats:
	 * - Date object with usertime as UTC value
	 * - string like Date.toJSON()
	 * - string or number with timestamp in usertime like server-side uses it
	 * - string starting with + or - to add/substract given number of seconds from current value, "+600" to add 10 minutes
	 */
	set_value: function(_value)
	{
		var old_value = this._oldValue;
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

		// timestamp in usertime, convert to 'Y-m-d\\TH:i:s\\Z', as we do on server-side with equivalent of PHP date()
		if (typeof _value == 'number' || typeof _value == 'string' && !isNaN(_value) && _value[0] != '+' && _value[0] != '-')
		{
			_value = date('Y-m-d\\TH:i:s\\Z', _value);
		}
		// Check for full timestamp
		if(typeof _value == 'string' && _value.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})(?:\.\d{3})?(?:Z|[+-](\d{2})\:(\d{2})|)$/))
		{
			_value = new Date(_value);
		}
		// Handle just time as a string in the form H:i
		if(typeof _value == 'string' && isNaN(_value))
		{
			try {
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
						var parsed = jQuery.datepicker.parseTime(this.timeFormat, _value);
						if (!parsed)	// parseTime returns false
						{
							this.set_validation_error(this.egw().lang("'%1' has an invalid format !!!",_value));
							return;
						}
						this.set_validation_error(false);
						// this.date is on current date, changing it in get_value() to 1970-01-01, gives a time-difference, if we are currently on DST
						this.date.setDate(1);
						this.date.setMonth(0);
						this.date.setFullYear(1970);
						// Avoid javascript timezone offset, hour is in 'user time'
						this.date.setUTCHours(parsed.hour);
						this.date.setMinutes(parsed.minute);
						if(this.input_date.val() != _value)
						{
							this.input_date.val(_value);
							this.input_date.timepicker('setTime',_value);
							if (this._oldValue !== et2_no_init)
							{
								this.change(this.input_date);
							}
						}
						this._oldValue = this.date.toJSON();
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
						}
						else  // Parse other date widgets date with timepicker date/time format to date onject
						{
							var parsed = jQuery.datepicker.parseDateTime(this.dateFormat,
									this.timeFormat, _value.replace('T', ' '));
							if(!parsed)
							{
								this.set_validation_error(this.egw().lang("%1' han an invalid format !!!",_value));
								return;
							}
						}
						// Update local variable, but remove the timezone offset that
						// javascript adds when we parse
						if(parsed)
						{
							this.date = new Date(parsed.valueOf() - parsed.getTimezoneOffset() * 60000);
						}

						this.set_validation_error(false);
				}
			}
			// catch exception from unparsable date and display it empty instead
			catch(e) {
				return this.set_value(null);
			}
		} else if (typeof _value == 'object' && _value.date) {
			this.date = _value.date;
		} else if (typeof _value == 'object' && _value.valueOf) {
			this.date = _value;
		} else
		// string starting with + or - --> add/substract number of seconds from current value
		{
			this.date.setTime(this.date.getTime()+1000*parseInt(_value));
		}

		// Update input - popups do, but framework doesn't
		_value = '';
		// Add timezone offset back in, or formatDate will lose those hours
		var formatDate = new Date(this.date.valueOf() + this.date.getTimezoneOffset() * 60 * 1000);
		if(this._type != 'date-timeonly')
		{
			_value = jQuery.datepicker.formatDate(this.dateFormat, formatDate);
		}
		if(this._type != 'date')
		{
			if(this._type != 'date-timeonly') _value += this.is_mobile ? 'T' : ' ';

			_value += jQuery.datepicker.formatTime(this.timeFormat, {
				hour: formatDate.getHours(),
				minute: formatDate.getMinutes(),
				seconds: 0,
				timezone: 0
			});
		}
		if(this.options.inline )
		{
			this.input_date.datepicker("setDate",formatDate);
		}
		else
		{
			this.input_date.val(_value);
		}
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
		else if (this._type == 'date')
		{
			this.date.setUTCHours(0);
			this.date.setUTCMinutes(0);
		}

		// Convert to timestamp - no seconds
		this.date.setSeconds(0,0);
		return (this.date && typeof this.date.toJSON != 'undefined' && this.date.toJSON())?this.date.toJSON().replace(/\.\d{3}Z$/, 'Z'):this.date;
	}
});}).call(this);
et2_register_widget(et2_date, ["date", "date-time", "date-timeonly"]);

/**
 * @augments et2_date
 */
var et2_date_duration = (function(){ "use strict"; return et2_date.extend(
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
			"d": this.options.short_labels ? this.egw().lang("d") : this.egw().lang("Days"),
			"h": this.options.short_labels ? this.egw().lang("h") : this.egw().lang("Hours"),
			"m": this.options.short_labels ? this.egw().lang("m") : this.egw().lang("Minutes")
		},
		this.createInputWidget();
	},

	createInputWidget: function() {
		// Create nodes
		this.node = jQuery(document.createElement("span"))
						.addClass('et2_date_duration');
		this.duration = jQuery(document.createElement("input"))
						.addClass('et2_date_duration')
						.attr({type: 'number', size: 3});
		this.node.append(this.duration);

		if(this.options.display_format.length > 1)
		{
			this.format = jQuery(document.createElement("select"))
							.addClass('et2_date_duration');
			this.node.append(this.format);

			for(var i = 0; i < this.options.display_format.length; i++) {
				this.format.append("<option value='"+this.options.display_format[i]+"'>"+this.time_formats[this.options.display_format[i]]+"</option>");
			}
		}
		else if (this.time_formats[this.options.display_format])
		{
			this.format = jQuery("<span>"+this.time_formats[this.options.display_format]+"</span>").appendTo(this.node);
		}
		else
		{
			this.format = jQuery("<span>"+this.time_formats["m"]+"</span>").appendTo(this.node);
		}
	},
	attachToDOM: function() {
		var node = this.getInputNode();
		if (node)
		{
			jQuery(node).bind("change.et2_inputWidget", this, function(e) {
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
				jQuery("option[value='"+display.unit+"']",this.format).attr('selected','selected');
			}
			else
			{
				this.format.text(display.unit ? this.time_formats[display.unit] : '');
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
		switch(this.format && this.format.val() ? this.format.val() : this.options.display_format)
		{
			case 'd':
				value *= this.options.hours_per_day;
				// fall-through
			case 'h':
				value *= 60;
				break;
		}
		// Minutes should be an integer.  Floating point math.
		value = Math.round(value);

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
});}).call(this);
et2_register_widget(et2_date_duration, ["date-duration"]);

/**
 * @augments et2_date_duration
 */
var et2_date_duration_ro = (function(){ "use strict"; return et2_date_duration.extend([et2_IDetachedDOM],
{
	/**
	 * @memberOf et2_date_duration_ro
	 */
	createInputWidget: function() {
		this.node = jQuery(document.createElement("span"));
		this.duration = jQuery(document.createElement("span")).appendTo(this.node);
		this.format = jQuery(document.createElement("span")).appendTo(this.node);
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

});}).call(this);
et2_register_widget(et2_date_duration_ro, ["date-duration_ro"]);

/**
 * et2_date_ro is the readonly implementation of some date widget.
 * @augments et2_valueWidget
 */
var et2_date_ro = (function(){ "use strict"; return et2_valueWidget.extend([et2_IDetachedDOM],
{
	/**
	 * Ignore all more advanced attributes.
	 */
	attributes: {
		"value": {
			"type": "string"
		},
		"type": {
			"ignore": false
		},
		"data_format": {
			"ignore": true,
			"description": "Format data is in.  This is not used client-side because it's always a timestamp client side."
		},
		min: {ignore: true},
		max: {ignore: true},
		year_range: {ignore: true}
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
		this._labelContainer = jQuery(document.createElement("label"))
			.addClass("et2_label");
		this.value = "";
		this.span = jQuery(document.createElement(this._type == "date-since" || this._type == "date-time_today" ? "span" : "time"))
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

		if(typeof _value == 'string' && _value.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2})\:(\d{2})\:(\d{2})(?:\.\d{3})?(?:Z|[+-](\d{2})\:(\d{2}))/))
		{
			this.date = new Date(_value);
			this.date = new Date(this.date.valueOf() + (this.date.getTimezoneOffset()*60*1000));
		}
		else if(typeof _value == 'string' && isNaN(_value))
		{
			try {
				// parseDateTime to handle string PHP: DateTime local date/time format
				var parsed = (typeof jQuery.datepicker.parseDateTime("yy-mm-dd","hh:mm:ss", _value) !='undefined')?
							jQuery.datepicker.parseDateTime("yy-mm-dd","hh:mm:ss", _value):
							jQuery.datepicker.parseDateTime(this.egw().preference('dateformat'),this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a', _value);
			}
			// display unparsable dates as empty
			catch(e) {
				this.span.attr("datetime", "").text("");
				return;
			}
			var text = new Date(parsed);

			// Update local variable, but remove the timezone offset that javascript adds
			if(parsed)
			{
				this.date = new Date(text.valueOf() - (text.getTimezoneOffset()*60*1000));
			}

			// JS dates use milliseconds
			this.date.setTime(text.valueOf());
		}
		else
		{
			// _value is timestamp in usertime, ready to be used with date() function identical to PHP date()
			this.date = _value;
		}
		var display = this.date.toString();

		switch(this._type) {
			case "time_or_date":
			case "date-time_today":
				// Today - just the time
				if(date('Y-m-d', this.date) == date('Y-m-d'))
				{
					display = date(this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a', this.date);
				}
				else if (this._type === "time_or_date")
				{
					display = date(this.egw().preference('dateformat'), this.date);
				}
				// Before today - date and time
				else
				{
					display = date(this.egw().preference('dateformat') + " " +
						(this.egw().preference('timeformat') == '24' ? 'H:i' : 'g:i a'), this.date);
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

		// add class if label is empty
		this._labelContainer.toggleClass('et2_label_empty', !label || !parts[0]);
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
});}).call(this);
et2_register_widget(et2_date_ro, ["date_ro", "date-time_ro", "date-since", "date-time_today", "time_or_date", "date-timeonly_ro"]);


/**
 * Widget for selecting a date range
 *
 * @augments et2_inputWidget
 */
var et2_date_range = (function(){ "use strict"; return et2_inputWidget.extend({
	attributes: {
		value: {
			"type": "any",
			"description": "An object with keys 'from' and 'to' for absolute ranges, or a relative range string"
		},
		relative: {
			name: 'Relative',
			type: 'boolean',
			description: 'Is the date range relative (this week) or absolute (2016-02-15 - 2016-02-21).  This will affect the value returned.'
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_number
	 */
	init: function init() {
		this._super.apply(this, arguments);

		this.div = jQuery(document.createElement('div'))
			.attr({	class:'et2_date_range'});

		this.from = null;
		this.to = null;
		this.select = null;

		// Set domid
		this.set_id(this.id);

		this.setDOMNode(this.div[0]);
		this._createWidget();

		this.set_relative(this.options.relative || false);
	},

	_createWidget: function createInputWidget() {
		var widget = this;

		this.from = et2_createWidget('date',{
			id: this.id+'[from]',
			blur: egw.lang('From'),
			onchange: function() { widget.to.set_min(widget.from.getValue()); }
		},this);
		this.to = et2_createWidget('date',{
			id: this.id+'[to]',
			blur: egw.lang('To'),
			onchange: function() {widget.from.set_max(widget.to.getValue()); }
		},this);
		this.select = et2_createWidget('select',{
			id: this.id+'[relative]',
			select_options: et2_date_range.relative_dates,
			empty_label: this.options.blur || 'All'
		},this);
		this.select.loadingFinished();
	},

	/**
	 * Function which allows iterating over the complete widget tree.
	 * Overridden here to avoid problems with children when getting value
	 *
	 * @param _callback is the function which should be called for each widget
	 * @param _context is the context in which the function should be executed
	 * @param _type is an optional parameter which specifies a class/interface
	 * 	the elements have to be instanceOf.
	 */
	iterateOver: function(_callback, _context, _type) {
		if (typeof _type == "undefined")
		{
			_type = et2_widget;
		}

		if (this.isInTree() && this.instanceOf(_type))
		{
			_callback.call(_context, this);
		}
	},

	/**
	 * Toggles relative or absolute dates
	 *
	 * @param {boolean} _value
	 */
	set_relative: function set_relative(_value)
	{
		this.options.relative = _value;
		if(this.options.relative)
		{
			jQuery(this.from.getDOMNode()).hide();
			jQuery(this.to.getDOMNode()).hide();
		}
		else
		{
			jQuery(this.select.getDOMNode()).hide();
		}
	},

	set_value: function set_value(value)
	{
		if(!value || typeof value == 'null')
		{
			this.select.set_value('');
			this.from.set_value(null);
			this.to.set_value(null);
		}

		// Relative
		if(value && typeof value === 'string')
		{
			this._set_relative_value(value);

		}
		else if(value && typeof value.from === 'undefined' && value[0])
		{
			value = {
				from: value[0],
				to: value[1] || new Date().valueOf()/1000
			};
		}
		else if (value && value.from && value.to)
		{
			this.from.set_value(value.from);
			this.to.set_value(value.to);
		}
	},

	getValue: function getValue()
	{
		return this.options.relative ?
			this.select.getValue() :
			{ from: this.from.getValue(), to: this.to.getValue() };
	},

	_set_relative_value: function(_value)
	{
		if(this.options.relative)
		{
			jQuery(this.select.getDOMNode()).show();
		}
		// Show description
		this.select.set_value(_value);

		var tempDate = new Date();
		var today = new Date(tempDate.getFullYear(), tempDate.getMonth(), tempDate.getDate(),0,-tempDate.getTimezoneOffset(),0);

		// Use strings to avoid references
		this.from.set_value(today.toJSON());
		this.to.set_value(today.toJSON());

		var relative = null;
		for(var index in et2_date_range.relative_dates)
		{
			if(et2_date_range.relative_dates[index].value === _value)
			{
				relative = et2_date_range.relative_dates[index];
				break;
			}
		}
		if(relative)
		{
			var dates = ["from","to"];
			var value = today.toJSON();
			for(var i = 0; i < dates.length; i++)
			{
				var date = dates[i];
				if(typeof relative[date] == "function")
				{
					value = relative[date](new Date(value));
				}
				else
				{
					value = this[date]._relativeDate(relative[date]);
				}
				this[date].set_value(value);
			}
		}
	}
});}).call(this);
et2_register_widget(et2_date_range, ["date-range"]);
// Static part of the date range class
jQuery.extend(et2_date_range,
{
	// Class Constants
	relative_dates: [
		// Start and end are relative offsets, see et2_date.set_min()
		// or Date objects
		{
			value: 'Today',
			label: 'Today',
			from: function(date) {return date;},
			to: function(date) {return date;}
		},
		{
			label: 'Yesterday',
			value: 'Yesterday',
			from: function(date) {
				date.setUTCDate(date.getUTCDate() - 1);
				return date;
			},
			to: ''
		},
		{
			label: 'This week',
			value: 'This week',
			from: function(date) {return egw.week_start(date);},
			to: function(date) {
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: 'Last week',
			value: 'Last week',
			from: function(date) {
				var d = egw.week_start(date);
				d.setUTCDate(d.getUTCDate() - 7);
				return d;
			},
			to: function(date) {
				date.setUTCDate(date.getUTCDate() + 6);
				return date;
			}
		},
		{
			label: 'This month',
			value: 'This month',
			from: function(date)
			{
				date.setUTCDate(1);
				return date;
			},
			to: function(date)
			{
				date.setUTCMonth(date.getUTCMonth()+1);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: 'Last month',
			value: 'Last month',
			from: function(date)
			{
				date.setUTCMonth(date.getUTCMonth() - 1);
				date.setUTCDate(1);
				return date;
			},
			to: function(date)
			{
				date.setUTCMonth(date.getUTCMonth()+1);
				date.setUTCDate(0);
				return date;
			}
		},
		{
			label: 'Last 3 months',
			value: 'Last 3 months',
			from: function(date)
			{
				date.setUTCMonth(date.getUTCMonth() - 2);
				date.setUTCDate(1);
				return date;
			},
			to: function(date)
			{
				date.setUTCMonth(date.getUTCMonth()+3);
				date.setUTCDate(0);
				return date;
			}
		},
		/*
		'This quarter'=> array(0,0,0,0,  0,0,0,0),      // Just a marker, needs special handling
		'Last quarter'=> array(0,-4,0,0, 0,-4,0,0),     // Just a marker
		*/
		{
			label: 'This year',
			value: 'This year',
			from: function(d) {
				d.setUTCMonth(0);
				d.setUTCDate(1);
				return d;
			},
			to: function(d) {
				d.setUTCMonth(11);
				d.setUTCDate(31);
				return d;
			}
		},
		{
			label: 'Last year',
			value: 'Last year',
			from: function(d) {
				d.setUTCMonth(0);
				d.setUTCDate(1);
				d.setUTCYear(d.getUTCYear() - 1);
				return d;
			},
			to: function(d) {
				d.setUTCMonth(11);
				d.setUTCDate(31);
				d.setUTCYear(d.getUTCYear() - 1);
				return d;
			}
		}
		/* Still needed?
		'2 years ago' => array(-2,0,0,0, -1,0,0,0),
		'3 years ago' => array(-3,0,0,0, -2,0,0,0),
		*/
	]
});
