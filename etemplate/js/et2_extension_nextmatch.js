/**
 * eGroupWare eTemplate2 - JS Nextmatch object
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
	// Force some base libraries to be loaded
	jquery.jquery;
	/phpgwapi/egw_json.js;

	// Include the action system
	egw_action.egw_action;
	egw_action.egw_action_popup;
	egw_action.egw_menu_dhtmlx;

	// Include some core classes
	et2_core_widget;
	et2_core_interfaces;
	et2_core_DOMWidget;

	// Include all widgets the nextmatch extension will create
	et2_widget_template;
	et2_widget_grid;
	et2_widget_selectbox;

	// Include the dynheight manager
	et2_extension_nextmatch_dynheight;

	// Include the grid classes
	et2_dataview_view_gridContainer;
	et2_dataview_model_dataProvider;
	et2_dataview_model_columns;

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
 */ 
var et2_nextmatch = et2_DOMWidget.extend(et2_IResizeable, {

	attributes: {
		"template": {
			"name": "Template",
			"type": "string",
			"description": "The id of the template which contains the grid layout."
		},
		"settings": {
			"name": "Settings",
			"type": "any",
			"description": "The nextmatch settings"
		}
	},

	legacyOptions: ["template"],
	createNamespace: true,

	init: function() {
		this._super.apply(this, arguments);

		this.div = $j(document.createElement("div"))
			.addClass("et2_nextmatch");

		this.header = new et2_nextmatch_header_bar(this, this.div);

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(null, this.div, 150);

		// Create the action manager
		this.actionManager = new egwActionManager();

		// Create the data provider which cares about streaming the row data
		// efficiently to the rows.
		var total = typeof this.options.settings.total != "undefined" ?
			this.options.settings.total : 0;
		this.dataProvider = new et2_dataview_dataProvider(this, total,
			this.actionManager);

		// Load the first data into the dataProvider
		if (this.options.settings.rows)
		{
			this.dataProvider.loadData({"rows": this.options.settings.rows});
		}

		// Create the outer grid container
		this.dataviewContainer = new et2_dataview_gridContainer(this.div,
			this.dataProvider);

		this.activeFilters = {};
	},

	/**
	 * Destroys all 
	 */
	destroy: function() {
		// Free the grid components
		this.dataviewContainer.free();
		this.dataProvider.free();
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

			if (entry)
			{
				_attrs["settings"] = entry;
			}
		}
	},

	/**
	 * Implements the et2_IResizeable interface - lets the dynheight manager
	 * update the width and height and then update the dataview container.
	 */
	resize: function() {
		this.dynheight.update(function(_w, _h) {
			this.dataviewContainer.resize(_w, _h);
		}, this);
	},

	/**
	 * Get Rows callback
	 */
	getRows: function(_fetchList, _callback, _context) {
		// Create an ajax-request
		var request = new egw_json_request(
			"etemplate_widget_nextmatch::ajax_get_rows::etemplate", [
					this.getInstanceManager().etemplate_exec_id,
					_fetchList,
					this.activeFilters
				], this);

		var nextmatch = this;
		// Send the request
		request.sendRequest(true, function(_data) {
			_callback.call(_context, _data);
			// Let anything else know
			nextmatch.div.trigger({type:'nm_data', nm_data: _data, 'nextmatch': nextmatch});
		}, null);
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
		}

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

	applyFilters: function() {
		this.egw().debug("info", "Changing nextmatch filters to ", this.activeFilters);

		// Clear the dataprovider and the dataview container - this will cause
		// the grid to reload.
		this.dataProvider.clear();
		this.dataviewContainer.clear();
	},

	/**
	 * Generates the column caption for the given column widget
	 */
	_genColumnCaption: function(_widget) {
		var result = null;

		if(typeof _widget._genColumnCaption == "function") return _widget._genColumnCaption();

		_widget.iterateOver(function(_widget) {
			if (!result)
			{
				result = _widget.options.label;
			}
			else
			{
				result += ", " + _widget.options.label;
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
	 * Apply stored user preferences to discovered columns
	 */
	_applyUserPreferences: function(_row, _colData) {
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
			var pref = this.egw().preference("nextmatch-"+this.options.settings.columnselection_pref, list[0]);
			if(pref) 
			{
				negated = (pref[0] == "!");
				columnPreference = negated ? pref.substring(1) : pref;
			}
		}
		var columnDisplay = et2_csvSplit(columnPreference,null,",");

		// Adjusted column sizes
		var size = {};
		if(this.options.settings.columnselection_pref && app)
		{
			size = this.egw().preference("nextmatch-"+this.options.settings.columnselection_pref+"-size", app);
		}
		if(!size) size = {};

		// Add in display preferences
		if(columnDisplay && columnDisplay.length > 0)
		{
			RowLoop:
			for(var i = 0; i < _row.length; i++)
			{
				var colName = this._getColumnName(_row[i].widget);
				if(!colName) continue;

				if(size[colName]) _colData[i].width = size[colName];
				// Customfields needs special processing
				if(_row[i].widget.instanceOf(et2_nextmatch_customfields))
				{
					// Find cf field
					for(var j = 0; j < columnDisplay.length; j++)
					{
						if(columnDisplay[j].indexOf(_row[i].widget.id) == 0) {
							var cfDisplay = et2_csvSplit(columnDisplay[j],null,"_#");
							_row[i].widget.options.fields = {};
							for(var k = 1; k < cfDisplay.length; k++)
							{
								_row[i].widget.options.fields[cfDisplay[k]] = true;
							} 
							// Resets field visibility too
							_row[i].widget._getColumnName();
							_colData[i].disabled = negated;
							continue RowLoop;
						}
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
		var colMgr = this.dataviewContainer.getColumnMgr()
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
				if(visibility[colMgr.columns[i].id].visible) colDisplay.push(colName);
				if(colMgr.columns[i].fixedWidth) colSize[colName] = colMgr.columns[i].fixedWidth;
				// Server side wants each cf listed as a seperate column
				if(widget.instanceOf(et2_nextmatch_customfields)) 
				{
					for(name in widget.options.fields) {
						 if(widget.options.fields[name]) custom_fields.push("#"+name);
					}
				}
			} else if (colMgr.columns[i].fixedWidth) {
				this.egw().debug("info", "Could not save column width - no name", colMgr.columns[i].id);
			}
		}
			
		var list = et2_csvSplit(this.options.settings.columnselection_pref, 2, ".");
		var app = list[0];

		// Save visible columns
		// 'nextmatch-' prefix is there in preference name, but not in setting, so add it in
		this.egw().set_preference(app, "nextmatch-"+this.options.settings.columnselection_pref, colDisplay.join(","));

		// Save adjusted column sizes
		this.egw().set_preference(app, "nextmatch-"+this.options.settings.columnselection_pref+"-size", colSize);

		// Update query value, so data source can use visible columns to exclude expensive sub-queries
		var oldCols = this.activeFilters.selectcols ? this.activeFilters.selectcols : [];

		// Server side wants each cf listed as a seperate column
		jQuery.merge(colDisplay, custom_fields);

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
		// Get column display preference
		this._applyUserPreferences(_row, _colData);

		// Go over the header row and create the column entries
		this.columns = new Array(_row.length);
		var columnData = new Array(_row.length);
		for (var x = 0; x < _row.length; x++)
		{
			this.columns[x] = {
				"widget": _row[x].widget
			};


			columnData[x] = {
				"id": "col_" + x,
				"caption": this._genColumnCaption(_row[x].widget),
				"visibility": _colData[x].disabled ?
					ET2_COL_VISIBILITY_INVISIBLE : ET2_COL_VISIBILITY_VISIBLE,
				"width": _colData[x].width
			};

			// Append the widget to this container
			this.addChild(_row[x].widget);
		}

		// Create the column manager and update the grid container
		this.dataviewContainer.setColumns(columnData);

		var self = this;
		// Register handler to update preferences when column properties are changed
		this.dataviewContainer.onUpdateColumns = function() { self._updateUserPreferences();};
		
		// Register handler for column selection popup
		this.dataviewContainer.selectColumnsClick = function(event) { self._selectColumnsClick(event);};
	},

	_parseDataRow: function(_row, _colData) {
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
		}

		this.dataviewContainer.rowProvider.setDataRowTemplate(columnWidgets, this);
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
				this._parseDataRow(_grid.cells[y], _grid.colData);
			}
		}
	},

	_selectColumnsClick: function(e) {
		var self = this;
		var columnMgr = this.dataviewContainer.columnMgr;
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
					columns[et2_customfields_list.prototype.prefix+field_name] = " - "+widget.customfields[field_name].label;
					if(widget.fields[field_name]) columns_selected.push(et2_customfields_list.prototype.prefix+field_name);
				}
			}
		}

		// Build the popup
		if(!this.selectPopup)
		{
			var select = et2_createWidget("select", {multiple: true, rows: 8}, this);
			select.set_select_options(columns);
			select.set_value(columns_selected);

			var defaultCheck = et2_createWidget("checkbox", {}, this);
			defaultCheck.set_id('as_default');
			defaultCheck.set_label(this.egw().lang("As default"));

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
					if(visibility[value[i]])
					{
						visibility[value[i]].visible = true;
					}
					// Custom fields are listed seperately in column list, but are only 1 column
					if(self.columns[column].widget.instanceOf(et2_nextmatch_customfields)) {
						var cf = self.columns[column].widget.options.customfields;
						var visible = self.columns[column].widget.options.fields;

						// Turn off all custom fields
						for(var field_name in cf)
						{
							visible[field_name] = false;
						}
						i++;
						// Turn on selected custom fields - start from 0 in case they're not in order
						for(var j = 0; j < value.length; j++)
						{
							if(value[j].indexOf(et2_customfields_list.prototype.prefix) != 0) continue;
							visible[value[j].substring(1)] = true;
							i++;
						}
					}
					column++;
				}
				columnMgr.setColumnVisibilitySet(visibility);
				self.selectPopup.toggle();

				self.dataviewContainer.updateColumns();

				// Set default?
				if(defaultCheck.get_value() == "true")
				{
					self.getInstanceManager().submit();
				}
			};

			var cancelButton = et2_createWidget("buttononly", {}, this);
			cancelButton.set_label(this.egw().lang("cancel"));
			cancelButton.onclick = function() {
				self.selectPopup.toggle();
			}

			this.selectPopup = jQuery(document.createElement("fieldset"))
				.addClass("colselection ui-dialog")
				.append("<legend>"+this.egw().lang("Select columns")+"</legend>")
				.append(select.getDOMNode())
				.append(okButton.getDOMNode())
				.append(cancelButton.getDOMNode())
				.appendTo(this.div);

			// Add default checkbox for admins
			var apps = this.egw().user('apps');
			if(apps['admin'])
			{
				this.selectPopup.append(defaultCheck.getSurroundings().getDOMNode(defaultCheck.getDOMNode()))
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
	 * When the template attribute is set, the nextmatch widget tries to load
	 * that template and to fetch the grid which is inside of it. It then calls
	 */
	set_template: function(_value) {
		if (!this.template)
		{
			// Load the template
			var template = et2_createWidget("template", {"id": _value}, this);

			if (!template.proxiedTemplate)
			{
				this.egw().debug("error", "Error while loading definition template for" + 
					"nextmatch widget.");
				return;
			}

			// Fetch the grid element and parse it
			var definitionGrid = template.proxiedTemplate.getChildren()[0];
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

			// Free the template again
			template.free();

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
		}
	},

	/**
	 * Activates the actions
	 */
	set_settings: function(_settings) {
		if (_settings.actions)
		{
			// Read the actions from the settings array
			this.actionManager.updateActions(_settings.actions);
			this.actionManager.setDefaultExecute("javaScript:nm_action");
			// this is rather hackisch, but I have no idea how to get the action_link & row_id to the actionObject of the row otherwise
			this.actionManager.action_links = _settings.action_links;
			this.actionManager.row_id = _settings.row_id;
		}
	},

	getDOMNode: function(_sender) {
		if (_sender == this)
		{
			return this.div[0];
		}

		for (var i = 0; i < this.columns.length; i++)
		{
			if (_sender == this.columns[i].widget)
			{
				return this.dataviewContainer.getHeaderContainerNode(i);
			}
		}

		// Let header have a chance
		if(_sender._parent && _sender._parent == this)
		{
			return this.header.getDOMNode(_sender);
		}

		return null;
	},

	getPath: function() {
		var path = this._super.apply(this,arguments);
		if(this.id) path.push(this.id);
		return path;
	}

});

