/**
 * EGroupware eTemplate2 - JS Favorite widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */

"use strict";

/*egw:uses
	et2_dropdown_button;
	et2_extension_nextmatch;
*/

/**
 * Favorites widget, designed for use with a nextmatch widget
 *
 * The primary control is a split/dropdown button.  Clicking on the left side of the button filters the 
 * nextmatch list by the user's default filter.  The right side of the button gives a list of
 * saved filters, pulled from preferences.  Clicking a filter from the dropdown list sets the
 * filters as saved.
 *
 * Favorites can also automatically be shown in the sidebox, using the special ID favorite_sidebox.
 * Use the following code to generate the sidebox section:
 *  display_sidebox($appname,lang('Favorites'),array(
 *	array(  
 *		'no_lang' => true,
 *		'text'=>'<span id="favorite_sidebox"/>',
 *		'link'=>false,
 *		'icon' => false
 *	)
 * ));
 * This sidebox list will be automatically generated and kept up to date.
 *
 *
 * Favorites are implemented by saving the values for [column] filters.  Filters are stored
 * in preferences, with the name favorite_<name>.  The favorite favorite used for clicking on
 * the filter button is stored in nextmatch-<columnselection_pref>-favorite.
 * 
 * @augments et2_dropdown_button
 */
var et2_favorites = et2_dropdown_button.extend([et2_INextmatchHeader],
{
	attributes: {
		"default_pref": {
			"name": "Default preference key",
			"type": "string",
			"description": "The preference key where default favorite is stored (not the value)"
		},
		"sidebox_target": {
			"name": "Sidebox target",
			"type": "string",
			"description": "ID of element to insert favorite list into",
			"default": "favorite_sidebox"
		},
		"app": {
			"name": "Application",
			"type": "string",
			"description": "Application to show favorites for"
		},
		"filters": {
			"name": "Extra filters",
			"type": "any",
			"description": "Array of extra filters to include in the saved favorite"
		},

		// These are particular to favorites
		id: {"default": "favorite"},
		label: {"default": ""},
		label_updates: { "default": false},
		image: {"default": "etemplate/fav_filter"},
		statustext: {"default": "Favorite queries", "type": "string"}
	},

	// Some convenient variables, used in closures / event handlers
	header: null,
	nextmatch: null,
	favorite_prefix: "favorite_",
	stored_filters: {},

	// If filter was set server side, we need to remember it until nm is created
	nm_filter: false,

	/**
	 * Constructor
	 * 
	 * @memberOf et2_favorites
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.sidebox_target = $j("#"+this.options.sidebox_target);
		if(this.sidebox_target.length == 0 && egw_getFramework() != null)
		{
			var egw_fw = egw_getFramework();
			this.sidebox_target = $j("#"+this.options.sidebox_target,egw_fw.sidemenuDiv);
		}

		var apps = egw().user('apps');
		this.is_admin = (typeof apps['admin'] != "undefined");

		this.stored_filters = this.load_favorites(this.options.app);

		this.preferred = egw.preference(this.options.default_pref,this.options.app);
		if(!this.preferred || typeof this.stored_filters[this.preferred] == "undefined")
		{
			this.preferred = "blank";
		}

		this.init_filters(this);

		this.menu.addClass("favorites");

		// Set the default (button) value
		this.set_value(this.preferred,true);

		// If a pre-selected filter was passed from server
		if(typeof this.options.value != "undefined")
		{
			this.set_value(this.options.value);
		}
			
		var self = this;

		// Initialize sidebox
		if(this.sidebox_target.length)
		{
			this.sidebox_target
				.off()
				.on("mouseenter","div.ui-icon-trash", function() {$j(this).wrap("<span class='ui-state-active'/>");})
				.on("mouseleave","div.ui-icon-trash", function() {$j(this).unwrap();})
				.on("click","div.ui-icon-trash", this, this.delete_favorite)
				.addClass("ui-helper-clearfix");
			this.sidebox_target.on("click","li",function() {
				self.set_value($j(this).attr("id"));
				self.change(this);
			});
		}

		// Add a listener on the radio buttons to set default filter
		$j(this.menu).on("click","input:radio", function(event){
			// Don't do the menu
			event.stopImmediatePropagation();
			
			// Save as default favorite - used when you click the button
			self.egw().set_preference(self.options.app,self.options.default_pref,$j(this).val());
			self.preferred = $j(this).val();

			// Update sidebox, if there
			if(self.sidebox_target.length)
			{
				self.sidebox_target.find(".ui-icon-heart")
					.replaceWith("<img class='sideboxstar'/>");
				$j("li#"+self.preferred+" img",self.sidebox_target)
					.replaceWith("<div class='ui-icon ui-icon-heart'/>");
				
			}

			// Close the menu
			self.menu.hide();

			// Some user feedback
			self.button.addClass("ui-state-active", 500,"swing",function(){
				self.button.removeClass("ui-state-active",2000);
			});
		});

		// Add a listener on the delete to remove
		this.menu.on("click","div.ui-icon-trash", this, this.delete_favorite)
			// Wrap and unwrap because jQueryUI styles use a parent, and we don't want to change the state of the menu item
			// Wrap in a span instead of a div because div gets a border
			.on("mouseenter","div.ui-icon-trash", function() {$j(this).wrap("<span class='ui-state-active'/>");})
			.on("mouseleave","div.ui-icon-trash", function() {$j(this).unwrap();});

		// Trigger refresh of menu options now that events are registered
		// to update sidebox
		if(this.sidebox_target.length > 0)
		{
			this.init_filters(this);
		}
	},

	destroy: function() {
		if(this.popup != null)
		{
			this.popup.dialog("destroy");
			this.popup = null;
		}
		if(this.sidebox_target.length)
		{
			this.sidebox_target
				.off()
				.empty();
		}
		this._super.apply(this, arguments);
	},

	/**
	 * Load favorites from preferences
	 *
	 * @param app String Load favorites from this application
	 */
	load_favorites: function(app) {

		// Default blank filter
		var stored_filters = {
			'blank': {
				name: this.egw().lang("No filters"),
				filters: {},
			}
		};

		// Load saved favorites
		var preferences = egw.preference("*",app);
		for(var pref_name in preferences)
		{
			if(pref_name.indexOf(this.favorite_prefix) == 0)
			{
				var name = pref_name.substr(this.favorite_prefix.length);
				stored_filters[name] = preferences[pref_name];
			}
		}
		if(typeof stored_filters == "undefined" || !stored_filters)
		{
			stored_filters = {};
		}

		return stored_filters;
	},

	/**
	 * Delete a favorite from the list and update preferences
	 * Registered as a handler on the delete icons
	 */
	delete_favorite: function(event)
	{
		// Don't do the menu
		event.stopImmediatePropagation();

		var header = event.data;
		var name = $j(this).parentsUntil("li").parent().attr("id");

		// Make sure first
		var do_delete = function(button_id)
		{
			if(button_id != et2_dialog.YES_BUTTON) return;

			// Hide the trash
			$j(this).hide();

			// Delete preference server side
			var request = new egw_json_request("etemplate_widget_nextmatch::ajax_set_favorite::etemplate",
				[header.app, name, "delete", header.stored_filters[name].group ? header.stored_filters[name].group : '', ''],
				header
			);
			request.sendRequest(true, function(result) {
				if(result)
				{
					// Remove line from list
					this.slideUp("slow", function() { header.menu.hide();});
					delete header.stored_filters[name];
					header.init_filters(header);
				}
			}, $j(this).parentsUntil("li").parent());
		}
		et2_dialog.show_dialog(do_delete, (header.egw().lang("Delete") + " " +header.stored_filters[name].name +"?"),
			"Delete", et2_dialog.YES_NO, et2_dialog.QUESTION_MESSAGE);
	},

	// Create & set filter options for dropdown menu
	init_filters: function(widget, filters)
	{
		if(typeof filters == "undefined")
		{
			filters = this.stored_filters;
		}

		var options = {};
		for(var name in filters)
		{
			options[name] = "<input type='radio' name='favorite[button][favorite]' value='"+name+"' title='" + 
				this.egw().lang('Set as default') + "'/>"+
				(filters[name].name != undefined ? filters[name].name : name) + 
				(filters[name].group != false ? " ♦" :"") +
				(filters[name].group != false && !this.is_admin || name == 'blank' ? "" :
				"<div class='ui-icon ui-icon-trash' title='" + this.egw().lang('Delete') + "'/>");
		}

		// Only add 'Add current' if we have a nextmatch
		if(this.nextmatch)
		{
			options.add = "<img src='"+this.egw().image("new") +"'/>Add current";
		}
		widget.set_select_options(options);

		// Set radio to current value
		$j("input[value='"+ this.preferred +"']:radio", this.menu).attr("checked",true);

		// Clone for sidebox
		if(this.sidebox_target.length)
		{
			this.sidebox_target.empty();
			var sidebox_clone = widget.menu.clone();
			sidebox_clone
				.appendTo(this.sidebox_target)
				.removeAttr('style')
				.menu()
				.removeClass("ui-widget")
				.show()
				.find("input:checked").replaceWith("<div class='ui-icon ui-icon-heart'/>");
			sidebox_clone
				.find("input").replaceWith("<img class='sideboxstar'/>");

		}
	},

	set_nm_filters: function(filters)
	{
		if(this.nextmatch)
		{
			this.nextmatch.activeFilters = filters;
			this.nextmatch.applyFilters();
		}
		else
		{
			console.log(filters);
		}
	},

	onclick: function(node) {
		// Apply preferred filter - make sure it's an object, and not a reference
		if(this.preferred && this.stored_filters[this.preferred])
		{
			this.set_nm_filters(jQuery.extend({},this.stored_filters[this.preferred].filter));
		}
		else
		{
			alert(this.egw().lang("No default set"));
		}
	},

	
	// Apply the favorite when you pick from the list
	change: function(selected_node) {
		this.value = $j(selected_node).attr("id");
		if(this.value == "add" && this.nextmatch)
		{
			// Get current filters
			this.popup.current_filters = $j.extend({},this.nextmatch.activeFilters);

			// Skip columns for now
			delete this.popup.current_filters.selcolumns;

			// Add in application's settings
			if(this.filters != true)
			{
				for(var i = 0; i < this.filters.length; i++)
				{
					this.popup.current_filters[this.options.filters[i]] = this.nextmatch.options.settings[this.options.filters[i]];
				}
			}
		
			// Remove some internal values
			delete this.popup.current_filters[this.id];
			if(this.popup.group != undefined)
			{
				delete this.popup.current_filters[this.popup.group.id];
			}

			// Make sure it's an object - deep copy to prevent references in sub-objects (col_filters)
			this.popup.current_filters = jQuery.extend(true,{},this.popup.current_filters);

			// Update popup with current set filters (more for debug than user)
			var filter_list = [];
			var add_to_popup = function(arr) {
				filter_list.push("<ul>");
				jQuery.each(arr, function(index, filter) {
					filter_list.push("<li id='index'><span class='filter_id'>"+index+"</span>" + 
						(typeof filter != "object" ? "<span class='filter_value'>"+filter+"</span>": "")
					);
					if(typeof filter == "object" && filter != null) add_to_popup(filter);
					filter_list.push("</li>");
				});
				filter_list.push("</ul>");
			}
			add_to_popup(this.popup.current_filters);
			$j("#nm_favorites_popup_filters",this.popup)
				.replaceWith(
					$j(filter_list.join("")).attr("id","nm_favorites_popup_filters")
				);
			$j("#nm_favorites_popup_filters",this.popup)
				.hide()
				.siblings(".ui-icon-circle-plus")
				.removeClass("ui-icon-circle-minus");
			
			// Popup
			this.popup.dialog("open");

			// Reset value
			this.set_value(this.preferred,true);
		}
	},


	/**
	 * Create the "Add new" popup dialog
	 */
	create_popup: function()
	{
		var self = this;

		// Create popup
		this.popup = $j('<div id="nm_favorites_popup" title="' + egw().lang("New favorite") + '">\
			<form>\
			<label for="name">'+ 
				this.egw().lang("name") +
			'</label>' + 

			'<input type="text" name="name" id="name"/>\
			<div id="nm_favorites_popup_admin"/>\
			<span>'+ this.egw().lang("Details") + '</span><span style="float:left;" class="ui-icon ui-icon-circle-plus" />\
			<ul id="nm_favorites_popup_filters"/>\
			</form>\
			</div>'
		).appendTo(this.div);

		$j(".ui-icon-circle-plus",this.popup).prev().andSelf().click(function() {
			var details = $j("#nm_favorites_popup_filters",this.popup)
				.slideToggle()
				.siblings(".ui-icon-circle-plus")
				.toggleClass("ui-icon-circle-minus");
		});

		// Add some controls if user is an admin
		if(this.is_admin)
		{
			this.popup.group = et2_createWidget("select-account",{
				id: "favorite[group]",
				account_type: "groups",
				empty_label: "Groups",
				no_lang: true,
				parent_node: 'nm_favorites_popup_admin'
			},this);
/*
			$j("#nm_favorites_popup_admin",this.popup)
				.append(this.popup.group.getDOMNode(this.popup.group));
*/

		}

		var buttons = {};
		buttons[this.egw().lang("save")] =  function() {
			// Add a new favorite
			var name = $j("#name",this);

			if(name.val())
			{
				// Add to the list
				name.val(name.val().replace(/(<([^>]+)>)/ig,""));
				var safe_name = name.val().replace(/[^A-Za-z0-9-_]/g,"_");
				self.stored_filters[safe_name] = {
					name: name.val(),
					group: (typeof self.popup.group != "undefined" && 
						self.popup.group.get_value() ? self.popup.group.get_value() : false),
					filter: self.popup.current_filters
				};
				self.init_filters(self);

				var favorite_pref = self.favorite_prefix+safe_name;

				// Save to preferences
				if(typeof self.popup.group != "undefined")
				{
					// Admin stuff - save preference server side
					var request = new egw_json_request("etemplate_widget_nextmatch::ajax_set_favorite::etemplate",
						[
							self.options.app,
							name.val(),
							"add",
							self.popup.group.get_value(),
							self.popup.current_filters
						],
						self
					);
					request.sendRequest(true, function(result) {
						if(result)
						{
							// Do something nice and confirmy if result is true - not really needed
						}
					}, self);
					self.popup.group.set_value(null);
				}
				else
				{
					// Normal user - just save to preferences client side
					self.egw().set_preference(self.options.app,favorite_pref,{
						name: name.val(),
						group: false,
						filter:self.popup.current_filters
					});
				}
				delete self.popup.current_filters;
			}
			// Reset form
			name.val("");
			$j("#filters",self.popup).empty();

			$j(this).dialog("close");
		};
		buttons[this.egw().lang("cancel")] = function() {
			self.popup.group.set_value(null);
			$j(this).dialog("close");
		};
			
		this.popup.dialog({
			autoOpen: false,
			modal: true,
			buttons: buttons,
			close: function() {
			}
		});
	},

	set_value: function(filter_name, parent) {
		if(parent)
		{
			return this._super.apply(filter_name);
		}
		if(this.nextmatch)
		{
			if(this.stored_filters[filter_name])
			{
				// Apply selected filter - make sure it's an object, and not a reference
				this.set_nm_filters(jQuery.extend(true, {},this.stored_filters[filter_name].filter));
			}
		}
		else
		{
			// Too soon - nm doesn't exist yet
			this.nm_filter = filter_name;
		}
	},

	getValue: function()
	{
		return null;
	},

	/**
	 * Set the nextmatch to filter
	 * From et2_INextmatchHeader interface
	 */
	setNextmatch: function(nextmatch)
	{
		this.nextmatch = nextmatch;

		if(this.nm_filter)
		{
			this.set_value(this.nm_filter);
			this.nm_filter = false;
		}

		// Re-generate filter list so we can add 'Add current'
		this.init_filters(this);

		// With no Add current, this is only needed when there's a nm
		this.create_popup();
	}
});
et2_register_widget(et2_favorites, ["favorites"]);
