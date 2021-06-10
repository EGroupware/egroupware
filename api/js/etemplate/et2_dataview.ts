/**
 * EGroupware eTemplate2 - dataview code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_common;

	et2_dataview_model_columns;
	et2_dataview_view_grid;
	et2_dataview_view_rowProvider;
	et2_dataview_view_resizeable;
*/

import {et2_dataview_column, et2_dataview_columns} from './et2_dataview_model_columns';
import {et2_dataview_view_resizable} from "./et2_dataview_view_resizeable";
import {et2_dataview_grid} from "./et2_dataview_view_grid";
import {et2_dataview_rowProvider} from "./et2_dataview_view_rowProvider"
import {egw} from "../jsapi/egw_global";

/**
 * The et2_dataview class is the main class for displaying a dataview. The
 * dataview class manages the creation of the outer html nodes (like the table,
 * header, etc.) and contains the root container: an instance of
 * et2_dataview_view_grid, which can be accessed using the "grid" property of
 * this object.
 *
 * @augments Class
 */
export class et2_dataview
{

	/**
	 * Constant which regulates the column padding.
	 */
	columnPadding: number;

	/**
	 * Some browser dependant variables which will be calculated on creation of
	 * the first gridContainer object.
	 */
	scrollbarWidth: number;
	headerBorderWidth: number;
	columnBorderWidth: number;

	private width: number;
	private height: number;

	private uniqueId: string;

	/**
	 * Hooks to allow parent to keep up to date if things change
	 */
	onUpdateColumns: Function;
	selectColumnsClick: Function;

	private parentNode: JQuery;
	egw: any;

	private columnNodes: any[];
	private columns: any[];
	private columnMgr: et2_dataview_columns;
	rowProvider: et2_dataview_rowProvider;

	grid: et2_dataview_grid;

	// DOM stuff
	private selectColIcon: JQuery;
	private headTr: any;
	private containerTr: JQuery;
	private selectCol: JQuery;
	private thead: JQuery;
	private tbody: JQuery;
	private table: JQuery;
	private visibleColumnCount: number;


	/**
	 * Constructor for the grid container
	 *
	 * @param {DOMElement} _parentNode is the DOM-Node into which the grid view will be inserted
	 * @param {egw} _egw
	 * @memberOf et2_dataview
	 */
	constructor(_parentNode, _egw) {

		// Copy the arguments
		this.parentNode = jQuery(_parentNode);
		this.egw = _egw;

		// Initialize some variables
		this.columnNodes = []; // Array with the header containers
		this.columns = [];
		this.columnMgr = null;
		this.rowProvider = null;

		this.width = 0;
		this.height = 0;

		this.uniqueId = "gridCont_" + this.egw.uid();

		// Build the base nodes
		this._createElements();

		// Read the browser dependant variables
		this._getDepVars();
	}

	/**
	 * Destroys the object, removes all dom nodes and clears all references.
	 */
	destroy()
	{
		// Clear the columns
		this._clearHeader();

		// Free the grid
		if (this.grid)
		{
			this.grid.destroy();
		}

		// Free the row provider
		if (this.rowProvider)
		{
			this.rowProvider.destroy();
		}

		// Detatch the outer element
		this.table.remove();
	}

	/**
	 * Clears all data rows and reloads them
	 */
	clear()
	{
		if (this.grid)
		{
			this.grid.clear();
		}
	}

	/**
	 * Returns the column container node for the given column index
	 *
	 * @param _columnIdx the integer column index
	 */
	getHeaderContainerNode(_columnIdx)
	{
		if (typeof this.columnNodes[_columnIdx] != "undefined")
		{
			return this.columnNodes[_columnIdx].container[0];
		}

		return null;
	}

	/**
	 * Sets the column descriptors and creates the column header according to it.
	 * The inner grid will be emptied if it has already been built.
	 */
	setColumns(_columnData)
	{
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
	}

