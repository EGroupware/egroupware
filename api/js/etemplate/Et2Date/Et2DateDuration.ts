/**
 * EGroupware eTemplate2 - Duration date widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {classMap, css, html, LitElement} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {sprintf} from "../../egw_action/egw_action_common";
import {dateStyles} from "./DateStyles";
import {FormControlMixin} from "@lion/form-core";
import shoelace from "../Styles/shoelace";

export interface formatOptions
{
	selectUnit : string;
	displayFormat : string;
	dataFormat : string;
	hoursPerDay : number;
	emptyNot0 : boolean;
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
		return {value: options.emptyNot0 ? "0" : "", unit: ""};
	}
	// Make sure it's a number now
	value = typeof value == "string" ? parseInt(value) : value;

	if(!options.selectUnit)
	{
		let vals = [];
		for(let i = 0; i < options.displayFormat.length; ++i)
		{
			let unit = options.displayFormat[i];
			let val = this._unit_from_value(value, unit, i === 0);
			if(unit === 's' || unit === 'm' || unit === 'h' && options.displayFormat[0] === 'd')
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
	switch(options.dataFormat)
	{
		case 'd':
			value *= options.hoursPerDay;
		// fall-through
		case 'h':
			value *= 60;
			break;
		case 's':	// round to full minutes, unless this would give 0, use 1 digit instead
			value = value < 30 ? Math.round(value / 6.0)/10.0 : Math.round(value / 60.0);
			break;
	}

	// Figure out the best unit for display
	let _unit = options.displayFormat == "d" ? "d" : "h";
	if(options.displayFormat.indexOf('m') > -1 && value < 60)
	{
		_unit = 'm';
	}
	else if(options.displayFormat.indexOf('d') > -1 && value >= (60 * options.hoursPerDay))
	{
		_unit = 'd';
	}
	let out_value = "" + (_unit == 'm' ? value : (Math.round((value / 60.0 / (_unit == 'd' ? options.hoursPerDay : 1)) * 100) / 100));

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
export class Et2DateDuration extends Et2InputWidget(FormControlMixin(LitElement))
{
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			...dateStyles,
			css`
			.form-field__group-two {
				max-width: 100%;
			}
			.input-group {
				display: flex;
				flex-direction: row;
				flex-wrap: nowrap;
				align-items: baseline;
			}
			.input-group__after {
				margin-inline-start: var(--sl-input-spacing-medium);
			}
			et2-select {
				color: var(--input-text-color);
				border-left: 1px solid var(--input-border-color);
				flex: 2 1 auto;
			}
			et2-select::part(control) {
				border-top-left-radius: 0px;
				border-bottom-left-radius: 0px;
			}
			et2-textbox {
				flex: 1 1 auto;
				max-width: 4.5em;
				margin-right: -2px;
			}
			et2-textbox::part(input) {
				padding-right: 0px;
			}
			et2-textbox:not(:last-child)::part(base) {
				border-right: none;
				border-top-right-radius: 0px;
				border-bottom-right-radius: 0px;
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
			dataFormat: {
				reflect: true,
				type: String
			},
			/**
			 * Display format
			 *
			 * Permitted units for displaying the data.
			 * 'd' = days, 'h' = hours, 'm' = minutes, 's' = seconds.  Use combinations to give a choice.
			 * Default is 'dh' = days or hours with selectbox.
			 */
			displayFormat: {
				reflect: true,
				type: String
			},

			/**
			 * Select unit or input per unit
			 *
			 * Display a unit-selection for multiple units, or an input field per unit.
			 * Default is true
			 */
			selectUnit: {
				reflect: true,
				type: Boolean
			},

			/**
			 * Percent allowed
			 *
			 * Allows to enter a percentage instead of numbers
			 */
			percentAllowed: {
				type: Boolean
			},

			/**
			 * Hours per day
			 *
			 * Number of hours in a day, used for converting between hours and (working) days.
			 * Default 8
			 */
			hoursPerDay: {
				reflect: true,
				type: Number
			},

			/**
			 * 0 or empty
			 *
			 * Should the widget differ between 0 and empty, which get then returned as NULL
			 * Default false, empty is considered as 0
			 */
			emptyNot0: {
				reflect: true,
				type: Boolean
			},

			/**
			 * Short labels
			 *
			 * use d/h/m instead of day/hour/minute
			 */
			shortLabels: {
				reflect: true,
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

	protected static time_formats = {d: "d", h: "h", m: "m", s: "s"};
	protected _display = {value: "", unit: ""};

	constructor()
	{
		super();

		// Property defaults
		this.dataFormat = "m";
		this.displayFormat = "dhm";
		this.selectUnit = true;
		this.percentAllowed = false;
		this.hoursPerDay = 8;
		this.emptyNot0 = false;
		this.shortLabels = false;

		this.formatter = formatDuration;
	}

	transformAttributes(attrs)
	{
		// Clean formats, but avoid things that need to be expanded like $cont[displayFormat]
		const check = new RegExp('[\$\@' + Object.keys(Et2DateDuration.time_formats).join('') + ']');
		for(let attr in ["displayFormat", "dataFormat"])
		{
			if(typeof attrs[attrs] === 'string' && !check.test(attrs[attr]))
			{
				console.warn("Invalid format for " + attr + "'" + attrs[attr] + "'", this);
				attrs[attr] = attrs[attr].replace(/[^dhms]/g, '');
			}
		}

		super.transformAttributes(attrs);
	}

	get value() : string
	{
		let value = 0;

		if(!this.selectUnit)
		{
			for(let i = this._durationNode.length; --i >= 0;)
			{
				value += parseInt(<string>this._durationNode[i].value) * this._unit2seconds(this._durationNode[i].name);
			}
			if(this.dataFormat !== 's')
			{
				value /= this._unit2seconds(this.dataFormat);
			}
			return "" + (this.dataFormat === 'm' ? Math.round(value) : value);
		}

		let val = this._durationNode.length ? this._durationNode[0].value : '';
		if(val === '' || isNaN(val))
		{
			return this.emptyNot0 ? '' : "0";
		}
		value = parseFloat(val);

		// Put value into minutes for further processing
		switch(this._formatNode && this._formatNode.value ? this._formatNode.value : this.displayFormat)
		{
			case 'd':
				value *= this.hoursPerDay;
			// fall-through
			case 'h':
				value *= 60;
				break;
		}
		// Minutes should be an integer.  Floating point math.
		if(this.dataFormat !== 's')
		{
			value = Math.round(value);
		}
		switch(this.dataFormat)
		{
			case 'd':
				value /= this.hoursPerDay;
			// fall-through
			case 'h':
				value /= 60.0;
				break;
			case 's':
				value = Math.round(value * 60.0);
				break;
		}

		return "" + value;
	}

	set value(_value)
	{
		this._display = this._convert_to_display(parseFloat(_value));
		this.requestUpdate();
	}

	/**
	 * Set the format for displaying the duration
	 *
	 * @param {string} value
	 */
	set displayFormat(value : string)
	{
		this.__displayFormat = "";
		if(!value)
		{
			// Don't allow an empty value, but don't throw a real error
			console.warn("Invalid displayFormat ", value, this);
			value = "dhm";
		}
		// Display format must be in decreasing size order (dhms) or the calculations
		// don't work out nicely
		for(let f of Object.keys(Et2DateDuration.time_formats))
		{
			if(value.indexOf(f) !== -1)
			{
				this.__displayFormat += f;
			}
		}
	}

	get displayFormat()
	{
		return this.__displayFormat;
	}


	render()
	{
		return html`
            <div part="form-control" class=${classMap({
                'form-control': true,
                'form-control--has-label': this.label.split("%")[0] || false
            })}>
                <div class="form-field__group-one form-control__label" part="form-control-label">
                    ${this._groupOneTemplate()}
                </div>
                <div class="form-field__group-two" part="form-control-input">${this._groupTwoTemplate()}</div>
            </div>
		`;
	}

	/**
	 * @return {TemplateResult}
	 * @protected
	 */
	// eslint-disable-next-line class-methods-use-this
	_inputGroupInputTemplate()
	{
		return html`
            <div class="input-group__input">
                <slot name="input">
                    ${this._inputTemplate()}
                    ${this._formatTemplate()}
                </slot>
            </div>
		`;
	}

	/**
	 * Converts the value in data format into value in display format.
	 *
	 * @param _value int/float Data in data format
	 *
	 * @return Object {value: Value in display format, unit: unit for display}
	 */
	protected _convert_to_display(_value)
	{
		if(!this.selectUnit)
		{
			let vals = [];
			for(let i = 0; i < this.displayFormat.length; ++i)
			{
				let unit = this.displayFormat[i];
				let val = this._unit_from_value(_value, unit, i === 0);
				if(unit === 's' || unit === 'm' || unit === 'h' && this.displayFormat[0] === 'd')
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
		if(_value)
		{
			// Put value into minutes for further processing
			switch(this.dataFormat)
			{
				case 'd':
					_value *= this.hoursPerDay;
				// fall-through
				case 'h':
					_value *= 60;
					break;
				case 's':
					_value /= 60.0;
					break;
			}
		}

		// Figure out best unit for display
		var _unit = this.displayFormat == "d" ? "d" : "h";
		if(this.displayFormat.indexOf('m') > -1 && _value && _value < 60)
		{
			_unit = 'm';
		}
		else if(this.displayFormat.indexOf('d') > -1 && _value >= 60 * this.hoursPerDay)
		{
			_unit = 'd';
		}
		_value = this.emptyNot0 && _value === '' || !this.emptyNot0 && !_value ? '' :
				 (_unit == 'm' ? parseInt(_value) : (Math.round((_value / 60.0 / (_unit == 'd' ? this.hoursPerDay : 1)) * 100) / 100));

		if(_value === '')
		{
			_unit = '';
		}

		// use decimal separator from user prefs
		var format = this.egw().preference('number_format');
		var sep = format ? format[0] : '.';
		if(typeof _value == 'string' && format && sep && sep != '.')
		{
			_value = _value.replace('.', sep);
		}

		return {value: _value, unit: _unit};
	}

	private _unit2seconds(_unit)
	{
		switch(_unit)
		{
			case 's':
				return 1;
			case 'm':
				return 60;
			case 'h':
				return 3600;
			case 'd':
				return 3600 * this.hoursPerDay;
		}
	}

	private _unit_from_value(_value, _unit, _highest)
	{
		_value *= this._unit2seconds(this.dataFormat);
		// get value for given _unit
		switch(_unit)
		{
			case 's':
				return _highest ? _value : _value % 60;
			case 'm':
				_value = Math.floor(_value / 60);
				return _highest ? _value : _value % 60;
			case 'h':
				_value = Math.floor(_value / 3600);
				return _highest ? _value : _value % this.hoursPerDay;
			case 'd':
				return Math.floor(_value / 3600 / this.hoursPerDay);
		}
	}

	/**
	 * Render the needed number inputs according to selectUnit & displayFormat properties
	 *
	 * @returns {any}
	 * @protected
	 */
	protected _inputTemplate()
	{
		let inputs = [];
		let value = typeof this._display.value === "number" ? this._display.value : (this._display.value.split(":") || []);
		for(let i = this.selectUnit ? 1 : this.displayFormat.length; i > 0; --i)
		{
			let input = {
				name: "",
				title: "",
				value: typeof value == "number" ? value : ((this.selectUnit ? value.pop() : value[i]) || ""),
				min: undefined,
				max: undefined
			};
			if(!this.selectUnit)
			{
				input.min = 0;
				input.name = this.displayFormat[this.displayFormat.length - i];
				// @ts-ignore - it doesn't like that it may have been an array
				input.value = <string>(value[this.displayFormat.length - i]);
				switch(this.displayFormat[this.displayFormat.length - i])
				{
					case 's':
						input.max = 60;
						input.title = this.egw().lang('Seconds');
						break;
					case 'm':
						input.max = 60;
						input.title = this.egw().lang('Minutes');
						break;
					case 'h':
						input.max = 24;
						input.title = this.egw().lang('Hours');
						break;
					case 'd':
						input.title = this.egw().lang('Days');
						break;
				}
			}
			inputs.push(input);
		}
		return html`${inputs.map((input : any) =>
                html`
                    <et2-textbox part="duration__${input.name}" type="number" class="duration__input" name=${input.name}
                                 min=${input.min} max=${input.max} title=${input.title}
                                 value=${input.value}></et2-textbox>`
        )}
		`;
	}

	/**
	 * Generate the format selector according to the selectUnit and displayFormat properties
	 *
	 * @returns {any}
	 * @protected
	 */
	protected _formatTemplate()
	{
		// If no formats or only 1 format, no need for a selector
		if(!this.displayFormat || this.displayFormat.length < 1 ||
			(!this.selectUnit && this.displayFormat.length > 1))
		{
			return html``;
		}
		// Get translations
		this.time_formats = this.time_formats || {
			d: this.shortLabels ? this.egw().lang("d") : this.egw().lang("Days"),
			h: this.shortLabels ? this.egw().lang("h") : this.egw().lang("Hours"),
			m: this.shortLabels ? this.egw().lang("m") : this.egw().lang("Minutes"),
			s: this.shortLabels ? this.egw().lang("s") : this.egw().lang("Seconds")
		};
		// It would be nice to use an et2-select here, but something goes weird with the styling
		return html`
            <et2-select value="${this._display.unit || this.displayFormat[0]}">
                ${[...this.displayFormat].map((format : string) =>
                        html`
                            <sl-menu-item value=${format} ?checked=${this._display.unit === format}>
                                ${this.time_formats[format]}
                            </sl-menu-item>`
                )}
            </et2-select>
		`;
	}

	/**
	 * @returns {HTMLInputElement}
	 */
	get _durationNode() : HTMLInputElement[]
	{
		return this.shadowRoot ? this.shadowRoot.querySelectorAll("et2-textbox") || [] : [];
	}


	/**
	 * @returns {HTMLSelectElement}
	 */
	get _formatNode() : HTMLSelectElement
	{
		return this.shadowRoot ? this.shadowRoot.querySelector("et2-select") : null;
	}
}

// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-date-duration", Et2DateDuration);