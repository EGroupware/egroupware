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

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";

/**
 * Class which implements the "checkbox" XET-Tag
 *
 * @augments et2_inputWidget
 */
export class et2_checkbox extends et2_inputWidget
{
	static readonly _attributes : any = {
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
			"description": "String caption to show for ON status",
			"translate": true
		},
		"toggle_off": {
			"name": "Toggle off caption",
			"type": "string",
			"default": "",
			"description": "String caption to show OFF status",
			"translate": true
		}
	};

	public static readonly legacyOptions : string[]  = ["selected_value", "unselected_value", "ro_true", "ro_false"];
	input : JQuery = null;
	toggle : JQuery = null;
	value : string | boolean;

	/**
	 * Constructor
	 *
	 * @memberOf et2_checkbox
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_checkbox._attributes, _child || {}));
		this.input = null;
		this.createInputWidget();
	}

	createInputWidget()
	{
		this.input = jQuery(document.createElement("input")).attr("type", "checkbox");

		this.input.addClass("et2_checkbox");

		if (this.options.toggle_on || this.options.toggle_off)
		{
			let self = this;
			// checkbox container
			this.toggle = jQuery(document.createElement('span'))
				.addClass('et2_checkbox_slideSwitch')
				.append(this.input);
			// update switch status on change
			this.input.change(function(){
				self.getValue();
				return true;
			});
			// switch container
			let area = jQuery(document.createElement('span')).addClass('slideSwitch_container').appendTo(this.toggle);
			// on span tag
			let on = jQuery(document.createElement('span')).addClass('on').appendTo(area);
			// off span tag
			let off = jQuery(document.createElement('span')).addClass('off').appendTo(area);
			on.text(this.options.toggle_on);
			off.text(this.options.toggle_off);

			// handle a tag
			jQuery(document.createElement('a')).appendTo(area);
			this.setDOMNode(this.toggle[0]);
		}
		else
		{
			this.setDOMNode(this.input[0]);
		}
	}

	/**
	 * Override default to place checkbox before label, if there is no %s in the label
	 *
	 * @param {string} label
	 */
	set_label(label) {
		if(label.length && label.indexOf('%s') < 0)
		{
			label = '%s'+label;
		}
		super.set_label(label);
		jQuery(this.getSurroundings().getWidgetSurroundings()).addClass('et2_checkbox_label');
	}

	/**
	 * Override default to match against set/unset value
	 *
	 * @param {string|boolean} _value
	 */
	set_value(_value : string | boolean)
	{
		// in php, our database storage and et2_checkType(): "0" == false
		if (_value === "0" && this.options.selected_value != "0")
		{
			_value = false;
		}
		if(_value != this.value) {
			if(_value == this.options.selected_value ||
				_value && this.options.selected_value == this.attributes["selected_value"]["default"] &&
				_value != this.options.unselected_value) {
				if (this.options.toggle_on || this.options.toggle_off) this.toggle.addClass('switchOn');
				this.input.prop("checked", true);
			} else {
				this.input.prop("checked", false);
				if (this.options.toggle_on || this.options.toggle_off) this.toggle.removeClass('switchOn');
			}
		}
	}

	/**
	 * Disable checkbox on runtime
	 *
	 * @param {boolean} _ro
	 */
	set_readonly(_ro)
	{
		jQuery(this.getDOMNode()).attr('disabled', _ro);
		this.input.prop('disabled', _ro);
	}

	/**
	 * Override default to return unchecked value
	 */
	getValue()
	{
		if(this.input.prop("checked")) {
			if (this.options.toggle_on || this.options.toggle_off) this.toggle.addClass('switchOn');
			return this.options.selected_value;
		} else {
			if (this.options.toggle_on || this.options.toggle_off) this.toggle.removeClass('switchOn');
			return this.options.unselected_value;
		}
	}

	set_disabled(_value)
	{
		let parentNode = jQuery(this.getDOMNode()).parent();
		if (parentNode[0] && parentNode[0].nodeName == "label" && parentNode.hasClass('.et2_checkbox_label'))
		{
			if (_value)
			{
				parentNode.hide();
			}
			else
			{
				parentNode.show();
			}
		}
		super.set_disabled(_value);
	}
}
et2_register_widget(et2_checkbox, ["checkbox"]);

/**
* et2_checkbox_ro is the dummy readonly implementation of the checkbox
* @augments et2_checkbox
*/
class et2_checkbox_ro extends et2_checkbox implements et2_IDetachedDOM
{
	/**
	 * Ignore unset value
	 */
	static readonly _attributes : any = {
		"unselected_value": {
			"ignore": true
		}
	};

	span : JQuery = null;

	/**
	 * Constructor
	 *
	 * @memberOf et2_checkbox_ro
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_checkbox_ro._attributes, _child || {}));

		this.value = "";
		this.span = jQuery(document.createElement("span"))
			.addClass("et2_checkbox_ro");

		this.setDOMNode(this.span[0]);
	}

	/**
	 * note: checkbox is checked if even there is a value but not only if the _value is only "true"
	 * it's an exceptional validation for cases that we pass non boolean values as checkbox _value
	 *
	 * @param {string|boolean} _value
	 */
	set_value(_value)
	{
		if(_value == this.options.selected_value ||_value && this.options.selected_value == this.attributes["selected_value"]["default"] &&
			_value != this.options.unselected_value) {
			this.span.text(this.options.ro_true);
			this.value = _value;
		} else {
			this.span.text(this.options.ro_false);
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "class");
	}

	getDetachedNodes()
	{
		return [this.span[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		// Update the properties
		if (typeof _values["value"] != "undefined")
		{
			this.span = jQuery(_nodes[0]);
			this.set_value(_values["value"]);
		}

		if (typeof _values["class"] != "undefined")
		{
			_nodes[0].setAttribute("class", _values["class"]);
		}
	}
}
et2_register_widget(et2_checkbox_ro, ["checkbox_ro"]);