	/**
	 * Resizes the grid
	 */
	resize(_w: number, _h: number)
	{
		// Not fully initialized yet...
		if (!this.columnMgr) return;

		if (this.width != _w)
		{
			this.width = _w;

			// Take grid border width into account
			_w -= (this.table.outerWidth(true) - this.table.innerWidth());

			// Take grid header border's width into account. eg. category colors may add extra pixel into width
			_w = _w - (this.thead.find('tr').outerWidth() - this.thead.find('tr').innerWidth());

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
	}

	/**
	 * Returns the column manager object. You can use it to set the visibility
	 * of columns etc. Call "updateHeader" if you did any changes.
	 */
	getColumnMgr() {
		return this.columnMgr;
	}

	/**
	 * Recalculates the stylesheets which determine the column visibility and
	 * width.
	 *
	 * @param setDefault boolean Allow admins to save current settings as default for all users
	 */
	updateColumns(setDefault : boolean = false)
	{
		if (this.columnMgr)
		{
			this._updateColumns();
		}

		// Ability to notify parent / someone else
		if (this.onUpdateColumns)
		{
			this.onUpdateColumns(setDefault);
		}
	}


	/* --- PRIVATE FUNCTIONS --- */

	/* --- Code for building the grid container DOM-Tree elements ---- */

	/**
	 * Builds the base DOM-Tree elements
	 */
	private _createElements()
	{
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

		this.containerTr = jQuery(document.createElement("tr"));
		this.headTr = jQuery(document.createElement("tr"));

		this.thead = jQuery(document.createElement("thead"))
			.append(this.headTr);
		this.tbody = jQuery(document.createElement("tbody"))
			.append(this.containerTr);

		this.table = jQuery(document.createElement("table"))
			.addClass("egwGridView_outer")
			.append(this.thead, this.tbody)
			.appendTo(this.parentNode);
	}


	/* --- Code for building the header row --- */

	/**
	 * Clears the header row
	 */
	private _clearHeader ()
	{
		if (this.columnMgr)
		{
			this.columnMgr.destroy();
			this.columnMgr = null;
		}

		// Remove dynamic CSS,
		for (var i = 0; i < this.columns.length; i++)
		{
			if(this.columns[i].tdClass)
			{
				this.egw.css('.'+this.columns[i].tdClass);
			}
			if(this.columns[i].divClass)
			{
				this.egw.css('.'+this.columns[i].divClass);
				this.egw.css(".egwGridView_outer ." + this.columns[i].divClass);
				this.egw.css(".egwGridView_grid ." + this.columns[i].divClass);
			}
		}
		this.egw.css(".egwGridView_grid ." + this.uniqueId + "_div_fullRow");
		this.egw.css(".egwGridView_outer ." + this.uniqueId + "_td_fullRow");
		this.egw.css(".egwGridView_outer ." + this.uniqueId + "_spacer_fullRow");

		// Reset the headerColumns array and empty the table row
		this.columnNodes = [];
		this.columns = [];
		this.headTr.empty();
	}

	/**
	 * Sets the column data which is retrieved by calling egwGridColumns.getColumnData.
	 * The columns will be updated.
	 */
	private _updateColumns()
	{
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
				this.egw.css("." + col.tdClass,
					"display: table-cell; " +
					"!important;");

				// Ugly browser dependant code - each browser seems to treat the
				// right (collapsed) border of the row differently
				var subBorder = 0;
				var subHBorder = 0;
				/*
				if (jQuery.browser.mozilla)
				{
					var maj = jQuery.browser.version.split(".")[0];
					if (maj < 2) {
						subBorder = 1; // Versions <= FF 3.6
					}
				}
				if (jQuery.browser.webkit)
				{
					if (!first)
					{
						subBorder = 1;
					}
					subHBorder = 1;
				}
				if ((jQuery.browser.msie || jQuery.browser.opera) && first)
				{
					subBorder = -1;
				}
				*/

				// Make the last columns one pixel smaller, to prevent a horizontal
				// scrollbar from showing up
				if (vis_col == total_cnt)
				{
					subBorder += 1;
				}

				// Write the width of the header columns
				var headerWidth = Math.max(0, (col.width - this.headerBorderWidth - subHBorder));
				this.egw.css(".egwGridView_outer ." + col.divClass,
					"width: " + headerWidth + "px;");

				// Write the width of the body-columns
				var columnWidth = Math.max(0, (col.width  - this.columnBorderWidth - subBorder));
				this.egw.css(".egwGridView_grid ." + col.divClass,
					"width: " + columnWidth + "px;");

				totalWidth += col.width;

				first = false;
			}
			else
			{
				this.egw.css("." + col.tdClass, "display: none;");
			}
		}

