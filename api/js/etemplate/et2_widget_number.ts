/**
 * EGroupware eTemplate2 - JS Number object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

/*egw:uses
	et2_widget_textbox;
*/

import {et2_textbox, et2_textbox_ro} from "./et2_widget_textbox";
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_no_init} from "./et2_core_common";

/**
 * Class which implements the "int" and textbox type=float XET-Tags
 *
 * @augments et2_textbox
 */
export class et2_number extends et2_textbox
{
	static readonly	_attributes : any = {
		"value": {
			"type": "float"
		},
		// Override default width, numbers are usually shorter
		"size": {
			"default": 5
		},
		"min": {
			"name": "Minimum",
			"type": "any",
			"default": et2_no_init,
			"description": "Minimum allowed value"
		},
		"max": {
			"name": "Maximum",
			"type": "any",
			"default": et2_no_init,
			"description": "Maximum allowed value"
		},
		"step": {
			"name": "step value",
			"type": "integer",
			"default": et2_no_init,
			"description": "Step attribute specifies the interval between legal numbers"
		},
		"precision": {
			// TODO: Implement this in some nice way other than HTML5's step attribute
			"name": "Precision",
			"type": "integer",
			"default": et2_no_init,
			"description": "Allowed precision - # of decimal places",
			"ignore": true
		}
	};

	min : number = null;
	max : number = null;
	step : number = null;
	/**
	 * Constructor
	 *
	 * @memberOf et2_number
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object) {
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_number._attributes, _child || {}));
	}

	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		if (typeof _attrs.validator == 'undefined')
		{
			_attrs.validator = _attrs.type == 'float' ? '/^-?[0-9]*[,.]?[0-9]*$/' : '/^-?[0-9]*$/';
		}
	}

	/**
	 * Clientside validation using regular expression in "validator" attribute
	 *
	 * @param {array} _messages
	 */
	isValid(_messages)
	{
		let ok = true;
		// if we have a html5 validation error, show it, as this.input.val() will be empty!
		if (this.input && this.input[0] && (<HTMLInputElement><unknown>this.input[0]).validationMessage && !(<HTMLInputElement><unknown>this.input[0]).validity.stepMismatch)
		{
			_messages.push((<HTMLInputElement><unknown>this.input[0]).validationMessage);
			ok = false;
		}
		return super.isValid(_messages) && ok;
	}

	createInputWidget()
	{
		this.input = jQuery(document.createElement("input"));
		this.input.attr("type", "number");
		this.input.addClass("et2_textbox");
		// bind invalid event to change, to trigger our validation
		this.input.on('invalid', jQuery.proxy(this.change, this));
		if (this.options.onkeypress && typeof this.options.onkeypress == 'function')
		{
			var self = this;
			this.input.keypress(function(_ev)
			{
				return self.options.onkeypress.call(this, _ev, self);
			});
		}
		this.setDOMNode(this.input[0]);
	}

	/**
	 * Set input widget size
	 *
	 * Overwritten from et2_textbox as input type=number seems to ignore size,
	 * therefore we set width in em instead, if not et2_fullWidth given.
	 *
	 * @param _size Rather arbitrary size units, approximately characters
	 */
	set_size(_size)
	{
		if (typeof _size != 'undefined' && _size != this.input.attr("size"))
		{
			this.size = _size;
			this.input.attr("size", this.size);

			if (typeof this.options.class == 'undefined' || this.options.class.search('et2_fullWidth') == -1)
			{
				this.input.css('width', _size+'em');
			}
		}
	}

	set_min(_value)
	{
		this.min = _value;
		if(this.min == null) {
			this.input.removeAttr("min");
		} else {
			this.input.attr("min",this.min);
		}
	}

	set_max(_value)
	{
		this.max = _value;
		if(this.max == null) {
			this.input.removeAttr("max");
		} else {
			this.input.attr("max",this.max);
		}
	}

	set_step(_value : number)
	{
		this.step = _value;
		if(this.step == null) {
			this.input.removeAttr("step");
		} else {
			this.input.attr("step",this.step);
		}
	}
}
et2_register_widget(et2_number, ["int", "integer", "float", "old-int"]);

/**
 * Extend read-only to tell it to ignore special attributes, which
 * would cause warnings otherwise
 * @augments et2_textbox_ro
 * @class
 */
export class et2_number_ro extends et2_textbox_ro
{
	static readonly _attributes : any = {
		min: { ignore: true},
		max: { ignore: true},
		precision: {
			name: "Precision",
			type: "integer",
			default: et2_no_init,
			description: "Allowed precision - # of decimal places",
			ignore: true
		},
		value: { type: "float" }
	};

	set_value(_value)
	{
		if (typeof this.options.precision != 'undefined' && ""+_value != "")
		{
			_value = parseFloat(_value).toFixed(this.options.precision);
		}
		super.set_value(_value);
	}
}
et2_register_widget(et2_number_ro, ["int_ro", "integer_ro", "float_ro"]);

