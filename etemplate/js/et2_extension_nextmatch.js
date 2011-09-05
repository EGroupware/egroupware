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
	jquery.jquery;
	et2_widget;
	et2_core_interfaces;
	et2_core_DOMWidget;
	et2_widget_template;
	et2_widget_grid;
	et2_widget_selectbox;
	et2_extension_nextmatch_dynheight;
	et2_dataview_view_gridContainer;
	et2_dataview_model_dataProvider;
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
		}
	},

	legacyOptions: ["template"],

	init: function() {
		this._super.apply(this, arguments);

		this.div = $j(document.createElement("div"))
			.addClass("et2_nextmatch");

		// Create the dynheight component which dynamically scales the inner
		// container.
		this.dynheight = new et2_dynheight(null, this.div, 150);

		// Create the data provider which cares about streaming the row data
		// efficiently to the rows
		this.dataProvider = new et2_dataview_dataProvider();

		// Create the outer grid container
		this.dataviewContainer = new et2_dataview_gridContainer(this.div,
			this.dataProvider);

		this.activeFilters = {};
	},

	/**
	 * Destroys all 
	 */
	destroy: function() {
		this.dataviewContainer.free();
		this.dataProvider.free();
		this.dynheight.free();

		this._super.apply(this, arguments);
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
	 * Sorts the nextmatch widget by the given ID.
	 *
	 * @param _id is the id of the data entry which should be sorted.
	 * @param _asc if true, the elements are sorted ascending, otherwise
	 * 	descending. If not set, the sort direction will be determined
	 * 	automatically.
	 */
	sortBy: function(_id, _asc) {
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

		et2_debug("info", "Sorting nextmatch by '" + _id + "' in direction '" + 
			(_asc ? "asc" : "desc") + "'");
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
	 * _parseGrid in order to get the information for the column headers etc.
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