		// Add the full row and spacer class
		this.egw.css(".egwGridView_grid ." + this.uniqueId + "_div_fullRow",
			"width: " + (totalWidth - this.columnBorderWidth - 2) + "px; border-right-width: 0 !important;");
		this.egw.css(".egwGridView_outer ." + this.uniqueId + "_td_fullRow",
			"border-right-width: 0 !important;");
		this.egw.css(".egwGridView_outer ." + this.uniqueId + "_spacer_fullRow",
			"width: " + (totalWidth - 1) + "px; border-right-width: 0 !important;");
	}

	/**
	 * Builds the containers for the header row
	 */
	private _buildHeader()
	{
		var self = this;
		var handler = function(event) {
		};
		for (var i = 0; i < this.columns.length; i++)
		{
			var col = this.columns[i];

			// Create the column header and the container element
			var cont = jQuery(document.createElement("div"))
				.addClass("innerContainer")
				.addClass(col.divClass);

			var column = jQuery(document.createElement("th"))
				.addClass(col.tdClass)
				.attr("align", "left")
				.append(cont)
				.appendTo(this.headTr);

			if(this.columnMgr && this.columnMgr.getColumnById(i))
			{
				column.addClass(this.columnMgr.getColumnById(i).fixedWidth ? 'fixedWidth' : 'relativeWidth');
				if(this.columnMgr.getColumnById(i).visibility === et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT)
				{
					column.addClass('noResize');
				}
			}

			// make column resizable
			var enc_column = self.columnMgr.getColumnById(col.id);
			if(enc_column.visibility !== et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT)
			{
				et2_dataview_view_resizable.makeResizeable(column, function(_w) {

					// User wants the column to stay where they put it, even for relative
					// width columns, so set it explicitly first and adjust other relative
					// columns to match.
					if(this.relativeWidth)
					{
						// Set to selected width
						this.set_width(_w + "px");
						self.columnMgr.updated();
						// Just triggers recalculation
						self.columnMgr.getColumnWidth(0);

						// Set relative widths to match
						var relative = self.columnMgr.totalWidth - self.columnMgr.totalFixed + _w;
						this.set_width(_w / relative);
						for(var i = 0; i < self.columnMgr.columnCount(); i++)
						{
							var col = self.columnMgr.getColumnById('col_'+i);
							if(!col || col == this || col.fixedWidth) continue;
							col.set_width(self.columnMgr.getColumnWidth(i) / relative);
						}
						// Triggers column change callback, which saves
						self.updateColumns();
					}
					else
					{
						this.set_width(this.relativeWidth ? (_w / self.columnMgr.totalWidth) : _w + "px");
						self.columnMgr.updated();
						self.updateColumns();
					}

				}, enc_column);
			}

			// Store both nodes in the columnNodes array
			this.columnNodes.push({
				"column": column,
				"container": cont
			});
		}

		this._buildSelectCol();
	}

	/**
	 * Builds the select cols column
	 */
	private _buildSelectCol()
	{
		// Build the "select columns" icon
		this.selectColIcon = jQuery(document.createElement("span"))
			.addClass("selectcols")
			.css('display', 'inline-block');	// otherwise jQuery('span.selectcols',this.dataview.headTr).show() set it to "inline" causing it to not show up because 0 height

		// Build the option column
		this.selectCol = jQuery(document.createElement("th"))
			.addClass("optcol")
			.append(this.selectColIcon)
			// Toggle display of option popup
			.click(this, function(e) {if(e.data.selectColumnsClick) e.data.selectColumnsClick(e);})
			.appendTo(this.headTr);

		this.selectCol.css("width", this.scrollbarWidth - this.selectCol.outerWidth()
				+ this.selectCol.width() + 1);
	}

	/**
	 * Builds the inner grid class
	 */
	private _buildGrid()
	{
		// Create the collection of column ids
		var colIds = [];
		for (var i = 0; i < this.columns.length; i++)
		{
			if(this.columns[i].visible)
			{
				colIds[i] = this.columns[i].id;
			}
		}

		// Create the row provider
		if(this.rowProvider)
		{
			this.rowProvider.destroy();
		}

		this.rowProvider = new et2_dataview_rowProvider(this.uniqueId, colIds);

		// Create the grid class and pass "19" as the starting average row height
		this.grid = new et2_dataview_grid(null, null, this.egw, this.rowProvider, 19);

		// Insert the grid into the DOM-Tree
		var tr = jQuery(this.grid.getFirstNode());
		this.containerTr.replaceWith(tr);
		this.containerTr = tr;
	}

	/* --- Code for calculating the browser/css depending widths --- */

	/**
	 * Reads the browser dependant variables
	 */
	private _getDepVars()
	{
		if (typeof this.scrollbarWidth === 'undefined')
		{
			// Clone the table and attach it to the outer body tag
			var clone = this.table.clone();
			jQuery(egw.top.document.getElementsByTagName("body")[0])
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
	}

	/**
	 * Reads the scrollbar width
	 */
	private _getScrollbarWidth(_table: JQuery)
	{
		// Create a temporary td and two divs, which are inserted into the
		// DOM-Tree. The outer div has a fixed size and "overflow" set to auto.
		// When the second div is inserted, it will be forced to display a scrollbar.
		var div_inner = jQuery(document.createElement("div"))
			.css("height", "1000px");
		var div_outer = jQuery(document.createElement("div"))
			.css("height", "100px")
			.css("width", "100px")
			.css("overflow", "auto")
			.append(div_inner);
		var td = jQuery(document.createElement("td"))
			.append(div_outer);

		// Store the scrollbar width statically.
		jQuery("tbody tr", _table).append(td);
		var width = Math.max(10, div_outer.outerWidth() - div_inner.outerWidth());

		// Remove the elements again
		div_outer.remove();

		return width;
	}

	/**
	 * Calculates the total width of the header column border
	 */
	private _getHeaderBorderWidth(_table: JQuery)
	{
		// Create a temporary th which is appended to the outer thead row
		var cont = jQuery(document.createElement("div"))
			.addClass("innerContainer");

		var th = jQuery(document.createElement("th"))
			.append(cont);

		// Insert the th into the document tree
		jQuery("thead tr", _table).append(th);

		// Calculate the total border width
		var width = th.outerWidth(true) - cont.width();

		// Remove the appended element again
		th.remove();

		return width;
	}

	/**
	 * Calculates the total width of the column border
	 */
	private _getColumnBorderWidth(_table: JQuery)
	{
		// Create a temporary th which is appended to the outer thead row
		var cont = jQuery(document.createElement("div"))
			.addClass("innerContainer");

		var td = jQuery(document.createElement("td"))
			.append(cont);

		// Insert the th into the document tree
		jQuery("tbody tr", _table).append(td);

		// Calculate the total border width
		_table.addClass("egwGridView_grid");
		var width = td.outerWidth(true) - cont.width();

		// Remove the appended element again
		td.remove();

		return width;
	}
}
