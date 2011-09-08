/**
 * eGroupWare eTemplate2 - JS Description object
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
var et2_image = et2_baseWidget.extend(/*et2_IDetachedDOM,*/ {

	attributes: {
		"src": {
			"name": "Image",
			"type": "string",
			"description": "Displayed image"
		},
		"link": {
		},
		"link_target":{
		},
		"imagemap":{
		},
		"link_size":{
		}
	},

	legacyOptions: ["link", "link_target", "imagemap", "link_size"],

	init: function() {
		this._super.apply(this, arguments);

		// Create the image or a/image tag
		var node = this.image = $j(document.createElement("img"));
		if(this.options.link)
		{
			this._node = $j(document.createElement("a"));
			this.image.appendTo(node);
		}
		if(this.options["class"])
		{
			node.addClass(this.options["class"]);
		}
		this.setDOMNode(node[0]);
	},

	transformAttributes: function(_attrs) {
		this._super.apply(arguments);

		// Check to expand name
		if (typeof _attrs["src"] != "undefined")
		{
			var src = this.getArrayMgr("content").getEntry(_attrs["src"]);
			if (src)
			{
				_attrs["src"] = src;
			}
		}
	},

	set_label: function(_value) {
		if(_value == this.options.label) return;
		this.options.label = _value;
		// label is NOT the alt attribute in eTemplate, but the title/tooltip
		this.image.attr("alt", _value);
		this.set_statustext(_value);
	},

	setValue: function(_value) {
		// Value is src, images don't get IDs
		this.set_src(_value);
	},

	percentagePreg: /^[0-9]+%$/,

	set_src: function(_value) {
		if(!this.isInTree())
		{
			return;
		}

		this.options.src = _value;

		// Check whether "src" is a percentage
		if (this.percentagePreg.test(_value))
		{
			this.getSurroundings().prependDOMNode(document.createTextNode(_value));
		}
		else
		{
			// Get application to use from template ID
			var src = egw.image(_value, this.getTemplateApp());
			if(src)
			{
				this.image.attr("src", src).show();
			}
			else
			{
				this.image.css("display","none");
			}
		}
	},

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 */

	// Does currently not work for percentages, as the surroundings manager
	// cannot opperate on other DOM-Nodes.

/*	getDetachedAttributes: function(_attrs) {
		_attrs.push("src", "label");
	},

	getDetachedNodes: function() {
		return [this.node, this.image[0]];
	},

	setDetachedAttributes: function(_nodes, _values) {
		// Set the given DOM-Nodes
		this.node = _nodes[0];
		this.image = $j(_nodes[1]);

		this.transformAttributes(_values);

		// Set the attributes
		if (_values["src"])
		{
			this.set_src(_values["src"]);
		}

		if (_values["label"])
		{
			this.set_label(_values["label"]);
		}
	}*/
});

et2_register_widget(et2_image, ["image"]);


