/**
 * EGroupware eTemplate2 - JS Nextmatch object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses

	// Include the action system
	egw_action.egw_action;
	egw_action.egw_action_popup;
	egw_action.egw_action_dragdrop;
	egw_action.egw_menu_dhtmlx;

	// Include some core classes
	et2_core_widget;
	et2_core_interfaces;
	et2_core_DOMWidget;

	// Include all widgets the nextmatch extension will create
	et2_widget_template;
	et2_widget_grid;
	et2_widget_selectbox;
	et2_widget_selectAccount;
	et2_widget_taglist;
	et2_extension_customfields;

	// Include all nextmatch subclasses
	et2_extension_nextmatch_rowProvider;
	et2_extension_nextmatch_controller;
	et2_widget_dynheight;

	// Include the grid classes
	et2_dataview;

*/

import {et2_csvSplit, et2_no_init} from "./et2_core_common";
import {
	et2_IInput,
	et2_implements_registry,
	et2_IPrint,
	et2_IResizeable,
	implements_methods
} from "./et2_core_interfaces";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_createWidget, et2_register_widget, et2_widget, WidgetConfig} from "./et2_core_widget";
import {et2_DOMWidget} from "./et2_core_DOMWidget";
import {et2_baseWidget} from "./et2_core_baseWidget";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {et2_selectbox} from "./et2_widget_selectbox";
import {et2_nextmatch_rowProvider} from "./et2_extension_nextmatch_rowProvider";
import {et2_nextmatch_controller} from "./et2_extension_nextmatch_controller";
import {et2_dataview} from "./et2_dataview";
import {et2_dataview_column} from "./et2_dataview_model_columns";
import {et2_customfields_list} from "./et2_extension_customfields";
import {et2_link_to} from "./et2_widget_link";
import {et2_grid} from "./et2_widget_grid";
import {et2_dataview_grid} from "./et2_dataview_view_grid";
import {et2_dynheight} from "./et2_widget_dynheight";
import {et2_arrayMgr} from "./et2_core_arrayMgr";
import {et2_button} from "./et2_widget_button";
import {et2_template} from "./et2_widget_template";
import {egw} from "../jsapi/egw_global";
import {et2_compileLegacyJS} from "./et2_core_legacyJSFunctions";
import {egwIsMobile} from "../egw_action/egw_action_common.js";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {Et2Select} from "./Et2Select/Et2Select";
import {loadWebComponent} from "./Et2Widget/Et2Widget";
import {Et2AccountFilterHeader} from "./Nextmatch/Headers/AccountFilterHeader";
import {Et2SelectCategory} from "./Et2Select/Et2SelectCategory";
import {Et2Searchbox} from "./Et2Textbox/Et2Searchbox";

//import {et2_selectAccount} from "./et2_widget_SelectAccount";
let keep_import : Et2AccountFilterHeader

/**
 * Interface all special nextmatch header elements have to implement.
 */
export interface et2_INextmatchHeader
{

	/**
	 * The 'setNextmatch' function is called by the parent nextmatch widget
	 * and tells the nextmatch header widgets which widget they should direct
	 * their 'sort', 'search' or 'filter' calls to.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch(nextmatch : et2_nextmatch) : void
}

export const et2_INextmatchHeader = "et2_INextmatchHeader";
et2_implements_registry.et2_INextmatchHeader = function(obj : et2_widget)
{
	return implements_methods(obj, ["setNextmatch"]);
}

export interface et2_INextmatchSortable
{
	setSortmode(_sort_mode) : void
}

export const et2_INextmatchSortable = "et2_INextmatchSortable";
et2_implements_registry.et2_INextmatchSortable = function(obj : et2_widget)
{
	return implements_methods(obj, ["setSortmode"]);
}

// For holding settings while whe print
interface PrintSettings
{
	old_height : number,
	row_selector : string,
	orientation_style : HTMLStyleElement
}

interface ActiveFilters
{
	search? : string,
	filter? : any,
	filter2? : any,
	col_filter : { [key : string] : any },
	selectcols? : string[],
	searchletter? : string,
	selected? : string[]
}

/**
 * Class which implements the "nextmatch" XET-Tag
 *
 * NM header is build like this in DOM
 *
 * +- nextmatch_header -----+------------+----------+--------+---------+--------------+-----------+-------+
 * + header_left | search.. | header_row | category | filter | filter2 | header_right | favorites | count |
 * +-------------+----------+------------+----------+--------+---------+--------------+-----------+-------+
 *
 * everything left incl. standard filters is floated left:
 * +- nextmatch_header -----+------------+----------+--------+---------+
 * + header_left | search.. | header_row | category | filter | filter2 |
 * +-------------+----------+------------+----------+--------+---------+
 * everything from header_right on is floated right:
 *                                          +--------------+-----------+-------+
 *                                          | header_right | favorites | count |
 *                                          +--------------+-----------+-------+
 * @augments et2_DOMWidget
 */
export class et2_nextmatch extends et2_DOMWidget implements et2_IResizeable, et2_IInput, et2_IPrint
{
	static readonly _attributes = {
		// These normally set in settings, but broken out into attributes to allow run-time changes
		"template": {
			"name": "Template",
			"type": "string",
			"description": "The id of the template which contains the grid layout."
		},
		"hide_header": {
			"name": "Hide header",
			"type": "boolean",
			"description": "Hide the header",
			"default": false
		},
		"header_left": {
			"name": "Left custom template",
			"type": "string",
			"description": "Customise the nextmatch - left side.  Provided template becomes a child of nextmatch, and any input widgets are automatically bound to refresh the nextmatch on change.  Any inputs with an onChange attribute can trigger the nextmatch to refresh by returning true.",
			"default": ""
		},
		"header_right": {
			"name": "Right custom template",
			"type": "string",
			"description": "Customise the nextmatch - right side.  Provided template becomes a child of nextmatch, and any input widgets are automatically bound to refresh the nextmatch on change.  Any inputs with an onChange attribute can trigger the nextmatch to refresh by returning true.",
			"default": ""
		},
		"header_row": {
			"name": "Inline custom template",
			"type": "string",
			"description": "Customise the nextmatch - inline, after row count.  Provided template becomes a child of nextmatch, and any input widgets are automatically bound to refresh the nextmatch on change.  Any inputs with an onChange attribute can trigger the nextmatch to refresh by returning true.",
			"default": ""
		},
		"no_filter": {
			"name": "No filter",
			"type": "boolean",
			"description": "Hide the first filter",
			"default": et2_no_init
		},
		"no_filter2": {
			"name": "No filter2",
			"type": "boolean",
			"description": "Hide the second filter",
			"default": et2_no_init
		},
		"disable_autorefresh": {
			"name": "Disable autorefresh",
			"type": "boolean",
			"description": "Disable the ability to autorefresh the nextmatch on a regular interval.  ",
			"default": false
		},
		"disable_selection_advance": {
			"name": "Disable selection advance",
			"type": "boolean",
			"description": "If a refresh deletes the currently selected row, we normally advance the selection to the next row.  Set to true to stop this.",
			"default": false
		},
		"view": {
			"name": "View",
			"type": "string",
			"description": "Display entries as either 'row' or 'tile'.  A matching template must also be set after changing this.",
			"default": et2_no_init
		},
		"onselect": {
			"name": "onselect",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which gets executed when rows are selected.  Can also be a app.appname.func(selected) style method"
		},
		"onfiledrop": {
			"name": "onFileDrop",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code that gets executed when a _file_ is dropped on a row.  Other drop interactions are handled by the action system.  Return false to prevent the default link action."
		},
		"onadd": {
			"name": "onAdd",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code that gets executed when a new entry is added via refresh().  Allows apps to override the default handling.  Return false to cancel the add."
		},
		"settings": {
			"name": "Settings",
			"type": "any",
			"description": "The nextmatch settings",
			"default": {}
		}
	};

	// Currently active filters
	activeFilters : ActiveFilters;

	/**
	 * Update types
	 * @see et2_nextmatch.refresh() for more information
	 */
	public static readonly ADD = 'add';
	public static readonly UPDATE_IN_PLACE = 'update-in-place';
	public static readonly UPDATE = 'update';
	public static readonly EDIT = 'edit';
	public static readonly DELETE = 'delete';

	// DOM / jQuery stuff
	private div : JQuery;
	private innerDiv : JQuery;
	private dynheight : any;
	private blank : JQuery;

	// Popup to select columns
	private selectPopup : any;

	public static readonly legacyOptions = ["template", "hide_header", "header_left", "header_right"];

	private template : any;
	columns : { visible : boolean, widget : et2_widget }[];
	private sortedColumnsList : string[];

	// If we need the nextmatch to have a value, keep it here.
	// Normally this is used in actions, and is the action and selected rows.
	private value : any;

	// Big old bag of settings
	private settings : any;

	// Current view, either row or tile.  We store it here as controllers are
	// recreated when the template changes.
	view : string;

	// Sub-objects used for actual work
	private readonly header : et2_nextmatch_header_bar;
	dataview : any;
	controller : any;
	private rowProvider : any;


	// Flag for an update is currently being done, to avoid a loop
	private update_in_progress : boolean;

	// Window timer for automatically refreshing
	private _autorefresh_timer : number;

	// Nextmatch can't render while hidden, we store refresh requests for later
	private _queued_refreshes : null | { type : string, ids : string[] }[] = [];

