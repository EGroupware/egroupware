/**
 * EGroupware eTemplate2 - JS Itempicker object
 * derived from et2_link_entry widget @copyright 2011 Nathan Gray
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Christian Binder
 * @author Nathan Gray
 * @copyright 2012 Christian Binder
 * @copyright 2011 Nathan Gray
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_inputWidget;
	et2_core_valueWidget;
	et2_extension_itempicker_actions;
	egw_action.egw_action_common;
*/

import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_csvSplit, et2_no_init} from "./et2_core_common";
import {et2_button} from "./et2_widget_button";
import {egw} from "../jsapi/egw_global";

/**
 * Class which implements the "itempicker" XET-Tag
 *
 * @augments et2_inputWidget
 */
export class et2_itempicker extends et2_inputWidget
{
	static readonly _attributes : any = {
		"action": {
			"name": "Action callback",
			"type": "string",
			"default": false,
			"description": "Callback for action.  Must be a function(context, data)"
		},
		"action_label": {
			"name": "Action label",
			"type": "string",
			"default": "Action",
			"description": "Label for action button"
		},
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to the listed application or applications (comma separated)"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": et2_no_init,
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
		"value": {
			"name": "value",
			"type": "any",
			"default": "",
			"description": "Optional itempicker value(s) - can be used for e.g. environmental information"
		},
		"query": {
			"name": "Query callback",
			"type": "any",
			"default": false,
			"description": "Callback before query to server.  Must return true, or false to abort query."
		}
	};

	public static readonly legacyOptions : string[] = ["application"];
	private last_search : string =  "";	// Remember last search value
	private action : egwFnct = null;	// Action function for button
	private current_app : string =  "";	// Remember currently chosen application
	private div : JQuery = null;
	private left : JQuery = null;
	private right : JQuery = null;
	private right_container : JQuery = null;
	private app_select : JQuery = null;
	private search : JQuery = null;
	private button_action : any = null;
	private itemlist : JQuery = null;
	private clear : JQuery;

	/**
	 * Constructor
	 *
	 * @memberOf et2_itempicker
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_itempicker._attributes, _child || {}));

		this.div = null;
		this.left = null;
		this.right = null;
		this.right_container = null;
		this.app_select = null;
		this.search = null;
		this.button_action = null;
		this.itemlist = null;

		if(this.options.action !== null && typeof this.options.action == "string")
		{
			this.action = new egwFnct(this, "javaScript:" + this.options.action);
		}
		else
		{
			console.log("itempicker widget: no action provided for button");
		}

		this.createInputWidget();
	}

	clearSearchResults()
	{
		this.search.val("");
		this.itemlist.html("");
		this.search.focus();
		this.clear.hide();
	}

	createInputWidget() {
		let _self = this;

		this.div = jQuery(document.createElement("div"));
		this.left = jQuery(document.createElement("div"));
		this.right = jQuery(document.createElement("div"));
		this.right_container = jQuery(document.createElement("div"));
		this.app_select = jQuery(document.createElement("ul"));
		this.search = jQuery(document.createElement("input"));
		this.clear = jQuery(document.createElement("span"));
		this.itemlist = jQuery(document.createElement("div"));

		// Container elements
		this.div.addClass("et2_itempicker");
		this.left.addClass("et2_itempicker_left");
		this.right.addClass("et2_itempicker_right");
		this.right_container.addClass("et2_itempicker_right_container");

		// Application select
		this.app_select.addClass("et2_itempicker_app_select");
		let item_count = 0;
		for(let key in this.options.select_options)
		{
			let img_icon = this.egw().image(key + "/navbar");
			if(img_icon === null)
			{
				continue;
			}
			let img = jQuery(document.createElement("img"));
			img.attr("src", img_icon);
			let item = jQuery(document.createElement("li"));
			item.attr("id", key)
				.click(function() {
					_self.selectApplication(jQuery(this));
				})
				.append(img);
			if(item_count == 0) {
				this.selectApplication(item); // select first item by default
			}
			this.app_select.append(item);
			item_count++;
		}

		// Search input field
		this.search.addClass("et2_itempicker_search");
		this.search.keyup(function() {
			let request : any = {};
			request.term = jQuery(this).val();
			_self.query(request);
		});
		this.set_blur(this.options.blur, this.search);

		// Clear button for search
		this.clear
			.addClass("et2_itempicker_clear ui-icon ui-icon-close")
			.click(function(){
				_self.clearSearchResults();
			})
			.hide();

		// Action button
		this.button_action = <et2_button>et2_createWidget("button", {});
		jQuery(this.button_action.getDOMNode()).addClass("et2_itempicker_button_action");
		this.button_action.set_label(this.egw().lang(this.options.action_label));
		this.button_action.click = function() { _self.doAction(); };

		// Itemlist
		this.itemlist.attr("id", "itempicker_itemlist");
		this.itemlist.addClass("et2_itempicker_itemlist");

		// Put everything together
		this.left.append(this.app_select);
		this.right_container.append(this.search);
		this.right_container.append(this.clear);
		this.right_container.append(this.button_action.getDOMNode());
		this.right_container.append(this.itemlist);
		this.right.append(this.right_container);
		this.div.append(this.right); // right before left to have a natural
		this.div.append(this.left); // z-index for left div over right div

		this.setDOMNode(this.div[0]);
	}

	doAction()
	{
		if(this.action !== null)
		{
			let data : any = {};
			data.app = this.current_app;
			data.value = this.options.value;
			data.checked = this.getSelectedItems();
			return this.action.exec(this, data);
		}

		return false;
	}

	getSelectedItems()
	{
		let items = [];
		jQuery(this.itemlist).children("ul").children("li.selected").each(function(index) {
			items[index] = jQuery(this).attr("id");
		});
		return items;
	}

	/**
	 * Ask server for entries matching selected app/type and filtered by search string
	 */
	query(request)
	{
		if(request.term.length < 3) {
			return true;
		}
		// Remember last search
		this.last_search = request.term;

		// Allow hook / tie in
		if(this.options.query && typeof this.options.query == 'function')
		{
			if(!this.options.query(request, response)) return false;
		}

		//if(request.term in this.cache) {
		//	return response(this.cache[request.term]);
		//}

		this.itemlist.addClass("loading");
		this.clear.css("display", "inline-block");
		egw.json("EGroupware\\Api\\Etemplate\\Widget\\ItemPicker::ajax_item_search",
			[this.current_app, '', request.term, request.options],
			this.queryResults,
			this,true,this
		).sendRequest();
	}

