/**
 * EGroupware eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_interfaces;
	et2_core_valueWidget;
*/

import {et2_no_init} from "./et2_core_common";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from './et2_core_valueWidget'
import {et2_IInput, et2_ISubmitListener} from "./et2_core_interfaces";
import {et2_compileLegacyJS} from "./et2_core_legacyJSFunctions";
// fixing circular dependencies by only importing the type (not in compiled .js)
import type {Et2Tabs} from "./Layout/Et2Tabs/Et2Tabs";

export interface et2_input
{
	getInputNode(): HTMLInputElement | HTMLElement;
}

/**
 * et2_inputWidget derrives from et2_simpleWidget and implements the IInput
 * interface. When derriving from this class, call setDOMNode with an input
 * DOMNode.
 */
export class et2_inputWidget extends et2_valueWidget implements et2_IInput, et2_ISubmitListener, et2_input
{
	static readonly _attributes: any = {
		"needed": {
			"name": "Required",
			"default": false,
			"type": "boolean",
			"description": "If required, the user must enter a value before the form can be submitted"
		},
		"onchange": {
			"name": "onchange",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which is executed when the value changes."
		},
		"onfocus": {
			"name": "onfocus",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which get executed when wiget receives focus."
		},
		"validation_error": {
			"name": "Validation Error",
			"type": "string",
			"default": et2_no_init,
			"description": "Used internally to store the validation error that came from the server."
		},
		"tabindex": {
			"name": "Tab index",
			"type": "integer",
			"default": et2_no_init,
			"description": "Specifies the tab order of a widget when the 'tab' button is used for navigating."
		},
		readonly: {
			name: "readonly",
			type: "boolean",
			"default": false,
			description: "Does NOT allow user to enter data, just displays existing data"
		}
	}