et2_register_widget(et2_nextmatch, ["nextmatch"]);

/**
 * Standard nextmatch header bar, containing filters, search, record count, letter filters, etc.
 *
 * Unable to use an existing template for this because parent (nm) doesn't, and template widget doesn't
 * actually load templates from the server.
 */
var et2_nextmatch_header_bar = Class.extend(et2_INextmatchHeader, {
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

	init: function(nextmatch, nm_div) {
		this.nextmatch = nextmatch;
		
		this.div = jQuery(document.createElement("div"))
			.addClass("nextmatch_header");
		if(this.nextmatch) this._createHeader();
	},

	destroy: function() {
		this.nextmatch = null;
		this.div = null;
	},

	setNextmatch: function(nextmatch) {
		if(this.div) this.div.remove();
		this.nextmatch = nextmatch;
		this._createHeader();
	},

	_createHeader: function() {

		var self = this;
		var nm_div = this.nextmatch.div;
		var settings = this.nextmatch.options.settings;

		this.div.prependTo(nm_div);

		// Left

		// Record count
		this.count = jQuery(document.createElement("span"))
			.addClass("header_count ui-corner-all");

		// Need to figure out how to update this as grid scrolls
		//this.count.append("? - ? ").append(egw.lang("of")).append(" ");
		this.count_total = jQuery(document.createElement("span"))
			.appendTo(this.count)
			.text(settings.total + "");

		// Set up so if row count changes, display is updated
		nm_div.bind('nm_data', function(e) { // Have to bind to DOM node, not et2 widget
			self.count_total.text(e.nm_data.total);
		});

		// Right

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


		// Export
		if(!settings.no_csv_export)
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

		this.count.appendTo(this.filters);

		// Search
		this.search = et2_createWidget("textbox", {"blur":egw.lang("search")}, this.nextmatch);
		this.search.input.attr("type", "search");
		this.search.input.val(settings.search);
		
		jQuery(document.createElement("button"))
			.appendTo(this.filters)
			.text(">")
			.click(this.nextmatch, function(event) {
				event.data.activeFilters.search = self.search.getValue()
				event.data.applyFilters();
			});

		

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
		}
	},


	/**
	 * Build the selectbox filters in the header bar
	 * Sets value, options, labels, and change handlers
	 */
	_build_select: function(name, type, value, lang) {
		// Create widget
		var select = et2_createWidget(type, {
			"id": this.nextmatch.id + "_"+name,
			"label": this.nextmatch.options.settings[name+"_label"]
		},this.nextmatch);

		// Set options
		// Check in content for options-<name>
		var mgr = this.nextmatch.getArrayMgr("content");
		var options = mgr.getEntry("options-" + name);
		// Look in sel_options
		if(!options) options = this.nextmatch.getArrayMgr("sel_options").getEntry(name);
		// Check parent sel_options, because those are usually global and don't get passed down
		if(!options) options = this.nextmatch.getArrayMgr("sel_options").parentMgr.getEntry(name);
		if(options) select.set_select_options(options);

		// Set value
		select.set_value(value);

		// Set onChange
		var input = select.input;
		if(this.nextmatch.options.settings[name+"_onchange"])
		{
			// Get the onchange function string
			var onchange = this.nextmatch.options.settings[name+"_onchange"];

			// Connect it to the onchange event of the input element
			input.change(this.nextmatch, et2_compileLegacyJS(onchange, this.nextmatch, input));
		}
		else
		{
			input.change(this.nextmatch, function(event) {
				event.data.activeFilters[name] = input.val()
				event.data.applyFilters();
			});
		}
			
		return select;
	},

	/**
	 * Help out nextmatch / widget stuff by checking to see if sender is part of header
	 */
	getDOMNode: function(_sender) {
		var filters = [this.category, this.filter, this.filter2, this.search];
		for(var i = 0; i < filters.length; i++)
		{
			if(_sender == filters[i]) 
			{
				return this.filters[0];
			}
		}
		return null;
	}

});
et2_register_widget(et2_nextmatch_header_bar, ["nextmatch_header_bar"]);

