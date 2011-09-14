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
 		"no_files": {
			"name": "No files",
			"type": "boolean",
			"default": false,
			"description": "Suppress attach-files"
		},
 		"search_label": {
			"name": "Search label",
			"type": "string",
			"default": "",
			"description": "Label to use for search"
		},
		"link_label": {
			"name": "Link label",
			"type": "string",
			"default": "Link",	
			"description": "Label for the link button"
		}
	},

	search_timeout: 200, //ms after change to send query
	minimum_characters: 2, // Don't send query unless there's at least this many chars

	init: function() {
		this._super.apply(this, arguments);

		this.div = null;

		this.link_button = null;
		this.status_span = null;
		this.comment = null;

		this.link_entry = null;
		this.file_upload = null;

		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.link_button = null;
		this.status_span = null;
		this.comment = null;

		this.link_entry.destroy();
		this.link_entry = null;
		this.file_upload.destroy();
		this.file_upload = null;
		this.div = null;
	},

	/**
	 * Override to provide proper node for sub widgets to go in
	 */
	getDOMNode: function(_sender) {
		if(_sender == this) {
			return this.div[0];
		} else if (_sender._type == 'link-entry') {
			return this.link_div[0];
		} else if (_sender._type == 'file') {
			return this.file_div[0];

		}
	},

	createInputWidget: function() {
		this.div = $j(document.createElement("div")).addClass("et2_link_to");

		// One common link button
		this.link_button = $j(document.createElement("button"))
			.text(egw.lang(this.options.link_label))
			.appendTo(this.div).hide()
			.click(this, this.createLink);
		
		// Span for indicating status
		this.status_span = $j(document.createElement("span"))
			.appendTo(this.div).addClass("status").hide();

		// Need a div for link-to widget
		this.link_div = $j(document.createElement("div")).appendTo(this.div);

		// Link comment field
		this.comment = $j(document.createElement("input"))
			.css("display", "block").css("width","89%")
			.appendTo(this.div).hide();
		et2_link_entry.prototype.set_blur(egw.lang("Comment..."),this.comment);

		// Need a div for file upload widget
		this.file_div = $j(document.createElement("div")).appendTo(this.div);

		this.setDOMNode(this.div[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		var self = this;

		// Link-to
		var link_entry_attrs = {
			id: this.id + '_link_entry',
			blur: this.options.search_label ? this.options.search_label : egw.lang('Search...'),
			query: function() { self.link_button.hide(); self.comment.hide(); return true;},
			select: function() {self.link_button.show(); self.comment.show(); return true;}
		}
		this.link_entry = et2_createWidget("link-entry", link_entry_attrs,this);

		// File upload
		var file_attrs = {
			multiple: true,
			id: this.id + '_file',
			onFinish: function(event, file_count) {
				self.filesUploaded(event);
			}
		};

		this.file_upload = et2_createWidget("file", file_attrs,this);

		return true;
	},

	getValue: function() {
		return this.options.value;
	},

	filesUploaded: function(event) {
		var self = this;

		this.link_button.show();

		// Add some comment fields
		this.file_upload.progress.children().each(function() {
			var comment = jQuery(document.createElement("input"))
				.appendTo(this).hide();
			self.link_entry.set_blur(egw.lang("Comment..."),comment);

			var comment_icon = jQuery(document.createElement("span"))
				.appendTo(this)
				.addClass("ui-icon ui-icon-comment")
				.click(function() {comment_icon.hide(); comment.toggle();})
		});
	},

	/**
	 * Create a link using the current internal values
	 */
	createLink: function(event) {
		// Disable link button
		event.data.link_button.attr("disabled", true);

		var values = event.data.options.value;
		var self = event.data;

		var links = [];

		// Links to other entries
		event.data = self.link_entry;
		self.link_entry.createLink(event,links);
		// Add comment
		if(links.length > 0 && self.comment.val() && self.comment.val() != self.comment.attr("placeholder"))
		{
			for(var i = 0; i < links.length; i++)
			{
				links[i].remark = self.comment.val();
			}
			self.comment.val(self.comment.attr("placeholder"));
		}
		
		// Files
		for(var file in self.file_upload.options.value) {
			
			links.push({
				app: 'file',
				id: file,
				name: self.file_upload.options.value[file].name,
				type: self.file_upload.options.value[file].type,
				remark: jQuery("li[file='"+self.file_upload.options.value[file].name+"'] > input", self.file_upload.progress)
					.filter(function() { return jQuery(this).attr("placeholder") != jQuery(this).val();}).val()
			});
		}
		
		var request = new egw_json_request("etemplate_widget_link::ajax_link::etemplate", 
			[values.to_app, values.to_id, links],
			this
		);
		request.sendRequest(true, self._link_result, self);
	},

	/**
	 * Sent some links, server has a result
	 */
	_link_result: function(success) {
		if(success) {
			this.comment.hide();
			this.link_button.hide().attr("disabled", false);
			this.status_span.fadeIn().delay(1000).fadeOut();
			delete this.options.value.app;
			delete this.options.value.id;
			for(var file in this.file_upload.options.value) {
				delete this.file_upload.options.value[file];
			}
			this.file_upload.progress.empty();

			// Look for a link-list with the same ID, refresh it
			var self = this;
			this.getRoot().iterateOver(
				function(widget) {
					if(widget.id == self.id) {
						widget._get_links();
					}
				},
				this, et2_link_list
			);
		}
	}
});
et2_register_widget(et2_link_to, ["link-to"]);

