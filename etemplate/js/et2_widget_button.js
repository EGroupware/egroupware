/**
 * eGroupWare eTemplate2 - JS Button object
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
	et2_core_baseWidget;
*/

/**
 * Class which implements the "button" XET-Tag
 */ 
var et2_button = et2_baseWidget.extend([et2_IInput, et2_IDetachedDOM], {

	attributes: {
		"label": {
			"name": "caption",
			"type": "string",
			"description": "Label of the button",
			"translate": true
		},
		"image": { 
			"name": "Icon",
			"type": "string",
			"description": "Use an icon instead of label (when available)"
		},
		"onclick": {
			"name": "onclick",
			"type": "js",
			"description": "JS code which gets executed when the button is clicked"
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this.label = "";
		this.clicked = false;
		this.btn = null;

		if (!this.options.readonly)
		{
			this.btn = $j(document.createElement("button"))
				.addClass("et2_button");
			this.setDOMNode(this.btn[0]);
		}
	},

	set_image: function(_image) {
		if(!this.isInTree()) return;
		this.options.image = _image;

		var found_image = false;
		if(this.options.image != "") 
		{
			if(!this.image)
			{
				this.image = et2_createWidget("image",{label: this.options.label});
			}
			found_image = this.image.set_src(this.options.image);
			jQuery(this.image.getDOMNode()).appendTo(this.btn);
		}
		if(found_image)
		{
			// No label if there's an image
			this.options.label = "";
		}
	},

	getDOMNode: function() {
		return this.btn[0];
	},

	// TODO: What's going on here?  It doesn't get called, but something happens if you double click.
	click: function() {
		// Execute the JS code connected to the event handler
		if (this.options.onclick)
		{
			if (!this.options.onclick())
				return false;
		}

		// Submit the form
		if (this._type != "buttononly")
		{
			this.clicked = true;
			this.getInstanceManager().submit();
			this.clicked = false;
		}
	},

	set_label: function(_value) {
		if (this.btn)
		{
			this.label = _value;

			this.btn.text(_value);
		}
	},


	/**
	 * Implementation of the et2_IInput interface
	 */

	/**
	 * Always return false as a button is never dirty
	 */
	isDirty: function() {
		return false;
	},

	resetDirty: function() {
	},

	getValue: function() {
		if (this.clicked)
		{
			return true;
		}

		// If "null" is returned, the result is not added to the submitted
		// array.
		return null;
	},

	/**
	 * et2_IDetachedDOM
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("value", "class", "image");
	},

	getDetachedNodes: function()
	{
		return [this.getDOMNode(),this.image];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		this.btn = _nodes[0];
		this.image = _nodes[1];

		if (typeof _values["id"] != "undefined")
		{
			this.set_id(_values["id"]);
		}
		if (typeof _values["value"] != "undefined")
		{
		}

		if (typeof _values["class"] != "undefined")
		{
			this.set_class(_values["class"]);
		}
	}
});

et2_register_widget(et2_button, ["button", "buttononly"]);

