/**
 * eGroupWare eTemplate2 - Class which generates the outer container for the grid
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict"

/*egw:uses
	jquery.jquery;
	et2_core_common;
	et2_core_stylesheet;

	et2_dataview_view_grid;
*/

/**
 * Base view class which is responsible for displaying a grid view element.
 */
var et2_dataview_gridContainer = Class.extend({

	/**
	 * Constant which regulates the column padding.
	 */
	columnPadding: 2,

	/**
	 * Some browser dependant variables which will be calculated on creation of
	 * the first gridContainer object.
	 */
	scrollbarWidth: false,
	headerBorderWidth: false,
	columnBorderWidth: false,

	/**
	 * Constructor for the grid container
	 * @param object _parentNode is the DOM-Node into which the grid view will be inserted
	 */
	init: function(_parentNode, _dataProvider) {

		// Copy the arguments
		this.parentNode = $j(_parentNode);
		this.dataProvider = _dataProvider;

		// Initialize some variables
		this.columnNodes = []; // Array with the header containers
		this.columns = [];
		this.columnMgr = null;

		this.grid = null;

		this.width = 0;
		this.height = 0;

		this.uniqueId = "gridCont_" + et2_uniqueId();

		// Build the base nodes
		this._createElements();

		// Read the browser dependant variables
		this._getDepVars();
	},

	/**
	 * Destroys the object, removes all dom nodes and clears all references.
	 */
	destroy: function() {
		// Clear the columns
		this._clearHeader();

		// Free the grid
		if (this.grid)
		{
			this.grid.free();
		}

		// Detatch the outer element
		this.table.remove();
	},

	/**
	 * Returns the column container node for the given column index
	 *
	 * @param _columnIdx the integer column index
	 */
	getHeaderContainerNode: function(_columnIdx) {
		if (typeof this.columnNodes[_columnIdx] != "undefined")
		{
			return this.columnNodes[_columnIdx].container[0];
		}

		return null;
	},

	/**
	 * Sets the column descriptors and creates the column header according to it.
	 * The inner grid will be emptied if it has already been built.
	 */
	setColumns: function(_columnData) {
		// Free all column objects which have been created till this moment
		this._clearHeader();

		// Copy the given column data
		this.columnMgr = new et2_dataview_columns(_columnData);

		// Create the stylesheets
		this.updateColumns();

		// Build the header row
		this._buildHeader();

		// Build the grid
		this._buildGrid();
	},

	/**
	 * Resizes the grid
	 */
	resize: function(_w, _h) {
		if (this.width != _w)
		{
			this.width = _w;

			// Rebuild the column stylesheets
			this.columnMgr.setTotalWidth(_w - this.scrollbarWidth);
			this._updateColumns();
		}

		if (this.height != _h)
		{
			this.height = _h;

			// Set the height of the grid.
			if (this.grid)
			{
				this.grid.setScrollHeight(this.height -
					this.headTr.outerHeight(true));
			}
		}
	},

	/**
	 * Returns the column manager object. You can use it to set the visibility
	 * of columns etc. Call "updateHeader" if you did any changes.
	 */
	getColumnMgr: function() {
		return this.columnMgr;
	},

	/**
	 * Recalculates the stylesheets which determine the column visibility and
	 * width.
	 */
	updateColumns: function() {
		if (this.columnMgr)
		{
			this._updateColumns();
		}
	},


	/* --- PRIVATE FUNCTIONS --- */

	/* --- Code for building the grid container DOM-Tree elements ---- */

	/**
	 * Builds the base DOM-Tree elements
	 */
	_createElements: function() {
		/*
			Structure:
			<table class="egwGridView_outer">
				<thead>
					<tr> [HEAD] </tr>
				</thead>
				<tbody>
					<tr> [GRID CONTAINER] </tr>
				</tbody>
			</table>
		*/

		this.containerTr = $j(document.createElement("tr"));
		this.headTr = $j(document.createElement("tr"));

		this.thead = $j(document.createElement("thead"))
			.append(this.headTr);
		this.tbody = $j(document.createElement("tbody"))
			.append(this.containerTr);

		this.table = $j(document.createElement("table"))
			.addClass("egwGridView_outer")
			.append(this.thead, this.tbody)
			.appendTo(this.parentNode);
	},


	/* --- Code for building the header row --- */

	/**
	 * Clears the header row
	 */
	_clearHeader: function() {
		if (this.columnMgr)
		{
			this.columnMgr.free();
			this.columnMgr = null;
		}

		// Reset the headerColumns array and empty the table row
		this.columnNodes = [];
		this.columns = [];
		this.headTr.empty();
	},

	/**
	 * Sets the column data which is retrieved by calling egwGridColumns.getColumnData.
	 * The columns will be updated.
	 */
	_updateColumns: function() {
		// Get the stylesheet singleton
		var styleSheet = new et2_dynStyleSheet();

		// Copy the columns data
		this.columns = this.columnMgr.getColumnData();

		// Count the visible rows
		var total_cnt = 0;
		for (var i = 0; i < this.columns.length; i++)
		{
			if (this.columns[i].visible)
			{
				total_cnt++;
			}
		}

		// Set the grid column styles
		var first = true;
		var vis_col = this.visibleColumnCount = 0;
		var totalWidth = 0;
		for (var i = 0; i < this.columns.length; i++)
		{
			var col = this.columns[i];

			col.tdClass = this.uniqueId + "_td_" + col.id;
			col.divClass = this.uniqueId + "_div_" + col.id;

			if (col.visible)
			{
				vis_col++;
				this.visibleColumnCount++;

				// Update the visibility of the column
				styleSheet.updateRule("." + col.tdClass, 
					"display: table-cell; " + 
					((vis_col == total_cnt) ? "border-right-width: 0 " : "border-right-width: 1px ") +
					"!important;");

				// Ugly browser dependant code - each browser seems to treat the 
				// right (collapsed) border of the row differently
				var subBorder = 0;
				var subHBorder = 0;
				if ($j.browser.mozilla)
				{
					var maj = $j.browser.version.split(".")[0];
					if (maj < 2) {
						subBorder = 1; // Versions <= FF 3.6
					}
				}
				if ($j.browser.webkit)
				{
					if (!first)
					{
						subBorder = 1;
					}
					subHBorder = 1;
				}
				if (($j.browser.msie || $j.browser.opera) && first)
				{
					subBorder = -1;
				}

				// Make the last columns one pixel smaller, to prevent a horizontal
				// scrollbar from showing up
				if (vis_col == total_cnt)
				{
					subBorder += 1;
				}

				// Write the width of the header columns
				var headerWidth = Math.max(0, (col.width - this.headerBorderWidth - subHBorder));
				styleSheet.updateRule(".egwGridView_outer ." + col.divClass, 
					"width: " + headerWidth + "px;");

				// Write the width of the body-columns
				var columnWidth = Math.max(0, (col.width  - this.columnBorderWidth - subBorder));
				styleSheet.updateRule(".egwGridView_grid ." + col.divClass, 
					"width: " + columnWidth + "px;");

				totalWidth += col.width;

				first = false;
			}
			else
			{
				styleSheet.updateRule("." + col.tdClass, "display: none;");
			}
		}

		// Add the full row and spacer class
		styleSheet.updateRule(".egwGridView_grid ." + this.uniqueId + "_div_fullRow",
			"width: " + (totalWidth - this.columnBorderWidth - 1) + "px; border-right-width: 0 !important;");
		styleSheet.updateRule(".egwGridView_outer ." + this.uniqueId + "_spacer_fullRow",
			"width: " + (totalWidth - 1) + "px; border-right-width: 0 !important;");
	},

	/**
	 * Builds the containers for the header row
	 */
	_buildHeader: function() {
		for (var i = 0; i < this.columns.length; i++)
		{
			var col = this.columns[i];

			// Create the column header and the container element
			var cont = $j(document.createElement("div"))
				.addClass("innerContainer")
				.addClass(col.divClass);
			var column = $j(document.createElement("th"))
				.addClass(col.tdClass)
				.append(cont)
				.appendTo(this.headTr);

			// Store both nodes in the columnNodes array
			this.columnNodes.push({
				"column": column,
				"container": cont
			});
		}

		this._buildSelectCol();
	},

	/**
	 * Builds the select cols column
	 */
	_buildSelectCol: function() {
		// Build the "select columns" icon
		this.selectColIcon = $j(document.createElement("span"))
			.addClass("selectcols");

		// Build the option column
		this.selectCol = $j(document.createElement("th"))
			.addClass("optcol")
			.append(this.selectColIcon)
			.appendTo(this.headTr);

		this.selectCol.css("width", this.scrollbarWidth - this.selectCol.outerWidth()
				+ this.selectCol.width() + 1);
	},

	/**
	 * Builds the inner grid class
	 */
	_buildGrid: function() {
		// Create the collection of column ids
		var colIds = new Array(this.columns.length);
		for (var i = 0; i < this.columns.length; i++)
		{
			colIds[i] = this.columns[i].id;
		}

		// Create the grid class and pass "19" as the starting average row height
		this.grid = new et2_dataview_grid(null, this.uniqueId, colIds,
			this.dataProvider, 19);

		// Insert the grid into the DOM-Tree
		this.containerTr.append(this.grid.getJNode());
	},

	/* --- Code for calculating the browser/css depending widths --- */

	/**
	 * Reads the browser dependant variables
	 */
	_getDepVars: function() {
		if (this.scrollbarWidth === false)
		{
			// Clone the table and attach it to the outer body tag
			var clone = this.table.clone();
			$j(window.top.document.getElementsByTagName("body")[0])
				.append(clone);

			// Read the scrollbar width
			this.scrollbarWidth = this.constructor.prototype.scrollbarWidth =
				this._getScrollbarWidth(clone);

			// Read the header border width
			this.headerBorderWidth = this.constructor.prototype.headerBorderWidth =
				this._getHeaderBorderWidth(clone);

			// Read the column border width
			this.columnBorderWidth = this.constructor.prototype.columnBorderWidth =
				this._getColumnBorderWidth(clone);

			// Remove the cloned DOM-Node again from the outer body
			clone.remove();
		}
	},

	/**
	 * Reads the scrollbar width
	 */
	_getScrollbarWidth: function(_table) {
		// Create a temporary td and two divs, which are inserted into the 
		// DOM-Tree. The outer div has a fixed size and "overflow" set to auto.
		// When the second div is inserted, it will be forced to display a scrollbar.
		var div_inner = $j(document.createElement("div"))
			.css("height", "1000px");
		var div_outer = $j(document.createElement("div"))
			.css("height", "100px")
			.css("width", "100px")
			.css("overflow", "auto")
			.append(div_inner);
		var td = $j(document.createElement("td"))
			.append(div_outer);

		// Store the scrollbar width statically.
		$j("tbody tr", _table).append(td);
		var width = Math.max(10, div_outer.outerWidth() - div_inner.outerWidth());

		// Remove the elements again
		div_outer.remove();

		return width;
	},

	/**
	 * Calculates the total width of the header column border
	 */
	_getHeaderBorderWidth: function(_table) {
		// Create a temporary th which is appended to the outer thead row
		var cont = $j(document.createElement("div"))
			.addClass("innerContainer");

		var th = $j(document.createElement("th"))
			.append(cont);

		// Insert the th into the document tree
		$j("thead tr", _table).append(th);

		// Calculate the total border width
		var width = th.outerWidth(true) - cont.width();

		// Remove the appended element again
		th.remove();

		return width;
	},

	/**
	 * Calculates the total width of the column border
	 */
	_getColumnBorderWidth : function(_table) {
		// Create a temporary th which is appended to the outer thead row
		var cont = $j(document.createElement("div"))
			.addClass("innerContainer");

		var td = $j(document.createElement("td"))
			.append(cont);

		// Insert the th into the document tree
		$j("tbody tr", _table).append(td);

		// Calculate the total border width
		_table.addClass("egwGridView_grid");
		var width = td.outerWidth(true) - cont.width();

		// Remove the appended element again
		td.remove();

		return width;
	}

});


