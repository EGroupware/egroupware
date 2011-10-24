/**
 * eGroupWare eTemplate2 - JS Description object
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
	et2_core_DOMWidget;
	et2_core_xml;
*/

/**
 * Class which implements the "grid" XET-Tag
 */ 
var et2_grid = et2_DOMWidget.extend([et2_IDetachedDOM], {

	init: function() {
		// Create the table body and the table
		this.tbody = $j(document.createElement("tbody"));
		this.table = $j(document.createElement("table"))
			.addClass("et2_grid");
		this.table.append(this.tbody);

		// Call the parent constructor
		this._super.apply(this, arguments);

		// Counters for rows and columns
		this.rowCount = 0;
		this.columnCount = 0;

		// 2D-Array which holds references to the DOM td tags
		this.cells = [];
		this.rowData = [];
		this.colData = [];
		this.managementArray = [];
	},

	destroy: function() {
		this._super.call(this, arguments);
	},

	_initCells: function(_colData, _rowData) {
		// Copy the width and height
		var w = _colData.length;
		var h = _rowData.length;

		// Create the 2D-Cells array
		var cells = new Array(h);
		for (var y = 0; y < h; y++)
		{
			cells[y] = new Array(w);

			// Initialize the cell description objects
			for (var x = 0; x < w; x++)
			{
				cells[y][x] = {
					"td": null,
					"widget": null,
					"colData": _colData[x],
					"rowData": _rowData[y],
					"disabled": _colData[x].disabled || _rowData[y].disabled,
					"class": _colData[x]["class"],
					"colSpan": 1,
					"autoColSpan": false,
					"rowSpan": 1,
					"autoRowSpan": false,
					"width": _colData[x].width,
					"x": x,
					"y": y
				};
			}
		}

		return cells;
	},

	_getColDataEntry: function() {
		return {
			"width": "auto",
			"class": "",
			"align": "",
			"span": "1",
			"disabled": false
		};
	},

	_getRowDataEntry: function() {
		return {
			"height": "auto",
			"class": "",
			"valign": "",
			"span": "1",
			"disabled": false
		};
	},

	_getCell: function(_cells, _x, _y) {
		if ((0 <= _y) && (_y < _cells.length))
		{
			var row = _cells[_y];
			if ((0 <= _x) && (_x < row.length))
			{
				return row[_x];
			}
		}

		throw("Error while accessing grid cells, invalid element count or span value!");
	},

	_forceNumber: function(_val) {
		if (isNaN(_val))
		{
			throw(_val + " is not a number!");
		}

		return parseInt(_val);
	},

	_fetchRowColData: function(columns, rows, colData, rowData) {
		// Parse the columns tag
		et2_filteredNodeIterator(columns, function(node, nodeName) {
			var colDataEntry = this._getColDataEntry();
			colDataEntry["disabled"] = this.getArrayMgr("content")
					.parseBoolExpression(et2_readAttrWithDefault(node, "disabled", ""));
			if (nodeName == "column")
			{
				colDataEntry["width"] = et2_readAttrWithDefault(node, "width", "auto");
				colDataEntry["class"] = et2_readAttrWithDefault(node, "class", "");
				colDataEntry["align"] = et2_readAttrWithDefault(node, "align", "");
				colDataEntry["span"] = et2_readAttrWithDefault(node, "span", "1");
			}
			else
			{
				colDataEntry["span"] = "all";
			}
			colData.push(colDataEntry);
		}, this);

		// Parse the rows tag
		et2_filteredNodeIterator(rows, function(node, nodeName) {
			var rowDataEntry = this._getRowDataEntry();
			rowDataEntry["disabled"] = this.getArrayMgr("content")
					.parseBoolExpression(et2_readAttrWithDefault(node, "disabled", ""));
			if (nodeName == "row")
			{
				rowDataEntry["height"] = et2_readAttrWithDefault(node, "height", "auto");
				rowDataEntry["class"] = et2_readAttrWithDefault(node, "class", "");
				rowDataEntry["valign"] = et2_readAttrWithDefault(node, "valign", "");
				rowDataEntry["span"] = et2_readAttrWithDefault(node, "span", "1");
			}
			else
			{
				rowDataEntry["span"] = "all";
			}
			rowData.push(rowDataEntry);
		}, this);
	},

	_fillCells: function(cells, columns, rows) {
		var h = cells.length;
		var w = (h > 0) ? cells[0].length : 0;

		// Read the elements inside the columns
		var x = 0;

		et2_filteredNodeIterator(columns, function(node, nodeName) {

			function _readColNode(node, nodeName) {
				if (y >= h)
				{
					et2_debug("warn", "Skipped grid cell in column, '" +
						nodeName + "'");
					return;
				}

				var cell = this._getCell(cells, x, y);

				// Read the span value of the element
				if (node.getAttribute("span"))
				{
					cell.rowSpan = node.getAttribute("span");
				}
				else
				{
					cell.rowSpan = cell.colData["span"];
					cell.autoRowSpan = true;
				}

				if (cell.rowSpan == "all")
				{
					cell.rowSpan = cells.length;
				}

				var span = cell.rowSpan = this._forceNumber(cell.rowSpan);

				// Create the widget
				var widget = this.createElementFromNode(node, nodeName);

				// Fill all cells the widget is spanning
				for (var i = 0; i < span && y < cells.length; i++, y++)
				{
					this._getCell(cells, x, y).widget = widget;
				}
			};

			// If the node is a column, create the widgets which belong into
			// the column
			var y = 0;
			if (nodeName == "column")
			{
				et2_filteredNodeIterator(node, _readColNode, this);
			}
			else
			{
				_readColNode.call(this, node, nodeName);
			}

			x++;
		}, this);

		// Read the elements inside the rows
		var y = 0;
		et2_filteredNodeIterator(rows, function(node, nodeName) {

			function _readRowNode(node, nodeName) {
				if (x >= w)
				{
					et2_debug("warn", "Skipped grid cell in row, '" +
						nodeName + "'");
					return;
				}

				var cell = this._getCell(cells, x, y);

				// Read the span value of the element
				if (node.getAttribute("span"))
				{
					cell.colSpan = node.getAttribute("span");
				}
				else
				{
					cell.colSpan = cell.rowData["span"];
					cell.autoColSpan = true;
				}

				if (cell.colSpan == "all")
				{
					cell.colSpan = cells[y].length;
				}

				var span = cell.colSpan = this._forceNumber(cell.colSpan);

				// Create the element
				var widget = this.createElementFromNode(node, nodeName);

				// Fill all cells the widget is spanning
				for (var i = 0; i < span && x < cells[y].length; i++, x++)
				{
					cell = this._getCell(cells, x, y);
					if (cell.widget == null)
					{
						cell.widget = widget;
					}
					else
					{
						throw("Grid cell collision, two elements " + 
							"defined for cell (" + x + "," + y + ")!");
					}
				}
			}

			// If the node is a row, create the widgets which belong into
			// the row
			var x = 0;
			if (nodeName == "row")
			{
				et2_filteredNodeIterator(node, _readRowNode, this);
			}
			else
			{
				_readRowNode.call(this, node, nodeName);
			}

			y++;
		}, this);
	},

	_expandLastCells: function(_cells) {
		var h = _cells.length;
		var w = (h > 0) ? _cells[0].length : 0;

		// Determine the last cell in each row and expand its span value if
		// the span has not been explicitly set.
		for (var y = 0; y < h; y++)
		{
			for (var x = w - 1; x >= 0; x--)
			{
				var cell = _cells[y][x];

				if (cell.widget != null)
				{
					if (cell.autoColSpan)
					{
						cell.colSpan = w - x;
					}
					break;
				}
			}
		}

		// Determine the last cell in each column and expand its span value if
		// the span has not been explicitly set.
		for (var x = 0; x < w; x++)
		{
			for (var y = h - 1; y >= 0; y--)
			{
				var cell = _cells[y][x];

				if (cell.widget != null)
				{
					if (cell.autoRowSpan)
					{
						cell.rowSpan = h - y;
					}
					break;
				}
			}
		}
	},

	/**
	 * As the does not fit very well into the default widget structure, we're
	 * overwriting the loadFromXML function and doing a two-pass reading - 
	 * in the first step the 
	 */
	loadFromXML: function(_node) {
		// Get the columns and rows tag
		var rowsElems = et2_directChildrenByTagName(_node, "rows");
		var columnsElems = et2_directChildrenByTagName(_node, "columns");

		if (rowsElems.length == 1 && columnsElems.length == 1)
		{
			var columns = columnsElems[0];
			var rows = rowsElems[0];
			var colData = [];
			var rowData = [];

			// Fetch the column and row data
			this._fetchRowColData(columns, rows, colData, rowData);

			// Initialize the cells
			var cells = this._initCells(colData, rowData);

			// Create the widgets inside the cells and read the span values
			this._fillCells(cells, columns, rows);

			// Expand the span values of the last cells
			this._expandLastCells(cells);

			// Create the table rows
			this.createTableFromCells(cells, colData, rowData);
		}
		else
		{
			throw("Error while parsing grid, none or multiple rows or columns tags!");
		}
	},

	createTableFromCells: function(_cells, _colData, _rowData) {
		// Set the rowCount and columnCount variables
		var h = this.rowCount = _cells.length;
		var w = this.columnCount = (h > 0) ? _cells[0].length : 0;

		this.managementArray = [];
		this.cells = _cells;
		this.colData = _colData;
		this.rowData = _rowData;

		// Create the table rows.
		for (var y = 0; y < h; y++)
		{
			var row = _cells[y];
			var tr = $j(document.createElement("tr")).appendTo(this.tbody)
				.addClass(this.rowData[y]["class"]);

			if (this.rowData[y].disabled)
			{
				tr.hide();
			}

			if (this.rowData[y].height != "auto")
			{
				tr.height(this.rowData[y].height);
			}

			// Create the cells. x is incremented by the colSpan value of the
			// cell.
			for (var x = 0; x < w;)
			{
				// Fetch a cell from the cells
				var cell = this._getCell(_cells, x, y);

				if (cell.td == null && cell.widget != null)
				{
					// Create the cell
					var td = $j(document.createElement("td")).appendTo(tr)
						.addClass(cell["class"]);

					if (cell.disabled)
					{
						td.hide();
					}

					if (cell.width != "auto")
					{
						td.width(cell.width);
					}

					// Add the entry for the widget to the management array
					this.managementArray.push({
						"cell": td[0],
						"widget": cell.widget,
						"disabled": cell.disabled
					});

					// Set the span values of the cell
					var cs = (x == w - 1) ? w - x : Math.min(w - x, cell.colSpan);
					var rs = (y == h - 1) ? h - y : Math.min(h - y, cell.rowSpan);

					// Set the col and row span values
					if (cs > 1) {
						td.attr("colspan", cs);
					}

					if (rs > 1) {
						td.attr("rowspan", rs);
					}

					// Assign the td to the cell
					for (var sx = x; sx < x + cs; sx++)
					{
						for (var sy = y; sy < y + rs; sy++)
						{
							this._getCell(_cells, sx, sy).td = td;
						}
					}

					x += cell.colSpan;
				}
				else
				{
					x++;
				}
			}
		}
	},

	/**
	 * The grid needs its own assign function in order to fill the grid
	 * accordingly.
	 */
	assign: function(_obj) {
		if (_obj instanceof et2_grid)
		{
			// Remember all widgets which have already been instanciated
			var instances = [];

			// Copy some data from the colData array
			var colData = new Array(_obj.colData.length);
			for (var x = 0; x < _obj.colData.length; x++)
			{
				colData[x] = {
					"disabled": _obj.colData[x].disabled,
					"class": _obj.colData[x]["class"],
					"width": _obj.colData[x].width
				}
			}

			// Copy the some data from the rowData array
			var rowData = new Array(_obj.rowData.length);
			for (var y = 0; y < _obj.rowData.length; y++)
			{
				rowData[y] = {
					"disabled": _obj.rowData[y].disabled,
					"class": _obj.rowData[y]["class"],
					"height": _obj.rowData[y].height
				}
			}

			// Copy the cells array of the other grid and clone the widgets
			// inside of it
			var cells = new Array(_obj.cells.length);

			for (var y = 0; y < _obj.cells.length; y++)
			{
				cells[y] = new Array(_obj.cells[y].length);

				for (var x = 0; x < _obj.cells[y].length; x++)
				{
					var srcCell = _obj.cells[y][x];

					var widget = null;
					if (srcCell.widget)
					{
						// Search for the widget inside the instances array
						for (var i = 0; i < instances.length; i++)
						{
							if (instances[i].srcWidget == srcCell.widget)
							{
								widget = instances[i].widget;
								break;
							}
						}

						if (widget == null)
						{
							widget = srcCell.widget.clone(this, srcCell.widget.type);
							instances.push({
								"widget": widget,
								"srcWidget": srcCell.widget
							});
						}
					}

					cells[y][x] = {
						"widget": widget,
						"td": null,
						"colSpan": srcCell.colSpan,
						"rowSpan": srcCell.rowSpan,
						"disabled": srcCell.disabled,
						"class": srcCell["class"],
						"width": srcCell.width
					}
				}
			}

			// Create the table
			this.createTableFromCells(cells, colData, rowData);

			// Copy a reference to the content array manager
			if (_obj._mgr)
			{
				this._mgr = _obj._mgr;
			}
		}
		else
		{
			throw("Invalid assign to grid!");
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
		for (var i = 0; i < this.managementArray.length; i++)
		{
			if (this.managementArray[i].widget == _sender)
			{
				return this.managementArray[i].cell;
			}
		}

		return null;
	},

	isInTree: function(_sender) {
		var vis = true;

		if (typeof _sender != "undefined" && _sender != this)
		{
			vis = false;

			// Check whether the _sender object exists inside the management array
			for (var i = 0; i < this.managementArray.length; i++)
			{
				if (this.managementArray[i].widget == _sender)
				{
					vis = !(this.managementArray[i].disabled);
					break;
				}
			}
		}

		return this._super(this, vis);
	},

	/**
         * Code for implementing et2_IDetachedDOM
	 * This doesn't need to be implemented.
	 * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
         */
        getDetachedAttributes: function(_attrs)
        {
        },

        getDetachedNodes: function()
        {
                return [];
        },

        setDetachedAttributes: function(_nodes, _values)
        {
        }
});

et2_register_widget(et2_grid, ["grid"]);


