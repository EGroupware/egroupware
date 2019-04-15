/**
 * EGroupware eTemplate2 - JS Widget base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	et2_core_inputWidget;
*/

/**
 * et2_editableWidget derives from et2_inputWidget and adds the ability to start
 * readonly, then turn editable on double-click.  If we decide to do this with
 * more widgets, it should just be merged with et2_inputWidget.
 *
 * @augments et2_inputWidget
 */
var et2_editableWidget = (function(){ "use strict"; return et2_inputWidget.extend(
{
	attributes: {
		readonly: {
			name: "readonly",
			type: "string", // | boolean
			default: false,
			description: "If set to 'editable' will start readonly, double clicking will make it editable and clicking out will save"
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
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_inputWidget
	 */
	init: function(_parent, _attrs) {
		// 'Editable' really should be boolean for everything else to work
		if(_attrs.readonly && typeof _attrs.readonly === 'string')
		{
			_attrs.readonly = true;
			this._toggle_readonly = true;
		}
		this._super.apply(this, arguments);
	},

	destroy: function() {
		var node = this.getInputNode();
		if (node)
		{
			jQuery(node).off('.et2_editableWidget');
		}

		this._super.apply(this, arguments);
	},

	/**
	 * Load the validation errors from the server
	 *
	 * @param {object} _attrs
	 */
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

	},

	attachToDOM: function() {
		this._super.apply(this,arguments);
		var node = this.getDOMNode();
		if (node && this._toggle_readonly)
		{
			jQuery(node)
				.off('.et2_editableWidget')
				.on("dblclick.et2_editableWidget", this, function(e) {
					e.data.dblclick.call(e.data, this);
				})
				.addClass('et2_clickable et2_editable');
		}

	},

	detatchFromDOM: function() {
		this._super.apply(this,arguments);
	},

	/**
	 * Handle double click
	 *
	 * Turn widget editable
	 *
	 * @param {DOMNode} _node
	 */
	dblclick: function (_node) {
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
	},

	/**
	 * User clicked somewhere else, save and turn back to readonly
	 *
	 * @param {DOMNode} _node Body node
	 * @returns {et2_core_editableWidgetet2_editableWidget.et2_core_editableWidgetAnonym$0@call;getInstanceManager@call;submit}
	 */
	focusout: function (_node)
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
			return this.getInstanceManager().submit();
		}
	}

});}).call(this);

