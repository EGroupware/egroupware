/**
 * EGroupware eTemplate2 - JS Timestamp button object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2017
 */

/*egw:uses
	et2_button;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_button} from "./et2_widget_button";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
 * Class which implements the "button-timestamper" XET-Tag
 *
 * Clicking the button puts the current time and current user at the end of
 * the provided field.
 *
 * @augments et2_button
 */
class et2_timestamper extends et2_button
{
	static readonly _attributes : any = {
		target: {
			name: "Target field",
			type: "string",
			default: et2_no_init,
			description: "Which field to place the timestamp in"
		},
		format: {
			name: "Time format",
			type: "string",
			default: et2_no_init,
			description: "Format for the timestamp.  User is always after."
		},
		timezone: {
			name: "Timezone",
			type: "string",
			default: et2_no_init,
			description: "Timezone.  Default is user time."
		},
		statustext: {
			default: "Insert timestamp into description field"
		},
		image: {
			default: "timestamp"
		},
		background_image: {
			default: true
		}
	};
	target : string;

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_timestamper._attributes, _child || {}));
		jQuery(this.getDOMNode()).addClass('et2_timestamper');
	}

	/**
	 * Overwritten to maintain an internal clicked attribute
	 *
	 * @param _ev
	 * @returns {Boolean}
	 */
	click(_ev) {
		// ignore click on readonly button
		if (this.options.readonly) return false;

		this._insert_text();

		return false;
	}

	private _insert_text() {
		let text = "";
		let now = new Date(new Date().toLocaleString('en-US', {
			timeZone: this.options.timezone ? this.options.timezone : egw.preference('tz')
		}));
		let format = (this.options.format ?
			this.options.format :
			egw.preference('dateformat') + ' ' + (egw.preference("timeformat") === "12" ? "h:ia" : "H:i"))+' ';

		text += date(format, now);

		// Get properly formatted user name
		let user = parseInt(egw.user('account_id'));
		let accounts = egw.accounts('accounts');
		for(let j = 0; j < accounts.length; j++)
		{
			if(accounts[j]["value"] === user)
			{
				text += accounts[j]["label"];
				break;
			}
		}
		text += ': ';

		let widget = this._get_input(this.target);
		let input = widget.input ? widget.input : widget.getDOMNode();
		if(input.context)
		{
			input = input.get(0);
		}

		let scrollPos = input.scrollTop;
		let browser = ((input.selectionStart || input.selectionStart == "0") ?
			"standards" : (document["selection"] ? "ie" : false ) );

		let pos = 0;
		let tinymce = tinyMCE && tinyMCE.EditorManager.get(input.id) || false;

		// Find cursor or selection
		if (browser == "ie")
		{
			input.focus();
			let range = document["selection"].createRange();
			range.moveStart ("character", -input.value.length);
			pos = range.text.length;
		}
		else if (browser == "standards")
		{
			pos = input.selectionStart;
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
			input.value = front+text+back;

			// Clean up a little
			pos = pos + text.length;
			if (browser == "ie") {
				input.focus();
				let range = document["selection"].createRange();
				range.moveStart ("character", -input.value.length);
				range.moveStart ("character", pos);
				range.moveEnd ("character", 0);
				range.select();
			}
			else if (browser == "standards") {
				input.selectionStart = pos;
				input.selectionEnd = pos;
				input.focus();
			}
			input.scrollTop = scrollPos;
			input.focus();
		}
		// If on a tab, switch to that tab so user can see it
		let tab = widget;
		while(tab._parent && tab._type != 'tabbox')
		{
			tab = tab._parent;
		}
		if (tab._type == 'tabbox') tab.activateTab(widget);
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
et2_register_widget(et2_timestamper, ["button-timestamp", "timestamper"]);
