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
*/

/**
 * Class which implements the "checkbox" XET-Tag
 */ 
var et2_checkbox = et2_inputWidget.extend({

	attributes: {
		"set_value": {
			"name": "Set value",
			"type": "string",
			"default": "true",
			"description": "Value when checked"
		},
		"unset_value": {
			"name": "Unset value",
			"type": "string",
			"default": "false",
			"description": "Value when not checked"
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
			if(_value == this.set_value) {
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
			return this.set_value;
		} else {
			return this.unset_value;
		}
	}
});

et2_register_widget(et2_checkbox, ["checkbox"]);
