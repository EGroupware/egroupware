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
var et2_nextmatch = et2_DOMWidget.extend([et2_IResizeable, et2_IInput],
{
	attributes: {
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
			"description": "Customise the nextmatch - left side.  Provided template becomes a child of nextmatch, and any input widgets with onChange can trigger the nextmatch to refresh by returning true.",
			"default": ""
		},
		"header_right": {
			"name": "Right custom template",
			"type": "string",
			"description": "Customise the nextmatch - right side.  Provided template becomes a child of nextmatch, and any input widgets with onChange can trigger the nextmatch to refresh by returning true.",
			"default": ""
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

	/**
	 * Constructor
	 * 
	 * @memberOf et2_nextmatch
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.activeFilters = {col_filter:{}};
		
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
				this.innerDiv, 150);

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
		// Free the grid components
		this.dataview.free();
		this.rowProvider.free();
		this.controller.free();
		this.dynheight.free();

		this._super.apply(this, arguments);
	},

	/**
	 * Loads the nextmatch settings
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
				$j('table.egwGridView_grid',this.div)
					.on('dragenter','tr',function(e) {
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
					.on('dragexit','tr', function(e) {
						self.controller._selectionMgr.setFocused();
					})
					.on('dragover','tr',false).attr("dropzone","copy")
					
					.on('drop', 'tr',function(e) {
						self.handle_drop(e,this);
						return false;
					});
			}
		}
		return true;
	},

	/**
	 * Implements the et2_IResizeable interface - lets the dynheight manager
	 * update the width and height and then update the dataview container.
	 */
	resize: function() {
		this.dynheight.update(function(_w, _h) {
			this.dataview.resize(_w, _h);
		}, this);
	},

	/**
	 * Sorts the nextmatch widget by the given ID.
	 *
	 * @param _id is the id of the data entry which should be sorted.
	 * @param _asc if true, the elements are sorted ascending, otherwise
	 * 	descending. If not set, the sort direction will be determined
	 * 	automatically.
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
			if (this.activeFilters["sort"].id == _id)
			{
				_asc = !this.activeFilters["sort"].asc;
			}
		}

		// Update the entry in the activeFilters object
		this.activeFilters["sort"] = {
			"id": _id,
			"asc": _asc
		};

		// Set the sortmode display
		this.iterateOver(function(_widget) {
			_widget.setSortmode((_widget.id == _id) ? (_asc ? "asc": "desc") : "none");
		}, this, et2_INextmatchSortable);

		if (_update)
		{
			this.applyFilters();
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
			delete(this.activeFilters["sort"]);
			this.applyFilters();
		}
	},

	/**
	 * Apply current or modified filters on NM widget (updating rows accordingly)
	 * 
	 * @param _set filter(s) to set eg. { filter: '' } to reset filter in NM header
	 */
	applyFilters: function(_set) {
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
					for(var c in _set.col_filter)
					{
						this.activeFilters.col_filter[c] = _set.col_filter[c];
					}
				}
				else
				{
					this.activeFilters[s] = _set[s];
				}
			}
		}

		this.egw().debug("info", "Changing nextmatch filters to ", this.activeFilters);
		
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
	},
	
	/**
	 * Refresh given rows for specified change
	 * 
	 * Change type parameters allows for quicker refresh then complete server side reload:
	 * - edit: request just modified data from given rows
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload
	 * 
	 * @param array|string _row_ids rows to refresh
	 * @param string _type "edit" (default), "delete" or "add"
	 */
	refresh: function(_row_ids, _type) {
		if (typeof _type == 'undefined') _type = 'edit';
		if (typeof _row_ids == 'string' || typeof _row_ids == 'number') _row_ids = [_row_ids];
		if (typeof _row_ids == "undefined" || _row_ids === null) 
		{
			this.applyFilters();
			return;
		}

		// Use jsapi data module to update
		var list = this.options.settings.get_rows.split('.', 2);
		if (list.length < 2) list = this.options.settings.get_rows.split('_', 2);	// support "app_something::method"
		var app = list[0];
		
		id_loop:
		for(var i = 0; i < _row_ids.length; i++)
		{
			var uid = app + "::" + _row_ids[i];
			switch(_type)
			{
				case "edit":
					if(!egw().dataRefreshUID(uid))
					{
						// Could not update just that row
						this.applyFilters();
						break id_loop;
					}
					break;
				case "delete":
					// Blank the row
					egw().dataStoreUID(uid,null);
					// Stop caring about this ID
					egw().dataUnregisterUID(uid);
					break;
				case "add":
				default:
					// Trigger refresh
					this.applyFilters();
					break id_loop;
			}
		}
	},

	/**
	 * Gets the selection
	 * 
	 * @return Object { ids: [UIDs], inverted: boolean}
	 */
	getSelection: function() {
		var selected = this.controller._selectionMgr.getSelected();
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
	 */
	_genColumnCaption: function(_widget) {
		var result = null;

		if(typeof _widget._genColumnCaption == "function") return _widget._genColumnCaption();

		_widget.iterateOver(function(_widget) {
			var label = (_widget.options.label ? _widget.options.label : _widget.options.empty_label);
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
		}
		if(!this.options.settings.columnselection_pref)
		{
			// Set preference name so changes are saved
			this.options.settings.columnselection_pref = this.options.template;
		}
		if(this.options.settings.columnselection_pref) {
			var list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
			var app = list[0];
			// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
			var pref = egw.preference("nextmatch-"+this.options.settings.columnselection_pref, list[0]);
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
			size = this.egw().preference("nextmatch-"+this.options.settings.columnselection_pref+"-size", app);
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
	 */
	_applyUserPreferences: function(_row, _colData) {
		var prefs = this._getPreferences();
		var columnDisplay = prefs.visible;
		var size = prefs.size;
		var negated = prefs.visible_negated;

		// Add in display preferences
		if(columnDisplay && columnDisplay.length > 0)
		{
			RowLoop:
			for(var i = 0; i < _row.length; i++)
			{
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
							for(var k = i; k < columnDisplay.length; k++)
							{
								if(columnDisplay[k].indexOf(_row[i].widget.prefix) == 0)
								{
									_row[i].widget.options.fields[columnDisplay[k].substr(1)] = true;
								}
							} 
							// Resets field visibility too
							_row[i].widget._getColumnName();
							_colData[i].disabled = negated || jQuery.isEmptyObject(_row[i].widget.options.fields);
							continue RowLoop;
						}
					}
				}

				var colName = this._getColumnName(_row[i].widget);
				if(!colName) continue;
				
				if(size[colName]) _colData[i].width = size[colName];
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
		var app = list[0];

		// Server side wants each cf listed as a seperate column
		jQuery.merge(colDisplay, custom_fields);

		// Save visible columns
		// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
		egw.set_preference(app, "nextmatch-"+this.options.settings.columnselection_pref, colDisplay.join(","));

		// Save adjusted column sizes
		egw.set_preference(app, "nextmatch-"+this.options.settings.columnselection_pref+"-size", colSize);

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
		if(changed.length)
		{
			this.applyFilters();
		}
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
			this.columns[x] = {
				"widget": _row[x].widget
			};


			columnData[x] = {
				"id": "col_" + x,
				"caption": this._genColumnCaption(_row[x].widget),
				"visibility": (!_colData[x] || _colData[x].disabled) ?
					ET2_COL_VISIBILITY_INVISIBLE : ET2_COL_VISIBILITY_VISIBLE,
				"width": _colData[x] ? _colData[x].width : 0
			};

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

		// Set the initial row count
		var total = typeof this.options.settings.total != "undefined" ?
			this.options.settings.total : 0;
		this.dataview.grid.setTotalCount(total);

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

		// Set custom data cache prefix
		if(this.options.settings.dataStorePrefix)
		{
			this.controller.setPrefix(this.options.settings.dataStorePrefix);
		}

		// Load the initial order
		/*this.controller.loadInitialOrder(this._getInitialOrder(
			this.options.settings.rows, this.options.settings.row_id
		));*/

		this.controller.setFilters(this.activeFilters);
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
				for(var field_name in widget.customfields)
				{
					columns[widget.prefix+field_name] = " - "+widget.customfields[field_name].label;
					if(widget.options.fields[field_name]) columns_selected.push(et2_customfields_list.prototype.prefix+field_name);
				}
			}
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
				"empty_label":"Refresh",
			}, this);
			autoRefresh.set_id("nm_autorefresh");
			autoRefresh.set_select_options({
				'': "off",
				30: "30 seconds",
				60: "1 Minute",
				300: "5 Minutes"
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

				// Set default or clear forced?
				if(defaultCheck.get_value())
				{
					self.getInstanceManager().submit();
				}
			};

			var cancelButton = et2_createWidget("buttononly", {}, this);
			cancelButton.set_label(this.egw().lang("cancel"));
			cancelButton.onclick = function() {
				self.selectPopup.toggle();
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
		var self = this;
		if (this._autorefresh_timer)
		{
			window.clearInterval(this._autorefresh_timer);
			delete this._autorefresh_timer;
		}
		if(time > 0)
		{
			this._autorefresh_timer = setInterval(function() {self.refresh();}, time * 1000);
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
			// INextmatchHeader widgets.
			this.iterateOver(function (_node) {
				_node.setNextmatch(this);
			}, this, et2_INextmatchHeader);

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
		var promise = []
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
		this.header._build_left_right("left",template);
	},
	set_header_right: function(template) {
		this.header._build_left_right("right",template);
	},
	set_no_filter: function(bool, filter_name) {
		if(typeof filter_name == 'undefined')
		{
			filter_name = 'filter'
		}
		
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
	 * Actions are handled by the controller, so ignore these
	 */
	set_actions: function(actions) {},
	
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
	 */
	 handle_drop: function(event, target) {
		// Check to see if we can handle the link
		// First, find the UID
		var row = this.controller.getRowByNode(target);
		if(!row || !row.uid) return false;
		var uid = row.uid;
		
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
		
		// Link the file to the row
		// just use a link widget, it's all already done
		var split = uid.split('::');
		var link_value = {
			to_app: split.shift(),
			to_id: split.join('::')
		}
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

	getPath: function() {
		var path = this._super.apply(this,arguments);
		if(this.id && path[path.length -1] == this.id) path.pop();
		return path;
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
			"selected": idsArr,
		}	
		jQuery.extend(value, this.activeFilters, this.value);
		return value;
	},
	resetDirty: function() {},
	isDirty: function() { return typeof this.value !== 'undefined';},
	isValid: function() { return true;},
	set_value: function(_value)
	{
		this.value = _value;
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
	 */
	set_actions: function(actions) {},

	_createHeader: function() {

		var self = this;
		var nm_div = this.nextmatch.div;
		var settings = this.nextmatch.options.settings;

		this.div.prependTo(nm_div);

		// Record count
		this.count = jQuery(document.createElement("span"))
			.addClass("header_count ui-corner-all");

		// Need to figure out how to update this as grid scrolls
		// this.count.append("? - ? ").append(egw.lang("of")).append(" ");
		this.count_total = jQuery(document.createElement("span"))
			.appendTo(this.count)
			.text(settings.total + "");
		this.count.prependTo(this.div);

		// Set up so if row count changes, display is updated
		// Register the handler which will update the "totalCount" display
		
		// Left & Right headers
		this.header_div = jQuery(document.createElement("div")).addClass("ui-helper-clearfix ui-helper-reset").prependTo(this.div);
		this.headers = [{id:this.nextmatch.options.header_left}, {id:this.nextmatch.options.header_right}];

		this.filters = jQuery(document.createElement("div")).appendTo(this.div)
			.addClass("filters");
		
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

		// Favorites
		this._setup_favorites(settings['favorites']);

		// Export
		if(typeof settings.csv_fields == "undefined" || settings.csv_fields != false)
		{
			var definition = settings.csv_fields;
			if(settings.csv_fields === true)
			{
				definition = egw.preference('nextmatch-export-definition', this.nextmatch.egw().getAppName());
			}
			var button = et2_createWidget("buttononly", {"label": "Export", image:"phpgwapi/filesave"}, this.nextmatch);
			jQuery(button.getDOMNode()).appendTo(this.filters).css("float", "right")
				.click(this.nextmatch, function(event) {
					egw_openWindowCentered2( egw.link('/index.php', {
						'menuaction':	'importexport.importexport_export_ui.export_dialog',
						'appname':	event.data.egw().getAppName(),
						'definition':	definition
					}), '_blank', 850, 440, 'yes');
				});
		}


		// Search
		this.search = et2_createWidget("textbox", {"id":"search","blur":egw.lang("search")}, this);
		this.search.input.attr("type", "search");
		this.search.input.val(settings.search);

		// Set activeFilters to current value
		this.nextmatch.activeFilters.search = settings.search;
		
		this.search_button = et2_createWidget("button", {"label":">"}, this);
		this.search_button.onclick = function(event) {
			self.nextmatch.activeFilters.search = self.search.getValue();
			self.nextmatch.applyFilters();
		};


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
				var letter = event.target.id;
				event.data.activeFilters.searchletter = (letter == "" ? false : letter);
				event.data.applyFilters();
			});
			// Set activeFilters to current value
			this.nextmatch.activeFilters.searchletter = current_letter;
		}
	},


	_build_left_right: function(left_or_right, template_name)
	{
		var existing = this.headers[left_or_right == "left" ? 0 : 1];
		if(existing && existing._type)
		{
			if(existing.id == template_name) return;
			existing.free();
			this.headers[this.headers.indexOf(existing)] = '';
		}

		// Load the template
		var header = et2_createWidget("template", {"id": template_name}, this);
		jQuery(header.getDOMNode()).addClass(left_or_right == "left" ? "et2_hbox_left":"et2_hbox_right").addClass("nm_header");
		this.headers[left_or_right == "left" ? 0 : 1] = header;
		$j(header.getDOMNode()).on("load", jQuery.proxy(function() {
			//header.loadingFinished();
			this._bindHeaderInput(header);
		},this));
		header.loadingFinished();
	},

	/**
	 * Build the selectbox filters in the header bar
	 * Sets value, options, labels, and change handlers
	 */
	_build_select: function(name, type, value, lang) {
		var widget_options = {
			"id": name,
			"label": this.nextmatch.options.settings[name+"_label"],
			"no_lang": lang
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
		if(name == 'cat_id' && typeof options[''] == 'undefined' && typeof options[0] == 'undefined')
		{
			widget_options.empty_label = this.egw().lang('All');
			this.egw().debug('warn', 'Nextmatch category filter had no "All" option.  Added, but you should fix that.');
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
		select.attributes.value.ignore = true;
		select.attributes.select_options.ignore = true;

		if (this.nextmatch.options.settings[name+"_onchange"])
		{
			// Make sure to get the new value for filtering
			input.change(this.nextmatch, function(event) {
				event.data.activeFilters[name] = select.getValue();
				event.data.applyFilters();
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
				event.data.activeFilters[name] = select.getValue();
				event.data.applyFilters();
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
		if(typeof filters == "undefined")
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
		$j(this.favorites.getDOMNode(this.favorites)).insertAfter(this.count).css("float","right");
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
	},

	/**
	 * Help out nextmatch / widget stuff by checking to see if sender is part of header
	 */
	getDOMNode: function(_sender) {
		var filters = [this.category, this.filter, this.filter2, this.search, this.search_button];
		for(var i = 0; i < filters.length; i++)
		{
			if(_sender == filters[i]) 
			{
				// Give them the filter div
				return this.filters[0];
			}
		}
		if(_sender && _sender._type == "template")
		{
			for(var i = 0; i < this.headers.length; i++)
			{
				if(_sender.id == this.headers[i].id && _sender._parent == this) return this.header_div[0];
			}
		}
		return null;
	},
		
	_bindHeaderInput: function(_widget) {
		var header = this;
		_widget.iterateOver(function(_widget){
			// Previously set change function
			var widget_change = _widget.change;
			_widget.change = function(_node) {
				// Call previously set change function
				var result = widget_change.call(_widget,_node);

				// Update filters
				if(result && _widget.isDirty()) {
					// Update dirty
					_widget._oldValue = _widget.getValue();
					
					var value = this.getInstanceManager().getValues(header);
					// Filter now
					header.nextmatch.applyFilters(value[header.nextmatch.id]);
				}
			};

			// Set activeFilters to current value
			// Use an array mgr to hande non-simple IDs
			var value = {};
			value[_widget.id] = _widget._oldValue = _widget.getValue();
			var mgr = new et2_arrayMgr(value);
			jQuery.extend(this.nextmatch.activeFilters,mgr.data);
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

			if(field.type == 'select')
			{
				widget = et2_createWidget("nextmatch-filterheader", {
					id: cf_id,
					label: field.label,
					select_options: field.values
				}, this);
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
		}
	},

	/**
	 * Override parent so we can update the nextmatch row too
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
			this.nextmatch.sortBy(this.id);
			return true;
		}

		return false;
	},

	/**
	 * Wrapper to join up interface * framework
	 */
	set_sortmode: function(_mode)
	{
		this.setSortmode(_mode);
	},
		
	/**
	 * Function which implements the et2_INextmatchSortable function.
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
			if(typeof event.data.nextmatch.activeFilters.col_filter == 'undefined')
				event.data.nextmatch.activeFilters.col_filter = {};
			if(event.data.input.val())
			{
				event.data.nextmatch.activeFilters["col_filter"][event.data.id] = event.data.input.val();
			}
			else
			{
				delete (event.data.nextmatch.activeFilters["col_filter"][event.data.id]);
			}
			// Set value so it's there for response (otherwise it gets cleared if options are updated)
			event.data.set_value(event.data.input.val());

			event.data.nextmatch.applyFilters();
		});

	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.options.settings.col_filter && typeof this.nextmatch.options.settings.col_filter[this.id] != "undefined")
		{
			this.set_value(this.nextmatch.options.settings.col_filter[this.id]);

			// Make sure it's set in the nextmatch
			_nextmatch.activeFilters.col_filter[this.id] = this.getValue();

			// Tell framework to ignore, or it will reset it to ''/empty when it does loadingFinished()
			this.attributes.value.ignore = true;
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
			if(typeof event.data.nextmatch.activeFilters.col_filter == 'undefined')
				event.data.nextmatch.activeFilters.col_filter = {};
			if(event.data.getValue())
			{
				event.data.nextmatch.activeFilters["col_filter"][event.data.id] = event.data.getValue();
			}
			else
			{
				delete (event.data.nextmatch.activeFilters["col_filter"][event.data.id]);
			}

			event.data.nextmatch.applyFilters();
		});

	},

	set_select_options: function(_options) {
		// Tell framework to ignore, or it will reset it to ''/empty when it does loadingFinished()
		this.attributes.select_options.ignore = true;
		this._super.apply(this, arguments);
	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.options.settings.col_filter && this.nextmatch.options.settings.col_filter[this.id])
		{
			this.set_value(this.nextmatch.options.settings.col_filter[this.id]);

			// Tell framework to ignore, or it will reset it to ''/empty when it does loadingFinished()
			this.attributes.value.ignore = true;
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
	 * @memberOf et2_link_entry
	 */
	select: function(event, selected) {
		this._super.apply(this, arguments);
		if(typeof this.nextmatch.activeFilters.col_filter == 'undefined')
			this.nextmatch.activeFilters.col_filter = {};
		if(selected && selected.item.value) {
			if(event.data.options.only_app)
			{
				// Only one application, just give the ID
				this.nextmatch.activeFilters["col_filter"][this.id] = selected.item.value;
			}
			else
			{
				// App is expecting app:id
				this.nextmatch.activeFilters["col_filter"][this.id] = 
					event.data.app_select.val() + ":"+ selected.item.value;
				
			}
		} else {
			delete (this.nextmatch.activeFilters["col_filter"][this.id]);
		}
		this.nextmatch.applyFilters();
	},

	/**
	 * Override to always return a string appname:id (or just id), parent returns an object
	 */
	getValue: function() {
		var value = this._super.apply(this, arguments);
		if(typeof value == "object" && value != null)
		{
			if(!value.app || !value.id) return null;
			value = value.app +":"+value.id;
		}
		return value;
	},

	/**
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;

		// Set current filter value from nextmatch settings
		if(this.nextmatch.options.settings.col_filter && this.nextmatch.options.settings.col_filter[this.id])
		{
			this.set_value(this.nextmatch.options.settings.col_filter[this.id]);

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
			"description": "The actual type of widget you should use"
		},
		"widget_options": {
			"name": "Actual options",
			"type": "any",
			"description": "The options for the actual widget",
			"default": {}
		},
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
		this._super.apply(this, arguments);

		switch(_attrs.widget_type)
		{
			case "link-entry":
				_attrs.type = 'nextmatch-entryheader';
				break;
			default:
				_attrs.type = _attrs.widget_type;
		}
		// Avoid warning about non-existant attribute
		delete(_attrs.widget_type);
		this.real_node = et2_createWidget(_attrs.type, _attrs.widget_options, this._parent);
	},

	// Just pass the real DOM node through, in case anybody asks
	getDOMNode: function(_sender) {
		return this.real_node ? this.real_node.getDOMNode(_sender) : null;
	},

	// Also need to pass through real children
	getChildren: function() {
		return this.real_node.getChildren();
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
