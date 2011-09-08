/**
 * eGroupWare eTemplate2 - JS Link object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	jquery.jquery-ui;
	et2_core_inputWidget;
	et2_core_valueWidget;
*/

/**
 * UI widgets for Egroupware linking system
 */ 
var et2_link_to = et2_inputWidget.extend({

	attributes: {
		"application": {
			"name": "Application",
			"type": "string",
			"default": egw_getAppName(),
			"description": "Limit to the listed application or applications (comma seperated)"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": "",
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
		},
	},

	search_timeout: 200, //ms after change to send query
	minimum_characters: 2, // Don't send query unless there's at least this many chars

	init: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.search = null;
		this.app_select = null;

		this.cache = {};

		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.search.autocomplete("destroy");
		this.search = null;
		this.app_select = null;

		this.createInputWidget();
	},

	createInputWidget: function() {
		var self = this;
		this.div = $j(document.createElement("div"));

		// Application selection
		this.app_select = $j(document.createElement("select")).appendTo(this.div)
			.change(function(e) {
				self.cache = {}; // Clear cache when app changes
				self.options.value.app = this.val();
			});
		for(var key in this.options.select_options) {
			var option = $j(document.createElement("option"))
				.attr("value", key)
				.text(this.options.select_options[key]);
			option.appendTo(this.app_select);
		}
		self.options.value.app = this.app_select.val();

		// Search input
		this.search = $j(document.createElement("input")).attr("type", "search").appendTo(this.div);

		// Link button
		this.link_button = $j(document.createElement("button"))
			.text(egw.lang("link"))
			.appendTo(this.div).hide()
			.click(this, this.createLink);

		this.set_blur(this.blur ? this.blur : egw.lang("search"));

		this.search.autocomplete({
			source: function(request, response) { return self.query(request, response);},
			select: function(event, item) { event.data = self; self.select(event,item); return false;},
			focus: function(event, item) { 
				event.stopPropagation(); 
				self.search.val(item.item.label);
				return false;
			},
			minLength: self.minimum_characters,
			disabled: self.options.disabled
		});
		this.setDOMNode(this.div[0]);
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		_attrs["select_options"] = egw.link_app_list('query');

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

	getValue: function() {
		return this.options.value;
	},

	set_blur: function(_value) {
		if(_value) {
			this.search.attr("placeholder", _value);	// HTML5
			if(!this.search[0].placeholder) {
				// Not HTML5
				if(this.search.val() == "") this.search.val(this.options.blur);
				this.search.focus(this,function(e) {
					if(e.data.search.val() == e.data.options.blur) e.data.search.val("");
				}).blur(this, function(e) {
					if(e.data.search.val() == "") e.data.search.val(e.data.options.blur);
				});
			}
		} else {
			this.search.removeAttr("placeholder");
		}
	},
	
	/**
	 * Ask server for entries matching selected app/type and filtered by search string
	 */
	query: function(request, response) {
		if(request.term in this.cache) {
			return response(this.cache[request.term]);
		}
		this.search.addClass("loading");
		this.link_button.hide();
		var request = new egw_json_request("etemplate_widget_link::ajax_link_search::etemplate", 
			[this.app_select.val(), '', request.term],
			this
		);
		this.response = response;
		request.sendRequest(true, this._results, this);
	},

	/**
	 * User selected a value
	 */
	select: function(event, selected) {
		event.data.options.value.id = selected.item.value;
		event.data.search.val(selected.item.label);
		event.data.link_button.show();
	},

	/**
	 * Server found some results
	 */
	_results: function(data) {
		this.search.removeClass("loading");
		var result = [];
		for(var id in data) {
			result.push({"value": id, "label":data[id]});
		}
		this.cache[this.search.val()] = result;
		this.response(result);
	},

	/**
	 * Create a link using the current internal values
	 */
	createLink: function(event) {
		console.info("Link: ",event.data.options.value);
		event.data.link_button.hide();
	}
});

et2_register_widget(et2_link_to, ["link-to"]);


