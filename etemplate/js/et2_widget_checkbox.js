/**
 * EGroupware eTemplate2 - JS Checkbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "checkbox" XET-Tag
 * 
 * @augments et2_inputWidget
 */ 
var et2_checkbox = et2_inputWidget.extend(
{
	attributes: {
		"selected_value": {
			"name": "Set value",
			"type": "string",
			"default": "true",
			"description": "Value when checked"
		},
		"unselected_value": {
			"name": "Unset value",
			"type": "string",
			"default": "false",
			"description": "Value when not checked"
		},
		"ro_true": {
			"name": "Read only selected",
			"type": "string",
			"default": "x",
			"description": "What should be displayed when readonly and selected"
		},
		"ro_false": {
			"name": "Read only unselected",
			"type": "string",
			"default": "",
			"description": "What should be displayed when readonly and not selected"
		},
		"value": {
			// Stop framework from messing with value
			"type": "any"
		}
	},

	legacyOptions: ["selected_value", "unselected_value", "ro_true", "ro_false"],

	/**
	 * Constructor
	 * 
	 * @memberOf et2_checkbox
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.input = null;

		this.createInputWidget();

	},

	createInputWidget: function() {
		this.input = $j(document.createElement("input")).attr("type", "checkbox");

		this.input.addClass("et2_checkbox");

		this.setDOMNode(this.input[0]);
	},

	/**
	 * Override default to match against set/unset value
	 */
	set_value: function(_value) {
		if(_value != this.value) {
			if(_value == this.options.selected_value || 
					_value && this.options.selected_value == this.__proto__.attributes.selected_value["default"] &&
					_value != this.options.unselected_value) {
				this.input.attr("checked", "checked");
			} else {
				this.input.removeAttr("checked");
			}
		}
	},

	/**
	 * Override default to return unchecked value
	 */
	getValue: function() {
		if(this.input.attr("checked")) {
			return this.options.selected_value;
		} else {
			return this.options.unselected_value;
		}
	}
});
et2_register_widget(et2_checkbox, ["checkbox"]);

/**
 * et2_checkbox_ro is the dummy readonly implementation of the checkbox
 * @augments et2_checkbox
 */
var et2_checkbox_ro = et2_checkbox.extend(
{
	/**
	 * Ignore unset value
	 */
	attributes: {
		"unselected_value": {
			"ignore": true
		}
	},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_checkbox_ro
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("span"))
			.addClass("et2_checkbox_ro");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		if(_value == this.options.selected_value) {
			this.span.text(this.options.ro_true);
			this.value = _value;
		} else {
			this.span.text(this.options.ro_false);
		}
	}
});
et2_register_widget(et2_checkbox_ro, ["checkbox_ro"]);
