/**
 * EGroupware - Home - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package home
 * @author Nathan Gray
 * @copyright (c) 2013 Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

"use strict";

/*egw:uses
        jquery.jquery;
        jquery.jquery-ui;
	/phpgwapi/js/jquery/gridster/jquery.gridster.js;
*/

/**
 * JS for home application
 *
 * Home is a collection of little bits of content (portlets) from the other applications.
 *
 *
 * Uses Gridster for the grid layout
 * @see http://gridster.net
 * @augments AppJS
 */
app.classes.home = AppJS.extend(
{
	/**
	 * AppJS requires overwriting this with the actual application name
	 */
	appname: "home",

	/**
	 * Grid resolution.  Must match et2_portlet GRID
	 */
	GRID: 100,

	/**
	 * Default size for new portlets
	 */
	DEFAULT: {
		WIDTH:	2,
		HEIGHT:	1
	},

	/**
	 * Constructor
	 *
	 * @memberOf app.home
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 * @memberOf app.home
	 */
	destroy: function()
	{
		delete this.et2;
		delete this.portlet_container;

		// call parent
		this._super.apply(this, arguments);

		// Make sure all other sub-etemplates in portlets are done
		var others = etemplate2.getByApplication(this.appname);
		for(var i = 0; i < others.length; i++)
		{
			others[i].clear();
		}
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} et2 Newly ready object
	 * @param {string} Template name
	 */
	et2_ready: function(et2, name)
	{
		// Top level
		if(name == 'home.index')
		{
			// call parent
			this._super.apply(this, arguments);

			this.et2.set_id('home.index');
			this.et2.set_actions(this.et2.getArrayMgr('modifications').getEntry('home.index')['actions']);

			this.portlet_container = this.et2.getWidgetById("portlets");

			// Set up sorting of portlets
			this._do_ordering();

			// Accept drops of favorites, which aren't part of action system
			$j(this.et2.getDOMNode().parentNode).droppable({
				hoverClass: 'drop-hover',
				accept: function(draggable) {
					// Check for direct support for that application
					if(draggable[0].dataset && draggable[0].dataset.appname)
					{
						return egw_getActionManager('home',false,1).getActionById('drop_'+draggable[0].dataset.appname +'_favorite_portlet') != null;
					}
					return false;
				},
				drop: function(event, ui) {
					// Favorite dropped on home - fake an action and divert to normal handler
					var action = {
						data: {
							class: 'add_home_favorite_portlet'
						}
					}

					// Check for direct support for that application
					if(ui.helper.context.dataset && ui.helper.context.dataset.appname)
					{
						var action = egw_getActionManager('home',false,1).getActionById('drop_'+ui.helper.context.dataset.appname +'_favorite_portlet') || {}
					}
					action.ui = ui;
					app.home.add_from_drop(action, [{data: ui.helper.context.dataset}])
				}
			})
		}
		else if (et2.uniqueId)
		{
			// Handle bad timing - a sub-template was finished first
			if(!this.portlet_container)
			{
				window.setTimeout(jQuery.proxy(this, function() {this.et2_ready(et2, name);}),200);
				return;
			}

			var portlet = this.portlet_container.getWidgetById(et2.uniqueId);
			// Check for existing etemplate, this one loaded over it
			// NOTE: Moving them around like this can cause problems with event handlers
			var existing = etemplate2.getById(et2.uniqueId);
			if(portlet && existing && existing.etemplate_exec_id != et2.etemplate_exec_id)
			{
				for(var i = 0; i < portlet._children.length; i++)
				{
					portlet._children[i]._inst.clear();
				}
				portlet._children = [];
			}
			// It's in the right place for original load, but move it into portlet
			var misplaced = $j(etemplate2.getById('home-index').DOMContainer).siblings('#'+et2.DOMContainer.id);
			if(portlet)
			{
				portlet.content = $j(et2.DOMContainer).appendTo(portlet.content);
				portlet.addChild(et2.widgetContainer);
				et2.resize();
			}
			if(portlet && misplaced.length)
			{
				et2.DOMContainer.id = et2.uniqueId;
			}
		}
	},

	/**
	 * Add a new portlet from the context menu
	 */
	add: function(action, source) {
		// Basic portlet attributes
		var attrs = {
			id: this._create_id(),
			class: action.data.class,
			width: this.DEFAULT.WIDTH,
			height: this.DEFAULT.HEIGHT
		};

		// Try to put it about where the menu was opened
		if(action.menu_context)
		{
			var $portlet_container = $j(this.portlet_container.getDOMNode());
			attrs.row = Math.max(1,Math.round((action.menu_context.posy - $portlet_container.offset().top )/ this.GRID)+1);
			attrs.col = Math.max(1,Math.round((action.menu_context.posx - $portlet_container.offset().left) / this.GRID)+1);
		}

		var portlet = et2_createWidget('portlet',attrs, this.portlet_container);
		// Override content ID so etemplate loads
		portlet.content.attr('id', attrs.id);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		portlet._process_edit(et2_dialog.OK_BUTTON, attrs);

		// Set up sorting/grid of new portlet
		var $portlet_container = $j(this.portlet_container.getDOMNode());
		$portlet_container.data("gridster").add_widget(
			portlet.getDOMNode(),
			this.DEFAULT.WIDTH, this.DEFAULT.HEIGHT,
			attrs.col, attrs.row
		);
	},

	/**
	 * User dropped something on home.  Add a new portlet
	 */
	add_from_drop: function(action,source) {

		// Actions got confused drop vs popup
		if(source[0].id == 'portlets')
		{
			return this.add(action);
		}

		var $portlet_container = $j(this.portlet_container.getDOMNode());

		// Basic portlet attributes
		var attrs = {
			id: this._create_id(),
			class: action.data.class || action.id.substr(5),
			width: this.DEFAULT.WIDTH,
			height: this.DEFAULT.HEIGHT
		};

		// Try to find where the drop was
		if(action != null && action.ui && action.ui.position)
		{
			attrs.row = Math.max(1,Math.round((action.ui.position.top - $portlet_container.offset().top )/ this.GRID));
			attrs.col = Math.max(1,Math.round((action.ui.position.left - $portlet_container.offset().left) / this.GRID));
		}

		var portlet = et2_createWidget('portlet',attrs, this.portlet_container);
		// Override content ID so etemplate loads
		portlet.content.attr('id', attrs.id);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		var drop_data = [];
		for(var i = 0; i < source.length; i++)
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
		portlet._process_edit(et2_dialog.OK_BUTTON, jQuery.extend({dropped_data: drop_data},attrs));

		// Set up sorting/grid of new portlet
		$portlet_container.data("gridster").add_widget(
			portlet.getDOMNode(),
			this.DEFAULT.WIDTH, this.DEFAULT.HEIGHT,
			attrs.col, attrs.row
		);
	},

	/**
	 * Allow a refresh from anywhere by triggering an update with no changes
	 * 
	 * @param {string} id
	 */
	refresh: function($id) {
		var p = this.portlet_container.getWidgetById($id);
		if(p)
		{
			p._process_edit(et2_dialog.OK_BUTTON, '~reload~');
		}
	},

	/**
	 * For link_portlet - opens the configured record when the user
	 * double-clicks or chooses view from the context menu
	 */
	open_link: function(action) {

		// Get widget
		var widget = null;
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
			egw().log("warning", "Could not find widget");
			return;
		}
		egw().open(widget.options.settings.entry, "", 'edit');
	},

	/**
	 * Set up the drag / drop / re-order of portlets
	 */
	_do_ordering: function() {
		var $portlet_container = $j(this.portlet_container.getDOMNode());
		$portlet_container
			.addClass("home ui-helper-clearfix")
			.disableSelection()
			/* Gridster */
			.gridster({
				widget_selector: 'div.et2_portlet',
				// Dimensions + margins = grid spacing
				widget_base_dimensions: [this.GRID-5, this.GRID-5],
				widget_margins: [5,5],
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
				serialize_params: function($w, grid) {
					return {
						id: $w.attr('id').replace(app.home.portlet_container.getInstanceManager().uniqueId+'_',''),
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
					stop: function(event,ui) {
						// Update widget(s)
						var changed = this.serialize_changed();

						// Reset changed, or they keep accumulating
						this.$changed = $j([]);
						
						for (var key in changed)
						{
							if(!changed[key].id) continue;
							// Changed ID is the ID
							var widget = window.app.home.portlet_container.getWidgetById(changed[key].id);
							if(!widget || widget == window.app.home.portlet_container) continue;

							egw().jsonq("home.home_ui.ajax_set_properties",[changed[key].id, widget.options.settings,{
									row: changed[key].row,
									col: changed[key].col
								}],
								null,
								widget, true, widget
							);
						}
					}
				}

			});

		// Bind window resize to re-layout gridster
		$j(window).one("resize."+this.et2._inst.uniqueId, function() {
			// Note this doesn't change the positions, just makes them invalid
			$portlet_container.data('gridster').recalculate_faux_grid();
		});
		// Bind resize to update gridster - this may happen _before_ the widget gets a
		// chance to update itself, so we can't use the widget
		$portlet_container
			.on("resizestop", function(event, ui) {
				$portlet_container.data("gridster").resize_widget(
					ui.element,
					Math.round(ui.size.width / app.home.GRID),
					Math.round(ui.size.height / app.home.GRID)
				);
			});
	},

	/**
	 * Create an ID that should be unique, at least amoung a single user's portlets
	 */
	_create_id: function() {
		var id = '';
		do
		{
			id = Math.floor((1 + Math.random()) * 0x10000)
			     .toString(16)
			     .substring(1);
		}
		while(this.portlet_container.getWidgetById(id));
		return id;
	},

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
	add_link: function(action, source, target_action) {
		// Actions got confused drop vs popup
		if(source[0].id == 'portlets')
		{
			return this.add_link(action);
		}

		// Get widget
		var widget = null;
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
			var splitted = 'home.edit'.split('.');
			var path = app.home.portlet_container.getRoot()._inst.template_base_url + splitted.shift() + "/templates/default/" +
				splitted.join('.')+ ".xet";
			var dialog = et2_createWidget("dialog",{
				callback: function(button_id, value) {
					if(button_id == et2_dialog.CANCEL_BUTTON) return;
					var new_list = widget.options.settings.list || [];
					for(var i = 0; i < new_list.length; i++)
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
					widget._process_edit(button_id,{list: new_list});
					// Update client side
					widget.getWidgetById('list').set_value(new_list);
				},
				buttons: et2_dialog.BUTTONS_OK_CANCEL,
				title: app.home.egw.lang('add'),
				template:path,
				value: { content: [{label: app.home.egw.lang('add'),type: 'link-entry',name: 'add',size:''}]}
			});
		}
		else
		{
			// Drag'n'dropped something on the list - just send action IDs
			var new_list = widget.options.settings.list || [];
			var changed = false;
			for(var i = 0; i < new_list.length; i++)
			{
				// Avoid duplicates
				for(var j = 0; j < source.length; j++)
				{
					if(!source[j].id || new_list[i].app+"::"+new_list[i].id == source[j].id)
					{
						// Duplicate - skip it
						source.splice(j,1);
					}
				}
			}
			for(var i = 0; i < source.length; i++)
			{
				var explode = source[i].id.split('::');
				new_list.push({app: explode[0],id: explode[1], link_id: explode.join(':')});
				changed = true;
			}
			if(changed)
			{
				widget._process_edit(et2_dialog.OK_BUTTON,{
					list: new_list || {}
				});
			}
			// Filemanager support - links need app = 'file' and type set
			for(var i = 0; i < new_list.length; i++)
			{
				if(new_list[i]['app'] == 'filemanager')
				{
					new_list[i]['app'] = 'file';
					new_list[i]['path'] = new_list[i]['title'] = new_list[i]['icon'] = new_list[i]['id'];
				}
			}

			widget.getWidgetById('list').set_value(new_list);

		}
	},

	/**
	 * Remove a link from the list
	 */
	link_change: function(list, link_id, row) {
		// Quick response client side
		row.slideUp(row.remove);

		// Actual removal
		var portlet = list._parent._parent;
		portlet.options.settings.list.splice(row.index(), 1);
		portlet._process_edit(et2_dialog.OK_BUTTON,{list: portlet.options.settings.list || {}});
	},

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
	note_edit: function(action, selected) {
		if(!selected && typeof action == 'string')
		{
			var id = action;
		}
		else
		{
			var id = selected[0].id;
		}

		// Aim to match the size
		var portlet_dom = $j('[id$='+id+'][data-sizex]',this.portlet_container.getDOMNode);
		var width = portlet_dom.attr('data-sizex') * this.GRID;
		var height = portlet_dom.attr('data-sizey') * this.GRID;
		
		// CKEditor is impossible to use below a certain size
		// Add 35px for the toolbar, 35px for the buttons
		var window_width = Math.max(580, width+20);
		var window_height = Math.max(350, height+70);
		
		// Open popup, but add 70 to the height for the toolbar
		egw.open_link(egw.link('/index.php',{
			menuaction: 'home.home_note_portlet.edit',
			id: id,
			height: window_height - 70
		}),'home_'+id, window_width+'x'+window_height,'home');
	},

	/**
	 * Favorites / nextmatch
	 */
	/**
	 * Toggle the nextmatch header shown / hidden
	 *
	 * @param {Event} event
	 * @param {et2_button} widget
	 */
	nextmatch_toggle_header: function(event, widget) {
		widget.set_image(widget.options.image == 'arrow_down' ? 'arrow_left' : 'arrow_down');
		// We operate on the DOM here, nm should be unaware of our fiddling
		var nm = widget.getParent().getWidgetById('nm');
		if(!nm) return;
		var header = nm.header;
		var header_height = header.div.innerHeight();

		// Hide header
		nm.div.toggleClass('header_hidden');

		header_height -= header.div.height();

		// Grow row space - I have no idea why it needs to be 25 pixels instead of header_height
		var scroll_height = $j('.egwGridView_scrollarea',nm.getDOMNode()).height();
		$j('.egwGridView_scrollarea',nm.getDOMNode()).height(scroll_height + (header_height > 0 ? 25 : -25));
		nm.resize();
	}
});
