/**
 * EGroupware eTemplate2 - JS Nextmatch object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

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
	et2_extension_customfields;

	// Include all nextmatch subclasses
	et2_extension_nextmatch_controller;
	et2_extension_nextmatch_rowProvider;
	et2_extension_nextmatch_dynheight;

	// Include the grid classes
	et2_dataview;

*/

/**
 * Interface all special nextmatch header elements have to implement.
 */
var et2_INextmatchHeader = new Interface({

	/**
	 * The 'setNextmatch' function is called by the parent nextmatch widget
	 * and tells the nextmatch header widgets which widget they should direct
	 * their 'sort', 'search' or 'filter' calls to.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch: function(_nextmatch) {}
});

var et2_INextmatchSortable = new Interface({

	setSortmode: function(_mode) {}

});

/**
 * Class which implements the "nextmatch" XET-Tag
 *
 * @augments et2_DOMWidget
 */
var et2_nextmatch = et2_DOMWidget.extend([et2_IResizeable, et2_IInput, et2_IPrint],
{
	attributes: {
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
		"settings": {
			"name": "Settings",
			"type": "any",
			"description": "The nextmatch settings",
			"default": {}
		}
	},

	legacyOptions: ["template","hide_header","header_left","header_right"],
	createNamespace: true,

	columns: [],

	// Current view, either row or tile.  We store it here as controllers are
	// recreated when the template changes.
	view: 'row',

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.activeFilters = {col_filter:{}};

		// Directly set current col_filters from settings
		jQuery.extend(this.activeFilters.col_filter, this.options.settings.col_filter);

		/*
		Process selected custom fields here, so that the settings are correctly
		set before the row template is parsed
		*/
		var prefs = this._getPreferences();
		var cfs = {};
		for(var i = 0; i < prefs.visible.length; i++)
		{
			if(prefs.visible[i].indexOf(et2_nextmatch_customfields.prototype.prefix) == 0)
			{
				cfs[prefs.visible[i].substr(1)] = !prefs.negated;
			}
		}
		var global_data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~');
		if(typeof global_data == 'object' && global_data != null)
		{
			global_data.fields = cfs;
		}

		this.div = $j(document.createElement("div"))
			.addClass("et2_nextmatch");


		this.header = et2_createWidget("nextmatch_header_bar", {}, this);
		this.innerDiv = $j(document.createElement("div"))
			.appendTo(this.div);

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(this.getInstanceManager().DOMContainer,
				this.innerDiv, 100);

		// Create the outer grid container
		this.dataview = new et2_dataview(this.innerDiv, this.egw());

		// Blank placeholder
		this.blank = $j(document.createElement("div"))
			.appendTo(this.dataview.table);

		// We cannot create the grid controller now, as this depends on the grid
		// instance, which can first be created once we have the columns
		this.controller = null;
		this.rowProvider = null;
	},

	/**
	 * Destroys all
	 */
	destroy: function() {
		// Stop autorefresh
		if(this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			this._autorefresh_timer = null;
		}
		// Unbind handler used for toggling autorefresh
		$j(this.getInstanceManager().DOMContainer.parentNode).off('show.et2_nextmatch');
		$j(this.getInstanceManager().DOMContainer.parentNode).off('hide.et2_nextmatch');

		// Free the grid components
		this.dataview.free();
		this.rowProvider.free();
		this.controller.free();
		this.dynheight.free();

		this._super.apply(this, arguments);
	},

	/**
	 * Loads the nextmatch settings
	 *
	 * @param {object} _attrs
	 */
	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		if (this.id)
		{
			var entry = this.getArrayMgr("content").data;
			_attrs["settings"] = {};

			if (entry)
			{
				_attrs["settings"] = entry;

				// Make sure there's an action var parameter
				if(_attrs["settings"]["actions"] && !_attrs.settings["action_var"])
				{
					_attrs.settings.action_var = "action";
				}

				// Merge settings mess into attributes
				for(var attr in this.attributes)
				{
					if(_attrs.settings[attr])
					{
						_attrs[attr] = _attrs.settings[attr];
						delete _attrs.settings[attr];
					}
				}
			}
		}
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);

		// Register handler for dropped files, if possible
		if(this.options.settings.row_id)
		{
			// Appname should be first part of the template name
			var split = this.options.template.split('.');
			var appname = split[0];

			// Check link registry
			if(this.egw().link_get_registry(appname))
			{
				var self = this;
				// Register a handler
				$j(this.div)
					.on('dragenter','.egwGridView_grid tr',function(e) {
						// Figure out _which_ row
						var row = self.controller.getRowByNode(this);

						if(!row || !row.uid)
						{
							return false;
						}
						e.stopPropagation(); e.preventDefault();

						// Indicate acceptance
						if(row.controller && row.controller._selectionMgr)
						{
							row.controller._selectionMgr.setFocused(row.uid,true);
						}
						return false;
					})
					.on('dragexit','.egwGridView_grid tr', function(e) {
						self.controller._selectionMgr.setFocused();
					})
					.on('dragover','.egwGridView_grid tr',false).attr("dropzone","copy")

					.on('drop', '.egwGridView_grid tr',function(e) {
						self.handle_drop(e,this);
						return false;
					});
			}
		}
		// stop invalidation in no visible tabs
		$j(this.getInstanceManager().DOMContainer.parentNode).on('hide.et2_nextmatch', jQuery.proxy(function(e) {
			if(this.controller && this.controller._grid)
			{
				this.controller._grid.doInvalidate = false;
			}
		},this));
		$j(this.getInstanceManager().DOMContainer.parentNode).on('show.et2_nextmatch', jQuery.proxy(function(e) {
			if(this.controller && this.controller._grid)
			{
				this.controller._grid.doInvalidate = true;
			}
		},this));

		return true;
	},

	/**
	 * Implements the et2_IResizeable interface - lets the dynheight manager
	 * update the width and height and then update the dataview container.
	 */
	resize: function()
	{
		if (this.dynheight)
		{
			this.dynheight.update(function(_w, _h) {
				this.dataview.resize(_w, _h);
			}, this);
		}
	},

	/**
	 * Sorts the nextmatch widget by the given ID.
	 *
	 * @param {string} _id is the id of the data entry which should be sorted.
	 * @param {boolean} _asc if true, the elements are sorted ascending, otherwise
	 * 	descending. If not set, the sort direction will be determined
	 * 	automatically.
	 * @param {boolean} _update true/undefined: call applyFilters, false: only set sort
	 */
	sortBy: function(_id, _asc, _update) {
		if (typeof _update == "undefined")
		{
			_update = true;
		}

		// Create the "sort" entry in the active filters if it did not exist
		// yet.
		if (typeof this.activeFilters["sort"] == "undefined")
		{
			this.activeFilters["sort"] = {
				"id": null,
				"asc": true
			};
		}

		// Determine the sort direction automatically if it is not set
		if (typeof _asc == "undefined")
		{
			_asc = true;
			if (this.activeFilters["sort"].id == _id)
			{
				_asc = !this.activeFilters["sort"].asc;
			}
		}

		// Set the sortmode display
		this.iterateOver(function(_widget) {
			_widget.setSortmode((_widget.id == _id) ? (_asc ? "asc": "desc") : "none");
		}, this, et2_INextmatchSortable);

		if (_update)
		{
			this.applyFilters({sort: { id: _id, asc: _asc}});
		}
		else
		{
			// Update the entry in the activeFilters object
			this.activeFilters["sort"] = {
				"id": _id,
				"asc": _asc
			};
		}
	},

	/**
	 * Removes the sort entry from the active filters object and thus returns to
	 * the natural sort order.
	 */
	resetSort: function() {
		// Check whether the nextmatch widget is currently sorted
		if (typeof this.activeFilters["sort"] != "undefined")
		{
			// Reset the sortmode
			this.iterateOver(function(_widget) {
				_widget.setSortmode("none");
			}, this, et2_INextmatchSortable);

			// Delete the "sort" filter entry
			this.applyFilters({sort: undefined});
		}
	},

	/**
	 * Apply current or modified filters on NM widget (updating rows accordingly)
	 *
	 * @param _set filter(s) to set eg. { filter: '' } to reset filter in NM header
	 */
	applyFilters: function(_set) {
		var changed = false;
		var keep_selection = false;

		// Avoid loops cause by change events
		if(this.update_in_progress) return;
		this.update_in_progress = true;

		// Cleared explicitly
		if(typeof _set != 'undefined' && jQuery.isEmptyObject(_set))
		{
			changed = true;
			this.activeFilters = {};
		}
		if(typeof this.activeFilters == "undefined")
		{
			this.activeFilters = {col_filter: {}};
		}
		if(typeof this.activeFilters.col_filter == "undefined")
		{
			this.activeFilters.col_filter = {};
		}

		if (typeof _set == 'object')
		{
			for(var s in _set)
			{
				if (s == 'col_filter')
				{
					// allow apps setState() to reset all col_filter by using undefined or null for it
					// they can not pass {} for _set / state.state, if they need to set something
					if (_set.col_filter === undefined || _set.col_filter === null)
					{
						this.activeFilters.col_filter = {};
						changed = true;
					}
					else
					{
						for(var c in _set.col_filter)
						{
							if (this.activeFilters.col_filter[c] !== _set.col_filter[c])
							{
								if (_set.col_filter[c])
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
				else if (s === 'selected')
				{
					changed = true;
					keep_selection = true;
					this.controller._selectionMgr.resetSelection();
					for(var i in _set.selected)
					{
						this.controller._selectionMgr.setSelected(_set.selected[i].indexOf('::') > 0 ? _set.selected[i] : this.controller.dataStorePrefix + '::'+_set.selected[i],true);
					}
					delete _set.selected;
				}
				else if (this.activeFilters[s] !== _set[s])
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
		}

		// Update the filters in the grid controller
		this.controller.setFilters(this.activeFilters);

		// Update the header
		this.header.setFilters(this.activeFilters);

		// Update any column filters
		this.iterateOver(function(column) {
			// Skip favorites - it implements et2_INextmatchHeader, but we don't want it in the filter
			if(typeof column.id != "undefined" && column.id.indexOf('favorite') == 0) return;

			if(typeof column.set_value != "undefined" && column.id)
			{
				column.set_value(typeof this[column.id] == "undefined" || this[column.id] == null ? "" : this[column.id]);
			}
			if (column.id && typeof column.get_value == "function")
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
				var app = this.getInstanceManager().app;
				if(window.app[app] && window.app[app].highlight_favorite)
				{
					window.app[app].highlight_favorite();
				}
			}
		}
		
		this.update_in_progress = false;
	},

	/**
	 * Refresh given rows for specified change
	 *
	 * Change type parameters allows for quicker refresh then complete server side reload:
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload
	 *
	 * @param {string[]|string} _row_ids rows to refresh
	 * @param {?string} _type "update", "edit", "delete" or "add"
	 *
	 * @see jsapi.egw_refresh()
	 * @fires refresh from the widget itself
	 */
	refresh: function(_row_ids, _type) {
		// Framework trying to refresh, but nextmatch not fully initialized
		if(this.controller === null || !this.div)
		{
			return;
		}
		if (!this.div.is(':visible'))	// run refresh, once we become visible again
		{
			$j(this.getInstanceManager().DOMContainer.parentNode).one('show.et2_nextmatch',
				// Important to use anonymous function instead of just 'this.refresh' because
				// of the parameters passed
				jQuery.proxy(function() {this.refresh();},this)
			);
			return;
		}
		if (typeof _type == 'undefined') _type = 'edit';
		if (typeof _row_ids == 'string' || typeof _row_ids == 'number') _row_ids = [_row_ids];
		if (typeof _row_ids == "undefined" || _row_ids === null)
		{
			this.applyFilters();

			// Trigger an event so app code can act on it
			$j(this).triggerHandler("refresh",[this]);

			return;
		}

		if(_type == "delete")
		{
			// Record current & next index
			var uid = _row_ids[0].toString().indexOf(this.controller.dataStorePrefix) == 0 ? _row_ids[0] : this.controller.dataStorePrefix + "::" + _row_ids[0];
			var entry = this.controller._selectionMgr._getRegisteredRowsEntry(uid);
			var next = (entry.ao?entry.ao.getNext(_row_ids.length):null);
			if(next == null || !next.id || next.id == uid)
			{
				// No next, select previous
				next = (entry.ao?entry.ao.getPrevious(1):null);
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
			if(next && next.id)
			{
				this.controller._selectionMgr.setSelected(next.id,true);
				this.controller._selectionMgr.setFocused(next.id,true);
			}

			// Update the count
			var total = this.dataview.grid._total - _row_ids.length;
			// This will remove the last row!
			// That's OK, because grid adds one in this.controller.deleteRow()
			this.dataview.grid.setTotalCount(total);
			// Re-enable automatic updating
			this.dataview.grid.doInvalidate = true;
			this.dataview.grid.invalidate();
		}

		id_loop:
		for(var i = 0; i < _row_ids.length; i++)
		{
			var uid = _row_ids[i].toString().indexOf(this.controller.dataStorePrefix) == 0 ? _row_ids[i] : this.controller.dataStorePrefix + "::" + _row_ids[i];
			switch(_type)
			{
				case "update":
					if(!this.egw().dataRefreshUID(uid))
					{
						// Could not update just that row
						this.applyFilters();
						break id_loop;
					}
					break;
				case "delete":
					// Handled above, more code to execute after loop
					break;
				case "edit":
				case "add":
				default:
					// Trigger refresh
					this.applyFilters();
					break id_loop;
			}
		}
		// Trigger an event so app code can act on it
		$j(this).triggerHandler("refresh",[this,_row_ids,_type]);
	},

	/**
	 * Gets the selection
	 *
	 * @return Object { ids: [UIDs], inverted: boolean}
	 */
	getSelection: function() {
		var selected = this.controller && this.controller._selectionMgr ? this.controller._selectionMgr.getSelected() : null;
		if(typeof selected == "object" && selected != null)
		{
			return selected;
		}
		return {ids:[],all:false};
	},

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
	onselect: function(action,senders) {
		// Execute the JS code connected to the event handler
		if (typeof this.options.onselect == 'function')
		{
			return this.options.onselect.call(this, this.getSelection().ids, this);
		}
	},

	/**
	 * Generates the column caption for the given column widget
	 *
	 * @param {et2_widget} _widget
	 */
	_genColumnCaption: function(_widget) {
		var result = null;

		if(typeof _widget._genColumnCaption == "function") return _widget._genColumnCaption();

		_widget.iterateOver(function(_widget) {
			var label = (_widget.options.label ? _widget.options.label : _widget.options.empty_label);
			if (!label) return;	// skip empty, undefined or null labels
			if (!result)
			{
				result = label;
			}
			else
			{
				result += ", " + label;
			}
		}, this, et2_INextmatchHeader);

		return result;
	},

	/**
	 * Generates the column name (internal) for the given column widget
	 * Used in preferences to refer to the columns by name instead of position
	 *
	 * See _getColumnCaption() for human fiendly captions
	 *
	 * @param {et2_widget} _widget
	 */
	_getColumnName: function(_widget) {
		if(typeof _widget._getColumnName == 'function') return _widget._getColumnName();

		var name = _widget.id;
		var child_names = [];
		var children = _widget.getChildren();
		for(var i = 0; i < children.length; i++) {
			if(children[i].id) child_names.push(children[i].id);
		}

		var colName =  name + (name != "" && child_names.length > 0 ? "_" : "") + child_names.join("_");
		if(colName == "") {
			this.egw().debug("info", "Unable to generate nm column name for ", _widget);
		}
		return colName;
	},


	/**
	 * Retrieve the user's preferences for this nextmatch merged with defaults
	 * Column display, column size, etc.
	 */
	_getPreferences: function() {
		// Read preference or default for column visibility
		var negated = false;
		var columnPreference = "";
		if(this.options.settings.default_cols)
		{
			negated = this.options.settings.default_cols[0] == "!";
			columnPreference = negated ? this.options.settings.default_cols.substring(1) : this.options.settings.default_cols;
		}
		if(this.options.settings.selectcols)
		{
			columnPreference = this.options.settings.selectcols;
			negated = false;
		}
		if(!this.options.settings.columnselection_pref)
		{
			// Set preference name so changes are saved
			this.options.settings.columnselection_pref = this.options.template;
		}

		var app = '';
		if(this.options.settings.columnselection_pref) {
			var pref = {};
			var list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
			if(this.options.settings.columnselection_pref.indexOf('nextmatch') == 0)
			{
				app = list[0].substring('nextmatch'.length+1);
				pref = egw.preference(this.options.settings.columnselection_pref, app);
			}
			else
			{
				app = list[0];
				// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
				pref = egw.preference("nextmatch-"+this.options.settings.columnselection_pref, app);
			}
			if(pref)
			{
				negated = (pref[0] == "!");
				columnPreference = negated ? pref.substring(1) : pref;
			}
		}

		// If no column preference or default set, use all columns
		if(typeof columnPreference =="string" && columnPreference.length == 0)
		{
			columnDisplay = {};
			negated = true;
		}

		var columnDisplay = typeof columnPreference === "string"
				? et2_csvSplit(columnPreference,null,",") : columnPreference;

		// Adjusted column sizes
		var size = {};
		if(this.options.settings.columnselection_pref && app)
		{
			var size_pref = this.options.settings.columnselection_pref +"-size";

			// If columnselection pref is missing prefix, add it in
			if(size_pref.indexOf('nextmatch') == -1)
			{
				size_pref = 'nextmatch-'+size_pref;
			}
			size = this.egw().preference(size_pref, app);
		}
		if(!size) size = {};
		return {
			visible: columnDisplay,
			visible_negated: negated,
			size: size
		};
	},

	/**
	 * Apply stored user preferences to discovered columns
	 *
	 * @param {array} _row
	 * @param {array} _colData
	 */
	_applyUserPreferences: function(_row, _colData) {
		var prefs = this._getPreferences();
		var columnDisplay = prefs.visible;
		var size = prefs.size;
		var negated = prefs.visible_negated;
		var colName = '';

		// Add in display preferences
		if(columnDisplay && columnDisplay.length > 0)
		{
			RowLoop:
			for(var i = 0; i < _row.length; i++)
			{
				colName = '';
				if(_row[i].disabled)
				{
					_colData[i].disabled = true;
					continue;
				}

				// Customfields needs special processing
				if(_row[i].widget.instanceOf(et2_nextmatch_customfields))
				{
					// Find cf field
					for(var j = 0; j < columnDisplay.length; j++)
					{
						if(columnDisplay[j].indexOf(_row[i].widget.id) == 0) {
							_row[i].widget.options.fields = {};
							for(var k = j; k < columnDisplay.length; k++)
							{
								if(columnDisplay[k].indexOf(_row[i].widget.prefix) == 0)
								{
									_row[i].widget.options.fields[columnDisplay[k].substr(1)] = true;
								}
							}
							// Resets field visibility too
							_row[i].widget._getColumnName();
							_colData[i].disabled = negated || jQuery.isEmptyObject(_row[i].widget.options.fields);
							break;
						}
					}
					// Disable if there are no custom fields
					if(jQuery.isEmptyObject(_row[i].widget.customfields))
					{
						_colData[i].disabled = true;
						continue;
					}
					colName = _row[i].widget.id;
				}
				else
				{
					colName = this._getColumnName(_row[i].widget);
				}
				if(!colName) continue;

				if(size[colName])
				{
					// Make sure percentages stay percentages, and forget any preference otherwise
					if(_colData[i].width.charAt(_colData[i].width.length - 1) == "%")
					{
						_colData[i].width = typeof size[colName] == 'string' && size[colName].charAt(size[colName].length - 1) == "%" ? size[colName] : _colData[i].width;
					}
					else
					{
						_colData[i].width = parseInt(size[colName])+'px';
					}
				}
				for(var j = 0; j < columnDisplay.length; j++)
				{
					if(columnDisplay[j] == colName)
					{
						_colData[i].disabled = negated;

						continue RowLoop;
					}
				}
				_colData[i].disabled = !negated;
			}
		}
	},

	/**
	 * Take current column display settings and store them in this.egw().preferences
	 * for next time
	 */
	_updateUserPreferences: function() {
		var colMgr = this.dataview.getColumnMgr();
		var app = "";
		if(!this.options.settings.columnselection_pref) {
			this.options.settings.columnselection_pref = this.options.template;
		}

		var visibility = colMgr.getColumnVisibilitySet();
		var colDisplay = [];
		var colSize = {};
		var custom_fields = [];

		// visibility is indexed by internal ID, widget is referenced by position, preference needs name
		for(var i = 0; i < colMgr.columns.length; i++)
		{
			var widget = this.columns[i].widget;
			var colName = this._getColumnName(widget);
			if(colName) {
				// Server side wants each cf listed as a seperate column
				if(widget.instanceOf(et2_nextmatch_customfields))
				{
					// Just the ID for server side, not the whole nm name - some apps use it to skip custom fields
					colName = widget.id;
					for(var name in widget.options.fields) {
						 if(widget.options.fields[name]) custom_fields.push(widget.prefix+name);
					}
				}
				if(visibility[colMgr.columns[i].id].visible) colDisplay.push(colName);

				// When saving sizes, only save columns with explicit values, preserving relative vs fixed
				// Others will be left to flex if width changes or more columns are added
				if(colMgr.columns[i].relativeWidth)
				{
					colSize[colName] = (colMgr.columns[i].relativeWidth * 100) + "%";
				}
				else if (colMgr.columns[i].fixedWidth)
				{
					colSize[colName] = colMgr.columns[i].fixedWidth;
				}
			} else if (colMgr.columns[i].fixedWidth || colMgr.columns[i].relativeWidth) {
				this.egw().debug("info", "Could not save column width - no name", colMgr.columns[i].id);
			}
		}

		var list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
		var pref = this.options.settings.columnselection_pref;
		if(pref.indexOf('nextmatch') == 0)
		{
			app = list[0].substring('nextmatch'.length+1);
		}
		else
		{
			app = list[0];
			// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
			pref = "nextmatch-"+this.options.settings.columnselection_pref;
		}

		// Server side wants each cf listed as a seperate column
		jQuery.merge(colDisplay, custom_fields);

		// Update query value, so data source can use visible columns to exclude expensive sub-queries
		var oldCols = this.activeFilters.selectcols ? this.activeFilters.selectcols : [];

		this.activeFilters.selectcols = colDisplay;

		// We don't need to re-query if they've removed a column
		var changed = [];
		ColLoop:
		for(var i = 0; i < colDisplay.length; i++)
		{
			for(var j = 0; j < oldCols.length; j++) {
				 if(colDisplay[i] == oldCols[j]) continue ColLoop;
			}
			changed.push(colDisplay[i]);
		}

		// If a custom field column was added, throw away cache to deal with
		// efficient apps that didn't send all custom fields in the first request
		var cf_added = $j(changed).filter($j(custom_fields)).length > 0;

		// Save visible columns
		// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
		this.egw().set_preference(app, pref, colDisplay.join(","),
			// Use callback after the preference gets set to trigger refresh, in case app
			// isn't looking at selectcols and just uses preference
			cf_added ? jQuery.proxy(function() {if(this.controller) this.controller.update(true);}, this):null
		);

		// Save adjusted column sizes
		this.egw().set_preference(app, pref+"-size", colSize);

		// No significant change (just normal columns shown) and no need to wait,
		// but the grid still needs to be redrawn if a custom field was removed because
		// the cell content changed.  This is a cheaper refresh than the callback,
		// this.controller.update(true)
		if((changed.length || custom_fields.length) && !cf_added) this.applyFilters();
	},

	_parseHeaderRow: function(_row, _colData) {

		// Make sure there's a widget - cols disabled in template can be missing them, and the header really likes to have a widget

		for (var x = 0; x < _row.length; x++)
		{
			if(!_row[x].widget)
			{
				_row[x].widget = et2_createWidget("label");
			}
		}

		// Get column display preference
		this._applyUserPreferences(_row, _colData);

		// Go over the header row and create the column entries
		this.columns = new Array(_row.length);
		var columnData = new Array(_row.length);

		// No action columns in et2
		var remove_action_index = null;

		for (var x = 0; x < _row.length; x++)
		{
			this.columns[x] = jQuery.extend({
				"widget": _row[x].widget
			},_colData[x]);


			columnData[x] = {
				"id": "col_" + x,
				"caption": this._genColumnCaption(_row[x].widget),
				"visibility": (!_colData[x] || _colData[x].disabled) ?
					ET2_COL_VISIBILITY_INVISIBLE : ET2_COL_VISIBILITY_VISIBLE,
				"width": _colData[x] ? _colData[x].width : 0
			};
			if(_colData[x].minWidth)
			{
				columnData[x].minWidth = _colData[x].minWidth;
			}
			if(_colData[x].maxWidth)
			{
				columnData[x].maxWidth = _colData[x].maxWidth;
			}

			// No action columns in et2
			var colName = this._getColumnName(_row[x].widget);
			if(colName == 'actions' || colName == 'legacy_actions' || colName == 'legacy_actions_check_all')
			{
				remove_action_index = x;
				continue;
			}

		}

		// Remove action column
		if(remove_action_index != null)
		{
			this.columns.splice(remove_action_index,remove_action_index);
			columnData.splice(remove_action_index,remove_action_index);
			_colData.splice(remove_action_index,remove_action_index);
		}

		// Create the column manager and update the grid container
		this.dataview.setColumns(columnData);

		for (var x = 0; x < _row.length; x++)
		{
			// Append the widget to this container
			this.addChild(_row[x].widget);
		}

		// Create the nextmatch row provider
		this.rowProvider = new et2_nextmatch_rowProvider(
			this.dataview.rowProvider, this._getSubgrid, this);

		// Register handler to update preferences when column properties are changed
		var self = this;
		this.dataview.onUpdateColumns = function() {
			// Use apply to make sure context is there
			self._updateUserPreferences.apply(self);

			// Allow column widgets a chance to resize
			self.iterateOver(function(widget) {widget.resize();}, self, et2_IResizeable);
		};

		// Register handler for column selection popup, or disable
		if(this.selectPopup)
		{
			this.selectPopup.remove();
			this.selectPopup = null;
		}
		if(this.options.settings.no_columnselection)
		{
			this.dataview.selectColumnsClick = function() {return false;};
			$j('span.selectcols',this.dataview.headTr).hide();
		}
		else
		{
			$j('span.selectcols',this.dataview.headTr).show();
			this.dataview.selectColumnsClick = function(event) {
				self._selectColumnsClick(event);
			};
		}
	},

	_parseDataRow: function(_row, _rowData, _colData) {
		var columnWidgets = new Array(this.columns.length);

		for (var x = 0; x < columnWidgets.length; x++)
		{
			if (typeof _row[x] != "undefined" && _row[x].widget)
			{
				columnWidgets[x] = _row[x].widget;

				// Append the widget to this container
				this.addChild(_row[x].widget);
			}
			else
			{
				columnWidgets[x] = _row[x].widget;
			}
			// Pass along column alignment
			if(_row[x].align)
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

		// Need to trigger empty row the first time
		if(total == 0) this.controller._emptyRow();

		// Set data cache prefix to either provided custom or auto
		if(!this.options.settings.dataStorePrefix)
		{
			// Use jsapi data module to update
			var list = this.options.settings.get_rows.split('.', 2);
			if (list.length < 2) list = this.options.settings.get_rows.split('_');	// support "app_something::method"
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
	},

	_parseGrid: function(_grid) {
		// Search the rows for a header-row - if one is found, parse it
		for (var y = 0; y < _grid.rowData.length; y++)
		{
			// Parse the first row as a header, need header to parse the data rows
			if (_grid.rowData[y]["class"] == "th" || y == 0)
			{
				this._parseHeaderRow(_grid.cells[y], _grid.colData);
			}
			else
			{
				this._parseDataRow(_grid.cells[y], _grid.rowData[y],
						_grid.colData);
			}
		}
		this.dataview.table.resize();
	},

	_getSubgrid: function (_row, _data, _controller) {
		// Fetch the id of the element described by _data, this will be the
		// parent_id of the elements in the subgrid
		var rowId = _data.content[this.options.settings.row_id];

		// Create a new grid with the row as parent and the dataview grid as
		// parent grid
		var grid = new et2_dataview_grid(_row, this.dataview.grid);

		// Create a new controller for the grid
		var controller = new et2_nextmatch_controller(
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
		grid.setDestroyCallback(function () {
			controller.free();
		});

		return grid;
	},

	_getInitialOrder: function (_rows, _rowId) {

		var _order = [];

		// Get the length of the non-numerical rows arra
		var len = 0;
		for (var key in _rows) {
			if (!isNaN(key) && parseInt(key) > len)
				len = parseInt(key);
		}

		// Iterate over the rows
		for (var i = 0; i < len; i++)
		{
			// Get the uid from the data
			var uid = this.egw().appName + '::' + _rows[i][_rowId];

			// Store the data for that uid
			this.egw().dataStoreUID(uid, _rows[i]);

			// Push the uid onto the order array
			_order.push(uid);
		}

		return _order;
	},

	_selectColumnsClick: function(e) {
		var self = this;
		var columnMgr = this.dataview.getColumnMgr();

		// ID for faking letter selection in column selection
		var LETTERS = '~search_letter~';

		var columns = {};
		var columns_selected = [];

		for (var i = 0; i < columnMgr.columns.length; i++)
		{
			var col = columnMgr.columns[i];
			var widget = this.columns[i].widget;

			if(col.caption && col.visibility != ET2_COL_VISIBILITY_ALWAYS_NOSELECT)
			{
				columns[col.id] = col.caption;
				if(col.visibility == ET2_COL_VISIBILITY_VISIBLE) columns_selected.push(col.id);
			}
			// Custom fields get listed separately
			if(widget.instanceOf(et2_nextmatch_customfields))
			{
				if(jQuery.isEmptyObject(widget.customfields))
				{
					// No customfields defined, don't show column
					delete(columns[col.id]);
					continue;
				}
				for(var field_name in widget.customfields)
				{
					columns[widget.prefix+field_name] = " - "+widget.customfields[field_name].label;
					if(widget.options.fields[field_name]) columns_selected.push(et2_customfields_list.prototype.prefix+field_name);
				}
			}
		}

		// Letter search
		if(this.options.settings.lettersearch)
		{
			columns[LETTERS] = egw.lang('Search letter');
			if(this.header.lettersearch.is(':visible')) columns_selected.push(LETTERS);
		}

		// Build the popup
		if(!this.selectPopup)
		{
			var select = et2_createWidget("select", {
				multiple: true,
				rows: 8,
				empty_label:this.egw().lang("select columns"),
				selected_first: false
			}, this);
			select.set_select_options(columns);
			select.set_value(columns_selected);

			var autoRefresh = et2_createWidget("select", {
				"empty_label":"Refresh"
			}, this);
			autoRefresh.set_id("nm_autorefresh");
			autoRefresh.set_select_options({
				// Cause [unknown] problems with mail
				//30: "30 seconds",
				//60: "1 Minute",
				300: "5 Minutes",
				900: "15 Minutes",
				1800: "30 Minutes"
			});
			autoRefresh.set_value(this._get_autorefresh());
			autoRefresh.set_statustext(egw.lang("Automatically refresh list"));

			var defaultCheck = et2_createWidget("select", {"empty_label":"Preference"}, this);
			defaultCheck.set_id('nm_col_preference');
			defaultCheck.set_select_options({
				'default': {label: 'Default',title:'Set these columns as the default'},
				'reset':   {label: 'Reset', title:"Reset all user's column preferences"},
				'force':   {label: 'Force', title:'Force column preference so users cannot change it'}
			});
			defaultCheck.set_value(this.options.settings.columns_forced ? 'force': '');

			var okButton = et2_createWidget("buttononly", {}, this);
			okButton.set_label(this.egw().lang("ok"));
			okButton.onclick = function() {
				// Update visibility
				var visibility = {};
				for (var i = 0; i < columnMgr.columns.length; i++)
				{
					var col = columnMgr.columns[i];
					if(col.caption && col.visibility != ET2_COL_VISIBILITY_ALWAYS_NOSELECT )
					{
						visibility[col.id] = {visible: false};
					}
				}
				var value = select.getValue();

				// Update & remove letter filter
				if(self.header.lettersearch)
				{
					var show_letters = true;
					if(value.indexOf(LETTERS) >= 0)
					{
						value.splice(value.indexOf(LETTERS),1);
					}
					else
					{
						show_letters = false;
					}
					self._set_lettersearch(show_letters);
				}

				var column = 0;
				for(var i = 0; i < value.length; i++)
				{
					// Handle skipped columns
					while(value[i] != "col_"+column && column < columnMgr.columns.length)
					{
						column++;
					}
					if(visibility[value[i]])
					{
						visibility[value[i]].visible = true;
					}
					// Custom fields are listed seperately in column list, but are only 1 column
					if(self.columns[column] && self.columns[column].widget.instanceOf(et2_nextmatch_customfields)) {
						var cf = self.columns[column].widget.options.customfields;
						var visible = self.columns[column].widget.options.fields;

						// Turn off all custom fields
						for(var field_name in cf)
						{
							visible[field_name] = false;
						}
						// Turn on selected custom fields - start from 0 in case they're not in order
						for(var j = 0; j < value.length; j++)
						{
							if(value[j].indexOf(et2_customfields_list.prototype.prefix) != 0) continue;
							visible[value[j].substring(1)] = true;
							i++;
						}
						self.columns[column].widget.set_visible(visible);
					}
				}
				columnMgr.setColumnVisibilitySet(visibility);

				// Hide popup
				self.selectPopup.toggle();

				self.dataview.updateColumns();

				// Auto refresh
				self._set_autorefresh(autoRefresh.get_value());

				// Set default or clear forced
				if(show_letters)
				{
					self.activeFilters.selectcols.push('lettersearch');
				}
				self.getInstanceManager().submit();
				
				self.selectPopup = null;
			};

			var cancelButton = et2_createWidget("buttononly", {}, this);
			cancelButton.set_label(this.egw().lang("cancel"));
			cancelButton.onclick = function() {
				self.selectPopup.toggle();
				self.selectPopup = null;
			};

			this.selectPopup = jQuery(document.createElement("div"))
				.addClass("colselection ui-dialog ui-widget-content")
				.append(select.getDOMNode())
				.append(okButton.getDOMNode())
				.append(cancelButton.getDOMNode())
				.appendTo(this.innerDiv);

			// Add autorefresh
			this.selectPopup.append(autoRefresh.getSurroundings().getDOMNode(autoRefresh.getDOMNode()));

			// Add default checkbox for admins
			var apps = this.egw().user('apps');
			if(apps['admin'])
			{
				this.selectPopup.append(defaultCheck.getSurroundings().getDOMNode(defaultCheck.getDOMNode()));
			}
		}
		else
		{
			this.selectPopup.toggle();
		}
		var t_position = jQuery(e.target).position();
		var s_position = this.div.position();
		this.selectPopup.css("top", t_position.top)
			.css("left", s_position.left + this.div.width() - this.selectPopup.width());
	},

	/**
	 * Set the currently displayed columns, without updating user's preference
	 * 
	 * @param {string[]} column_list List of column names
	 * @param {boolean} trigger_update=false - explicitly trigger an update
	 */
	set_columns: function(column_list, trigger_update)
	{
		var columnMgr = this.dataview.getColumnMgr();
		var visibility = {};

		// Initialize to false
		for (var i = 0; i < columnMgr.columns.length; i++)
		{
			var col = columnMgr.columns[i];
			if(col.caption && col.visibility != ET2_COL_VISIBILITY_ALWAYS_NOSELECT )
			{
				visibility[col.id] = {visible: false};
			}
		}
		for(var i = 0; i < this.columns.length; i++)
		{

			var widget = this.columns[i].widget;
			var colName = this._getColumnName(widget);
			if(column_list.indexOf(colName) !== -1)
			{
				visibility[columnMgr.columns[i].id].visible = true;
			}
			// Custom fields are listed seperately in column list, but are only 1 column
			if(widget && widget.instanceOf(et2_nextmatch_customfields)) {

				// Just the ID for server side, not the whole nm name - some apps use it to skip custom fields
				colName = widget.id;
				if(column_list.indexOf(colName) !== -1)
				{
					visibility[columnMgr.columns[i].id].visible = true;
				}

				var cf = this.columns[i].widget.options.customfields;
				var visible = this.columns[i].widget.options.fields;
				
				// Turn off all custom fields
				for(var field_name in cf)
				{
					visible[field_name] = false;
				}
				// Turn on selected custom fields - start from 0 in case they're not in order
				for(var j = 0; j < column_list.length; j++)
				{
					if(column_list[j].indexOf(et2_customfields_list.prototype.prefix) != 0) continue;
					visible[column_list[j].substring(1)] = true;
				}
				widget.set_visible(visible);
			}
		}
		columnMgr.setColumnVisibilitySet(visibility);

		// We don't want to update user's preference, so directly update
		this.dataview._updateColumns();

		// Allow column widgets a chance to resize
		this.iterateOver(function(widget) {widget.resize();}, this, et2_IResizeable);
	},

	/**
	 * Set the letter search preference, and update the UI
	 *
	 * @param {boolean} letters_on
	 */
	_set_lettersearch: function(letters_on) {
		if(letters_on)
		{
			this.header.lettersearch.show();
		}
		else
		{
			this.header.lettersearch.hide();
		}
		var lettersearch_preference = "nextmatch-" + this.options.settings.columnselection_pref + "-lettersearch";
		this.egw().set_preference(this.egw().getAppName(),lettersearch_preference,letters_on);
	},

	/**
	 * Set the auto-refresh time period, and starts the timer if not started
	 *
	 * @param time int Refresh period, in seconds
	 */
	_set_autorefresh: function(time) {
		// Store preference
		var refresh_preference = "nextmatch-" + this.options.settings.columnselection_pref + "-autorefresh";
		var app = this.options.template.split(".");
		if(this._get_autorefresh() != time)
		{
			this.egw().set_preference(app[0],refresh_preference,time);
		}

		// Start / update timer
		if (this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			delete this._autorefresh_timer;
		}
		if(time > 0)
		{
			this._autorefresh_timer = setInterval(jQuery.proxy(this.controller.update, this.controller), time * 1000);

			// Bind to tab show/hide events, so that we don't bother refreshing in the background
			$j(this.getInstanceManager().DOMContainer.parentNode).on('hide.et2_nextmatch', jQuery.proxy(function(e) {
				// Stop
				window.clearInterval(this._autorefresh_timer);
				$j(e.target).off(e);

				// If the autorefresh time is up, bind once to trigger a refresh
				// (if needed) when tab is activated again
				this._autorefresh_timer = setTimeout(jQuery.proxy(function() {
					// Check in case it was stopped / destroyed since
					if(!this._autorefresh_timer || !this.getInstanceManager()) return;

					$j(this.getInstanceManager().DOMContainer.parentNode).one('show.et2_nextmatch',
						// Important to use anonymous function instead of just 'this.refresh' because
						// of the parameters passed
						jQuery.proxy(function() {this.refresh();},this)
					);
				},this), time*1000);
			},this));
			$j(this.getInstanceManager().DOMContainer.parentNode).on('show.et2_nextmatch', jQuery.proxy(function(e) {
				// Start normal autorefresh timer again
				this._set_autorefresh(this._get_autorefresh());
				$j(e.target).off(e);
			},this));
		}
	},

	/**
	 * Get the auto-refresh timer
	 *
	 * @return int Refresh period, in secods
	 */
	_get_autorefresh: function() {
		var refresh_preference = "nextmatch-" + this.options.settings.columnselection_pref + "-autorefresh";
		var app = this.options.template.split(".");
		return this.egw().preference(refresh_preference,app[0]);
	},

	/**
	 * When the template attribute is set, the nextmatch widget tries to load
	 * that template and to fetch the grid which is inside of it. It then calls
	 *
	 * @param {string} _value template name
	 */
	set_template: function(_value) {
		if(this.template)
		{
			// Stop early to prevent unneeded processing, and prevent infinite
			// loops if the server changes the template in get_rows
			if(this.template == _value)
			{
				return;
			}

			// Free the grid components - they'll be re-created as the template is processed
			this.dataview.free();
			this.rowProvider.free();
			this.controller.free();

			// Free any children from previous template
			// They may get left behind because of how detached nodes are processed
			// We don't use iterateOver because it checks sub-children
			for(var i = this._children.length-1; i >=0 ; i--)
			{
				var _node = this._children[i];
				if(_node != this.header) {
					this.removeChild(_node);
					_node.destroy();
				}
			}

			// Clear this setting if it's the same as the template, or
			// the columns will not be loaded
			if(this.template == this.options.settings.columnselection_pref)
			{
				this.options.settings.columnselection_pref = _value;
			}
			this.dataview = new et2_dataview(this.innerDiv, this.egw());
		}

		// Create the template
		var template = et2_createWidget("template", {"id": _value}, this);

		if (!template)
		{
			this.egw().debug("error", "Error while loading definition template for " +
				"nextmatch widget.",_value);
			return;
		}

		// Deferred parse function - template might not be fully loaded
		var parse = function(template)
		{
			// Keep the name of the template, as we'll free up the widget after parsing
			this.template = _value;

			// Fetch the grid element and parse it
			var definitionGrid = template.getChildren()[0];
			if (definitionGrid && definitionGrid instanceof et2_grid)
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
			setTimeout(function() {
				template.free();
			},1);

			// Call the "setNextmatch" function of all registered
			// INextmatchHeader widgets.  This updates this.activeFilters.col_filters according
			// to what's in the template.
			this.iterateOver(function (_node) {
				_node.setNextmatch(this);
			}, this, et2_INextmatchHeader);

			// Set filters to current values
			this.controller.setFilters(this.activeFilters);

			// If no data was sent from the server, and num_rows is 0, the nm will be empty.
			// This triggers a cache check.
			if(!this.options.settings.num_rows)
			{
				this.controller.update();
			}

			// Load the default sort order
			if (this.options.settings.order && this.options.settings.sort)
			{
				this.sortBy(this.options.settings.order,
					this.options.settings.sort == "ASC", false);
			}

			// Start auto-refresh
			this._set_autorefresh(this._get_autorefresh());
		};

		// Template might not be loaded yet, defer parsing
		var promise = [];
		template.loadingFinished(promise);

		// Wait until template (& children) are done
		jQuery.when.apply(null, promise).done(
			jQuery.proxy(function() {
				parse.call(this, template);
				this.dynheight.initialized = false;
				this.resize();
			}, this)
		);
	},

	// Some accessors to match conventions
	set_hide_header: function(hide) {
		(hide ? this.header.div.hide() : this.header.div.show());
	},

	set_header_left: function(template) {
		this.header._build_header("left",template);
	},
	set_header_right: function(template) {
		this.header._build_header("right",template);
	},
	set_header_row: function(template) {
		this.header._build_header("row",template);
	},
	set_no_filter: function(bool, filter_name) {
		if(typeof filter_name == 'undefined')
		{
			filter_name = 'filter';
		}
		this.options['no_'+filter_name] = bool;

		var filter = this.header[filter_name];
		if(filter)
		{
			filter.set_disabled(bool);
		}
		else if (bool)
		{
			filter = this.header._build_select(filter_name, 'select',
				this.settings[filter_name], this.settings[filter_name+'_no_lang']);
		}
	},
	set_no_filter2: function(bool) {
		this.set_no_filter(bool,'filter2');
	},

	/**
	 * If nextmatch starts disabled, it will need a resize after being shown
	 * to get all the sizing correct.  Override the parent to add the resize
	 * when enabling.
	 *
	 * @param {boolean} _value
	 */
	set_disabled: function(_value)
	{
		var previous = this.disabled;
		this._super.apply(this, arguments);

		if(previous && !_value)
		{
			this.resize();
		}
	},

	/**
	 * Actions are handled by the controller, so ignore these during init.
	 *
	 * @param {object} actions
	 */
	set_actions: function(actions) {
		if(actions != this.options.actions && this.controller != null && this.controller._actionManager)
		{
			for(var i = this.controller._actionManager.children.length - 1; i >= 0; i--)
			{
				this.controller._actionManager.children[i].remove();
			}
			this.options.actions = actions;
			this.options.settings.action_links = this.controller._actionLinks = this._get_action_links(actions);

			this.controller._initActions(actions);
		}
	},

	/**
	 * Switch view between row and tile.
	 * This should be followed by a call to change the template to match, which
	 * will cause a reload of the grid using the new settings.
	 *
	 * @param {string} view Either 'tile' or 'row'
	 */
	set_view: function(view)
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
	},

	/**
	 * Set a different / additional handler for dropped files.
	 *
	 * File dropping doesn't work with the action system, so we handle it in the
	 * nextmatch by linking automatically to the target row.  This allows an additional handler.
	 * It should accept a row UID and a File[], and return a boolean Execute the default (link) action
	 *
	 * @param {String|Function} handler
	 */
	set_onfiledrop: function(handler) {
		this.options.onfiledrop = handler;
	},

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
	handle_drop: function(event, target) {
		// Check to see if we can handle the link
		// First, find the UID
		var row = this.controller.getRowByNode(target);
		var uid = row.uid || null;

		// Get the file information
		var files = [];
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
		if (this.options.onfiledrop && !this.options.onfiledrop.call(this, uid, files))
		{
			return false;
		}
		event.stopPropagation();
		event.preventDefault();

		if(!row || !row.uid) return false;

		// Link the file to the row
		// just use a link widget, it's all already done
		var split = uid.split('::');
		var link_value = {
			to_app: split.shift(),
			to_id: split.join('::')
		};
		// Create widget and mangle to our needs
		var link = et2_createWidget("link-to", {value: link_value}, this);
		link.loadingFinished();
		link.file_upload.set_drop_target(false);

		if(row.row.tr)
		{
			// Ignore most of the UI, just use the status indicators
			var status = $j(document.createElement("div"))
				.addClass('et2_link_to')
				.width(row.row.tr.width())
				.position({my: "left top", at: "left top", of: row.row.tr})
				.append(link.status_span)
				.append(link.file_upload.progress)
				.appendTo(row.row.tr);

			// Bind to link event so we can remove when done
			link.div.on('link.et2_link_to', function(e, linked) {
				if(!linked)
				{
					$j("li.success", link.file_upload.progress)
						.removeClass('success').addClass('validation_error');
				}
				else
				{
					// Update row
					link._parent.refresh(uid,'edit');
				}
				// Fade out nicely
				status.delay(linked ? 1 : 2000)
					.fadeOut(500, function() {
						link.free();
						status.remove();
					});

			});
		}

		// Upload and link - this triggers the upload, which triggers the link, which triggers the cleanup and refresh
		link.file_upload.set_value(files);
	},

	getDOMNode: function(_sender) {
		if (_sender == this)
		{
			return this.div[0];
		}
		if (_sender == this.header)
		{
			return this.header.div[0];
		}
		for (var i = 0; i < this.columns.length; i++)
		{
			if (this.columns[i] && this.columns[i].widget && _sender == this.columns[i].widget)
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
	},

	// Input widget

	/**
	 * Get the current 'value' for the nextmatch
	 */
	getValue: function() {
		var _ids = this.getSelection();

		// Translate the internal uids back to server uids
		var idsArr = _ids.ids;
		for (var i = 0; i < idsArr.length; i++)
		{
			idsArr[i] = idsArr[i].split("::").pop();
		}
		var value = {
			"selected": idsArr
		};
		jQuery.extend(value, this.activeFilters, this.value);
		return value;
	},
	resetDirty: function() {},
	isDirty: function() { return typeof this.value !== 'undefined';},
	isValid: function() { return true;},
	set_value: function(_value)
	{
		this.value = _value;
	},

	// Printing
	/**
	 * Prepare for printing
	 *
	 * We check for un-loaded rows, and ask the user what they want to do about them.
	 * If they want to print them all, we ask the server and print when they're loaded.
	 */
	beforePrint: function() {
		// Add the class, if needed
		this.div.addClass('print');

		// Trigger resize, so we can fit on a page
		this.dynheight.outerNode.css('max-width',this.div.css('max-width'));
		this.resize();
		// Reset height to auto (after width resize) so there's no restrictions
		this.dynheight.innerNode.css('height', 'auto');

		// Check for rows that aren't loaded yet, or lots of rows
		var range = this.controller._grid.getIndexRange();
		this.old_height = this.controller._grid._scrollHeight;
		var loaded_count = range.bottom - range.top +1;
		var total = this.controller._grid.getTotalCount();
		if(loaded_count != total ||
			this.controller._grid.getTotalCount() > 100)
		{
			// Defer the printing
			var defer = jQuery.Deferred();

			// Something not in the grid, lets ask
			et2_dialog.show_prompt(jQuery.proxy(function(button, value) {
					if(button == 'dialog[cancel]') {
						// Give dialog a chance to close, or it will be in the print
						window.setTimeout(function() {defer.reject();}, 0);
						return;
					}
					value = parseInt(value);
					if(value > total)
					{
						value = total;
					}

					// If they want the whole thing, treat it as all
					if(button == 'dialog[ok]' && value == this.controller._grid.getTotalCount())
					{
						button = 'dialog[all]';
						// Add the class, gives more reliable sizing
						this.div.addClass('print');
						// Show it all
						$j('.egwGridView_scrollarea',this.div).css('height','auto');
					}
					// We need more rows
					if(button == 'dialog[all]' || value > loaded_count)
					{
						var count = 0;
						var fetchedCount = 0;
						var cancel = false;
						var nm = this;
						var dialog = et2_dialog.show_dialog(
							// Abort the long task if they canceled the data load
							function() {count = total; cancel=true;window.setTimeout(function() {defer.reject();},0)},
							egw.lang('Loading'), egw.lang('please wait...'),{},[
								{"button_id": et2_dialog.CANCEL_BUTTON,"text": 'cancel',id: 'dialog[cancel]',image: 'cancel'}
							]
						);

						// dataFetch() is asyncronous, so all these requests just get fired off...
						// 200 rows chosen arbitrarily to reduce requests.
						do {
							var ctx = {
								"self": this.controller,
								"start": count,
								"count": Math.min(value,200),
								"lastModification": this.controller._lastModification
							};
							if(nm.controller.dataStorePrefix)
							{
								ctx.prefix = nm.controller.dataStorePrefix;
							}
							nm.controller.dataFetch({start:count, num_rows: Math.min(value,200)}, function(data)  {
								// Keep track
								if(data && data.order)
								{
									fetchedCount += data.order.length;
								}
								nm.controller._fetchCallback.apply(this, arguments);

								if(fetchedCount >= value)
								{
									if(cancel)
									{
										dialog.destroy();
										defer.reject();
										return;
									}
									// Use CSS to hide all but the requested rows
									// Prevents us from showing more than requested, if actual height was less than average
									nm.print_row_selector = ".egwGridView_grid > tbody > tr:not(:nth-child(-n+"+value+"))";
									egw.css(nm.print_row_selector, 'display: none');

									// No scrollbar in print view
									$j('.egwGridView_scrollarea',this.div).css('overflow-y','hidden');
									// Show it all
									$j('.egwGridView_scrollarea',this.div).css('height','auto');

									// Grid needs to redraw before it can be printed, so wait
									window.setTimeout(jQuery.proxy(function() {
										dialog.destroy();

										// Should be OK to print now
										defer.resolve();
									},nm),ET2_GRID_INVALIDATE_TIMEOUT);

								}

							},ctx);
							count += 200;
						} while (count < value)
						nm.controller._grid.setScrollHeight(nm.controller._grid.getAverageHeight() * (value+1));
					}
					else
					{
						// Don't need more rows, limit to requested and finish

						// Show it all
						$j('.egwGridView_scrollarea',this.div).css('height','auto');

						// Use CSS to hide all but the requested rows
						// Prevents us from showing more than requested, if actual height was less than average
						this.print_row_selector = ".egwGridView_grid > tbody > tr:not(:nth-child(-n+"+value+"))";
						egw.css(this.print_row_selector, 'display: none');

						// No scrollbar in print view
						$j('.egwGridView_scrollarea',this.div).css('overflow-y','hidden');
						// Give dialog a chance to close, or it will be in the print
						window.setTimeout(function() {defer.resolve();}, 0);
					}
				},this),
				egw.lang('How many rows to print'), egw.lang('Print'),
				Math.min(100, total),
				[
					{"button_id": 1,"text": egw.lang('Ok'), id: 'dialog[ok]', image: 'check', "default":true},
					// Nice for small lists, kills server for large lists
					//{"button_id": 2,"text": egw.lang('All'), id: 'dialog[all]', image: ''},
					{"button_id": 0,"text": egw.lang('Cancel'), id: 'dialog[cancel]', image: 'cancel'},
				]
			);
			return defer;
		}
		else
		{
			// Show all rows
			this.dynheight.innerNode.css('height', 'auto');
			$j('.egwGridView_scrollarea',this.div).css('height','auto');
		}
		// Don't return anything, just work normally
	},

	/**
	 * Try to clean up the mess we made getting ready for printing
	 * in beforePrint()
	 */
	afterPrint: function() {

		this.div.removeClass('print');

		// Put scrollbar back
		$j('.egwGridView_scrollarea',this.div).css('overflow-y','');

		// Correct size of grid, and trigger resize to fix it
		this.controller._grid.setScrollHeight(this.old_height);
		delete this.old_height;

		// Remove CSS rule hiding extra rows
		if(this.print_row_selector)
		{
			egw.css(this.print_row_selector, false);
			delete this.print_row_selector;
		}

		this.dynheight.outerNode.css('max-width','inherit');
		this.resize();
	}
});
et2_register_widget(et2_nextmatch, ["nextmatch"]);

/**
 * Standard nextmatch header bar, containing filters, search, record count, letter filters, etc.
 *
 * Unable to use an existing template for this because parent (nm) doesn't, and template widget doesn't
 * actually load templates from the server.
 * @augments et2_DOMWidget
 */
var et2_nextmatch_header_bar = et2_DOMWidget.extend(et2_INextmatchHeader,
{
	attributes: {
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
	},
	headers: [],
	header_div: [],

	/**
	 * Constructor
	 *
	 * @param nextmatch
	 * @param nm_div
	 * @memberOf et2_nextmatch_header_bar
	 */
	init: function(nextmatch, nm_div) {
		this._super.apply(this, [nextmatch,nextmatch.options.settings]);
		this.nextmatch = nextmatch;
		this.div = jQuery(document.createElement("div"))
			.addClass("nextmatch_header");
		this._createHeader();

		// Flag to avoid loops while updating filters
		this.update_in_progress = false;
	},

	destroy: function() {
		this.nextmatch = null;

		this._super.apply(this, arguments);
		this.div = null;
	},

	setNextmatch: function(nextmatch) {
		var create_once = (this.nextmatch == null);
		this.nextmatch = nextmatch;
		if(create_once)
		{
			this._createHeader();
		}

		// Bind row count
		this.nextmatch.dataview.grid.setInvalidateCallback(function () {
			this.count_total.text(this.nextmatch.dataview.grid.getTotalCount() + "");
		}, this);
	},

	/**
	 * Actions are handled by the controller, so ignore these
	 *
	 * @param {object} actions
	 */
	set_actions: function(actions) {},

	_createHeader: function() {

		var self = this;
		var nm_div = this.nextmatch.div;
		var settings = this.nextmatch.options.settings;
		
		this.div.prependTo(nm_div);
		
		// Left & Right (& row) headers
		this.header_div = jQuery(document.createElement("div")).addClass("ui-helper-clearfix ui-helper-reset").prependTo(this.div);
		this.headers = [
			{id:this.nextmatch.options.header_left},
			{id:this.nextmatch.options.header_right},
			{id:this.nextmatch.options.header_row}
		];
		
		// The rest of the header
		this.row_div = jQuery(document.createElement("div"))
			.addClass("nextmatch_header_row")
			.appendTo(this.div);

		// Search
		this.search_box = jQuery(document.createElement("div"))
			.prependTo(egwIsMobile()?this.nextmatch.div:this.row_div)
			.addClass("search");
		this.search = et2_createWidget("textbox", {"id":"search","blur":egw.lang("search")}, this);
		this.search.input.attr("type", "search");
		this.search.input.val(settings.search)
			.on("keypress", function(event) {
				if(event.which == 13)
				{
					event.preventDefault();
					self.getInstanceManager().autocomplete_fixer();
					// Use a timeout to make sure we get the autocomplete value,
					// if one was chosen, instead of what was actually typed.
					// Chrome doesn't need this, but FF does.
					window.setTimeout(function() {
						self.nextmatch.applyFilters({search: self.search.getValue()});
					},0);
				}
			});
		// Firefox treats search differently.  Add in the clear button.
		if(navigator.userAgent.toLowerCase().indexOf('firefox') > -1)
		{
			this.search.input.on("keyup",
				function(event) {
					// Insert the button, if needed
					if(self.search.input.next('span').length == 0)
					{
						self.search.input.after(
							$j('<span class="ui-icon"></span>').click(
								function() {self.search.input.val('');self.search.input.focus();}
							)
						);
					}
					if(event.which == 27) // Escape
					{
						// Excape clears search
						self.search.input.val('');
					}
					self.search.input.next('span').toggle(self.search.input.val() != '');
				}
			);
		}
		
		// Set activeFilters to current value
		this.nextmatch.activeFilters.search = settings.search;
		
		/**
		 *  Mobile theme specific part for nm header 
		 *  nm header has very different behaivior for mobile theme and basically
		 *  it has its own markup separately from nm header in normal templates.
		 */
		if (egwIsMobile())
		{
			jQuery(this.div).css({display:'inline-block'}).addClass('nm_header_hide');
			// toggle header 
			// add new button
			this.fav_span = jQuery(document.createElement('div'))
					.addClass('nm_favorites_div')
					.prependTo(this.search_box);
			// toggle header menu
			this.toggle_header = jQuery(document.createElement('button'))
					.addClass('nm_toggle_header')
					.click(function(){
						jQuery(self.div).slideToggle('fast');
						jQuery(self.div).removeClass('nm_header_hide');
						jQuery(this).toggleClass('nm_toggle_header_on');
						window.setTimeout(function(){self.nextmatch.resize()},800);
					})
					.prependTo(this.search_box);
			// Context menu 
			this.action_header = jQuery(document.createElement('button'))
					.addClass('nm_action_header')
					.click (function(e){
						jQuery('tr.selected',self.nextmatch.div).trigger({type:'taphold',which:3,originalEvent:e});
					})
					.prependTo(this.search_box);
			
			
			this.search_button = et2_createWidget("button", {id: "search_button","background":"pixelegg/images/topmenu_items/mobile/search_white.png"}, this);
			this.search.input.on ('focus blur', function (e){
				self.search_box.toggleClass('searchOn');
			});
		}
		else
		{
			this.search_button = et2_createWidget("button", {id: "search_button","label":">"}, this);
			this.search_button.onclick = function(event) {
				self.nextmatch.applyFilters({search: self.search.getValue()});
			};
		}
	
		// Add category
		if(!settings.no_cat) {
			settings.cat_id_label = egw.lang("Category");
			this.category = this._build_select('cat_id', 'select-cat', settings.cat_id, true);
		}

		// Filter 1
		if(!settings.no_filter) {
			this.filter = this._build_select('filter', 'select', settings.filter, settings.filter_no_lang);
		}

		// Filter 2
		if(!settings.no_filter2) {
			this.filter2 = this._build_select('filter2', 'select', settings.filter2, settings.filter2_no_lang);
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
		this.count.prependTo(this.right_div);

		// Favorites
		this._setup_favorites(settings['favorites']);

		// Export
		if(typeof settings.csv_fields != "undefined" && settings.csv_fields != false)
		{
			var definition = settings.csv_fields;
			if(settings.csv_fields === true)
			{
				definition = egw.preference('nextmatch-export-definition', this.nextmatch.egw().getAppName());
			}
			var button = et2_createWidget("buttononly", {id: "export", "label": "Export", image:"phpgwapi/filesave"}, this);
			jQuery(button.getDOMNode())
				.click(this.nextmatch, function(event) {
					egw_openWindowCentered2( egw.link('/index.php', {
						'menuaction':	'importexport.importexport_export_ui.export_dialog',
						'appname':	event.data.egw().getAppName(),
						'definition':	definition
					}), '_blank', 850, 440, 'yes');
				});
		}

		// Another place to customize nextmatch
		this.header_row = jQuery(document.createElement("div"))
			.addClass('header_row').appendTo(this.right_div);

		// Letter search
		var current_letter = this.nextmatch.options.settings.searchletter ?
			this.nextmatch.options.settings.searchletter :
			(this.nextmatch.activeFilters ? this.nextmatch.activeFilters.searchletter : false);
		if(this.nextmatch.options.settings.lettersearch || current_letter)
		{
			this.lettersearch = jQuery(document.createElement("table"))
				.css("width", "100%")
				.appendTo(this.div);
			var tbody = jQuery(document.createElement("tbody")).appendTo(this.lettersearch);
			var row = jQuery(document.createElement("tr")).appendTo(tbody);

			// Capitals, A-Z
			for(var i = 65; i <= 90; i++) {
				var button = jQuery(document.createElement("td"))
					.addClass("lettersearch")
					.appendTo(row)
					.attr("id", String.fromCharCode(i))
					.text(String.fromCharCode(i));
				if(String.fromCharCode(i) == current_letter) button.addClass("lettersearch_active");
			}
			button = jQuery(document.createElement("td"))
				.addClass("lettersearch")
				.appendTo(row)
				.attr("id", "")
				.text(egw.lang("all"));
			if(!current_letter) button.addClass("lettersearch_active");

			this.lettersearch.click(this.nextmatch, function(event) {
				// this is the lettersearch table
				jQuery("td",this).removeClass("lettersearch_active");
				jQuery(event.target).addClass("lettersearch_active");
				event.data.applyFilters({searchletter: event.target.id || false});
			});
			// Set activeFilters to current value
			this.nextmatch.activeFilters.searchletter = current_letter;
		}
		// Apply letter search preference
		var lettersearch_preference = "nextmatch-" + this.nextmatch.options.settings.columnselection_pref + "-lettersearch";
		if(this.lettersearch && !egw.preference(lettersearch_preference,this.nextmatch.egw().getAppName()))
		{
			this.lettersearch.hide();
		}
	},


	/**
	 * Build & bind to a sub-template into the header
	 *
	 * @param {string} location One of left, right, or row
	 * @param {string} template_name Name of the template to load into the location
	 */
	_build_header: function(location, template_name)
	{
		var id = location == "left" ? 0 : (location == "right" ? 1 : 2);
		var existing = this.headers[id];
		if(existing && existing._type)
		{
			if(existing.id == template_name) return;
			existing.free();
			this.headers[id] = '';
		}

		// Load the template
		var self = this;
		var header = et2_createWidget("template", {"id": template_name}, this);
		jQuery(header.getDOMNode()).addClass(location == "left" ? "et2_hbox_left": location=="right" ?"et2_hbox_right":'').addClass("nm_header");
		this.headers[id] = header;
		var deferred = [];
		header.loadingFinished(deferred);

		// Wait until all child widgets are loaded, then bind
		jQuery.when.apply(jQuery,deferred).then(function() {
			self._bindHeaderInput(header);
		});
	},

	/**
	 * Build the selectbox filters in the header bar
	 * Sets value, options, labels, and change handlers
	 *
	 * @param {string} name
	 * @param {string} type
	 * @param {string} value
	 * @param {string} lang
	 */
	_build_select: function(name, type, value, lang) {
		var widget_options = {
			"id": name,
			"label": this.nextmatch.options.settings[name+"_label"],
			"no_lang": lang,
			"disabled": this.nextmatch.options['no_'+name]
		};

		// Set select options
		// Check in content for options-<name>
		var mgr = this.nextmatch.getArrayMgr("content");
		var options = mgr.getEntry("options-" + name);
		// Look in sel_options
		if(!options) options = this.nextmatch.getArrayMgr("sel_options").getEntry(name);
		// Check parent sel_options, because those are usually global and don't get passed down
		if(!options) options = this.nextmatch.getArrayMgr("sel_options").parentMgr.getEntry(name);
		// Sometimes legacy stuff puts it in here
		if(!options) options = mgr.getEntry('rows[sel_options]['+name+']');

		// Maybe in a row, and options got stuck in ${row} instead of top level
		var row_stuck = ['${row}','{$row}'];
		for(var i = 0; !options && i < row_stuck.length; i++)
		{
			var row_id = '';
			if((!options || options.length == 0) && (
				// perspectiveData.row in nm, data["${row}"] in an auto-repeat grid
				this.nextmatch.getArrayMgr("sel_options").perspectiveData.row || this.nextmatch.getArrayMgr("sel_options").data[row_stuck[i]]))
			{
				var row_id = name.replace(/[0-9]+/,row_stuck[i]);
				options = this.nextmatch.getArrayMgr("sel_options").getEntry(row_id);
				if(!options)
				{
					row_id = row_stuck[i] + "["+name+"]";
					options = this.nextmatch.getArrayMgr("sel_options").getEntry(row_id);
				}
			}
			if(options)
			{
				this.egw().debug('warn', 'Nextmatch filter options in a weird place - "%s".  Should be in sel_options[%s].',row_id,name);
			}
		}

		// Legacy: Add in 'All' option for cat_id, if not provided.
		if(name == 'cat_id' && options != null && (typeof options[''] == 'undefined' && typeof options[0] != 'undefined' && options[0].value != ''))
		{
			widget_options.empty_label = this.egw().lang('All');
		}

		// Create widget
		var select = et2_createWidget(type, widget_options, this);

		if(options) select.set_select_options(options);

		// Set value
		select.set_value(value);

		// Set activeFilters to current value
		this.nextmatch.activeFilters[select.id] = select.get_value();

		// Set onChange
		var input = select.input;

		// Tell framework to ignore, or it will reset it to ''/empty when it does loadingFinished()
		select.attributes.select_options.ignore = true;

		if (this.nextmatch.options.settings[name+"_onchange"])
		{
			// Make sure to get the new value for filtering
			input.change(this.nextmatch, function(event) {
				var set = {};
				set[name] = select.getValue();
				event.data.applyFilters(set);
			});

			// Get the onchange function string
			var onchange = this.nextmatch.options.settings[name+"_onchange"];

			// Real submits cause all sorts of problems
			if(onchange.match(/this\.form\.submit/))
			{
				this.egw().debug("warn","%s tries to submit form, which is not allowed.  Filter changes automatically refresh data with no reload.",name);
				onchange = onchange.replace(/this\.form\.submit\([^)]*\);?/,'return true;');
			}

			// Connect it to the onchange event of the input element - may submit
			input.change(this.nextmatch, et2_compileLegacyJS(onchange, this.nextmatch, select.getInputNode()));
		}
		else	// default request changed rows with new filters, previous this.form.submit()
		{
			input.change(this.nextmatch, function(event) {
				var set = {};
				set[name] = select.getValue();
				event.data.applyFilters(set);
			});
		}
		return select;
	},

	/**
	 * Set up the favorites UI control
	 *
	 * @param filters Array|boolean The nextmatch setting for favorites.  Either true, or a list of
	 *	additional fields/settings to add in to the favorite.
	 */
	_setup_favorites: function(filters) {
		if(typeof filters == "undefined" || filters === false)
		{
			// No favorites configured
			return;
		}

		var list = et2_csvSplit(this.options.get_rows, 2, ".");
		var widget_options = {
			default_pref: "nextmatch-" + this.nextmatch.options.settings.columnselection_pref + "-favorite",
			app: list[0],
			filters: filters,
			sidebox_target:'favorite_sidebox_'+list[0]
		};
		this.favorites = et2_createWidget('favorites', widget_options, this);

		// Add into header
		$j(this.favorites.getDOMNode(this.favorites)).prependTo(egwIsMobile()?this.search_box.find('.nm_favorites_div'):this.right_div);
	},

	/**
	 * Updates all the filter elements in the header
	 *
	 * Does not actually refresh the data, just sets values to match those given.
	 * Called by et2_nextmatch.applyFilters().
	 *
	 * @param filters Array Key => Value pairs of current filters
	 */
	setFilters: function(filters) {

		// Avoid loops cause by change events
		if(this.update_in_progress) return;
		this.update_in_progress = true;

		// Use an array mgr to hande non-simple IDs
		var mgr = new et2_arrayMgr(filters);

		this.iterateOver(function(child) {
			// Skip favorites, don't want them in the filter
			if(typeof child.id != "undefined" && child.id.indexOf("favorite") == 0) return;

			var value = '';
			if(typeof child.set_value != "undefined" && child.id)
			{
				value = mgr.getEntry(child.id);
				if (value == null) value = '';
				/**
				 * Sometimes a filter value is not in current options.  This can
				 * happen in a saved favorite, for example, or if server changes
				 * some filter options, and the order doesn't work out.  The normal behaviour
				 * is to warn & not set it, but for nextmatch we'll just add it
				 * in, and let the server either set it properly, or ignore.
				 */
				if(value && typeof value != 'object' && child.instanceOf(et2_selectbox))
				{
					var found = typeof child.options.select_options[value] != 'undefined';
					// options is array of objects with attribute value&label
					if (jQuery.isArray(child.options.select_options))
					{
						for(var o=0; o < child.options.select_options.length; ++o)
						{
							if (child.options.select_options[o].value == value)
							{
								found = true;
								break;
							}
						}
					}
					if (!found)
					{
						var old_options = child.options.select_options;
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
				var target = this;
				var value = child.get_value();

				// Split up indexes
				var indexes = child.id.replace(/&#x5B;/g,'[').split('[');

				for(var i = 0; i < indexes.length; i++)
				{
					indexes[i] = indexes[i].replace(/&#x5D;/g,'').replace(']','');
					if (i < indexes.length-1)
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
			jQuery("td",this.lettersearch).removeClass("lettersearch_active");
			$j(filters.searchletter ? "td#"+filters.searchletter : "td.lettersearch[id='']").addClass("lettersearch_active");

			// Set activeFilters to current value
			filters.searchletter = $j("td.lettersearch_active").attr("id");
		}

		// Reset flag
		this.update_in_progress = false;
	},

	/**
	 * Help out nextmatch / widget stuff by checking to see if sender is part of header
	 *
	 * @param {et2_widget} _sender
	 */
	getDOMNode: function(_sender) {
		var filters = [this.category, this.filter, this.filter2];
		for(var i = 0; i < filters.length; i++)
		{
			if(_sender == filters[i])
			{
				// Give them the row div
				return this.row_div[0];
			}
		}
		if(_sender == this.search || _sender == this.search_button) return this.search_box[0];
		if(_sender.id == 'export') return this.right_div[0];

		if(_sender && _sender._type == "template")
		{
			for(var i = 0; i < this.headers.length; i++)
			{
				if(_sender.id == this.headers[i].id && _sender._parent == this) return i == 2 ? this.header_row[0] : this.header_div[0];
			}
		}
		return null;
	},

	/**
	 * Bind all the inputs in the header sub-templates to update the filters
	 * on change, and update current filter with the inputs' current values
	 *
	 * @param {et2_template} sub_header
	 */
	_bindHeaderInput: function(sub_header) {
		var header = this;

		sub_header.iterateOver(function(_widget){
			// Previously set change function
			var widget_change = _widget.change;

			var change = function(_node) {
				// Call previously set change function
				var result = widget_change.call(_widget,_node);

				// Update filters, if we're not already doing so
				if(result && _widget.isDirty() && !header.update_in_progress) {
					// Update dirty
					_widget._oldValue = _widget.getValue();

					// Widget will not have an entry in getValues() because nulls
					// are not returned, we remove it from activeFilters
					if(_widget._oldValue == null)
					{
						var path = _widget.getArrayMgr('content').explodeKey(_widget.id);
						if(path.length > 0)
						{
							var entry = header.nextmatch.activeFilters;
							var i = 0;
							for(; i < path.length-1; i++)
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
						var value = this.getInstanceManager().getValues(sub_header);
						header.nextmatch.applyFilters(value[header.nextmatch.id]);
					}
				}
				// In case this gets bound twice, it's important to return
				return true;
			};

			_widget.change = change;

			// Set activeFilters to current value
			// Use an array mgr to hande non-simple IDs
			var value = {};
			value[_widget.id] = _widget._oldValue = _widget.getValue();
			var mgr = new et2_arrayMgr(value);
			jQuery.extend(true, this.nextmatch.activeFilters,mgr.data);
		}, this, et2_inputWidget);
	}
});
et2_register_widget(et2_nextmatch_header_bar, ["nextmatch_header_bar"]);

/**
 * Classes for the nextmatch sortheaders etc.
 *
 * @augments et2_baseWidget
 */
var et2_nextmatch_header = et2_baseWidget.extend(et2_INextmatchHeader,
{
	attributes: {
		"label": {
			"name": "Caption",
			"type": "string",
			"description": "Caption for the nextmatch header",
			"translate": true
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch_header
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.labelNode = $j(document.createElement("span"));
		this.nextmatch = null;

		this.setDOMNode(this.labelNode[0]);
	},

	destroy: function() {
		this._super.apply(this, arguments);
	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;
	},

	set_label: function(_value) {
		this.label = _value;

		this.labelNode.text(_value);
	}

});
et2_register_widget(et2_nextmatch_header, ['nextmatch-header']);

/**
 * Extend header to process customfields
 *
 * @augments et2_customfields_list
 */
var et2_nextmatch_customfields = et2_customfields_list.extend(et2_INextmatchHeader,
{
	attributes: {
		'customfields': {
			'name': 'Custom fields',
			'description': 'Auto filled'
		},
		'fields': {
			'name': "Visible fields",
			"description": "Auto filled"
		}
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch_customfields
	 */
	init: function() {
		this.nextmatch = null;
		this._super.apply(this, arguments);

		// Specifically take the whole column
		this.table.css("width", "100%");
	},

	destroy: function() {
		this.nextmatch = null;
		this._super.apply(this, arguments);
	},

	transformAttributes: function(_attrs) {
		this._super.apply(this, arguments);

		// Add in settings that are objects
		if(!_attrs.customfields)
		{
			// Check for custom stuff (unlikely)
			var data = this.getArrayMgr("modifications").getEntry(this.id);
			// Check for global settings
			if(!data) data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~', true);
			for(var key in data)
			{
				if(typeof data[key] === 'object' && ! _attrs[key]) _attrs[key] = data[key];
			}
		}
	},

	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;
		this.loadFields();
	},

	/**
	 * Build widgets for header - sortable for numeric, text, etc., filterables for selectbox, radio
	 */
	loadFields: function() {
		if(this.nextmatch == null)
		{
			// not ready yet
			return;
		}
		var columnMgr = this.nextmatch.dataview.getColumnMgr();
		var nm_column = null;
		var set_fields = {};
		for(var i = 0; i < this.nextmatch.columns.length; i++)
		{
			if(this.nextmatch.columns[i].widget == this)
			{
				nm_column = columnMgr.columns[i];
				break;
			}
		}
		if(!nm_column) return;

		// Check for global setting changes (visibility)
		var global_data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~');
		if(global_data != null && global_data.fields) this.options.fields = global_data.fields;

		var apps = egw.link_app_list();
		for(var field_name in this.options.customfields)
		{
			var field = this.options.customfields[field_name];
			var cf_id = et2_customfields_list.prototype.prefix + field_name;


			if(this.rows[field_name]) continue;

			// Table row
			var row = jQuery(document.createElement("tr"))
					.appendTo(this.tbody);
			var cf = jQuery(document.createElement("td"))
					.appendTo(row);
			this.rows[cf_id] = cf[0];

			// Create widget by type
			var widget = null;
			if(field.type == 'select' || field.type == 'select-account')
			{
				if(field.values && typeof field.values[''] !== 'undefined')
				{
					delete(field.values['']);
				}
				widget = et2_createWidget(
					field.type == 'select-account' ? 'nextmatch-accountfilter' : "nextmatch-filterheader",
					{
						id: cf_id,
						empty_label: field.label,
						select_options: field.values
					},
					this
				);
			}
			else if (apps[field.type])
			{
				widget = et2_createWidget("nextmatch-entryheader", {
					id: cf_id,
					only_app: field.type,
					blur: field.label
				}, this);
			}
			else
			{
				widget = et2_createWidget("nextmatch-sortheader", {
					id: cf_id,
					label: field.label
				}, this);
			}

			// Check for column filter
			if(!jQuery.isEmptyObject(this.options.fields) && (
				this.options.fields[field_name] == false || typeof this.options.fields[field_name] == 'undefined'))
			{
				cf.hide();
			}
			else if (jQuery.isEmptyObject(this.options.fields))
			{
				// If we're showing it make sure it's set, but only after
				set_fields[field_name] = true;
			}
		}
		jQuery.extend(this.options.fields, set_fields);
	},

	/**
	 * Override parent so we can update the nextmatch row too
	 *
	 * @param {array} _fields
	 */
	set_visible: function(_fields) {
		this._super.apply(this, arguments);

		// Find data row, and do it too
		var self = this;
		if(this.nextmatch)
		{
			this.nextmatch.iterateOver(
				function(widget) {
					if(widget == self) return;
					widget.set_visible(_fields);
				}, this, et2_customfields_list
			);
		}
	},

	/**
	 * Provide own column caption (column selection)
	 *
	 * If only one custom field, just use that, otherwise use "custom fields"
	 */
	_genColumnCaption: function() {
		return egw.lang("Custom fields");
	},

	/**
	 * Provide own column naming, including only selected columns - only useful
	 * to nextmatch itself, not for sending server-side
	 */
	_getColumnName: function() {
		var name = this.id;
		var visible = [];
		for(var field_name in this.options.customfields)
		{
			if(jQuery.isEmptyObject(this.options.fields) || this.options.fields[field_name] == true)
			{
				visible.push(et2_customfields_list.prototype.prefix + field_name);
				jQuery(this.rows[field_name]).show();
			}
			else if (typeof this.rows[field_name] != "undefined")
			{
				jQuery(this.rows[field_name]).hide();
			}
		}

		if(visible.length) {
			name  +="_"+ visible.join("_");
		}
		else
		{
			// None hidden means all visible
			jQuery(this.rows[field_name]).parent().parent().children().show();
		}

		// Update global custom fields column(s) - widgets will check on their own

		// Check for custom stuff (unlikely)
		var data = this.getArrayMgr("modifications").getEntry(this.id);
		// Check for global settings
		if(!data) data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~', true) || {};
		if(!data.fields) data.fields = {};
		for(var field in this.options.customfields)
		{
			data.fields[field] = (this.options.fields == null || typeof this.options.fields[field] == 'undefined' ? false : this.options.fields[field]);
		}
		return name;
	}
});
et2_register_widget(et2_nextmatch_customfields, ['nextmatch-customfields']);

/**
 * @augments et2_nextmatch_header
 */
var et2_nextmatch_sortheader = et2_nextmatch_header.extend(et2_INextmatchSortable,
{
	attributes: {
		"sortmode": {
			"name": "Sort order",
			"type": "string",
			"description": "Default sort order",
			"translate": false
		}
	},
	legacyOptions: ['sortmode'],

	/**
	 * Constructor
	 *
	 * @memberOf et2_nextmatch_sortheader
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.sortmode = "none";

		this.labelNode.addClass("nextmatch_sortheader none");
	},

	click: function() {
		if (this.nextmatch && this._super.apply(this, arguments))
		{
			// Send default sort mode if not sorted, otherwise send undefined to calculate
			this.nextmatch.sortBy(this.id, this.sortmode == "none" ? !(this.options.sortmode.toUpperCase() == "DESC") : undefined);
			return true;
		}

		return false;
	},

	/**
	 * Wrapper to join up interface * framework
	 *
	 * @param {string} _mode
	 */
	set_sortmode: function(_mode)
	{
		// Set via nextmatch after setup
		if(this.nextmatch) return;

		this.setSortmode(_mode);
	},

	/**
	 * Function which implements the et2_INextmatchSortable function.
	 *
	 * @param {string} _mode
	 */
	setSortmode: function(_mode) {
		// Remove the last sortmode class and add the new one
		this.labelNode.removeClass(this.sortmode)
			.addClass(_mode);

		this.sortmode = _mode;
	}

});
et2_register_widget(et2_nextmatch_sortheader, ['nextmatch-sortheader']);

/**
 * @augments et2_selectbox
 */
var et2_nextmatch_filterheader = et2_selectbox.extend([et2_INextmatchHeader, et2_IResizeable],
{
	/**
	 * Override to add change handler
	 *
	 * @memberOf et2_nextmatch_filterheader
	 */
	createInputWidget: function() {
		// Make sure there's an option for all
		if(!this.options.empty_label && (!this.options.select_options || !this.options.select_options[""]))
		{
			this.options.empty_label = this.options.label ? this.options.label : egw.lang("All");
		}
		this._super.apply(this, arguments);

		this.input.change(this, function(event) {
			if(typeof event.data.nextmatch == 'undefined')
			{
				// Not fully set up yet
				return;
			}
			var col_filter = {};
			col_filter[event.data.id] = event.data.input.val();
			// Set value so it's there for response (otherwise it gets cleared if options are updated)
			event.data.set_value(event.data.input.val());

			event.data.nextmatch.applyFilters({col_filter: col_filter});
		});

	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.activeFilters.col_filter && typeof this.nextmatch.activeFilters.col_filter[this.id] != "undefined")
		{
			this.set_value(this.nextmatch.activeFilters.col_filter[this.id]);

			// Make sure it's set in the nextmatch
			_nextmatch.activeFilters.col_filter[this.id] = this.getValue();
		}
	},

	// Make sure selectbox is not longer than the column
	resize: function() {
		this.input.css("max-width",jQuery(this.parentNode).innerWidth() + "px");
	}

});
et2_register_widget(et2_nextmatch_filterheader, ['nextmatch-filterheader']);

/**
 * @augments et2_selectAccount
 */
var et2_nextmatch_accountfilterheader = et2_selectAccount.extend([et2_INextmatchHeader, et2_IResizeable],
{
	/**
	 * Override to add change handler
	 *
	 * @memberOf et2_nextmatch_accountfilterheader
	 */
	createInputWidget: function() {
		// Make sure there's an option for all
		if(!this.options.empty_label && !this.options.select_options[""])
		{
			this.options.empty_label = this.options.label ? this.options.label : egw.lang("All");
		}
		this._super.apply(this, arguments);

		this.input.change(this, function(event) {
			if(typeof event.data.nextmatch == 'undefined')
			{
				// Not fully set up yet
				return;
			}
			var col_filter = {};
			col_filter[event.data.id] = event.data.getValue();
			event.data.nextmatch.applyFilters({col_filter: col_filter});
		});

	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.activeFilters.col_filter && this.nextmatch.activeFilters.col_filter[this.id])
		{
			this.set_value(this.nextmatch.activeFilters.col_filter[this.id]);
		}
	},
	// Make sure selectbox is not longer than the column
	resize: function() {
		var max = jQuery(this.parentNode).innerWidth() - 4;
		var surroundings = this.getSurroundings()._widgetSurroundings;
		for(var i = 0; i < surroundings.length; i++)
		{
			max -= jQuery(surroundings[i]).outerWidth();
		}
		this.input.css("max-width",max + "px");
	}

});
et2_register_widget(et2_nextmatch_accountfilterheader, ['nextmatch-accountfilter']);

/**
 * @augments et2_link_entry
 */
var et2_nextmatch_entryheader = et2_link_entry.extend(et2_INextmatchHeader,
{
	/**
	 * Override to add change handler
	 *
	 * @memberOf et2_nextmatch_entryheader
	 * @param {object} event
	 * @param {object} selected
	 */
	select: function(event, selected) {
		this._super.apply(this, arguments);
		var col_filter = {};
		if(selected && selected.item.value) {
			if(event.data.options.only_app)
			{
				// Only one application, just give the ID
				col_filter[this.id] = selected.item.value;
			}
			else
			{
				// App is expecting app:id
				col_filter[this.id] = event.data.app_select.val() + ":"+ selected.item.value;
			}
		} else {
			col_filter[this.id] = '';
		}
		this.nextmatch.applyFilters.call(this.nextmatch, {col_filter: col_filter});
	},

	/**
	 * Override to always return a string appname:id (or just id) for simple (one real selection)
	 * cases, parent returns an object.  If multiple are selected, or anything other than app and
	 * id, the original parent value is returned.
	 */
	getValue: function() {
		var value = this._super.apply(this, arguments);
		if(typeof value == "object" && value != null)
		{
			if(!value.app || !value.id) return null;

			// If array with just one value, use a string instead for legacy server handling
			if(typeof value.id == 'object' && value.id.shift && value.id.length == 1)
			{
				value.id = value.id.shift();
			}
			// If simple value, format it legacy string style, otherwise
			// we return full value
			if(typeof value.id == 'string')
			{
				value = value.app +":"+value.id;
			}
		}
		return value;
	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 *
	 * @param {et2_nextmatch} _nextmatch
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.options.settings.col_filter && this.nextmatch.options.settings.col_filter[this.id])
		{
			this.set_value(this.nextmatch.options.settings.col_filter[this.id]);

			if(this.getValue() != this.nextmatch.activeFilters.col_filter[this.id])
			{
				this.nextmatch.activeFilters.col_filter[this.id] = this.getValue();
			}

			// Tell framework to ignore, or it will reset it to ''/empty when it does loadingFinished()
			this.attributes.value.ignore = true;
			//this.attributes.select_options.ignore = true;
		}
		var self = this;
		// Fire on lost focus, clear filter if user emptied box
		this.search.focusout(this, function(event) {if(!self.search.val()) { self.select(event, {item:{value:null}});}});
	}
});
et2_register_widget(et2_nextmatch_entryheader, ['nextmatch-entryheader']);

/**
 * @augments et2_nextmatch_filterheader
 */
var et2_nextmatch_customfilter = et2_nextmatch_filterheader.extend(
{
	attributes: {
		"widget_type": {
			"name": "Actual type",
			"type": "string",
			"description": "The actual type of widget you should use",
			"no_lang": 1
		},
		"widget_options": {
			"name": "Actual options",
			"type": "any",
			"description": "The options for the actual widget",
			"no_lang": 1,
			"default": {}
		}
	},
	legacyOptions: ["widget_type","widget_options"],

	real_node: null,

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_nextmatch_customfilter
	 */
	init: function(_parent, _attrs) {

		switch(_attrs.widget_type)
		{
			case "link-entry":
				_attrs.type = 'nextmatch-entryheader';
				break;
			default:
				if(_attrs.widget_type.indexOf('select') === 0)
				{
					_attrs.type = 'nextmatch-filterheader';
				}
				else
				{
					_attrs.type = _attrs.widget_type;
				}
		}
		jQuery.extend(_attrs.widget_options,{id: this.id});

		_attrs.id = '';
		this._super.apply(this, arguments);
		this.real_node = et2_createWidget(_attrs.type, _attrs.widget_options, this._parent);
		var select_options = [];
		var correct_type = _attrs.type;
		this.real_node._type = _attrs.widget_type;
		et2_selectbox.find_select_options(this.real_node, select_options, _attrs);
		this.real_node._type = correct_type;
		if(typeof this.real_node.set_select_options === 'function')
		{
			this.real_node.set_select_options(select_options);
		}
	},

	// Just pass the real DOM node through, in case anybody asks
	getDOMNode: function(_sender) {
		return this.real_node ? this.real_node.getDOMNode(_sender) : null;
	},

	// Also need to pass through real children
	getChildren: function() {
		return this.real_node.getChildren() || [];
	},
	setNextmatch: function(_nextmatch)
	{
		if(this.real_node && this.real_node.setNextmatch)
		{
			return this.real_node.setNextmatch(_nextmatch);
		}
	}
});
et2_register_widget(et2_nextmatch_customfilter, ['nextmatch-customfilter']);
