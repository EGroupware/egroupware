/**
 * EGroupware eTemplate2 - JS Button object
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
 * @augments et2_baseWidget
 */
var et2_button = et2_baseWidget.extend([et2_IInput, et2_IDetachedDOM],
{
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
		"ro_image": {
			"name": "Read-only Icon",
			"type": "string",
			"description": "Use this icon instead of hiding for read-only"
		},
		"onclick": {
			"description": "JS code which gets executed when the button is clicked"
		},
		"accesskey": {
			"name": "Access Key",
			"type": "string",
			"default": et2_no_init,
			"description": "Alt + <key> activates widget"
		},
		"tabindex": {
			"name": "Tab index",
			"type": "integer",
			"default": et2_no_init,
			"description": "Specifies the tab order of a widget when the 'tab' button is used for navigating."
		},
		background_image: {
			name: "Add image in front of text",
			type: "boolean",
			description: "Adds image in front of text instead of just using an image with text as tooltip",
			default: et2_no_init	// to leave it undefined, if not defined, so background-image is assigned by default
		},
		novalidate: {
			name: "Do NOT validate form",
			type: "boolean",
			description: "Do NOT validate form before submitting it",
			default: false
		},
		// No such thing as a required button
		"needed": {
			"ignore": true
		}
	},

	legacyOptions: ["image", "ro_image"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_button
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.label = "";
		this.clicked = false;
		this.btn = null;
		this.image = null;

		if (!this.options.background_image && (this.options.image || this.options.ro_image))
		{
			this.image = jQuery(document.createElement("img"))
				.addClass("et2_button et2_button_icon");
			this.setDOMNode(this.image[0]);
			return;
		}
		if (!this.options.readonly)
		{
			this.btn = $j(document.createElement("button"))
				.addClass("et2_button et2_button_text")
				.attr({type:"button"});
			this.setDOMNode(this.btn[0]);
		}
	},

	/**
	 * Apply the "modifications" to the element and translate attributes marked
	 * with "translate: true"
	 *
	 * Reimplemented here to assign default background-images to buttons
	 *
	 * @param {object} _attrs
	 */
	transformAttributes: function(_attrs)
	{
		if (this.id && typeof _attrs.background_image == 'undefined' && !_attrs.image)
		{
			for(var image in et2_button.default_background_images)
			{
				if (this.id.match(et2_button.default_background_images[image]))
				{
					_attrs.image = image;
					_attrs.background_image = true;
					break;
				}
			}
		}
		for(var name in et2_button.default_classes)
		{
			if (this.id.match(et2_button.default_classes[name]))
			{
				_attrs.class = (typeof _attrs.class == 'undefined' ? '' : _attrs.class+' ')+name;
				break;
			}
		}
		this._super.apply(this, arguments);
	},

	set_accesskey: function(key) {
		jQuery(this.node).attr("accesskey", key);
	},
	/**
	 * Set image and update current image
	 *
	 * @param _image
	 */
	set_image: function(_image) {
		this.options.image = _image;
		this.update_image();
	},
	/**
	 * Set readonly image and update current image
	 *
	 * @param _image
	 */
	set_ro_image: function(_image) {
		this.options.ro_image = _image;
		this.update_image();
	},
	/**
	 * Set current image (dont update options.image)
	 *
	 * @param _image
	 */
	update_image: function(_image) {
		if(!this.isInTree() || !this.options.background_image && this.image == null) return;

		if (typeof _image == 'undefined')
			_image = this.options.readonly ? this.options.ro_image : this.options.image;

		// Silently blank for percentages instead of warning about missing image - use a progress widget
		if(_image.match(/^[0-9]+\%$/))
		{
			_image = "";
			//this.egw().debug("warn", "Use a progress widget instead of percentage images", this);
		}

		var found_image = false;
		if(_image != "")
		{
			var src = this.egw().image(_image);
			if(src)
			{
				found_image = true;
			}
			else if (_image[0] == '/' || _image.substr(0,4) == 'http')
			{
				src= image;
				found_image = true;
			}
			if(found_image)
			{
				if(this.image != null)
				{
					this.image.attr("src", src);
				}
				else if (this.options.background_image)
				{
					this.btn.css("background-image","url("+src+")");
					this.btn.addClass('et2_button_with_image');
				}
			}
		}
		if(!found_image)
		{
			this.set_label(this.label);
			if(this.btn)
			{
				this.btn.css("background-image","");
				this.btn.removeClass('et2_button_with_image');
			}
		}
	},

	/**
	 * Set options.readonly and update image
	 *
	 * @param {boolean} _ro
	 */
	set_readonly: function(_ro)
	{
		if (_ro != this.options.readonly)
		{
			this.options.readonly = _ro;

			if (this.image)
			{
				this.update_image();
			}
			// dont show readonly buttons as clickable
			if (this.btn || this.image)
			{
				(this.btn || this.image)
					.toggleClass('et2_clickable', !_ro)
					.toggleClass('et2_button_ro', _ro)
					.css('cursor', _ro ? 'default' : 'pointer');	// temp. 'til it is removed from et2_button
			}
		}
	},

	attachToDOM: function() {
		this._super.apply(this, arguments);

		if (this.options.readonly && (this.btn || this.image))
		{
			(this.btn || this.image)
				.removeClass('et2_clickable')
				.addClass('et2_button_ro')
				.css('cursor', 'default');	// temp. 'til it is removed from et2_button
		}
	},

	getDOMNode: function() {
		return this.btn ? this.btn[0] : (this.image ? this.image[0] : null);
	},

	/**
	 * Overwritten to maintain an internal clicked attribute
	 *
	 * @param _ev
	 * @returns {Boolean}
	 */
	click: function(_ev) {
		// ignore click on readonly button
		if (this.options.readonly) return false;

		this.clicked = true;

		if (!this._super.apply(this, arguments))
		{
			this.clicked = false;
			return false;
		}

		// Submit the form
		if (this._type != "buttononly")
		{
			this.getInstanceManager().submit(this, false, this.options.novalidate); //TODO: this only needs to be passed if it's in a datagrid
		}
		this.clicked = false;
		return true;
	},

	set_label: function(_value) {
		if (this.btn)
		{
			this.label = _value;

			this.btn.text(_value);
		}
		if(this.image)
		{
			this.image.attr("alt", _value);
			// Don't set title if there's a tooltip, browser may show both
			if(!this.options.statustext)
			{
				this.image.attr("title",_value);
			}
		}
	},

	/**
	 * Set tab index
	 *
	 * @param {number} index
	 */
	set_tabindex: function(index) {
		jQuery(this.btn).attr("tabindex", index);
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
	isValid: function() {
		return true;
	},

	/**
	 * et2_IDetachedDOM
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("label", "value", "class", "image", "ro_image", "onclick", "background_image" );
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
		this.btn = _nodes[0].nodeName[0] != '#' ? jQuery(_nodes[0]) : null;
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
		if (typeof _values["ro_image"] != "undefined")
		{
			this.set_ro_image(_values["ro_image"]);
		}
		if (typeof _values["class"] != "undefined")
		{
			this.set_class(_values["class"]);
		}

		if (typeof _values["onclick"] != "undefined")
		{
			this.options.onclick = _values["onclick"];
		}
		var type = this._type;
		var attrs = jQuery.extend(_values, this.options);
		var parent = this._parent;
		jQuery(this.getDOMNode()).bind("click.et2_baseWidget", this, function(e) {
			var widget = et2_createWidget(type,attrs,parent);
			e.data = widget;
			e.data.set_id(_values["id"]);
			return e.data.click.call(e.data,e);
		});
	}
});
et2_register_widget(et2_button, ["button", "buttononly"]);

// Static class stuff
jQuery.extend(et2_button,
/** @lends et2_button */
{
	/**
	 * images to be used as background-image, if none is explicitly applied and id matches given regular expression
	 */
	default_background_images: {
		save: /save(&|\]|$)/,
		apply: /apply(&|\]|$)/,
		cancel: /cancel(&|\]|$)/,
		delete: /delete(&|\]|$)/,
		edit: /edit(&|\[\]|$)/,
		next: /(next|continue)(&|\]|$)/,
		finish: /finish(&|\]|$)/,
		back: /(back|previous)(&|\]|$)/,
		copy: /copy(&|\]|$)/,
		more: /more(&|\]|$)/,
		check: /(yes|check)(&|\]|$)/,
		cancelled: /no(&|\]|$)/,
		ok: /ok(&|\]|$)/,
		close: /close(&|\]|$)/,
		add: /(add(&|\]|$)|create)/	// customfields use create*
	},

	/**
	 * Classnames added automatic to buttons to set certain hover background colors
	 */
	default_classes: {
		et2_button_cancel: /cancel(&|\]|$)/,		// yellow
		et2_button_question: /(yes|no)(&|\]|$)/,	// yellow
		et2_button_delete: /delete(&|\]|$)/			// red
	}
});
