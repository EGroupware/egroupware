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
import {holidays} from "./Holidays";
import flatpickr from "flatpickr";
import {egw} from "../../jsapi/egw_global";
import {Et2Textbox} from "../Et2Textbox/Et2Textbox";

// Request this year's holidays now
holidays(new Date().getFullYear());

// list of existing localizations from node_modules/flatpicker/dist/l10n directory:
const l10n = [
	'ar', 'at', 'az', 'be', 'bg', 'bn', 'bs', 'cat', 'cs', 'cy', 'da', 'de', 'eo', 'es', 'et', 'fa', 'fi', 'fo',
	'fr', 'ga', 'gr', 'he', 'hi', 'hr', 'hu', 'id', 'index', 'is', 'it', 'ja', 'ka', 'km', 'ko', 'kz', 'lt', 'lv', 'mk',
	'mn', 'ms', 'my', 'nl', 'no', 'pa', 'pl', 'pt', 'ro', 'ru', 'si', 'sk', 'sl', 'sq', 'sr-cyr', 'sr', 'sv', 'th', 'tr',
	'uk', 'uz', 'uz_latn', 'vn', 'zh-tw', 'zh',
];
const lang = egw ? <string>egw.preference('lang') || "" : "";
// only load localization, if we have one
if (l10n.indexOf(lang) >= 0)
{
	import(egw.webserverUrl + "/node_modules/flatpickr/dist/l10n/" + lang + ".js").then(() =>
	{
		// @ts-ignore
		flatpickr.localize(flatpickr.l10ns[lang]);
	});
}

/**
 * Parse a date string into a Date object
 * Time will be 00:00:00 UTC
 *
 * @param {string} dateString
 * @returns {Date | undefined}
 */
