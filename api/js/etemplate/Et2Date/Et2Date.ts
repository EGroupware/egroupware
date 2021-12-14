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
import {LionInputDatepicker} from "@lion/input-datepicker";
import {Unparseable} from "@lion/form-core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {dateStyles} from "./DateStyles";


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
	// Add timezone offset back in, or formatDate will lose those hours
	let formatDate = new Date(date.valueOf() - date.getTimezoneOffset() * 60 * 1000);

	let dateformat = options.dateFormat || <string>window.egw.preference("dateformat") || 'Y-m-d';

	var replace_map = {
		d: (date.getUTCDate() < 10 ? "0" : "") + date.getUTCDate(),
		m: (date.getUTCMonth() < 9 ? "0" : "") + (date.getUTCMonth() + 1),
		Y: "" + date.getUTCFullYear()
	}
	var re = new RegExp(Object.keys(replace_map).join("|"), "gi");
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

export class Et2Date extends Et2InputWidget(LionInputDatepicker)
{
	static get styles()
	{
		return [
			...super.styles,
			dateStyles,
			css`
			:host([focused]) ::slotted(button), :host(:hover) ::slotted(button) {
				display: inline-block;
			}
            ::slotted(.calendar_button) {
            	border: none;
            	background: transparent;
            	margin-left: -20px;
                display: none;
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
		this.parser = parseDate;
		this.formatter = formatDate;
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	/**
	 * @param {Date} modelValue
	 */
	// eslint-disable-next-line class-methods-use-this
	serializer(modelValue : Date)
	{
		// isValidDate() is hidden inside LionInputDate, and not exported
		// @ts-ignore Can't call isNan(Date), but we're just checking
		if(!(modelValue instanceof Date) || isNaN(modelValue))
		{
			return '';
		}
		// modelValue is localized, so we take the timezone offset in milliseconds and subtract it
		// before converting it to ISO string.
		const offset = modelValue.getTimezoneOffset() * 60000;
		return new Date(modelValue.getTime() - offset).toJSON().replace(/\.\d{3}Z$/, 'Z');
	}

	set_value(value)
	{
		this.modelValue = this.parser(value);
	}

	getValue()
	{
		if(this.readOnly)
		{
			return null;
		}

		// Empty field, return ''
		if(!this.modelValue)
		{
			return '';
		}

		// The supplied value was not understandable, return null
		if(this.modelValue instanceof Unparseable || !this.modelValue)
		{
			return null;
		}
		this.modelValue.setUTCHours(0);
		this.modelValue.setUTCMinutes(0);
		this.modelValue.setSeconds(0, 0);

		return this.modelValue.toJSON();
	}

	get _overlayReferenceNode()
	{
		return this.getInputNode();
	}

	/**
	 * @override Configures OverlayMixin
	 * @desc overrides default configuration options for this component
	 * @returns {Object}
	 */
	_defineOverlayConfig()
	{
		this.hasArrow = false;
		if(window.innerWidth >= 600)
		{
			return {
				hidesOnOutsideClick: true,
				placementMode: 'local',
				popperConfig: {
					placement: 'bottom-end',
				},
			};
		}
		return super.withBottomSheetConfig();
	}

	/**
	 * The LionCalendar shouldn't know anything about the modelValue;
	 * it can't handle Unparseable dates, but does handle 'undefined'
	 * @param {?} modelValue
	 * @returns {Date|undefined} a 'guarded' modelValue
	 */
	static __getSyncDownValue(modelValue)
	{
		if(!(modelValue instanceof Date))
		{
			return undefined;
		}
		const offset = modelValue.getTimezoneOffset() * 60000;
		return new Date(modelValue.getTime() + offset);
	}

	/**
	 * Overriding from parent for read-only
	 *
	 * @return {TemplateResult}
	 * @protected
	 */
	// eslint-disable-next-line class-methods-use-this
	_inputGroupInputTemplate()
	{
		if(this.readOnly)
		{
			return this.formattedValue;
		}
		else
		{
			return super._inputGroupInputTemplate();
		}
	}

	/**
	 * Overriding parent to add class to button, and use an image instead of unicode emoji
	 */
	// eslint-disable-next-line class-methods-use-this
	_invokerTemplate()
	{
		if(this.readOnly)
		{
			return '';
		}
		let img = this.egw() ? this.egw().image("calendar") || '' : '';
		return html`
            <button
                    type="button"
                    class="calendar_button"
                    @click="${this.__openCalendarOverlay}"
                    id="${this.__invokerId}"
                    aria-label="${this.msgLit('lion-input-datepicker:openDatepickerLabel')}"
                    title="${this.msgLit('lion-input-datepicker:openDatepickerLabel')}"
            >
                <img src="${img}" style="width:16px"/>
            </button>
		`;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-date", Et2Date);
