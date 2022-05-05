/**
 * EGroupware eTemplate2 - Readonly time since WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {html} from "@lion/core";
import {parseDate, parseDateTime} from "./Et2Date";
import {Et2DateReadonly} from "./Et2DateReadonly";

/**
 * Formatter for time since widget.
 *
 * @param {Date} date
 * @returns {string}
 */
const formatDate = function(date : Date, options = {units: "YmdHis"})
{
	const unit2label = {
		'Y': 'years',
		'm': 'month',
		'd': 'days',
		'H': 'hours',
		'i': 'minutes',
		's': 'seconds'
	};
	let unit2s : Object = {
		'Y': 31536000,
		'm': 2628000,
		'd': 86400,
		'H': 3600,
		'i': 60,
		's': 1
	};
	var d = new Date();
	var diff = Math.round(d.valueOf() / 1000) - Math.round(date.valueOf() / 1000 + egw.getTimezoneOffset() * 60);
	let display = '';

	// limit units used to display
	let smallest = 's';
	if(options.units)
	{
		const valid = Object.entries(unit2s).filter((e) => (<string>options.units).includes(e[0]));
		unit2s = Object.fromEntries(valid);
		smallest = (valid.pop() || [])[0];
	}

	for(var unit in unit2s)
	{
		var unit_s = unit2s[unit];
		if(diff >= unit_s || unit === smallest)
		{
			display = Math.round(diff / unit_s) + ' ' + this.egw().lang(unit2label[unit]);
			break;
		}
	}
	return display;
}

/**
 * Displays the elapsed time since the given date
 *
 * The time units (years, months, days, etc) will be calculated automatically to best match the
 * time scale being dealt with, unless the units property is set.
 *
 * This is a stripped-down read-only widget used in nextmatch
 */
export class Et2DateSinceReadonly extends Et2DateReadonly
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Allowed display units, default 'YmdHis', e.g. 'd' to display a value only in days"
			 */
			units: {type: String, reflect: true},
		}
	}

	constructor()
	{
		super();

		this.parser = parseDateTime;
		this.formatter = formatDate;
	}

	set_value(value)
	{
		this.value = value;
	}

	render()
	{
		let parsed : Date | Boolean = this.value ? this.parser(this.value) : false

		// Be more forgiving if time is missing
		if(!parsed && this.value)
		{
			parsed = parseDate(this.value) || false;
		}

		return html`
            <span ${this.id ? html`id="${this._dom_id}"` : ''}
                  datetime="${parsed ? (<Date>parsed).toJSON() : ""}">
                ${this.value ? this.formatter(<Date>parsed, {units: this.units}) : ''}
            </span>
		`;
	}

	getDetachedAttributes(attrs)
	{
		attrs.push("id", "value", "class");
	}

	getDetachedNodes() : HTMLElement[]
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data? : any) : void
	{
		// Do nothing, since we can't actually stop being a DOM node...
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-date-since", Et2DateSinceReadonly);