	// When printing, we change the layout around.  Keep some values so it can be restored after
	private print : PrintSettings = {
		old_height: 0,
		row_selector: '',
		orientation_style: null
	};
	/**
	 * When loading the row template, we have to wait for the template before we try to process it.
	 * During the legacy load process, we need to return this from doLoadingFinished() so it can be waited on so we
	 * have to store it.
	 */
	private template_promise : Promise<void>;

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_nextmatch._attributes, _child || {}));

		this.activeFilters = {col_filter: {}};
		this.columns = [];
		// keeps sorted columns
		this.sortedColumnsList = [];

		// Directly set current col_filters from settings
		jQuery.extend(this.activeFilters.col_filter, this.options.settings.col_filter);

		/*
		Process selected custom fields here, so that the settings are correctly
		set before the row template is parsed
		*/
		const prefs = this._getPreferences();
		const cfs = {};
		for(let i = 0; i < prefs.visible.length; i++)
		{
			if(prefs.visible[i].indexOf(et2_nextmatch_customfields.PREFIX) == 0)
			{
				cfs[prefs.visible[i].substr(1)] = !prefs.negated;
			}
		}
		const global_data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~');
		if(typeof global_data == 'object' && global_data != null)
		{
			global_data.fields = cfs;
		}

		this.div = jQuery(document.createElement("div"))
			.addClass("et2_nextmatch");


		this.header = <et2_nextmatch_header_bar>et2_createWidget("nextmatch_header_bar", {}, this);
		this.innerDiv = jQuery(document.createElement("div"))
			.appendTo(this.div);

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = this._getDynheight();

		// Create the outer grid container
		this.dataview = new et2_dataview(this.innerDiv, this.egw());

		// Blank placeholder
		this.blank = jQuery(document.createElement("div"))
			.appendTo(this.dataview.table);

		// We cannot create the grid controller now, as this depends on the grid
		// instance, which can first be created once we have the columns
		this.controller = null;
		this.rowProvider = null;

	}

	/**
	 * Destroys all
	 */
	destroy()
	{
		// Stop auto-refresh
		if(this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			this._autorefresh_timer = null;
		}
		// Unbind handler used for toggling autorefresh
		jQuery(this.getInstanceManager().DOMContainer.parentNode).off('show.et2_nextmatch');
		jQuery(this.getInstanceManager().DOMContainer.parentNode).off('hide.et2_nextmatch');

		// Free the grid components
		this.dataview.destroy();
		if(this.rowProvider)
		{
			this.rowProvider.destroy();
		}
		if(this.controller)
		{
			this.controller.destroy();
		}
		this.dynheight.destroy();

		super.destroy();
	}

	getController()
	{
		return this.controller;
	}

	/**
	 * Loads the nextmatch settings
	 *
	 * @param {object} _attrs
	 */
	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		if(this.id)
		{
			const entry = this.getArrayMgr("content").data;
			_attrs["settings"] = {};

			if(entry)
			{
				_attrs["settings"] = entry;

				// Make sure there's an action var parameter
				if(_attrs["settings"]["actions"] && !_attrs.settings["action_var"])
				{
					_attrs.settings.action_var = "action";
				}

				// Merge settings mess into attributes
				for(let attr in this.attributes)
				{
					if(_attrs.settings[attr])
					{
						_attrs[attr] = _attrs.settings[attr];
						delete _attrs.settings[attr];
					}
				}
			}
		}
	}

	doLoadingFinished()
	{
		super.doLoadingFinished();

		if(!this.dynheight)
		{
			this.dynheight = this._getDynheight();
		}

		// Register handler for dropped files, if possible
		if(this.options.settings.row_id)
		{
			// Appname should be first part of the template name
			const split = this.options.template.split('.');
			const appname = split[0];

			// Check link registry
			if(this.egw().link_get_registry(appname))
			{
				const self = this;
				// Register a handler
				// @ts-ignore
				jQuery(this.div)
					.on('dragenter', '.egwGridView_grid tr', function(e)
					{
						// Figure out _which_ row
						const row = self.controller.getRowByNode(this);

						if(!row || !row.uid)
						{
							return false;
						}
						e.stopPropagation();
						e.preventDefault();

						// Indicate acceptance
						if(row.controller && row.controller._selectionMgr)
						{
							row.controller._selectionMgr.setFocused(row.uid, true);
						}
						return false;
					})
					.on('dragexit', '.egwGridView_grid tr', function()
					{
						self.controller._selectionMgr.setFocused();
					})
					.on('dragover', '.egwGridView_grid tr', false).attr("dropzone", "copy")

					.on('drop', '.egwGridView_grid tr', function(e)
					{
						self.handle_drop(e, this);
						return false;
					});
			}
		}
		// stop invalidation in no visible tabs
		jQuery(this.getInstanceManager().DOMContainer.parentNode).on('hide.et2_nextmatch', jQuery.proxy(function()
		{
			if(this.controller && this.controller._grid)
			{
				this.controller._grid.doInvalidate = false;
			}
		}, this));
		jQuery(this.getInstanceManager().DOMContainer.parentNode).on('show.et2_nextmatch', jQuery.proxy(function()
		{
			if(this.controller && this.controller._grid)
			{
				this.controller._grid.doInvalidate = true;
			}
		}, this));
		return this.template_promise ? this.template_promise : true;
	}

	/**
	 * Implements the et2_IResizeable interface - lets the dynheight manager
	 * update the width and height and then update the dataview container.
	 */
	resize()
	{
		if(this.dynheight)
		{
			this.dynheight.update(function(_w, _h)
			{
				this.dataview.resize(_w, _h);
			}, this);
		}
	}

	/**
	 * Sorts the nextmatch widget by the given ID.
	 *
	 * @param {string} _id is the id of the data entry which should be sorted.
	 * @param {boolean} _asc if true, the elements are sorted ascending, otherwise
	 * 	descending. If not set, the sort direction will be determined
	 * 	automatically.
	 * @param {boolean} _update true/undefined: call applyFilters, false: only set sort
	 */
	sortBy(_id, _asc, _update? : boolean)
	{
		if(typeof _update == "undefined")
		{
			_update = true;
		}

		// Create the "sort" entry in the active filters if it did not exist
		// yet.
		if(typeof this.activeFilters["sort"] == "undefined")
		{
			this.activeFilters["sort"] = {
				"id": null,
				"asc": true
			};
		}

		// Determine the sort direction automatically if it is not set
		if(typeof _asc == "undefined")
		{
			_asc = true;
			if(this.activeFilters["sort"].id == _id)
			{
				_asc = !this.activeFilters["sort"].asc;
			}
		}

		// Set the sortmode display
		this.iterateOver(function(_widget)
		{
			_widget.setSortmode((_widget.id == _id) ? (_asc ? "asc" : "desc") : "none");
		}, this, et2_INextmatchSortable);

		if(_update)
		{
			this.applyFilters({sort: {id: _id, asc: _asc}});
		}
		else
		{
			// Update the entry in the activeFilters object
			this.activeFilters["sort"] = {
				"id": _id,
				"asc": _asc
			};
		}
	}

	/**
	 * Removes the sort entry from the active filters object and thus returns to
	 * the natural sort order.
	 */
	resetSort()
	{
		// Check whether the nextmatch widget is currently sorted
		if(typeof this.activeFilters["sort"] != "undefined")
		{
			// Reset the sort mode
			this.iterateOver(function(_widget)
			{
				_widget.setSortmode("none");
			}, this, et2_INextmatchSortable);

			// Delete the "sort" filter entry
			this.applyFilters({sort: undefined});
		}
	}

	/**
	 * Apply current or modified filters on NM widget (updating rows accordingly)
	 *
	 * @param _set filter(s) to set eg. { filter: '' } to reset filter in NM header
	 */
	applyFilters(_set? : object | any)
	{
		let changed = false;
		let keep_selection = false;

		// Avoid loops cause by change events
		if(this.update_in_progress || !this.controller) return;
		this.update_in_progress = true;

		// Cleared explicitly
		if(typeof _set != 'undefined' && jQuery.isEmptyObject(_set))
		{
			changed = true;
			this.activeFilters = {col_filter: {}};
		}
		if(typeof this.activeFilters == "undefined")
		{
			this.activeFilters = {col_filter: {}};
		}
		if(typeof this.activeFilters.col_filter == "undefined")
		{
			this.activeFilters.col_filter = {};
		}

		if(typeof _set == 'object')
		{
			for(let s in _set)
			{
				if(s == 'col_filter')
				{
					// allow apps setState() to reset all col_filter by using undefined or null for it
					// they can not pass {} for _set / state.state, if they need to set something
					if(_set.col_filter === undefined || _set.col_filter === null)
					{
						this.activeFilters.col_filter = {};
						changed = true;
					}
					else
					{
						for(let c in _set.col_filter)
						{
							if(this.activeFilters.col_filter[c] !== _set.col_filter[c])
							{
								if(_set.col_filter[c])
								{
									this.activeFilters.col_filter[c] = _set.col_filter[c];
								}
								else
								{
									delete this.activeFilters.col_filter[c];
								}
								changed = true;
							}
						}
					}
				}
				else if(s === 'selected')
				{
					changed = true;
					keep_selection = true;
					this.controller._selectionMgr.resetSelection();
					this.controller._objectManager.clear();
					for(let i in _set.selected)
					{
						this.controller._selectionMgr.setSelected(_set.selected[i].indexOf('::') > 0 ? _set.selected[i] : this.controller.dataStorePrefix + '::' + _set.selected[i], true);
					}
					delete _set.selected;
				}
				else if(this.activeFilters[s] !== _set[s])
				{
					this.activeFilters[s] = _set[s];
					changed = true;
				}
			}
		}

		this.egw().debug("info", "Changing nextmatch filters to ", this.activeFilters);

		// Keep the selection after applying filters, but only if unchanged
		if(!changed || keep_selection)
		{
			this.controller.keepSelection();
		}
		else
		{
			// Do not keep selection
			this.controller._selectionMgr.resetSelection();
			this.controller._objectManager.clear();
			this.controller.keepSelection();
		}

		// Update the filters in the grid controller
		this.controller.setFilters(this.activeFilters);

		// Update the header
		this.header.setFilters(this.activeFilters);

		// Update any column filters
		this.iterateOver(function(column)
		{
			// Skip favorites - it implements et2_INextmatchHeader, but we don't want it in the filter
			if(typeof column.id != "undefined" && column.id.indexOf('favorite') == 0) return;

			if(typeof column.set_value != "undefined" && column.id)
			{
				column.set_value(typeof this[column.id] == "undefined" || this[column.id] == null ? "" : this[column.id]);
			}
			if(column.id && typeof column.get_value == "function")
			{
				this[column.id] = column.get_value();
			}
		}, this.activeFilters.col_filter, et2_INextmatchHeader);

		// Trigger an update
		this.controller.update(true);

		if(changed)
		{
			// Highlight matching favorite in sidebox
			if(this.getInstanceManager().app)
			{
				const appname = this.getInstanceManager().app;
				if(app[appname] && app[appname].highlight_favorite)
				{
					app[appname].highlight_favorite();
				}
			}
		}

		this.update_in_progress = false;
	}

	/**
	 * Refresh given rows for specified change
	 *
	 * Change type parameters allows for quicker refresh then complete server side reload:
	 * - update: request modified data from given rows.  May be moved.
	 * - update-in-place: update row, but do NOT move it, or refresh if uid does not exist
	 * - edit: rows changed, but sorting may be affected.  Full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: put the new row in at the top, unless app says otherwise
	 *
	 * What actually happens also depends on a general preference "lazy-update":
	 *	default/lazy:
	 *  - add always on top
	 *	- updates on top, if sorted by last modified, otherwise update-in-place
	 *	- update-in-place is always in place!
	 *
	 *	exact:
	 *	- add and update on top if sorted by last modified, otherwise full refresh
	 *	- update-in-place is always in place!
	 *
	 * Nextmatch checks the application callback nm_refresh_index, which has a default implementation
	 * in egw_app.nm_refresh_index().
	 *
	 * @param {string[]|string} _row_ids rows to refresh
	 * @param {?string} _type "update-in-place", "update", "edit", "delete" or "add"
	 *
	 * @see jsapi.egw_refresh()
	 * @see egw_app.nm_refresh_index()
	 * @fires refresh from the widget itself
	 */
	refresh(_row_ids, _type)
	{
		// Framework trying to refresh, but nextmatch not fully initialized
		if(this.controller === null || !this.div)
		{
			return;
		}

		// Make sure we're dealing with arrays
		if(typeof _row_ids == 'string' || typeof _row_ids == 'number') _row_ids = [_row_ids];

		// Make some changes in what we're doing based on preference
		let update_pref = egw.preference("lazy-update") || 'lazy';
		if(_type == et2_nextmatch.UPDATE && !this.is_sorted_by_modified())
		{
			_type = update_pref == "lazy" ? et2_nextmatch.UPDATE_IN_PLACE : et2_nextmatch.EDIT;
		}
		else if(update_pref == "exact" && _type == et2_nextmatch.ADD && !this.is_sorted_by_modified())
		{
			_type = et2_nextmatch.EDIT;
		}
		if(_type == et2_nextmatch.ADD && !(update_pref == "lazy" || update_pref == "exact" && this.is_sorted_by_modified()))
		{
			_type = et2_nextmatch.EDIT;
		}

		if(typeof _type == 'undefined') _type = et2_nextmatch.EDIT;

		if(!this.div.is(':visible'))	// run refresh, once we become visible again
		{
			return this._queue_refresh(_row_ids, _type);
		}

		if(typeof _row_ids == "undefined" || _row_ids === null)
		{
			this.applyFilters();

			// Trigger an event so app code can act on it
			jQuery(this).triggerHandler("refresh", [this]);

			return;
		}

		// Clean IDs in case they're UIDs with app prefixed
		_row_ids = _row_ids.map(function(id)
		{
			if(id.toString().indexOf(this.controller.dataStorePrefix) == -1)
			{
				return id;
			}
			let parts = id.split("::");
			parts.shift();
			return parts.join("::");
		}.bind(this));
		if(_type == et2_nextmatch.DELETE)
		{
			// Record current & next index
			var uid = _row_ids[0].toString().indexOf(this.controller.dataStorePrefix) == 0 ? _row_ids[0] : this.controller.dataStorePrefix + "::" + _row_ids[0];
			const entry = this.controller._selectionMgr._getRegisteredRowsEntry(uid);
			if(entry && entry.idx !== null)
			{
				let next = (entry.ao ? entry.ao.getNext(_row_ids.length) : null);
				if(next == null || !next.id || next.id == uid)
				{
					// No next, select previous
					next = (entry.ao ? entry.ao.getPrevious(1) : null);
				}

				// Stop automatic updating
				this.dataview.grid.doInvalidate = false;
				for(var i = 0; i < _row_ids.length; i++)
				{
					uid = _row_ids[i].toString().indexOf(this.controller.dataStorePrefix) == 0 ? _row_ids[i] : this.controller.dataStorePrefix + "::" + _row_ids[i];

					// Delete from internal references
					this.controller.deleteRow(uid);
				}

				// Select & focus next row
				if(next && next.id && !this.options.disable_selection_advance)
				{
					this.controller._selectionMgr.setSelected(next.id, true);
					this.controller._selectionMgr.setFocused(next.id, true);
				}

				// Update the count
				const total = this.dataview.grid._total - _row_ids.length;
				// This will remove the last row!
				// That's OK, because grid adds one in this.controller.deleteRow()
				this.dataview.grid.setTotalCount(total);
				this.controller._selectionMgr.setTotalCount(total);

				// Re-enable automatic updating
				this.dataview.grid.doInvalidate = true;
				this.dataview.grid.invalidate();
			}
		}

		id_loop:
			for(var i = 0; i < _row_ids.length; i++)
			{
				let uid = _row_ids[i].toString().indexOf(this.controller.dataStorePrefix) == 0 ? _row_ids[i] : this.controller.dataStorePrefix + "::" + _row_ids[i];

				// Check for update on a row we don't have
				let known = Object.values(this.controller._indexMap).filter(function(row)
				{
					return row.uid == uid;
				});
				if((_type == et2_nextmatch.UPDATE || _type == et2_nextmatch.UPDATE_IN_PLACE) && (!known || known.length == 0))
				{
					_type = et2_nextmatch.ADD;
					if(update_pref == "exact" && !this.is_sorted_by_modified())
					{
						_type = et2_nextmatch.EDIT;
					}
				}
				if([et2_nextmatch.ADD, et2_nextmatch.UPDATE].indexOf(_type) !== -1)
				{
					// Pre-ask for the row data, and only proceed if we actually get it
					// need to send nextmatch filters too, as server-side will merge old version from request otherwise
					this._refresh_grid(_type, this.controller, _row_ids, uid);
					return;
				}
				switch(_type)
				{
					// update-in-place = update, but always only in place
					case et2_nextmatch.UPDATE_IN_PLACE:
						this.egw().dataRefreshUID(uid);
						break;

					// These ones handled above in dataFetch() callback
					case et2_nextmatch.UPDATE:
						// update [existing] row, maybe we'll put it on top
						break;
					case et2_nextmatch.DELETE:
						// Handled above, more code to execute after loop so don't exit early
						break;
					case et2_nextmatch.ADD:
						break;

					// No more smart things we can do, refresh the whole thing
					case et2_nextmatch.EDIT:
					default:
						// Trigger refresh
						this.applyFilters();
						break id_loop;
				}
			}
		// Trigger an event so app code can act on it
		jQuery(this).triggerHandler("refresh", [this, _row_ids, _type]);
	}

	protected _refresh_grid(type, controller, row_ids, uid)
	{
		// Pre-ask for the row data, and only proceed if we actually get it
		// need to send nextmatch filters too, as server-side will merge old version from request otherwise
		return this.egw().dataFetch(
			this.getInstanceManager().etemplate_exec_id,
			{refresh: row_ids},
			controller._filters,
			this.id, function(data)
			{
				// In the event that the etemplate got removed before the data came back (Usually an action caused
				// a full submit) just stop here.
				if(!this.nm.getParent())
				{
					return;
				}

				if(data.total >= 1)
				{
					this.type == et2_nextmatch.ADD ? this.nm.refresh_add(this.uid, this.type, controller)
												   : this.nm.refresh_update(this.uid, controller);
				}
				else if(this.type == et2_nextmatch.UPDATE)
				{
					// Remove row from controller
					this.controller.deleteRow(this.uid);

					// Adjust total rows, clean grid
					this.controller._grid.setTotalCount(this.nm.controller._grid._total - row_ids.length);
					this.controller._selectionMgr.setTotalCount(this.nm.controller._grid._total);
				}
			}, {
				type: type,
				nm: this,
				controller: controller,
				uid: uid,
				prefix: this.controller.dataStorePrefix
			}, [row_ids]
		);
	}

	/**
	 * An entry has been updated.  Request new data, and ask app about where the row
	 * goes now.
	 *
	 * @param uid
	 */
	protected refresh_update(uid : string, controller : et2_nextmatch_controller)
	{
		// Row data update has been sent, let's move it where app wants it
		let entry = controller._selectionMgr._getRegisteredRowsEntry(uid);

		// Need to delete first as there's a good chance indexes will change in an unknown way
		// and we can't always find it by UID after due to duplication
		controller.deleteRow(uid);

		// Pretend it's a new row, let app tell us where it goes and we'll mark it as new
		if(!this.refresh_add(uid, et2_nextmatch.UPDATE, controller))
		{
			// App did not want the row, or doesn't know where it goes but we've already removed it...
			// Put it back before anyone notices.  New data coming from server anyway.
			let callback = function(data)
			{
				data.class += " new_entry";
				this.egw().dataUnregisterUID(uid, callback, this);
			};
			this.egw().dataRegisterUID(uid, callback, this, this.getInstanceManager().etemplate_exec_id, this.id);
			controller._insertDataRow(entry, true);
		}
		// Update does not need to increase row count, but refresh_add() adds it in
		controller._grid.setTotalCount(controller._grid.getTotalCount() - 1);
		controller._selectionMgr.setTotalCount(controller._grid.getTotalCount());

		return true;
	}

	/**
	 * An entry has been added.  Put it in the list.
	 *
	 * @param uid
	 * @return boolean false: not added, true: added
	 */
	protected refresh_add(uid : string, type = et2_nextmatch.ADD, controller)
	{
		let index : boolean | number = egw.preference("lazy-update") !== "exact" ? 0 :
									   (this.is_sorted_by_modified() ? 0 : false);

		// No add, do a full refresh
		if(index === false)
		{
			return false;
		}

		let time = new Date().valueOf();

		this.egw().dataRegisterUID(uid, this._push_add_callback, {
			nm: this,
			controller: controller,
			uid: uid,
			index: index
		}, this.getInstanceManager().etemplate_exec_id, this.id);
		return true;
	}

	/**
	 * Callback for adding a new row via push
	 *
	 * Expected context: {nm: this, uid: string, index: number}
	 */
	protected _push_add_callback(this : { nm : et2_nextmatch, uid : string, index : number, controller : et2_nextmatch_controller }, data : any)
	{
		if(data && this.nm && this.nm.getParent())
		{
			if(data.class)
			{
				data.class += " new_entry";
			}
			// Don't remove if new data has not arrived
			let stored = egw.dataGetUIDdata(this.uid);
			//if(stored?.timestamp >= time) return;

			// Increase displayed row count or we lose the last row when we add and the total is wrong
			this.controller._grid.setTotalCount(this.nm.controller._grid.getTotalCount() + 1);
			this.controller._selectionMgr.setTotalCount(this.nm.controller._grid.getTotalCount());

			// Insert at the top of the list, or where app said
			var entry = this.controller._selectionMgr._getRegisteredRowsEntry(this.uid);
			entry.idx = typeof this.index == "number" ? this.index : 0;
			this.controller._insertDataRow(entry, true);
		}
		else if(this.nm && this.nm.getParent())
		{
			// Server didn't give us our row data
			// Delete from internal references
			this.controller.deleteRow(this.uid);
			this.controller._grid.setTotalCount(this.nm.controller._grid.getTotalCount() - 1);
			this.controller._selectionMgr.setTotalCount(this.nm.controller._grid.getTotalCount());
		}
		this.nm.egw().dataUnregisterUID(this.uid, this.nm._push_add_callback, this);
	}

	/**
	 * Queue a refresh request until later, when nextmatch is visible
	 *
	 * Nextmatch can't re-draw anything while it's hidden (it messes up the sizing when it renders) so we can't actually
	 * do a refresh right now.  Queue it up and when visible again we'll update then.  If we get too many changes
	 * queued, we'll throw them all away and do a full refresh.
	 *
	 * @param _row_ids
	 * @param _type
	 * @private
	 */
	protected _queue_refresh(_row_ids : string[], _type : string)
	{
		// Maximum number of requests to queue.  50 chosen arbitrarily just to limit things
		const max_queued = 50;

		if(this._queued_refreshes === null)
		{
			// Already too many or an EDIT came, we'll refresh everything later
			return;
		}

		// Bind so we can get the queued data when tab is re-activated
		let tab = jQuery(this.getInstanceManager().DOMContainer.parentNode)
			.one('show.et2_nextmatch', this._queue_refresh_callback.bind(this));


		// Edit means refresh everything, so no need to keep queueing
		// Too many?  Forget it, we'll refresh everything.
		if(this._queued_refreshes.length >= max_queued || _type == et2_nextmatch.EDIT || !_type)
		{
			this._queued_refreshes = null;
			return;
		}

		// Skip if already in array
		if(this._queued_refreshes.some(queue => queue.ids.length === _row_ids.length && queue.ids.every((value, index) => value === _row_ids[index])))
		{
			return;
		}
		this._queued_refreshes.push({ids: _row_ids, type: _type});
	}

	protected _queue_refresh_callback()
	{
		if(this._queued_refreshes === null)
		{
			// Still bound, but length is 0 - full refresh time
			this._queued_refreshes = [];
			return this.applyFilters();
		}
		let types = {};
		types[et2_nextmatch.ADD] = [];
		types[et2_nextmatch.UPDATE] = [];
		types[et2_nextmatch.UPDATE_IN_PLACE] = [];
		types[et2_nextmatch.DELETE] = [];
		for(let refresh of this._queued_refreshes)
		{
			types[refresh.type] = types[refresh.type].concat(refresh.ids);
		}
		this._queued_refreshes = [];
		for(let type in types)
		{
			if(types[type].length > 0)
			{
				// Fire each change type once will all changed IDs
				this.refresh(types[type].filter((v, i, a) => a.indexOf(v) === i), type);
			}
		}
	}

	/**
	 * Is this nextmatch currently sorted by "modified" date
	 *
	 * This is decided by the row_modified options passed from the server and the current sort order
	 */
	public is_sorted_by_modified()
	{
		let sort = this.getValue()?.sort || {};
		return sort && sort.id && sort.id == this.settings.add_on_top_sort_field && sort.asc == false;
	}

	private _get_appname()
	{
		let app = '';
		let list = [];

		list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
		if(this.options.settings.columnselection_pref.indexOf('nextmatch') == 0)
		{
			app = list[0].substring('nextmatch'.length + 1);
		}
		else
		{
			app = list[0];
		}
		return app;
	}

	/**
	 * Gets the selection
	 *
	 * @return Object { ids: [UIDs], inverted: boolean}
	 */
	getSelection() : { ids : string[], all : boolean }
	{
		const selected = this.controller && this.controller._selectionMgr ? this.controller._selectionMgr.getSelected() : null;
		if(typeof selected == "object" && selected != null)
		{
			return selected;
		}
		return {ids: [], all: false};
	}

	/**
	 * Log some debug information about internal values
	 */
	spillYourGuts()
	{
		let guts = function(controller)
		{
			console.log("Controller:", controller);
			console.log("Controller indexMap:", controller._indexMap);
			console.log("Grid:", controller._grid);
			console.log("Selection Manager:", controller._selectionMgr);
			console.log("Selection registered rows:", controller._selectionMgr._registeredRows);
			if(controller && controller._children.length > 0)
			{
				console.groupCollapsed("Sub-grids");
				let child_index = 0;
				for(let child of controller._children)
				{
					console.groupCollapsed("Child " + (++child_index));
					guts(child);
					console.groupEnd();
				}
				console.groupEnd()
			}
		}
		console.group("Nextmatch internals");
		guts(this.controller);
		console.groupEnd();
	}

	/**
	 * Event handler for when the selection changes
	 *
	 * If the onselect attribute was set to a string with javascript code, it will
	 * be executed "legacy style".  You can get the selected values with getSelection().
	 * If the onselect attribute is in app.appname.function style, it will be called
	 * with the nextmatch and an array of selected row IDs.
	 *
	 * The array can be empty, if user cleared the selection.
	 *
	 * @param action ActionObject From action system.  Ignored.
	 * @param senders ActionObjectImplemetation From action system.  Ignored.
	 */
	onselect(action, senders)
	{
		// Execute the JS code connected to the event handler
		if(typeof this.options.onselect == 'function')
		{
			return this.options.onselect.call(this, this.getSelection().ids, this);
		}
	}

	/**
	 * Nextmatch needs a namespace
	 */
	protected _createNamespace() : boolean
	{
		return true;
	}

	/**
	 * Create the dynamic height so nm fills all available space
	 *
	 * @returns {undefined}
	 */
	_getDynheight()
	{
		// Find the parent container, either a tab or the main container
		const tab = this.get_tab_info();

		if(!tab)
		{
			return new et2_dynheight(this.getInstanceManager().DOMContainer, this.innerDiv, 100);
		}

		else if(tab && tab.contentDiv)
		{
			// Bind a resize while we're here
			if(tab.flagDiv)
			{
				tab.flagDiv.addEventListener("click", (e) =>
				{
					window.setTimeout(() => this.resize(), 1);
				});
			}
			return new et2_dynheight(tab.contentDiv, this.innerDiv, 100);
		}

		return false;
	}

	/**
	 * Generates the column caption for the given column widget
	 *
	 * @param {et2_widget} _widget
	 */
	_genColumnCaption(_widget)
	{
		let result = null;

		if(typeof _widget._genColumnCaption == "function") return _widget._genColumnCaption();
		const self = this;

		_widget.iterateOver(function(_widget)
		{
			const label = self.egw().lang(_widget.label || _widget.emptyLabel || _widget.options.label || _widget.options.empty_label || '');
			if(!label) return;	// skip empty, undefined or null labels
			if(!result)
			{
				result = label;
			}
			else
			{
				result += ", " + label;
			}
		}, this, et2_INextmatchHeader);

		return result;
	}

	/**
	 * Generates the column name (internal) for the given column widget
	 * Used in preferences to refer to the columns by name instead of position
	 *
	 * See _getColumnCaption() for human fiendly captions
	 *
	 * @param {et2_widget} _widget
	 */
	_getColumnName(_widget)
	{
		if(typeof _widget._getColumnName == 'function') return _widget._getColumnName();

		const name = _widget.id;
		const child_names = [];
		const children = _widget.getChildren();
		for(let i = 0; i < children.length; i++)
		{
			if(children[i].id) child_names.push(children[i].id);
		}

		const colName = name + (name != "" && child_names.length > 0 ? "_" : "") + child_names.join("_");
		if(colName == "")
		{
			this.egw().debug("info", "Unable to generate nm column name for %o, no IDs", _widget);
		}
		return colName;
	}


	/**
	 * Retrieve the user's preferences for this nextmatch merged with defaults
	 * Column display, column size, etc.
	 */
	_getPreferences()
	{
		// Read preference or default for column visibility
		let negated = false;
		let columnPreference = "";
		if(this.options.settings.default_cols)
		{
			negated = this.options.settings.default_cols[0] == "!";
			columnPreference = negated ? this.options.settings.default_cols.substring(1) : this.options.settings.default_cols;
		}
		if(this.options.settings.selectcols && this.options.settings.selectcols.length)
		{
			columnPreference = this.options.settings.selectcols;
			negated = false;
		}
		if(!this.options.settings.columnselection_pref)
		{
			// Set preference name so changes are saved
			this.options.settings.columnselection_pref = this.options.template;
		}

		let app = '';
		let list = [];
		if(this.options.settings.columnselection_pref)
		{
			let pref = {};
			list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
			if(this.options.settings.columnselection_pref.indexOf('nextmatch') == 0)
			{
				app = list[0].substring('nextmatch'.length + 1);
				pref = egw.preference(this.options.settings.columnselection_pref, app);
			}
			else
			{
				app = list[0];
				// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
				pref = egw.preference("nextmatch-" + this.options.settings.columnselection_pref, app);
			}
			if(pref)
			{
				negated = (pref[0] == "!");
				columnPreference = negated ? (<string>pref).substring(1) : <string>pref;
			}
		}

		let columnDisplay = [];
		// If no column preference or default set, use all columns
		if(typeof columnPreference == "string" && columnPreference.length == 0)
		{
			columnDisplay = [];
			negated = true;
		}

		columnDisplay = typeof columnPreference === "string"
						? et2_csvSplit(columnPreference, null, ",") : columnPreference;

		// Adjusted column sizes
		let size = {};
		if(this.options.settings.columnselection_pref && app)
		{
			let size_pref = this.options.settings.columnselection_pref + "-size";

			// If columnselection pref is missing prefix, add it in
			if(size_pref.indexOf('nextmatch') == -1)
			{
				size_pref = 'nextmatch-' + size_pref;
			}
			size = this.egw().preference(size_pref, app);
		}
		if(!size) size = {};

		// Column order
		const order = {};
		for(let i = 0; i < columnDisplay.length; i++)
		{
			order[columnDisplay[i]] = i;
		}
		return {
			visible: columnDisplay,
			visible_negated: negated,
			negated: negated,
			size: size,
			order: order
		};
	}

	/**
	 * Apply stored user preferences to discovered columns
	 *
	 * @param {array} _row
	 * @param {array} _colData
	 */
	_applyUserPreferences(_row, _colData)
	{
		const prefs = this._getPreferences();
		const columnDisplay = prefs.visible;
		const size = prefs.size;
		const negated = prefs.visible_negated;
		const order = prefs.order;
		let colName = '';

		// Add in display preferences
		if(columnDisplay && columnDisplay.length > 0)
		{
			RowLoop:
				for(let i = 0; i < _row.length; i++)
				{
					colName = '';
					if(_row[i].disabled === true)
					{
						_colData[i].visible = false;
						continue;
					}

					// Customfields needs special processing
					if(_row[i].widget.instanceOf(et2_nextmatch_customfields))
					{
						// Find cf field
						for(var j = 0; j < columnDisplay.length; j++)
						{
							if(columnDisplay[j].indexOf(_row[i].widget.id) == 0)
							{
								_row[i].widget.options.fields = {};
								for(let k = j; k < columnDisplay.length; k++)
								{
									if(columnDisplay[k].indexOf(_row[i].widget.prefix) == 0)
									{
										_row[i].widget.options.fields[columnDisplay[k].substr(1)] = true;
									}
								}
								// Resets field visibility too
								_row[i].widget._getColumnName();
								_colData[i].visible = !(negated || jQuery.isEmptyObject(_row[i].widget.options.fields));
								break;
							}
						}
						// Disable if there are no custom fields
						if(jQuery.isEmptyObject(_row[i].widget.customfields))
						{
							_colData[i].visible = false;
							continue;
						}
						colName = _row[i].widget.id;
					}
					else
					{
						colName = this._getColumnName(_row[i].widget);
					}
					if(!negated)
					{
						_colData[i].order = typeof order[colName] === 'undefined' ? i : order[colName];
					}
					if(!colName) continue;
					_colData[i].visible = negated;
					let stop = false;
					for(var j = 0; j < columnDisplay.length && !stop; j++)
					{
						if(columnDisplay[j] == colName)
						{
							_colData[i].visible = !negated;
							stop = true;
						}
					}

					if(size[colName])
					{
						// Make sure percentages stay percentages, and forget any preference otherwise
						if(_colData[i].width.charAt(_colData[i].width.length - 1) == "%")
						{
							_colData[i].width = typeof size[colName] == 'string' && size[colName].charAt(size[colName].length - 1) == "%" ? size[colName] : _colData[i].width;
						}
						else
						{
							_colData[i].width = parseInt(size[colName]) + 'px';
						}
					}
				}
		}

		_colData.sort(function(a, b)
		{
			return a.order - b.order;
		});
		_row.sort(function(a, b)
		{
			if(typeof a.colData !== 'undefined' && typeof b.colData !== 'undefined')
			{
				return a.colData.order - b.colData.order;
			}
			else if(typeof a.order !== 'undefined' && typeof b.order !== 'undefined')
			{
				return a.order - b.order;
			}
		});
	}

	/**
	 * Take current column display settings and store them in this.egw().preferences
	 * for next time
	 */
	_updateUserPreferences()
	{
		const colMgr = this.dataview.getColumnMgr();
		let app = "";
		if(!this.options.settings.columnselection_pref)
		{
			this.options.settings.columnselection_pref = this.options.template;
		}

		const visibility = colMgr.getColumnVisibilitySet();
		const colDisplay = [];
		const colSize = {};
		const custom_fields = [];

		// visibility is indexed by internal ID, widget is referenced by position, preference needs name
		for(var i = 0; i < colMgr.columns.length; i++)
		{
			// @ts-ignore
			const widget = this.columns[i].widget;
			let colName = this._getColumnName(widget);
			if(colName)
			{
				// Server side wants each cf listed as a seperate column
				if(widget.instanceOf(et2_nextmatch_customfields))
				{
					// Just the ID for server side, not the whole nm name - some apps use it to skip custom fields
					colName = widget.id;
					for(let name in widget.options.fields)
					{
						if(widget.options.fields[name]) custom_fields.push(et2_nextmatch_customfields.PREFIX + name);
					}
				}
				if(visibility[colMgr.columns[i].id].visible) colDisplay.push(colName);

				// When saving sizes, only save columns with explicit values, preserving relative vs fixed
				// Others will be left to flex if width changes or more columns are added
				if(colMgr.columns[i].relativeWidth)
				{
					colSize[colName] = (colMgr.columns[i].relativeWidth * 100) + "%";
				}
				else if(colMgr.columns[i].fixedWidth)
				{
					colSize[colName] = colMgr.columns[i].fixedWidth;
				}
			}
			else if(colMgr.columns[i].fixedWidth || colMgr.columns[i].relativeWidth)
			{
				this.egw().debug("info", "Could not save column width - no name", colMgr.columns[i].id);
			}
		}

		const list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
		let pref = this.options.settings.columnselection_pref;
		if(pref.indexOf('nextmatch') == 0)
		{
			app = list[0].substring('nextmatch'.length + 1);
		}
		else
		{
			app = list[0];
			// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
			pref = "nextmatch-" + this.options.settings.columnselection_pref;
		}

		// Server side wants each cf listed as a seperate column
		jQuery.merge(colDisplay, custom_fields);

		// Update query value, so data source can use visible columns to exclude expensive sub-queries
		const oldCols = this.activeFilters.selectcols ? this.activeFilters.selectcols : [];

		this.activeFilters.selectcols = this.sortedColumnsList.length > 0 ? this.sortedColumnsList : colDisplay;

		// We don't need to re-query if they've removed a column
		const changed = [];
		ColLoop:
			for(var i = 0; i < colDisplay.length; i++)
			{
				for(let j = 0; j < oldCols.length; j++)
				{
					if(colDisplay[i] == oldCols[j]) continue ColLoop;
				}
				changed.push(colDisplay[i]);
			}

		// If a custom field column was added, throw away cache to deal with
		// efficient apps that didn't send all custom fields in the first request
		const cf_added = jQuery(changed).filter(jQuery(custom_fields)).length > 0;

		// Save visible columns and sizes if selectcols is not emtpy (an empty selectcols actually deletes the prefrence)
		if(!jQuery.isEmptyObject(this.activeFilters.selectcols))
		{
			// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
			this.egw().set_preference(app, pref, this.activeFilters.selectcols.join(","),
				// Use callback after the preference gets set to trigger refresh, in case app
				// isn't looking at selectcols and just uses preference
				cf_added ? jQuery.proxy(function()
				{
					if(this.controller) this.controller.update(true);
				}, this) : null
			);
			// Save adjusted column sizes and inform user about it
			this.egw().set_preference(app, pref + "-size", colSize);
			this.egw().message(this.egw().lang("Saved column sizes to preferences."));
		}
		this.egw().set_preference(app, pref + "-size", colSize);

		// No significant change (just normal columns shown) and no need to wait,
		// but the grid still needs to be redrawn if a custom field was removed because
		// the cell content changed.  This is a cheaper refresh than the callback,
		// this.controller.update(true)
		if((changed.length || custom_fields.length) && !cf_added) this.applyFilters();
	}

	_parseHeaderRow(_row, _colData)
	{

		// Make sure there's a widget - cols disabled in template can be missing them, and the header really likes to have a widget

		for(var x = 0; x < _row.length; x++)
		{
			if(!_row[x].widget)
			{
				_row[x].widget = et2_createWidget("label", {});
			}
		}

		// Get column display preference
		this._applyUserPreferences(_row, _colData);

		// Go over the header row and create the column entries
		this.columns = new Array(_row.length);
		const columnData = new Array(_row.length);

		// No action columns in et2
		let remove_action_index = null;

		for(var x = 0; x < _row.length; x++)
		{
			this.columns[x] = jQuery.extend({
				"order": _colData[x] && typeof _colData[x].order !== 'undefined' ? _colData[x].order : x,
				"widget": _row[x].widget
			}, _colData[x]);

			let visibility = (!_colData[x] || _colData[x].visible) ?
							 et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE :
							 et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE;
			if(_colData[x].disabled && _colData[x].disabled !== '' &&
				this.getArrayMgr("content").parseBoolExpression(_colData[x].disabled))
			{
				visibility = et2_dataview_column.ET2_COL_VISIBILITY_DISABLED;
				this.columns[x].visible = false;
			}
			columnData[x] = {
				"id": "col_" + x,
				// @ts-ignore
				"order": this.columns[x].order,
				"caption": this._genColumnCaption(_row[x].widget),
				"visibility": visibility,
				"width": _colData[x] ? _colData[x].width : 0
			};
			if(_colData[x].width === 'auto')
			{
				// Column manager does not understand 'auto', which grid widget
				// uses if width is not set
				columnData[x].width = '100%';
			}
			if(_colData[x].minWidth)
			{
				columnData[x].minWidth = _colData[x].minWidth;
			}
			if(_colData[x].maxWidth)
			{
				columnData[x].maxWidth = _colData[x].maxWidth;
			}

			// No action columns in et2
			const colName = this._getColumnName(_row[x].widget);
			if(colName == 'actions' || colName == 'legacy_actions' || colName == 'legacy_actions_check_all')
			{
				remove_action_index = x;

			}
			else if(!colName)
			{
				// Unnamed column cannot be toggled or saved
				columnData[x].visibility = et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT;
				this.columns[x].visible = true;
			}

		}

		// Remove action column
		if(remove_action_index != null)
		{
			this.columns.splice(remove_action_index, remove_action_index);
			columnData.splice(remove_action_index, remove_action_index);
			_colData.splice(remove_action_index, remove_action_index);
		}

		// Create the column manager and update the grid container
		this.dataview.setColumns(columnData);

		for(var x = 0; x < _row.length; x++)
		{
			// Append the widget to this container
			this.addChild(_row[x].widget);
		}

		// Create the nextmatch row provider
		this.rowProvider = new et2_nextmatch_rowProvider(
			this.dataview.rowProvider, this._getSubgrid, this);

		// Register handler to update preferences when column properties are changed
		const self = this;
		this.dataview.onUpdateColumns = function()
		{
			// Use apply to make sure context is there
			self._updateUserPreferences.apply(self);

			// Allow column widgets a chance to resize
			self.iterateOver(function(widget)
			{
				if (typeof widget.resize === 'function')
				{
					widget.resize();
				}
			}, self, et2_IResizeable);
		};

		// Register handler for column selection popup, or disable
		if(this.selectPopup)
		{
			this.selectPopup.remove();
			this.selectPopup = null;
		}
		if(this.options.settings.no_columnselection)
		{
			this.dataview.selectColumnsClick = function()
			{
				return false;
			};
			jQuery('span.selectcols', this.dataview.headTr).hide();
		}
		else
		{
			jQuery('span.selectcols', this.dataview.headTr).show();
			this.dataview.selectColumnsClick = function(event)
			{
				self._selectColumnsClick(event);
			};
		}
	}

	_parseDataRow(_row, _rowData, _colData)
	{
		const columnWidgets = [];

		_row.sort(function(a, b)
		{
			return a.colData.order - b.colData.order;
		});

		for(let x = 0; x < this.columns.length; x++)
		{
			if(!this.columns[x].visible)
			{
				continue;
			}

			columnWidgets[x] = _row[x].widget;

			// Pass along column alignment
			if(_row[x].align && columnWidgets[x])
			{
				columnWidgets[x].align = _row[x].align;
			}
		}

		this.rowProvider.setDataRowTemplate(columnWidgets, _rowData, this);

		// Create the grid controller
		this.controller = new et2_nextmatch_controller(
			null,
			this.egw(),
			this.getInstanceManager().etemplate_exec_id,
			this,
			null,
			this.dataview.grid,
			this.rowProvider,
			this.options.settings.action_links,
			null,
			this.options.actions
		);

		this.controller.setFilters(this.activeFilters)

		// Need to trigger empty row the first time
		if(total == 0) this.controller._emptyRow();

		// Set data cache prefix to either provided custom or auto
		if(!this.options.settings.dataStorePrefix && this.options.settings.get_rows)
		{
			// Use jsapi data module to update
			let list = this.options.settings.get_rows.split('.', 2);
			if(list.length < 2) list = this.options.settings.get_rows.split('_');	// support "app_something::method"
			this.options.settings.dataStorePrefix = list[0];
		}
		this.controller.setPrefix(this.options.settings.dataStorePrefix);

		// Set the view
		this.controller._view = this.view;

		// Load the initial order
		/*this.controller.loadInitialOrder(this._getInitialOrder(
			this.options.settings.rows, this.options.settings.row_id
		));*/

		// Set the initial row count
		var total = typeof this.options.settings.total != "undefined" ?
					this.options.settings.total : 0;
		// This triggers an invalidate, which updates the grid
		this.dataview.grid.setTotalCount(total);

		// Insert any data sent from server, so invalidate finds data already
		if(this.options.settings.rows && this.options.settings.num_rows)
		{
			this.controller.loadInitialData(
				this.options.settings.dataStorePrefix,
				this.options.settings.row_id,
				this.options.settings.rows
			);
			// Remove, to prevent duplication
			delete this.options.settings.rows;
		}
	}

	_parseGrid(_grid)
	{
		// Search the rows for a header-row - if one is found, parse it
		for(let y = 0; y < _grid.rowData.length; y++)
		{
			// Parse the first row as a header, need header to parse the data rows
			if(_grid.rowData[y]["class"] == "th" || y == 0)
			{
				this._parseHeaderRow(_grid.cells[y], _grid.colData);
			}
			else if(this.controller == null)
			{
				this._parseDataRow(_grid.cells[y], _grid.rowData[y],
					_grid.colData);
			}
		}
		this.dataview.table.resize();
	}

	_getSubgrid(_row, _data, _controller)
	{
		// Fetch the id of the element described by _data, this will be the
		// parent_id of the elements in the subgrid
		const rowId = _data.content[this.options.settings.row_id];

		// Create a new grid with the row as parent and the dataview grid as
		// parent grid
		const grid = new et2_dataview_grid(_row, this.dataview.grid);

		// Create a new controller for the grid
		const controller = new et2_nextmatch_controller(
			_controller,
			this.egw(),
			this.getInstanceManager().etemplate_exec_id,
			this,
			rowId,
			grid,
			this.rowProvider,
			this.options.settings.action_links,
			_controller.getObjectManager()
		);
		controller.update();

		// Register inside the destruction callback of the grid
		grid.setDestroyCallback(function()
		{
			controller.destroy();
		});

		return grid;
	}

	_getInitialOrder(_rows, _rowId)
	{

		const _order = [];

		// Get the length of the non-numerical rows arra
		let len = 0;
		for(let key in _rows)
		{
			if(!isNaN(parseInt(key)) && parseInt(key) > len)
				len = parseInt(key);
		}

		// Iterate over the rows
		for(let i = 0; i < len; i++)
		{
			// Get the uid from the data
			const uid = this.egw().app_name() + '::' + _rows[i][_rowId];

			// Store the data for that uid
			this.egw().dataStoreUID(uid, _rows[i]);

			// Push the uid onto the order array
			_order.push(uid);
		}

		return _order;
	}

	_selectColumnsClick(e)
	{
		const self = this;
		const columnMgr = this.dataview.getColumnMgr();

		// ID for faking letter selection in column selection
		const LETTERS = '~search_letter~';

		const columns = [];
		const columns_selected = [];

		for(var i = 0; i < columnMgr.columns.length; i++)
		{
			var col = columnMgr.columns[i];
			const widget = this.columns[i].widget;
			columns.push({...col, widget: widget});
		}

		// Letter search
		if(this.options.settings.lettersearch)
		{
			columns.push({
				id: LETTERS,
				caption: this.egw().lang('Search letter'),
				visibility: (this.header.lettersearch.is(':visible') ? et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE : et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT)
			});
		}

		let updateColumns = function(button, values)
		{
			if(button != Et2Dialog.OK_BUTTON)
			{
				return;
			}

			// Update visibility
			const visibility = {};
			for(var i = 0; i < columnMgr.columns.length; i++)
			{
				const col = columnMgr.columns[i];
				if(col.caption && col.visibility !== et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT &&
					col.visibility !== et2_dataview_column.ET2_COL_VISIBILITY_DISABLED)
				{
					visibility[col.id] = {visible: false};
				}
			}
			const value = values.columns;

			// Update & remove letter filter
			if(self.header.lettersearch)
			{
				var show_letters = true;
				if(value.indexOf(LETTERS) >= 0)
				{
					value.splice(value.indexOf(LETTERS), 1);
				}
				else
				{
					show_letters = false;
				}
				self._set_lettersearch(show_letters);
			}
			self.sortedColumnsList = [];
			for(var i = 0; i < value.length; i++)
			{
				// Handle skipped columns
				let column = 0;
				while(value[i] != "col_" + column && column < columnMgr.columns.length)
				{
					column++;
				}
				if(!self.columns[column])
				{
					continue
				}
				if(visibility[value[i]])
				{
					visibility[value[i]].visible = true;
				}
				let col_name = self._getColumnName(self.columns[column].widget);

				// Custom fields are listed seperately in column list, but are only 1 column
				if(self.columns[column] && self.columns[column].widget.instanceOf(et2_nextmatch_customfields))
				{
					const cf = self.columns[column].widget.options.customfields;
					const visible = self.columns[column].widget.options.fields;
					self.sortedColumnsList.push(self.columns[column].widget.id);

					// Turn off all custom fields
					for(var field_name in cf)
					{
						visible[field_name] = false;
					}
					// Turn on selected custom fields
					for(let j = i; j < value.length; j++)
					{
						if(value[j].indexOf(et2_customfields_list.PREFIX) != 0)
						{
							continue;
						}
						self.sortedColumnsList.push(value[j]);

						visible[value[j].substring(1)] = true;
						i++;
					}
					(<et2_customfields_list><unknown>self.columns[column].widget).set_visible(visible);
				}
				else
				{
					self.sortedColumnsList.push(col_name);
				}
			}
			columnMgr.setColumnVisibilitySet(visibility);

			self.dataview.updateColumns();

			// Auto refresh
			self._set_autorefresh(values.autoRefresh);

			if(show_letters)
			{
				self.activeFilters.selectcols.push('lettersearch');
			}
			self.getInstanceManager().submit();

			self.selectPopup = null;
		};

		// Build the popup
		const apps = this.egw().user('apps');
		let colDialog = new Et2Dialog(this.egw());
		colDialog.transformAttributes({
			title: this.egw().lang("Select columns"),
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			template: this.egw().link(this.egw().webserverUrl + "/api/templates/default/nm_column_selection.xet"),
			callback: updateColumns,
			value: {
				content: {
					autoRefresh: parseInt(this._get_autorefresh())
				},
				readonlys: {
					default_preference: typeof apps.admin == "undefined"
				},
				modifications: {
					autoRefresh: {
						disabled: this.options.disable_autorefresh
					},
					columns: {
						columns: columns,
					}
				}
			}
		});

		document.body.appendChild(colDialog);
	}

	/**
	 * Get the currently displayed columns
	 * Each customfield is listed separately
	 */
	get_columns()
	{
		const colMgr = this.dataview.getColumnMgr();
		if(!colMgr)
		{
			return [];
		}
		const visibility = colMgr.getColumnVisibilitySet();
		const colDisplay = [];
		const custom_fields = [];

		// visibility is indexed by internal ID, widget is referenced by position, preference needs name
		for(var i = 0; i < colMgr.columns.length; i++)
		{
			// @ts-ignore
			const widget = this.columns[i].widget;
			let colName = this._getColumnName(widget);
			if(colName)
			{
				// Server side wants each cf listed as a seperate column
				if(widget.instanceOf(et2_nextmatch_customfields))
				{
					// Just the ID for server side, not the whole nm name - some apps use it to skip custom fields
					colName = widget.id;
					for(let name in widget.options.fields)
					{
						if(widget.options.fields[name]) custom_fields.push(et2_nextmatch_customfields.PREFIX + name);
					}
				}
				if(visibility[colMgr.columns[i].id].visible)
				{
					colDisplay.push(colName);
				}
			}
		}

		// List each customfield as a seperate column
		jQuery.merge(colDisplay, custom_fields);

		return this.sortedColumnsList.length > 0 ? this.sortedColumnsList : colDisplay;
	}

	/**
	 * Set the currently displayed columns, without updating user's preference
	 *
	 * @param {string[]} column_list List of column names
	 * @param {boolean} trigger_update =false - explicitly trigger an update
	 */
	set_columns(column_list : string[], trigger_update = false)
	{
		const columnMgr = this.dataview.getColumnMgr();
		const visibility = {};

		// Initialize to false
		for(var i = 0; i < columnMgr.columns.length; i++)
		{
			const col = columnMgr.columns[i];
			if(col.caption && col.visibility != et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT)
			{
				visibility[col.id] = {visible: false};
			}
		}
		for(var i = 0; i < this.columns.length; i++)
		{

			let widget = this.columns[i].widget;
			let colName = this._getColumnName(widget);
			if(column_list.indexOf(colName) !== -1 &&
				typeof visibility[columnMgr.columns[i].id] !== 'undefined'
			)
			{
				visibility[columnMgr.columns[i].id].visible = true;
			}
			// Custom fields are listed seperately in column list, but are only 1 column
			if(widget && widget.instanceOf(et2_nextmatch_customfields))
			{

				// Just the ID for server side, not the whole nm name - some apps use it to skip custom fields
				colName = widget.id;
				if(column_list.indexOf(colName) !== -1)
				{
					visibility[columnMgr.columns[i].id].visible = true;
				}

				const cf = this.columns[i].widget.options.customfields;
				const visible = this.columns[i].widget.options.fields;

				// Turn off all custom fields
				for(let field_name in cf)
				{
					visible[field_name] = false;
				}
				// Turn on selected custom fields - start from 0 in case they're not in order
				for(let j = 0; j < column_list.length; j++)
				{
					if(column_list[j].indexOf(et2_customfields_list.PREFIX) != 0) continue;
					visible[column_list[j].substring(1)] = true;
				}
				(<et2_nextmatch_customfields><unknown>widget).set_visible(visible);
			}
		}
		columnMgr.setColumnVisibilitySet(visibility);

		// We don't want to update user's preference, so directly update
		this.dataview._updateColumns();

		// Allow column widgets a chance to resize
		this.iterateOver(function(widget)
		{
			if (typeof widget.resize === 'function')
			{
				widget.resize();
			}
		}, this, et2_IResizeable);
	}

	/**
	 * Set the letter search preference, and update the UI
	 *
	 * @param {boolean} letters_on
	 */
	_set_lettersearch(letters_on)
	{
		if(letters_on)
		{
			this.header.lettersearch.show();
		}
		else
		{
			this.header.lettersearch.hide();
		}
		const lettersearch_preference = "nextmatch-" + this.options.settings.columnselection_pref + "-lettersearch";
		this.egw().set_preference(this.egw().app_name(), lettersearch_preference, letters_on);
	}

	/**
	 * Set the auto-refresh time period, and starts the timer if not started
	 *
	 * @param time int Refresh period, in seconds
	 */
	_set_autorefresh(time)
	{
		// Start / update timer
		if(this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			delete this._autorefresh_timer;
		}

		// Store preference
		const refresh_preference = "nextmatch-" + this.options.settings.columnselection_pref + "-autorefresh";
		const app = this._get_appname();
		if(this._get_autorefresh() != time)
		{
			this.egw().set_preference(app, refresh_preference, time);
		}
		if(time > 0)
		{
			if(!this.controller)
			{
				// Controller is not ready yet, come back later
				setTimeout(() => {this._set_autorefresh(time)}, 1000);
				return;
			}
			this._autorefresh_timer = setInterval(jQuery.proxy(this.controller.update, this.controller), time * 1000);

			// Bind to tab show/hide events, so that we don't bother refreshing in the background
			jQuery(this.getInstanceManager().DOMContainer.parentNode).on('hide.et2_nextmatch', jQuery.proxy(function(e)
			{
				// Stop
				window.clearInterval(this._autorefresh_timer);
				jQuery(e.target).off(e);

				// If the autorefresh time is up, bind once to trigger a refresh
				// (if needed) when tab is activated again
				this._autorefresh_timer = setTimeout(jQuery.proxy(function()
				{
					// Check in case it was stopped / destroyed since
					if(!this._autorefresh_timer || !this.getInstanceManager()) return;

					jQuery(this.getInstanceManager().DOMContainer.parentNode).one('show.et2_nextmatch',
						// Important to use anonymous function instead of just 'this.refresh' because
						// of the parameters passed
						jQuery.proxy(function()
						{
							this.refresh(null, 'edit');
						}, this)
					);
				}, this), time * 1000);
			}, this));
			jQuery(this.getInstanceManager().DOMContainer.parentNode).on('show.et2_nextmatch', jQuery.proxy(function(e)
			{
				// Start normal autorefresh timer again
				this._set_autorefresh(this._get_autorefresh());
				jQuery(e.target).off(e);
			}, this));
		}
	}

	/**
	 * Get the auto-refresh timer
	 *
	 * @return int Refresh period, in secods
	 */
	_get_autorefresh()
	{
		if(this.options.disable_autorefresh)
		{
			return 0;
		}
		const refresh_preference = "nextmatch-" + this.options.settings.columnselection_pref + "-autorefresh";
		return this.egw().preference(refresh_preference, this._get_appname());
	}

	/**
	 * Enable or disable autorefresh
	 *
	 * If false, autorefresh will be shown in column selection.  If the user already has an autorefresh preference
	 * for this nextmatch, the timer will be started.
	 *
	 * If true, the timer will be stopped and autorefresh will not be shown in column selection
	 *
	 * @param disabled
	 */
	set_disable_autorefresh(disabled : boolean)
	{
		this.options.disable_autorefresh = disabled;

		this._set_autorefresh(this._get_autorefresh());
	}

	/**
	 * When the template attribute is set, the nextmatch widget tries to load
	 * that template and to fetch the grid which is inside of it. It then calls
	 *
	 * @param {string} template_name Full template name in the form app.template[.template]
	 */
	set_template(template_name : string)
	{
		const template = et2_createWidget("template", {"id": template_name}, this);
		if(this.template)
		{
			// Stop early to prevent unneeded processing, and prevent infinite
			// loops if the server changes the template in get_rows
			if(this.template == template_name)
			{
				return;
			}

			// Free the grid components - they'll be re-created as the template is processed
			this.dataview.destroy();
			this.rowProvider.destroy();
			this.controller.destroy();
			this.controller = null;

			// Free any children from previous template
			// They may get left behind because of how detached nodes are processed
			// We don't use iterateOver because it checks sub-children
			for(let i = this._children.length - 1; i >= 0; i--)
			{
				const _node = this._children[i];
				if(_node != this.header && _node !== template)
				{
					this.removeChild(_node);
					_node.destroy();
				}
			}

			// Clear this setting if it's the same as the template, or
			// the columns will not be loaded
			if(this.template == this.options.settings.columnselection_pref)
			{
				this.options.settings.columnselection_pref = template_name;
			}
			this.dataview = new et2_dataview(this.innerDiv, this.egw());
		}

		if(!template)
		{
			this.egw().debug("error", "Error while loading definition template for " +
				"nextmatch widget.", template_name);
			return;
		}

		if(this.options.disabled)
		{
			return;
		}

		// Deferred parse function - template might not be fully loaded
		const parse = function(template)
		{
			// Keep the name of the template, as we'll free up the widget after parsing
			this.template = template_name;

			// Fetch the grid element and parse it
			const definitionGrid = template.getChildren()[0];
			if(definitionGrid && definitionGrid instanceof et2_grid)
			{
				this._parseGrid(definitionGrid);
			}
			else
			{
				this.egw().debug("error", "Nextmatch widget expects a grid to be the " +
					"first child of the defined template.");
				return;
			}

			// Free the template again, but don't remove it
			setTimeout(function()
			{
				template.destroy();
			}, 1);

			// Call the "setNextmatch" function of all registered
			// INextmatchHeader widgets.  This updates this.activeFilters.col_filters according
			// to what's in the template.
			this.iterateOver(function(_node)
			{
				_node.setNextmatch(this);
			}, this, et2_INextmatchHeader);

			// Set filters to current values
			// TODO this.controller.setFilters(this.activeFilters);

			// If no data was sent from the server, and num_rows is 0, the nm will be empty.
			// This triggers a cache check if visible
			if(!this.options.settings.num_rows && this.controller)
			{
				if(jQuery(this.getDOMNode()).filter(":visible").length > 0)
				{
					this.controller.update();
				}
				else
				{
					// Not visible, queue it up
					this._queue_refresh([], et2_nextmatch.EDIT);
				}
			}

			// Load the default sort order
			if(this.options.settings.order && this.options.settings.sort)
			{
				this.sortBy(this.options.settings.order,
					this.options.settings.sort == "ASC", false);
			}

			// Start auto-refresh
			this._set_autorefresh(this._get_autorefresh());
		};

		// Template might not be loaded yet, defer parsing
		const promise = [];
		template.loadingFinished(promise);

		// Wait until template (& children) are done
		// Keep promise so we can return it from doLoadingFinished
		this.template_promise = Promise.all(promise).then(() =>
			{
				parse.call(this, template);
				if(!this.dynheight)
				{
					this.dynheight = this._getDynheight();
				}
				this.dynheight.initialized = false;

				// Give components a chance to finish.  Their size will affect available space, especially column headers.
				let waitForWebComponents = [];
				this.getChildren().forEach((w) =>
				{
					// @ts-ignore
					if(typeof w.updateComplete !== "undefined")
					{
						// @ts-ignore
						waitForWebComponents.push(w.updateComplete)
					}
				});

				Promise.all(waitForWebComponents).then(() =>
				{
					this.resize();
				});
			}
		).finally(() => this.template_promise = null);

		return this.template_promise;
	}

	// Some accessors to match conventions
	set_hide_header(hide : boolean)
	{
		(hide ? this.header.div.hide() : this.header.div.show());
	}

	set_header_left(template : string)
	{
		this.header._build_header("left", template);
	}

	set_header_right(template : string)
	{
		this.header._build_header("right", template);
	}

	set_header_row(template : string)
	{
		this.header._build_header("row", template);
	}

	set_no_filter(bool, filter_name)
	{
		if(typeof filter_name == 'undefined')
		{
			filter_name = 'filter';
		}
		this.options['no_' + filter_name] = bool;

		let filter = this.header[filter_name];
		if(filter)
		{
			filter.set_disabled(bool);
		}
		else if(bool)
		{
			filter = this.header._build_select(filter_name, 'et2-select',
				this.settings[filter_name], this.settings[filter_name + '_no_lang']);
		}
	}

	set_no_filter2(bool)
	{
		this.set_no_filter(bool, 'filter2');
	}

	/**
	 * Directly change filter value, with no server query.
	 *
	 * This allows the server app code to change filter value, and have it
	 * updated in the client UI.
	 *
	 * @param {String|number} value
	 */
	set_filter(value)
	{
		const update = this.update_in_progress;
		this.update_in_progress = true;

		this.activeFilters.filter = value;

		// Update the header
		this.header.setFilters(this.activeFilters);

		this.update_in_progress = update;
	}

	/**
	 * Directly change filter2 value, with no server query.
	 *
	 * This allows the server app code to change filter2 value, and have it
	 * updated in the client UI.
	 *
	 * @param {String|number} value
	 */
	set_filter2(value)
	{
		const update = this.update_in_progress;
		this.update_in_progress = true;

		this.activeFilters.filter2 = value;

		// Update the header
		this.header.setFilters(this.activeFilters);

		this.update_in_progress = update;
	}

	/**
	 * If nextmatch starts disabled, it will need a resize after being shown
	 * to get all the sizing correct.  Override the parent to add the resize
	 * when enabling.
	 *
	 * @param {boolean} _value
	 */
	set_disabled(_value : boolean)
	{
		const previous = this.disabled;
		super.set_disabled(_value);

		if(previous && !_value)
		{
			this.resize();
		}
	}

	/**
	 * Actions are handled by the controller, so ignore these during init.
	 *
	 * @param {object} actions
	 */
	set_actions(actions : object[])
	{
		if(actions != this.options.actions && this.controller != null && this.controller._actionManager)
		{
			for(let i = this.controller._actionManager.children.length - 1; i >= 0; i--)
			{
				this.controller._actionManager.children[i].remove();
			}
			this.options.actions = actions;
			this.options.settings.action_links = this.controller._actionLinks = this._get_action_links(actions);

			this.controller._initActions(actions);
		}
	}

	/**
	 * Switch view between row and tile.
	 * This should be followed by a call to change the template to match, which
	 * will cause a reload of the grid using the new settings.
	 *
	 * @param {string} view Either 'tile' or 'row'
	 */
	set_view(view : "tile" | "row")
	{
		// Restrict to the only 2 accepted values
		if(view == 'tile')
		{
			this.view = 'tile';
		}
		else
		{
			this.view = 'row';
		}
	}

	/**
	 * Set a different / additional handler for dropped files.
	 *
	 * File dropping doesn't work with the action system, so we handle it in the
	 * nextmatch by linking automatically to the target row.  This allows an additional handler.
	 * It should accept a row UID and a File[], and return a boolean Execute the default (link) action
	 *
	 * @param {String|Function} handler
	 */
	set_onfiledrop(handler)
	{
		this.options.onfiledrop = handler;
	}

	/**
	 * Handle drops of files by linking to the row, if possible.
	 *
	 * HTML5 / native file drops conflict with jQueryUI draggable, which handles
	 * all our drop actions.  So we side-step the issue by registering an additional
	 * drop handler on the rows parent.  If the row/actions itself doesn't handle
	 * the drop, it should bubble and get handled here.
	 *
	 * @param {object} event
	 * @param {object} target
	 */
	handle_drop(event, target)
	{
		// Check to see if we can handle the link
		// First, find the UID
		const row = this.controller.getRowByNode(target);
		const uid = row?.uid || null;

		// Get the file information
		let files = [];
		if(event.originalEvent && event.originalEvent.dataTransfer &&
			event.originalEvent.dataTransfer.files && event.originalEvent.dataTransfer.files.length > 0)
		{
			files = event.originalEvent.dataTransfer.files;
		}
		else
		{
			return false;
		}

		// Exectute the custom handler code
		if(this.options.onfiledrop && !this.options.onfiledrop.call(this, uid, files))
		{
			return false;
		}
		event.stopPropagation();
		event.preventDefault();

		if(!row || !row.uid) return false;

		// Link the file to the row
		// just use a link widget, it's all already done
		const split = uid.split('::');
		const link_value = {
			to_app: split.shift(),
			to_id: split.join('::')
		};
		// Create widget and mangle to our needs
		const link = <et2_link_to>et2_createWidget("link-to", {value: link_value}, this);
		link.loadingFinished();
		link.file_upload.set_drop_target(false);

		if(row.row.tr)
		{
			// Ignore most of the UI, just use the status indicators
			const status = jQuery(document.createElement("div"))
				.addClass('et2_link_to')
				.width(row.row.tr.width())
				.position({my: "left top", at: "left top", of: row.row.tr})
				.append(link.status_span)
				.append(link.file_upload.progress)
				.appendTo(row.row.tr);

			// Bind to link event so we can remove when done
			link.div.on('link.et2_link_to', function(e, linked)
			{
				if(!linked)
				{
					jQuery("li.success", link.file_upload.progress)
						.removeClass('success').addClass('validation_error');
				}
				else
				{
					// Update row
					link._parent.refresh(uid, 'edit');
				}
				// Fade out nicely
				status.delay(linked ? 1 : 2000)
					.fadeOut(500, function()
					{
						link.destroy();
						status.remove();
					});

			});
		}

		// Upload and link - this triggers the upload, which triggers the link, which triggers the cleanup and refresh
		link.file_upload.set_value(files);
	}

	getDOMNode(_sender?)
	{
		if(_sender == this || typeof _sender === 'undefined')
		{
			return this.div[0];
		}
		if(_sender == this.header)
		{
			return this.header.div[0];
		}
		for(let i = 0; i < this.columns.length; i++)
		{
			if(this.columns[i] && this.columns[i].widget && _sender == this.columns[i].widget)
			{
				return this.dataview.getHeaderContainerNode(i);
			}
		}

		// Let header have a chance
		if(_sender && _sender._parent && _sender._parent == this)
		{
			return this.header.getDOMNode(_sender);
		}

		return null;
	}

	/**
	 * Called when loading the widget (sub-tree) is finished. First when this
	 * function is called, the DOM-Tree is created. loadingFinished is
	 * recursively called for all child elements. Do not directly override this
	 * function but the doLoadingFinished function which is executed before
	 * descending deeper into the DOM-Tree.
	 *
	 * Some widgets (template) do not load immediately because they request
	 * additional resources via AJAX.  They will return a Deferred Promise object.
	 * If you call loadingFinished(promises) after creating such a widget
	 * programmatically, you might need to wait for it to fully complete its
	 * loading before proceeding.
	 *
	 * Overridden to skip children in the sub-templates since we handle those directly.
	 * Putting the children's promises into the list will stall the load, since those children
	 * will never actually get completed - we clone them, and use the clones instead.
	 *
	 * @param {Promise[]} promises List of promises from widgets that are not done.  Pass an empty array, it will be filled if needed.
	 */
	loadingFinished(promises?)
	{
		// Call all availble setters
		this.initAttributes(this.options);
		let childPromises = [];
		let loadChildren = () =>
		{
			// Descend recursively into the tree
			for(var i = 0; i < this._children.length; i++)
			{
				try
				{
					this._children[i].loadingFinished(childPromises);
				}
				catch(e)
				{
					egw.debug("error", "There was an error with a widget:\nError:%o\nProblem widget:%o", e.valueOf(), this._children[i], e.stack);
				}
			}
		};
		var result = this.doLoadingFinished();
		if(typeof result == "object" && result.then)
		{
			// Widget is waiting.  Add to the list
			promises.push(result);
			result.then(loadChildren);
		}
		else
		{
			loadChildren()
		}


	}

	// Input widget

	/**
	 * Get the current 'value' for the nextmatch
	 */
	getValue() : ActiveFilters
	{
		const _ids = this.getSelection();

		// Translate the internal uids back to server uids
		const idsArr = _ids.ids;
		for(let i = 0; i < idsArr.length; i++)
		{
			idsArr[i] = idsArr[i].split("::").pop();
		}
		const value : ActiveFilters = {
			selected: idsArr,
			col_filter: {}
		};
		jQuery.extend(value, this.activeFilters, this.value);

		if(typeof value.selectcols == "undefined" || value.selectcols.length === 0)
		{
			value.selectcols = this.get_columns();
		}
		return value;
	}

	resetDirty()
	{
	}

	isDirty()
	{
		return false;
	}

	isValid()
	{
		return true;
	}

	set_value(_value)
	{
		this.value = _value;
	}

	// Printing
	/**
	 * Prepare for printing
	 *
	 * We check for un-loaded rows, and ask the user what they want to do about them.
	 * If they want to print them all, we ask the server and print when they're loaded.
	 */
	beforePrint()
	{
		// Add the class, if needed
		this.div.addClass('print');

		// Trigger resize, so we can fit on a page
		this.dynheight.outerNode.css('max-width', this.div.css('max-width'));
		this.resize();
		// Reset height to auto (after width resize) so there's no restrictions
		this.dynheight.innerNode.css('height', 'auto');

		// Check for rows that aren't loaded yet, or lots of rows
		const range = this.controller._grid.getIndexRange();
		this.print.old_height = this.controller._grid._scrollHeight;
		const loaded_count = range.bottom - range.top + 1;
		const total = this.controller._grid.getTotalCount();

		// Defer the printing to ask about columns & rows
		return new Promise(async(resolve, reject) =>
		{
			let pref = this.options.settings.columnselection_pref;
			if(pref.indexOf('nextmatch') == 0)
			{
				pref = 'nextmatch-' + pref;
			}
			const app = this.getInstanceManager().app;

			const columns = [];
			const columnMgr = this.dataview.getColumnMgr();
			pref += '_print';
			const columns_selected = [];

			for(var i = 0; i < columnMgr.columns.length; i++)
			{
				let col = columnMgr.columns[i];
				const widget = this.columns[i].widget;
				columns.push({...col, widget: widget, name: this._getColumnName(widget)});
			}

			// Preference exists?  Set it now
			if(this.egw().preference(pref, app))
			{
				this.set_columns(jQuery.extend([], this.egw().preference(pref, app)));
			}

			const callback = function(button, value)
			{
				if(button === Et2Dialog.CANCEL_BUTTON)
				{
					// Give dialog a chance to close, or it will be in the print
					window.setTimeout(function()
					{
						reject();
					}, 0);
					return;
				}

				const orientation = value.orientation ? "landscape" : "portrait";
				// Set CSS for orientation
				this.div.addClass(orientation);
				this.egw().set_preference(app, pref + '_orientation', orientation);


				// Try to tell browser about orientation
				const css = '@page { size: ' + orientation + '; }',
					head = document.head || document.getElementsByTagName('head')[0],
					style = document.createElement('style');

				style.type = 'text/css';
				style.media = 'print';

				// @ts-ignore
				if(style.styleSheet)
				{
					// @ts-ignore
					style.styleSheet.cssText = css;
				}
				else
				{
					style.appendChild(document.createTextNode(css));
				}

				head.appendChild(style);
				this.print.orientation_style = style;

				// Trigger resize, so we can fit on a page
				this.dynheight.outerNode.css('max-width', this.div.css('max-width'));

				// Handle columns
				let column_names = [];
				value.columns.forEach((col_id) =>
				{
					let name = columns.find((col) => col.id == col_id)?.name || ""
					column_names.push(name || col_id);
				})
				this.set_columns(column_names);
				this.egw().set_preference(app, pref, column_names);

				let rows = parseInt(value.row_count);
				if(rows > total)
				{
					rows = total;
				}

				// If they want the whole thing, style it as all
				if(button === Et2Dialog.OK_BUTTON && rows == this.controller._grid.getTotalCount())
				{
					// Add the class, gives more reliable sizing
					this.div.addClass('print');
					// Show it all
					jQuery('.egwGridView_scrollarea', this.div).css('height', 'auto');
				}
				// We need more rows
				if(button === 'dialog[all]' || rows > loaded_count)
				{
					let count = 0;
					let fetchedCount = 0;
					let cancel = false;
					const nm = this;
					const dialog = Et2Dialog.show_dialog(
						// Abort the long task if they canceled the data load
						function()
						{
							count = total;
							cancel = true;
							window.setTimeout(function()
							{
								reject();
							}, 0);
						},
						egw.lang('Loading'), egw.lang('please wait...'), {}, [
							{
								"button_id": Et2Dialog.CANCEL_BUTTON,
								label: 'cancel',
								id: 'dialog[cancel]',
								image: 'cancel'
							}
						]
					);

					// dataFetch() is asynchronous, so all these requests just get fired off...
					// 200 rows chosen arbitrarily to reduce requests.
					do
					{
						const ctx = {
							"self": this.controller,
							"start": count,
							"count": Math.min(rows, 200),
							"lastModification": this.controller._lastModification
						};
						if(nm.controller.dataStorePrefix)
						{
							// @ts-ignore
							ctx.prefix = nm.controller.dataStorePrefix;
						}
						nm.controller.dataFetch({start: count, num_rows: Math.min(rows, 200)}, function(data)
						{
							// Keep track
							if(data && data.order)
							{
								fetchedCount += data.order.length;
							}
							nm.controller._fetchCallback.apply(this, arguments);

							if(fetchedCount >= rows)
							{
								if(cancel)
								{
									dialog.destroy();
									reject();
									return;
								}
								// Use CSS to hide all but the requested rows
								// Prevents us from showing more than requested, if actual height was less than average
								nm.print.row_selector = ".egwGridView_grid > tbody > tr:not(:nth-child(-n+" + rows + "))";
								egw.css(nm.print.row_selector, 'display: none');

								// No scrollbar in print view
								jQuery('.egwGridView_scrollarea', this.div).css('overflow-y', 'hidden');
								// Show it all
								jQuery('.egwGridView_scrollarea', this.div).css('height', 'auto');

								// Grid needs to redraw before it can be printed, so wait
								window.setTimeout(function()
								{
									dialog.close();

									// Should be OK to print now
									resolve();
								}.bind(nm), et2_dataview_grid.ET2_GRID_INVALIDATE_TIMEOUT);

							}

						}, ctx);
						count += 200;
					}
					while(count < rows);
					nm.controller._grid.setScrollHeight(nm.controller._grid.getAverageHeight() * (rows + 1));
				}
				else
				{
					// Don't need more rows, limit to requested and finish

					// Show it all
					jQuery('.egwGridView_scrollarea', this.div).css('height', 'auto');

					// Use CSS to hide all but the requested rows
					// Prevents us from showing more than requested, if actual height was less than average
					this.print.row_selector = ".egwGridView_grid > tbody > tr:not(:nth-child(-n+" + rows + "))";
					egw.css(this.print.row_selector, 'display: none');

					// No scrollbar in print view
					jQuery('.egwGridView_scrollarea', this.div).css('overflow-y', 'hidden');
					// Give dialog a chance to close, or it will be in the print
					window.setTimeout(function()
					{
						resolve();
					}, 0);
				}
			}.bind(this);
			const value = {
				content: {
					row_count: Math.min(100, total),
					columns: this.egw().preference(pref, app) || columns_selected,
					orientation: this.egw().preference(pref + '_orientation', app) == "landscape"
				},
				modifications: {
					autoRefresh: {
						disabled: true
					},
					columns: {
						columns: columns,
					}
				}
			};
			await this._create_print_dialog.call(this, value, callback).updateComplete;
		});
	}

	/**
	 * Create and show the print dialog, which calls the provided callback when
	 * done.  Broken out for overriding if needed.
	 *
	 * @param {Object} value Current settings and preferences, passed to the dialog for
	 *	the template
	 * @param {Object} value.content
	 * @param {Object} value.sel_options
	 *
	 * @param {function(int, Object)} callback - Process the dialog response,
	 *  format things according to the specified orientation and fetch any needed
	 *  rows.
	 *
	 */
	_create_print_dialog(value, callback)
	{
		let base_url = this.getInstanceManager().template_base_url;
		if(base_url.substr(base_url.length - 1) == '/') base_url = base_url.slice(0, -1);	// otherwise we generate a url //api/templates, which is wrong
		const tab = this.get_tab_info();
		// Get title for print dialog from settings or tab, if available
		const title = this.options.settings.label ? this.options.settings.label : (tab ? tab.label : '');
		const dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
			// If you use a template, the second parameter will be the value of the template, as if it were submitted.
			callback: callback,	// return false to prevent dialog closing
			buttons: Et2Dialog.BUTTONS_OK_CANCEL,
			title: this.egw().lang('Print') + ' ' + this.egw().lang(title),
			template: this.egw().link(base_url + '/api/templates/default/nm_print_dialog.xet'),
			value: value
		});
		document.body.appendChild(dialog);

		return dialog;
	}

	/**
	 * Try to clean up the mess we made getting ready for printing
	 * in beforePrint()
	 */
	afterPrint()
	{
		if(!this.div.hasClass('print'))
		{
			return;
		}
		this.div.removeClass('print landscape portrait');
		jQuery(this.print.orientation_style).remove();
		delete this.print.orientation_style;

		// Put scrollbar back
		jQuery('.egwGridView_scrollarea', this.div).css('overflow-y', '');

		// Correct size of grid, and trigger resize to fix it
		this.controller._grid.setScrollHeight(this.print.old_height);
		delete this.print.old_height;

		// Remove CSS rule hiding extra rows
		if(this.print.row_selector)
		{
			egw.css(this.print.row_selector, '');
			delete this.print.row_selector;
		}

		// Restore columns
		let pref : string | object | boolean = [];
		const app = this.getInstanceManager().app;
		if(this.options.settings.columnselection_pref.indexOf('nextmatch') == 0)
		{
			pref = egw.preference(this.options.settings.columnselection_pref, app);
		}
		else
		{
			// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
			pref = egw.preference("nextmatch-" + this.options.settings.columnselection_pref, app);
		}
		if(pref)
		{
			if(typeof pref === 'string') pref = (<string>pref).split(',');
			// @ts-ignore
			this.set_columns(pref, app);
		}
		this.dynheight.outerNode.css('max-width', 'inherit');
		this.resize();
	}
}

