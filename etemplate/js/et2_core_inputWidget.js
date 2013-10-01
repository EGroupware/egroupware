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

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_interfaces;
	et2_core_valueWidget;
*/

/**
 * et2_inputWidget derrives from et2_simpleWidget and implements the IInput
 * interface. When derriving from this class, call setDOMNode with an input
 * DOMNode.
 * 
 * @augments et2_valueWidget
 */
var et2_inputWidget = et2_valueWidget.extend([et2_IInput,et2_ISubmitListener], 
{
	attributes: {
		"needed": {
			"name":	"Required",
			"default": false,
			"type": "boolean",
			"description": "If required, the user must enter a value before the form can be submitted"
		},
		"onchange": {
			"name": "onchange",
			"type": "string",
			"description": "JS code which is executed when the value changes."
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
		}
	},

	/**
	 * Constructor
	 * 
	 * @memberOf et2_inputWidget
	 */
	init: function() {
		this._super.apply(this, arguments);

		this._oldValue = "";
		this._labelContainer = null;
	},

	destroy: function() {
		var node = this.getInputNode();
		if (node)
		{
			$j(node).unbind("change.et2_inputWidget");
		}

		this._super.apply(this, arguments);

		this._labelContainer = null;
	},

	/**
	 * Load the validation errors from the server
	 */
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// Check whether an validation error entry exists
		if (this.id && this.getArrayMgr("validation_errors"))
		{
			var val = this.getArrayMgr("validation_errors").getEntry(this.id);
			if (val)
			{
				_attrs["validation_error"] = val;
			}
		}
	},

	attachToDOM: function() {
		var node = this.getInputNode();
		if (node)
		{
			$j(node).bind("change.et2_inputWidget", this, function(e) {
				e.data.change.call(e.data, this);
			});
		}

		this._super.apply(this,arguments);
		
//		$j(this.getInputNode()).attr("novalidate","novalidate"); // Stop browser from getting involved
//		$j(this.getInputNode()).validator();
	},

	detatchFromDOM: function() {
//		if(this.getInputNode()) {
//			$j(this.getInputNode()).data("validator").destroy();
//		}
		this._super.apply(this,arguments);
	},

	change: function(_node) {
		var messages = [];
		var valid = this.isValid(messages);
		
		// Passing false will clear any set messages
		this.set_validation_error(valid ? false : messages);

		if (valid && this.onchange)
		{
			if(typeof this.onchange == 'function')
			{
				// Make sure function gets a reference to the widget
				var args = Array.prototype.slice.call(arguments);
				if(args.indexOf(this) == -1) args.push(this);
				
				return this.onchange.apply(this, args);
			} else {
				return (et2_compileLegacyJS(this.options.onchange, this, _node))();
			}
		}
		return valid;
	},

	set_value: function(_value) {
		this._oldValue = _value;

		var node = this.getInputNode();
		if (node)
		{
			$j(node).val(_value);
		}
	},

	set_id: function(_value) {
		this.id = _value;
		this.dom_id = _value ? this.getInstanceManager().uniqueId+'_'+this.id : _value;

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
	},

	set_needed: function(_value) {
		var node = this.getInputNode();
		if (node)
		{
			if(_value && !this.options.readonly) {
				$j(node).attr("required", "required");
			} else {
				node.removeAttribute("required");
			}
		}

	},

	set_validation_error: function(_value) {
		var node = this.getInputNode();
		if (node)
		{
			if (_value === false)
			{
				this.hideMessage();
				$j(node).removeClass("invalid");
			}
			else
			{
				this.showMessage(_value, "validation_error");
				$j(node).addClass("invalid");
				
				// If on a tab, switch to that tab so user can see it
				var widget = this;
				var tab = this;
				while(widget._parent && widget._type !='tabbox')
				{
					tab = widget;
					widget = widget._parent;
				}
				if(!widget || typeof widget.setActiveTab == 'undefined') return;
				var index = widget._children.indexOf(tab);
				widget.setActiveTab(index);
				console.log(widget);
			}
		}
	},

	/**
	 * Set tab index
	 */
	set_tabindex: function(index) {
		jQuery(this.getInputNode()).attr("tabindex", index);
	},

	getInputNode: function() {
		return this.node;
	},

	get_value: function() {
		return this.getValue();
	},

	getValue: function() {
		var node = this.getInputNode();
		if (node)
		{
			var val = $j(node).val();

			return val;
		}

		return this._oldValue;
	},

	isDirty: function() {
		return this._oldValue != this.getValue();
	},

	resetDirty: function() {
		this._oldValue = this.getValue();
	},
		
	isValid: function(messages) {
		var ok = true;
		
		// Check for required
		if(this.options.needed && !this.options.readonly && (this.getValue() == null || this.getValue().valueOf() == ''))
		{
			messages.push(this.egw().lang('input required'));
			ok = false;
		}
		return ok;
	},

	/**
	 * Called whenever the template gets submitted. We return false if the widget
	 * is not valid, which cancels the submission.
	 * 
	 * @param _values contains the values which will be sent to the server.
	 * 	Listeners may change these values before they get submitted.
	 */
	submit: function(_values) {
		var messages = [];
		var valid = this.isValid(messages);
		
		// Passing false will clear any set messages
		this.set_validation_error(valid ? false : messages);
		return valid;
	}
});

