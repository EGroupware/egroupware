/**
 * EGroupware eTemplate2 - JS Link object
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
 *
 * @augments et2_inputWidget
 */
var et2_link_to = et2_inputWidget.extend(
{
	attributes: {
		"only_app": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to just this one application - hides app selection"
		},
		"application_list": {
			"name": "Application list",
			"type": "any",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": "",
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text.",
			translate:true
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

	/**
	 * Constructor
	 *
	 * @memberOf et2_link_to
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.div = null;

		this.link_button = null;
		this.status_span = null;
		
		this.link_entry = null;
		this.file_upload = null;

		this.createInputWidget();
	},

	destroy: function() {
		this.link_button = null;
		this.status_span = null;
		
		this.link_entry.destroy();
		this.link_entry = null;
		this.file_upload.destroy();
		this.file_upload = null;
		this.div = null;

		this._super.apply(this, arguments);
	},

	/**
	 * Override to provide proper node for sub widgets to go in
	 * 
	 * @param {Object} _sender 
	 */
	getDOMNode: function(_sender) {
		if(_sender == this) {
			return this.div[0];
		} else if (_sender._type == 'link-entry') {
			return this.link_div[0];
		} else if (_sender._type == 'file') {
			return this.file_div[0];
		} else if (_sender._type == 'vfs-select') {
			return this.filemanager_button[0];
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
		this.link_div = $j(document.createElement("div"))
			.css("margin-bottom", "1ex")
			// Leave room for link button
			.css("width", "89%")
			.appendTo(this.div);

		// Filemanager link popup
		this.filemanager_button = $j(document.createElement("div")).appendTo(this.div);

		// Need a div for file upload widget
		this.file_div = $j(document.createElement("div")).appendTo(this.div);

		this.setDOMNode(this.div[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		var self = this;
		if(this.link_entry && this.vfs_select && this.file_upload)
		{
			// Already done
			return false;
		}

		// Link-to
		var link_entry_attrs = {
			id: this.id + '_link_entry',
			only_app: this.options.only_app,
			application_list: this.options.application_list,
			blur: this.options.search_label ? this.options.search_label : this.egw().lang('Search...'),
			query: function() { self.link_button.hide(); return true;},
			select: function() {self.link_button.show(); return true;}
		};
		this.link_entry = et2_createWidget("link-entry", link_entry_attrs,this);

		// Filemanager select
		var select_attrs = {
			method: 'etemplate_widget_link::link_existing',
			method_id: function() { return self.options.value.to_app + ':' + self.options.value.to_id;},
			button_label: egw.lang('Link')
		};
		this.vfs_select = et2_createWidget("vfs-select", select_attrs,this);
		$j(this.vfs_select.getDOMNode()).change( function() {
			var values = true;
			// If entry not yet saved, store for linking on server
			if(!self.options.value.to_id || typeof self.options.value.to_id == 'object')
			{
				values = self.options.value.to_id || {};
				var files = self.vfs_select.getValue();
				for(var i = 0; i < files.length; i++)
				{
					values['link:'+files[i]] = {
						app: 'link',
						id: files[i],
						type: 'unknown',
						icon: 'link',
						remark: '',
						title: files[i]
					};
				}
			}
			self._link_result(values);
		});

		// File upload
		var file_attrs = {
			multiple: true,
			id: this.id + '_file',

			// Make the whole template a drop target
			drop_target: this.getInstanceManager().DOMContainer.getAttribute("id"),

			// Change to this tab when they drop
			onStart: function(event, file_count) {
				// Find the tab widget, if there is one
				var tabs = self;
				do {
					tabs = tabs._parent;
				} while (tabs != self.getRoot() && tabs._type != 'tabbox');
				if(tabs != self.getRoot())
				{
					// Find the tab index
					for(var i = 0; i < tabs.tabData.length; i++)
					{
						// Find the tab
						if(tabs.tabData[i].contentDiv.has(self.div).length)
						{
							tabs.setActiveTab(i);
							break;
						}
					}
				}
				return true;
			},
			onFinish: function(event, file_count) {
				event.data = self;
				self.filesUploaded(event);

				// Auto-link uploaded files
				self.createLink(event);
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
	 * 
	 * @param {Object} event 
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
		
		// Files
		if(!self.options.no_files)
		{
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
		}
		if(links.length == 0)
		{
			return;
		}

		var request = egw.json(self.egw().getAppName()+".etemplate_widget_link.ajax_link.etemplate",
			[values.to_app, values.to_id, links],
			self._link_result,
			self,
			true,
			self
		);
		request.sendRequest();
	},

	/**
	 * Sent some links, server has a result
	 * 
	 * @param {Object} success
	 */
	_link_result: function(success) {
		if(success) {
			this.link_button.hide().attr("disabled", false);
			this.status_span.removeClass("error").addClass("success");
			this.status_span.fadeIn().delay(1000).fadeOut();
			delete this.options.value.app;
			delete this.options.value.id;
			for(var file in this.file_upload.options.value) {
				delete this.file_upload.options.value[file];
			}
			this.file_upload.progress.empty();

			// Server says it's OK, but didn't store - we'll send this again on submit
			// This happens if you link to something before it's saved to the DB
			if(typeof success == "object")
			{
				// Save as appropriate in value
				if(typeof this.options.value != "object")
				{
					this.options.value = {};
				}
				this.options.value.to_id = success;
				for(var link in success)
				{
					// Icon should be in registry
					if(typeof success[link].icon == 'undefined')
					{
						success[link].icon = egw.link_get_registry(success[link].app,'icon');
						// No icon, try by mime type - different place for un-saved entries
						if(success[link].icon == false && success[link].id.type)
						{
							// Triggers icon by mime type, not thumbnail or app
							success[link].type = success[link].id.type;
							success[link].icon = true;
						}
					}
					// Special handling for file - if not existing, we can't ask for title
					if(success[link].app == 'file' && typeof success[link].title == 'undefined')
					{
						success[link].title = success[link].id.name || '';
					}
				}
			}

			// Look for a link-list with the same ID, refresh it
			var self = this;
			var list_widget = null;
			this.getRoot().iterateOver(
				function(widget) {
					if(widget.id == self.id) {
						list_widget = widget;
						if(success === true)
						{
							widget._get_links();
						}
					}
				},
				this, et2_link_list
			);

			// If there's an array of data (entry is not yet saved), updating the list will
			// not work, so add them in explicitly.
			if(list_widget && success)
			{
				// Clear list
				list_widget.set_value(null);

				// Add temp links in
				for(var link_id in success)
				{
					var link = success[link_id];
					if(typeof link.title == 'undefined')
					{
						// Callback to server for title
						egw.link_title(link.app, link.id, function(title) {
							link.title = title;
							list_widget._add_link(link);
						});
					}
					else
					{
						// Add direct
						list_widget._add_link(link);
					}
				}
			}
		}
		else
		{
			this.status_span.removeClass("success").addClass("error")
				.fadeIn();
		}
		this.div.trigger('link.et2_link_to',success);
	},

	set_no_files: function(no_files)
	{
		if(no_files)
		{
			this.file_div.hide();
			this.filemanager_button.hide();
		}
		else
		{
			this.file_div.show();
			this.filemanager_button.show();
		}
		this.options.no_files = no_files;
	}
});
et2_register_widget(et2_link_to, ["link-to"]);

/**
 * @augments et2_selectbox
 */
var et2_link_apps = et2_selectbox.extend(
{
	attributes: {
		"only_app": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to just this one application - hides app selection"
		},
		"application_list": {
			"name": "Application list",
			"type": "any",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_link_apps
	 */
	init: function() {
		this._super.apply(this, arguments);

		if (this.options.select_options != null)
		{
			// Preset to last application
			if(!this.options.value)
			{
				this.set_value(egw.preference('link_app', this.egw().getAppName()));
			}
			// Register to update preference
			var self = this;
			this.input.bind("click",function() {
				if (typeof self.options.value != 'undefined') var appname = self.options.value.to_app; 
				egw.set_preference(appname || self.egw().getAppName(),'link_app',self.getValue());
			});
		}
	},

	/**
	 * We get some minor speedups by overriding parent searching and directly setting select options
	 * 
	 * @param {Array} _attrs an array of attributes
	 */
	transformAttributes: function(_attrs) {
		var select_options = {};

		// Limit to one app
		if(_attrs.only_app) {
			select_options[_attrs.only_app] = this.egw().lang(_attrs.only_app);
		} else {
			select_options = _attrs.application_list ? _attrs.application_list : egw.link_app_list('query');
		}
		_attrs.select_options = select_options;
		this._super.apply(this, arguments);
	}
});
et2_register_widget(et2_link_apps, ["link-apps"]);

/**
 * @augments et2_inputWidget
 */
var et2_link_entry = et2_inputWidget.extend(
{
	attributes: {
		"value": {
			"type": "any",
			"default": {}
		},
		"only_app": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to just this one application - hides app selection"
		},
		"application_list": {
			"name": "Application list",
			"type": "any",
			"default": "",
			"description": "Limit to the listed applications (comma seperated)"
		},
		"blur": {
			"name": "Placeholder",
			"type": "string",
			"default": et2_no_init,
			"description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text.",
			translate:true
		},
		"query": {
			"name": "Query callback",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback before query to server.  Must return true, or false to abort query."
		},
		"select": {
			"name": "Select callback",
			"type": "js",
			"default": et2_no_init,
			"description": "Callback when user selects an option.  Must return true, or false to abort normal action."
		}
	},

	legacyOptions: ["only_app", "application_list"],
	search_timeout: 500, //ms after change to send query
	minimum_characters: 2, // Don't send query unless there's at least this many chars

	/**
	 * Constructor
	 *
	 * @memberOf et2_link_entry
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.search = null;
		this.app_select = null;
		this._oldValue = {
			id: null,
			app: this.options.value && this.options.value.app ? this.options.value.app : this.options.only_app
		};

		if(typeof this.options.value == 'undefined' || this.options.value == null)
		{
			this.options.value = {};
		}
		this.cache = {};
		this.request = null;

		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.div = null;
		if(this.search.data("ui-autocomplete"))
		{
			this.search.autocomplete("destroy");
		}
		this.search = null;
		this.app_select = null;
		this.request = null;
	},

	createInputWidget: function() {
		var self = this;
		this.div = $j(document.createElement("div")).addClass("et2_link_entry");

		// Application selection
		this.app_select = $j(document.createElement("select")).appendTo(this.div)
			.val(this.options.value.app||'')
			.change(function(e) {
				// Clear cache when app changes
				self.cache = {};

				// Update preference with new value
				egw.set_preference(self.options.value.to_app || self.egw().getAppName(),'link_app',self.app_select.val());

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
		if(this.options.only_app)
		{
			this.app_select.val(this.options.only_app);
			this.app_select.hide();
			this.div.addClass("no_app");
		}

		// Search input
		this.search = $j(document.createElement("input"))
			// .attr("type", "search") // Fake it for all browsers below
			.focus(function(){if(!self.options.only_app) {
				// Adjust width, leave room for app select & link button
				self.div.removeClass("no_app");self.app_select.show();
			}})
			.appendTo(this.div);

		this.set_blur(this.options.blur ? this.options.blur : this.egw().lang("search"), this.search);

		// Autocomplete
		this.search.autocomplete({
			source: function(request, response) {
				return self.query(request, response);
			},
			select: function(event, item) {
				event.data = self;
				// Correct changed value from server
				item.item.value = item.item.value.trim();
				self.select(event,item);
				return false;
			},
			focus: function(event, item) {
				event.stopPropagation();
				self.search.val(item.item.label);
				return false;
			},
			minLength: self.minimum_characters,
			delay: self.search_timeout,
			disabled: self.options.disabled,
			appendTo: self.div
		});

		// Custom display (colors)
		this.search.data("uiAutocomplete")._renderItem = function(ul, item) {
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
			if(keycode == '13' && !self.processing)
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
					self.set_value(null);
					self.search.val("");
					self.search.trigger("change");
				}
				self.search.focus();
			})
			.appendTo(this.div)
			.hide();

		this.setDOMNode(this.div[0]);
	},

	getDOMNode: function() {
		return this.div ? this.div[0] : null;
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);


		_attrs["select_options"] = {};
		if(_attrs["application_list"])
		{
			var apps = (typeof _attrs["application_list"] == "string") ? et2_csvSplit(_attrs["application_list"], null, ","): _attrs["application_list"];
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
				.getEntry("options-" + this.id);
		}

		// Default to an empty object
		if (_attrs["select_options"] == null)
		{
			_attrs["select_options"] = {};
		}
	},

	doLoadingFinished: function() {
		if(typeof this.options.value == 'object' && !this.options.value.app)
		{
			this.options.value.app = egw.preference('link_app',this.options.value.to_app || this.egw().getAppName());
			this.app_select.val(this.options.value.app);
		}
		return this._super.apply(this,arguments);
	},

	getValue: function() {
		var value = this.options.only_app ? this.options.value.id : this.options.value;
		if(!this.options.only_app)
		{
			value.search = this.search.val();
		}
		return value;
	},

	set_value: function(_value) {
		if(typeof _value == 'string' || typeof _value == 'number')
		{
			if(typeof _value == 'string' && _value.indexOf(",") > 0) _value = _value.replace(",",":");
			if(typeof _value == 'string' && _value.indexOf(":") >= 0)
			{
				var split = _value.split(":");

				_value = {
					app: split.shift(),
					id: split.length == 1 ? split[0] : split
				};
			}
			else if(_value && this.options.only_app)
			{
				_value = {
					app: this.options.only_app,
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
		if(!_value.app) _value.app = this.options.only_app;

		if(_value.id) {
			// Remove specific display and revert to CSS file
			// show() would use inline, should be inline-block
			this.clear.css('display','');
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
					// Remove specific display and revert to CSS file
					// show() would use inline, should be inline-block
					this.clear.css('display','');
				}, this);
				this.search.addClass("loading");
			}
		}
		if(_value.title)
		{
			this.search.val(_value.title+"");
		}
		this.options.value = _value;

		jQuery("option[value='"+_value.app+"']",this.app_select).prop("selected",true);
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
	 * Set the query callback
	 *
	 * @param {function} query
	 */
	set_query: function(f)
	{
		this.options.query = f;
	},

	/**
	 * Set the select callback
	 *
	 * @param {function} query
	 */
	set_select: function(f)
	{
		this.options.select = f;
	},

	/**
	 * Ask server for entries matching selected app/type and filtered by search string
	 * 
	 * @param {Object} request
	 * @param {Object} response 
	 */
	query: function(request, response) {
		// If there is a pending request, abort it
		if(this.request)
		{
			this.request.abort();
			this.request = null;
		}

		// Remember last search
		this.last_search = this.search.val();

		// Allow hook / tie in
		if(this.options.query && typeof this.options.query == 'function')
		{
			if(!this.options.query(request, response)) return false;
		}

		if((typeof request.no_cache == 'undefined' && !request.no_cache) && request.term in this.cache) {
			return response(this.cache[request.term]);
		}
		
		// Remember callback
		this.response = response;

		this.search.addClass("loading");
		// Remove specific display and revert to CSS file
		// show() would use inline, should be inline-block
		this.clear.css('display','');
		this.request = egw.json(this.egw().getAppName() + ".etemplate_widget_link.ajax_link_search.etemplate",
			[this.app_select.val(), '', request.term, request.options],
			this._results,
			this,true,this
		).sendRequest();
	},

	/**
	 * User selected a value
	 * 
	 * @param {Object} event
	 * @param {Object} selected
	 * 
	 */
	select: function(event, selected) {
		if(selected.item.value !== null && typeof selected.item.value == "string")
		{
			// Correct changed value from server
			selected.item.value = selected.item.value.trim();
		}
		if(this.options.select && typeof this.options.select == 'function')
		{
			if(!this.options.select(event, selected)) return false;
		}
		if(typeof event.data.options.value != 'object' || event.data.options.value == null)
		{
			event.data.options.value = {};
		}
		event.data.options.value.id = selected.item.value;

		// Set a processing flag to filter some events
		event.data.processing = true;

		// Remove specific display and revert to CSS file
		// show() would use inline, should be inline-block
		this.clear.css('display','');
		event.data.search.val(selected.item.label);

		// Fire change event
		this.search.change();

		// Turn off processing flag when done
		window.setTimeout(jQuery.proxy(function() {delete this.processing;},event.data));
	},

	/**
	 * Server found some results
	 * 
	 * @param {Array} data
	 */
	_results: function(data) {
		if(this.request)
		{
			this.request = null;
		}
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
	 * 
	 * @param {Object} event
	 * @param {Object} _links
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
			var request = egw.json(self.egw().getAppName()+".etemplate_widget_link.ajax_link.etemplate",
				[values.to_app, values.to_id, links],
				self._link_result,
				this,
				true
			);
			request.sendRequest();
		}
	},

	/**
	 * Sent some links, server has a result
	 * 
	 * @param {Object} success
	 * 
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
 *
 * @augments et2_valueWidget
 */
var	et2_link = et2_valueWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"only_app": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Use the given application, so you can pass just the ID for value"
		},
		"value": {
			description: "Array with keys app, id, and optionally title",
			type: "any"
		},
		"needed": {
			"ignore": true
		}
	},
	legacyOptions: ["only_app"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_link
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.label_span = $j(document.createElement("label"))
			.addClass("et2_label");
		this.link = $j(document.createElement("span"))
			.addClass("et2_link")
			.appendTo(this.label_span);

		if(this.options['class']) this.label_span.addClass(this.options['class']);
		this.setDOMNode(this.label_span[0]);
	},
	destroy: function() {
		if(this.link) this.link.unbind();
		this.link = null;
		this._super.apply(this, arguments);
	},
	set_label: function(label) {
		// Remove current label
		this.label_span.contents()
			.filter(function(){ return this.nodeType == 3; }).remove();

		var parts = et2_csvSplit(label, 2, "%s");
		this.label_span.prepend(parts[0]);
		this.label_span.append(parts[1]);
		this.label = label;
	},
	set_value: function(_value) {
		if(typeof _value != 'object' && _value && !this.options.only_app)
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
				app:	this.options.only_app,
				id:	_value
			};
		}
		if(!_value || jQuery.isEmptyObject(_value)) {
			this.link.text("").unbind();
			return;
		}
		var self = this;
		this.link.unbind();
		if(_value.id && _value.app)
		{
			this.link.click( function(){
				self.egw().open(_value, "", "view",null,_value.app,_value.app);
			});
		}
		if(!_value.title) {
			var self = this;
			var node = this.link[0];
			if(_value.app && _value.id)
			{
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
			else
			{
				_value.title = "";
			}
		}
		this.set_title(this.link, _value.title);
	},

	/**
	 * Sets the text to be displayed.
	 * Used as a callback, so node is provided to make sure we get the right one
	 * 
	 * @param {Object} node
	 * @param {String} _value description
	 */
	set_title: function(node, _value) {
		if(_value === false || _value === null) _value = "";
		jQuery(node).text(_value+"");
	},

	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 * 
	 * @param {Array} _attrs an array of attributes
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("label","value");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes: function() {
		return [this.node, this.link[0]];
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
		this.node = _nodes[0];
		this.label_span = jQuery(_nodes[0]);
		this.link = jQuery(_nodes[1]);
		if(typeof _values["id"] !== "undefined") this.set_id(_values['id']);
		if(typeof _values["label"] !== "undefined") this.set_label(_values['label']);
		if(typeof _values["value"] !== "undefined") this.set_value(_values["value"]);
	}

});
et2_register_widget(et2_link, ["link", "link-entry_ro"]);

/**
 * UI widget for one or more links, comma separated
 *
 * @augments et2_valueWidget
 */
var et2_link_string = et2_valueWidget.extend([et2_IDetachedDOM],
{
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

	/**
	 * Constructor
	 *
	 * @memberOf et2_link_string
	 */
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
		this.egw().jsonq(this.egw().getAppName()+'.etemplate_widget_link.ajax_link_list', [_value], this.set_value, this);
		return;
	},

	_add_link: function(_link_data) {
		var self = this;
		var link = $j(document.createElement("li"))
			.appendTo(this.list)
			.addClass("et2_link loading")
			.click( function(){self.egw().open(_link_data, "", "view",null,_link_data.app,_link_data.app);});

		if(_link_data.title) link.text(_link_data.title);

		// Now that link is created, get title from server & update
		if(!_link_data.title) {
			this.egw().link_title(_link_data.app, _link_data.id, function(title) {
				if (title)
					this.removeClass("loading").text(title);
				else
					this.remove();	// no rights or not found
			}, link);
		}
	},

	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 * 
	 * @param {Array} _attrs an array of attributes
	 */
	getDetachedAttributes: function(_attrs) {
		// Create the label container if it didn't exist yet
		if (this._labelContainer == null)
		{
			this._labelContainer = $j(document.createElement("label"))
					.addClass("et2_label");
			this.getSurroundings().insertDOMNode(this._labelContainer[0]);
			this.getSurroundings().update();
		}
		_attrs.push("value","label");
	},

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes: function() {
		// Create the label container if it didn't exist yet
		if (this._labelContainer == null)
		{
			this._labelContainer = $j(document.createElement("label"))
				.addClass("et2_label");
			this.getSurroundings().insertDOMNode(this._labelContainer[0]);
		}
		return [this.list[0], this._labelContainer[0]];
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
		
		// Special detached, to prevent DOM node modification of the normal method
		this._labelContainer = _nodes.length > 1 ? $j(_nodes[1]) : null;
		if(_values['label'])
		{
			this.set_label(_values['label']);
		}
		else if (this._labelContainer)
		{
			this._labelContainer.contents().not(this.list).remove();
		}
	}
});
et2_register_widget(et2_link_string, ["link-string"]);

/**
 * UI widget for one or more links in a list (table)
 *
 * @augments et2_link_string
 */
var et2_link_list = et2_link_string.extend(
{
	attributes: {
		"show_deleted": {
			"name": "Show deleted",
			"type": "boolean",
			"default": false,
			"description": "Show links that are marked as deleted, being held for purge"
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_link_list
	 */
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

			et2_dialog.show_prompt(
				function(button, comment) {
					if(button != et2_dialog.OK_BUTTON) return;
					var remark = jQuery('#link_'+(self.context.data.dom_id ? self.context.data.dom_id : link_id), self.list).children('.remark');
					if(isNaN(self.context.data.id))
					{
						remark.text(comment);
						// Look for a link-to with the same ID, refresh it
						if(self.context.data.link_id)
						{
							var _widget = link_id.widget || null;
							self.getRoot().iterateOver(
								function(widget) {
									if(widget.id == self.id) {
										_widget = widget;
									}
								},
								self, et2_link_to
							);
							var value = _widget != null ? _widget.getValue() : false;
							if(_widget && value && value.to_id)
							{
								value.to_id[link_id].remark = comment;
							}
						}
						return;
					}
					remark.addClass("loading");
					var request = egw.json(self.egw().getAppName() + ".etemplate_widget_link.ajax_link_comment.etemplate",
						[link_id, comment],
						function() {
							if(remark)
							{
								// Append "" to make sure it's a string, not undefined
								remark.removeClass("loading").text(comment+"");
								// Update internal data
								self.context.data.remark = comment+"";
							}
						},
						this,true
					).sendRequest();
				},
				'',self.egw().lang("Comment"),self.context.data.remark||''
			);

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
			var link_id = isNaN(self.context.data.link_id) ? self.context.data : self.context.data.link_id;
			var row = jQuery('#link_'+(self.context.data.dom_id ? self.context.data.dom_id : self.context.data.link_id), self.list);
			self._delete_link(link_id, row);
		});
	},

	destroy: function() {

		this._super.apply(this, arguments);
		if(this.context)
		{
			this.context.clear();
			delete this.context;
		}
	},

	set_value: function(_value)
	{
		// Handle server passed a list of links that aren't ready yet
		if(_value && typeof _value == "object" && _value.to_id && typeof _value.to_id == "object")
		{
			this.list.empty();
			for(var id in _value.to_id)
			{
				var link = _value.to_id[id];
				if(link.app)
				{
					// Temp IDs can cause problems since the ID includes the file name or :
					if(link.link_id && typeof link.link_id != 'number')
					{
						link.dom_id = 'temp_'+egw.uid();
					}
					// Icon should be in registry
					if(typeof link.icon == 'undefined')
					{
						link.icon = egw.link_get_registry(link.app,'icon');
						// No icon, try by mime type - different place for un-saved entries
						if(link.icon == false && link.id.type)
						{
							// Triggers icon by mime type, not thumbnail or app
							link.type = link.id.type;
							link.icon = true;
						}
					}
					// Special handling for file - if not existing, we can't ask for title
					if(link.app == 'file' && typeof link.title == 'undefined')
					{
						link.title = link.id.name || '';
					}
					this._add_link(link);
				}
			}
		}
		else
		{
			this._super.apply(this,arguments);
		}
	},

	_add_link: function(_link_data) {
		var row = $j(document.createElement("tr"))
			.attr("id", "link_"+(_link_data.dom_id ? _link_data.dom_id : _link_data.link_id))
			.appendTo(this.list);

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
				.click( function(){self.egw().open(_link_data, "", "view",null,_link_data.app,_link_data.app);})
				.text(_link_data[columns[i]] ? _link_data[columns[i]]+"" : "");
		}

		if (typeof _link_data.title == 'undefined')
		{
			// Title will be fetched from server and then set
			$j('td.title',row).addClass("loading");
			var title = this.egw().link_title(_link_data.app, _link_data.id, function(title) {
				$j('td.title',this).removeClass("loading").text(title+"");
			}, row);
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
			.appendTo(row);
		$j("<div />")
			.appendTo(delete_button)
			// We don't use ui-icon because it assigns a bg image
			.addClass("delete icon")
			.bind( 'click', function() {self._delete_link(
				self.value && typeof self.value.to_id != 'object' && _link_data.link_id ? _link_data.link_id:_link_data,
				row
			);});

		// Context menu
		row.bind("contextmenu", function(e) {
			// File info only available for existing files
			self.context.getItem("file_info").set_enabled(typeof _link_data.id != 'object' && _link_data.app == 'file');

			self.context.data = _link_data;
			self.context.showAt(e.pageX, e.pageY, true);
			e.preventDefault();
		});
	},
	_delete_link: function(link_id, row) {
		if(row)
		{
			var delete_button = jQuery('.delete',row);
			delete_button.removeClass("delete").addClass("loading");
		}
		if(typeof link_id != "object")
		{
			egw.json(this.egw().getAppName()+".etemplate_widget_link.ajax_delete.etemplate", [link_id],
				function(data) { if(data) {row.slideUp(row.remove);}}
			).sendRequest();
		}
		else if (row)
		{
			// No link ID means a link on an unsaved entry.
			// Just remove the row, but need to adjust the link_to value also
			row.slideUp(row.remove);

			// Look for a link-to with the same ID, refresh it
			if(link_id.link_id)
			{
				var self = this;
				var _widget = link_id.widget || null;
				this.getRoot().iterateOver(
					function(widget) {
						if(widget.id == self.id) {
							_widget = widget;
						}
					},
					this, et2_link_to
				);
				var value = _widget != null ? _widget.getValue() : false;
				if(_widget && value && value.to_id)
				{
					delete value.to_id[link_id.link_id];
					_widget.set_value(value);
				}
			}
		}
	}
});
et2_register_widget(et2_link_list, ["link-list"]);


