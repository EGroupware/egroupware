/**
 * EGroupware eTemplate2 - Date widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "../../../node_modules/@lion/core/index.js"
import {LionInputDatepicker} from "../../../node_modules/@lion/input-datepicker/index.js"
import {Et2InputWidget} from "./et2_core_inputWidget";
import {Et2Widget} from "./Et2Widget";


/**
 * To parse a date into the right format
 *
 * @param {string} dateString
 * @returns {Date | undefined}
 */
export function parseDate(dateString)
{
	debugger;
	let formatString = <string>(egw.preference("dateformat") || 'Y-m-d');
	formatString = formatString.replaceAll(/-\/\./ig, '-');
	let parsedString = "";
	switch (formatString)
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
	const parsedDate = new Date(Date.UTC(year, month - 1, day));

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
	debugger;
	if (!date || !(date instanceof Date))
	{
		return "";
	}
	let _value = '';
	// Add timezone offset back in, or formatDate will lose those hours
	let formatDate = new Date(date.valueOf() + date.getTimezoneOffset() * 60 * 1000);

	let dateformat = options.dateFormat || <string>egw.preference("dateformat") || 'Y-m-d';

	var replace_map = {
		d: "" + date.getUTCDate(),
		m: "" + date.getUTCMonth() + 1,
		Y: "" + date.getUTCFullYear()
	}
	var re = new RegExp(Object.keys(replace_map).join("|"), "gi");
	_value = dateformat.replace(re, function (matched)
	{
		return replace_map[matched];
	});
	return _value;
}

export class Et2Date extends Et2InputWidget(Et2Widget(LionInputDatepicker))
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Custom CSS */
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			value: {
				attribute: true,
				converter: {
					toAttribute(value)
					{
						return value ? value.toJSON().replace(/\.\d{3}Z$/, 'Z') : "";
					},
					fromAttribute(value)
					{
						return new Date(value);
					}
				}
			},
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


	getValue()
	{
		debugger;
		return this.modelValue ? this.modelValue.toJSON().replace(/\.\d{3}Z$/, 'Z') : "";
	}
}

customElements.define("et2-date", Et2Date);
