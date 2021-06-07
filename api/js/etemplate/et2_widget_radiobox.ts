/**
 * EGroupware eTemplate2 - JS Radiobox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
*/
import {et2_inputWidget} from "./et2_core_inputWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_valueWidget} from './et2_core_valueWidget';
import {et2_IDetachedDOM} from "./et2_core_interfaces";

/**
 * Class which implements the "radiobox" XET-Tag
 *
 * A radio button belongs to same group by giving all buttons of a group same id!
 *
 * set_value iterates over all of them and (un)checks them depending on given value.
 *
 * @augments et2_inputWidget
 */
export class et2_radiobox extends et2_inputWidget
{
	static readonly _attributes : any = {
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
	};

	public static readonly legacyOptions : string[]  = ["set_value", "ro_true", "ro_false"];
	input : JQuery = null;
	id : string = "";

	/**
	 * Constructor
	 *
	 * @memberOf et2_radiobox
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object) {
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_radiobox._attributes, _child || {}));
		this.createInputWidget();
	}

	transformAttributes(_attrs) {
		super.transformAttributes(_attrs);

		let readonly = this.getArrayMgr('readonlys').getEntry(this.id);
		if(readonly && readonly.hasOwnProperty(_attrs.set_value))
		{
			_attrs.readonly = readonly[_attrs.set_value];
		}
	}

	createInputWidget() {
		this.input = jQuery(document.createElement("input"))
			.val(this.options.set_value)
			.attr("type", "radio")
			.attr("disabled", this.options.readonly);

		this.input.addClass("et2_radiobox");

		this.setDOMNode(this.input[0]);
	}

	/**
	 * Overwritten to set different DOM level ids by appending set_value
	 *
	 * @param _id
	 */
	set_id(_id)
	{
		super.set_id(_id);

		this.dom_id = this.dom_id.replace('[]', '')+'-'+this.options.set_value;
		if (this.input) this.input.attr('id', this.dom_id);
	}

	/**
	 * Default for radio buttons is label after button
	 *
	 * @param _label String New label for radio button.  Use %s to locate the radio button somewhere else in the label
	 */
	set_label(_label)
	{
		if(_label.length > 0 && _label.indexOf('%s')==-1)
		{
			_label = '%s'+_label;
		}
		super.set_label(_label);
	}

	/**
	 * Override default to match against set/unset value AND iterate over all siblings with same id
	 *
	 * @param {string} _value
	 */
	set_value(_value)
	{
		this.getRoot().iterateOver(function(radio)
		{
			if (radio.id == this.id)
			{
				radio.input.prop('checked', _value == radio.options.set_value);
			}
		}, this, et2_radiobox);
	}

	/**
	 * Override default to iterate over all siblings with same id
	 *
	 * @return {string}
	 */
	getValue()
	{
		let val = this.options.value;	// initial value, when form is loaded
		let values = [];
		this.getRoot().iterateOver(function(radio)
		{
			values.push(radio.options.set_value);
			if (radio.id == this.id && radio.input && radio.input.prop('checked'))
			{
				val = radio.options.set_value;
			}
		}, this, et2_radiobox);

		return val && val.indexOf(values) ? val : null;
	}

	/**
	 * Overridden from parent so if it's required, only 1 in a group needs a value
	 *
	 * @param {array} messages
	 * @returns {Boolean}
	 */
	isValid(messages) {
		let ok = true;

		// Check for required
		if (this.options && this.options.needed && !this.options.readonly && !this.disabled &&
			(this.getValue() == null || this.getValue().valueOf() == ''))
		{
			if(jQuery.isEmptyObject(this.getInstanceManager().getValues(this.getInstanceManager().widgetContainer)[this.id.replace('[]', '')]))
			{
				messages.push(this.egw().lang('Field must not be empty !!!'));
				ok = false;
			}
		}
		return ok;
	}

	/**
	 * Set radio readonly attribute.
	 *
	 * @param _readonly Boolean
	 */
	set_readonly(_readonly)
	{
		this.options.readonly = _readonly;
		this.getRoot().iterateOver(function(radio)
		{
			if (radio.id == this.id)
			{
				radio.input.prop('disabled',_readonly);
			}
		}, this, et2_radiobox);
	}

}
et2_register_widget(et2_radiobox, ["radio"]);

/**
 * @augments et2_valueWidget
 */