export function parseDate(dateString, formatString?)
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

	formatString = formatString || <string>(window.egw.preference("dateformat") || 'Y-m-d');
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
		case 'Ymd':
			// Not a preference option, but used by some dates
			parsedString = `${dateString.slice(0, 4)}/${dateString.slice(4, 6,)}/${dateString.slice(6, 8)}`;
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
	if(dateformat.indexOf("M") != -1)
	{
		replace_map["M"] = flatpickr.formatDate(date, "M");
	}

	let re = new RegExp(Object.keys(replace_map).join("|"), "g");
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
			...super.properties,
			/**
			 * Display the calendar inline instead of revealed as needed
			 */
			inline: {type: Boolean}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			input: () =>
			{
				const text = <Et2Textbox>document.createElement('et2-textbox');
				text.type = "text";
				return text;
			}
		}
	}

	constructor()
	{
		super();

		this._onDayCreate = this._onDayCreate.bind(this);
	}


	connectedCallback()
	{
		super.connectedCallback();
		this._updateValueOnChange = this._updateValueOnChange.bind(this);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._inputNode.removeEventListener('change', this._onChange);
		this.destroy();
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
		if(typeof this._instance === "undefined")
		{
			this.initializeComponent();

			// This has to go in init() rather than connectedCallback() because flatpickr creates its nodes in
			// initializeComponent() so this._inputNode is not available before this
			this._inputNode.setAttribute("slot", "input");
			this._inputNode.addEventListener('change', this._updateValueOnChange);
		}
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

		options.altFormat = <string>this.egw()?.preference("dateformat") || "Y-m-d";
		options.altInput = true;
		options.allowInput = true;
		options.dateFormat = "Y-m-dT00:00:00\\Z";
		options.weekNumbers = true;

		options.onDayCreate = this._onDayCreate;

		this._localize(options);

		if(this.inline)
		{
			options.inline = this.inline;
		}

		// Turn on scroll wheel support
		// @ts-ignore TypeScript can't find scrollPlugin, but rollup does
		options.plugins = [new scrollPlugin()];

		// Listen for flatpickr change so we can update internal value, needed for validation
		options.onChange = options.onReady = this._updateValueOnChange;

		return options;
	}

	/**
	 * Set localize options & translations
	 * @param options
	 * @protected
	 */
	protected _localize(options)
	{
		let first_dow = <string>this.egw()?.preference('weekdaystarts', 'calendar') || 'Monday';
		const DOW_MAP = {Monday: 1, Sunday: 0, Saturday: 6};
		options.locale = {
			firstDayOfWeek: DOW_MAP[first_dow]
		};
	}

	set_value(value)
	{
		if(!value || value == 0 || value == "0")
		{
			value = "";
			this.modelValue = "";
			this.clear();
			return;
		}
		let date;
		// handle relative time (eg. "+3600" or "-3600") used in calendar
		if (typeof value === 'string' && (value[0] === '+' || value[0] === '-'))
		{
			date = new Date(this.getValue());
			date.set_value(date.getSeconds() + parseInt(value));
		}
		else
		{
			date = new Date(value);
		}
		// Handle timezone offset, flatpickr uses local time
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
		if(this.readonly || this.disabled)
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
	 * Inline calendars need a slot
	 *
	 * @return {TemplateResult}
	 * @protected
	 */
	// eslint-disable-next-line class-methods-use-this
	_inputGroupAfterTemplate()
	{
		return html`
            <div class="input-group__after">
                <slot name="after"></slot>
                <slot/>
            </div>
		`;
	}

	/**
	 * Change handler setting modelValue for validation
	 *
	 * @param _ev
	 * @returns
	 */
	_updateValueOnChange(_ev : Event)
	{
		this.modelValue = this.getValue();
	}

	/**
	 * Customise date rendering
	 *
	 * @see https://flatpickr.js.org/events/
	 *
	 * @param {Date} dates Currently selected date(s)
	 * @param dStr a string representation of the latest selected Date object by the user. The string is formatted as per the dateFormat option.
	 * @param inst flatpickr instance
	 * @param dayElement
	 * @protected
	 */
	protected _onDayCreate(dates : Date[], dStr : string, inst, dayElement : HTMLElement)
	{
		//@ts-ignore flatpickr adds dateObj to days
		let date = new Date(dayElement.dateObj);
		let f_date = new Date(date.valueOf() - date.getTimezoneOffset() * 60 * 1000);
		if(!f_date)
		{
			return;
		}

		let set_holiday = function(holidays, element)
		{
			let day_holidays = holidays[formatDate(f_date, {dateFormat: "Ymd"})]
			let tooltip = '';
			if(typeof day_holidays !== 'undefined' && day_holidays.length)
			{
				for(var i = 0; i < day_holidays.length; i++)
				{
					if(typeof day_holidays[i]['birthyear'] !== 'undefined')
					{
						element.classList.add('calBirthday');
					}
					else
					{
						element.classList.add('calHoliday');
					}
					tooltip += day_holidays[i]['name'] + "\n";
				}
			}
			if(tooltip)
			{
				this.egw().tooltipBind(element, tooltip);
			}
		}.bind(this);

		let holiday_list = holidays(f_date.getFullYear());
		if(holiday_list instanceof Promise)
		{
			holiday_list.then((h) => {set_holiday(h, dayElement);});
		}
		else
		{
			set_holiday(holiday_list, dayElement);
		}
	}

	/**
	 * Set the minimum allowed date
	 * @param {string | Date} min
	 */
	set_min(min : string | Date)
	{
		this.minDate = min;
	}

	set minDate(min : string | Date)
	{
		if(this._instance)
		{
			if(min)
			{
				// Handle timezone offset, flatpickr uses local time
				let date = new Date(min);
				let formatDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);
				this._instance.set("minDate", formatDate)
			}
			else
			{
				this._instance.set("minDate", "")
			}
		}
	}

	/**
	 * Set the minimum allowed date
	 * @param {string | Date} max
	 */
	set_max(max : string | Date)
	{
		this.maxDate = max;
	}

	set maxDate(max : string | Date)
	{
		if(this._instance)
		{
			if(max)
			{
				// Handle timezone offset, flatpickr uses local time
				let date = new Date(max);
				let formatDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);
				this._instance.set("maxDate", formatDate)
			}
			else
			{
				this._instance.set("maxDate", "")
			}
		}
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
	 * The interactive (form) element.
	 * @protected
	 */
	get _inputNode() : HTMLElement
	{
		return this.querySelector('et2-textbox');
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-date", Et2Date);