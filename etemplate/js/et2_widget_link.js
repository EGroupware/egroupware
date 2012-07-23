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

	// Include menu system for list context menu
        egw_action.egw_menu_dhtmlx;
*/

/**
 * UI widgets for Egroupware linking system
 */ 
var et2_link_to = et2_inputWidget.extend({

	attributes: {
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
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
		},
		"value": {
			// Could be string or int if application is provided, or an Object
			"type": "any"
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
		this.link_button = null;
		this.status_span = null;
		this.comment = null;

		this.link_entry.destroy();
		this.link_entry = null;
		this.file_upload.destroy();
		this.file_upload = null;
		this.div = null;

		this._super.apply(this, arguments);
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
			.text(this.egw().lang(this.options.link_label))
			.appendTo(this.div).hide()
			.click(this, this.createLink);
		
		// Span for indicating status
		this.status_span = $j(document.createElement("span"))
			.appendTo(this.div).addClass("status").hide();

		// Need a div for link-to widget
		this.link_div = $j(document.createElement("div")).css("margin-bottom", "1ex").appendTo(this.div);

		// Link comment field
		this.comment = $j(document.createElement("input"))
			.css("display", "block").css("width","89%")
			.appendTo(this.div).hide();
		et2_link_entry.prototype.set_blur(this.egw().lang("Comment..."),this.comment);

		// Filemanager link popup
		var self = this;
		this.filemanager_button = $j(document.createElement("img"))
			.attr("src", this.egw().image("filemanager/navbar"))
			.addClass("et2_button et2_button_icon")
			.appendTo(this.div)
			.click(this, function(e) {
				// Open the filemanager select in a popup
				var values = e.data.options.value;
				var popup = e.data.egw().open_link(
					'/index.php?menuaction=filemanager.filemanager_select.select&mode=open-multiple&method=etemplate_widget_link::link_existing&label=link&id=' + values.to_app + ":" + values.to_id,
					'link_existing',
					'640x580'
				);
				if(popup)
				{
					// Update on close doesn't always (ever, in chrome) work, so poll
					var poll = self.egw().window.setInterval(
						function() {
							if(popup.closed) {
								self.getRoot().iterateOver(
									function(widget) {
										if(widget.id == self.id) {
											widget._get_links();
										}
									},
									self, et2_link_list
								)
								self.egw().window.clearInterval(poll);
							}
						},1000);
					
				}
			});

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
			blur: this.options.search_label ? this.options.search_label : this.egw().lang('Search...'),
			query: function() { self.link_button.hide(); self.comment.hide(); return true;},
			select: function() {self.link_button.show(); self.comment.show(); return true;}
		}
		this.link_entry = et2_createWidget("link-entry", link_entry_attrs,this);

		// File upload
		var file_attrs = {
			multiple: true,
			id: this.id + '_file',
			onFinish: function(event, file_count) {
				event.data = self;
				self.filesUploaded(event);

				// Auto-link uploaded files
				if(self.options.value.to_id) self.createLink(event);
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

var et2_link_apps = et2_selectbox.extend({
	attributes: {
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		}
	},

	init: function() {
		this._super.apply(this, arguments);

		var select_options = {};

		// Limit to one app
		if(this.options.application) {
			select_options[_attrs.application] = this.egw().lang(_attrs.application);
		} else {
			select_options = this.egw().link_app_list('query');

			// Check whether the options entry was found, if not read it from the
			// content array.
			if (select_options == null)
			{
				select_options = this.getArrayMgr('content')
					.getEntry("options-" + this.id)
			}

			// Default to an empty object
			if (select_options == null)
			{
				select_options = {};
			}
		}
		this.set_select_options(select_options);
	}
});
et2_register_widget(et2_link_apps, ["link-apps"]);

var et2_link_entry = et2_inputWidget.extend({

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

	init: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.search = null;
		this.app_select = null;
		this._oldValue = {id: null, app: this.options.application};

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
				if(typeof self.options.value != 'object') self.options.value = {};
				self.options.value.app = self.app_select.val();
			});
		var opt_count = 0;
		for(var key in this.options.select_options) {
			opt_count++;
			var option = $j(document.createElement("option"))
				.attr("value", key)
				.text(this.options.select_options[key]);
			option.appendTo(this.app_select);
		}
		this.app_select.val(this.options.application);
		if(opt_count == 1) 
		{
			this.app_select.hide();
			this.div.addClass("no_app");
		}
		self.options.value.app = this.app_select.val();

		// Search input
		this.search = $j(document.createElement("input")) 
			// .attr("type", "search") // Fake it for all browsers below
			.focus(function(){if(!self.options.application) {
				// Adjust width, leave room for app select & link button
				self.div.removeClass("no_app");self.app_select.show();
			}})
			.appendTo(this.div);

		this.set_blur(this.options.blur ? this.options.blur : this.egw().lang("search"), this.search);
		
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
		
		// Custom display (colors)
		this.search.data("autocomplete")._renderItem = function(ul, item) {
			var li = jQuery(document.createElement('li'))
				.data("item.autocomplete", item);
			var extra = {};

			// Extra stuff
			if(typeof item.label == 'object') {
				extra = item.label;
				item.label = extra.label ? extra.label : extra;
				if(extra['style.backgroundColor'] || extra.color)
				{
					li.css('backgroundColor', extra.color ? extra.color : extra['style.backgroundColor']);
				}
				// Careful with this, some browsers may have trouble loading all at once, which can slow display
				if(extra.icon)
				{
					var img = self.egw().image(extra.icon);
					if(img)
					{
						jQuery(document.createElement("img"))
							.attr("src", img)
							.css("float", "right")
							.appendTo(li);
					}
				}
			}

			// Normal stuff
			li.append(jQuery( "<a></a>" ).text( item.label ))
				.appendTo(ul);

			return li;
		};

		// Bind to enter key to start search early
		this.search.keydown(function(e) {
			var keycode = (e.keyCode ? e.keyCode : e.which);
			if(keycode == '13')
			{
				self.search.autocomplete("option","minLength", 0);
				self.search.autocomplete("search");
				self.search.autocomplete("option","minLength", self.minimum_characters);
				e.stopPropagation();
			}
		});

		// Clear / last button
		this.clear = $j(document.createElement("span"))
			.addClass("ui-icon ui-icon-close")
			.click(function(e){
				// No way to tell if the results is open, so if they click the button while open, it clears
				if(self.last_search && self.last_search != self.search.val())
				{
					// Repeat last search (should be cached)
					self.search.val(self.last_search);
					self.last_search = "";
					self.search.autocomplete("search");
				}
				else
				{
					// Clear
					self.search.autocomplete("close");
					self.set_value("");
					self.search.trigger("change");
				}
				self.search.focus();
			})
			.appendTo(this.div)
			.hide();

		this.setDOMNode(this.div[0]);
	},

	getDOMNode: function() {
		return this.div[0];
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

	getValue: function() {
		return this.options.application ? this.options.value.id : this.options.value;
	},

	set_value: function(_value) {
		if(typeof _value == 'string')
		{
			if(_value.indexOf(",") > 0) _value = _value.replace(",",":");
			if(_value.indexOf(":") >= 0)
			{
				var split = et2_csvSplit(_value, 2,":");

				_value = {
					app: split[0],
					id: split[1]
				};
			}
			else if(_value && this.options.application)
			{
				_value = {
					app: this.options.application,
					id: _value
				};
			}
		}
		this._oldValue = this.options.value;
		if(!_value || _value.length == 0 || _value == null || jQuery.isEmptyObject(_value))
		{
			this.search.val("");
			this.clear.hide();
			this.options.value = _value = {'id':null};
		}
		if(!_value.app) _value.app = this.options.application;

		if(_value.id) {
			this.clear.show();
		} else {
			this.clear.hide();
			return;
		}
		if(typeof _value != 'object' || (!_value.app && !_value.id))
		{
			console.warn("Bad value for link widget.  Need an object with keys 'app', 'id', and optionally 'title'", _value);
			return;
		}
		if(!_value.title) {
			var title = this.egw().link_title(_value.app, _value.id);
			if(title != null) {
				_value.title = title;
			}
			else
			{
				// Title will be fetched from server and then set
				var title = this.egw().link_title(_value.app, _value.id, function(title) {
					this.search.removeClass("loading").val(title+"");
					this.clear.show();
				}, this);
				this.search.addClass("loading");
			}
		}
		if(_value.title)
		{
			this.search.val(_value.title+"");
		}
		this.options.value = _value;
		
		jQuery("option[value='"+_value.app+"']",this.app_select).attr("selected",true);
		this.app_select.hide();
		this.div.addClass("no_app");
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
		// Remember last search
		this.last_search = this.search.val();

		// Allow hook / tie in
		if(this.options.query && typeof this.options.query == 'function')
		{
			if(!this.options.query(request, response)) return false;
		}

		if(request.term in this.cache) {
			return response(this.cache[request.term]);
		}

		this.search.addClass("loading");
		this.clear.show();
		var request = new egw_json_request("etemplate_widget_link::ajax_link_search::etemplate", 
			[this.app_select.val(), '', request.term, request.options],
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
		if(typeof event.data.options.value != 'object' || event.data.options.value == null)
		{
			event.data.options.value = {};
		}
		event.data.options.value.id = selected.item.value;

		this.clear.show();
		event.data.search.val(selected.item.label);

		// Fire change event
		this.search.change();
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
		var links = [];

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
	legacyOptions: ["application"],
	init: function() {
		this._super.apply(this, arguments);

		this.link = $j(document.createElement("span"))
			.addClass("et2_link");

		if(this.options['class']) this.link.addClass(this.options['class']);
		this.setDOMNode(this.link[0]);
	},
	destroy: function() {
		if(this.link) this.link.unbind();
		this.link = null;
	},

	set_value: function(_value) {
		if(typeof _value != 'object' && _value && !this.options.application)
		{
			if(_value.indexOf(':') >= 0)
			{
				var app = _value.split(':',1);
				var id = _value.substr(app[0].length+1);
				_value = {'app': app[0], 'id': id};
			}
			else
			{
				console.warn("Bad value for link widget.  Need an object with keys 'app', 'id', and optionally 'title'", _value);
				return;
			}
		}
		// Application set, just passed ID
		else if (typeof _value != "object")
		{
			_value = {
				app:	this.options.application,
				id:	_value
			};
		}
		if(!_value || jQuery.isEmptyObject(_value)) {
			this.link.text("").unbind();
			return;
		}
		if(!_value.title) {
			var self = this;
			var node = this.link[0];
			var title = this.egw().link_title(_value.app, _value.id, function(title) {self.set_title(node, title);}, this);
			if(title != null) {
				_value.title = title;
			}
			else
			{
				// Title will be fetched from server and then set
				return;
			}
		}
		this.set_title(this.link, _value.title);
		var self = this;
		this.link.unbind()
			.click( function(){self.egw().open(_value, "", "edit");});
	},

	/**
	 * Sets the text to be displayed.
	 * Used as a callback, so node is provided to make sure we get the right one
	 */
	set_title: function(node, _value) {
		if(_value === false || _value === null) _value = "";
		jQuery(node).text(_value+"");
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
		this.link = jQuery(_nodes[0]);
		if(typeof _values["id"] !== "undefined") this.set_id(_values['id']);
		if(typeof _values["value"] !== "undefined" && typeof _values["value"].title !== "undefined")
		{
			// Direct route
			this.set_title(_nodes[0], _values["value"].title);
		}
		else
		{
			this.set_value(_values["value"]);
		}
	}

});
et2_register_widget(et2_link, ["link", "link-entry_ro"]);

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

		if(this.options['class']) this.list.addClass(this.options['class']);
		this.setDOMNode(this.list[0]);
	},

	destroy: function() {
		this._super.apply(this, arguments);
		if (this.node != null) {
			this.node.children().unbind();
		}
	},

	set_value: function(_value) {
		// Get data
		if(!_value || _value == null) 
		{
			this.list.empty();
			return;
		}
		if(typeof _value == "string" && _value.indexOf(',') > 0)
		{
			_value = _value.split(',');
		}
		if(!_value.to_app && typeof _value == "object" && this.options.application) 
		{
			_value.to_app = this.options.application;
		}

		if(typeof _value == 'object' && _value.to_app && _value.to_id)
		{
			this.value = _value;
			this._get_links();
			return;
		}
		this.list.empty();
		if(typeof _value == 'object' && _value.length > 0) {
			// Have full info
			// Don't store new value, just update display


			// Make new links
			for(var i = 0; i < _value.length; i++)
			{
				if(!this.options.only_app || this.options.only_app && _value[i].app == this.options.only_app)
				{
					this._add_link(_value[i].id ? _value[i] : {id:_value[i], app: _value.to_app});
				}
			}
		}
		else if(this.options.application)
		{
			this._add_link({id:_value, app: this.options.application});
		}
	},

	_get_links: function() {
		var _value = this.value;
		// Just IDs - get from server
		if(this.options.only_app)
		{
			_value.only_app = this.options.only_app;
		}
		this.egw().jsonq('etemplate.etemplate_widget_link.ajax_link_list', [_value], this.set_value, this);
		return;
	},

	_add_link: function(_link_data) {
		var self = this;
		var link = $j(document.createElement("li"))
			.appendTo(this.list)
			.addClass("et2_link loading")
			.click( function(){self.egw().open(_link_data, "", "edit");});

		if(_link_data.title) link.text(_link_data.title);

		// Now that link is created, get title from server & update
		if(!_link_data.title) {
			this.egw().link_title(_link_data.app, _link_data.id, function(title) {
				this.removeClass("loading").text(title);
			}, link);
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
		if(this.options['class']) this.node.addClass(this.options['class']);
		this.setDOMNode(this.list[0]);

		// Set up context menu
		var self = this;
		this.context = new egwMenu();
		this.context.addItem("comment", this.egw().lang("Comment"), "", function() {
			var link_id = self.context.data.link_id;
			var comment = prompt(self.egw().lang("Comment"));
			if(comment !== null)
			{
				var remark = jQuery('#link_'+link_id, self.list).children('.remark');
				remark.addClass("loading");
				var request = new egw_json_request("etemplate_widget_link::ajax_link_comment::etemplate", 
					[link_id, comment],
					this
				);
				request.sendRequest(true, function() {
					if(remark)
					{
						remark.removeClass("loading").text(comment+"");
					}
				}, self);
			}
		});
		this.context.addItem("file_info", this.egw().lang("File information"), this.egw().image("edit"), function(menu_item) {
			var link_data = self.context.data;
			if(link_data.app == 'file')
			{
				var url = self.egw().mime_open(link_data);
				if(typeof url == 'string' && url.indexOf('webdav.php'))
				{
					// URL is url to file in webdav, so get rid of that part
					url = url.replace('/webdav.php', '');
				}
				else if (typeof url == 'object' && url.path)
				{
					url = url.path;
				}
				self.egw().open(url, "filemanager", "edit");
			}
		});
		this.context.addItem("-", "-");
		this.context.addItem("delete", this.egw().lang("Delete link"), this.egw().image("delete"), function(menu_item) {
			var link_id = self.context.data.link_id;
			self._delete_link(link_id);
		});
	},

	_add_link: function(_link_data) {
		var row = $j(document.createElement("tr"))
			.attr("id", "link_"+_link_data.link_id)
			.appendTo(this.list)

		// Icon
		var icon = $j(document.createElement("td"))
			.appendTo(row)
			.addClass("icon");
		if(_link_data.icon)
		{
			var icon_widget = et2_createWidget("image");
			var src = '';
			if(_link_data.type)
			{
				// VFS - file
				src = this.egw().mime_icon(_link_data.type, _link_data.icon);
			}
			else
			{
				src = this.egw().image(_link_data.icon);
			}
			if(src)	icon_widget.set_src(src);
			icon.append(icon_widget.getDOMNode());
		}
		
		var columns = ['title','remark'];
		
		var self = this;
		for(var i = 0; i < columns.length; i++) {
			$j(document.createElement("td"))
				.appendTo(row)
				.addClass(columns[i])
				.click( function(){self.egw().open(_link_data, "", "edit");})
				.text(_link_data[columns[i]]);
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
			.bind( 'click', function() {self._delete_link(_link_data.link_id);});

		// Context menu
		row.bind("contextmenu", function(e) {
			// File info only available for files
			self.context.getItem("file_info").set_enabled(_link_data.app == 'file');

			self.context.data = _link_data;
			self.context.showAt(e.pageX, e.pageY, true);
			e.preventDefault();
		});
	},
	_delete_link: function(link_id) {
		var row = jQuery('#link_'+link_id, this.list);
		var delete_button = jQuery('.delete.icon',row);
		delete_button.addClass("loading").removeClass("delete");
		new egw_json_request("etemplate.etemplate_widget_link.ajax_delete", [link_id])
			.sendRequest(true, function(data) { if(data) {row.slideUp(row.remove);}});
		
	}
});
et2_register_widget(et2_link_list, ["link-list"]);


/**
 * UI widget for one or more links in a list (table)
 */ 
var et2_link_add = et2_inputWidget.extend({

	attributes: {
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		}
	},
	init: function() {
		this._super.apply(this, arguments);

		this.div = jQuery(document.createElement("div")).text(this.egw().lang("Add new"));
		this.setDOMNode(this.div[0]);
	},
	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		this.app_select = et2_createWidget("link-apps", jQuery.extend({},this.options,{'id': this.options.id + 'app'}) ,this);
		this.div.append(this.app_select.getDOMNode());
		this.button = et2_createWidget("button", {label: this.egw().lang("add")}, this);
		this.button.set_label(this.egw().lang("add"));
		var self = this;
		this.button.click = function() {
			self.egw().open(self.options.value.to_app + ":" + self.options.value.to_id, self.app_select.get_value(), 'add');
		};
		this.div.append(this.button.getDOMNode());
	},
	/**
	 * Should be handled client side.
	 * Return null to avoid overwriting other link values, in case designer used the same ID for multiple widgets
	 */
	getValue: function() {
		return null;
	}
});
et2_register_widget(et2_link_add, ["link-add"]);
