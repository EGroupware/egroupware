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


/**
 * To parse a date into the right format
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

	let formatString = <string>(egw.preference("dateformat") || 'Y-m-d');
	formatString = formatString.replaceAll(new RegExp('[-/\.]', 'ig'), '-');
	let parsedString = "";
	switch(formatString)
	{
		case 'd-m-Y':
			parsedString = `${dateString.slice(6, 10)}/${dateString.slice(
				3,
				5,
			)}/${dateString.slice(0, 2)}`;
			break;
		case 'm-d-Y':
			parsedString = `${dateString.slice(6, 10)}/${dateString.slice(
				0,
				2,
			)}/${dateString.slice(3, 5)}`;
			break;
		case 'Y-m-d':
			parsedString = `${dateString.slice(0, 4)}/${dateString.slice(
				5,
				7,
			)}/${dateString.slice(8, 10)}`;
			break;
		case 'd-M-Y':
			parsedString = `${dateString.slice(6, 10)}/${dateString.slice(
				3,
				5,
			)}/${dateString.slice(0, 2)}`;
		default:
			parsedString = '0000/00/00';
	}

	const [year, month, day] = parsedString.split('/').map(Number);
	const parsedDate = new Date(year, month - 1, day);

	// Check if parsedDate is not `Invalid Date` or that the date has changed (e.g. the not existing 31.02.2020)
	if (
		year > 0 &&
		month > 0 &&
		day > 0 &&
		parsedDate.getDate() === day &&
		parsedDate.getMonth() === month - 1
	)
	{
		return parsedDate;
	}
	return undefined;
}

/**
 * Format dates according to user preference
 *
 * @param {Date} date
 * @param {import('@lion/localize/types/LocalizeMixinTypes').FormatDateOptions} [options] Intl options are available
 * 	set 'dateFormat': "Y-m-d" to specify a particular format
 * @returns {string}
 */
export function formatDate(date: Date, options): string
{
	if (!date || !(date instanceof Date))
	{
		return "";
	}
	let _value = '';
	// Add timezone offset back in, or formatDate will lose those hours
	let formatDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);

	let dateformat = options.dateFormat || <string>egw.preference("dateformat") || 'Y-m-d';

	var replace_map = {
		d: (date.getUTCDate() < 10 ? "0" : "") + date.getUTCDate(),
		m: (date.getUTCMonth() < 9 ? "0" : "") + (date.getUTCMonth() + 1),
		Y: "" + date.getUTCFullYear()
	}
	var re = new RegExp(Object.keys(replace_map).join("|"), "gi");
	_value = dateformat.replace(re, function (matched)
	{
		return replace_map[matched];
	});
	return _value;
}

export class Et2Date extends Et2InputWidget(LionInputDatepicker)
{
	static get styles()
	{
		return [
			...super.styles,
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

	getValue()
	{
		// The supplied value was not understandable, return null
		if(this.modelValue instanceof Unparseable)
		{
			return null;
		}

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
	 * Overriding parent to add class to button, and use an image instead of unicode emoji
	 */
	// eslint-disable-next-line class-methods-use-this
	_invokerTemplate()
	{
		return html`
            <button
                    type="button"
                    class="calendar_button"
                    @click="${this.__openCalendarOverlay}"
                    id="${this.__invokerId}"
                    aria-label="${this.msgLit('lion-input-datepicker:openDatepickerLabel')}"
                    title="${this.msgLit('lion-input-datepicker:openDatepickerLabel')}"
            >
                <img src="${this.egw().image("calendar")}" style="width:16px"/>
            </button>
		`;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("et2-date", Et2Date);
