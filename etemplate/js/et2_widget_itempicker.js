/**
 * eGroupWare eTemplate2 - JS Itempicker object
 * derived from et2_link_entry widget @copyright 2011 Nathan Gray
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Christian Binder
 * @author Nathan Gray
 * @copyright 2012 Christian Binder
 * @copyright 2011 Nathan Gray
 * @version $Id: et2_widget_itempicker.js 38623 2012-03-26 23:27:53Z jaytraxx $
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * Class which implements the "itempicker" XET-Tag
 */ 
var et2_itempicker = et2_inputWidget.extend({

	attributes: {
		"value": {
			"type": "any",
			"default": {}
		},
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": et2_no_init,
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
		"query": {
			"name": "Query callback",
			"type": "any",
			"default": false,
			"description": "Callback before query to server.  Must return true, or false to abort query."
		},
		"select": {
			"name": "Select callback",
			"type": "any",
			"default": false,
			"description": "Callback when user selects an option.  Must return true, or false to abort normal action."
		},
	},

	legacyOptions: ["application"],
	search_timeout: 200, //ms after change to send query
	minimum_characters: 2, // Don't send query unless there's at least this many chars
	last_search: "",	// Remember last search value
	current_app: "",	// Remember currently chosen application

	init: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.left = null;
		this.right = null;
		this.right_container = null;
		this.app_select = null;
		this.search = null;
		this.itemlist = null;

		this.createInputWidget();
	},

	createInputWidget: function() {
		var _self = this;
		
		this.div = $j(document.createElement("div"));
		this.left = $j(document.createElement("div"));
		this.right = $j(document.createElement("div"));
		this.right_container = $j(document.createElement("div"));
		this.app_select = $j(document.createElement("ul"));
		this.search = $j(document.createElement("input"));
		this.clear = $j(document.createElement("span"));
		this.itemlist = $j(document.createElement("div"));
			
		// Container elements
		this.div.addClass("et2_itempicker");
		this.left.addClass("et2_itempicker_left");
		this.right.addClass("et2_itempicker_right");
		this.right_container.addClass("et2_itempicker_right_container");
		
		// Application select
		this.app_select.addClass("et2_itempicker_app_select");
		var item_count = 0;
		for(var key in this.options.select_options) {
			var img_icon = this.egw().image(key + "/navbar");
			if(img_icon === null) {
				continue;
			}
			var img = $j(document.createElement("img"));
			img.attr("src", img_icon);
			var item = $j(document.createElement("li"))
			item.attr("id", key)
				.click(function() { 
					$j(".et2_itempicker_app_select li").removeClass("selected");
					$j(this).addClass("selected");
					_self.current_app = $j(this).attr("id");
				})
				.append(img);
			if(item_count == 0) {
				item.addClass("selected"); // select first item by default
				this.current_app = key;
			}
			this.app_select.append(item);
			item_count++;
		}
		
		// Search input field
		this.search.addClass("et2_itempicker_search");
		this.search.keyup(function() {
			var request = {};
			request.term = $j(this).val();
			_self.query(request);
		});
		
		// Clear button for search
		this.clear
			.addClass("ui-icon ui-icon-close")
			.click(function(e){
				_self.search.val("");
				_self.itemlist.html("");
				_self.search.focus();
			})
			.hide();
		
		// Itemlist
		this.itemlist.attr("id", "itempicker_itemlist");
		this.itemlist.addClass("et2_itempicker_itemlist");
		
		// Put everything together
		this.left.append(this.app_select);
		this.right_container.append(this.search);
		this.right_container.append(this.clear);
		this.right_container.append(this.itemlist);
		this.right.append(this.right_container);
		this.div.append(this.right); // right before left to have a natural 
		this.div.append(this.left); // z-index for left div over right div

		this.setDOMNode(this.div[0]);
	},

	getValue: function()
	{
		if(this.options.blur && this.input.val() == this.options.blur) return "";
		return this._super.apply(this, arguments);
	},
	
	/**
	 * Ask server for entries matching selected app/type and filtered by search string
	 */
	query: function(request) {
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
		this.clear.show();
		var request = new egw_json_request("etemplate_widget_link::ajax_link_search::etemplate", 
			[this.current_app, '', request.term, request.options],
			this
		);
		
		request.sendRequest(true, this._results, this);
	},
	
	/**
	 * Server found some results
	 */
	_results: function(data) {
		this.itemlist.removeClass("loading");
		this.updateItemList(data);
	},
	
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		_attrs["select_options"] = {};
		if(_attrs["application"])
		{
			var apps = et2_csvSplit(_attrs["application"], null, ",");
			for(var i = 0; i < apps.length; i++)
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
				.getEntry("options-" + this.id)
		}

		// Default to an empty object
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = {};
		}
	},
	
	updateItemList: function(data) {
		var list = $j(document.createElement("ul"));
		var item_count = 0;
		for(var id in data) {
			var item = $j(document.createElement("li"));
			item.attr("id", id);
			if(item_count%2 == 0) {
				item.addClass("row_on");
			} else {
				item.addClass("row_off");
			}
			item.html(data[id]);
			list.append(item);
			item_count++;
		}
		this.itemlist.html(list);
	}

});

et2_register_widget(et2_itempicker, ["itempicker"]);