et2_register_widget(et2_nextmatch, ["nextmatch"]);

/**
 * Standard nextmatch header bar, containing filters, search, record count, letter filters, etc.
 *
 * Unable to use an existing template for this because parent (nm) doesn't, and template widget doesn't
 * actually load templates from the server.
 * @augments et2_DOMWidget
 */
export class et2_nextmatch_header_bar extends et2_DOMWidget implements et2_INextmatchHeader
{
	static readonly _attributes : any = {
		"filter_label": {
			"name": "Filter label",
			"type": "string",
			"description": "Label for filter",
			"default": "",
			"translate": true
		},
		"filter_help": {
			"name": "Filter help",
			"type": "string",
			"description": "Help message for filter",
			"default": "",
			"translate": true
		},
		"filter": {
			"name": "Filter value",
			"type": "any",
			"description": "Current value for filter",
			"default": ""
		},
		"no_filter": {
			"name": "No filter",
			"type": "boolean",
			"description": "Remove filter",
			"default": false
		}
	};
	headers : { id : string }[] | et2_widget[];
	et2_searchbox : Et2Searchbox;
	private favorites : et2_DOMWidget;  // Actually favorite

	private nextmatch : et2_nextmatch;
	div : JQuery;
	private update_in_progress : boolean;

	header_div : JQuery;
	private header_row : JQuery;
	private filter_div : JQuery;
	private row_div : JQuery;
	private fav_span : JQuery;
	private toggle_header : JQuery;
	lettersearch : JQuery;

