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
			"type": "string",
			"description": "JS code which gets executed when the button is clicked"
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		this.label = "";
		this.clicked = false;
		this.btn = null;
		this.image = null;

		if (!this.options.readonly)
		{
			this.btn = $j(document.createElement("button"))
				.addClass("et2_button et2_button_text");
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
				this.image = et2_createWidget("image", 
					{
						label: this.options.label
					}, this);
			}
			found_image = this.image.set_src(this.options.image);
			if(found_image) {
				// No label if there's an image
				this.set_label("");
				this.btn.removeClass("et2_button_text").addClass("et2_button_icon");
			}
			jQuery(this.image.getDOMNode()).appendTo(this.btn);
			//this.addChild(this.image);
		}
		if(!found_image)
		{
			this.set_label(this.label);
		}
	},

	getDOMNode: function() {
		return this.btn ? this.btn[0] : null;
	},

	onclick: function(_node) {
		// Execute the JS code connected to the event handler
		if (this.options.onclick)
		{
			// Exectute the legacy JS code
			if (!(et2_compileLegacyJS(this.options.onclick, this, _node))())
			{
				return false;
			}
		}

		// Submit the form
		if (this._type != "buttononly")
		{
			this.clicked = true;
			this.getInstanceManager().submit(this); //TODO: this only needs to be passed if it's in a datagrid
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
		_attrs.push("label", "value", "class", "image", "onclick" );
	},

	getDetachedNodes: function()
	{
		return [this.getDOMNode()];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		this.btn = jQuery(_nodes[0]);

		if (typeof _values["id"] != "undefined")
		{
			this.set_id(_values["id"]);
		}
		if (typeof _values["label"] != "undefined")
		{
			this.set_label(_values["label"]);
		}
		if (typeof _values["value"] != "undefined")
		{
		}
		if (typeof _values["image"] != "undefined")
		{
			this.image = null;
			this.set_image(_values["image"]);
		}

		if (typeof _values["class"] != "undefined")
		{
			this.set_class(_values["class"]);
		}

		if (typeof _values["onclick"] != "undefined")
		{
			this.options.onclick = _values["onclick"];
		}
		this.btn.bind("click.et2_baseWidget", this, function(e) {
			e.data.set_id(_values["id"]);
			return e.data.click.call(e.data,e);
		});

	}
});

et2_register_widget(et2_button, ["button", "buttononly"]);

