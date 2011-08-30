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
			"description": "Displayed image",
			"translate": "!no_lang"
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
		this.setDOMNode(this.node[0]);
	},

	set_label: function(_value) {
		if(_value == this.options.label) return;
		this.options.label = _value;
		this.image.attr("alt", _value);
	},

	set_src: function(_value) {
		console.log("IMAGE ", _value);
		this.options.src = _value;
		this.image.attr("src", _value);
	}
});

et2_register_widget(et2_image, ["image"]);


