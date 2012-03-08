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

		if (this.options.image)
		{
			this.image = jQuery(document.createElement("img"))
				.addClass("et2_button et2_button_icon");
			this.setDOMNode(this.image[0]);
			return;
		}
		if (!this.options.readonly)
		{
			this.btn = $j(document.createElement("button"))
				.addClass("et2_button et2_button_text");
			this.setDOMNode(this.btn[0]);
		}
	},

	set_image: function(_image) {
		if(!this.isInTree() || this.image == null) return;

		this.options.image = _image;

		var found_image = false;
		if(this.options.image != "") 
		{
			var src = this.egw().image(_image);
			if(src)
			{
				this.image.attr("src", src);
				found_image = true;
			}
			// allow url's too
			else if (_image[0] == '/' || _image.substr(0,4) == 'http')
			{
				this.image.attr('src', _image);
				found_image = true;
			}
		}
		if(!found_image)
		{
			this.set_label(this.label);
		}
	},

	getDOMNode: function() {
		return this.btn ? this.btn[0] : (this.image ? this.image[0] : null);
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
		if(this.image)
		{
			this.image.attr("alt", _value).attr("title",_value);
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
		return [
			this.btn != null ? this.btn[0] : null,
			this.image != null ? this.image[0] : null
		];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		// Datagrid puts in the row for null
		this.btn = _nodes[0].nodeName == 'button' ? jQuery(_nodes[0]) : null;
		this.image = jQuery(_nodes[1]);

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
		jQuery(this.getDOMNode()).bind("click.et2_baseWidget", this, function(e) {
			e.data.set_id(_values["id"]);
			return e.data.click.call(e.data,e);
		});

	}
});

et2_register_widget(et2_button, ["button", "buttononly"]);

