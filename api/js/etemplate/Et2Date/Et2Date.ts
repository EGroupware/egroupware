/**
 * EGroupware eTemplate2 - Date widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "@lion/core";
import {FormControlMixin, ValidateMixin} from "@lion/form-core";
import 'lit-flatpickr';
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {dateStyles} from "./DateStyles";
import {LitFlatpickr} from "lit-flatpickr";
import "flatpickr/dist/plugins/scrollPlugin.js";


/**
 * Parse a date string into a Date object
 * Time will be 00:00:00 UTC
 *
 * @param {string} dateString
 * @returns {Date | undefined}
 */
export function parseDate(dateString)
{
	// First try the server format
	if(dateString.substr(-1) === "Z")
	{
		try
		{
			let date = new Date(dateString);
			if(date instanceof Date)
			{
				return date;
			}
		}
		catch(e)
		{
			// Nope, that didn't parse directly
		}
	}

	let formatString = <string>(window.egw.preference("dateformat") || 'Y-m-d');
	//@ts-ignore replaceAll() does not exist
	formatString = formatString.replaceAll(new RegExp('[-/\.]', 'ig'), '-');
	let parsedString = "";
	switch(formatString)
	{
		case 'd-m-Y':
			parsedString = `${dateString.slice(6, 10)}/${dateString.slice(3, 5,)}/${dateString.slice(0, 2)}`;
			break;
		case 'm-d-Y':
			parsedString = `${dateString.slice(6, 10)}/${dateString.slice(0, 2,)}/${dateString.slice(3, 5)}`;
			break;
		case 'Y-m-d':
			parsedString = `${dateString.slice(0, 4)}/${dateString.slice(5, 7,)}/${dateString.slice(8, 10)}`;
			break;
		case 'Y-d-m':
			parsedString = `${dateString.slice(0, 4)}/${dateString.slice(8, 10)}/${dateString.slice(5, 7)}`;
			break;
		case 'd-M-Y':
			parsedString = `${dateString.slice(6, 10)}/${dateString.slice(3, 5,)}/${dateString.slice(0, 2)}`;
			break;
		default:
			parsedString = '0000/00/00';
	}

	const [year, month, day] = parsedString.split('/').map(Number);
	const parsedDate = new Date(`${year}-${month < 10 ? "0" + month : month}-${day < 10 ? "0" + day : day}T00:00:00Z`);

	// Check if parsedDate is not `Invalid Date` or that the date has changed (e.g. the not existing 31.02.2020)
	if(
		year > 0 &&
		month > 0 &&
		day > 0 &&
		parsedDate.getUTCDate() === day &&
		parsedDate.getUTCMonth() === month - 1
	)
	{
		return parsedDate;
	}
	return undefined;
}

/**
 * To parse a time into a Date object
 * Date will be 1970-01-01, time is in UTC to avoid browser issues
 *
 * @param {string} timeString
 * @returns {Date | undefined}
 */
export function parseTime(timeString)
{
	// First try the server format
	if(timeString.substr(-1) === "Z")
	{
		try
		{
			let date = new Date(timeString);
			if(date instanceof Date)
			{
				return date;
			}
		}
		catch(e)
		{
			// Nope, that didn't parse directly
		}
	}

	let am_pm = timeString.endsWith("pm") || timeString.endsWith("PM") ? 12 : 0;

	let strippedString = timeString.replaceAll(/[^0-9:]/gi, '');

	if(timeString.startsWith("12") && strippedString != timeString)
	{
		// 12:xx am -> 0:xx, 12:xx pm -> 12:xx
		am_pm -= 12;
	}

	const [hour, minute] = strippedString.split(':').map(Number);

	const parsedDate = new Date("1970-01-01T00:00:00Z");
	parsedDate.setUTCHours(hour + am_pm);
	parsedDate.setUTCMinutes(minute);

	// Check if parsedDate is not `Invalid Date` or that the time has changed
	if(
		parsedDate.getUTCHours() === hour + am_pm &&
		parsedDate.getUTCMinutes() === minute
	)
	{
		return parsedDate;
	}
	return undefined;
}

