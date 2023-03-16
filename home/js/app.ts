/**
 * EGroupware - Home - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package home
 * @author Nathan Gray
 * @copyright (c) 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";
import {EgwApp} from "../../api/js/jsapi/egw_app";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {Et2PortletFavorite} from "./Et2PortletFavorite";
import {loadWebComponent} from "../../api/js/etemplate/Et2Widget/Et2Widget";
import "./Et2PortletLink";
import "./Et2PortletList";
import "./Et2PortletNote";
import './Et2PortletWeather';
import "../../calendar/js/Et2PortletCalendar"
import Sortable from "sortablejs/modular/sortable.complete.esm.js";

/**
 * JS for home application
 *
 * Home is a collection of little bits of content (portlets) from the other applications.
 *
 * Uses Gridster for the grid layout
 * @see http://gridster.net
 * @augments AppJS
 */
export class HomeApp extends EgwApp
{

	/**
	 * Grid resolution.  Must match et2_portlet GRID
	 */
	public static GRID = 150;

	// List of portlets
	private portlets = {};
	portlet_container : any;
	private sortable : Sortable;

	/**
	 * Constructor
	 *
	 */
	constructor()
	{
		// call parent
		super("home");
	}

	/**
	 * Destructor
	 */
	destroy()
	{
		delete this.et2;
		delete this.portlet_container;

		this.portlets = {};

		// call parent
		super.destroy(this.appname);

		// Make sure all other sub-etemplates in portlets are done
		if(this == window.app.home)
		{
			let others = etemplate2.getByApplication(this.appname);
			for(let i = 0; i < others.length; i++)
			{
				others[i].clear();
			}
		}
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} et2 Newly ready object
	 * @param {string} Template name
	 */
	et2_ready(et2, name)
	{
		// Top level
		if(name == 'home.index')
		{
			// call parent
			super.et2_ready(et2, name);

			this.et2.set_id('home.index');
			this.et2.set_actions(this.et2.getArrayMgr('modifications').getEntry('home.index')['actions']);

			this.portlet_container = this.et2.getWidgetById("portlets");

			// Accept drops of favorites, which aren't part of action system
			this.sortable = new Sortable(this.et2.getDOMNode().parentNode, {
				chosenClass: 'drop-hover',
				accept: function(draggable)
				{
					// Check for direct support for that application
					if(draggable[0].dataset && draggable[0].dataset.appname)
					{
						return egw_getActionManager('home', false, 1).getActionById('drop_' + draggable[0].dataset.appname + '_favorite_portlet') != null;
					}
					return false;
				},
				onAdd: function(event, ui)
				{
					debugger;
					// Favorite dropped on home - fake an action and divert to normal handler
					let action = {
						data: {
							class: 'add_home_favorite_portlet'
						}
					}

					// Check for direct support for that application
					if(ui.helper.context.dataset && ui.helper.context.dataset.appname)
					{
						action = egw_getActionManager('home', false, 1).getActionById('drop_' + ui.helper.context.dataset.appname + '_favorite_portlet') || {}
					}
					action.ui = ui;
					app.home.add_from_drop(action, [{data: ui.helper.context.dataset}])
				}
			});

			this._do_ordering()
		}
		else if(et2.uniqueId)
		{
			let portlet_container = this.portlet_container || window.app.home?.portlet_container;
			// Handle bad timing - a sub-template was finished first
			if(!portlet_container)
			{
				window.setTimeout(() => {this.et2_ready(et2, name);}, 200);
				return;
			}
			let portlet = portlet_container.getWidgetById(et2.uniqueId) || et2.DOMContainer;
			// Check for existing etemplate, this one loaded over it
			// NOTE: Moving them around like this can cause problems with event handlers
			let existing = etemplate2.getById(et2.uniqueId);
			if(portlet && existing)
			{
				for(let i = 0; i < portlet._children.length; i++)
				{
					if(typeof portlet._children[i]._init == 'undefined')
					{
						portlet.removeChild(portlet._children[i])
					}
				}
			}
			// Set size & position, if somehow not set yet (Et2Portlet does it)
			if(portlet.style.gridArea == "")
			{
				let et2_data = et2.widgetContainer.getArrayMgr("content").data;
				let settings = et2_data && et2_data.id == portlet.id && et2_data || portlet_container.getArrayMgr("content").data.find(e => et2.uniqueId.endsWith(e.id)) || {settings: {}};
				portlet.settings = settings.settings || {};
				portlet.style.gridArea = settings.row + "/" + settings.col + "/ span " + (settings.height || 1) + "/ span " + (settings.width || 1);
			}


			// It's in the right place for original load, but move it into portlet

			let misplaced = jQuery(etemplate2.getById('home-index').DOMContainer).siblings('#' + et2.DOMContainer.id);

			if(portlet && et2.DOMContainer !== portlet)
			{
				portlet.append(et2.DOMContainer);
				et2.resize();
			}
			if(portlet && misplaced.length)
			{
				et2.DOMContainer.id = et2.uniqueId;
			}

			// Ordering of portlets
			// Only needs to be done once, but its hard to tell when everything is loaded
			this._do_ordering();
		}
	}

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * Home passes the notification off to specific code for each portlet, which
	 * decide if they should be updated or not.
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {string} _targetapp which app's window should be refreshed, default current
	 * @return {false|*} false to stop regular refresh, thought all observers are run
	 */
	observer(_msg, _app, _id, _type, _msg_type, _targetapp)
	{
		for(let id in this.portlets)
		{
			// App is home, refresh all portlets
			if(_app == 'home')
			{
				this.refresh(id);
				continue;
			}

			// Ask the portlets if they're interested
			try
			{
				let code = this.portlets[id];
				if(code)
				{
					code.observer(_msg, _app, _id, _type, _msg_type, _targetapp);
				}
			}
			catch(e)
			{
				this.egw.debug("error", "Error trying to update portlet " + id, e);
			}
		}
		return false;
	}

