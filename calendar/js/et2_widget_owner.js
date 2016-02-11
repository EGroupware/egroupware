/* 
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package 
 * @subpackage 
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


"use strict";

/*egw:uses
	et2_widget_taglist;
*/

/**
 * Tag list widget customised for calendar owner, which can be a user
 * account or group, or an entry from almost any app, or an email address
 *
 * A cross between auto complete, selectbox and chosen multiselect
 *
 * Uses MagicSuggest library
 * @see http://nicolasbize.github.io/magicsuggest/
 * @augments et2_selectbox
 */
var et2_calendar_owner = et2_taglist_email.extend(
{
	attributes: {
		"autocomplete_url": {
			"default": "calendar.calendar_uiforms.ajax_owner.etemplate"
		},
		"autocomplete_params": {
			"name": "Autocomplete parameters",
			"type": "any",
			"default": {},
			"description": "Extra parameters passed to autocomplete URL.  It should be a stringified JSON object."
		},
		allowFreeEntries: {
			"default": false,
			ignore: true
		},
		select_options: {
			"type": "any",
			"name": "Select options",
			// Set to empty object to use selectbox's option finding
			"default": {},
			"description": "Internally used to hold the select options."
		},
	},

	// Allows sub-widgets to override options to the library
	lib_options: {
		groupBy: 'app',
		minChars: 2,
		// This option will also expand when the selection is changed
		// via code, which we do not want
		//expandOnFocus: true
		toggleOnClick: true
	},


	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		var widget = this;
		// onChange fired when losing focus, which is different from normal
		this._oldValue = this.taglist.getValue();
		$j(this.taglist)
			.off("selectionchange");
		//	.on('focus', function() {debugger; widget.taglist.expand();});
		
		if(this.options.onchange && typeof this.onchange === 'function')
		{
			$j(this.taglist).on("blur", function() {
				if(widget._oldValue.toString() !== widget.taglist.getValue().toString())
				{
					widget.onchange.call(widget, arguments);
				}
				widget._oldValue = widget.taglist.getValue();
			});
		}
		return true;
	},

	getValue: function()
	{
		if(this.taglist == null) return null;
		return this.taglist.getValue();
	}
});
et2_register_widget(et2_calendar_owner, ["calendar_owner"]);