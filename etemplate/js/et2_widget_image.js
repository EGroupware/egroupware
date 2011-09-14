/**
 * EGroupware eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_interfaces;
	et2_core_baseWidget;
*/

/**
 * Class which implements the "image" XET-Tag
 */ 
var et2_image = et2_baseWidget.extend(et2_IDetachedDOM, {

	attributes: {
		"src": {
			"name": "Image",
			"type": "string",
			"description": "Displayed image"
		},
		"href": {
			"name": "Link Target",
			"type": "string",
			"description": "Link URL, empty if you don't wan't to display a link."
		},
		"extra_link_target": {
			"name": "Link target",
			"type": "string",
			"default": "_self",
			"description": "Link target descriptor"
		},
		"extra_link_popup": {
			"name": "Popup",
			"type": "string",
			"description": "widthxheight, if popup should be used, eg. 640x480"
		},
		"imagemap":{
		},
		"label": {
		}
	},
	legacyOptions: ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"],

	init: function() {
		this._super.apply(this, arguments);

		// Create the image or a/image tag
		var node = this.image = $j(document.createElement("img"));
		if (this.options.label)
		{
			this.image.attr("alt", this.options.label).attr("title", this.options.label);
		}
		if (this.options.href) 
		{
			this.image.addClass('et2_clickable');
		}
		if(this.options["class"])
		{
			node.addClass(this.options["class"]);
		}
		this.setDOMNode(node[0]);
	},
	
	click: function()
	{
		if(this.options.href)
		{
			egw.call_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
		}		
	},

	transformAttributes: function(_attrs) {
		this._super.apply(arguments);

		// Check to expand name
		if (typeof _attrs["src"] != "undefined")
		{
			var manager = this.getArrayMgr("content");
			if(manager) {
				var src = manager.getEntry(_attrs["src"]);
				if (src)
				{
					_attrs["src"] = src;
				}
			}
		}
	},

	set_label: function(_value) {
		if(_value == this.options.label) return;
		this.options.label = _value;
		// label is NOT the alt attribute in eTemplate, but the title/tooltip
		this.image.attr("alt", _value).attr("title", _value);
	},

	setValue: function(_value) {
		// Value is src, images don't get IDs
		this.set_src(_value);
	},

	set_src: function(_value) {
		if(!this.isInTree())
		{
			return;
		}

		this.options.src = _value;
		var app = this.getTemplateApp();

		// Handle app/image
		if(_value.indexOf("/") > 0) {
			var split = et2_csvSplit(_value, 2,"/");
			var app = split[0];
			_value = split[1];
		}
		// Get application to use from template ID
		var src = egw.image(_value, app);
		if(src)
		{
			this.image.attr("src", src).show();
			return true;
		}
		// allow url's too
		else if (_value[0] == '/' || _value.substr(0,4) == 'http')
		{
			this.image.attr('src', _value).show();
			return true;
		}
		else
		{
			this.image.css("display","none");
			return false;
		}
	},

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 */

	getDetachedAttributes: function(_attrs) {
		_attrs.push("src", "label");
	},

	getDetachedNodes: function() {
		return [this.image[0]];
	},

	setDetachedAttributes: function(_nodes, _values) {
		// Set the given DOM-Nodes
		this.image = $j(_nodes[0]);

		// Set the attributes
		if (_values["src"])
		{
			this.set_src(_values["src"]);
		}

		if (_values["label"])
		{
			this.set_label(_values["label"]);
		}
	}
});

et2_register_widget(et2_image, ["image"]);


