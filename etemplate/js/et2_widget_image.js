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
	et2_core_baseWidget;
*/

/**
 * Class which implements the "image" XET-Tag
 */ 
var et2_image = et2_baseWidget.extend({

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
		this.image = this.node = $j(document.createElement("img"));
		if(this.options.link)
		{
			this.node = $j(document.createElement("a"));
			this.image.appendTo(this.node);
		}
		if(this.options.class)
		{
			this.node.addClass(this.options.class);
		}
		this.setDOMNode(this.node[0]);
	},

	set_label: function(_value) {
		if(_value == this.options.label) return;
		this.options.label = _value;
		// label is NOT the alt attribute in eTemplate, but the title/tooltip
		this.image.attr("alt", _value);
		this.image.set_statustext(_value);
	},

	set_src: function(_value) {
		if(!this.isInTree())
		{
			return;
		}
		this.options.src = _value;
		// Get application to use from template ID
		var appname = this.getTemplateApp();
		var src = egw.image(_value,appname || "phpgwapi");
		if(src )
		{
			this.image.attr("src", src).show();
		}
		else
		{
			this.image.css("display","none");
		}
	}
});

et2_register_widget(et2_image, ["image"]);


