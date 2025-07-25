/**
 * EGroupware eTemplate2 - Duration date widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, LitElement, nothing} from "lit";
import {classMap} from "lit/directives/class-map.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {sprintf} from "../../egw_action/egw_action_common";
import {dateStyles} from "./DateStyles";
import shoelace from "../Styles/shoelace";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {live} from "lit/directives/live.js";

export interface formatOptions
{
	selectUnit : string;
	displayFormat : string;
	dataFormat : string;
	hoursPerDay : number;
	emptyNot0 : boolean;
	number_format? : string;
}

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
	if((value === "" || value == "0" || !value) && !options.emptyNot0)
	{
		return {value: "", unit: ""};
	}
	// Make sure it's a number now
	value = typeof value == "string" ? parseInt(value) : value;

	if(!options.selectUnit)
	{
		let vals = [];
		for(let i = 0; i < options.displayFormat.length; ++i)
		{
			let unit = options.displayFormat[i];
			let val = this._unit_from_value(value, unit, i === 0, options);
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
@customElement("et2-date-duration")
export class Et2DateDuration extends Et2InputWidget(LitElement)
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

				.form-control-input {
					display: flex;
					flex-direction: row;
					flex-wrap: nowrap;
					align-items: baseline;
				}

				.input-group__after {
					display: contents;
					margin-inline-start: var(--sl-input-spacing-medium);
				}

				sl-select {
					color: var(--input-text-color);
					flex: 2 1 auto;
					min-width: min-content;
					width: 8em;

					&::part(combobox) {
						border-left: 1px solid var(--input-border-color);
						border-top-left-radius: 0px;
						border-bottom-left-radius: 0px;
					}
				}

				sl-select::part(control) {
					border-top-left-radius: 0px;
					border-bottom-left-radius: 0px;
				}

				.duration__input {
					flex: 1 1 auto;
					width: min-content;
					min-width: 5em;
					/* This is the same as max-width of the number field */
					max-width: 7em;
					margin-right: -2px;
				}


				.duration__input:not(:first-child)::part(base) {
					border-top-left-radius: 0px;
					border-bottom-left-radius: 0px;
				}

				.duration__input:not(:last-child)::part(base) {
					border-right: none;
					border-top-right-radius: 0px;
					border-bottom-right-radius: 0px;
				}
			`,
		];
	}

	/**
	 * Data format
	 *
	 * Units to read/store the data.  'd' = days (float), 'h' = hours (float), 'm' = minutes (int), 's' = seconds (int).
	 */
	@property({type: String, reflect: true})
	dataFormat : "d" | "h" | "m" | "s" = "m";
	/**
	 * Display format
	 *
	 * Permitted units for displaying the data.
	 * 'd' = days, 'h' = hours, 'm' = minutes, 's' = seconds.  Use combinations to give a choice.
	 * Default is 'dhm' = days or hours with selectbox.
	 */
	@property({type: String, reflect: true})
	set displayFormat(value : string)
	{
		// Update display if needed
		let current : number;
		if(value !== this.__displayFormat)
		{
			const currentValue = this._oldValue ?? this._display;
			current = this._unit_from_value(
				currentValue.value, this.dataFormat, true, {
					dataFormat: currentValue.unit || this.dataFormat,
					hoursPerDay: this.hoursPerDay
				}
			);
		}
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
		if(!isNaN(current))
		{
			this.value = current;
		}
	}

	get displayFormat()
	{
		return this.__displayFormat;
	}

	/**
	 * Select unit or input per unit
	 *
	 * Display a unit-selection for multiple units, or an input field per unit.
	 * Default is true
	 */
	@property({type: Boolean, reflect: true})
	selectUnit = true;

	/**
	 * Percent allowed
	 *
	 * Allows to enter a percentage instead of numbers
	 */
	@property({type: Boolean})
	percentAllowed = false;

	/**
	 * Hours per day
	 *
	 * Number of hours in a day, used for converting between hours and (working) days.
	 * Default 8
	 */
	@property({type: Number, reflect: true})
	hoursPerDay = 8;

	/**
	 * 0 or empty
	 *
	 * Should the widget differ between 0 and empty, which get then returned as NULL
	 * Default false, empty is considered as 0
	 */
	@property({type: Boolean, reflect: true})
	emptyNot0 = false;

	/**
	 * Short labels
	 *
	 * use d/h/m instead of day/hour/minute
	 */
	@property({type: Boolean, reflect: true})
	shortLabels = false;

	/**
	 * Step limit
	 *
	 * Works with the min and max attributes to limit the increments at which a numeric or date-time value can be set.
	 */
	@property({type: Number, reflect: true})
	step = 1;

	protected static time_formats = {d: "d", h: "h", m: "m", s: "s"};
	protected _display = {value: "", unit: ""};

	constructor()
	{
		super();

		// Property defaults
		this.displayFormat = "dhm";

		this.formatter = formatDuration;
	}

	async getUpdateComplete()
	{
		const result = await super.getUpdateComplete();

		// Format select does not start with value, needs an update
		this._formatNode?.requestUpdate("value");

		return result;
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
				value += this._durationNode[i].valueAsNumber * this._unit2seconds(this._durationNode[i].name);
			}
			if(this.dataFormat !== 's')
			{
				value /= this._unit2seconds(this.dataFormat);
			}
			return "" + (this.dataFormat === 'm' ? Math.round(value) : value);
		}

		let val = this._durationNode.length ? this._durationNode[0].valueAsNumber : '';
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
		this._oldValue = {value: _value, unit: this.dataFormat};
		this._display = this._convert_to_display(this.emptyNot0 && ""+_value === "" ? '' : parseFloat(_value));
		// Update values
		(typeof this._display.value == "string" ? this._display.value.split(":") : [this._display.value])
			.forEach((v, index) =>
			{
				if(!this._durationNode[index])
				{
					return;
				}
				const old = this._durationNode[index]?.value;
				this._durationNode[index].value = v;
				this._durationNode[index].requestUpdate("value", old);
			});
		this.requestUpdate();
	}

	render()
	{

		const labelTemplate = this._labelTemplate();
		const helpTemplate = this._helpTextTemplate();

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': labelTemplate !== nothing,
                        'form-control--has-help-text': helpTemplate !== nothing
                    })}
            >
                ${labelTemplate}
                <div part="form-control-input" class="form-control-input" @sl-change=${() =>
                {
                    this.dispatchEvent(new Event("change", {bubbles: true}));
                }}>
                    ${this._inputTemplate()}
                    ${this._formatTemplate()}
                </div>
                ${helpTemplate}
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
				let val = this._unit_from_value(_value, unit, i === 0, {
					hoursPerDay: this.hoursPerDay,
					dataFormat: this.dataFormat
				});
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

	private _unit_from_value(_value, _unit, _highest, options)
	{
		_value *= this._unit2seconds(options.dataFormat);
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
				return _highest ? _value : _value % options.hoursPerDay;
			case 'd':
				return Math.floor(_value / 3600 / options.hoursPerDay);
		}
	}

	handleInputChange(event)
	{
		// Rather than error, roll over when possible
		const changed = event.target;
		if(typeof changed.max == "number" && parseInt(changed.value) >= changed.max)
		{
			const next = changed.previousElementSibling;
			if(next)
			{
				next.value = next.valueAsNumber + Math.floor(changed.valueAsNumber / changed.max);
				changed.value = changed.valueAsNumber % changed.max;
			}
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
		let count = this.selectUnit ? 1 : this.displayFormat.length;
		for(let i = count; i > 0; --i)
		{
			let input = {
				name: "",
				title: "",
				value: typeof value == "number" ? value : ((this.selectUnit ? value.pop() : value[i]) || ""),
				min: undefined,
				max: undefined,
				precision: count == 1 ? 2 : 0
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
                    <et2-number part="${"duration__" + input.name}" class="duration__input"
                                exportparts="scroll:scroll,scrollbutton:scrollbutton,base"
                                name=${input.name}
                                min=${typeof input.min === "number" ? input.min : nothing}                                
								max=${typeof input.max === "number" ? input.max : nothing}
                                step=${this.step}
								precision=${typeof input.precision === "number" ? input.precision : nothing} 
								title=${input.title || nothing}
                                value=${live(input.value)}
                                @sl-change=${this.handleInputChange}
                    ></et2-number>`
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
		const current = this._display.unit || this.displayFormat[0];
		return html`
            <sl-select exportparts="combobox" value="${current}">
                ${[...this.displayFormat].map((format : string) =>
                        html`
                            <sl-option
                                    value=${format}
                                    .selected=${(format == current)}
                            >
                                ${this.time_formats[format]}
                            </sl-option>`
                )}
            </sl-select>
		`;
	}

	/**
	 * @returns {HTMLInputElement}
	 */
	get _durationNode() : HTMLInputElement[]
	{
		return this.shadowRoot ? this.shadowRoot.querySelectorAll(".duration__input") || [] : [];
	}


	/**
	 * @returns {HTMLSelectElement}
	 */
	get _formatNode() : HTMLSelectElement
	{
		return this.shadowRoot ? this.shadowRoot.querySelector("sl-select") : null;
	}
}