/**
 * UI widget for one or more links in a list (table)
 *
 * @augments et2_inputWidget
 */
var et2_link_add = et2_inputWidget.extend(
{
	attributes: {
		"value": {
			"description": "Either an array of link information (see egw_link::link()) or array with keys to_app and to_id",
			"type": "any"
		},
		"application": {
			"name": "Application",
			"type": "string",
			"default": "",
			"description": "Limit to the listed application or applications (comma seperated)"
		}
	},
	/**
	 * Constructor
	 *
	 * @memberOf et2_link_add
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.span = jQuery(document.createElement("span"))
				.text(this.egw().lang("Add new"))
				.addClass('et2_link_add_span');
		this.div = jQuery(document.createElement("div")).append(this.span);
		this.setDOMNode(this.div[0]);
	},
	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		if(this.app_select && this.button)
		{
			// Already done
			return false;
		}
		this.app_select = et2_createWidget("link-apps", jQuery.extend({},this.options,{
			'id': this.options.id + 'app',
			value: this.options.application ? this.options.application : this.options.value && this.options.value.add_app ? this.options.value.add_app : null
		}) ,this);
		this.div.append(this.app_select.getDOMNode());
		this.button = et2_createWidget("button", {id:this.options.id+"_add",label: this.egw().lang("add")}, this);
		this.button.set_label(this.egw().lang("add"));
		var self = this;
		this.button.click = function() {
			self.egw().open(self.options.value.to_app + ":" + self.options.value.to_id, self.app_select.get_value(), 'add');
		};
		this.div.append(this.button.getDOMNode());

		return true;
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