	/**
	 * Server found some results for query
	 */
	queryResults(data)
	{
		this.itemlist.removeClass("loading");
		this.updateItemList(data);
	}

	selectApplication(app)
	{
		this.clearSearchResults();
		jQuery(".et2_itempicker_app_select li").removeClass("selected");
		app.addClass("selected");
		this.current_app = app.attr("id");
		return true;
	}

	set_blur(_value, input)
	{
		if(typeof input == 'undefined') input = this.search;

		if(_value)
		{
			input.attr("placeholder", _value);	// HTML5
			if(!input[0].placeholder)
			{
				// Not HTML5
				if(input.val() == "") input.val(_value);
				input.focus(input,function(e) {
					let placeholder = _value;
					if(e.data.val() == placeholder) e.data.val("");
				}).blur(input, function(e) {
					let placeholder = _value;
					if(e.data.val() == "") e.data.val(placeholder);
				});
				if(input.val() == "") input.val(_value);
			}
		}
		else
		{
			this.search.removeAttr("placeholder");
		}
	}

	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		_attrs["select_options"] = {};
		if(_attrs["application"])
		{
			let apps = et2_csvSplit(_attrs["application"], null, ",");
			for(let i = 0; i < apps.length; i++)
			{
				_attrs["select_options"][apps[i]] = this.egw().lang(apps[i]);
			}
		}
		else
		{
			_attrs["select_options"] = this.egw().link_app_list('query');
		}

		// Check whether the options entry was found, if not read it from the
		// content array.
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = this.getArrayMgr('content')
				.getEntry("options-" + this.id);
		}

		// Default to an empty object
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = {};
		}
	}

	updateItemList(data)
	{
		let list = jQuery(document.createElement("ul"));
		let item_count = 0;
		for(let id in data) {
			let item = jQuery(document.createElement("li"));
			if(item_count%2 == 0)
			{
				item.addClass("row_on");
			}
			else
			{
				item.addClass("row_off");
			}
			item.attr("id", id)
				.html(data[id])
				.click(function(e) {
					if(e.ctrlKey || e.metaKey)
					{
						// add to selection
						jQuery(this).addClass("selected");
					}
					else if(e.shiftKey)
					{
						// select range
						let start = jQuery(this).siblings(".selected").first();
						if(start?.length == 0)
						{
							// no start item - cannot select range - select single item
							jQuery(this).addClass("selected");
							return true;
						}
						let end = jQuery(this);
						// swap start and end if start appears after end in dom hierarchy
						if(start.index() > end.index())
						{
							let startOld = start;
							start = end;
							end = startOld;
						}
						// select start to end
						start.addClass("selected");
						start.nextUntil(end).addClass("selected");
						end.addClass("selected");
					}
					else
					{
						// select single item
						jQuery(this).siblings(".selected").removeClass("selected");
						jQuery(this).addClass("selected");
					}
				});
			list.append(item);
			item_count++;
		}
		this.itemlist.html(list);
	}
}
et2_register_widget(et2_itempicker, ["itempicker"]);
