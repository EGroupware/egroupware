/**
 * EGroupware eTemplate2 - JS Description object
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
	et2_core_baseWidget;
*/

/**
 * Class which implements the "description" XET-Tag
 *
 * @augments et2_baseWidget
 */
var et2_description = et2_baseWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"value": {
			"name": "Value",
			"type": "string",
			"description": "Displayed text",
			"translate": "!no_lang",
			"default": ""
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
			"name": "Link URL",
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
		"for": {
			"name": "Label for widget",
			"type": "string",
			"description": "Marks the text as label for the given widget."
		},
		"extra_link_target": {
			"name": "Link target",
			"type": "string",
			"default": "_self",
			"description": "Link target for href attribute"
		},
		"extra_link_popup": {
			"name": "Popup",
			"type": "string",
			"description": "widthxheight, if popup should be used, eg. 640x480"
		},
		"extra_link_title": {
			"name": "Link Title",
			"type": "string",
			"description": "Link title which is displayed on mouse over.",
			"translate": true
		}
	},

	legacyOptions: ["font_style", "href", "activate_links", "for",
		"extra_link_target", "extra_link_popup", "extra_link_title"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_description
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Create the span/label tag which contains the label text
		this.span = $j(document.createElement(this.options["for"] ? "label" : "span"))
			.addClass("et2_label");

		if (this.options["for"])
		{
			// TODO: Get the real id of the widget in the doLoadingFinished method.
			this.span.attr("for", this.options["for"]);
		}

		et2_insertLinkText(this._parseText(this.options.value), this.span[0],
			this.options.href ? this.options.extra_link_target : '_blank');

		this.setDOMNode(this.span[0]);
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		if (this.id)
		{
			var val = this.getArrayMgr("content").getEntry(this.id);

			if (val)
			{
					_attrs["value"] = val;
			}
		}
	},

	set_value: function(_value) {
		if (!_value) _value = "";
		if (!this.options.no_lang) _value = this.egw().lang(_value);
		if (this.options.value && (this.options.value+"").indexOf('%s') != -1)
		{
			_value = this.options.value.replace(/%s/g, _value);
		}
		et2_insertLinkText(this._parseText(_value),
			this.span[0],
			this.options.href ? this.options.extra_link_target : '_blank'
		);
		if(this.options.extra_link_popup)
		{
			var href = this.options.href;
			var title = this.options.extra_link_title;
			var popup = this.options.extra_link_popup;
			jQuery('a',this.span)
				.click(function(e) {
					egw.open_link(href, title,popup);
					e.preventDefault();
					return false;
				});
		}
	},

	_parseText: function(_value) {
		if (this.options.href)
		{
			var href = this.options.href;
                	if (href.indexOf('/')==-1 && href.split('.').length >= 3 &&
				!(href.indexOf('mailto:')!=-1 || href.indexOf('://') != -1 || href.indexOf('javascript:') != -1)
			)
			{
				href = "/index.php?menuaction="+href;
			}
			if (href.charAt(0) == '/')             // link relative to eGW
			{
				href = egw.link(href);
			}
			return [{
				"href": href,
				"text": _value
			}];
		}
		else if (this.options.activate_links)
		{
			return et2_activateLinks(_value);
		}
		else
		{
			return [_value];
		}
	},

	set_font_style: function(_value) {
		this.font_style = _value;

		this.span.toggleClass("et2_bold", _value.indexOf("b") >= 0);
		this.span.toggleClass("et2_italic", _value.indexOf("i") >= 0);
	},

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("value", "class", "href");
	},

	getDetachedNodes: function()
	{
		return [this.span[0]];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		// Update the properties
		var updateLink = false;
		if (typeof _values["href"] != "undefined")
		{
			updateLink = true;
			this.options.href = _values["href"];
		}

		if (typeof _values["value"] != "undefined" || (updateLink && (_values["value"] || this.options.value)))
		{
			this.span = jQuery(_nodes[0]);
			this.set_value(_values["value"]);
		}

		if (typeof _values["class"] != "undefined")
		{
			_nodes[0].setAttribute("class", _values["class"]);
		}
	}
});
et2_register_widget(et2_description, ["description", "label"]);

