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

/**
 * Class which implements the "button-timestamper" XET-Tag
 *
 * Clicking the button puts the current time and current user at the end of
 * the provided field.
 *
 * @augments et2_button
 */
var et2_timestamper = (function(){ "use strict"; return et2_button.extend([],
{
	attributes: {
		target: {
			name: "Target field",
			type: "string",
			default: et2_no_init,
			description: "Which field to place the timestamp in"
		},
		image: {
			default: "timestamp"
		},
		background_image: {
			default: true
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_button
	 */
	init: function() {
		this._super.apply(this, arguments);
	},

	/**
	 * Overwritten to maintain an internal clicked attribute
	 *
	 * @param _ev
	 * @returns {Boolean}
	 */
	click: function(_ev) {
		// ignore click on readonly button
		if (this.options.readonly) return false;

		this._insert_text();

		return false;
	},

	_insert_text: function() {
		var text = "";
		var now = new Date();
		text += date(egw.preference('dateformat') + ' ' + (egw.preference("timeformat") === "12" ? "h:ia" : "H:i")+' ',now);

		// Get properly formatted user name
		var user = parseInt(egw.user('account_id'));
		var accounts = egw.accounts('accounts');
		for(var j = 0; j < accounts.length; j++)
		{
			if(accounts[j].value === user)
			{
				text += accounts[j].label;
				break;
			}
		}
		text += ': ';

		var input = this._get_input(this.target);
		var scrollPos = input.scrollTop;
		var browser = ((input.selectionStart || input.selectionStart == "0") ?
			"standards" : (document.selection ? "ie" : false ) );

		var pos = 0;
		var CK = CKEDITOR && CKEDITOR.instances[input.id] || false;

		// Find cursor or selection
		if (browser == "ie")
		{
			input.focus();
			var range = document.selection.createRange();
			range.moveStart ("character", -input.value.length);
			pos = range.text.length;
		}
		else if (browser == "standards")
		{
			pos = input.selectionStart;
		};

		// If CKEDitor, update it
		if(CKEDITOR && CKEDITOR.instances[input.id])
		{
			CKEDITOR.instances[input.id].insertText(text);
			window.setTimeout(function() {
				CKEDITOR.instances[input.id].focus();
			}, 10);
		}
		else
		{
			// Insert the text
			var front = (input.value).substring(0, pos);
			var back = (input.value).substring(pos, input.value.length);
			input.value = front+text+back;

			// Clean up a little
			pos = pos + text.length;
			if (browser == "ie") {
				input.focus();
				var range = document.selection.createRange();
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
	},

	_get_input: function _get_input(target)
	{
		var input = null;
		var widget = null;
		if(jQuery('#'+this.target).is('input'))
		{
			input = this.target;
		}
		else if (typeof target == 'string')
		{
			var widget = this.getRoot().getWidgetById(target);
		}
		else if (target.instanceOf && target.instanceOf(et2_IInput))
		{
			widget = target;
		}
		if(widget)
		{
			input = widget.input ? widget.input : widget.getDOMNode();
		}
		if(input.context)
		{
			input = input.get(0);
		}
		return input;
	}
});}).call(this);
et2_register_widget(et2_timestamper, ["button-timestamp", "timestamper"]);