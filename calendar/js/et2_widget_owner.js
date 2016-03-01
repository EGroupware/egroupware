/*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package
 * @subpackage
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */


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
var et2_calendar_owner = (function(){ "use strict"; return et2_taglist_email.extend(
{
	attributes: {
		"autocomplete_url": {
			"default": "calendar_owner_etemplate_widget::ajax_owner"
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
		}
	},

	// Allows sub-widgets to override options to the library
	lib_options: {
		autoSelect: false,
		groupBy: 'app',
		minChars: 2,
		selectFirst: true,
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
		this.$taglist
			.on('focus', function() {widget.taglist.expand();})
			// Since not using autoSelect, avoid some errors with selection starting
			// with the group
			.on('load expand', function() {
				window.setTimeout(function() {
					if(widget && widget.div)
					{
						widget.div.find('.ms-res-item-active')
							.removeClass('ms-res-item-active');
					}
				},1);
			});


		return true;
	},

	getValue: function()
	{
		if(this.taglist == null) return null;
		return this.taglist.getValue();
	}
});}).call(this);
et2_register_widget(et2_calendar_owner, ["calendar-owner"]);