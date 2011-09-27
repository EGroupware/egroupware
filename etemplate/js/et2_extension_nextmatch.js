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
			var entry = this.getArrayMgr("content").getEntry(this.id);

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
		et2_debug("info", "Changing nextmatch filters to ", this.activeFilters);

		// Clear the dataprovider and the dataview container - this will cause
		// the grid to reload.
		this.dataProvider.clear();
		this.dataviewContainer.clear();
	},

	/**
	 * Generates the column name for the given column widget
	 */
	_genColumnCaption: function(_widget) {
		var result = null;

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

	_parseHeaderRow: function(_row, _colData) {
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
			if (_grid.rowData[y]["class"] == "th")
			{
				this._parseHeaderRow(_grid.cells[y], _grid.colData);
			}
			else
			{
				this._parseDataRow(_grid.cells[y], _grid.colData);
			}
		}
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
				et2_debug("error", "Error while loading definition template for" + 
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
				et2_debug("error", "Nextmatch widget expects a grid to be the " + 
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

		return null;
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
		this.count = jQuery(document.createElement("div"))
			.addClass("header_count")
			.appendTo(this.div);

		this.count.append("? - ? ").append(egw.lang("of")).append(" ");
		this.count_total = jQuery(document.createElement("span"))
			.appendTo(this.count)
			.text(settings.total + "");

		// Set up so if row count changes, display is updated
		nm_div.bind('nm_data', function(e) { // Have to bind to DOM node, not et2 widget
			self.count_total.text(e.nm_data.total);
		});

		// Right

		var filters = jQuery(document.createElement("div")).appendTo(this.div);
		

		// Add category
		if(!settings.no_cat) {
			settings.cat_id_label = egw.lang("Category");
			this.category = this._build_select('cat_id', 'select-cat', settings.cat_id, true);
			filters.append(this.category.getDOMNode());
		}

		// Filter 1
		if(!settings.no_filter) {
			this.filter = this._build_select('filter', 'select', settings.filter, settings.filter_no_lang);
			filters.append(this.filter.getDOMNode());
		}

		// Filter 2
		if(!settings.no_filter2) {
			this.filter2 = this._build_select('filter2', 'select', settings.filter2, settings.filter2_no_lang);
			filters.append(this.filter2.getDOMNode());
		}

		// Search
		this.search = et2_createWidget("textbox", {"blur":egw.lang("search")}, this.nextmatch);
		this.search.input.attr("type", "search")
			.css("left", "40%").css("position", "relative");
		this.search.input.val(settings.search);
		filters.append(this.search.getDOMNode());
		
		jQuery(document.createElement("button"))
			.appendTo(filters)
			.css("left", "40%").css("position", "relative")
			.text(">")
			.click(this.nextmatch, function(event) {
				event.data.activeFilters.search = self.search.getValue()
				event.data.applyFilters();
			});
		
		// Export
		if(!settings.no_csv_export)
		{
			var definition = settings.csv_fields;
			if(settings.csv_fields === true)
			{
				definition = egw.preference('nextmatch-export-definition', this.nextmatch.getTemplateApp());
			}
			var button = et2_createWidget("buttononly", {"label": "Export", image:"phpgwapi/filesave"}, this.nextmatch);
			jQuery(button.getDOMNode()).appendTo(filters).css("float", "right")
				.click(this.nextmatch, function(event) {
					egw_openWindowCentered2( egw.link('/index.php', {
						'menuaction':	'importexport.importexport_export_ui.export_dialog',
						'appname':	event.data.getTemplateApp(),
						'definition':	definition
					}), '_blank', 850, 440, 'yes');
				});
		}

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
		var select = et2_createWidget(type, {
			"id": this.nextmatch.id + "_"+name, 
			"label": this.nextmatch.options.settings[name+"_label"]
		},this.nextmatch);
		select.set_value(value);
		var mgr = this.nextmatch.getArrayMgr("content").openPerspective(this.nextmatch, this.nextmatch.id);
		var options = mgr.getEntry("options-" + name);
		if(options) select.set_select_options(options);
		select.set_value(this.nextmatch.options.settings[name]);
		var input = select.input;
		if(this.nextmatch.options.settings[name+"_onchange"])
		{
			var onchange = this.nextmatch.options.settings[name+"_onchange"];
			// onchange needs to get current values
			if(typeof onchange == "string") {
				// Don't change original so we can do this again
				onchange = et2_js_pseudo_funcs(onchange, this.nextmatch.id);
				if(onchange.indexOf("$") >= 0 || onchange.indexOf("@") >= 0) {
					var mgr = this.nextmatch.getArrayMgr("content");
					if(mgr) onchange = mgr.expandName(onchange);
				}
				onchange = new Function(onchange);
			}
			input.change(this.nextmatch, function(event) {
				onchange(event);
			});
		}
		else
		{
			input.change(this.nextmatch, function(event) {
				event.data.activeFilters[name] = input.val()
				event.data.applyFilters();
			});
		}
			
		return select;
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
	'nextmatch-customfilter', 'nextmatch-customfields']);

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
	 * Set nextmatch is the function which has to be implemented for the
	 * et2_INextmatchHeader interface.
	 */
	setNextmatch: function(_nextmatch) {
		this.nextmatch = _nextmatch;
	}

});

et2_register_widget(et2_nextmatch_filterheader, ['nextmatch-filterheader',
	'nextmatch-accountfilter']);

