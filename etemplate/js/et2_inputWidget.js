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
	et2_valueWidget;
*/

/**
 * Interface for all widgets which support returning a value
 */
var et2_IInput = new Interface({
	/**
	 * getValue has to return the value of the input widget
	 */
	getValue: function() {},

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty: function() {},

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty: function() {}
});

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
			"description": "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables)."
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this._oldValue = "";
		this._labelContainer = null;
		this._widgetPlaceholder = null;
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this._labelContainer = null;
		this._widgetPlaceholder = null;
	},

	attachToDOM: function() {
		if (this._labelContainer)
		{
			// Get the DOM Node without the labelContainer - that's why null is
			// passed here.
			var node = this.getDOMNode(null);

			if (node)
			{
				// Recreate the widget placeholder and return, as the set_label
				// function will call attachToDOM again
				if (!this._widgetPlaceholder)
				{
					this.set_label(this.label);
					return;
				}

				// Insert the widget at the position of the placeholder
				this._labelContainer.replaceChild(node, this._widgetPlaceholder);

				// Set the widgetPlaceholder to null
				this._widgetPlaceholder = null;
			}
		}

		this._super.apply(this,arguments);
		
		$j(this.getInputNode()).attr("novalidate","novalidate"); // Stop browser from getting involved
		$j(this.getInputNode()).validator();
	},

	detatchFromDOM: function() {
		if(this.getInputNode()) {
			$j(this.getInputNode()).data("validator").destroy();
		}
		this._super.apply(this,arguments);
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
		// Copy the given value
		this.label = _value;

		// Detach the current element from the DOM
		var attached = this.isAttached();
		if (attached)
		{
			this.detatchFromDOM();
		}

		if (_value)
		{
			// Create the label container if it didn't exist yet
			if (this._labelContainer == null)
			{
				this._labelContainer = document.createElement("label");
				this._labelContainer.setAttribute("class", "et2_label");
			}

			// Clear the label container.
			for (;this._labelContainer.childNodes.length > 0;)
			{
				this._labelContainer.removeChild(this._labelContainer.childNodes[0]);
			}

			// Create the placeholder element
			this._widgetPlaceholder = document.createElement("span");

			// Split the label at the "%s"
			var parts = et2_csvSplit(_value, 2, "%s");

			// Create the content of the label container
			for (var i = 0; i < parts.length; i++)
			{
				if (parts[i])
				{
					this._labelContainer.appendChild(document.createTextNode(parts[i]));
				}
				if (i == 0)
				{
					this._labelContainer.appendChild(this._widgetPlaceholder);
				}
			}
		}
		else
		{
			this._labelContainer = null;
			this._widgetPlaceholder = null;
		}

		// Attach the current element to the DOM again
		if (attached)
		{
			this.attachToDOM();
		}
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

	getDOMNode: function(_sender) {
		if (_sender == this && this._labelContainer)
		{
			return this._labelContainer;
		}

		return this._super.apply(this, arguments);
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

