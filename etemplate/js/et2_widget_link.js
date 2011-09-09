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
		this.link_button = null;
		this.status_span = null;
		this.comment = null;
		this.file_upload = null;

		this.cache = {};

		this.createInputWidget();
	},

	destroy: function() {
		this._super.apply(this, arguments);

		this.div = null;
		this.search.autocomplete("destroy");
		this.search = null;
		this.app_select = null;
		this.link_button = null;
		this.status_span = null;
		this.comment = null;
		this.file_upload.destroy();
		this.file_upload = null;
	},

	/**
	 * Override to provide proper node for file widget to go in
	 */
	getDOMNode: function(_sender) {
		if(_sender == this) {
			return this.div[0];
		} else {
			return this.file_div[0];
		}
	},

	createInputWidget: function() {
		var self = this;
		this.div = $j(document.createElement("div")).addClass("et2_link_to");

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

		// Link button
		this.link_button = $j(document.createElement("button"))
			.text(egw.lang("link"))
			.appendTo(this.div).hide()
			.click(this, this.createLink);

		this.set_blur(this.options.blur ? this.options.blur : egw.lang("search"), this.search);
		
		// Span for indicating status
		this.status_span = $j(document.createElement("span"))
			.appendTo(this.div).addClass("status").hide();

		// Link comment field
		this.comment = $j(document.createElement("input"))
			.css("display", "block").css("width","89%")
			.appendTo(this.div).hide();
		this.set_blur(egw.lang("Comment..."),this.comment);

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

		// Need a div for file upload widget
		this.file_div = $j(document.createElement("div")).appendTo(this.div);

		this.setDOMNode(this.div[0]);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		var self = this;

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
				if(input.val() == "") input.val(this.options.blur);
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
		event.data.comment.show();
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
	 * User uploaded some files, and they're done
	 */
	filesUploaded: function(event) {
		var self = this;

		this.link_button.show();
		this.comment.hide();

		// Add some comment fields
		this.file_upload.progress.children().each(function() {
			var comment = jQuery(document.createElement("input"))
				.appendTo(this).hide();
			self.set_blur(egw.lang("Comment..."),comment);

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
		// Hide extra controls
		event.data.link_button.attr("disabled", true);

		var values = event.data.options.value;
		var self = event.data;

		var links = [];

		// Links to other entries
		if(values.id) {
			links.push({
				app: values.app,
				id: values.id,
				remark: self.comment.val()
			});
			self.comment.val("");
			self.search.val("");
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
		}
	}
});

et2_register_widget(et2_link_to, ["link-to"]);


