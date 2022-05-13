/**
 * EGroupware eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_baseWidget;
*/

import {et2_activateLinks, et2_csvSplit, et2_insertLinkText, et2_no_init} from "./et2_core_common";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {WidgetConfig} from "./et2_core_widget";
import {et2_baseWidget} from './et2_core_baseWidget'
import {et2_inputWidget} from "./et2_core_inputWidget";
import {et2_IDetachedDOM, et2_IInputNode} from "./et2_core_interfaces";
import {egw} from "../jsapi/egw_global";

/**
 * Class which implements the "description" XET-Tag
 *
 * @deprecated use Et2Description
 * @todo remove after extending widgets are ported to web-components
 */
export class et2_description extends et2_baseWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
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
			"default": "_browser",
			"description": "Link target for href attribute"
		},
		"extra_link_popup": {
			"name": "Popup",
			"type": "string",
			"description": "widthxheight, if popup should be used, eg. 640x480"
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
		},
		mime_data:{
			name: "Mime data",
			type: "string",
			default: '',
			description: "hash for data stored on service-side with egw_link::(get|set)_data()"
		},
		hover_action: {
			"name": "hover action",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which is executed when clicking on action button. This action is explicitly for attached nodes, like in nm."
		},
		hover_action_title: {
			"name": "hover action title",
			"type": "string",
			"default": "Edit",
			"description": "Text to show as tooltip of defined action"
		}

	};

	public static readonly legacyOptions: string[] = ["font_style", "href", "activate_links", "for",
		"extra_link_target", "extra_link_popup", "statustext"];

	span: JQuery;
	label: string;
	private _labelContainer: JQuery = null;
	font_style: string;
	mime_regexp: any;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_description._attributes, _child || {}));

		// Create the span/label tag which contains the label text
		this.span = jQuery(document.createElement(this.options["for"] ? "label" : "span"))
			.addClass("et2_label");

		et2_insertLinkText(this._parseText(this.options.value), this.span[0],
			this.options.href ? this.options.extra_link_target : '_blank');

		this.setDOMNode(this.span[0]);
	}

	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		if (this.id)
		{
			var val = this.getArrayMgr("content").getEntry(this.id);

			if (val)
			{
					_attrs["value"] = val;
			}
		}
	}

	doLoadingFinished()
	{
		super.doLoadingFinished();

		// Get the real id of the 'for' widget
		var for_widget = null;
		let for_id = "";
		if (this.options["for"] && (
				(for_widget = this.getParent().getWidgetById(this.options.for)) ||
				(for_widget = this.getRoot().getWidgetById(this.options.for))
		) && for_widget && for_widget.id)
		{
			if(for_widget.dom_id || for_widget.getDOMNode().id)
			{
				for_id = for_widget.dom_id || for_widget.getDOMNode().id;
				if(for_widget.instanceOf(et2_IInputNode) && for_widget.getInputNode() && for_id !== for_widget.getInputNode().id)
				{
					for_id = for_widget.getInputNode().id;
				}
				this.span.attr("for", for_id);
			}
			else
			{
				// Target widget is not done yet, need to wait
				return new Promise((resolve) =>
				{
					window.setTimeout(() =>
					{
						for_id = for_widget.dom_id;
						if (for_widget.instanceOf(et2_inputWidget) && for_widget.getInputNode() && for_widget.dom_id !== for_widget.getInputNode()?.id)
						{
							for_id = for_widget.getInputNode().id;
						}
						this.span.attr("for", for_id);
						resolve();
					}, 0);
				});
			}
		}
		return true;
	}

	set_label(_value)
	{
		// Abort if there was no change in the label
		if (_value == this.label)
		{
			return;
		}

		if (_value)
		{
			// Create the label container if it didn't exist yet
			if (this._labelContainer == null)
			{
				this._labelContainer = jQuery(document.createElement("label"))
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

			// add class if label is empty
			this._labelContainer.toggleClass('et2_label_empty', !_value || !parts[0]);
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
	}

	/**
	 * Function to get media content to feed the expose
	 * @param {type} _value
	 * @returns {Array|Array.getMedia.mediaContent}
	 */
	getMedia(_value)
	{
		let base_url = egw.webserverUrl.match(new RegExp(/^\//,'ig'))?egw(window).window.location.origin :'';
		let mediaContent = [];
		if (_value)
		{
			mediaContent = [{
				title: this.options.label,
				href: base_url + _value,
				type: this.options.type + "/*",
				thumbnail: base_url + _value
			}];
			if (_value.match(/\/webdav.php/,'ig')) mediaContent[0]["download_href"] = base_url + _value + '?download';
		}
		return mediaContent;
	}

	set_value(_value)
	{
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
		// Add hover action button (Edit)
		if (this.options.hover_action)
		{
			this._build_hover_action();
		}
		if(this.options.extra_link_popup || this.options.mime)
		{
			var href = this.options.href;
			var mime_data = this.options.mime_data;
			var self= this;
			var $span =  this.options.mime_data? jQuery(this.span): jQuery('a',this.span);
			$span.click(function(e) {
				if (self.options.expose_view && typeof self.options.mime !='undefined' && self.options.mime.match(self.mime_regexp,'ig'))
				{
					self._init_blueimp_gallery(e, href);
				}
				else
				{
					egw(window).open_link(mime_data || href, self.options.extra_link_target, self.options.extra_link_popup, null, null, self.options.mime);
				}
				e.preventDefault();
				return false;
			});
		}
	}

	_parseText(_value)
	{
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
	}

	set_font_style(_value)
	{
		this.font_style = _value;

		this.span.toggleClass("et2_bold", _value.indexOf("b") >= 0);
		this.span.toggleClass("et2_italic", _value.indexOf("i") >= 0);
	}

	/**
	 * Code for implementing et2_IDetachedDOM
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value", "class", "href");
	}

	getDetachedNodes()
	{
		return [this.span[0]];
	}

	setDetachedAttributes(_nodes, _values, _data?)
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

		// Add hover action button (Edit), _data is nm's row data
		if (this.options.hover_action)
		{
			this._build_hover_action(_data);
		}
	}

	/**
	 * Builds button for hover action
	 * @param {object} _data
	 */
	_build_hover_action(_data?)
	{
		var content = _data && _data.content ? _data.content: undefined;
		var widget = this;
		this.span.off().on('mouseenter', jQuery.proxy(function(event) {
				event.stopImmediatePropagation();
				var self = this;
				this.span.tooltip({
					items: 'span.et2_label',
					position: {my:"right top", at:"left top", collision:"flipfit"},
					tooltipClass: "et2_email_popup",
					content()
					{
						return jQuery('<a href="#" class= "et2_url_email_contactPlus" title="'+widget.egw().lang(widget.options.hover_action_title)+'"><img src="'
								+egw.image("edit") +'"/></a>')
							.on('click', function() {
								widget.options.hover_action.call(self, self.widget, content);
							});
					},
					close( event, ui )
					{
						ui.tooltip.hover(
							function () {
								jQuery(this).stop(true).fadeTo(400, 1);
							},
							function () {
								jQuery(this).fadeOut("400", function(){	jQuery(this).remove();});
							}
						);
					}
				})
				.tooltip("open");
			}, {widget: this, span: this.span}));
		this.span.on('mouseout', function(){
			if(jQuery(this).tooltip('instance')) {
				jQuery(this).tooltip('close');
			}
		});
	}
}