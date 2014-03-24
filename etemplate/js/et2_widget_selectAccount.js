/**
 * EGroupware eTemplate2 - JS Select account widget
 *
 * Selecting accounts needs special UI, and displaying needs special consideration
 * to avoid sending the entire user list to the client.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_widget_link;
*/

/**
 * Account selection widget
 * Changes according to the user's account_selection preference
 * - 'none'	     => Server-side: the read-only widget is used
 * - 'groupmembers'  => Non admins can only select groupmembers  (Server side - normal selectbox)
 * - 'selectbox'     => Selectbox with all accounts and groups (Server side - normal selectbox)
 * - 'primary_group' => Selectbox with primary group and search
 * - 'popup'         => No selectbox, just search.  No popup, the search replaces the selectbox
 *
 * Only primary_group and popup need anything different from a normal selectbox
 *
 * @augments et2_selectbox
 */
var et2_selectAccount = et2_selectbox.extend(
{
	attributes: {
		'account_type': {
			'name': 'Account type',
			'default': 'accounts',
			'type': 'string',
			'description': 'Limit type of accounts.  One of {accounts,groups,both,owngroups}.'
		}
	},

	legacyOptions: ['empty_label','account_type'],

	account_types: ['accounts','groups','both','owngroups'],

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_selectAccount
	 * @returns
	 */
	init: function(_parent, _attrs) {

		// Type in rows or somewhere else?
		if(jQuery.inArray(_attrs['empty_label'], this.account_types) > 0 && (
			jQuery.inArray(_attrs['account_type'], this.account_types) < 0 ||
			_attrs['account_type'] == this.attributes.account_type['default'])
		)
		{
			_attrs['account_type'] = _attrs['empty_label'];
			_attrs['empty_label'] = '';
		}
		if(jQuery.inArray(_attrs['account_type'], this.account_types) < 0)
		{
			this.egw().debug("warn", "Invalid account_type: %s Valid options:",_attrs['account_type'], this.account_types);
		}

		// Holder for search jQuery nodes
		this.search = null;

		// Reference to object with dialog
		this.dialog = null;

		this._super.call(this, _parent, _attrs);

		// Allow certain widgets inside this one
		this.supportedWidgetClasses = [et2_link_entry];
	},

	destroy: function() {
		this._super.apply(this, arguments);
	},

	/**
	 * Tell et2 widget framework where to go
	 */
	getDOMNode: function(_sender) {
		if(this.search_widget != null && _sender == this.search_widget)
		{
			return this.search != null ? this.search[0] : this.search_widget._parent.getDOMNode();
		}
		return this._super.apply(this, arguments);
	},


	/**
	 *  Single selection - override to add search button
	 */
	createInputWidget: function()
	{
		this._super.apply(this, arguments);

		var type = this.egw().preference('account_selection', 'common');

		// Add search button
		if(type == 'primary_group')
		{
			var button = jQuery(document.createElement("span"))
				.addClass("et2_clickable")
				.click(this, this.options.multiple ? this._open_multi_search : this._open_search)
				.attr("title", egw.lang("popup with search"))
				.append('<span class="ui-icon ui-icon-search" style="display:inline-block"/>');

			this.getSurroundings().insertDOMNode(button[0]);
		}
		else if (type == 'popup')
		{
			// Allow search 'inside' this widget
			this.supportedWidgetClasses = [et2_link_entry];

			this._create_search();
			// Use empty label as blur
			if(this.options.empty_label) this.search_widget.set_blur(this.options.empty_label);

			// Rework to go around / through the selectbox
			if(this.input)
			{
				this.getValue = function() {return this.value;};
				this.set_value = function(_value) {
					this.value = _value;
					this.search_widget.set_value(_value);
				};
				this.search_widget.search.change(this, function(event) {
					var value = event.data.search_widget.getValue();
					event.data.value = typeof value == 'object' && value ? value.id : value;
					event.data.input.trigger("change");
				});
			}
			var div = jQuery(document.createElement("div")).append(this.search_widget.getDOMNode());
			this.setDOMNode(div[0]);
		}
	},

	/**
	 * Multiple selection - override to add search button
	 */
	createMultiSelect: function() {

		this._super.apply(this, arguments);

		var type = this.egw().preference('account_selection', 'common');
		if(type == 'primary_group')
		{
			// Allow search 'inside' this widget
			this.supportedWidgetClasses = [et2_link_entry];

			// Add quick search - turn off multiple to get normal result list
			this.options.multiple = false;
			this._create_search();

			// Clear search box after select
			var old_select = this.search_widget.select;
			var self = this;
			this.search_widget.select =  function(e, selected) {
				var current = self.getValue();

				// Fix ID as sent from server - must be numeric
				selected.item.value = parseInt(selected.item.value);

				// This one is important, it makes sure the option is there
				old_select.apply(this, arguments);

				// Add quick search selection into current selection
				current.push(selected.item.value);

				// Clear search
				this.search.val('');

				self.set_value(current);
			};

			// Put search results as a DOM sibling of the options, for proper display
			this.search_widget.search.on("autocompleteopen", jQuery.proxy(function() {
				this.search_widget.search.data("ui-autocomplete").menu.element
					.appendTo(this.node)
					.position({my: 'left top', at: 'left bottom', of: this.multiOptions.prev()})
			},this));
			this.search = jQuery(document.createElement("li"))
				.appendTo(this.multiOptions.prev().find('ul'));
			this.options.multiple = true;

			// Add search button
			var button = jQuery(document.createElement("li"))
				.addClass("et2_clickable")
				.click(this, this._open_multi_search)
				.attr("title", egw.lang("popup with search"))
				.append('<span class="ui-icon ui-icon-search"/>')
			var type = this.egw().preference('account_selection', 'common');

			// Put it last so check/uncheck doesn't move around
			this.multiOptions.prev().find('ul')
				.append(button);
		}
		else if (type == 'popup')
		{
			// Allow search 'inside' this widget
			this.supportedWidgetClasses = [et2_link_entry];

			/**
			 * Popup takes the dialog and embeds it in place of the selectbox
			 */
			var dialog = this._open_multi_search();
			dialog.dialog("close");
			var div = jQuery(document.createElement("div")).append(this.dialog);
			this.setDOMNode(div[0]);

			var select_col = jQuery('#selection_col',dialog).children();
			var selected = jQuery('#'+this.getInstanceManager().uniqueId + "_selected", select_col);

			// Re-do to get it to work around/through the select box
			this.set_value = function(_value) {
				selected.empty();
				if(typeof _value == 'string')
				{
					_value = _value.split(',');
				}
				if(typeof _value == 'object')
				{
					for(var key in _value)
					{
						this._add_selected(selected, _value[key]);
					}
				}
			};
			var widget = this;
			this.getValue = function() {
				// Update widget with selected
				var ids = [];
				var data = {};
				jQuery('#'+this.getInstanceManager().uniqueId + '_selected li',select_col).each(function() {
					var id = $j(this).attr("data-id");
					// Add to list
					ids.push(id);

					// Make sure option is there
					if(jQuery('input[data-id="'+id+'"]',widget.multiOptions).length == 0)
					{
						widget._appendMultiOption(id,jQuery('label',this).text());
					}
				});

				this.set_multi_value(ids);
				return ids;
			};
		}
	},

	/**
	 * Create & display a way to search & select a single account / group
	 * Single selection is just link widget
	 */
	_open_search: function(e) {
		var widget = e.data;
		var search = widget._create_search();

		// Selecting a single user closes the dialog, this only used if user cleared
		var ok_click = function() {
			jQuery(this).dialog("close");
			widget.set_value([]);
			// Fire change event
			if(widget.input) widget.input.trigger("change");
			// Free it up, it will be re-created, if ever needed again
			jQuery(this).dialog("destroy");
		};
		widget._create_dialog(search, ok_click);
	},

	/**
	 * Create & display a way to search & select multiple accounts / groups
	 */
	_open_multi_search: function(e) {
		var widget = e && e.data ? e.data : this;
		var table = widget.search = jQuery('<table><tbody><tr valign="top"><td id="search_col"/><td id="selection_col"/></tr></tbody></table>');
		table.css("width", "100%").css("height", "100%");
		var search_col = jQuery('#search_col',table);
		var select_col = jQuery('#selection_col',table);

		// Search / Selection
		search_col.append(widget._create_search());

		// Currently selected
		select_col.append(widget._create_selected());

		var ok_click = function() {
			jQuery(this).dialog("close");
			// Update widget with selected
			var ids = [];
			var data = {};
			jQuery('#'+widget.getInstanceManager().uniqueId + '_selected li',select_col).each(function() {
				var id = $j(this).attr("data-id");
				// Add to list
				ids.push(id);

				// Make sure option is there
				if(!widget.options.multiple && jQuery('input[id$="_opt_'+id+'"]',widget.multiOptions).length == 0)
				{
					widget._appendMultiOption(id,jQuery('label',this).text());
				}
				else if (widget.options.multiple && jQuery('option[value="'+id+'"]',widget.node).length == 0)
				{
					widget._appendOptionElement(id,jQuery('label',this).text());
				}
			});

			widget.set_value(ids);

			// Free it up, it will be re-created, if ever needed again
			jQuery(this).dialog("destroy");

			// Fire change event
			if(widget.input) widget.input.trigger("change");
		};

		var container = jQuery(document.createElement("div")).append(table);
		return widget._create_dialog(container, ok_click);
	},

	/**
	 * Create / display popup with search / selection widgets
	 */
	_create_dialog: function(widgets, update_function) {
		this.dialog = widgets;
		widgets.dialog({
			title: this.options.label ? this.options.label : this.egw().lang('Select'),
			modal: true,
			// Static size for easier layout
			width: "500",
			height: "350",
			buttons: [{
				text: this.egw().lang("ok"),
				click: update_function
				},{
				text: this.egw().lang("cancel"),
				click: function() {
					jQuery(this).dialog("close");
					jQuery(this).dialog("destroy");
				}}
			]
		});
		return widgets;
	},

	/**
	 * Search is a link-entry widget, with some special display for multi-select
	 */
	_create_search: function() {
		var self = this;
		var search = this.search = jQuery(document.createElement("div"));

		var search_widget = this.search_widget = et2_createWidget('link-entry', {
				'only_app': 'home-accounts',
				'query': function(request, response) {
					// Clear previous search results for multi-select
					if(!request.options)
					{
						search.find('#search_results').empty();
					}
					// Restrict to specified account type
					if(!request['options'] || !request['options']['filter'])
					{
						request['options'] = {filter:{group:self.options.account_type}};
					}
					return true;
				},
				'select': function(e, selected) {
					// Make sure option is there
					var already_there = false;
					var last_key = null;
					for(last_key in self.options.select_options)
					{
						var option = self.options.select_options[last_key];
						already_there = already_there || (typeof option.value != 'undefined' && option.value == selected.item.value);
					}
					if(!already_there)
					{
						self.options.select_options[parseInt(last_key)+1] = selected.item;
						self._appendOptionElement(selected.item.value, selected.item.label);
					}
					self.set_value(selected.item.value);
					if(self.dialog)
					{
						self.dialog.dialog("close");
						self.dialog.dialog("destroy");
					}
					// Fire change event
					if(self.input) self.input.trigger("change");
					return true;
				}
			}, this);
		// add it where we want it
		search.append(search_widget.getDOMNode());

		if(!this.options.multiple) return search;

		// Multiple is more complicated.  It uses a custom display for results to
		// allow choosing multiples from a match
		var results = jQuery(document.createElement("ul"))
			.attr("id", "search_results")
			.css("height", "230px")
			.addClass("ui-multiselect-checkboxes ui-helper-reset");
		jQuery(document.createElement("div"))
			.addClass("et2_selectbox")
			.css("height", "100%")
			.append(results)
			.appendTo(search);

		// Override link-entry auto-complete for custom display
		// Don't show normal drop-down
		search_widget.search.data("ui-autocomplete")._suggest = function(items) {
			jQuery.each(items, function (index, item) {
				// Make sure value is numeric
				item.value = parseInt(item.value);
				self._add_search_result(results, item);
			});
		};

		return search;
	},

	/**
	 * Add the selected result to the list of search results
	 */
	_add_search_result: function(list, item) {

		var node = null;
		var self = this;
		
		// Make sure value is numeric
		if(item.value) item.value = parseInt(item.value);

		// (containter of) Currently selected users / groups
		var selected = jQuery('#'+this.getInstanceManager().uniqueId + "_selected", this.dialog);

		// Group
		if(item.value && item.value < 0)
		{
			node = jQuery(document.createElement('ul'));
			// Add button to show users
			if(this.options.account_type != 'groups')
			{
				jQuery('<span class="ui-icon ui-icon-circlesmall-plus et2_clickable"/>')
					.css("float", "left")
					.appendTo(node)
					.click(function() {
						if(jQuery(this).hasClass("ui-icon-circlesmall-plus"))
						{
							jQuery(this).removeClass("ui-icon-circlesmall-plus")
								.addClass("ui-icon-circlesmall-minus");

							var group = jQuery(this).parent()
								.addClass("expanded");

							if(group.children("li").length == 0)
							{
								// Fetch group members
								self.search_widget.query({
										term:"",
										options: {filter:{group: item.value}},
										no_cache:true
									}, function(items) {
									jQuery(items).each(function(index,item) {
										self._add_search_result(node, item);
									});
								});
							}
							else
							{
								group.children("li")
									// Only show children that are not selected
									.each(function(index, item) {
										var j = jQuery(item);
										if(jQuery('[data-id="'+j.attr("data-id")+'"]',selected).length == 0)
										{
											j.show();
										}
									});
							}
						}
						else
						{
							jQuery(this).addClass("ui-icon-circlesmall-plus")
								.removeClass("ui-icon-circlesmall-minus");

							var group = jQuery(this).parent().children("li").hide();
						}
					});
			}

		}
		// User
		else if (item.value)
		{
			node = jQuery(document.createElement('li'));
		}
		node.attr("data-id", item.value);

		jQuery('<span class="ui-icon ui-icon-arrow-1-e et2_clickable"/>')
			.css("float", "right")
			.appendTo(node)
			.click(function() {
				var button = jQuery(this);
				self._add_selected(selected, button.parent().attr("data-id"));
				// Hide user, but only hide button for group
				if(button.parent().is('li'))
				{
					button.parent().hide();
				}
				else
				{
					button.hide();
				}
			});

		// If already in list, hide it
		if(jQuery('[data-id="'+item.value+'"]',selected).length != 0)
		{
			node.hide();
		}

		var label = jQuery(document.createElement('label'))
			.addClass("loading")
			.appendTo(node);

		this.egw().link_title('home-accounts', item.value, function(name) {
			label.text(name).removeClass("loading");
		}, label);

		node.appendTo(list);
	},

	_create_selected: function() {
		var node = jQuery(document.createElement("div"))
			.addClass("et2_selectbox");

		var header = jQuery(document.createElement("div"))
			.addClass("ui-widget-header ui-helper-clearfix")
			.appendTo(node);

		var selected = jQuery(document.createElement("ul"))
			.addClass("ui-multiselect-checkboxes ui-helper-reset")
			.attr("id", this.getInstanceManager().uniqueId + "_selected")
			.css("height", "230px")
			.appendTo(node);

		jQuery(document.createElement("span"))
			.text(this.egw().lang("Selection"))
			.addClass("ui-multiselect-header")
			.appendTo(header);

		var controls = jQuery(document.createElement("ul"))
			.addClass('ui-helper-reset')
			.appendTo(header);

		jQuery(document.createElement("li"))
			.addClass("et2_clickable")
			.click(selected, function(e) {jQuery("li",e.data).remove();})
			.append('<span class="ui-icon ui-icon-closethick"/>')
			.appendTo(controls);

		// Add in currently selected
		if(this.getValue())
		{
			var value = this.getValue();
			for(var i = 0; i < value.length; i++) {
				this._add_selected(selected, value[i]);
			}
		}
		return node;
	},

	/**
	 * Add an option to the list of selected accounts
	 * value is the account / group ID
	 */
	_add_selected: function(list, value) {

		// Each option only once
		var there = jQuery('[data-id="' + value + '"]',list);
		if(there.length)
		{
			there.show();
			return;
		}

		var option = jQuery(document.createElement('li'))
			.attr("data-id",value)
			.appendTo(list);
		jQuery('<div class="ui-icon ui-icon-close et2_clickable"/>')
			.css("float", "right")
			.appendTo(option)
			.click(function() {
				var id = jQuery(this).parent().attr("data-id");
				jQuery(this).parent().remove();
				// Add 'add' button back, if in results list
				list.parents("tr").find("[data-id='"+id+"']").show()
					// Show button(s) for group
					.children('span').show();
			});

		var label = jQuery(document.createElement('label'))
			.addClass("loading")
			.appendTo(option);
		this.egw().link_title('home-accounts', value, function(name) {this.text(name).removeClass("loading");}, label);
	}
});
et2_register_widget(et2_selectAccount, ["select-account"]);