var et2_link_entry = et2_valueWidget.extend({

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

	search_timeout: 200, //ms after change to send query
	minimum_characters: 2, // Don't send query unless there's at least this many chars

	init: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.search = null;
		this.app_select = null;
		this.comment = null;

		if(typeof this.options.value == 'undefined') this.options.value = {};
		this.cache = {};

		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.search.autocomplete("destroy");
		this.search = null;
		this.app_select = null;
	},

	createInputWidget: function() {
		var self = this;
		this.div = $j(document.createElement("div")).addClass("et2_link_entry");

		// Application selection
		this.app_select = $j(document.createElement("select")).appendTo(this.div)
			.change(function(e) {
				self.cache = {}; // Clear cache when app changes
				self.options.value.app = self.app_select.val();
			})
			.css("width","39%");
		for(var key in this.options.select_options) {
			var option = $j(document.createElement("option"))
				.attr("value", key)
				.text(this.options.select_options[key]);
			option.appendTo(this.app_select);
		}
		self.options.value.app = this.app_select.val();

		// Search input
		this.search = $j(document.createElement("input")).attr("type", "search")
			.css("width","50%")
			.appendTo(this.div);

		this.set_blur(this.options.blur ? this.options.blur : egw.lang("search"), this.search);
		
		// Autocomplete
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

	getDOMNode: function() {
		return this.div[0];
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

	set_blur: function(_value, input) {

		if(typeof input == 'undefined') input = this.search;

		if(_value) {
			input.attr("placeholder", _value);	// HTML5
			if(!input[0].placeholder) {
				// Not HTML5
				if(input.val() == "") input.val(_value);
				input.focus(input,function(e) {
					var placeholder = _value;
					if(e.data.val() == placeholder) e.data.val("");
				}).blur(input, function(e) {
					var placeholder = _value;
					if(e.data.val() == "") e.data.val(placeholder);
				});
				if(input.val() == "") input.val(_value);
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

		// Allow hook / tie in
		if(this.options.query && typeof this.options.query == 'function')
		{
			if(!this.options.query(request, response)) return false;
		}
		this.search.addClass("loading");
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
		if(this.options.select && typeof this.options.select == 'function')
		{
			if(!this.options.select(event, selected)) return false;
		}
		event.data.options.value.id = selected.item.value;
		event.data.search.val(selected.item.label);

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
	createLink: function(event, _links) {

		var values = event.data.options.value;
		var self = event.data;

		if(typeof _links == 'undefined')
		{
			links = [];
		}
		else
		{
			links = _links;
		}

		// Links to other entries
		if(values.id) {
			links.push({
				app: values.app,
				id: values.id,
			});
			self.search.val("");
		}
		
		// If a link array was passed in, don't make the ajax call
		if(typeof _links == 'undefined')
		{
			var request = new egw_json_request("etemplate_widget_link::ajax_link::etemplate", 
				[values.to_app, values.to_id, links],
				this
			);
			request.sendRequest(true, self._link_result, self);
		}
	},

	/**
	 * Sent some links, server has a result
	 */
	_link_result: function(success) {
		if(success) {
			this.comment.hide();
			this.link_button.hide().attr("disabled", false);
			this.status_span.fadeIn().delay(1000).fadeOut();
			delete this.options.value.app;
			delete this.options.value.id;
		}
	}
});
et2_register_widget(et2_link_entry, ["link-entry"]);

/**
 * UI widget for a single (read-only) link
 */ 
var et2_link = et2_valueWidget.extend([et2_IDetachedDOM], {
	attributes: {
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Use the given application, so you can pass just the ID for value"
		},
		"value": {
			description: "Array with keys app, id, and optionally title",
			type: "any"
		}
	},
	init: function() {
		this._super.apply(this, arguments);

		this.node = $j(document.createElement("span"))
			.addClass("et2_link");

		if(this.options.class) this.node.addClass(this.options.class);
		this.setDOMNode(this.node[0]);
	},
	destroy: function() {
		this.node.unbind();
		this.node = null;
	},

	set_value: function(_value) {
		if(!_value || !_value.title) {
			this.node.text("").unbind();
			return;
		}
		if(typeof _value != 'object' && !this.options.application)
		{
			console.warn("Bad value for link widget.  Need an object with keys 'app', 'id', and optionally 'title'", _value);
			return;
		}
		if(!_value.title) {
			var title = egw.link_title(_value.id, _value.app, this.set_value, this);
			if(title != null) {
				_value.title = title;
			}
			else
			{
				// Title will be fetched from server and then set
				return;
			}
		}

		this.node.text(_value.title).unbind()
			.click( function(){egw.open(_value.id, _value.app, "edit", _value.extra);});
	},

	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes: function() {
		return [this.node];
	},

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which have to be in the same order as
	 *      the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 *      returned by the "getDetachedAttributes" function and sets them to the
	 *      given values.
	 */
	setDetachedAttributes: function(_nodes, _values) {
		this.transformAttributes(_values);
		this.node = $j(_nodes[0]);
		this.set_value(_values["value"]);
	}

});
et2_register_widget(et2_link, ["link"]);

/**
 * UI widget for one or more links, comma separated
 */ 
var et2_link_string = et2_valueWidget.extend([et2_IDetachedDOM], {
	attributes: {
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Use the given application, so you can pass just the ID for value"
		},
		"value": {
			"description": "Either an array of link information (see egw_link::link()) or array with keys to_app and to_id",
			"type": "any"
		},
		"only_app": {
			"name": "Application filter",
			"type": "string",
			"default": "",
			"description": "Appname, eg. 'projectmananager' to list only linked projects"
		},
		"link_type": {
			"name": "Type filter",
			"type": "string",
			"default":"",
			"description": "Sub-type key to list only entries of that type"
		}
	},
	init: function() {
		this._super.apply(this, arguments);

		this.list = $j(document.createElement("ul"))
			.addClass("et2_link_string");

		if(this.options.class) this.node.addClass(this.options.class);
		this.setDOMNode(this.list[0]);
	},

	destroy: function() {
		this._super.apply(this, arguments);
		this.node.children().unbind();
	},

	set_value: function(_value) {
		// Get data
		if(!_value || _value == null) return;
		if(!_value.to_app && this.options.application) _value.to_app = this.options.application;

		if(typeof _value == 'object' && _value.to_app && _value.to_id)
		{
			this.value = _value;
			this._get_links();
			return;
		}
		if(_value.length > 0) {
			// Have full info
			// Don't store new value, just update display

			this.list.empty();

			// Make new links
			for(var i = 0; i < _value.length; i++)
			{
				if(!this.options.only_app || this.options.only_app && _value[i].app == this.options.only_app)
				{
					this._add_link(_value[i]);
				}
			}
		}
	},

	_get_links: function() {
		var _value = this.value;
		// Just IDs - get from server
		if(this.options.only_app)
		{
			_value.only_app = this.options.only_app;
		}
		egw.jsonq('etemplate.etemplate_widget_link.ajax_link_list', [_value], this.set_value, this);
		return;
	},

	_add_link: function(_link_data) {
		if(!_link_data.title) {
			// No callback yet, need something to do with it
			var title = egw.link_title(_link_data.app, _link_data.id);
			// Need to set it to something, or call to text() will return current value
			if(title == null || title == false) _link_data.title = "";
		}
		var link = $j(document.createElement("li"))
			.appendTo(this.list)
			.text(_link_data.title)
			.addClass("et2_link")
			.click( function(){egw.open(_link_data.id, _link_data.app, "edit", _link_data.extra);});

		// Now that link is created, get title from server & update
		if(!_link_data.title) {
			egw.link_title(_link_data.app, _link_data.id, function(title) {this.text(title);}, link);
		}
	},

	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes: function() {
		return [this.list[0]];
	},

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which have to be in the same order as
	 *      the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 *      returned by the "getDetachedAttributes" function and sets them to the
	 *      given values.
	 */
	setDetachedAttributes: function(_nodes, _values) {
		this.list = $j(_nodes[0]);
		if(!_values.value) this.transformAttributes(_values);
		this.set_value(_values["value"]);
	}
});
et2_register_widget(et2_link_string, ["link-string"]);

/**
 * UI widget for one or more links in a list (table)
 */ 
var et2_link_list = et2_link_string.extend({
	attributes: {
		"show_deleted": {
			"name": "Show deleted",
			"type": "boolean",
			"default": false,
			"description": "Show links that are marked as deleted, being held for purge"
		}
	},
	init: function() {
		this._super.apply(this, arguments);

		this.list = $j(document.createElement("table"))
			.addClass("et2_link_list");
		if(this.options.class) this.node.addClass(this.options.class);
		this.setDOMNode(this.list[0]);
	},

	_add_link: function(_link_data) {
		var row = $j(document.createElement("tr"))
			.appendTo(this.list)

		// Icon
		//TODO: Needs vfs widget
		var icon = $j(document.createElement("td"))
			.appendTo(row)
			.addClass("icon");
		if(_link_data.icon)
		{
			var icon_widget = et2_createWidget("image");
			icon_widget.set_src(_link_data.icon);
			icon.append(icon_widget.getDOMNode());
		}
		
		var columns = ['app','title','remark'];
		for(var i = 0; i < columns.length; i++) {
			$j(document.createElement("td"))
				.appendTo(row)
				.addClass(columns[i])
				.text(_link_data[columns[i]])
				.click( function(){egw.open(_link_data.id, _link_data.app, "edit", _link_data.extra);});
		}

		// Date
		/*
		var date_row = $j(document.createElement("td"))
			.appendTo(row);
		if(_link_data.lastmod)
		{
			var date_widget = et2_createWidget("date-since");
			date_widget.set_value(_link_data.lastmod);
			date_row.append(date_widget.getDOMNode());
		}
		*/

		// Delete
		var delete_button = $j(document.createElement("td"))
			.appendTo(row)
			.addClass("delete icon")
			.bind( 'click', function(){
				delete_button.addClass("loading").removeClass("delete");
				new egw_json_request("etemplate.etemplate_widget_link.ajax_delete", [_link_data.link_id])
					.sendRequest(true, function(data) { if(data) {row.slideUp(row.remove);}});
			});
	}
});
et2_register_widget(et2_link_list, ["link-list"]);
