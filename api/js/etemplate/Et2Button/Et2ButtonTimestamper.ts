/**
 * EGroupware eTemplate2 - JS Timestamp button object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2017
 */

import {et2_IInput} from "../et2_core_interfaces";
import {date} from "../lib/date.js";
import {Et2Button} from "./Et2Button";
import {Et2Tabs} from "../Layout/Et2Tabs/Et2Tabs";
import {SelectOption} from "../Et2Select/FindSelectOptions";

/**
 * Class which implements the "et2-button-timestamp" tag
 *
 * Clicking the button puts the current time and current user at the end of
 * the provided field.
 */
export class Et2ButtonTimestamper extends Et2Button
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Which field to place the timestamp in
			 */
			target: {
				type: String
			},
			/**
			 * Format for the timestamp.  User is always after.
			 */
			format: {
				type: String
			},
			/**
			 * Timezone.  Default is user time.
			 */
			timezone: {
				type: String
			},
			/**
			 * Icon to use, default "timestamp"
			 */
			image: {
				type: String
			}
		}
	}

	constructor(...args : any[])
	{
		super(...args);

		// Property default values
		this.image = 'timestamp';
		this.noSubmit = true;

		this.onclick = this.stamp.bind(this);
	}

	/**
	 * Overwritten to maintain an internal clicked attribute
	 *
	 * @param _ev
	 * @returns {Boolean}
	 */
	stamp(event: MouseEvent): boolean
	{
		const now = new Date(new Date().toLocaleString('en-US', {
			timeZone: this.timezone || this.egw().preference('tz')
		}));
		const format = this.format || this.egw().preference('dateformat') + ' ' + (this.egw().preference("timeformat") === "12" ? "h:ia" : "H:i") + ' ';

		let text = date(format, now);

		// Get properly formatted user name
		const user = '' + parseInt(this.egw().user('account_id'));
		this.egw().accounts('accounts').then((accounts) =>
		{
			const account = accounts.filter((option : SelectOption) => option.value == user)[0];
			text += account.label + ': ';

			const widget = this._get_input(this.target);
			let input = widget.input ? widget.input : widget.getDOMNode();
			if(input.context)
			{
				input = input.get(0);
			}

			let scrollPos = input.scrollTop;
			let browser = ((input.selectionStart || input.selectionStart == "0") ?
						   "standards" : (document["selection"] ? "ie" : false));

			let pos = 0;
			let tinymce = tinyMCE && tinyMCE.EditorManager.get(input.id) || false;

			// Find cursor or selection
			if(browser == "ie")
			{
				input.focus();
				let range = document["selection"].createRange();
				range.moveStart("character", -input.value.length);
				pos = range.text.length;
			}
			else if(browser == "standards")
			{
				pos = input.selectionStart;
			}

			// If on a tab, switch to that tab so user can see it
			let tabbox = widget;
			while(tabbox._parent && tabbox.nodeName !== 'ET2-TABBOX')
			{
				tabbox = tabbox._parent;
			}
			if(tabbox.nodeName === 'ET2-TABBOX')
			{
				(<Et2Tabs>tabbox).activateTab(widget);
			}

			// If tinymce, update it
			if(tinymce)
			{
				tinymce.insertContent(text);
			}
			else
			{
				// Insert the text
				let front = (input.value).substring(0, pos);
				let back = (input.value).substring(pos, input.value.length);
				// for webComponent, we need to set the component value too, otherwise the change is lost!
				if(typeof widget.tagName !== 'undefined')
				{
					widget.value = front + text + back;
				}
				input.value = front + text + back;

				// Clean up a little
				pos = pos + text.length;
				if(browser == "ie")
				{
					input.focus();
					let range = document["selection"].createRange();
					range.moveStart("character", -input.value.length);
					range.moveStart("character", pos);
					range.moveEnd("character", 0);
					range.select();
				}
				else if(browser == "standards")
				{
					input.selectionStart = pos;
					input.selectionEnd = pos;
					input.focus();
				}
				input.scrollTop = scrollPos;
				input.focus();
			}
		});
	}

	private _get_input(target)
	{
		let input = null;
		let widget = null;

		if (typeof target == 'string')
		{
			widget = this.getRoot().getWidgetById(target);
		}
		else if (target.instanceOf && target.instanceOf(et2_IInput))
		{
			widget = target;
		}
		else if(typeof target == 'string' && target.indexOf('#') < 0 && jQuery('#'+this.target).is('input'))
		{
			input = this.target;
		}
		if(widget)
		{
			return widget;
		}
		if(input?.context)
		{
			input = input.get(0);
		}
		return input;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Button is a LitElement
customElements.define("et2-button-timestamp", Et2ButtonTimestamper);