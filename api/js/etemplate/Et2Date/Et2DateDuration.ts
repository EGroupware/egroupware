/**
 * EGroupware eTemplate2 - Duration date widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, LitElement} from "@lion/core";
import {Unparseable} from "@lion/form-core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

export interface formatOptions
{
	select_unit : string;
	display_format : string;
	data_format : string;
	hours_per_day : number;
	empty_not_0 : boolean;
	number_format? : string;
};

/**
 * Format a number as a time duration
 *
 * @param {number} value
 * @param {object} options
 * 	set 'timeFormat': "12" to specify a particular format
 * @returns {value: string, unit: string}
 */
export function formatDuration(value : number | string, options : formatOptions) : { value : string, unit : string }
{
	// Handle empty / 0 / no value
	if(value === "" || value == "0" || !value)
	{
		return {value: options.empty_not_0 ? "0" : "", unit: ""};
	}
	// Make sure it's a number now
	value = typeof value == "string" ? parseInt(value) : value;

	if(!options.select_unit)
	{
		let vals = [];
		for(let i = 0; i < options.display_format.length; ++i)
		{
			let unit = options.display_format[i];
			let val = this._unit_from_value(value, unit, i === 0);
			if(unit === 's' || unit === 'm' || unit === 'h' && options.display_format[0] === 'd')
			{
				vals.push(sprintf('%02d', val));
			}
			else
			{
				vals.push(val);
			}
		}
		return {value: vals.join(':'), unit: ''};
	}

	// Put value into minutes for further processing
	switch(options.data_format)
	{
		case 'd':
			value *= options.hours_per_day;
		// fall-through
		case 'h':
			value *= 60;
			break;
		case 's':
			value /= 60.0;
			break;
	}

	// Figure out the best unit for display
	let _unit = options.display_format == "d" ? "d" : "h";
	if(options.display_format.indexOf('m') > -1 && value < 60)
	{
		_unit = 'm';
	}
	else if(options.display_format.indexOf('d') > -1 && value >= (60 * options.hours_per_day))
	{
		_unit = 'd';
	}
	let out_value = "" + (_unit == 'm' ? value : (Math.round((value / 60.0 / (_unit == 'd' ? options.hours_per_day : 1)) * 100) / 100));

	if(out_value === '')
	{
		_unit = '';
	}

	// use decimal separator from user prefs
	var format = options.number_format || this.egw().preference('number_format');
	var sep = format ? format[0] : '.';
	if(format && sep && sep != '.')
	{
		out_value = out_value.replace('.', sep);
	}

	return {value: out_value, unit: _unit};
}

/**
 * Display a time duration (eg: 3 days, 6 hours)
 *
 * If not specified, the time is in assumed to be minutes and will be displayed with a calculated unit
 * but this can be specified with the properties.
 */
export class Et2DateDuration extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host([focused]) ::slotted(button), :host(:hover) ::slotted(button) {
				display: inline-block;
			}
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			/**
			 * Data format
			 *
			 * Units to read/store the data.  'd' = days (float), 'h' = hours (float), 'm' = minutes (int), 's' = seconds (int).
			 */
			data_format: {
				type: String
			},
			/**
			 * Display format
			 *
			 * Permitted units for displaying the data.
			 * 'd' = days, 'h' = hours, 'm' = minutes, 's' = seconds.  Use combinations to give a choice.
			 * Default is 'dh' = days or hours with selectbox.
			 */
			display_format: {
				type: String
			},

			/**
			 * Select unit or input per unit
			 *
			 * Display a unit-selection for multiple units, or an input field per unit.
			 * Default is true
			 */
			select_unit: {
				type: Boolean
			},

			/**
			 * Percent allowed
			 *
			 * Allows to enter a percentage instead of numbers
			 */
			percent_allowed: {
				type: Boolean
			},

			/**
			 * Hours per day
			 *
			 * Number of hours in a day, used for converting between hours and (working) days.
			 * Default 8
			 */
			hours_per_day: {type: Number},

			/**
			 * 0 or empty
			 *
			 * Should the widget differ between 0 and empty, which get then returned as NULL
			 * Default false, empty is considered as 0
			 */
			empty_not_0: {type: Boolean},

			/**
			 * Short labels
			 *
			 * use d/h/m instead of day/hour/minute
			 */
			short_labels: {
				type: Boolean
			},

			/**
			 * Step limit
			 *
			 * Works with the min and max attributes to limit the increments at which a numeric or date-time value can be set.
			 */
			step: {
				type: String
			}
		}
	}

	constructor()
	{
		super();

		// Property defaults
		this.data_format = "m";
		this.display_format = "dhm";
		this.select_unit = true;
		this.percent_allowed = false;
		this.hours_per_day = 8;
		this.empty_not_0 = false;
		this.short_labels = false;

		this.formatter = formatDuration;
	}

	getValue()
	{
		if(this.readOnly)
		{
			return null;
		}

		// The supplied value was not understandable, return null
		if(this.modelValue instanceof Unparseable || !this.modelValue)
		{
			return null;
		}

		return this.modelValue.toJSON();
	}

	render()
	{
		// TODO
	}
}

// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-date-duration", Et2DateDuration);
