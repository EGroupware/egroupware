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
import 'lit-flatpickr';
import {dateStyles} from "./DateStyles";
import {Instance} from 'flatpickr/dist/types/instance';
import "flatpickr/dist/plugins/scrollPlugin.js";
import "shortcut-buttons-flatpickr/dist/shortcut-buttons-flatpickr";
import flatpickr from "flatpickr";
import {egw} from "../../jsapi/egw_global";
import {HTMLElementWithValue} from "@lion/form-core/types/FormControlMixinTypes";
import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {Et2ButtonIcon} from "../Et2Button/Et2ButtonIcon";
import {FormControlMixin} from "@lion/form-core";
import {LitFlatpickr} from "lit-flatpickr";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import shoelace from "../Styles/shoelace";

const textbox = new Et2Textbox();
const button = new Et2ButtonIcon();

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
		Y: "" + date.getUTCFullYear(),
		H: ("0" + date.getUTCHours()).slice(-2),
		i: ("0" + date.getUTCMinutes()).slice(-2)
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

// !!! ValidateMixin !!!
export class Et2Date extends Et2InputWidget(FormControlMixin(LitFlatpickr))
{
	static get styles()
	{
		return [
			...(super.styles ? (Array.isArray(super.styles) ? super.styles : [super.styles]) : []),
			shoelace,
			dateStyles,
			css`
			  :host {
				width: auto;
			  }

			  ::slotted([slot='input']) {
				flex: 1 1 auto;
				min-width: 12ex;
			  }

			  /* Scroll buttons */

			  .input-group__container {
				position: relative;
			  }

			  .input-group__container:hover .et2-date-time__scrollbuttons {
				display: flex;
			  }

			  .et2-date-time__scrollbuttons {
				display: none;
				flex-direction: column;
				width: calc(var(--sl-input-height-medium) / 2);
				position: absolute;
				right: 0px;
			  }

			  .et2-date-time__scrollbuttons > * {
				font-size: var(--sl-font-size-2x-small);
				height: calc(var(--sl-input-height-medium) / 2);
			  }
			.et2-date-time__scrollbuttons > *::part(base) {
				padding: 3px;
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
			inline: {type: Boolean},
			/**
			 * Placeholder text for input
			 */
			placeholder: {type: String},

			/**
			 * Allow value that is not a multiple of minuteIncrement
			 *
			 * eg: 11:23 with default 5 minuteIncrement = 11:25
			 * 16:47 with 30 minuteIncrement = 17:00
			 * If false (default), it is impossible to have a time that is not a multiple of minuteIncrement.
			 * Does not affect scroll, which always goes to nearest multiple.
			 */
			freeMinuteEntry: {type: Boolean},

			/**
			 * The preferred placement of the calendar popup can be set with the placement attribute.  The default
			 * is "auto".  Note that the actual position may vary to ensure the calendar remains in the viewport.
			 * Valid placements are "top", "bottom" or "auto".
			 */
			placement: {type: String, noAccessor: true}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			input: () =>
			{
				if(typeof egwIsMobile == "function" && egwIsMobile())
				{
					// Plain input for mobile
					const text = document.createElement('input');
					text.type = this._mobileInputType();
					return text;
				}
				// This element gets hidden and used for value, but copied by flatpicr and used for input
				const text = <Et2Textbox>document.createElement('et2-textbox');
				text.type = "text";
				text.placeholder = this.placeholder;
				text.required = this.required;
				return text;
			}
		}
	}

	constructor()
	{
		super();

		// By default, 5 minute resolution (see minuteIncrement to change resolution)
		this.freeMinuteEntry = false;

		this._onDayCreate = this._onDayCreate.bind(this);
		this._handleInputChange = this._handleInputChange.bind(this);
		this._onReady = this._onReady.bind(this);
		this.handleScroll = this.handleScroll.bind(this);
	}


	connectedCallback()
	{
		super.connectedCallback();
		this._updateValueOnChange = this._updateValueOnChange.bind(this);
		this._handleShortcutButtonClick = this._handleShortcutButtonClick.bind(this);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this._inputNode?.removeEventListener('change', this._onChange);
		this.findInputField()?.removeEventListener("input", this._handleInputChange);
	}

	/**
	 * Override parent to skip call to CDN
	 * @returns {Promise<void>}
	 */
	async init()
	{
		// Plain input for mobile
		if(typeof egwIsMobile == "function" && egwIsMobile())
		{
			return;
		}

		if(this.locale)
		{
			//	await loadLocale(this.locale);
		}
		if(typeof this._instance === "undefined")
		{
			if(this.getOptions().allowInput)
			{
				// Change this so it uses findInputField() to get the input
				this._hasSlottedElement = true;

				// Wait for everything to be there before we start flatpickr
				await this.updateComplete;
				await this._inputNode.updateComplete;
				this._inputNode.requestUpdate();
				await this._inputNode.updateComplete;

				// Set flag attribute on _internal_ input - flatpickr needs an <input>
				if(this._inputNode.shadowRoot.querySelectorAll("input[type='text']").length == 1)
				{
					this.findInputField().setAttribute("data-input", "");
				}
				if(this.defaultDate)
				{
					this._inputNode.value = flatpickr.formatDate(<Date>this.defaultDate, this.getOptions().dateFormat);
				}
			}

			this.initializeComponent();

			// This has to go in init() rather than connectedCallback() because flatpickr creates its nodes in
			// initializeComponent() so this._inputNode is not available before this
			this.findInputField().addEventListener('change', this._updateValueOnChange);
			this.findInputField().addEventListener("input", this._handleInputChange);
		}
	}

	/**
	 * Override some flatpickr defaults to get things how we like it
	 *
	 * @see https://flatpickr.js.org/options/
	 * @returns {any}
	 */
	public getOptions()
	{
		let options = super.getOptions();

		options.altFormat = <string>this.egw()?.preference("dateformat") || "Y-m-d";
		options.altInput = true;
		options.allowInput = true;
		options.dateFormat = "Y-m-dT00:00:00\\Z";
		options.weekNumbers = true;
		// Wrap needs to be false because flatpickr can't look inside et2-textbox and find the <input> it wants
		// We provide it directly through findInputField()
		options.wrap = false;

		options.onDayCreate = this._onDayCreate;

		this._localize(options);

		if(this.inline)
		{
			options.inline = this.inline;
		}
		if(this.placement)
		{
			options.position = this._convert_placement(this.placement);
		}

		options.plugins = [
			// Turn on scroll wheel support
			// @ts-ignore TypeScript can't find scrollPlugin, but rollup does
			new scrollPlugin()
		];
		// Add "Ok" and "today" buttons
		const buttons = this._buttonPlugin();
		if(buttons)
		{
			options.plugins.push(buttons)
		}


		// Listen for flatpickr change so we can update internal value, needed for validation
		options.onChange = this._updateValueOnChange;
		options.onReady = this._onReady;

		// Remove Lion's inert attribute so we can work in Et2Dialog
		options.onOpen = [() =>
		{
			this._instance.calendarContainer?.removeAttribute("inert")
		}];

		return options;
	}

	/**
	 * Handle click on shortcut button(s) like "Today"
	 *
	 * @param button_index
	 * @param fp Flatpickr instance
	 */
	public _handleShortcutButtonClick(button_index, fp)
	{
		switch(button_index)
		{
			case 0: // OK
				fp.close();
				break;
			default:
				fp.setDate(new Date());
		}
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

	/**
	 * For mobile, we use a plain input of the proper type
	 * @returns {string}
	 */
	_mobileInputType() : string
	{
		return "date";
	}

	/**
	 * Add "today" button below calendar
	 * @protected
	 */
	protected _buttonPlugin()
	{
		// @ts-ignore TypeScript can't find ShortcutButtonsPlugin, but rollup does
		return ShortcutButtonsPlugin({
			button: [
				{label: this.egw().lang("ok")},
				{label: this.egw().lang("Today")}
			],
			onClick: this._handleShortcutButtonClick
		})
	}

	set value(value)
	{
		if(!value || value == 0 || value == "0")
		{
			value = "";
			this.clear();
			return;
		}
		let date;
		// handle relative time (eg. "+3600" or "-3600") used in calendar
		if(typeof value === 'string' && (value[0] === '+' || value[0] === '-'))
		{
			date = new Date(this.getValue());
			date.setSeconds(date.getSeconds() + parseInt(value));
		}
		else
		{
			date = new Date(value);
		}
		if(typeof egwIsMobile == "function" && egwIsMobile())
		{
			if(this._inputNode)
			{
				this._inputNode.value = isNaN(date) ? "" : this.format(date, {dateFormat: 'Y-m-dTH:i'});
			}
			else
			{
				this.updateComplete.then(() =>
				{
					this._inputNode.value = isNaN(date) ? "" : this.format(date, {dateFormat: 'Y-m-dTH:i'});
				});
			}
			return;
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

		this.requestUpdate("value", this._oldValue);
	}

	get value()
	{
		if(!this._inputElement)
		{
			if(this.defaultDate)
			{
				return flatpickr.formatDate(<Date>this.defaultDate, this.getOptions().dateFormat);
			}
			if(typeof egwIsMobile == "function" && egwIsMobile())
			{
				return this._inputNode?.value + "Z" || "";
			}
			return this._inputNode?.value || '';
		}
		let value = this._inputElement.value;

		// Empty field, return ''
		if(!value)
		{
			return '';
		}

		return value;
	}

	get parse() : Function
	{
		return parseDate;
	}

	get format() : Function
	{
		return formatDate;
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
	 * Update the calendar when the input value changes
	 * Otherwise, user's change will be overwritten by calendar popup when the input loses focus
	 *
	 * @param e
	 */
	_handleInputChange(e : InputEvent)
	{
		// Update
		const value = this.findInputField().value;

		if(value === "" && this._instance.selectedDates.length > 0)
		{
			// Update the et2-textbox so it will fail a required validation check
			this._inputNode.value = '';
			this._instance.clear();
			this.dispatchEvent(new Event("change", {bubbles: true}));
		}
		let parsedDate = null
		try
		{
			parsedDate = this._instance.parseDate(value, this.getOptions().altFormat)
		}
		catch(e)
		{
			// Invalid date string
		}
		// If they typed a valid date/time, try to update flatpickr
		if(parsedDate)
		{
			const formattedDate = flatpickr.formatDate(parsedDate, this.getOptions().altFormat)
			if(value === formattedDate &&
				// Avoid infinite loop of setting the same value back triggering another change
				this._instance.input.value !== flatpickr.formatDate(parsedDate, this.getOptions().dateFormat))
			{
				this._instance.setDate(value, true, this._instance.config.altFormat)
			}
			// Update the et2-textbox so it has current value for any (required) validation
			this._inputNode.value = formattedDate;
			(<Et2Textbox>this._inputNode).validate();
		}
		this.dispatchEvent(new Event("change", {bubbles: true}));
	}

	/**
	 * Change handler setting modelValue for validation
	 *
	 * @param _ev
	 * @returns
	 */
	_updateValueOnChange(selectedDates : Date[], dateStr : string, instance : Instance)
	{
		this.modelValue = this.getValue();
	}

	_onReady(selectedDates : Date[], dateStr : string, instance : Instance)
	{
		this._updateValueOnChange(selectedDates, dateStr, instance);

		// Add any classes we have to the instance
		instance.calendarContainer.classList.add(...this.classList.values())
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

		egw.holidays(f_date.getFullYear()).then((h) => set_holiday(h, dayElement));
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


	private _convert_placement(position : "top" | "bottom" | "auto" | "") : "above" | "below" | "auto"
	{
		let placement = "auto";
		switch(position)
		{
			case "top":
				placement = "above";
				break;
			case "bottom":
				placement = "below";
				break;
		}
		return placement;
	}

	get placement() : "top" | "bottom" | "auto"
	{
		return this.__placement;
	}

	set placement(new_placement : "top" | "bottom" | "auto")
	{
		if(this._instance)
		{
			this._instance.set("position", this._convert_placement(new_placement))
		}
		this.__placement = new_placement;
	}

	/**
	 * Override from flatpickr - This is the node we tell flatpickr to use
	 * It must be an <input>, flatpickr doesn't understand anything else
	 * @returns {any}
	 */
	findInputField() : HTMLInputElement
	{
		return <HTMLInputElement>this._inputNode?.shadowRoot?.querySelector('input:not([type="hidden"])');
	}

	/**
	 * The interactive (form) element.
	 * This is an et2-textbox, which causes some problems with flatpickr
	 * @protected
	 */
	get _inputNode() : HTMLElementWithValue
	{
		return this.querySelector('[slot="input"]');
	}

	/**
	 * The holder of value for flatpickr
	 */
	get _valueNode() : HTMLElementWithValue
	{
		return this.querySelector('et2-textbox');
	}

	/**
	 * Handle clicks on scroll buttons
	 *
	 * @param e
	 */
	public handleScroll(e)
	{
		if(e.target && !e.target.dataset.direction)
		{
			return;
		}
		e.stopPropagation();

		const direction = parseInt(e.target.dataset.direction, 10) || 1;
		this.increment(direction, "day", true);
	}

	/**
	 * Increment the current value
	 *
	 * @param {number} delta Amount of change, positive or negative
	 * @param {"day" | "hour" | "minute"} field
	 * @param {boolean} roundToDelta Round the current value to a multiple of delta before incrementing
	 * 	Useful for keeping things to a multiple of 5, for example.
	 */
	public increment(delta : number, field : "day" | "hour" | "minute", roundToDelta = true)
	{
		let date;
		if(this._inputElement.value)
		{
			date = new Date(this._inputElement.value);
			// Handle timezone offset, flatpickr uses local time
			date = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);
		}
		else
		{
			// No current value - start with "now", but don't increment at all
			date = new Date();
			delta = 0;
		}
		const fieldMap = {day: "UTCDate", hour: "UTCHours", minute: "UTCMinutes"};
		const original = date["get" + fieldMap[field]]();
		// Avoid divide by 0 in case we have no current value, or delta of 0 passed in
		const roundResolution = delta || {
			day: 1,
			hour: this.getOptions().hourIncrement,
			minute: this.getOptions().minuteIncrement
		}[field];

		let bound = roundToDelta ? (Math.round(original / roundResolution) * roundResolution) : original;
		date["set" + fieldMap[field]](bound + delta);


		this.setDate(date, false, null);
	}

	render()
	{
		return html`
            <div part="form-control" class="form-control">
                <div class="form-field__group-one" part="form-control-label">${this._groupOneTemplate()}</div>
                <div class="form-field__group-two" part="form-control-input">${this._groupTwoTemplate()}</div>
            </div>
		`;
	}

	protected _inputGroupInputTemplate()
	{
		return html`
            <slot name="input"></slot>
            ${this._incrementButtonTemplate()}
		`;
	}

	protected _incrementButtonTemplate()
	{
		// No increment buttons on mobile
		if(typeof egwIsMobile == "function" && egwIsMobile())
		{
			return '';
		}

		return html`
            <div class="et2-date-time__scrollbuttons" part="scrollbuttons" @click=${this.handleScroll}>
                <et2-button-icon
                        noSubmit
                        name="chevron-up"
                        data-direction="1"
                >↑
                </et2-button-icon>
                <et2-button-icon
                        noSubmit
                        name="chevron-down"
                        data-direction="-1"
                >↓
                </et2-button-icon>
            </div>`;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-date", Et2Date);