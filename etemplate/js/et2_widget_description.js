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
	/etemplate/js/expose.js;
*/

/**
 * Class which implements the "description" XET-Tag
 *
 * @augments et2_baseWidget
 */
var et2_description = expose(et2_baseWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"label": {
			"name": "Label",
			"default": "",
			"type": "string",
			"description": "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
			"translate": true
		},
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
		},
		"expose_view":{
			name: "Expose view",
			type: "boolean",
			default: false,
			description: "Clicking on description with href value would popup an expose view, and will show content referenced by href."
		},
		mime:{
			name: "Mime type",
			type: "string",
			default: '',
			description: "Mime type of the registered link"
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

	set_label: function(_value) {
		// Abort if ther was no change in the label
		if (_value == this.label)
		{
			return;
		}

		if (_value)
		{
			// Create the label container if it didn't exist yet
			if (this._labelContainer == null)
			{
				this._labelContainer = $j(document.createElement("label"))
					.addClass("et2_label");
				this.getSurroundings().insertDOMNode(this._labelContainer[0]);
			}
			
			// Clear the label container.
			this._labelContainer.empty();

			// Create the placeholder element and set it
			var ph = document.createElement("span");
			this.getSurroundings().setWidgetPlaceholder(ph);

			// Split the label at the "%s"
			var parts = et2_csvSplit(_value, 2, "%s");

			// Update the content of the label container
			for (var i = 0; i < parts.length; i++)
			{
				if (parts[i])
				{
					this._labelContainer.append(document.createTextNode(parts[i]));
				}
				if (i == 0)
				{
					this._labelContainer.append(ph);
				}
			}
		}
		else
		{
			// Delete the labelContainer from the surroundings object
			if (this._labelContainer)
			{
				this.getSurroundings().removeDOMNode(this._labelContainer[0]);
			}
			this._labelContainer = null;
		}
		
		// Update the surroundings in order to reflect the change in the label
		this.getSurroundings().update();

		// Copy the given value
		this.label = _value;
	},
	/**
	 * Function to get media content to feed the expose
	 * @param {type} _value
	 * @returns {Array|Array.getMedia.mediaContent}
	 */
	getMedia: function (_value)
	{
		var base_url = egw.webserverUrl.match(/^\//,'ig')?egw(window).window.location.origin :egw.webserverUrl + '/';
		var mediaContent = [];
		if (_value)
		{
			mediaContent = [{
				title: this.options.label,
				href: base_url + _value,
				type: this.options.type + "/*",
				thumbnail: base_url + _value
			}];
		}
		return mediaContent;
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
		if(this.options.extra_link_popup || this.options.mime)
		{
			var self= this;
			jQuery('a',this.span)
				.click(function(e) {
					if (self.options.expose_view && typeof self.options.mime !='undefined' && self.options.mime.match(/video\/|image\/|audio\//,'ig'))
					{
						self._init_blueimp_gallery(e,self.options.href);
					}
					else
					{
						egw(window).open_link(self.options.href, self.options.extra_link_title,self.options.extra_link_popup,null,null,self.options.mime);
					}
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
}));
et2_register_widget(et2_description, ["description", "label"]);