/**
 * To parse a date+time into an object
 * Time is in UTC to avoid browser issues
 *
 * @param {string} dateTimeString
 * @returns {Date | undefined}
 */
export function parseDateTime(dateTimeString)
{
	// First try some common invalid values
	if(dateTimeString === "" || dateTimeString === "0" || dateTimeString === 0)
	{
		return undefined;
	}

	// Next try server format
	if(typeof dateTimeString === "string" && dateTimeString.substr(-1) === "Z" || !isNaN(dateTimeString))
	{
		if(!isNaN(dateTimeString) && parseInt(dateTimeString) == dateTimeString)
		{
			console.warn("Invalid date/time string: " + dateTimeString);
			dateTimeString *= 1000;
		}
		try
		{
			let date = new Date(dateTimeString);
			if(date instanceof Date)
			{
				return date;
			}
		}
		catch(e)
		{
			// Nope, that didn't parse directly
		}
	}

	const date = parseDate(dateTimeString);

	let explody = dateTimeString.split(" ");
	explody.shift();
	const time = parseTime(explody.join(" "));

	if(typeof date === "undefined" || typeof time === "undefined")
	{
		return undefined;
	}
	date.setUTCHours(time.getUTCHours());
	date.setUTCMinutes(time.getUTCMinutes());
	date.setUTCSeconds(time.getUTCSeconds());
	return date;
}

/**
 * Format dates according to user preference
 *
 * @param {Date} date
 * @param {import('@lion/localize/types/LocalizeMixinTypes').FormatDateOptions} [options] Intl options are available
 * 	set 'dateFormat': "Y-m-d" to specify a particular format
 * @returns {string}
 */
export function formatDate(date : Date, options = {dateFormat: ""}) : string
{
	if(!date || !(date instanceof Date))
	{
		return "";
	}
	let _value = '';
	let dateformat = options.dateFormat || <string>window.egw.preference("dateformat") || 'Y-m-d';

	let replace_map = {
		d: (date.getUTCDate() < 10 ? "0" : "") + date.getUTCDate(),
		m: (date.getUTCMonth() < 9 ? "0" : "") + (date.getUTCMonth() + 1),
		Y: "" + date.getUTCFullYear()
	}
	let re = new RegExp(Object.keys(replace_map).join("|"), "gi");
	_value = dateformat.replace(re, function(matched)
	{
		return replace_map[matched];
	});
	return _value;
}

/**
 * Format dates according to user preference
 *
 * @param {Date} date
 * @param {import('@lion/localize/types/LocalizeMixinTypes').FormatDateOptions} [options] Intl options are available
 * 	set 'timeFormat': "12" to specify a particular format
 * @returns {string}
 */
export function formatTime(date : Date, options = {timeFormat: ""}) : string
{
	if(!date || !(date instanceof Date))
	{
		return "";
	}
	let _value = '';

	let timeformat = options.timeFormat || <string>window.egw.preference("timeformat") || "24";
	let hours = (timeformat == "12" && date.getUTCHours() > 12) ? (date.getUTCHours() - 12) : date.getUTCHours();
	if(timeformat == "12" && hours == 0)
	{
		// 00:00 is 12:00 am
		hours = 12;
	}

	_value = (timeformat == "24" && hours < 10 ? "0" : "") + hours + ":" +
		(date.getUTCMinutes() < 10 ? "0" : "") + (date.getUTCMinutes()) +
		(timeformat == "24" ? "" : (date.getUTCHours() < 12 ? " am" : " pm"));

	return _value;
}

/**
 * Format date+time according to user preference
 *
 * @param {Date} date
 * @param {import('@lion/localize/types/LocalizeMixinTypes').FormatDateOptions} [options] Intl options are available
 * 	set 'dateFormat': "Y-m-d", 'timeFormat': "12" to specify a particular format
 * @returns {string}
 */
