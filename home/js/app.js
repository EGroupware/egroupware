/**
 * EGroupware - Filemanager - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

"use strict";

/*egw:uses
        jquery.jquery;
        jquery.jquery-ui;
	/phpgwapi/js/jquery/shapeshift/core/jquery.shapeshift.js;
	/phpgwapi/js/jquery/gridster/jquery.gridster.js;
*/

/**
 * JS for home application
 * 
 * Home is a collection of little bits of content (portlets) from the other applications.
 *
 * 
 * Uses Gridster for the grid layout
 * @see https://github.com/dustmoo/gridster.js
 * @augments AppJS
 */
app.home = AppJS.extend(
{
	/**
	 * AppJS requires overwriting this with the actual application name
	 */
	appname: "home",

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
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

		this.et2 = et2.widgetContainer;
		this.portlet_container = this.et2.getWidgetById("portlets");

		// Add portlets
		var content = this.et2.getArrayMgr("content").getEntry("portlets");
		var modifications = this.et2.getArrayMgr("modifications").getEntry("portlets");
		for(var key in content)
		{
			//var attrs = jQuery.extend({id: key}, content[key], modifications[key]);
			var attrs = {id: key};
			var portlet = et2_createWidget('portlet',attrs, this.portlet_container);
		}
		this.et2.loadingFinished();

		// Set up sorting of portlets
		this._do_ordering();
	},

	/**
	 * Add a new portlet from the context menu
	 */
	add: function(action) {
		var attrs = {id: this._create_id(), class: action.id};
		var portlet = et2_createWidget('portlet',attrs, this.portlet_container);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		portlet._process_edit(et2_dialog.OK_BUTTON, {});

		// Set up sorting/grid of new portlet
		var $portlet_container = $j(this.portlet_container.getDOMNode());
		$portlet_container.data("gridster").add_widget(
			portlet.getDOMNode()
		);
	},

	/**
	 * User dropped something on home.  Add a new portlet
	 */
	add_from_drop: function(action,source,target_action) {
		var attrs = {id: this._create_id(), class: action.id};
		var portlet = et2_createWidget('portlet',attrs, this.portlet_container);
		portlet.loadingFinished();

		// Get actual attributes & settings, since they're not available client side yet
		var drop_data = [];
		for(var i = 0; i < source.length; i++)
		{
			if(source[i].id) drop_data.push(source[i].id);
		}
		portlet._process_edit(et2_dialog.OK_BUTTON, {dropped_data: drop_data});

		// Set up sorting/grid of new portlet
		var $portlet_container = $j(this.portlet_container.getDOMNode());
		$portlet_container.data("gridster").add_widget(
			portlet.getDOMNode()
		);
	console.log(this,arguments);
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
			/* Shapeshift 
			.shapeshift();
			*/
			/* Gridster */
			.gridster({
				widget_selector: 'div.et2_portlet',
				widget_base_dimensions: [45, 45],
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
						id: $w.attr("id"), 
						row: grid.row, 
						col: grid.col, 
						width: grid.width, 
						height: grid.height
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
						for (var key in changed)
						{
							if(!changed[key].id) continue;
							var widget = window.app.home.portlet_container.getWidgetById(changed[key].id);
							if(!widget || widget == window.app.home.portlet_container) continue;

							egw().json("home.home_ui.ajax_set_properties",[widget.id, widget.options.settings,{
									row: changed[key].row,
									col: changed[key].col
								}],
								null,
								widget, true, widget
							).sendRequest();
						}
					}
				}

			});

		// Bind resize to update gridster - this may happen _before_ the widget gets a
		// chance to update itself, so we can't use the widget
		$portlet_container
			.on("resizestop", function(event, ui) {
				$portlet_container.data("gridster").resize_widget(
					ui.element,
					Math.round(ui.size.width / 50),
					Math.round(ui.size.height / 50)
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
	}
});
