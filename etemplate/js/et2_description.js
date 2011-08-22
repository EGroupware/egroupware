/**
 * eGroupWare eTemplate2 - JS Description object
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
	et2_baseWidget;
*/

/**
 * Class which implements the "description" XET-Tag
 */ 
var et2_description = et2_baseWidget.extend({

	attributes: {
		"value": {
			"name": "Caption",
			"type": "string",
			"description": "Displayed text"
		},

		/**
		 * Options converted from the "options"-attribute.
		 */
		"font_style": {
			"name": "Font Style",
			"type": "string",
			"description": "Style may be a compositum of \"b\" and \"i\" which " +
				" renders the text bold and/or italic."
		},
		"href": {
			"name": "Link Target",
			"type": "string",
			"description": "Link URL, empty if you don't wan't to display a link."
		},
		"activate_links": {
			"name": "Replace URLs",
			"type": "boolean",
			"default": false,
			"description": "If set, URLs in the text are automatically replaced " + 
				"by links"
		},
		"label_for": {
			"name": "Label for widget",
			"type": "string",
			"description": "Marks the text as label for the given widget."
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
			"description": "???"
		},
		"extra_link_title": {
			"name": "Link Title",
			"type": "string",
			"description": "Link title which is displayed on mouse over."
		}
	},

	legacyOptions: ["font_style", "href", "activate_links", "label_for", 
		"extra_link_target", "extra_link_popup", "extra_link_title"],

	init: function(_parent) {
		this._super.apply(this, arguments);

		this.value = "";
		this.font_style = "";

		// Create the span/label tag which contains the label text
		this.span = $j(document.createElement(this.options.label_for ? "label" : "span"))
			.addClass("et2_label");

		if (this.options.label_for)
		{
			// TODO: Get the real id of the widget in the doLoadingFinished method.
			this.span.attr("for", this.options.label_for);
		}

		// Create an array which contains the parts of the text with links around
		// it
		et2_insertLinkText(this._parseText(), this.span[0], this.options.extra_link_target);

		this.setDOMNode(this.span[0]);
	},

	_parseText: function() {
		if (this.options.href)
		{
			return [{
				"href": this.options.href,
				"text": this.options.value
			}];
		}
		else if (this.options.activate_links)
		{
			return et2_activateLinks(this.options.value);
		}
		else
		{
			return [this.options.value];
		}
	},

	set_font_style: function(_value) {
		this.font_style = _value;

		this.span.toggleClass("et2_bold", _value.indexOf("b") >= 0);
		this.span.toggleClass("et2_italic", _value.indexOf("i") >= 0);
	}

});

et2_register_widget(et2_description, ["description", "label"]);