	/**
	 * Add a new portlet from the context menu
	 */
	add(action, source)
	{
		// Basic portlet attributes
		let attrs = {
			id: this._create_id(),
			class: action.data.class,
			settings: {}
		};
		// Add extra data from action
		Object.keys(action.data).forEach(k =>
		{
			if(["id", "type", "acceptedTypes", "class"].indexOf(k) == -1)
			{
				attrs["settings"][k] = action.data[k];
			}
		})

		// Try to put it about where the menu was opened
		if(action.menu_context)
		{
			let $portlet_container = jQuery(this.portlet_container.getDOMNode());
			attrs.row = Math.max(1, Math.round((action.menu_context.posy - $portlet_container.offset().top) / HomeApp.GRID) + 1);
			// Use "auto" col to avoid any overlap or overflow
			attrs.col = "auto";
		}

		let portlet = <Et2Portlet>loadWebComponent(this.get_portlet_tag(action), attrs, this.portlet_container);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		portlet.update_settings(attrs).then((result) =>
		{
			// Initial add needs to wait for the update to come back, then ask about settings
			// Etemplate can conflict with portlet asking for settings
			if(result === false)
			{
				portlet.edit_settings();
			}
		});
	}

	/**
	 * User dropped something on home.  Add a new portlet
	 */
	add_from_drop(action, source)
	{

		// Actions got confused drop vs popup
		if(source[0].id == 'portlets')
		{
			return this.add(action);
		}

		let $portlet_container = jQuery(this.portlet_container.getDOMNode());

		// Basic portlet attributes
		let attrs = {
			id: this._create_id(),
			class: action.data.class || action.id.substr(5),
			dropped_data: []
		};

		// Try to find where the drop was
		if(action != null && action.ui && action.ui.position)
		{
			attrs.row = Math.max(1, Math.round((action.ui.position.top - $portlet_container.offset().top) / HomeApp.GRID));
			// Use "auto" col to avoid any overlap or overflow
			attrs.col = "auto";
		}

		// Get actual attributes & settings, since they're not available client side yet
		for(let i = 0; i < source.length; i++)
		{
			if(source[i].id)
			{
				attrs.dropped_data.push(source[i].id);
			}
			else
			{
				attrs.dropped_data.push(source[i].data);
			}
		}

		let portlet = <Et2Portlet>loadWebComponent(this.get_portlet_tag(action), attrs, this.portlet_container);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		portlet.update_settings(attrs);
	}

	/**
	 * Set the current selection as default for other users
	 *
	 * Only works (and available) for admins, this shows a dialog to select
	 * the group, and then sets the default for that group.
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	set_default(action, selected)
	{
		// Gather just IDs, server will handle the details
		let portlet_ids = [];
		let group = action.data.portlet_group || false;
		if(selected[0].id == 'home.index')
		{
			// Set all
			this.portlet_container.iterateOver(function(portlet)
			{
				portlet_ids.push(portlet.id);
			}, this, et2_portlet);
		}
		else
		{
			for(let i = 0; i < selected.length; i++)
			{
				portlet_ids.push(selected[i].id);

				// Read the associated group so we can properly remove it
				let portlet = egw.preference(selected[i].id, 'home');
				if(!group && portlet && portlet.group)
				{
					group = portlet.group;
				}
			}
		}

		if(action.id.indexOf("remove_default") == 0)
		{
			// Disable action for feedback
			action.set_enabled(false);

			// Pass them to server
			egw.json('home_ui::ajax_set_default', ['delete', portlet_ids, group]).sendRequest(true);
			return;
		}
		let dialog = et2_createWidget("dialog", {
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: function(button_id, value)
			{
				if(button_id != et2_dialog.OK_BUTTON)
				{
					return;
				}

				// Pass them to server
				egw.json('home_ui::ajax_set_default', ['add', portlet_ids, value.group || false]).sendRequest(true);
			},
			buttons: et2_dialog.BUTTONS_OK_CANCEL,
			title: action.caption,
			template: "home.set_default",
			value: {content: {}, sel_options: {group: {default: egw.lang('All'), forced: egw.lang('Forced')}}}
		});
	}

	/**
	 * Allow a refresh from anywhere by triggering an update with no changes
	 *
	 * @param {string} id
	 */
	refresh(id)
	{
		let p = this.portlet_container.getWidgetById(id);
		if(p)
		{
			p.update_settings('~reload~');
		}
	}

