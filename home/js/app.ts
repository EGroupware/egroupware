/**
 * EGroupware - Home - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package home
 * @author Nathan Gray
 * @copyright (c) 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {AppJS} from "../../api/js/jsapi/app_base.js";
import {et2_createWidget} from "../../api/js/etemplate/et2_core_widget";
import {EgwApp} from "../../api/js/jsapi/egw_app";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {Et2PortletFavorite} from "./Et2PortletFavorite";

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
	public static GRID = 50;

	/**
	 * Default size for new portlets
	 */
	public static DEFAULT = {
		WIDTH: 4,
		HEIGHT: 1
	};

	// List of portlets
	private portlets = {};
	portlet_container : any;

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
		let others = etemplate2.getByApplication(this.appname);
		for(let i = 0; i < others.length; i++)
		{
			others[i].clear();
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

			// Set up sorting of portlets
			//this._do_ordering();

			// Accept drops of favorites, which aren't part of action system
			jQuery(this.et2.getDOMNode().parentNode).droppable({
				hoverClass: 'drop-hover',
				accept: function(draggable)
				{
					// Check for direct support for that application
					if(draggable[0].dataset && draggable[0].dataset.appname)
					{
						return egw_getActionManager('home', false, 1).getActionById('drop_' + draggable[0].dataset.appname + '_favorite_portlet') != null;
					}
					return false;
				},
				drop: function(event, ui)
				{
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
			})
				// Bind to unload to remove it from our list
				.on('clear', '.et2_container[id]', jQuery.proxy(function(e)
				{
					if(e.target && e.target.id && this.portlets[e.target.id])
					{
						this.portlets[e.target.id].destroy();
						delete this.portlets[e.target.id];
					}
				}, this));
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
			let portlet = portlet_container.getWidgetById(et2.uniqueId);
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
			// Set size & position
			let settings = portlet_container.getArrayMgr("content").data.find(e => e.id == et2.uniqueId) || {};
			portlet.style.gridArea = settings.row + "/" + settings.col + "/ span " + (settings.height || 1) + "/ span " + (settings.width || 1);
			
			// It's in the right place for original load, but move it into portlet
			let misplaced = jQuery(etemplate2.getById('home-index').DOMContainer).siblings('#' + et2.DOMContainer.id);
			if(portlet)
			{
				portlet.addChild(et2.widgetContainer);
				et2.resize();
			}
			if(portlet && misplaced.length)
			{
				et2.DOMContainer.id = et2.uniqueId;
			}

			// Instanciate custom code for this portlet
			this._get_portlet_code(portlet);
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
			...HomeApp.DEFAULT, ...{
				id: this._create_id(),
				class: action.data.class
			}
		};

		// Try to put it about where the menu was opened
		if(action.menu_context)
		{
			let $portlet_container = jQuery(this.portlet_container.getDOMNode());
			attrs.row = Math.max(1, Math.round((action.menu_context.posy - $portlet_container.offset().top) / HomeApp.GRID) + 1);
			attrs.col = Math.max(1, Math.round((action.menu_context.posx - $portlet_container.offset().left) / HomeApp.GRID) + 1);
		}

		let portlet = <Et2Portlet>et2_createWidget('et2-portlet', attrs, this.portlet_container);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		portlet.update_settings(attrs);

		// Instanciate custom code for this portlet
		this._get_portlet_code(portlet);
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
			width: this.DEFAULT.WIDTH,
			height: this.DEFAULT.HEIGHT
		};

		// Try to find where the drop was
		if(action != null && action.ui && action.ui.position)
		{
			attrs.row = Math.max(1, Math.round((action.ui.position.top - $portlet_container.offset().top) / this.GRID));
			attrs.col = Math.max(1, Math.round((action.ui.position.left - $portlet_container.offset().left) / this.GRID));
		}

		let portlet = <Et2Portlet>et2_createWidget('portlet', jQuery.extend({}, attrs), this.portlet_container);
		portlet.loadingFinished();
		// Immediately add content ID so etemplate loads into the right place
		portlet.content.append('<div id="' + attrs.id + '" class="et2_container"/>');

		// Get actual attributes & settings, since they're not available client side yet
		let drop_data = [];
		for(let i = 0; i < source.length; i++)
		{
			if(source[i].id)
			{
				drop_data.push(source[i].id);
			}
			else
			{
				drop_data.push(source[i].data);
			}
		}
		// Don't pass default width & height so class can set it
		delete attrs.width;
		delete attrs.height;
		portlet._process_edit(et2_dialog.OK_BUTTON, jQuery.extend({dropped_data: drop_data}, attrs));

		// Set up sorting/grid of new portlet
		$portlet_container.data("gridster").add_widget(
			portlet.getDOMNode(),
			this.DEFAULT.WIDTH, this.DEFAULT.HEIGHT,
			attrs.col, attrs.row
		);

		// Instanciate custom code for this portlet
		this._get_portlet_code(portlet);
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
			p._process_edit(et2_dialog.OK_BUTTON, '~reload~');
		}
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
	 * Set up the drag / drop / re-order of portlets
	 */
	_do_ordering()
	{
		let $portlet_container = jQuery(this.portlet_container.getDOMNode());
		$portlet_container
			/* Gridster */
			.gridster({
				widget_selector: 'div.et2_portlet',
				// Dimensions + margins = grid spacing
				widget_base_dimensions: [home.GRID - 5, home.GRID - 5],
				widget_margins: [5, 5],
				extra_rows: 1,
				extra_cols: 1,
				min_cols: 3,
				min_rows: 3,
				/**
				 * Set which parameters we want when calling serialize().
				 * @param $w jQuery jQuery-wrapped element
				 * @param grid Object Grid settings
				 * @return Object - will be returned by gridster.serialize()
				 */
				serialize_params: function($w, grid)
				{
					return {
						id: $w.attr('id').replace(app.home.portlet_container.getInstanceManager().uniqueId + '_', ''),
						row: grid.row,
						col: grid.col,
						width: grid.size_x,
						height: grid.size_y
					};
				},
				/**
				 * Gridster's internal drag settings
				 */
				draggable: {
					handle: '.ui-widget-header',
					stop: function(event, ui)
					{
						// Update widget(s)
						let changed = this.serialize_changed();

						// Reset changed, or they keep accumulating
						this.$changed = jQuery([]);

						for(let key in changed)
						{
							if(!changed[key].id)
							{
								continue;
							}
							// Changed ID is the ID
							let widget = window.app.home.portlet_container.getWidgetById(changed[key].id);
							if(!widget || widget == window.app.home.portlet_container)
							{
								continue;
							}

							egw().jsonq("home.home_ui.ajax_set_properties", [changed[key].id, {}, {
									row: changed[key].row,
									col: changed[key].col
								}, widget.settings ? widget.settings.group : false],
								null,
								widget, true, widget
							);
						}
					}
				}

			});

		// Rescue selectboxes from Firefox
		$portlet_container.on('mousedown touchstart', 'select', function(e)
		{
			e.stopPropagation();
		});
		// Bind window resize to re-layout gridster
		jQuery(window).one("resize." + this.et2._inst.uniqueId, function()
		{
			// Note this doesn't change the positions, just makes them invalid
			$portlet_container.data('gridster').recalculate_faux_grid();
		});
		// Bind resize to update gridster - this may happen _before_ the widget gets a
		// chance to update itself, so we can't use the widget
		$portlet_container
			.on("resizestop", function(event, ui)
			{
				$portlet_container.data("gridster").resize_widget(
					ui.element,
					Math.round(ui.size.width / app.home.GRID),
					Math.round(ui.size.height / app.home.GRID)
				);
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
	 * For list_portlet - opens a dialog to add a new entry to the list
	 *
	 * @param {egwAction} action Drop or add action
	 * @param {egwActionObject[]} Selected entries
	 * @param {egwActionObject} target_action Drop target
	 */
	add_link(action, source, target_action)
	{
		// Actions got confused drop vs popup
		if(source[0].id == 'portlets')
		{
			return this.add_link(action);
		}

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
		if(target_action == null)
		{
			// use template base url from initial template, to continue using webdav, if that was loaded via webdav
			let splitted = 'home.edit'.split('.');
			let path = app.home.portlet_container.getRoot()._inst.template_base_url + splitted.shift() + "/templates/default/" +
				splitted.join('.') + ".xet";
			let dialog = et2_createWidget("dialog", {
				callback: function(button_id, value)
				{
					if(button_id == et2_dialog.CANCEL_BUTTON)
					{
						return;
					}
					let new_list = widget.options.settings.list || [];
					for(let i = 0; i < new_list.length; i++)
					{
						if(new_list[i].app == value.add.app && new_list[i].id == value.add.id)
						{
							// Duplicate - skip it
							return;
						}
					}
					value.add.link_id = value.add.app + ':' + value.add.id;
					// Update server side
					new_list.push(value.add);
					widget._process_edit(button_id, {list: new_list});
					// Update client side
					let list = widget.getWidgetById('list');
					if(list)
					{
						list.set_value(new_list);
					}
				},
				buttons: et2_dialog.BUTTONS_OK_CANCEL,
				title: app.home.egw.lang('add'),
				template: path,
				value: {content: [{label: app.home.egw.lang('add'), type: 'link-entry', name: 'add', size: ''}]}
			});
		}
		else
		{
			// Drag'n'dropped something on the list - just send action IDs
			let new_list = widget.options.settings.list || [];
			let changed = false;
			for(let i = 0; i < new_list.length; i++)
			{
				// Avoid duplicates
				for(let j = 0; j < source.length; j++)
				{
					if(!source[j].id || new_list[i].app + "::" + new_list[i].id == source[j].id)
					{
						// Duplicate - skip it
						source.splice(j, 1);
					}
				}
			}
			for(let i = 0; i < source.length; i++)
			{
				let explode = source[i].id.split('::');
				new_list.push({app: explode[0], id: explode[1], link_id: explode.join(':')});
				changed = true;
			}
			if(changed)
			{
				widget._process_edit(et2_dialog.OK_BUTTON, {
					list: new_list || {}
				});
			}
			// Filemanager support - links need app = 'file' and type set
			for(let i = 0; i < new_list.length; i++)
			{
				if(new_list[i]['app'] == 'filemanager')
				{
					new_list[i]['app'] = 'file';
					new_list[i]['path'] = new_list[i]['title'] = new_list[i]['icon'] = new_list[i]['id'];
				}
			}

			widget.getWidgetById('list').set_value(new_list);
		}
	}

	/**
	 * Remove a link from the list
	 */
	link_change(list, link_id, row)
	{
		// Quick response client side
		row.slideUp(row.remove);

		// Actual removal
		let portlet = list._parent._parent;
		portlet.options.settings.list.splice(row.index(), 1);
		portlet._process_edit(et2_dialog.OK_BUTTON, {list: portlet.options.settings.list || {}});
	}

	/**
	 * Functions for the note portlet
	 */
	/**
	 * Set up for editing a note
	 * CKEditor has CSP issues, so we need a popup
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} Selected
	 */
	note_edit(action, selected)
	{
		if(!selected && typeof action == 'string')
		{
			let id = action;
		}
		else
		{
			let id = selected[0].id;
		}

		// Aim to match the size
		let portlet_dom = jQuery('[id$=' + id + '][data-sizex]', this.portlet_container.getDOMNode());
		let width = portlet_dom.attr('data-sizex') * this.GRID;
		let height = portlet_dom.attr('data-sizey') * this.GRID;

		// CKEditor is impossible to use below a certain size
		// Add 35px for the toolbar, 35px for the buttons
		let window_width = Math.max(580, width + 20);
		let window_height = Math.max(350, height + 70);

		// Open popup, but add 70 to the height for the toolbar
		this.egw.open_link(this.egw.link('/index.php', {
			menuaction: 'home.home_note_portlet.edit',
			id: id,
			height: window_height - 70
		}), 'home_' + id, window_width + 'x' + window_height, 'home');
	}

	/**
	 * Favorites / nextmatch
	 */
	/**
	 * Toggle the nextmatch header shown / hidden
	 *
	 * @param {Event} event
	 * @param {et2_button} widget
	 */
	nextmatch_toggle_header(event, widget)
	{
		widget.set_class(widget.class == 'opened' ? 'closed' : 'opened');
		// We operate on the DOM here, nm should be unaware of our fiddling
		let nm = widget.getParent().getWidgetById('nm');
		if(!nm)
		{
			return;
		}

		// Hide header
		nm.div.toggleClass('header_hidden');
		nm.set_hide_header(nm.div.hasClass('header_hidden'));
		nm.resize();
	}
}

app.classes.home = HomeApp;

/// Base class code

/**
 * Base class for portlet specific javascript
 *
 * Should this maybe extend et2_portlet?  It would complicate instantiation.
 *
 * @type @exp;Class@call;extend
 */
export class HomePortlet
{
	protected portlet = null;

	init(portlet)
	{
		this.portlet = portlet;
	}

	destroy()
	{
		this.portlet = null;
	}

	/**
	 * Handle framework refresh messages to determine if the portlet needs to
	 * refresh too.
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
	 *
	 */
	observer(_msg, _app, _id, _type, _msg_type, _targetapp)
	{
		// Not interested
	}
}

/*
app.classes.home.home_link_portlet = app.classes.home.home_portlet.extend({
	init: function(portlet) {
		// call parent
		this._super.apply(this, arguments);

		// Check for tooltip
		if(this.portlet)
		{
			let content = jQuery('.tooltip', this.portlet.content);
			if(content.length && content.children().length)
			{
				//Check if the tooltip is already initialized
				this.portlet.content.tooltip({
					items: this.portlet.content,
					content: content.html(),
					tooltipClass: 'portlet_' + this.portlet.id,
					show: {effect: 'slideDown', delay:500},
					hide: {effect: 'slideUp', delay: 500},
					position: {my: "left top", at:"left bottom", collision: "flipfit"},
					open: jQuery.proxy(function(event, ui) {
						// Calendar specific formatting
						if(ui.tooltip.has('.calendar_calEventTooltip').length)
						{
							ui.tooltip.removeClass("ui-tooltip");
							ui.tooltip.addClass("calendar_uitooltip");
						}
					},this),
					close: function(event,ui) {
						ui.tooltip.hover(
							function() {
								jQuery(this).stop(true).fadeTo(100,1);
							},
							function() {
								jQuery(this).slideUp("400",function() {jQuery(this).remove();});
							}
						);
					}
				});
			}
		}
	},
	observer: function(_msg, _app, _id, _type)
	{
		if(this.portlet && this.portlet.settings)
		{
			let value = this.portlet.settings.entry || {};
			if(value.app && value.app == _app && value.id && value.id == _id)
			{
				// We don't just get the updated title, in case there's a custom
				// template with more fields
				app.home.refresh(this.portlet.id);
			}
		}
	}
});
app.classes.home.home_list_portlet = app.classes.home.home_portlet.extend({
	observer: function(_msg, _app, _id, _type)
	{
		if(this.portlet && this.portlet.getWidgetById('list'))
		{
			let list = this.portlet.getWidgetById('list').options.value;
			for(let i = 0; i < list.length; i++)
			{
				if(list[i].app == _app && list[i].id == _id)
				{
					app.home.refresh(this.portlet.id);
					return;
				}
			}
		}
	}
});
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
app.classes.home.home_favorite_portlet = app.classes.home.home_portlet.extend({
	init: function(portlet) {
		// call parent
		this._super.apply(this, arguments);

		// Somehow favorite got lost, or is not set
		if(portlet.options && portlet.options.settings && typeof portlet.options.settings !== 'undefined' &&
			!portlet.options.settings.favorite
		)
		{
			portlet.edit_settings();
		}
	},
	observer: function(_msg, _app, _id, _type, _msg_type, _targetapp)
	{
		if(this.portlet.class.indexOf(_app) == 0 || this.portlet.class == 'home_favorite_portlet')
		{
			this.portlet.getWidgetById('nm').refresh(_id,_type);
		}
	}
});


/**
 * An example illustrating extending the base code for a application specific code.
 * See also the calendar app, which needs custom handlers
 *
 * @type @exp;app@pro;classes@pro;home@pro;home_favorite_portlet@call;extend
 * Note we put it in home, but this code should go in addressbook/js/addressbook_favorite_portlet.js
 *
app.classes.home.addressbook_favorite_portlet = app.classes.home.home_favorite_portlet.extend({

observer: function(_msg, _app, _id, _type, _msg_type, _targetapp)
{
	// Just checking...
	debugger;
}
});
*/
