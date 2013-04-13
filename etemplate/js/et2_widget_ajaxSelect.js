/**

 * EGroupware eTemplate2 - JS Ajax select / auto complete object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	jquery.jquery-ui;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Using AJAX, this widget allows a type-ahead find similar to a ComboBox, where as the user enters information,
 * a drop-down box is populated with the n closest matches.  If the user clicks on an item in the drop-down, that
 * value is selected.
 * n is the maximum number of results set in the user's preferences.
 * The user is restricted to selecting values in the list.
 * This widget can get data from any function that can provide data to a nextmatch widget.
 * @augments et2_inputWidget
 */ 
var et2_ajaxSelect = et2_inputWidget.extend(
{
	attributes: {
		'get_rows': {
			"name": "Data source",
			"type": "any",
			"default": "",
			"description": "Function to get search results."
		},
		'get_title': {
			"name": "Title function",
			"type": "any",
			"default": "",
			"description": "Function to get title for selected entry.  Used when closed, and if no template is given."
		},
		'id_field': {
			"name": "Result ID field",
			"type": "string",
			"default": "",
			"description": "Which key in result sub-array to look for row ID.  If omitted, the key for the row will be used."
		},
		'template': {
			"name": "Row template",
			"type": "string",
			"default": "",
			"description": "ID of the template to use to display rows.  If omitted, title will be shown for each result."
		},
		'filter': {
			"name": "Filter",
			"type": "string",
			"default": "",
			"description": "Apply filter to search results.  Same as nextmatch."
		},
		'filter2': {
			"name": "Filter 2",
			"type": "string",
			"default": "",
			"description": "Apply filter to search results.  Same as nextmatch."
		},
		'link': {
			"name": "Read only link",
			"type": "boolean",
			"default": "true",
			"description": "If readonly, widget will be text.  If link is set, widget will be a link."
		},
		'icon': {
			"name": "Icon",
			"type": "string",
			"default": "",
			"description": "Prevent all from looking the same.  Use an icon."
		},

		// Pass by code only
		'values': {
			"name": "Values",
			"type": "any",
			"default": {},
			"description": "Specify the available options.  Use this, or Data source."
		}
	},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_ajaxSelect
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		this.createInputWidget();
	},

	createInputWidget: function() {
		this.input = $j(document.createElement("input"));

		this.input.addClass("et2_textbox");

		this.setDOMNode(this.input[0]);
	},

	getValue: function()
	{
		if(this.options.blur && this.input.val() == this.options.blur) return "";
		return this._super.apply(this, arguments);
	},

	set_blur: function(_value) {
		if(_value) {
			this.input.attr("placeholder", _value + "!");	// HTML5
			if(!this.input[0].placeholder) {
				// Not HTML5
				if(this.input.val() == "") this.input.val(this.options.blur);
				this.input.focus(this,function(e) {
					if(e.data.input.val() == e.data.options.blur) e.data.input.val("");
				}).blur(this, function(e) {
					if(e.data.input.val() == "") e.data.input.val(e.data.options.blur);
				});
			}
		} else {
			this.input.removeAttr("placeholder");
		}
	}
});
et2_register_widget(et2_ajaxSelect, ["ajax_select"]);

/**
 * et2_textbox_ro is the dummy readonly implementation of the textbox.
 * @augments et2_valueWidget
 */
var et2_ajaxSelect_ro = et2_valueWidget.extend([et2_IDetachedDOM], 
{
	/**
	 * Ignore all more advanced attributes.
	 */
	attributes: {
		"multiline": {
			"ignore": true
		}
	},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_ajaxSelect_ro
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("span"));

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		this.value = _value;

		if(!_value) _value = "";
		this.span.text(_value);
	},
	/**
         * Code for implementing et2_IDetachedDOM
         */
        getDetachedAttributes: function(_attrs)
        {
                _attrs.push("value");
        },

        getDetachedNodes: function()
        {
                return [this.span[0]];
        },

        setDetachedAttributes: function(_nodes, _values)
        {
		this.span = jQuery(_nodes[0]);
		if(typeof _values["value"] != 'undefined')
		{
			this.set_value(_values["value"]);
		}
	}
});
et2_register_widget(et2_ajaxSelect_ro, ["ajax_select_ro"]);