export function formatDateTime(date : Date, options = {dateFormat: "", timeFormat: ""}) : string
{
	if(!date || !(date instanceof Date))
	{
		return "";
	}
	return formatDate(date, options) + " " + formatTime(date, options);
}

export class Et2Date extends Et2InputWidget(FormControlMixin(ValidateMixin(LitFlatpickr)))
{
	static get styles()
	{
		return [
			...super.styles,
			dateStyles,
			css`
			:host {
				width: auto;
			}
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties
		}
	}

	constructor()
	{
		super();
	}


	connectedCallback()
	{
		super.connectedCallback();
		this._onChange = this._onChange.bind(this);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._inputNode.removeEventListener('change', this._onChange);
	}

	/**
	 * Override parent to skip call to CDN
	 * @returns {Promise<void>}
	 */
	async init()
	{
		if(this.locale)
		{
			//	await loadLocale(this.locale);
		}
		this.initializeComponent();

		// This has to go in init() rather than connectedCallback() because flatpickr creates its nodes in
		// initializeComponent() so this._inputNode is not available before this
		this._inputNode.addEventListener('change', this._onChange);
	}

	/**
	 * Override some flatpickr defaults to get things how we like it
	 *
	 * @see https://flatpickr.js.org/options/
	 * @returns {any}
	 */
	protected getOptions()
	{
		let options = super.getOptions();

		options.altFormat = this.egw()?.preference("dateformat") || "Y-m-d";
		options.altInput = true;
		options.allowInput = true;
		options.dateFormat = "Y-m-dT00:00:00\\Z";
		options.weekNumbers = true;

		// Turn on scroll wheel support
		// @ts-ignore TypeScript can't find scrollPlugin, but rollup does
		options.plugins = [new scrollPlugin()];

		return options;
	}

	set_value(value)
	{
		if(!value || value == 0 || value == "0")
		{
			value = "";
			this.modelValue = "";
			return;
		}
		// Handle timezone offset, flatpickr uses local time
		let date = new Date(value);
		let formatDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);
		if(!this._instance)
		{
			this.defaultDate = formatDate;
		}
		else
		{
			this.setDate(formatDate);
		}
	}

	getValue()
	{
		if(this.readOnly)
		{
			return null;
		}

		// Copied from flatpickr, since Et2InputWidget overwrote flatpickr.getValue()
		if(!this._inputElement)
		{
			return '';
		}
		let value = this._inputElement.value;

		// Empty field, return ''
		if(!value)
		{
			return '';
		}

		return value;
	}

	/**
	 * Change handler setting modelValue for validation
	 *
	 * @param _ev
	 * @returns
	 */
	_onChange(_ev : Event) : boolean
	{
		const ret = super._onChange(_ev);

		this.modelValue = this.getValue();

		return ret;
	}

	/**
	 * Set the minimum allowed date
	 * @param {string | Date} min
	 */
	set_min(min : string | Date)
	{
		this.minDate = min;
	}

	/**
	 * Set the minimum allowed date
	 * @param {string | Date} max
	 */
	set_max(max : string | Date)
	{
		this.maxDate = max;
	}

	_inputGroupInputTemplate()
	{
		return html`
            <div class="input-group__input">
                <slot name="input">
                    <input class="lit-flatpickr flatpickr flatpickr-input"
                           placeholder=${this.placeholder}/>
                </slot>
            </div>
		`;
	}

	/**
	 * Override from flatpickr
	 * @returns {any}
	 */
	findInputField()
	{
		return this._inputNode;
	}

	/**
	 * The interactive (form) element.  Override from Lion
	 * @protected
	 */
	get _inputNode()
	{
		return /** @type {HTMLElementWithValue} */ (this.shadowRoot.querySelector('input'));
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-date", Et2Date);