	private delete_action : JQuery;
	private action_header : JQuery;

	private search_box : JQuery;
	private category : Et2Select | Et2SelectCategory;
	private filter : Et2Select;
	private filter2 : Et2Select;
	private right_div : JQuery;
	private count : JQuery;
	private count_total : JQuery;

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @param _child
	 */
	constructor(_parent : et2_nextmatch, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, [_parent, _parent.options.settings], ClassWithAttributes.extendAttributes(et2_nextmatch_header_bar._attributes, _child || {}));
		this.nextmatch = _parent;
		this.div = jQuery(document.createElement("div"))
			.addClass("nextmatch_header");
		this._createHeader();

		// Flag to avoid loops while updating filters
		this.update_in_progress = false;
	}

	destroy()
	{
		this.nextmatch = null;

		super.destroy();
		this.div = null;
	}

	setNextmatch(nextmatch)
	{
		const create_once = (this.nextmatch == null);
		this.nextmatch = nextmatch;
		if(create_once)
		{
			this._createHeader();
		}

		// Bind row count
		this.nextmatch.dataview.grid.setInvalidateCallback(function()
		{
			this.count_total.text(this.nextmatch.dataview.grid.getTotalCount() + "");
		}, this);
	}

	/**
	 * Actions are handled by the controller, so ignore these
	 *
	 * @param {object} actions
	 */
	set_actions(actions : object[])
	{
	}

	_createHeader()
	{

		let button;
		const self = this;
		const nm_div = this.nextmatch.getDOMNode();
		const settings = this.nextmatch.options.settings;

		this.div.prependTo(nm_div);

		// Left & Right (& row) headers
		this.headers = [
			{id: this.nextmatch.options.header_left},
			{id: this.nextmatch.options.header_right},
			{id: this.nextmatch.options.header_row}
		];

		// The rest of the header
		this.header_div = this.row_div = jQuery(document.createElement("div"))
			.addClass("nextmatch_header_row")
			.appendTo(this.div);
		this.filter_div = jQuery(document.createElement("div"))
			.addClass('filtersContainer')
			.appendTo(this.row_div);

		// Search
		this.search_box = jQuery(document.createElement("div"))
			.addClass('search')
			.prependTo(egwIsMobile() ? this.nextmatch.getDOMNode() : this.row_div)

		// searchbox widget options
		const searchbox_options = {
			id: "search",
			overlay: (typeof settings.searchbox != 'undefined' && typeof settings.searchbox.overlay != 'undefined') ? settings.searchbox.overlay : false,
			onchange: function()
			{
				if(this.value !== self.nextmatch.activeFilters.search)
				{
					self.nextmatch.applyFilters({search: this.get_value()});
				}
			},
			value: settings.search || '',
			fix: !egwIsMobile(),
			placeholder: egw.lang("Search")
		};
		// searchbox widget
		this.et2_searchbox = <Et2Searchbox>loadWebComponent('et2-searchbox', searchbox_options, this);

		// Set activeFilters to current value
		this.nextmatch.activeFilters.search = settings.search || '';

		this.et2_searchbox.set_value(settings.search || '');
		jQuery(this.et2_searchbox.getInputNode()).attr("aria-label", egw.lang("search"));
		/**
		 *  Mobile theme specific part for nm header
		 *  nm header has very different behaivior for mobile theme and basically
		 *  it has its own markup separately from nm header in normal templates.
		 */
		if(egwIsMobile())
		{
			this.search_box.addClass('nm-mob-header');
			jQuery(this.div).css({display: 'inline-block'}).addClass('nm_header_hide');

			//indicates appname in header
			jQuery(document.createElement('div'))
				.addClass('nm_appname_header')
				.text(egw.lang(egw.app_name()))
				.appendTo(this.search_box);

			this.delete_action = jQuery(document.createElement('div'))
				.addClass('nm_delete_action')
				.prependTo(this.search_box);
			// toggle header
			// add new button
			this.fav_span = jQuery(document.createElement('div'))
				.addClass('nm_favorites_div')
				.prependTo(this.search_box);
			// toggle header menu
			this.toggle_header = jQuery(document.createElement('button'))
				.addClass('nm_toggle_header')
				.click(function()
				{
					jQuery(self.div).toggleClass('nm_header_hide');
					jQuery(this).toggleClass('nm_toggle_header_on');
					window.setTimeout(function()
					{
						self.nextmatch.resize();
					}, 800);
				})
				.prependTo(this.search_box);
			// Context menu
			this.action_header = jQuery(document.createElement('button'))
				.addClass('nm_action_header')
				.hide()
				.click(function(e)
				{
					// @ts-ignore
					jQuery('tr.selected', self.nextmatch.getDOMNode()).trigger({
						type: 'contextmenu',
						which: 3,
						originalEvent: e
					});
				})
				.prependTo(this.search_box);
		}

		// Add category
		if(!settings.no_cat)
		{
			if(typeof settings.cat_id_label == 'undefined') settings.cat_id_label = '';
			this.category = this._build_select('cat_id', settings.cat_is_select ?
														 'et2-select' : 'et2-select-cat', settings.cat_id, settings.cat_is_select !== true, {
				multiple: false,
				tags: true,
				class: "select-cat",
				value_class: settings.cat_id_class
			});
		}

		// Filter 1
		if(!settings.no_filter)
		{
			this.filter = this._build_select('filter', 'et2-select', settings.filter, settings.filter_no_lang);
		}

		// Filter 2
		if(!settings.no_filter2)
		{
			this.filter2 = this._build_select('filter2', 'et2-select', settings.filter2,
				settings.filter2_no_lang, {
					multiple: false,
					tags: settings.filter2_tags,
					class: "select-cat",
					value_class: settings.filter2_class
				});
		}

		// Other stuff
		this.right_div = jQuery(document.createElement("div"))
			.addClass('header_row_right').appendTo(this.row_div);

		// Record count
		this.count = jQuery(document.createElement("span"))
			.addClass("header_count ui-corner-all");

		// Need to figure out how to update this as grid scrolls
		// this.count.append("? - ? ").append(egw.lang("of")).append(" ");
		this.count_total = jQuery(document.createElement("span"))
			.appendTo(this.count)
			.text(settings.total + "");
		this.count.appendTo(this.row_div);

		// Favorites
		this._setup_favorites(settings['favorites']);

		// Export
		if(typeof settings.csv_fields != "undefined" && settings.csv_fields != false)
		{
			let definition = settings.csv_fields;
			if(settings.csv_fields === true)
			{
				definition = egw.preference('nextmatch-export-definition', this.nextmatch.egw().app_name());
			}
			let button = <et2_button>et2_createWidget("buttononly", {
				id: "export",
				"statustext": "Export",
				image: "download",
				"background_image": true
			}, this);
			jQuery(button.getDOMNode())
				.click(this.nextmatch, function(event)
				{
					// @ts-ignore
					egw_openWindowCentered2(egw.link('/index.php', {
						'menuaction': 'importexport.importexport_export_ui.export_dialog',
						'appname': event.data.egw().getAppName(),
						'definition': definition
					}), '_blank', 850, 440, 'yes');
				});
		}

		// Another place to customize nextmatch
		this.header_row = jQuery(document.createElement("div"))
			.addClass('header_row').appendTo(this.right_div);

		// Letter search
		const current_letter = this.nextmatch.options.settings.searchletter ?
							   this.nextmatch.options.settings.searchletter :
							   (this.nextmatch.activeFilters ? this.nextmatch.activeFilters.searchletter : false);
		if(this.nextmatch.options.settings.lettersearch || current_letter)
		{
			this.lettersearch = jQuery(document.createElement("table"))
				.addClass('nextmatch_lettersearch')
				.css("width", "100%")
				.appendTo(this.div);
			const tbody = jQuery(document.createElement("tbody")).appendTo(this.lettersearch);
			const row = jQuery(document.createElement("tr")).appendTo(tbody);

			// Capitals, A-Z
			const letters = this.egw().lang('ABCDEFGHIJKLMNOPQRSTUVWXYZ').split('');
			for(let i in letters)
			{
				button = jQuery(document.createElement("td"))
					.addClass("lettersearch")
					.appendTo(row)
					.attr("id", letters[i])
					.text(letters[i]);
				if(letters[i] == current_letter) button.addClass("lettersearch_active");
			}
			button = jQuery(document.createElement("td"))
				.addClass("lettersearch")
				.appendTo(row)
				.attr("id", "")
				.text(egw.lang("all"));
			if(!current_letter) button.addClass("lettersearch_active");

			this.lettersearch.click(this.nextmatch, function(event)
			{
				// this is the lettersearch table
				jQuery("td", this).removeClass("lettersearch_active");
				jQuery(event.target).addClass("lettersearch_active");
				event.data.applyFilters({searchletter: event.target.id || false});
			});
			// Set activeFilters to current value
			this.nextmatch.activeFilters.searchletter = current_letter;
		}
		// Apply letter search preference
		const lettersearch_preference = "nextmatch-" + this.nextmatch.options.settings.columnselection_pref + "-lettersearch";
		if(this.lettersearch && !egw.preference(lettersearch_preference, this.nextmatch.egw().app_name()))
		{
			this.lettersearch.hide();
		}
	}


	/**
	 * Build & bind to a sub-template into the header
	 *
	 * @param {string} location One of left, right, or row
	 * @param {string} template_name Name of the template to load into the location
	 */
	_build_header(location : "left" | "right" | "row", template_name : string)
	{
		const id = location == "left" ? 0 : (location == "right" ? 1 : 2);
		const existing = this.headers[id];
		// @ts-ignore
		if(existing && existing._type)
		{
			if(existing.id == template_name) return;
			(<et2_widget><unknown>existing).destroy();
			this.headers[id] = null;
		}
		if(!template_name) return;

		// Load the template
		const self = this;
		const header = <et2_template>et2_createWidget("template", {"id": template_name}, this);
		this.headers[id] = header;
		const deferred = [];
		header.loadingFinished(deferred);

		// Wait until all child widgets are loaded, then bind
		Promise.all(deferred).then(() =>
		{
			// fix order in DOM by reattaching templates in correct position
			switch(id)
			{
				case 0:	// header_left: prepend
					jQuery(header.getDOMNode()).prependTo(self.header_div);
					break;
				case 1:	// header_right: before favorites and count
					window.setTimeout(() =>
						jQuery(header.getDOMNode()).prependTo(self.header_div.find('div.header_row_right')));
					break;
				case 2:	// header_row: after search
					window.setTimeout(function()
					{	// otherwise we might end up after filters
						jQuery(header.getDOMNode()).insertAfter(self.header_div.find('div.search'));
					}, 1);
					break;
			}
			self._bindHeaderInput(header);
		});
	}

	/**
	 * Build the selectbox filters in the header bar
	 * Sets value, options, labels, and change handlers
	 *
	 * @param {string} name
	 * @param {string} type
	 * @param {string} value
	 * @param {string} lang
	 * @param {object} extra
	 */
	_build_select(name : string, type : string, value : string, lang : string | boolean, extra? : object) : Et2Select
	{
		const widget_options = jQuery.extend({
			"id": name,
			"label": this.nextmatch.options.settings[name + "_label"],
			"no_lang": lang,
			"disabled": this.nextmatch.options['no_' + name]
		}, extra);

		// Set select options
		// Check in content for options-<name>
		const mgr = this.nextmatch.getArrayMgr("content");
		let options = false;
		// Sometimes legacy stuff puts it in here
		options = mgr.getEntry('rows[sel_options][' + name + ']');

		// Maybe in a row, and options got stuck in ${row} instead of top level
		const row_stuck = ['${row}', '{$row}'];
		for(let i = 0; !options && i < row_stuck.length; i++)
		{
			let row_id = '';
			if((!options || options.length == 0) && (
				// perspectiveData.row in nm, data["${row}"] in an auto-repeat grid
				this.nextmatch.getArrayMgr("sel_options").perspectiveData.row || this.nextmatch.getArrayMgr("sel_options").data[row_stuck[i]]))
			{
				row_id = name.replace(/[0-9]+/, row_stuck[i]);
				options = this.nextmatch.getArrayMgr("sel_options").getEntry(row_id);
				if(!options)
				{
					row_id = row_stuck[i] + "[" + name + "]";
					options = this.nextmatch.getArrayMgr("sel_options").getEntry(row_id);
				}
			}
			if(options)
			{
				this.egw().debug('warn', 'Nextmatch filter options in a weird place - "%s".  Should be in sel_options[%s].', row_id, name);
			}
		}
		// Legacy: Add in 'All' option for cat_id, if not provided.
		if(name == 'cat_id' && (options == null || options != null && (typeof options[''] == 'undefined' && typeof options[0] != 'undefined' && options[0].value != ''))
			// Not mail, since it needs to be different
			&& !['mail'].includes(this.getInstanceManager().app))
		{
			widget_options.empty_label = this.egw().lang('All categories');
		}

		// Create widget
		const select = <Et2Select>loadWebComponent(type, widget_options, this);

		if(options)
		{
			select.select_options = options;
		}
		if(select.disabled)
		{
			// Don't just disable, hide completely
			select.classList.add("hideme");
		}

		// Set value
		select.set_value(value);

		// Set activeFilters to current value
		this.nextmatch.activeFilters[select.id] = select.value;

		// Tell framework to ignore, or it will reset it to ''/empty when it does loadingFinished()
		//select.attributes.select_options.ignore = true;

		if(this.nextmatch.options.settings[name + "_onchange"])
		{
			// Get the onchange function string
			let onchange = this.nextmatch.options.settings[name + "_onchange"];

			// Real submits cause all sorts of problems
			if(onchange.match(/this\.form\.submit/))
			{
				this.egw().debug("warn", "%s tries to submit form, which is not allowed.  Filter changes automatically refresh data with no reload.", name);
				onchange = onchange.replace(/this\.form\.submit\([^)]*\);?/, 'return true;');
			}

			// Connect it to the onchange event of the input element - may submit
			select.onchange = et2_compileLegacyJS(onchange, this.nextmatch, select.getInputNode());
			this._bindHeaderInput(select);
		}
		else	// default request changed rows with new filters, previous this.form.submit()
		{
			select.addEventListener("change", () =>
			{
				const set = {};
				set[select.id] = select.getValue();
				this.nextmatch.applyFilters(set);
				select.resetDirty();
			});
		}
		// Sometimes the filter does not display the current value
		// Call sync to try to get it to display
		select.updateComplete.then(async() =>
		{
			await select.updateComplete;
			select.syncItemsFromValue();
		})
		return select;
	}

	/**
	 * Set up the favorites UI control
	 *
	 * @param filters Array|boolean The nextmatch setting for favorites.  Either true, or a list of
	 *	additional fields/settings to add in to the favorite.
	 */
	_setup_favorites(filters)
	{
		if(typeof filters == "undefined" || filters === false)
		{
			// No favorites configured
			return;
		}

		const widget_options = {
			default_pref: "nextmatch-" + this.nextmatch.options.settings.columnselection_pref + "-favorite",
			app: this.getInstanceManager().app,
			filters: filters,
			sidebox_target: 'favorite_sidebox_' + this.getInstanceManager().app
		};
		this.favorites = et2_createWidget('favorites', widget_options, this);

		// Add into header
		jQuery(this.favorites.getDOMNode(this.favorites)).prependTo(egwIsMobile() ? this.search_box.find('.nm_favorites_div').show() : this.right_div);
	}

	/**
	 * Updates all the filter elements in the header
	 *
	 * Does not actually refresh the data, just sets values to match those given.
	 * Called by et2_nextmatch.applyFilters().
	 *
	 * @param filters Array Key => Value pairs of current filters
	 */
	setFilters(filters)
	{

		// Avoid loops cause by change events
		if(this.update_in_progress) return;
		this.update_in_progress = true;

		// Use an array mgr to hande non-simple IDs
		const mgr = new et2_arrayMgr(filters);

		this.iterateOver(function(child)
		{
			// Skip favorites, don't want them in the filter
			if(typeof child.id != "undefined" && child.id.indexOf("favorite") == 0) return;

			let value : string | object = '';
			if(typeof child.set_value != "undefined" && child.id)
			{
				value = mgr.getEntry(child.id);
				if(value == null) value = '';
				/**
				 * Sometimes a filter value is not in current options.  This can
				 * happen in a saved favorite, for example, or if server changes
				 * some filter options, and the order doesn't work out.  The normal behaviour
				 * is to warn & not set it, but for nextmatch we'll just add it
				 * in, and let the server either set it properly, or ignore.
				 */
				if(value && typeof value != 'object' && child.instanceOf(et2_selectbox))
				{
					let found = typeof child.options.select_options[value] != 'undefined';
					// options is array of objects with attribute value&label
					if(jQuery.isArray(child.options.select_options))
					{
						for(let o = 0; o < child.options.select_options.length; ++o)
						{
							if(child.options.select_options[o].value == value)
							{
								found = true;
								break;
							}
						}
					}
					if(!found)
					{
						const old_options = child.options.select_options;
						// Actual label is not available, obviously, or it would be there
						old_options[value] = child.egw().lang("Loading");
						child.set_select_options(old_options);
					}
				}
				child.set_value(value);
			}
			if(typeof child.get_value == "function" && child.id)
			{
				// Put data in the proper place
				let target = this;
				value = child.get_value();

				// Split up indexes
				const indexes = child.id.replace(/&#x5B;/g, '[').split('[');

				for(let i = 0; i < indexes.length; i++)
				{
					indexes[i] = indexes[i].replace(/&#x5D;/g, '').replace(']', '');
					if(i < indexes.length - 1)
					{
						if(typeof target[indexes[i]] == "undefined") target[indexes[i]] = {};
						target = target[indexes[i]];
					}
					else
					{
						target[indexes[i]] = value;
					}
				}
			}
		}, filters);

		// Letter search
		if(this.nextmatch.options.settings.lettersearch)
		{
			jQuery("td", this.lettersearch).removeClass("lettersearch_active");
			jQuery(filters.searchletter ? "td#" + filters.searchletter : "td.lettersearch[id='']", this.lettersearch).addClass("lettersearch_active");

			// Set activeFilters to current value
			filters.searchletter = jQuery("td.lettersearch_active", this.lettersearch).attr("id") || false;
		}

		// Reset flag
		this.update_in_progress = false;
	}

	/**
	 * Help out nextmatch / widget stuff by checking to see if sender is part of header
	 *
	 * @param {et2_widget} _sender
	 */
	getDOMNode(_sender)
	{
		const filters = [this.category, this.filter, this.filter2];
		for(let i = 0; i < filters.length; i++)
		{
			if(_sender == filters[i])
			{
				// Give them the filter div
				return this.filter_div[0];
			}
		}
		if(_sender == this.et2_searchbox)
		{
			return this.search_box[0];
		}
		if(_sender == this.favorites)
		{
			return egwIsMobile() ? this.search_box.find('.nm_favorites_div').show()[0] : this.right_div[0];
		}
		if(_sender.id == 'export')
		{
			return this.right_div[0];
		}

		if(_sender && _sender._type == "template")
		{
			for(let i = 0; i < this.headers.length; i++)
			{
				if(_sender.id == this.headers[i].id && _sender._parent == this)
				{
					return i == 2 ? this.header_row[0] : this.header_div[0];
				}
			}
		}
		return null;
	}

	/**
	 * Bind all the inputs in the header sub-templates to update the filters
	 * on change, and update current filter with the inputs' current values
	 *
	 * @param {et2_template} sub_header
	 */
	_bindHeaderInput(sub_header)
	{
		const header = this;

		const bind_change = function(_widget)
		{
			// Previously set change function
			const widget_change = (window.customElements.get(_widget.localName)) ? _widget.onchange : _widget.change;

			let change = function(_node)
			{
				// Call previously set change function
				const result = widget_change?.call(_widget, _node, header.nextmatch, _widget);

				// Find current value in activeFilters
				let entry = header.nextmatch.activeFilters;
				const path = _widget.getArrayMgr('content').explodeKey(_widget.id);
				let i = 0;
				if(path.length > 0)
				{
					for(; i < path.length; i++)
					{
						entry = entry[path[i]];
					}
				}

				// Update filters, if the value is different and we're not already doing so
				if((result || typeof result === 'undefined') && entry != _widget.getValue() && !header.update_in_progress)
				{
					// Widget will not have an entry in getValues() because nulls
					// are not returned, we remove it from activeFilters
					if(_widget._oldValue == null)
					{
						const path = _widget.getArrayMgr('content').explodeKey(_widget.id);
						if(path.length > 0)
						{
							let entry = header.nextmatch.activeFilters;
							let i = 0;
							for(; i < path.length - 1; i++)
							{
								entry = entry[path[i]];
							}
							delete entry[path[i]];
						}
						header.nextmatch.applyFilters(header.nextmatch.activeFilters);
					}
					else
					{
						// Not null is easy, just get values
						const value = this.getInstanceManager().getValues(sub_header);
						header.nextmatch.applyFilters(value[header.nextmatch.id]);
					}
				}
				// In case this gets bound twice, it's important to return
				return true;
			};

			if(_widget.localName && window.customElements.get(_widget.localName) !== "undefined")
			{
				// We could use addEventListener(), but callbacks expect these arguments
				// @ts-ignore
				_widget.onchange = (ev) =>
				{
					return change.call(this, _widget, _widget);
				};
			}
			else
			{
				_widget.change = change;
			}

			// Set activeFilters to current value
			// Use an array mgr to hande non-simple IDs
			var value = {};
			value[_widget.id] = _widget._oldValue = _widget.getValue();
			const mgr = new et2_arrayMgr(value);
			jQuery.extend(true, this.nextmatch.activeFilters, mgr.data);
		};
		if(sub_header.instanceOf(et2_inputWidget))
		{
			bind_change.call(this, sub_header);
		}
		else
		{
			sub_header.iterateOver(bind_change, this, et2_IInput);
		}
	}
}

et2_register_widget(et2_nextmatch_header_bar, ["nextmatch_header_bar"]);

/**
 * Classes for the nextmatch sortheaders etc.
 *
 * @augments et2_baseWidget
 */
export class et2_nextmatch_header extends et2_baseWidget implements et2_INextmatchHeader
{
	static readonly _attributes : any = {
		"label": {
			"name": "Caption",
			"type": "string",
			"description": "Caption for the nextmatch header",
			"translate": true
		}
	};
	protected labelNode : JQuery;
	protected nextmatch : et2_nextmatch;
	private label : string;

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch_header
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_nextmatch_header._attributes, _child || {}));


		this.labelNode = jQuery(document.createElement("span"));
		this.nextmatch = null;

		this.setDOMNode(this.labelNode[0]);
	}

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch(_nextmatch)
	{
		this.nextmatch = _nextmatch;
	}

	set_label(_value)
	{
		this.label = _value;

		this.labelNode.text(_value);

		// add class if label is empty
		this.labelNode.toggleClass('et2_label_empty', !_value);
	}
}