	protected _oldValue: any;
	onchange: Function;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs?: WidgetConfig, _child?: object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_inputWidget._attributes, _child || {}));

		// mark value as not initialised, so set_value can determine if it is necessary to trigger change event
		this._oldValue = et2_no_init;
		this._labelContainer = null;
	}

	destroy()
	{
		var node = this.getInputNode();
		if (node)
		{
			jQuery(node).unbind("change.et2_inputWidget");
			jQuery(node).unbind("focus");
		}

		super.destroy();

		this._labelContainer = null;
	}

	/**
	 * Make sure dirty flag is properly set
	 */
	doLoadingFinished() : boolean | Promise<unknown>
	{
		let result = super.doLoadingFinished();

		this.resetDirty();

		return result;
	}

	/**
	 * Load the validation errors from the server
	 *
	 * @param {object} _attrs
	 */
	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		// Check whether an validation error entry exists
		if (this.id && this.getArrayMgr("validation_errors"))
		{
			var val = this.getArrayMgr("validation_errors").getEntry(this.id);
			if (val)
			{
				_attrs["validation_error"] = val;
			}
		}
	}

	attachToDOM()
	{
		var node = this.getInputNode();
		if (node)
		{
			jQuery(node)
				.off('.et2_inputWidget')
				.bind("change.et2_inputWidget", this, function (e)
				{
					e.data.change.call(e.data, this);
				})
				.bind("focus.et2_inputWidget", this, function (e)
				{
					e.data.focus.call(e.data, this);
				});
		}

		return super.attachToDOM();

//		jQuery(this.getInputNode()).attr("novalidate","novalidate"); // Stop browser from getting involved
//		jQuery(this.getInputNode()).validator();
	}

	detatchFromDOM()
	{
//		if(this.getInputNode()) {
//			jQuery(this.getInputNode()).data("validator").destroy();
//		}
		super.detachFromDOM();
	}

	change(_node, _widget?, _value?)
	{
		var messages = [];
		var valid = this.isValid(messages);

		// Passing false will clear any set messages
		this.set_validation_error(valid ? false : messages);

		if (valid && this.onchange)
		{
			if (typeof this.onchange == 'function')
			{
				// Make sure function gets a reference to the widget
				var args = Array.prototype.slice.call(arguments);
				if (args.indexOf(this) == -1) args.push(this);

				return this.onchange.apply(this, args);
			}
			else
			{
				return (et2_compileLegacyJS(this.options.onchange, this, _node))();
			}
		}
		return valid;
	}

	focus(_node)
	{
		if (typeof this.options.onfocus == 'function')
		{
			// Make sure function gets a reference to the widget
			var args = Array.prototype.slice.call(arguments);
			if (args.indexOf(this) == -1) args.push(this);

			return this.options.onfocus.apply(this, args);
		}
	}

	/**
	 * Set value of widget and trigger for real changes a change event
	 *
	 * First initialisation (_oldValue === et2_no_init) is NOT considered a change!
	 *
	 * @param {string} _value value to set
	 */
	set_value(_value: any | null)
	{
		var node = this.getInputNode();
		if (node)
		{
			jQuery(node).val(_value);
			if (this.isAttached() && this._oldValue !== et2_no_init && this._oldValue !== _value)
			{
				jQuery(node).change();
			}
		}
		this._oldValue = _value;
	}

	set_id(_value)
	{
		this.id = _value;
		this.dom_id = _value && this.getInstanceManager() ? this.getInstanceManager().uniqueId + '_' + this.id : _value;

		// Set the id of the _input_ node (in contrast to the default
		// implementation, which sets the base node)
		var node = this.getInputNode();
		if (node)
		{
			// Unique ID to prevent DOM collisions across multiple templates
			if (_value != "")
			{
				node.setAttribute("id", this.dom_id);
				node.setAttribute("name", _value);
			}
			else
			{
				node.removeAttribute("id");
				node.removeAttribute("name");
			}
		}
	}

	set_needed(_value)
	{
		var node = this.getInputNode();
		if (node)
		{
			if (_value && !this.options.readonly)
			{
				jQuery(node).attr("required", "required");
			}
			else
			{
				node.removeAttribute("required");
			}
		}
	}

	set_validation_error(_value)
	{
		var node = this.getInputNode();
		if (node)
		{
			if (_value === false)
			{
				this.hideMessage();
				jQuery(node).removeClass("invalid");
			}
			else
			{
				this.showMessage(_value, "validation_error");
				jQuery(node).addClass("invalid");

				// If on a tab, switch to that tab so user can see it
				let widget: et2_widget = this;
				while (widget.getParent() && widget.getType() !== 'et2-tabbox')
				{
					widget = widget.getParent();
				}
				if (widget.getType() == 'et2-tabbox') (<Et2Tabs><unknown>widget).activateTab(this);
			}
		}
	}

	/**
	 * Set tab index
	 *
	 * @param {number} index
	 */
	set_tabindex(index)
	{
		jQuery(this.getInputNode()).attr("tabindex", index);
	}

	getInputNode()
	{
		return this.node;
	}

	get_value()
	{
		return this.getValue();
	}

	getValue()
	{
		var node = this.getInputNode();
		if (node)
		{
			var val = jQuery(node).val();

			return val;
		}

		return this._oldValue;
	}

	isDirty()
	{
		let value = this.getValue();
		if (typeof value !== typeof this._oldValue)
		{
			return true;
		}
		if (this._oldValue === value)
		{
			return false;
		}
		switch (typeof this._oldValue)
		{
			case "object":
				if (typeof this._oldValue.length !== "undefined" &&
					this._oldValue.length !== value.length
				)
				{
					return true;
				}
				for (let key in this._oldValue)
				{
					if (this._oldValue[key] !== value[key]) return true;
				}
				return false;
			default:
				return this._oldValue != value;
		}
	}

	resetDirty()
	{
		this._oldValue = this.getValue();
	}

	isValid(messages)
	{
		var ok = true;

		// Check for required
		if (this.options && this.options.needed && !this.options.readonly && !this.disabled &&
			(this.getValue() == null || this.getValue().valueOf() == ''))
		{
			messages.push(this.egw().lang('Field must not be empty !!!'));
			ok = false;
		}
		return ok;
	}

	/**
	 * Called whenever the template gets submitted. We return false if the widget
	 * is not valid, which cancels the submission.
	 *
	 * @param _values contains the values which will be sent to the server.
	 * 	Listeners may change these values before they get submitted.
	 */
	submit(_values)
	{
		var messages = [];
		var valid = this.isValid(messages);

		// Passing false will clear any set messages
		this.set_validation_error(valid ? false : messages);
		return valid;
	}
}