/**
 * et2_selectAccount_ro is the readonly implementation of select account
 * It extends et2_link to avoid needing the whole user list on the client.
 * Instead, it just asks for the names of the ones needed, as needed.
 *
 * @augments et2_link_string
 */
var et2_selectAccount_ro = et2_link_string.extend([et2_IDetachedDOM],
{
	attributes: {
		"empty_label": {
			"name": "Empty label",
			"type": "string",
			"default": "",
			"description": "Textual label for first row, eg: 'All' or 'None'.  ID will be ''",
		},
	},

	legacyOptions: ["empty_label"],

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param options
	 * @memberOf et2_selectAccount_ro
	 */
	init: function(_parent, options) {
		/**
		Resolve some circular dependency problems here
		selectAccount extends link, link is in a file that needs select,
		select has menulist wrapper, which needs to know about selectAccount before it allows it
		*/
		if(_parent.supportedWidgetClasses.indexOf(et2_selectAccount_ro) < 0)
		{
			_parent.supportedWidgetClasses.push(et2_selectAccount_ro);
		}

		this._super.apply(this, arguments);

		// Legacy options could have row count or empty label in first slot
		if(typeof this.options.empty_label == "string")
		{
			if(isNaN(this.options.empty_label))
			{
				this.options.empty_label = this.egw().lang(this.options.empty_label);
			}
		}

		this.options.application = 'home-accounts';

		// Don't make it look like a link though
		this.list.removeClass("et2_link_string").addClass("et2_selectbox");
	},

	transformAttributes: function(_attrs) {
		et2_selectbox.prototype.transformAttributes.apply(this, arguments);
	},

	set_value: function(_value) {
		// Explode csv
		if(typeof _value == 'string' && _value.indexOf(',') > 0)
		{
			_value = _value.split(',');
		}

		// Don't bother to lookup if it's not an array, or a number
		if(typeof _value == 'object' || !isNaN(_value))
		{
			this._super.apply(this, arguments);
			// Don't make it look like a link though
			jQuery('li',this.list).removeClass("et2_link et2_link_string");
			return;
		}

		// Don't make it look like a link
		jQuery('li',this.list).removeClass("et2_link et2_link_string");

		if(this.options.select_options && this.options.select_options[_value] || this.options.empty_label)
		{
			if(!_value)
			{
				// Empty label from selectbox
				this.list.append("<li>"+this.options.empty_label+"</li>");
			}
			else if (this.options.select_options[_value])
			{
				this.list.append("<li>"+this.options.select_options[_value]+"</li>");
			}
			else if (typeof _value == 'object')
			{
				// An array with 0 / empty in it?
				for(var i = 0; i < _value.length; i++)
				{
					if(!_value[i] || !parseInt(_value[i]))
					{
						this.list.append("<li>"+this.options.empty_label+"</li>");
						return;
					}
					else if (this.options.select_options[_value])
					{
						this.list.append("<li>"+this.options.select_options[_value]+"</li>");
					}
				}
			}
		}
	}
});
et2_register_widget(et2_selectAccount_ro, ["select-account_ro"]);
