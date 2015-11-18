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
			"default": "",
			"description": "Value when not checked"
		},
		"ro_true": {
			"name": "Read only selected",
			"type": "string",
			"default": "X ",
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
		},
		"toggle_on": {
			"name": "Toggle on caption",
			"type": "string",
			"default": "",
			"description": "String caption to show for ON status"
		},
		"toggle_off": {
			"name": "Toggle off caption",
			"type": "string",
			"default": "",
			"description": "String caption to show OFF status"
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
	
		if (this.options.toggle_on || this.options.toggle_off)
		{
			var self = this;
			// checkbox container
			this.toggle = $j(document.createElement('span'))
					.addClass('et2_checkbox_slideSwitch')
					.append(this.input);
			// update switch status on change
			this.input.change(function(){
					self.getValue();
					return true;	
			});
			// switch container
			var area = jQuery(document.createElement('span')).addClass('slideSwitch_container').appendTo(this.toggle);
			// on span tag
			var on = jQuery(document.createElement('span')).addClass('on').appendTo(area);
			// off span tag
			var off = jQuery(document.createElement('span')).addClass('off').appendTo(area);
			on.text(this.options.toggle_on);
			off.text(this.options.toggle_off);
			
			// handle a tag
			var handle = jQuery(document.createElement('a')).appendTo(area);
			this.setDOMNode(this.toggle[0]);
		}
		else
		{
			this.setDOMNode(this.input[0]);
		}
		
	},

	/**
	 * Override default to place checkbox before label, if there is no %s in the label
	 */
	set_label: function(label) {
		if(label.length && label.indexOf('%s') < 0)
		{
			label = '%s'+label;
		}
		this._super.apply(this, [label]);
	},
	/**
	 * Override default to match against set/unset value
	 */
	set_value: function(_value) {
		if(_value != this.value) {
			if(_value == this.options.selected_value ||
					_value && this.options.selected_value == this.attributes.selected_value["default"] &&
					_value != this.options.unselected_value) {
				if (this.options.toggle_on || this.options.toggle_off) this.input.prop("checked", true);
				this.toggle.addClass('switchOn');
			} else {
				this.input.prop("checked", false);
				if (this.options.toggle_on || this.options.toggle_off) this.toggle.removeClass('switchOn');
			}
		}
	},

	/**
	 * Disable checkbox on runtime
	 *
	 * @param {boolean} _ro
	 */
	set_readonly: function(_ro)
	{
		jQuery(this.getDOMNode()).attr('disabled', _ro);
	},

	/**
	 * Override default to return unchecked value
	 */
	getValue: function() {
		if(this.input.prop("checked")) {
			if (this.options.toggle_on || this.options.toggle_off) this.toggle.addClass('switchOn');
			return this.options.selected_value;
		} else {
			if (this.options.toggle_on || this.options.toggle_off) this.toggle.removeClass('switchOn');
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

	/**
	 * note: checkbox is checked if even there is a value but not only if the _value is only "true"
	 * it's an exceptional validation for cases that we pass non boolean values as checkbox _value
	 */
	set_value: function(_value) {
		if(_value == this.options.selected_value ||_value && this.options.selected_value == this.attributes.selected_value["default"] &&
					_value != this.options.unselected_value) {
			this.span.text(this.options.ro_true);
			this.value = _value;
		} else {
			this.span.text(this.options.ro_false);
		}
	}
});
et2_register_widget(et2_checkbox_ro, ["checkbox_ro"]);