export class et2_radiobox_ro extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
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
			"type": "string"
		}
	};

	public static readonly legacyOptions : string[] = ["set_value", "ro_true", "ro_false"];
	value : string = "";
	span : JQuery = null;

	/**
	 * Constructor
	 *
	 * @memberOf et2_radiobox_ro
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_radiobox_ro._attributes, _child || {}));
		this.span = jQuery(document.createElement("span"))
			.addClass("et2_radiobox");

		this.setDOMNode(this.span[0]);
	}

	/**
	 * Override default to match against set/unset value
	 *
	 * @param {string} _value
	 */
	set_value(_value) {
		this.value = _value;
		if(_value == this.options.set_value) {
			this.span.text(this.options.ro_true);
		} else {
			this.span.text(this.options.ro_false);
		}
	}

	set_label(_label) {
		// no label for ro radio, we show label of checked option as content, unless it's x
		// then we need the label for things to make sense
		if(this.options.ro_true == "x")
		{
			return super.set_label(_label);
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
		// Show label in nextmatch instead of just x
		this.options.ro_true = this.options.label;
		_attrs.push("value");
	}

	getDetachedNodes()
	{
		return [this.span[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.span = jQuery(_nodes[0]);
		this.set_value(_values["value"]);
	}
}
et2_register_widget(et2_radiobox_ro, ["radio_ro"]);


/**
 * A group of radio buttons
 *
 * @augments et2_valueWidget
 */
export class et2_radioGroup extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
			"description": "The label is displayed above the list of radio buttons. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			"translate": true
		},
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
		},
		"needed": {
			"name":	"Required",
			"default": false,
			"type": "boolean",
			"description": "If required, the user must select one of the options before the form can be submitted"
		}
	};

	node : JQuery = null;
	value : any = null;
	/**
	 * Constructor
	 *
	 * @param parent
	 * @param attrs
	 * @memberOf et2_radioGroup
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_radioGroup._attributes, _child || {}));
		this.node = jQuery(document.createElement("div"))
			.addClass("et2_vbox")
			.addClass("et2_box_widget");
		if(this.options.needed)
		{
			// This isn't strictly allowed, but it works
			this.node.attr("required","required");
		}
		this.setDOMNode(this.node[0]);

		// The supported widget classes array defines a whitelist for all widget
		// classes or interfaces child widgets have to support.
		this.supportedWidgetClasses = [et2_radiobox,et2_radiobox_ro];
	}

	set_value(_value)
	{
		this.value = _value;
		for (let i = 0; i < this._children.length; i++)
		{
			let radio = this._children[i];
			radio.set_value(_value);
		}
	}

	getValue()
	{
		return jQuery("input:checked", this.getDOMNode()).val();
	}

	/**
	 * Set a bunch of radio buttons
	 *
	 * @param {object} _options object with value: label pairs
	 */
	set_options(_options)
	{
		// Call the destructor of all children
		for (let i = this._children.length - 1; i >= 0; i--)
		{
			this._children[i].destroy();
		}
		this._children = [];
		// create radio buttons for each option
		for(let key in _options)
		{
			let attrs = {
				// Add index so radios work properly
				"id": (this.options.readonly ? this.id : this.id + "[" +  "]"),
				set_value: key,
				label: _options[key],
				ro_true: this.options.ro_true,
				ro_false: this.options.ro_false,
				readonly: this.options.readonly
			};
			if(typeof _options[key] === 'object' && _options[key].label)
			{
				attrs.set_value = _options[key].value;
				attrs.label = _options[key].label;
			}
			// Can't have a required readonly, it will warn & be removed later, so avoid the warning
			if(attrs.readonly === false)
			{
				attrs['needed'] = this.options.needed;
			}
			et2_createWidget("radio", attrs, this);
		}
		this.set_value(this.value);
	}

	/**
	 * Set a label on the group of radio buttons
	 *
	 * @param {string} _value
	 */
	set_label(_value) {
		// Abort if ther was no change in the label
		if (_value == this.label)
		{
			return;
		}

		if (_value)
		{
			// Create the label container if it didn't exist yet
			if (this._labelContainer == null)
			{
				this._labelContainer = jQuery(document.createElement("label"));
				this.getSurroundings().insertDOMNode(this._labelContainer[0]);
			}

			// Clear the label container.
			this._labelContainer.empty();

			// Create the placeholder element and set it
			var ph = document.createElement("span");
			this.getSurroundings().setWidgetPlaceholder(ph);

			this._labelContainer
				.append(document.createTextNode(_value))
				.append(ph);
		}
		else
		{
			// Delete the labelContainer from the surroundings object
			if (this._labelContainer)
			{
				this.getSurroundings().removeDOMNode(this._labelContainer[0]);
			}
			this._labelContainer = null;
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 * This doesn't need to be implemented.
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
	 *
	 * @param {object} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
	}

	getDetachedNodes()
	{
		return [this.getDOMNode()];
	}

	setDetachedAttributes(_nodes, _values)
	{
	}

}
// No such tag as 'radiogroup', but it needs something
et2_register_widget(et2_radioGroup, ["radiogroup"]);