et2_register_widget(et2_nextmatch_header, ['nextmatch-header']);

/**
 * Extend header to process customfields
 *
 * @augments et2_customfields_list
 *
 * TODO This should extend customfield widget when it's ready, put the whole column in constructor() back too
 */
export class et2_nextmatch_customfields extends et2_customfields_list implements et2_INextmatchHeader
{
	static readonly _attributes : any = {
		'customfields': {
			'name': 'Custom fields',
			'description': 'Auto filled'
		},
		'fields': {
			'name': "Visible fields",
			"description": "Auto filled"
		}
	};
	private nextmatch : et2_nextmatch;

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch_customfields
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_nextmatch_customfields._attributes, _child || {}));

		// Specifically take the whole column
		this.table.css("width", "100%");
	}

	destroy()
	{
		this.nextmatch = null;
		super.destroy();
	}

	transformAttributes(_attrs)
	{
		super.transformAttributes(_attrs);

		// Add in settings that are objects
		if(!_attrs.customfields)
		{
			// Check for custom stuff (unlikely)
			let data = this.getArrayMgr("modifications").getEntry(this.id);
			// Check for global settings
			if(!data) data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~', true);
			for(let key in data)
			{
				if(typeof data[key] === 'object' && !_attrs[key]) _attrs[key] = data[key];
			}
		}
	}

	setNextmatch(_nextmatch)
	{
		this.nextmatch = _nextmatch;
		this.loadFields();
	}

	/**
	 * Build widgets for header - sortable for numeric, text, etc., filterables for selectbox, radio
	 */
	loadFields()
	{
		if(this.nextmatch == null)
		{
			// not ready yet
			return;
		}
		let columnMgr = this.nextmatch.dataview.getColumnMgr();
		let nm_column = null;
		const set_fields = {};
		for(let i = 0; i < this.nextmatch.columns.length; i++)
		{
			// @ts-ignore
			if(this.nextmatch.columns[i].widget == this)
			{
				nm_column = columnMgr.columns[i];
				break;
			}
		}
		if(!nm_column) return;

		// Check for global setting changes (visibility)
		const global_data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~');
		if(global_data != null && global_data.fields) this.options.fields = global_data.fields;

		const apps = egw.link_app_list();
		for(let field_name in this.options.customfields)
		{
			const field = this.options.customfields[field_name];
			const cf_id = et2_customfields_list.PREFIX + field_name;


			if(this.rows[field_name]) continue;

			// Table row
			const row = jQuery(document.createElement("tr"))
				.appendTo(this.tbody);
			const cf = jQuery(document.createElement("td"))
				.appendTo(row);
			this.rows[cf_id] = cf[0];

			// Create widget by type
			let widget = null;
			if(field.type == 'select' || field.type == 'select-account')
			{
				// Remove empty label
				if(field.values && field.values.findIndex && field.values.findIndex((i) => i.value == '') !== -1)
				{
					field.values.splice(field.values.findIndex((i) => i.value == ''), 1);
				}
				widget = loadWebComponent(
					field.type == 'select-account' ? 'et2-nextmatch-header-account' : "et2-nextmatch-header-filter",
					{
						id: cf_id,
						empty_label: field.label,
						select_options: field.values
					},
					this
				);
			}
			else if(apps[field.type])
			{
				widget = loadWebComponent("et2-nextmatch-header-entry", {
					id: cf_id,
					only_app: field.type,
					placeholder: field.label
				}, this);
			}
			else
			{
				widget = et2_createWidget("nextmatch-sortheader", {
					id: cf_id,
					label: field.label
				}, this);
			}

			// If this is already attached, widget needs to be finished explicitly
			if(this.isAttached() && typeof widget.isAttached == "function" && !widget.isAttached())
			{
				widget.loadingFinished();
			}
			// Check for column filter
			if(!jQuery.isEmptyObject(this.options.fields) && (
				this.options.fields[field_name] == false || typeof this.options.fields[field_name] == 'undefined'))
			{
				cf.hide();
			}
			else if(jQuery.isEmptyObject(this.options.fields))
			{
				// If we're showing it make sure it's set, but only after
				set_fields[field_name] = true;
			}
		}
		jQuery.extend(this.options.fields, set_fields);
	}

	/**
	 * Override parent so we can update the nextmatch row too
	 *
	 * @param {array} _fields
	 */
	set_visible(_fields)
	{
		super.set_visible(_fields);

		// Find data row, and do it too
		const self = this;
		if(this.nextmatch)
		{
			this.nextmatch.iterateOver(
				function(widget)
				{
					if(widget == self) return;
					widget.set_visible(_fields);
				}, this, et2_customfields_list
			);
		}
	}

	/**
	 * Provide own column caption (column selection)
	 *
	 * If only one custom field, just use that, otherwise use "custom fields"
	 */
	_genColumnCaption()
	{
		return egw.lang("Custom fields");
	}

	/**
	 * Provide own column naming, including only selected columns - only useful
	 * to nextmatch itself, not for sending server-side
	 */
	_getColumnName()
	{
		let name = this.id;
		const visible = [];
		for(var field_name in this.options.customfields)
		{
			if(jQuery.isEmptyObject(this.options.fields) || this.options.fields[field_name] == true)
			{
				visible.push(et2_customfields_list.PREFIX + field_name);
				jQuery(this.rows[field_name]).show();
			}
			else if(typeof this.rows[field_name] != "undefined")
			{
				jQuery(this.rows[field_name]).hide();
			}
		}

		if(visible.length)
		{
			name += "_" + visible.join("_");
		}
		else if(this.rows)
		{
			// None hidden means all visible
			jQuery(this.rows[field_name]).parent().parent().children().show();
		}

		// Update global custom fields column(s) - widgets will check on their own

		// Check for custom stuff (unlikely)
		let data = this.getArrayMgr("modifications").getEntry(this.id);
		// Check for global settings
		if(!data) data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~', true) || {};
		if(!data.fields) data.fields = {};
		for(let field in this.options.customfields)
		{
			data.fields[field] = (this.options.fields == null || typeof this.options.fields[field] == 'undefined' ? false : this.options.fields[field]);
		}
		return name;
	}
}

