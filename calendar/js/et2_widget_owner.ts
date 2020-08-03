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

import {et2_register_widget, WidgetConfig} from "../../api/js/etemplate/et2_core_widget";
import {ClassWithAttributes} from "../../api/js/etemplate/et2_core_inheritance";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";

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
export class et2_calendar_owner extends et2_taglist_email
{
	static readonly _attributes = {
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
	};

	// Allows sub-widgets to override options to the library
	lib_options = {
		autoSelect: false,
		groupBy: 'app',
		minChars: 2,
		selectFirst: true,
		// This option will also expand when the selection is changed
		// via code, which we do not want
		//expandOnFocus: true
		toggleOnClick: true
	};

	doLoadingFinished()
	{
		super.doLoadingFinished();

		var widget = this;
		// onChange fired when losing focus, which is different from normal
		this._oldValue = this.taglist.getValue();

		return true;
	}

	selectionRenderer(item)
	{
		if(this && this.options && this.options.allowFreeEntries)
		{
			return super.selectionRenderer(item);
		}
		else
		{
			var label = jQuery('<span>').text(item.label);
			if (item.class) label.addClass(item.class);
			if (typeof item.title != 'undefined') label.attr('title', item.title);
			if (typeof item.data != 'undefined') label.attr('data', item.data);
			if (typeof item.icon != 'undefined')
			{
				var wrapper = jQuery('<div>').addClass('et2_taglist_tags_icon_wrapper');
				jQuery('<span/>')
						.addClass('et2_taglist_tags_icon')
						.css({"background-image": "url("+(item.icon.match(/^(http|https|\/)/) ? item.icon : egw.image(item.icon, item.app))+")"})
						.appendTo(wrapper);
				label.appendTo(wrapper);
				return wrapper;
			}
			return label;
		}
	}

	getValue()
	{
		if(this.taglist == null) return null;
		return this.taglist.getValue();
	}

	/**
	 * Override parent to handle our special additional data types (c#,r#,etc.) when they
	 * are not available client side.
	 *
	 * @param {string|string[]} _value array of selected owners, which can be a number,
	 *	or a number prefixed with one character indicating the resource type.
	 */
	set_value(_value)
	{
		super.set_value(_value);

		// If parent didn't find a label, label will be the same as ID so we
		// can find them that way
		let missing_labels = [];
		for(var i = 0; i < this.options.value.length; i++)
		{
			var value = this.options.value[i];
			if(value.id == value.label)
			{
				missing_labels.push(value.id);
			}
		}
		if(Object.keys(missing_labels).length > 0)
		{
			// Proper label was not found by parent - ask directly
			egw.json('calendar_owner_etemplate_widget::ajax_owner',[missing_labels],function(data) {
				for(let owner in data)
				{
					if(!owner || typeof owner == "undefined") continue;
					let idx = this.options.value.find(element => element.id == owner);
					if(idx)
					{
						idx = jQuery.extend(idx, data[owner]);
					}
					// Put it in the list of options for next time
					this.options.select_options.push(data[owner]);
				}
				this.set_value(this.options.value);
			}, this,true,this).sendRequest();
		}

		if(this.taglist)
		{
			this.taglist.clear(true);
			this.taglist.addToSelection(this.options.value,true);
		}
	}
}
et2_register_widget(et2_calendar_owner, ["calendar-owner"]);