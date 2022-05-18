/**
 * EGroupware eTemplate2 - JS Favorite widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 */

/*egw:uses
	et2_dropdown_button;
	et2_extension_nextmatch;
*/

import {WidgetConfig} from "./et2_core_widget";
import {et2_INextmatchHeader} from "./et2_extension_nextmatch";
import {et2_dropdown_button} from "./et2_widget_dropdown_button";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {egw, egw_getFramework} from "../jsapi/egw_global";
import Sortable from 'sortablejs/modular/sortable.complete.esm.js';

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
export class et2_favorites extends et2_dropdown_button implements et2_INextmatchHeader
{
	static readonly _attributes : any = {
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
		image: {"default": egw().image('fav_filter')},
		statustext: {"default": "Favorite queries", "type": "string"}
	};

	// Some convenient variables, used in closures / event handlers
	header = null;
	nextmatch = null;
	public static readonly PREFIX = "favorite_";
	private stored_filters: {};
	private favSortedList : any = null;
	private sidebox_target : JQuery = null;
	private preferred;
	static is_admin : boolean;
	private filters : any;

	// If filter was set server side, we need to remember it until nm is created
	nm_filter = false;

	/**
	 * Constructor
	 *
	 * @memberOf et2_favorites
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_favorites._attributes, _child || {}));
		this.sidebox_target = jQuery("#"+this.options.sidebox_target);
		if(this.sidebox_target.length == 0 && egw_getFramework() != null)
		{
			let egw_fw = egw_getFramework();
			this.sidebox_target = jQuery("#"+this.options.sidebox_target,egw_fw.sidemenuDiv);
		}
		// Store array of sorted items
		this.favSortedList = ['blank'];

		let apps = egw().user('apps');
		et2_favorites.is_admin = (typeof apps['admin'] != "undefined");

		// Make sure we have an app
		if(!this.options.app)
		{
			this.options.app = this.getInstanceManager().app;
		}

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

		let self = this;

		// Add a listener on the radio buttons to set default filter
		jQuery(this.menu).on("click","input:radio", function(event){
			// Don't do the menu
			event.stopImmediatePropagation();

			// Save as default favorite - used when you click the button
			self.egw().set_preference(self.options.app,self.options.default_pref,jQuery(this).val());
			self.preferred = jQuery(this).val();

			// Update sidebox, if there
			if(self.sidebox_target.length)
			{
				jQuery("div.ui-icon-heart", self.sidebox_target)
					.replaceWith("<div class='sideboxstar'/>");
				jQuery("li[data-id='"+self.preferred+"'] div.sideboxstar",self.sidebox_target)
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
		let sideBoxDOMNodeSort = function (_favSList) {
			let favS = jQuery.isArray(_favSList)?_favSList.slice(0).reverse():[];

			for (let i=0; i < favS.length;i++)
			{
				self.sidebox_target.children().find('[data-id$="' + favS[i] + '"]').prependTo(self.sidebox_target.children());
			}
		};

		/**
		 * todo (@todo-jquery-ui): the sorting does not work at the moment becuase of jquery-ui menu being used in order to create dropdown
		 * buttons menu. Once we replace the et2_widget_dropdown_button with web component this should be adapted
		 * and working again.
		 **/
		let sortablejs = Sortable.create(this.menu[0], {
			ghostClass: 'ui-fav-sortable-placeholder',
			draggable: 'li:not([data-id$="add"])',
			delay: 25,
			dataIdAttr:'data-id',
			onSort: function(event){
				self.favSortedList  = sortablejs.toArray();
				self.egw.set_preference(self.options.app,'fav_sort_pref', self.favSortedList );
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
			.on("mouseenter","div.ui-icon-trash", function() {jQuery(this).wrap("<span class='ui-state-active'/>");})
			.on("mouseleave","div.ui-icon-trash", function() {jQuery(this).unwrap();});

		// Trigger refresh of menu options now that events are registered
		// to update sidebox
		if(this.sidebox_target.length > 0)
		{
			this.init_filters(this);
		}
	}

	/**
	 * Load favorites from preferences
	 *
	 * @param app String Load favorites from this application
	 */
	load_favorites(app)
	{

		// Default blank filter
		let stored_filters : any = {
			'blank': {
				name: this.egw().lang("No filters"),
				state: {}
			}
		};

		// Load saved favorites
		let preferences : any = egw.preference("*",app);
		for(let pref_name in preferences)
		{
			if(pref_name.indexOf(et2_favorites.PREFIX) == 0 && typeof preferences[pref_name] == 'object')
			{
				let name = pref_name.substr(et2_favorites.PREFIX.length);
				stored_filters[name] = preferences[pref_name];
				// Keep older favorites working - they used to store nm filters in 'filters',not state
				if(preferences[pref_name]["filters"])
				{
					stored_filters[pref_name]["state"] = preferences[pref_name]["filters"];
				}
			}
			if (pref_name == 'fav_sort_pref')
			{
				this.favSortedList = preferences[pref_name];
				//Make sure sorted list is always an array, seems some old fav are not array
				if (!jQuery.isArray(this.favSortedList)) this.favSortedList = this.favSortedList.split(',');
			}
		}
		if(typeof stored_filters == "undefined" || !stored_filters)
		{
			stored_filters = {};
		}
		else
		{
			for(let name in stored_filters)
			{
				if (this.favSortedList.indexOf(name) < 0)
				{
					this.favSortedList.push(name);
				}
			}
			this.egw().set_preference (this.options.app,'fav_sort_pref',this.favSortedList);
			if (this.favSortedList.length > 0)
			{
				let sortedListObj = {};

				for (let i=0; i < this.favSortedList.length; i++)
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
		}
		return stored_filters;
	}

	// Create & set filter options for dropdown menu
	init_filters(widget, filters?)
	{
		if(typeof filters == "undefined")
		{
			filters = this.stored_filters;
		}

		let options = {};
		for(let name in filters)
		{
			options[name] = "<input type='radio' name='"+this.internal_ids.menu+"[button][favorite]' value='"+name+"' title='" +
				this.egw().lang('Set as default') + "'/>"+
				(filters[name].name != undefined ? filters[name].name : name) +
				(filters[name].group != false && !et2_favorites.is_admin || name == 'blank' ? "" :
					"<div class='ui-icon ui-icon-trash' title='" + this.egw().lang('Delete') + "'/>");
		}

		// Only add 'Add current' if we have a nextmatch
		if(this.nextmatch)
		{
			options["add"] = "<img src='"+this.egw().image("new") +"'/>"+this.egw().lang('Add current');
		}
		widget.set_select_options.call(widget,options);

		// Set radio to current value
		jQuery("input[value='"+ this.preferred +"']:radio", this.menu).attr("checked",1);
	}

	set_nm_filters(filters)
	{
		if(this.nextmatch)
		{
			this.nextmatch.applyFilters(filters);
		}
		else
		{
			console.log(filters);
		}
	}

	onclick(node)
	{
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
	}

	// Apply the favorite when you pick from the list
	change(selected_node)
	{
		this.value = jQuery(selected_node).attr("data-id");
		if(this.value == "add" && this.nextmatch)
		{
			// Get current filters
			let current_filters = jQuery.extend({},this.nextmatch.activeFilters);

			// Add in extras
			for(let extra in this.options.filters)
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
				for(let i = 0; i < this.filters.length; i++)
				{
					current_filters[this.options.filters[i]] = this.nextmatch.options.settings[this.options.filters[i]];
				}
			}

			// Call framework
			app[this.options.app].add_favorite(current_filters);

			// Reset value
			this.set_value(this.preferred,true);
		}
		else if (this.value == 'blank')
		{
			// Reset filters when select no filters
			this.set_nm_filters({});
		}
	}

	set_value(filter_name, parent? : boolean) : void | boolean
	{
		if(parent)
		{
			return super.set_value(filter_name);
		}

		if(filter_name == 'add') return false;

		app[this.options.app].setState(this.stored_filters[filter_name]);
		return false;
	}

	getValue()
	{
		return null;
	}

	/**
	 * Set the nextmatch to filter
	 * From et2_INextmatchHeader interface
	 *
	 * @param {et2_nextmatch} nextmatch
	 */
	setNextmatch(nextmatch)
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
}

