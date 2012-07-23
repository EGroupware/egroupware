/**
 * eGroupWare eTemplate2 - JS Grid object
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
 * 
 * This also includes repeating the last row in the grid and filling
 * it with content data
 */ 
var et2_grid = et2_DOMWidget.extend([et2_IDetachedDOM, et2_IAligned], {

	createNamespace: true,

	attributes: {
		// Better to use CSS, no need to warn about it
		"border": {
			"ignore": true
		},
		"align": {
			"name": "Align",
			"type": "string",
			"default": "left",
			"description": "Position of this element in the parent hbox"
		},
		"spacing": {
			"ignore": true
		},
		"padding": {
			"ignore": true
		}
	},
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
			"valign": "top",
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
				// Remember this row for auto-repeat - it'll eventually be the last one
				this.lastRowNode = node;

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

		// Add in repeated rows
		// TODO: It would be nice if we could skip header (thead) & footer (tfoot) or treat them separately
		var rowIndex = 0;
		if(this.getArrayMgr("content"))
		{
			var content = this.getArrayMgr("content");
			var rowDataEntry = rowData[rowData.length-1];
			// Find out if we have any content rows, and how many
			while(true)
			{
				if(content.data[rowIndex])
				{
					rowData[rowIndex] = rowDataEntry;
				
					rowIndex++;
				}
				else if (rowIndex == 0)
				{
					// Sometimes the header takes a row, so try at 1
					rowIndex++;
				}
				else
				{
					// No more rows, stop
					break;
				}
			}
		}
		if(rowIndex <= 1)
		{
			// No auto-repeat
			this.lastRowNode = null;
		}
	},

	_fillCells: function(cells, columns, rows) {
		var h = cells.length;
		var w = (h > 0) ? cells[0].length : 0;
		var currentPerspective = this.getArrayMgr("content").perspectiveData;

		// Read the elements inside the columns
		var x = 0;

		et2_filteredNodeIterator(columns, function(node, nodeName) {

			function _readColNode(node, nodeName) {
				if (y >= h)
				{
					this.egw().debug("warn", "Skipped grid cell in column, '" +
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
		var x = 0;
		var readRowNode;
		et2_filteredNodeIterator(rows, function(node, nodeName) {

			readRowNode = function _readRowNode(node, nodeName) {
				if (x >= w)
				{
					if(nodeName != "description")
					{
						// Only notify it skipping other than description,
						// description used to pad
						this.egw().debug("warn", "Skipped grid cell in row, '" +
							nodeName + "'");
					}
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

				// Read the align value of the element
				if (node.getAttribute("align"))
				{
					cell.align = node.getAttribute("align");
				}

				// Apply widget's class to td, for backward compatability
				if(node.getAttribute("class"))
				{
					cell.class += (cell.class ? " " : "") + node.getAttribute("class");
				}

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
			x = 0;
			if(this.lastRowNode && node == this.lastRowNode)
			{
				return;
			}
			if (nodeName == "row")
			{
				// Adjust for the row
				for(var name in this.getArrayMgrs())
				{
					//this.getArrayMgr(name).perspectiveData.row = y;
				}

				// If row disabled, just skip it
				var disabled = false;
				if(node.getAttribute("disabled") == "1")
				{
					disabled = true;
				}
				if(!disabled)
				{
					et2_filteredNodeIterator(node, readRowNode, this);
				}
			}
			else
			{
				readRowNode.call(this, node, nodeName);
			}

			y++;
		}, this);

		// Extra content rows
		for(y; y < h; y++) {
			var x = 0;
			// Adjust for the row
			var mgrs = this.getArrayMgrs();
			for(var name in mgrs)
			{
				if(this.getArrayMgr(name).getEntry(y))
				{
					this.getArrayMgr(name).perspectiveData.row = y;
				}
			}

			et2_filteredNodeIterator(this.lastRowNode, readRowNode, this);
		}
		// Reset
		for(var name in this.getArrayMgrs())
		{
			this.getArrayMgr(name).perspectiveData = currentPerspective;
		}
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
		this.managementArray = [];
		this.cells = _cells;
		this.colData = _colData;
		this.rowData = _rowData;

		// Set the rowCount and columnCount variables
		var h = this.rowCount = _cells.length;
		var w = this.columnCount = (h > 0) ? _cells[0].length : 0;

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

			if (this.rowData[y].valign)
			{
				tr.attr("valign", this.rowData[y].valign);
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

					if (cell.align)
					{
						td.attr("align",cell.align);
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

	set_align: function(_value) {
                this.align = _value;
        },

        get_align: function(_value) {
                return this.align;
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
                return [this.getDOMNode()];
        },

        setDetachedAttributes: function(_nodes, _values)
        {
        }
});

et2_register_widget(et2_grid, ["grid"]);


