/**
 * eGroupWare eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id: et2_description.js 36016 2011-08-05 14:53:54Z igel457 $
 */

/*egw:uses
	jquery.jquery;
	et2_widget;
	et2_xml;
*/

/**
 * Class which implements the "grid" XET-Tag
 */ 
et2_grid = et2_DOMWidget.extend({

	init: function(_parent) {
		// Create the table body and the table
		this.tbody = $j(document.createElement("tbody"));
		this.table = $j(document.createElement("table"));
		this.table.append(this.tbody);

		// Call the parent constructor
		this._super.apply(this, arguments);

		// Counters for rows and columns
		this.rowCount = 0;
		this.columnCount = 0;

		// 2D-Array which holds references to the DOM td tags
		this.cells = [];
		this.managementArray = [];
	},

	destroy: function() {
		// Delete all references to cells or widgets
		this.cells = null;
		this.managementArray = null;
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
					"colSpan": 1,
					"rowSpan": 1,
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
			"span": "1"
		};
	},

	_getRowDataEntry: function() {
		return {
			"height": "auto",
			"class": "",
			"valign": "",
			"span": "1"
		};
	},

	_readAttrWithDefault: function(_node, _name, _default) {
		var val = _node.getAttribute(_name);

		return (val === null) ? _default : val;
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
			if (nodeName == "column")
			{
				colDataEntry["width"] = this._readAttrWithDefault(node, "width", "auto");
				colDataEntry["class"] = this._readAttrWithDefault(node, "class", "");
				colDataEntry["align"] = this._readAttrWithDefault(node, "align", "");
				colDataEntry["span"] = this._readAttrWithDefault(node, "span", "1");
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
			if (nodeName == "row")
			{
				rowDataEntry["height"] = this._readAttrWithDefault(node, "height", "auto");
				rowDataEntry["class"] = this._readAttrWithDefault(node, "class", "");
				rowDataEntry["valign"] = this._readAttrWithDefault(node, "valign", "");
				rowDataEntry["span"] = this._readAttrWithDefault(node, "span", "1");
			}
			else
			{
				rowDataEntry["span"] = "all";
			}
			rowData.push(rowDataEntry);
		}, this);
	},

	_fillCells: function(cells, columns, rows) {
		// Read the elements inside the columns
		var x = 0;
		et2_filteredNodeIterator(columns, function(node, nodeName) {

			function _readColNode(node, nodeName) {
				var cell = this._getCell(cells, x, y);

				// Read the span value of the element
				if (node.getAttribute("span"))
				{
					cell.rowSpan = node.getAttribute("span");
				}
				else
				{
					cell.rowSpan = cell.colData["span"];
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
				var cell = this._getCell(cells, x, y);

				// Read the span value of the element
				if (node.getAttribute("span"))
				{
					cell.colSpan = node.getAttribute("span");
				}
				else
				{
					cell.colSpan = cell.rowData["span"];
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

	/**
	 * As the does not fit very well into the default widget structure, we're
	 * overwriting the loadFromXML function and doing a two-pass reading - 
	 * in the first step the 
	 */
	loadFromXML: function(_node) {
		// Get the columns and rows tag
		var rowsElems = _node.getElementsByTagName("rows");
		var columnsElems = _node.getElementsByTagName("columns");

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

			// Create the table rows
			this.createTableFromCells(cells);
		}
		else
		{
			throw("Error while parsing grid, none or multiple rows or columns tags!");
		}
	},

	createTableFromCells: function(_cells) {
		// Set the rowCount and columnCount variables
		var h = this.rowCount = _cells.length;
		var w = this.columnCount = (h > 0) ? _cells[0].length : 0;

		this.managementArray = [];
		this.cells = _cells;

		// Create the table rows.
		for (var y = 0; y < h; y++)
		{
			var row = _cells[y];
			var tr = $j(document.createElement("tr")).appendTo(this.tbody);

			// Create the cells. x is incremented by the colSpan value of the
			// cell.
			for (var x = 0; x < w;)
			{
				// Fetch a cell from the cells
				var cell = this._getCell(_cells, x, y);

				if (cell.td == null && cell.widget != null)
				{
					// Create the cell
					var td = $j(document.createElement("td")).appendTo(tr);

					// Add the entry for the widget to the management array
					this.managementArray.push({
						"cell": td[0],
						"widget": cell.widget
					});

					// Trigger the "onSetParent" event of the widget
					cell.widget.onSetParent();

					// Set the span values of the cell
					var cs = Math.min(w - x, cell.colSpan);
					var rs = Math.min(h - y, cell.rowSpan);

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
	}

});

et2_register_widget(et2_grid, ["grid"]);


