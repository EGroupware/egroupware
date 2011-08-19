/**
 * eGroupWare eTemplate2 - JS Checkbox object
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
	et2_inputWidget;
	et2_valueWidget;
*/

/**
 * Class which implements the "checkbox" XET-Tag
 */ 
var et2_checkbox = et2_inputWidget.extend({

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
		}
	},

	init: function(_parent) {
		this._super.apply(this, arguments);

		this.input = null;
		this.id = "";

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
			if(_value == this.selected_value) {
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
			return this.selected_value;
		} else {
			return this.unselected_value;
		}
	}
});

et2_register_widget(et2_checkbox, ["checkbox"]);

/**
 * et2_checkbox_ro is the dummy readonly implementation of the checkbox and radio.
 */
var et2_checkbox_ro = et2_checkbox.extend({

	/**
	 * Ignore unset value
	 */
	attributes: {
		"unselected_value": {
			"ignore": true
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("span"))
			.addClass("et2_checkbox_ro");

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		if(_value == this.selected_value) {
			this.span.text(this.ro_true);
			this.value = _value;
		} else {
			this.span.text(this.ro_false);
		}
	}

});

et2_register_widget(et2_checkbox_ro, ["checkbox_ro", "radio_ro"]);