	/**
	 * Determine the correct portlet type to use for the given action
	 *
	 * @param {egwAction} action
	 */
	get_portlet_tag(action : egwAction) : string
	{
		// Try to turn action ID into tag (eg: home_list_portlet -> et2-portlet-list)
		let tag = "et2-" + action.id.replace("add_", "").split("_").reverse().slice(0, -1).map(i => i.toLowerCase()).join("-");
		if(typeof customElements.get(tag) != "undefined")
		{
			return tag;
		}
		return "et2-portlet";
	}

	/**
	 * Determine the best fitting code to use for the given portlet, instanciate
	 * it and add it to the list.
	 *
	 * @param {et2_portlet} portlet
	 * @returns {home_portlet}
	 */
	_get_portlet_code(portlet)
	{
		let classname = portlet.class;
		// Freshly added portlets can have 'add_' prefix
		if(classname.indexOf('add_') == 0)
		{
			classname = classname.replace('add_', '');
		}
		// Prefer a specific match
		let _class = app.classes.home[classname] ||
			(typeof customElements.get(classname) != "undefined" ? customElements.get(classname).class : false) ||
			// If it has a nextmatch, use favorite base class
			(portlet.getWidgetById('nm') ? Et2PortletFavorite : false) ||
			// Fall back to base class
			Et2Portlet;

		this.portlets[portlet.id] = new _class(portlet);

		return this.portlets[portlet.id];
	}

	/**
	 * For link_portlet - opens the configured record when the user
	 * double-clicks or chooses view from the context menu
	 */
	open_link(action)
	{

		// Get widget
		let widget = null;
		while(action.parent != null)
		{
			if(action.data && action.data.widget)
			{
				widget = action.data.widget;
				break;
			}
			action = action.parent;
		}
		if(widget == null)
		{
			this.egw.log("warning", "Could not find widget");
			return;
		}
		this.egw.open(widget.options.settings.entry, "", 'view', null, widget.options.settings.entry.app);
	}

	/**
	 * Set up the positioning of portlets
	 *
	 * This handles portlets that may be offscreen because of wrong settings or changed screen size
	 *
	 */
	_do_ordering()
	{
		if(!this.portlet_container)
		{
			return;
		}


		// Check for column overflow
		const gridStyle = getComputedStyle(this.portlet_container.getDOMNode());
		let col_list = gridStyle.getPropertyValue("grid-template-columns").split(" ") || [];
		const gridWidth = parseInt(gridStyle.width);
		const maxColumn = Math.floor(gridWidth / (parseInt(col_list[0]) || 1));

		// Check for column overlap
		let col_map = {};
		let last_row = 0;
		this.portlet_container.getDOMNode().querySelectorAll("[style*='grid-area']").forEach((n) =>
		{
			let [row] = (getComputedStyle(n).gridRow || "").split(" / ");
			const colData = (getComputedStyle(n).gridColumn || "").split(" / span ");
			let col = parseInt(colData[0]);
			let span = parseInt(colData[1] || "1");
			if(parseInt(row) != last_row && typeof col_map[row] == "undefined")
			{
				last_row = parseInt(row);
				col_map[row] = {};
			}
			// If column is already occupied, or start off screen, or width pushes right side off screen
			if(typeof col_map[row][col] !== "undefined" || col > maxColumn || (col + span) > maxColumn)
			{
				if(col + span > maxColumn)
				{
					span = Math.max(
						1,
						Math.min(span, (maxColumn - (parseInt(Object.keys(col_map[row]).at(-1)) || col)))
					);
				}
				// Set column to auto to avoid overlap
				n.style.gridColumn = "auto / span " + span;
			}
			for(let i = col; i <= span; i++)
			{
				col_map[row][i] = true;
			}
		});
	}

	/**
	 * Create an ID that should be unique, at least amoung a single user's portlets
	 */
	_create_id()
	{
		let id = '';
		do
		{
			id = Math.floor((1 + Math.random()) * 0x10000)
				.toString(16)
				.substring(1);
		}
		while(this.portlet_container.getWidgetById('portlet_' + id));
		return 'portlet_' + id;
	}

	/**
	 * Functions for the list portlet
	 */
	/**
	 * Remove a link from the list
	 */
	link_change(list, link_id, row)
	{
		list.link_change(link_id, row);
	}
}

app.classes.home = HomeApp;


/*

app.classes.home.home_weather_portlet = app.classes.home.home_portlet.extend({
	init: function(portlet) {
		// call parent
		this._super.apply(this, arguments);

		// Use location API
		if(!this.portlet.options.settings && 'geolocation' in navigator)
		{
			navigator.geolocation.getCurrentPosition(function(position) {
				if(portlet && portlet.options && portlet.options.settings &&
					portlet.options.settings.position && portlet.options.settings.position == position.coords.latitude + ',' + position.coords.longitude)
				{
					return;
				}
				portlet._process_edit(et2_dialog.OK_BUTTON, {position: position.coords.latitude + ',' + position.coords.longitude});
			});
		}
	}
});
*/
