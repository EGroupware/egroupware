/**
 * EGroupware eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

/*egw:uses
	et2_core_inputWidget;
*/

import {et2_inputWidget} from "./et2_core_inputWidget";
import {WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";
import {et2_ISubmitListener} from "./et2_core_interfaces";

/**
 * et2_editableWidget derives from et2_inputWidget and adds the ability to start
 * readonly, then turn editable on double-click.  If we decide to do this with
 * more widgets, it should just be merged with et2_inputWidget.
 *
 * @augments et2_inputWidget
 */
export class et2_editableWidget extends et2_inputWidget implements et2_ISubmitListener
{
	static readonly _attributes : any = {
		readonly: {
			name: "readonly",
			type: "string", // | boolean
			default: false,
			description: "If set to 'editable' will start readonly, double clicking will make it editable and clicking out will save",
			ignore: true // todo: not sure why this used to be ignored before migration by default but not anymore
		},
		toggle_readonly: {
			name: "toggle_readonly",
			type: "boolean",
			default: true,
			description: "Double clicking makes widget editable.  If off, must be made editable in some other way."
		},
		save_callback: {
			name: "save_callback",
			type: "string",
			default: et2_no_init,
			description: "Ajax callback to save changed value when readonly is 'editable'.  If not provided, a regular submit is done."
		},
		save_callback_params: {
			name: "readonly",
			type: "string",
			default: et2_no_init,
			description: "Additional parameters passed to save_callback"
		},
		editable_height: {
			name: "Editable height",
			description: "Set height for widget while in edit mode",
			type: "string"
		}
	};

	private _toggle_readonly : boolean;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// 'Editable' really should be boolean for everything else to work
		if(_attrs.readonly && typeof _attrs.readonly === 'string')
		{
			_attrs.readonly = true;
			var toggle_readonly = _attrs.toggle_readonly;
		}
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_editableWidget._attributes, _child || {}));
		if (typeof toggle_readonly != 'undefined') this._toggle_readonly = toggle_readonly;
	}

	destroy()
	{
		let node = this.getInputNode();
		if (node)
		{
			jQuery(node).off('.et2_editableWidget');
		}
		super.destroy();
	}

	/**
	 * Load the validation errors from the server
	 *
	 * @param {object} _attrs
	 */
	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);
	}

	attachToDOM()
	{
		let res = super.attachToDOM();
		let node = this.getDOMNode();
		if (node && this._toggle_readonly)
		{
			jQuery(node)
				.off('.et2_editableWidget')
				.on("dblclick.et2_editableWidget", this, function(e) {
					e.data.dblclick.call(e.data, this);
				})
				.addClass('et2_clickable et2_editable');
		}
		else
		{
			jQuery(node).addClass('et2_editable_readonly');
		}
		return res;
	}

	detatchFromDOM()
	{
		super.detatchFromDOM();
	}

	/**
	 * Handle double click
	 *
	 * Turn widget editable
	 *
	 * @param {DOMNode} _node
	 */
	dblclick(_node?)
	{
		// Turn off readonly
		this.set_readonly(false);

		jQuery('body').on("click.et2_editableWidget", this, function(e) {
			// Make sure click comes from body, not a popup
			if(jQuery.contains(this, e.target) && e.target.type != 'textarea')
			{
				jQuery(this).off("click.et2_editableWidget");
				e.data.focusout.call(e.data, this);
			}
		});
	}

	/**
	 * User clicked somewhere else, save and turn back to readonly
	 *
	 * @param {DOMNode} _node Body node
	 * @returns {et2_core_editableWidgetet2_editableWidget.et2_core_editableWidgetAnonym$0@call;getInstanceManager@call;submit}
	 */
	focusout(_node)
	{
		var value = this.get_value();
		var oldValue = this._oldValue;

		// Change back to readonly
		this.set_readonly(true);

		// No change, do nothing
		if(value == oldValue) return;


		// Submit
		if(this.options.save_callback)
		{
			var params = [value];
			if(this.options.save_callback_params)
			{
				params = params.concat(this.options.save_callback_params.split(','));
			}

			egw.json(this.options.save_callback, params, function() {
			}, this, true, this).sendRequest();
		}
		else
		{
			this.set_value(value);
			return this.getInstanceManager().submit();
		}
	}

	/**
	 * Called whenever the template gets submitted.
	 * If we have a save_callback, we call that before the submit (no check on
	 * the result)
	 *
	 * @param _values contains the values which will be sent to the server.
	 * 	Listeners may change these values before they get submitted.
	 */
	submit(_values)
	{
		if(this.options.readonly)
		{
			// Not currently editing, just continue on
			return true;
		}
		// Change back to readonly
		this.set_readonly(true);

		var params = [this.get_value()];
		if(this.options.save_callback_params)
		{
			params = params.concat(this.options.save_callback_params.split(','));
		}
		if(this.options.save_callback)
		{
			egw.json(this.options.save_callback, params, function() {
			}, this, true, this).sendRequest();
		}
		return true;
	}
}

