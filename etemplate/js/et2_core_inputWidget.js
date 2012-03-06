/**
 * eGroupWare eTemplate2 - JS Widget base class
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
 */
var et2_inputWidget = et2_valueWidget.extend(et2_IInput, {

	attributes: {
		"required": {
			"name":	"Required",
			"default": false,
			"type": "boolean",
			"description": "If required, the user must enter a value before the form can be submitted"
		},
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
			"description": "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			"translate": true
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
		}
	},

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
				e.data.change(this);
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
		if (this.onchange)
		{
			return this.onchange(_node);
		}
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

		// Set the id of the _input_ node (in contrast to the default
		// implementation, which sets the base node)
		var node = this.getInputNode();
		if (node)
		{
			if (_value != "")
			{
				node.setAttribute("id", _value);
				node.setAttribute("name", _value);
			}
			else
			{
				node.removeAttribute("id");
				node.removeAttribute("name");
			}
		}
	},

	set_label: function(_value) {
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
				this._labelContainer = $j(document.createElement("label"))
					.addClass("et2_label");
				this.getSurroundings().insertDOMNode(this._labelContainer[0]);
			}

			// Clear the label container.
			this._labelContainer.empty();

			// Create the placeholder element and set it
			var ph = document.createElement("span");
			this.getSurroundings().setWidgetPlaceholder(ph);

			// Split the label at the "%s"
			var parts = et2_csvSplit(_value, 2, "%s");

			// Update the content of the label container
			for (var i = 0; i < parts.length; i++)
			{
				if (parts[i])
				{
					this._labelContainer.append(document.createTextNode(parts[i]));
				}
				if (i == 0)
				{
					this._labelContainer.append(ph);
				}
			}
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

		// Update the surroundings in order to reflect the change in the label
		this.getSurroundings().update();

		// Copy the given value
		this.label = _value;
	},

	set_required: function(_value) {
		var node = this.getInputNode();
		if (node)
		{
			if(_value) {
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
			}
		}
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
	}

});