/**
 * Classes for the nextmatch sortheaders etc.
 */
var et2_nextmatch_header = et2_baseWidget.extend(et2_INextmatchHeader, {

	attributes: {
		"label": {
			"name": "Caption",
			"type": "string",
			"description": "Caption for the nextmatch header",
			"translate": true
		}
	},

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

et2_register_widget(et2_nextmatch_header, ['nextmatch-header',
	'nextmatch-customfilter']);

/**
 * Extend header to process customfields
 */
var et2_nextmatch_customfields = et2_nextmatch_header.extend({
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

	init: function() {

		// Create the table body and the table
                this.tbody = $j(document.createElement("tbody"));
                this.table = $j(document.createElement("table"))
                        .addClass("et2_grid");
                this.table.append(this.tbody);
		this.rows = {};

		this._super.apply(this, arguments);
	},

	destroy: function() {
		this.rows = null;
		this.tbody = null;
		this.table = null;
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
		this._super.apply(this, arguments);
		this.loadFields();
	},

	/**
	 * Build widgets for header - sortable for numeric, text, etc., filterables for selectbox, radio
	 */
	loadFields: function() {
		var columnMgr = this.nextmatch.dataviewContainer.columnMgr;
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
		var apps = egw.link_app_list();
		for(var field_name in this.options.customfields)
		{
			var field = this.options.customfields[field_name];

			
			if(this.rows[field_name]) continue;

			// Table row
			var row = jQuery(document.createElement("tr"))
                                .appendTo(this.tbody);
                        var cf = jQuery(document.createElement("td"))
                                .appendTo(row);
			this.rows[field_name] = cf[0];

			// Create widget by type
			var widget = null;
			var cf_id = et2_customfields_list.prototype.prefix + field_name;

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
				// TODO: Figure out why search can't get focus
				widget = et2_createWidget("nextmatch-entryheader", {
					id: cf_id,
					application: field.type,
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
			// Not sure why this is needed, widget should be added by et2_createWidget()
			if(widget) cf.append(widget.getDOMNode());

			// Check for column filter
			if(!jQuery.isEmptyObject(this.options.fields) && (
				this.options.fields[field_name] == false || typeof this.options.fields[field_name] == 'undefined'))
			{
				cf.hide();
			}
		}
	},

	getDOMNode: function(_sender) {
		// If the parent class functions are asking for the DOM-Node, return the
		// outer table.
		if (_sender == this)
		{
			return this.table[0];
		}

		// Check whether the _sender object exists inside the management array
		if(this.rows && _sender.id && this.rows[_sender.id])
		{
			// Empty it, to avoid doubled DOM nodes
			//jQuery(this.rows[_sender.id]).empty();
			return this.rows[_sender.id];
		}

		return null;
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
	 * Provide own column naming, including only selected columns
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
		if(!data) data = this.getArrayMgr("modifications").getRoot().getEntry('~custom_fields~', true);
		if(!data.fields) data.fields = {};
		for(var field in this.options.customfields)
		{
			data.fields[field] = (this.options.fields == null || typeof this.options.fields[field] == 'undefined' ? false : this.options.fields[field]);
		}
		return name;
	}
});
et2_register_widget(et2_nextmatch_customfields, ['nextmatch-customfields']);

var et2_nextmatch_sortheader = et2_nextmatch_header.extend(et2_INextmatchSortable, {

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


var et2_nextmatch_filterheader = et2_selectbox.extend(et2_INextmatchHeader, {

	/**
	 * Override to add change handler
	 */
	createInputWidget: function() {
		// Make sure there's an option for all
		if(!this.options.empty_label && !this.options.select_options[""])
		{
			this.options.empty_label = this.options.label ? this.options.label : egw.lang("All");
		}
		this._super.apply(this, arguments);

		this.input.change(this, function(event) {
			if(typeof event.data.nextmatch.activeFilters.col_filter == 'undefined')
				event.data.nextmatch.activeFilters.col_filter = {};
			if(event.data.input.val())
			{
				event.data.nextmatch.activeFilters["col_filter"][event.data.id] = event.data.input.val()
			}
			else
			{
				delete (event.data.nextmatch.activeFilters["col_filter"][event.data.id]);
			}
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
		if(this.nextmatch.options.settings.col_filter && this.nextmatch.options.settings.col_filter[this.id])
		{
			this.set_value(this.nextmatch.options.settings.col_filter[this.id]);
		}
	}

});

et2_register_widget(et2_nextmatch_filterheader, ['nextmatch-filterheader',
	'nextmatch-accountfilter']);

var et2_nextmatch_entryheader = et2_link_entry.extend(et2_INextmatchHeader, {

	/**
	 * Override to add change handler
	 */
	select: function(event, selected) {
		this._super.apply(this, arguments);
		if(typeof this.nextmatch.activeFilters.col_filter == 'undefined')
			this.nextmatch.activeFilters.col_filter = {};
		if(selected && selected.item.value) {
			this.nextmatch.activeFilters["col_filter"][this.id] = selected.item.value;
		} else {
			delete (this.nextmatch.activeFilters["col_filter"][this.id]);
		}
		this.nextmatch.applyFilters();
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
		}
		var self = this;
		// Fire on lost focus, clear filter if user emptied box
		this.search.focusout(this, function(event) {if(!self.search.val()) { self.select(event, {item:{value:null}});}});
	}
});
et2_register_widget(et2_nextmatch_entryheader, ['nextmatch-entryheader']);
