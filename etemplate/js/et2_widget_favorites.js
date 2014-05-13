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
		// Store array of sorted items
		this.favSortedList = [];
		
		var apps = egw().user('apps');
		this.is_admin = (typeof apps['admin'] != "undefined");
		
		this.stored_filters = this.load_favorites(this.options.app);
		
		this.preferred = egw.preference(this.options.default_pref,this.options.app);
		if(!this.preferred || typeof this.stored_filters[this.preferred] == "undefined")
		{
			this.preferred = "blank";
		}
		
		// It helps to have the ID properly set before we get too far
		this.set_id(this.id);

		this.init_filters(this);

		this.menu.addClass("favorites");

		// Set the default (button) value
		this.set_value(this.preferred,true);

		var self = this;

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
				self.sidebox_target.find("div.ui-icon-heart")
					.replaceWith("<div class='sideboxstar'/>");
				$j("li[data-id='"+self.preferred+"'] div.sideboxstar",self.sidebox_target)
					.replaceWith("<div class='ui-icon ui-icon-heart'/>");
			}

			// Close the menu
			self.menu.hide();

			// Some user feedback
			self.button.addClass("ui-state-active", 500,"swing",function(){
				self.button.removeClass("ui-state-active",2000);
			});
		});
		
		//Sort DomNodes of sidebox fav. menu
		var sideBoxDOMNodeSort = function (_favSList) {
			var favS = jQuery.isArray(_favSList)?_favSList.slice(0).reverse():[];
			
			for (var i=0; i < favS.length;i++)
			{
				self.sidebox_target.children().find('[data-id$="' + favS[i] + '"]').prependTo(self.sidebox_target.children());
			}
		};
		
		//Add Sortable handler to nm fav. menu
		$j(this.menu).sortable({
			
			items:'li:not([data-id$="add"])',
			placeholder:'ui-fav-sortable-placeholder',
			update: function (event, ui)
			{
				self.favSortedList = jQuery(this).sortable('toArray', {attribute:'data-id'});
				
				self.egw().set_preference(self.options.app,'fav_sort_pref',self.favSortedList);
				
				sideBoxDOMNodeSort(self.favSortedList);
			}
		});
		
		// Add a listener on the delete to remove
		this.menu.on("click","div.ui-icon-trash", app[self.options.app], function() {
				// App instance might not be ready yet, so don't bind directly
				app[self.options.app].delete_favorite.apply(this,arguments);
			})
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
				state: {}
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
				// Keep older favorites working - they used to store nm filters in 'filters',not state
				if(preferences[pref_name].filters)
				{
					stored_filters[pref_name].state = preferences[pref_name].filters;
				}
			}
			if (pref_name == 'fav_sort_pref')
			{
				 this.favSortedList = preferences[pref_name];
			}
		}
		if(typeof stored_filters == "undefined" || !stored_filters)
		{
			stored_filters = {};
		}
		else if (this.favSortedList.length > 0)
		{
			var sortedListObj = {};

			for (var i=0;i < this.favSortedList.length;i++)
			{
				if (typeof stored_filters[this.favSortedList[i]] != 'undefined')
				{
					sortedListObj[this.favSortedList[i]] = stored_filters[this.favSortedList[i]];
				}
				else
				{
					this.favSortedList.splice(i,1);
					this.egw().set_preference (this.options.app,'fav_sort_pref',this.favSortedList);
				}
			}
			stored_filters = jQuery.extend(sortedListObj,stored_filters);
		}
		
		return stored_filters;
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
			options[name] = "<input type='radio' name='"+this.internal_ids.menu+"[button][favorite]' value='"+name+"' title='" +
				this.egw().lang('Set as default') + "'/>"+
				(filters[name].name != undefined ? filters[name].name : name) +
				(filters[name].group != false && !this.is_admin || name == 'blank' ? "" :
				"<div class='ui-icon ui-icon-trash' title='" + this.egw().lang('Delete') + "'/>");
		}

		// Only add 'Add current' if we have a nextmatch
		if(this.nextmatch)
		{
			options.add = "<img src='"+this.egw().image("new") +"'/>Add current";
		}
		widget.set_select_options.call(widget,options);

		// Set radio to current value
		$j("input[value='"+ this.preferred +"']:radio", this.menu).attr("checked",true);
	},

	set_nm_filters: function(filters)
	{
		if(this.nextmatch)
		{
			this.nextmatch.applyFilters(filters);
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
			// use app[appname].setState if available to allow app to overwrite it (eg. change to non-listview in calendar)
			if (typeof app[this.options.app] != 'undefined')
			{
				app[this.options.app].setState(this.stored_filters[this.preferred]);
			}
			else
			{
				this.set_nm_filters(jQuery.extend({},this.stored_filters[this.preferred].state));
			}
		}
		else
		{
			alert(this.egw().lang("No default set"));
		}
	},

	// Apply the favorite when you pick from the list
	change: function(selected_node) {
		this.value = $j(selected_node).attr("data-id");
		if(this.value == "add" && this.nextmatch)
		{
			// Get current filters
			var current_filters = $j.extend({},this.nextmatch.activeFilters);

			// Add in extras
			for(var extra in this.options.filters)
			{
				// Don't overwrite what nm has, chances are nm has more up-to-date value
				if(typeof current_filters == 'undefined')
				{
					current_filters[extra] = this.nextmatch.options.settings[extra];
				}
			}

			// Skip columns for now
			delete current_filters.selcolumns;

			// Add in application's settings
			if(this.filters != true)
			{
				for(var i = 0; i < this.filters.length; i++)
				{
					current_filters[this.options.filters[i]] = this.nextmatch.options.settings[this.options.filters[i]];
				}
			}

			// Call framework
			app[this.options.app].add_favorite(current_filters);

			// Reset value
			this.set_value(this.preferred,true);
		}
	},

	set_value: function(filter_name, parent) {
		if(parent)
		{
			return this._super.call(this, filter_name);
		}

		if(filter_name == 'add') return false;

		app[this.options.app].setState(this.stored_filters[filter_name]);
		return false;
	},

	getValue: function()
	{
		return null;
	},

	/**
	 * Set the nextmatch to filter
	 * From et2_INextmatchHeader interface
	 *
	 * @param {et2_nextmatch} nextmatch
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
	}
});
et2_register_widget(et2_favorites, ["favorites"]);
