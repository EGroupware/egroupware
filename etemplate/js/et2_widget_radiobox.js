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
		this.input = $j(document.createElement("input"))
			.val(this.options.set_value)
			.attr("type", "radio");

		this.input.addClass("et2_radiobox");

		this.setDOMNode(this.input[0]);
	},

	set_name: function(_name) {
		if(_name.substr(_name.length-2) != "[]")
		{
			_name += "[]";
		}
		this.input.attr("name", _name);
	},

	/**
	 * Override default to match against set/unset value
	 */
	set_value: function(_value) {
		if(_value == this.options.set_value) {
			this.input.attr("checked", "checked");
		} else {
			this.input.removeAttr("checked");
		}
	},

	/**
	 * Override default to return unchecked value
	 */
	getValue: function() {
		if(jQuery("input:checked", this._parent.getDOMNode()).val() == this.options.set_value) {
			return this.options.set_value;
		}
		return null;
	}
});

et2_register_widget(et2_radiobox, ["radio"]);

var et2_radiobox_ro = et2_valueWidget.extend([et2_IDetachedDOM], {

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
		},
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
		}
	},
	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("span"))
			.addClass("et2_radiobox");

		this.setDOMNode(this.span[0]);
	},

	/**
	 * Override default to match against set/unset value
	 */
	set_value: function(_value) {
		if(_value == this.options.set_value) {
			this.span.text(this.options.ro_true);
		} else {
			this.span.text(this.options.ro_false);
		}
	},
	/**
	 * Code for implementing et2_IDetachedDOM
	 */
	getDetachedAttributes: function(_attrs)
	{
		// Show label in nextmatch instead of just x
		this.options.ro_true = this.options.label;
		_attrs.push("value");
	},

	getDetachedNodes: function()
	{
		return [this.span[0]];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		this.span = jQuery(_nodes[0]);
		this.set_value(_values["value"]);
	}
});

et2_register_widget(et2_radiobox_ro, ["radio_ro"]);


/**
 * A group of radio buttons
 */ 
var et2_radioGroup = et2_box.extend({

	attributes: {
		"value": {
			"name": "Value",
			"type": "string",
			"default": "true",
			"description": "Value for each radio button"
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
		"options": {
			"name": "Radio options",
			"type": "any",
			"default": {},
			"description": "Options for radio buttons.  Should be {value: label, ...}"
		}
	},

	createNamespace: false,

	init: function(parent, attrs) {
		attrs.type = "vbox";
		this._super.apply(this, arguments);
	},

	set_value: function(_value) {
		this.value = _value;
		for (var i = 0; i < this._children.length; i++)
                {
                        var radio = this._children[i];
			radio.set_value(_value);
                }
	},

	/**
	 * Set a bunch of radio buttons
	 * Options should be {value: label, ...}
	 */
	set_options: function(_options) {
		for(var key in _options)
		{
			var attrs = {
				// Add index so radios work properly
				"id": (this.options.readonly ? this.id : this.id + "[" +  "]"),
				set_value: key,
				label: _options[key],
				ro_true: this.options.ro_true,
				ro_false: this.options.ro_false,
				readonly: this.options.readonly
			}
			var radio = et2_createWidget("radio", attrs, this);
//			radio.set_name(this.id);
		}
		this.set_value(this.value);
	}
});
// No such tag as 'radiogroup', but it needs something
et2_register_widget(et2_radioGroup, ["radiogroup"]);
