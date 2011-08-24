/**
 * eGroupWare eTemplate2 - JS Radiobox object
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
*/

/**
 * Class which implements the "radiobox" XET-Tag
 */ 
var et2_radiobox = et2_inputWidget.extend({

	attributes: {
		"set_value": {
			"name": "Set value",
			"type": "string",
			"default": "true",
			"description": "Value when selected"
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

	init: function() {
		this._super.apply(this, arguments);

		this.input = null;
		this.id = "";

		this.createInputWidget();

	},

	createInputWidget: function() {
		this.input = $j(document.createElement("input")).attr("type", "radio");

		this.input.addClass("et2_radiobox");

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
		}
	}
});

et2_register_widget(et2_radiobox, ["radio"]);