et2_register_widget(et2_nextmatch_customfields, ['nextmatch-customfields']);

/**
 * @augments et2_nextmatch_header
 */
// @ts-ignore
export class et2_nextmatch_sortheader extends et2_nextmatch_header implements et2_INextmatchSortable
{
	static readonly _attributes : any = {
		"sortmode": {
			"name": "Sort order",
			"type": "string",
			"description": "Default sort order",
			"translate": false
		}
	};
	public static readonly legacyOptions : ['sortmode'];
	private sortmode : string;

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch_sortheader
	 */
	constructor(_parent?, _attrs? : WidgetConfig, _child? : object)
	{
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_nextmatch_sortheader._attributes, _child || {}));

		this.sortmode = "none";

		this.labelNode.addClass("nextmatch_sortheader none");
	}

	click(_event)
	{
		if(this.nextmatch && super.click(_event))
		{
			// Send default sort mode if not sorted, otherwise send undefined to calculate
			this.nextmatch.sortBy(this.id, this.sortmode == "none" ? !(this.options.sortmode.toUpperCase() == "DESC") : undefined);
			return true;
		}

		return false;
	}

	/**
	 * Wrapper to join up interface * framework
	 *
	 * @param {string} _mode
	 */
	set_sortmode(_mode)
	{
		// Set via nextmatch after setup
		if(this.nextmatch) return;

		this.setSortmode(_mode);
	}

	/**
	 * Function which implements the et2_INextmatchSortable function.
	 *
	 * @param {string} _mode
	 */
	setSortmode(_mode)
	{
		// Remove the last sortmode class and add the new one
		this.labelNode.removeClass(this.sortmode)
			.addClass(_mode);

		this.sortmode = _mode;
	}

}

et2_register_widget(et2_nextmatch_sortheader, ['nextmatch-sortheader']);