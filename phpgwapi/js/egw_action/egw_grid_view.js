/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

/*egw:uses
	jquery.jquery;
	egw_stylesheet;
*/

/**
 * Common functions used in most view classes
 */

/**
 * Returns an "area" object with the given top position and height
 */
function egwArea(_top, _height)
{
	return {
		"top": _top,
		"bottom": _top + _height
	}
}

/**
 * Returns whether two area objects intersect each other
 */
function egwAreaIntersect(_ar1, _ar2)
{
	return ! (_ar1.bottom < _ar2.top || _ar1.top > _ar2.bottom);
}

/**
 * Returns whether two areas intersect (result = 0) or their relative position
 * to each other (used to do a binary search inside a list of sorted area objects).
 */
function egwAreaIntersectDir(_ar1, _ar2)
{
	if (_ar1.bottom < _ar2.top)
	{
		return -1;
	}
	if (_ar1.top > _ar2.bottom)
	{
		return 1;
	}
	return 0;
}


/** -- egwGridViewOuter Class -- **/

var EGW_GRID_COLUMN_PADDING = 2;
var EGW_GRID_SCROLLBAR_WIDTH = false;
var EGW_GRID_HEADER_BORDER_WIDTH = false;
var EGW_GRID_COLUMN_BORDER_WIDTH = false;
var EGW_UNIQUE_COUNTER = 0;

/**
 * Base view class which is responsible for displaying a grid view element.
 *
 * @param object _parentNode is the DOM-Node into which the grid view will be inserted
 * @param object _data is the data-provider object which contains/loads the grid rows
 * 	and contains their data.
 */
function egwGridViewOuter(_parentNode, _dataRoot, _selectColsCallback, _toggleAllCallback,
	_sortColsCallback, _context)
{
	this.parentNode = $j(_parentNode);
	this.dataRoot = _dataRoot;

	EGW_UNIQUE_COUNTER++;

	// Build the base nodes
	this.outer_table = null;
	this.outer_thead = null;
	this.outer_head_tr = null;
	this.outer_tbody = null;
	this.outer_tr = null;
	this.optcol = null;
	this.selectcols = null;

	this.oldWidth = 0;
	this.oldHeight = 0;
	this.scrollbarWidth = 0;

	this.visibleColumnCount = 0;

	this.checkbox = null;

	this.uniqueId = 'grid_outer_' + EGW_UNIQUE_COUNTER;

	this.headerColumns = [];
	this.selectColsCallback = _selectColsCallback;
	this.sortColsCallback = _sortColsCallback;
	this.toggleAllCallback = _toggleAllCallback;
	this.context = _context;

	this.styleSheet = new egwDynStyleSheet();

	this.buildBase();

	// Now that the base grid has been build, we can perform a few tests, to
	// determine some browser/CSS dependant width values

	// Read the scrollbar width
	this.scrollbarWidth = Math.max(10, this.getScrollbarWidth());

	// Read the th and td border width
	this.headerBorderWidth = this.getHeaderBorderWidth();
	this.columnBorderWidth = this.getColumnBorderWidth();

	// Start value for the average row height
	this.avgRowHeight = 19.0;
	this.avgRowCnt = 1;

	// Insert the base grid container into the DOM-Tree
	this.grid = new egwGridViewGrid(null, null, true, this); // (No parent grid, no height change callback, scrollable)
	this.grid.insertIntoDOM(this.outer_tr, []);

	this.dataRoot.gridObject = this.grid;
}

/**
 * Adds a new element to the average container height counter.
 */
egwGridViewOuter.prototype.addHeightToAvg = function(_value)
{
	this.avgRowCnt++;

	var frac = 1.0 / this.avgRowCnt;
	this.avgRowHeight = this.avgRowHeight * (1 - frac) + _value * frac;
}

/**
 * Removes the height from the average container height
 */
egwGridViewOuter.prototype.remHeightFromAvg = function(_value)
{
	var sum = this.avgRowHeight * this.avgRowCnt - _value;
	this.avgRowCnt--;
	this.avgRowCount = sum / this.avgRowCnt;
}

/**
 * Removes all containers from the base grid and replaces it with spacers again.
 * As only partial data is displayed, this method is faster than updating every
 * displayed data row. Please note that this may also reset/change the scrollbar
 * position.
 */
egwGridViewOuter.prototype.empty = function()
{
	this.grid.empty(this.columns);

	// Create a new spacer container and set the item list to the root level children
	var spacer = this.grid.insertContainer(-1, egwGridViewSpacer, this.avgRowHeight);
	this.dataRoot.getChildren(function(_children) {
		spacer.setItemList(_children);
	}, null);
}

/**
 * Sets the column data which is retrieved by calling egwGridColumns.getColumnData.
 * The columns will be updated.
 */
egwGridViewOuter.prototype.updateColumns = function(_columns)
{
	// Copy the columns data
	this.columns = _columns;

	var first = true;

	// Count the visible rows
	var total_cnt = 0;
	for (var i = 0; i < this.columns.length; i++)
	{
		if (this.columns[i].visible)
		{
			total_cnt++;
		}
	}

	var vis_col = this.visibleColumnCount = 0;
	var totalWidth = 0;

	// Set the grid column styles
	for (var i = 0; i < this.columns.length; i++)
	{
		var col = this.columns[i];

		col.tdClass = this.uniqueId + "_td_" + col.id;
		col.divClass = this.uniqueId + "_div_" + col.id;

		if (col.visible)
		{
			vis_col++;
			this.visibleColumnCount++;

			this.styleSheet.updateRule("." + col.tdClass, 
				"display: " + (col.visible ? "table-cell" : "none") + "; " + 
				((vis_col == total_cnt) ? "border-right-width: 0 " : "border-right-width: 1px ") +
				"!important;");

			this.styleSheet.updateRule(".egwGridView_outer ." + col.divClass, 
				"width: " + (col.width - this.headerBorderWidth) + "px;");

			// Ugly browser dependant code - each browser seems to treat the 
			// right (collapsed) border of the row differently
			addBorder = 0;
			if ($j.browser.mozilla)
			{
				var maj = $j.browser.version.split(".")[0];
				if (maj < 2) {
					addBorder = 1; // Versions <= FF 3.6
				}
			}
			if ($j.browser.webkit && !first)
			{
				addBorder = 1;
			}
			if (($j.browser.msie || $j.browser.opera) && first)
			{
				addBorder = -1;
			}

			// Make the last columns one pixel smaller, to prevent a horizontal
			// scrollbar from showing up
			if (vis_col == total_cnt)
			{
				addBorder += 1;
			}

			var width = (col.width - this.columnBorderWidth - addBorder);

			this.styleSheet.updateRule(".egwGridView_grid ." + col.divClass, 
				"width: " + width + "px;");

			totalWidth += col.width;

			first = false;
		}
		else
		{
			this.styleSheet.updateRule("." + col.tdClass, 
				"display: " + (col.visible ? "table-cell" : "none") + ";");
		}
	}

	// Add the full row and spacer class
	this.styleSheet.updateRule(".egwGridView_grid ." + this.uniqueId + "_div_fullRow",
		"width: " + (totalWidth - this.columnBorderWidth - 1) + "px; border-right-width: 0 !important;");
	this.styleSheet.updateRule(".egwGridView_outer ." + this.uniqueId + "_spacer_fullRow",
		"width: " + (totalWidth - 1) + "px; border-right-width: 0 !important;");

	// Build the header if this hasn't been done yet
	this.buildBaseHeader();

	// Update the grid
	this.grid.updateColumns(this.columns);
}

egwGridViewOuter.prototype.buildBase = function()
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

	this.outer_table = $j(document.createElement("table"));
	this.outer_table.addClass("egwGridView_outer");
	this.outer_thead = $j(document.createElement("thead"));
	this.outer_tbody = $j(document.createElement("tbody"));
	this.outer_tr = $j(document.createElement("tr"));
	this.outer_head_tr = $j(document.createElement("tr"));

	this.outer_table.append(this.outer_thead, this.outer_tbody);
	this.outer_tbody.append(this.outer_tr);
	this.outer_thead.append(this.outer_head_tr);

	this.parentNode.append(this.outer_table);
}

egwGridViewOuter.prototype.updateColSortmode = function(_colIdx, _sortArrow)
{
	if (typeof _sortArrow == "undefined")
	{
		_sortArrow = $j("span.sort", this.headerColumns[_colIdx]);
	}

	var col = this.columns[_colIdx];
	if (_sortArrow)
	{
		_sortArrow.removeClass("asc");
		_sortArrow.removeClass("desc");

		switch (col.sortmode)
		{
			case EGW_COL_SORTMODE_ASC:
				_sortArrow.addClass("asc");
				break;
			case EGW_COL_SORTMODE_DESC:
				_sortArrow.addClass("desc");
				break;
		}
	}
}

egwGridViewOuter.prototype.buildBaseHeader = function()
{
	// Build the "option-column", if this hasn't been done yet
	if (this.headerColumns.length == 0)
	{
		// Create the head columns
		this.headerColumns = [];

		for (var i = 0; i < this.columns.length; i++)
		{
			var col = this.columns[i];

			// Create the column element and insert it into the DOM-Tree
			var column = $j(document.createElement("th"));
			column.addClass(col.tdClass);
			this.headerColumns.push(column);

			var cont = $j(document.createElement("div"));
			cont.addClass("innerContainer");
			cont.addClass(col.divClass);

			if (col.type == EGW_COL_TYPE_CHECKBOX)
			{
				this.checkbox = $j(document.createElement("input"));
				this.checkbox.attr("type", "checkbox");
				this.checkbox.change(this, function(e) {
					// Call the toggle all callback
					if (e.data.toggleAllCallback)
					{
						e.data.toggleAllCallback.call(e.data.context, $j(this).is(":checked"));
					}
				});

				cont.append(this.checkbox);
			} else {
				var caption = $j(document.createElement("span"));
				caption.html(col.caption);

				cont.append(caption);
			}

			if (col.type != EGW_COL_TYPE_CHECKBOX && col.sortable != EGW_COL_SORTABLE_NONE)
			{
				var sortArrow = $j(document.createElement("span"));
				sortArrow.addClass("sort");
				cont.append(sortArrow);

				this.updateColSortmode(i, sortArrow);

				column.click({"self": this, "idx": i}, function(e) {
					var idx = e.data.idx;
					var self = e.data.self;
					if (self.sortColsCallback)
					{
						self.sortColsCallback.call(self.context, idx);
					}
				});
			}

			column.append(cont);
			this.outer_head_tr.append(column);
		}

		// Build the "select columns" icon
		this.selectcols = $j(document.createElement("span"));
		this.selectcols.addClass("selectcols");

		// Build the option column
		this.optcol = $j(document.createElement("th"));
		this.optcol.addClass("optcol");
		this.optcol.append(this.selectcols);

		// Append the option column and set its width of the last column
		this.outer_head_tr.append(this.optcol);
	
		this.optcol.css("width", this.scrollbarWidth - this.optcol.outerWidth()
			+ this.optcol.width() + 1);
		this.optcol.click(this, function(e) {
			e.data.selectColsCallback.call(e.data.context, e.data.selectcols);

			return false;
		});
	}
}

/**
 * Calculates the width of the browser scrollbar
 */
egwGridViewOuter.prototype.getScrollbarWidth = function()
{
	if (EGW_GRID_SCROLLBAR_WIDTH === false)
	{
		// Create a temporary td and two div, which are inserted into the dom-tree
		var td = $j(document.createElement("td"));
		var div_outer = $j(document.createElement("div"));
		var div_inner = $j(document.createElement("div"));

		// The outer div has a fixed size and "overflow" set to auto. When the second
		// div is inserted, it will be forced to display a scrollbar.
		div_outer.css("height", "100px");
		div_outer.css("width", "100px");
		div_outer.css("overflow", "auto");

		div_inner.css("height", "1000px");

		// Clone the outer table and insert it into the top window (which should)
		// always be visible.
		var clone = this.outer_table.clone();
		var top_body = $j(window.top.document.getElementsByTagName("body")[0]);
		top_body.append(clone);

		$j("tbody tr", clone).append(td);
		td.append(div_outer);
		div_outer.append(div_inner);

		// Store the scrollbar width statically.
		EGW_GRID_SCROLLBAR_WIDTH = div_outer.outerWidth() - div_inner.outerWidth();

		// Remove the temporary elements again.
		clone.remove();
	}

	return EGW_GRID_SCROLLBAR_WIDTH;
}

/**
 * Calculates the total width of the header column border
 */
  egwGridViewOuter.prototype.getHeaderBorderWidth = function()
{
	if (EGW_GRID_HEADER_BORDER_WIDTH === false)
	{
		// Create a temporary th which is appended to the outer thead row
		var cont = $j(document.createElement("div"));
		cont.addClass("innerContainer");

		var th = $j(document.createElement("th"));
		th.append(cont);

		// Clone the outer table and insert it into the top window (which should)
		// always be visible.
		var clone = this.outer_table.clone();
		var top_body = $j(window.top.document.getElementsByTagName("body")[0]);
		top_body.append(clone);

		// Insert the th into the document tree
		$j("thead tr", clone).append(th);

		// Calculate the total border width
		EGW_GRID_HEADER_BORDER_WIDTH = th.outerWidth(true) - cont.width();

		// Remove the clone again
		clone.remove();
	}

	return EGW_GRID_HEADER_BORDER_WIDTH;
}

/**
 * Calculates the total width of the column border
 */
egwGridViewOuter.prototype.getColumnBorderWidth = function()
{
	if (EGW_GRID_COLUMN_BORDER_WIDTH === false)
	{
		// Create a temporary td which is appended to the outer tbody row
		var cont = $j(document.createElement("div"));
		cont.addClass("innerContainer");

		var td = $j(document.createElement("td"));
		td.append(cont);

		// Insert the th into the document tree
		var clone = this.outer_table.clone();
		var top_body = $j(window.top.document.getElementsByTagName("body")[0]);
		top_body.append(clone);

		clone.addClass("egwGridView_grid");
		$j("tbody tr", clone).append(td);

		// Calculate the total border width
		EGW_GRID_COLUMN_BORDER_WIDTH = td.outerWidth(true) - cont.width();

		// Remove the clone again
		clone.remove();
	}

	return EGW_GRID_COLUMN_BORDER_WIDTH;
}

egwGridViewOuter.prototype.setHeight = function(_h)
{
	this.grid.setScrollHeight(_h - this.outer_thead.outerHeight());
}


/** -- egwGridViewContainer Interface -- **/

/**
 * Constructor for the abstract egwGridViewContainer class. A grid view container
 * represents a chunk of data which is inserted into a grid. As the grid itself
 * is a container, hirachical structures can be realised. All containers are inserted
 * into the DOM tree directly after creation.
 *
 * @param object _grid is the parent grid this container is inserted into.
 */
function egwGridViewContainer(_grid, _heightChangeProc)
{
	this.grid = _grid;
	this.visible = true;
	this.position = 0;
	this.heightChangeProc = _heightChangeProc;
	this.parentNode = null;
	this.columns = [];
	this.height = false;
	this.assumedHeight = false;
	this.index = 0;
	this.viewArea = false;
	this.containerClass = "";
	this.heightInAvg = false;
	this.updated = true;

	this.doInsertIntoDOM = null;
	this.doSetViewArea = null;
}

/**
 * Calls the heightChangeProc (if set) in the context of the parent grid (if set)
 */
egwGridViewContainer.prototype.callHeightChangeProc = function()
{
	if (this.heightChangeProc && this.grid)
	{
		// Pass this element as parameter
		this.heightChangeProc.call(this.grid, this);
	}
}

/**
 * Sets the visibility of the container. Setting the visibility only takes place
 * if the parentNode is set and the visible state has changed or the _force
 * parameter is set to true.
 */
egwGridViewContainer.prototype.setVisible = function(_visible, _force)
{
	// Default the _force parameter to force
	if (typeof _force == "undefined")
	{
		_force = false;
	}

	if ((_visible != this.visible || _force) && this.parentNode)
	{
		$j(this.parentNode).toggleClass("hidden", !_visible);

		if (_visible)
		{
			this.assumedHeight = 0;
			this.height = false;
		}

		// As the element is now (in)visible, its height has changed. Inform the
		// parent about it.
		this.callHeightChangeProc();
	}

	this.visible = _visible;
}

/**
 * Returns whether the container is visible. The element is not visible as long
 * as it isn't implemented into the DOM-Tree.
 */
egwGridViewContainer.prototype.getVisible = function()
{
	return this.parentNode && this.visible;
}

/**
 * Inserts the container into the given _parentNode. This method may only be
 * called once after the creation of the container.
 *
 * @param object _parentNode is the parentDOM-Node into which the container should
 * 	be inserted.
 * @param array _columns is an array of columns which will be generated 
 */
egwGridViewContainer.prototype.insertIntoDOM = function(_parentNode, _columns)
{
	if (_parentNode && !this.parentNode)
	{
		// Copy the function arguments
		this.columns = _columns;
		this.parentNode = $j(_parentNode);

		// Call the interface function of the implementation which will insert its data
		// into the parent node.
		return egwCallAbstract(this, this.doInsertIntoDOM, arguments);

		this.setVisible(this.visible);
	}
	else
	{
		throw "egw_action Exception: egwGridViewContainer::insertIntoDOM called more than once for a container object or parent node not specified.";
	}

	return false;
}

egwGridViewContainer.prototype.setViewArea = function(_area, _force)
{
	// Calculate the relative coordinates and pass those to the implementation
	if (_area)
	{
		var relArea = {
			"top": _area.top - this.position,
			"bottom": _area.bottom - this.position
		};

		this.viewArea = relArea;

		if (isNaN(this.viewArea.top))
		{
			throw("View Area got NaN");
		}

		this.checkViewArea(_force);
	}
}

egwGridViewContainer.prototype.getViewArea = function()
{
	if (this.viewArea && this.visible)
	{
		return this.viewArea;
	}

	return false;
}

egwGridViewContainer.prototype.setPosition = function(_top)
{
	// Recalculate the relative view area
	if (this.viewArea)
	{
		var at = this.position + this.viewArea.top;
		this.viewArea = {
			"top": at - _top,
			"bottom": at - _top + (this.viewArea.bottom - this.viewArea.top)
		};

		this.checkViewArea();
	}

	this.position = _top;
}

/**
 * Returns the height of the container in pixels and zero if the element is not
 * visible. The height is clamped to positive values.
 * The browser switch is placed at this position as the getHeight function is one
 * of the mostly called functions in the whole grid code and should stay
 * quite fast.
 */
if ($j.browser.mozilla)
{
	egwGridViewContainer.prototype.getHeight = function(_update)
	{
		if (typeof _update == "undefined")
			_update = false;

		if (this.visible && this.parentNode)
		{
			if ((this.height === false && this.assumedHeight === false) || _update)
			{
				// Firefox sometimes provides fractional pixel values - we are
				// forced to use those - we can obtain the fractional pixel height
				// by using the window.getComputedStyle function
				var compStyle = getComputedStyle(this.parentNode.context, null);
				if (compStyle)
				{
					var styleHeightStr = compStyle.getPropertyValue("height");
					this.height = parseFloat(styleHeightStr.substr(0, styleHeightStr.length - 2));

					if (isNaN(this.height) || this.height < 1)
					{
						this.height = false;
					}
					else
					{
						this.assumedHeight = false;
					}
				}
			}

			return this.height !== false ? this.height : this.assumedHeight;
		}

		return 0;
	}
}
else
{
	egwGridViewContainer.prototype.getHeight = function(_update)
	{
		if (typeof _update == "undefined")
			_update = false;

		if (this.visible && this.parentNode)
		{
			if ((this.height === false && this.assumedHeight === false) || _update)
			{
				this.height = this.parentNode.context.offsetHeight

				if (this.height < 1) {
					this.height = false;
				}
				else
				{
					this.assumedHeight = false;
				}
			}

			return this.height !== false ? this.height : this.assumedHeight;
		}

		return 0;
	}
}

egwGridViewContainer.prototype.invalidateHeightCache = function()
{
	this.assumedHeight = false;
	this.height = false;
}

egwGridViewContainer.prototype.offsetPosition = function(_offset)
{
	this.position += _offset;

	// Offset the view area in the oposite direction
	if (this.viewArea)
	{
		this.viewArea.top -= _offset;
		this.viewArea.bottom -= _offset;

		this.checkViewArea();
	}
}

egwGridViewContainer.prototype.inArea = function(_area)
{
	return egwAreaIntersect(this.getArea(), _area);
}

egwGridViewContainer.prototype.checkViewArea = function(_force)
{
	if (typeof _force == "undefined")
	{
		_force = false;
	}

	if (this.visible && this.viewArea)
	{
		if (!this.grid || !this.grid.inUpdate || _force)
		{
			return egwCallAbstract(this, this.doSetViewArea, [this.viewArea]);
		}
	}

	return false;
}

egwGridViewContainer.prototype.getArea = function()
{
	return egwArea(this.position, this.getHeight());
}

egwGridViewContainer.prototype.getAbsolutePosition = function()
{
	if (this.grid)
	{
		return this.grid.getAbsolutePosition() + this.position;
	}
	else
	{
		return this.position;
	}
}

egwGridViewContainer.prototype.getAbsoluteArea = function()
{
	return egwArea(this.getAbsolutePosition(), this.getHeight());
}

/**
 * Function which is called whenever the column count or the data inside the columns
 * has probably changed - the checkViewArea function of the grid element is called
 * and the variable "updated" is set to true. Grid elements should check this
 * flag and set it to false if they have successfully updated themselves.
 */
egwGridViewContainer.prototype.updateColumns = function(_columns)
{
	this.columns = _columns;

	this.updated = true;
	this.checkViewArea();
}

/** -- egwGridViewGrid Class -- **/

var EGW_GRID_VIEW_EXT = 25;
var EGW_GRID_MAX_CYCLES = 10;
var EGW_GRID_SCROLL_TIMEOUT = 100;
var EGW_GRID_UPDATE_HEIGHTS_TIMEOUT = 50;

/**
 * egwGridViewGrid is the container for egwGridViewContainer objects, but itself
 * implements the egwGridViewContainer interface.
 */
function egwGridViewGrid(_grid, _heightChangeProc, _scrollable, _outer)
{
	if (typeof _scrollable == "undefined")
	{
		_scrollable = false;
	}

	EGW_UNIQUE_COUNTER++;

	var container = new egwGridViewContainer(_grid, _heightChangeProc);

	// Introduce new functions to the container interface
	container.outerNode = null;
	container.innerNode = null;
	container.scrollarea = null;
	container.scrollable = _scrollable;
	container.scrollHeight = 100;
	container.scrollEvents = 0;
	container.inUpdate = 0;
	container.didUpdate = false;
	container.updateIndex = 0;
	container.triggerID = 0;
	container.setupContainer = egwGridViewGrid_setupContainer;
	container.insertContainer = egwGridViewGrid_insertContainer;
	container.removeContainer = egwGridViewGrid_removeContainer;
	container.addContainer = egwGridViewGrid_addContainer;
	container.heightChangeHandler = egwGridViewGrid_heightChangeHandler;
	container.setScrollHeight = egwGridViewGrid_setScrollHeight;
	container.scrollCallback = egwGridViewGrid_scrollCallback;
	container.empty = egwGridViewGrid_empty;
	container.getOuter = egwGridViewGrid_getOuter;
	container.updateAssumedHeights = egwGridViewGrid_updateAssumedHeights;
	container.beginUpdate = egwGridViewGrid_beginUpdate;
	container.endUpdate = egwGridViewGrid_endUpdate;
	container.triggerUpdateAssumedHeights = egwGridViewGrid_triggerUpdateAssumedHeights;
	container.addIconHeightToAvg = egwGridViewGrid_addIconHeightToAvg;
	container.setIconWidth = egwGridViewGrid_setIconWidth;
	container.updateColumns = egwGridViewGrid_updateColumns;
	container.children = [];
	container.outer = _outer;
	container.containerClass = "grid";
	container.avgIconHeight = 16;
	container.avgIconCnt = 1;
	container.uniqueId = "grid_" + EGW_UNIQUE_COUNTER;
	container.maxIconWidth = 16;
	container.styleSheet = new egwDynStyleSheet();

	// Overwrite the abstract container interface functions
	container.getHeight = egwGridViewGrid_getHeight;
	container.doInsertIntoDOM = egwGridViewGrid_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewGrid_doSetviewArea;

	// Set the default selectmode
	container.selectmode = EGW_SELECTMODE_DEFAULT;

	return container;
}

function egwGridViewGrid_setIconWidth(_value)
{
	if (_value > this.maxIconWidth)
	{
		this.maxIconWidth = _value;

		this.styleSheet.updateRule(".iconContainer." + this.uniqueId, 
			"min-width: " + (this.maxIconWidth + 8) + "px;");
	}
}

function egwGridViewGrid_beginUpdate()
{
	if (this.inUpdate == 0)
	{
		this.didUpdate = false;

		if (this.grid)
		{
			this.grid.beginUpdate();
		}
	}
	this.inUpdate++;
}

function egwGridViewGrid_triggerUpdateAssumedHeights()
{
	this.triggerID++;
	var self = this;
	var id = this.triggerID;
	window.setTimeout(function() {
			if (id == self.triggerID)
			{
				self.triggerID = 0;
				self.updateAssumedHeights(20);
			}
		},
		EGW_GRID_UPDATE_HEIGHTS_TIMEOUT
	);
}

function egwGridViewGrid_endUpdate(_recPrev)
{
	if (typeof _recPrev == "undefined")
	{
		_recPrev = false;
	}

	if (this.inUpdate > 0)
	{
		this.inUpdate--;

		if (this.inUpdate == 0 && this.grid)
		{
			this.grid.endUpdate();
		}

		if (this.inUpdate == 0 && this.didUpdate)
		{
			// If an update has been done, check whether any height assumptions have been
			// done. This procedure is executed with some delay, as this gives the browser
			// the time to insert the newly generated objects into the DOM-Tree and allows
			// us to read their height at a very fast rate.
			if (this.didUpdate && !_recPrev)
			{
				this.triggerUpdateAssumedHeights();
			}

			this.didUpdate = false;
		}
	}
}

function egwGridViewGrid_updateColumns(_columns)
{
	try
	{
		this.beginUpdate();

		this.didUpdate = true;

		// Set the colspan value of the grid
		this.outerNode.attr("colspan", this.getOuter().visibleColumnCount
			+ (this.scrollable ? 1 : 0));

		// Call the update function of all children
		for (var i = 0; i < this.children.length; i++)
		{
			this.children[i].updateColumns(_columns);
		}

		// Call the inherited function
		egwGridViewContainer.prototype.updateColumns.call(this, _columns);

		this.updated = false;
	}
	finally
	{
		this.endUpdate();
	}
}

function egwGridViewGrid_getOuter()
{
	if (this.outer)
	{
		return this.outer;
	}
	else if (this.grid)
	{
		return this.grid.getOuter();
	}

	return null;
}

function egwGridViewGrid_setupContainer()
{
	/*
		Structure:
		<td colspan="[columncount]">
			[<div class="egwGridView_scrollarea">]
			<table class="egwGridView_grid">
				<tbody>
					[Container 1]
					[Container 2]
					[...]
					[Container n]
				</tbody>
			</table>
			[</div>]
		</td>
	*/

	this.outerNode = $j(document.createElement("td"));
	this.outerNode.addClass("frame");

	if (this.scrollable)
	{
		this.scrollarea = $j(document.createElement("div"));
		this.scrollarea.addClass("egwGridView_scrollarea");
		this.scrollarea.css("height", this.scrollHeight + "px");
		this.scrollarea.scroll(this, function(e) {
			e.data.scrollEvents++;
			var cnt = e.data.scrollEvents;
			window.setTimeout(function() {
				e.data.scrollCallback(cnt);
			}, EGW_GRID_SCROLL_TIMEOUT);
		});
	}

	var table = $j(document.createElement("table"));
	table.addClass("egwGridView_grid");

	this.innerNode = $j(document.createElement("tbody"));

	if (this.scrollable)
	{
		this.outerNode.append(this.scrollarea);
		this.scrollarea.append(table);
	}
	else
	{
		this.outerNode.append(table);
	}

	table.append(this.innerNode);
}

function egwGridViewGrid_setScrollHeight(_value)
{
	this.scrollHeight = _value;

	if (this.scrollarea)
	{
		this.scrollarea.css("height", _value + "px");
		this.scrollCallback();
	}
}

function egwGridViewGrid_scrollCallback(_event)
{
	if ((typeof _event == "undefined" || _event == this.scrollEvents) && this.scrollarea)
	{
		var cnt = 0;
		var area = egwArea(this.scrollarea.scrollTop() - EGW_GRID_VIEW_EXT,
			this.scrollHeight + EGW_GRID_VIEW_EXT * 2);

		this.setViewArea(area);

		this.scrollEvents = 0;
	}
}

function egwGridViewGrid_updateAssumedHeights(_maxCount)
{
	var traversed = 0;
	var cnt = _maxCount;
	var outer = this.getOuter();

	try
	{
		this.beginUpdate();

		while (traversed < this.children.length && cnt > 0)
		{
			// Clamp the update index
			if (this.updateIndex >= this.children.length)
			{
				this.updateIndex = 0;
			}

			// Get the child at the given position and check whether it used
			// an assumed height
			var child = this.children[this.updateIndex];
			if (child.assumedHeight !== false)
			{
				// Get the difference (delta) between the assumed and the real
				// height
				var oldHeight = child.assumedHeight;

				//XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX
				// This is an ugly hack, but currently I don't have the time to
				// provide a proper fix - problem is that with the "invalidateHeightCache"
				// line wrong height values may be returned which causes all
				// grid data to be loaded. The workaround for this causes
				// the tree view not to work correctly.
				var newHeight;
				if (egw_getObjectManager("felamimail", false) != null)
				{
					newHeight = child.getHeight(true);
				}
				else
				{
					child.invalidateHeightCache();
					newHeight = child.getHeight();
				}
				//XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX XXX

				if (child.containerClass == "row")
				{
					if (child.heightInAvg)
					{
						outer.remHeightFromAvg(oldHeight);
					}
					outer.addHeightToAvg(newHeight);
					child.heightInAvg = true;
				}

				// Offset the position of all following elements by the delta.
				var delta = newHeight - oldHeight;

				if (Math.abs(delta) > 0.001)
				{
					for (var j = this.updateIndex + 1; j < this.children.length; j++)
					{
						this.children[j].offsetPosition(delta);
					}
				}

				// We've now worked on one element with assumed height, decrease
				// the counter
				cnt--;
			}

			// Increment the element index and the count of checked elements
			this.updateIndex++;
			traversed++;
		}
	}
	finally
	{
		this.endUpdate(true);
	}

	if (cnt == 0)
	{
		// If the maximum-update-count has been exhausted, retrigger this function
		this.triggerUpdateAssumedHeights();
	}
	else if (this.viewArea)
	{
		// Otherwise, all elements have been checked - we'll now call "setViewArea"
		// which may check whether new objects are now in the currently visible range
		var self = this;
		window.setTimeout(function() {
			self.setViewArea(self.viewArea);
		}, EGW_GRID_UPDATE_HEIGHTS_TIMEOUT);
	}
}

function egwGridViewGrid_insertContainer(_after, _class, _params)
{
	var container = null;

	try
	{
		this.beginUpdate();
		this.didUpdate = true;

		container = new _class(this, this.heightChangeHandler, _params);

		var idx = this.children.length;
		if (typeof _after == "number")
		{
			idx = Math.min(this.children.length, Math.max(-1, _after)) + 1;
		}
		else if (typeof _after == "object" && _after)
		{
			idx = _after.index + 1;
		}

		// Insert the element at the given position
		this.children.splice(idx, 0, container);

		// Create a table row for that element
		var tr = $j(document.createElement("tr"));

		// Insert the table row after the container specified in the _after parameter
		// and set the top position of the node
		container.index = idx;

		if (idx == 0)
		{
			this.innerNode.prepend(tr);
			container.setPosition(0);
		}
		else
		{
			tr.insertAfter(this.children[idx - 1].parentNode);
			container.setPosition(this.children[idx - 1].getArea().bottom);
		}

		// Insert the container into the table row
		container.insertIntoDOM(tr, this.columns);

		// Offset the position of all following elements by the height of the container
		// and move the index of those elements
		var height = this.getOuter().avgRowHeight;
		container.assumedHeight = height;
		for (var i = idx + 1; i < this.children.length; i++)
		{
			this.children[i].offsetPosition(height);
			this.children[i].index++;
		}
	}
	finally
	{
		this.endUpdate();
	}

	return container;
}

function egwGridViewGrid_removeContainer(_container)
{
	this.didUpdate = true;

	try
	{
		this.beginUpdate();

		var idx = _container.index;

		// Offset the position of the folowing children back
		var height = _container.getHeight();
		for (var i = idx + 1; i < this.children.length; i++)
		{
			this.children[i].offsetPosition(-height);
			this.children[i].index--;
		}

		// Delete the parent node of the container object
		if (_container.parentNode)
		{
			_container.parentNode.remove();
			_container.parentNode = null;
		}

		this.children.splice(idx, 1);
	}
	finally
	{
		this.endUpdate();
	}

	this.callHeightChangeProc();
}

function egwGridViewGrid_empty(_newColumns)
{
	if (typeof _newColumns != "undefined")
	{
		this.columns = _newColumns;
	}

	this.innerNode.empty();
	this.children = [];
	this.maxIconWidth = 16;
}

function egwGridViewGrid_addContainer(_class)
{
	// Insert the container at the beginning of the list.
	this.insertContainer(false, _class);
	return container;
}

function egwGridViewGrid_getHeight()
{
	if (this.visible && this.parentNode)
	{
		if (this.height === false)
		{
			this.height = this.innerNode.outerHeight();
		}
		return this.height;
	}
	else
	{
		return 0;
	}
}

function egwGridViewGrid_heightChangeHandler(_elem)
{
	this.didUpdate = true;

	// The old height of the element is now only an assumed height - the next
	// time the "updateAssumedHeights" functions is triggered, this will be
	// updated.
	var oldHeight = _elem.assumedHeight !== false ? _elem.assumedHeight :
		(_elem.height === false ? 0 : _elem.height);
	_elem.invalidateHeightCache(false);
	_elem.assumedHeight = oldHeight;

	if ((_elem.containerClass == "grid" || _elem.containerClass == "spacer") && !this.inUpdate)
	{
		this.triggerUpdateAssumedHeights();
	}

	// As a result of the height of one of the children, the height of this element
	// has changed too - inform the parent grid about it.
	this.callHeightChangeProc();
}

function egwGridViewGrid_doInsertIntoDOM()
{
	// Generate the DOM Nodes and append the outer node to the parent node
	this.setupContainer();
	this.parentNode.append(this.outerNode);

	this.outerNode.attr("colspan", this.columns.length + (this.scrollable ? 1 : 0));
}

function egwGridViewGrid_doSetviewArea(_area, _recPrev)
{
	if (typeof _recPrev == "undefined")
	{
		_recPrev == false;
	}

	// Do a binary search for elements which are inside the given area
	this.didUpdate = false;
	var elem = null;
	var elems = [];

	var bordertop = 0;
	var borderbot = this.children.length - 1;
	var idx = 0;
	while ((borderbot - bordertop >= 0) && !elem)
	{
		idx = Math.round((borderbot + bordertop) / 2);

		var ar = this.children[idx].getArea();

		var dir = egwAreaIntersectDir(_area, ar);

		if (dir == 0)
		{
			elem = this.children[idx];
		}
		else if (dir == -1)
		{
			borderbot = idx - 1;
		}
		else
		{
			bordertop = idx + 1;
		}
	}

	if (elem)
	{
		elems.push(elem);

		// Search upwards for elements in the area from the matched element on
		for (var i = idx - 1; i >= 0; i--)
		{
			if (this.children[i].inArea(_area))
			{
				elems.unshift(this.children[i]);
			}
			else
			{
				break;
			}
		}

		// Search downwards for elemwnts in the area from the matched element on
		for (var i = idx + 1; i < this.children.length; i++)
		{
			if (this.children[i].inArea(_area))
			{
				elems.push(this.children[i]);
			}
			else
			{
				break;
			}
		}
	}

	try
	{
		this.beginUpdate();

		// Call the setViewArea function of visible child elements
		// Imporant: The setViewArea function has to work on a copy of children,
		// as the container may start to remove themselves or add new elements using
		// the insertAfter function.
		for (var i = 0; i < elems.length; i++)
		{
			elems[i].setViewArea(_area, true);
		}
	}
	finally
	{
		this.endUpdate(_recPrev);
	}
}

function egwGridViewGrid_addIconHeightToAvg(_value)
{
	this.avgIconCnt++;

	var frac = 1.0 / this.avgIconCnt;
	this.avgIconHeight = this.avgIconHeight * (1 - frac) + _value * frac;
}

/** -- egwGridViewRow Class -- **/

function egwGridViewRow(_grid, _heightChangeProc, _item)
{
	var container = new egwGridViewContainer(_grid, _heightChangeProc);

	// Copy the item parameter, which is used when fetching data from the data
	// source
	container.item = _item;

	// Set a few new functions/properties
	container.isOdd = 0;
	container.aoiSetup = egwGridViewRow_aoiSetup;
	container.getAOI = egwGridViewRow_getAOI;
	container._columnClick = egwGridViewRow__columnClick;
	container._checkboxClick = egwGridViewRow__checkboxClick;
	container.setOpen = egwGridViewRow_setOpen;
	container.reloadChildren = egwGridViewRow_reloadChildren;
	container.tdObjects = [];
	container.containerClass = "row";
	container.childGrid = null;
	container.opened = false;
	container.rowClass = "";
	container.checkbox = null;

	// Overwrite the inherited abstract functions
	container.doInsertIntoDOM = egwGridViewRow_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewRow_doSetViewArea;
	container.doUpdateData = egwGridViewRow_doUpdateData;

	return container;
}

/**
 * Creates AOI instance of the item and overwrites all necessary functions.
 */
function egwGridViewRow_aoiSetup()
{
	this.aoi = new egwActionObjectInterface();

	// The default state of an aoi is EGW_AO_STATE_NORMAL || EGW_AO_STATE_VISIBLE -
	// egwGridItems are not necessarily visible by default
	this.aoi._state = EGW_AO_STATE_NORMAL;
	this.aoi.row = this;
	this.aoi.doSetState = egwGridViewRow_aoiSetState;
	this.aoi.doTriggerEvent = egwGridViewRow_aoiTriggerEvent;
	this.aoi.doMakeVisible = egwGridViewRow_aoiMakeVisible;
	this.aoi.getDOMNode = egwGridViewRow_aoiGetDOMNode;
}

function egwGridViewRow_aoiSetState(_state, _shiftState)
{
	if (this.row.parentNode)
	{
		var selected = egwBitIsSet(_state, EGW_AO_STATE_SELECTED);
		this.row.parentNode.toggleClass("selected", selected);
		this.row.parentNode.toggleClass("focused", egwBitIsSet(_state,
			EGW_AO_STATE_FOCUSED));

		// Set the checkbox checked-state with the selected state
		if (this.row.checkbox)
		{
			this.row.checkbox.attr("checked", selected);
		}
	}
}

function egwGridViewRow_aoiGetDOMNode()
{
	return this.row.parentNode ? this.row.parentNode.context : null;
}

function egwGridViewRow_aoiTriggerEvent(_event, _data)
{
	if (_event == EGW_AI_DRAG_OVER)
	{
		this.row.parentNode.addClass("draggedOver");
	}

	if (_event == EGW_AI_DRAG_OUT)
	{
		this.row.parentNode.removeClass("draggedOver");
	}
}

function egwGridViewRow_aoiMakeVisible()
{
	egwGridView_scrollToArea(this.row.grid.getOuter().grid.scrollarea,
		this.row.getAbsoluteArea());
}

/**
 * Returns the actionObjectInterface object of this grid item.
 */
function egwGridViewRow_getAOI()
{
	return this.aoi;
}

function egwGridViewRow__columnClick(_shiftState, _column)
{
	var state = this.aoi.getState();
	var isSelected = egwBitIsSet(state, EGW_AO_STATE_SELECTED);

	switch (this.grid.selectmode)
	{
		case EGW_SELECTMODE_DEFAULT:
			this.aoi.updateState(EGW_AO_STATE_SELECTED,
				!egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI) || !isSelected,
				_shiftState);
			break;
		case EGW_SELECTMODE_TOGGLE:
			this.aoi.updateState(EGW_AO_STATE_SELECTED, !isSelected,
				egwSetBit(_shiftState, EGW_AO_SHIFT_STATE_MULTI, true));
			break;
	}
}

function egwGridViewRow__checkboxClick()
{
	this.aoi.updateState(EGW_AO_STATE_SELECTED, this.checkbox.is(":checked"),
		EGW_AO_SHIFT_STATE_MULTI);

	return false;
}

var
	EGW_GRID_VIEW_ROW_BORDER = false;

function egwGridViewRow_doInsertIntoDOM()
{
	this.parentNode.empty();
	this.parentNode.addClass("row");

	// Setup the aoi and inform the item about it
	if (!this.aoi)
	{
		this.aoiSetup();
		this.item.setGridViewObj(this);
	}

	for (var i = 0; i < this.columns.length; i++)
	{
		var col = this.columns[i];

		var td = $j(document.createElement("td"));
		td.addClass(col.tdClass);

		var cont = $j(document.createElement("div"));
		cont.addClass(col.divClass);
		cont.addClass("innerContainer");

		this.parentNode.append(td);

		// Assign the click event to the column
		td.mousedown(egwPreventSelect);
		if (col.type == EGW_COL_TYPE_CHECKBOX)
		{
			this.checkbox = $j(document.createElement("input"));
			this.checkbox.attr("type", "checkbox");
			this.checkbox.attr("checked", egwBitIsSet(this.aoi.getState(),
				EGW_AO_STATE_SELECTED));
			this.checkbox.change(this, function(e) {
				e.data._checkboxClick();
				return false;
			});

			cont.append(this.checkbox);
		}
		else
		{
			td.click({"item": this, "col": col.id}, function(e) {
				// Reset the browser focus, so that key navigation will work
				// properly
				egwUnfocus();
				this.onselectstart = null;
				if (!e.data.item.checkbox || this != e.data.item.checkbox.context)
				{
					e.data.item._columnClick(egwGetShiftState(e), e.data.col);
				}
			});
		}

		td.append(cont);

		// Store the column in the td object array
		this.tdObjects.push({
			"td": td,
			"cont": cont,
			"ts": 0
		});
	}

	this.doUpdateData(true);

	this.checkViewArea();
}

function egwGridViewRow_doUpdateData(_immediate)
{
	var ids = [];
	var vis_cnt = 0;
	for (var i = 0; i < this.columns.length; i++)
	{
		if (this.columns[i].visible)
		{
			ids.push(this.columns[i].id);
			vis_cnt++;
		}
	}

	var data = this.item.getData(ids);
	var vis_idx = 0;

	// Set the row class
	if (this.rowClass != this.item.rowClass)
	{
		if (this.rowClass != "")
		{
			this.parentNode.removeClass(this.rowClass);
		}

		this.parentNode.addClass(this.item.rowClass);
		this.rowClass = this.item.rowClass;
	}

	// Set the column data
	for (var i = 0; i < this.tdObjects.length; i++)
	{
		var col = this.columns[i];

		if (col.visible)
		{
			vis_idx++;

			var cont = this.tdObjects[i].cont;
			if (typeof data[col.id] != "undefined")
			{
				// If the timestamp of the tdObject and the data is still the
				// same we don't have to update
				if (this.tdObjects[i].ts == data[col.id].time)
				{
					continue;
				}

				// Update the timestamp
				this.tdObjects[i].ts = data[col.id].time;

				if (col.type == EGW_COL_TYPE_NAME_ICON_FIXED)
				{
					cont.empty();
					// Insert the indentation spacer
					var depth = this.item.getDepth() - 1;
					if (depth > 0)
					{
							// Build the indentation object
						var indentation = $j(document.createElement("span"));
						indentation.addClass("indentation");
						indentation.css("width", (depth * 20) + "px");
						cont.append(indentation);
					}

					// Insert the open/close arrow
					var arrow = $j(document.createElement("span"));
					arrow.addClass("arrow");
					if (this.item.canHaveChildren)
					{
						arrow.addClass(this.item.opened ? "opened" : "closed");
						arrow.click(this, function(e) {
							$this = $j(this);

							if (!e.data.opened)
							{
								$this.addClass("opened");
								$this.removeClass("closed");
							}
							else
							{
								$this.addClass("closed");
								$this.removeClass("opened");
							}

							e.data.setOpen(!e.data.opened);

							return false; // Don't bubble this event
						});
						arrow.dblclick(function() {return false;});
					}
					cont.append(arrow);

					// Insert the icon
					if (data[col.id].iconUrl)
					{
						// Build the icon container
						var iconContainer = $j(document.createElement("span"));
						iconContainer.addClass("iconContainer " + this.grid.uniqueId);

						// Default the iconContainer height to the average height - this attribute
						// is removed from the row as soon as the icon is loaded
						iconContainer.css("min-height", this.grid.avgIconHeight + "px");

						// Build the icon
						var overlayCntr = $j(document.createElement("span"));
						overlayCntr.addClass("iconOverlayContainer");

						var icon = $j(document.createElement("img"));
						if (this.item.iconSize)
						{
							icon.css("height", this.item.iconSize + "px");
							icon.css("width", this.item.iconSize + "px"); //has to be done because of IE :-(
						}
						icon.load({"item": this, "cntr": iconContainer}, function(e) {
							e.data.cntr.css("min-height", "");
							var icon = $j(this);
							window.setTimeout(function() {
								e.data.item.grid.setIconWidth(icon.width());
								e.data.item.grid.addIconHeightToAvg(icon.height());
							}, 100);
							e.data.item.callHeightChangeProc();
						});
						
						icon.attr("src", data[col.id].iconUrl);

						overlayCntr.append(icon);

						if (this.item.iconOverlay.length > 0)
						{
							var overlayCntr2 = $j(document.createElement("span"));
							overlayCntr2.addClass("overlayContainer");
							for (var i = 0; i < this.item.iconOverlay.length; i++)
							{
								var overlay = $j(document.createElement("img"));
								overlay.addClass("overlay");
								overlay.attr("src", this.item.iconOverlay[i]);
								overlayCntr2.append(overlay);
							}
							overlayCntr.append(overlayCntr2);
						}

						icon.addClass("icon");
						iconContainer.append(overlayCntr);
						cont.append(iconContainer);
					}

					// Build the caption
					if (data[col.id].caption)
					{
						var caption = $j(document.createElement("span"));
						caption.addClass("caption");
						caption.html(data[col.id].caption);
						cont.append(caption);
					}
				}
				else if (col.type == EGW_COL_TYPE_CHECKBOX)
				{
					var checked = (data[col.id].data === 0) ?
						egwBitIsSet(this.aoi.getState(), EGW_AO_STATE_SELECTED) :
						data[col.id].data;
					this.checkbox.attr("checked", checked);
					this.item.actionObject.setSelected(checked);
				}
				else
				{
					cont.empty();
					cont.html(data[col.id].data);
				}
				cont.toggleClass("queued", false);
			}
			else
			{
				cont.empty();
				cont.toggleClass("queued", true);
			}
		}
	}

	// Set the open state
	this.setOpen(this.item.opened);

	// If the call is not from inside the doInsertIntoDOM function, we have to
	// inform the parent about a possible height change
	if (!_immediate && (this.height || this.assumedHeight))
	{
		this.callHeightChangeProc();
	}
}

function egwGridViewRow_doSetViewArea()
{
	if (this.updated)
	{
		this.updated = false;
		this.doUpdateData(false);
	}
}

function egwGridViewRow_setOpen(_open, _force)
{
	if (typeof _force == "undefined")
	{
		_force = false;
	}

	if (_open != this.opened || _force)
	{
		var inserted = false;

		if (_open)
		{
			if (!this.childGrid)
			{
				// Get the arrow and put it to "loading" state
				var arrow = $j(".arrow", this.parentNode);
				arrow.removeClass("closed");
				arrow.addClass("loading");

				// Create the "child grid"
				var childGrid = null;
				this.childGrid = childGrid = this.grid.insertContainer(this.index, egwGridViewGrid,
					false);
				inserted = true;
				this.childGrid.setVisible(false);
				var spacer = this.childGrid.insertContainer(-1, egwGridViewSpacer,
					this.grid.getOuter().avgRowHeight);
				this.item.getChildren(function(_children) {
					arrow.removeClass("loading");
					arrow.removeClass("closed");
					arrow.addClass("opened");
					spacer.setItemList(_children);
					childGrid.setVisible(true);
				});
			}
		}

		if (this.childGrid && !inserted)
		{
			if (!_open)
			{
				// Deselect all childrens
				for (var i = 0; i < this.item.children.length; i++)
				{
					this.item.children[i].actionObject.setAllSelected(false);
				}
			}

			this.childGrid.setVisible(_open);
		}

		this.opened = _open;
		this.item.opend = _open;
	}
}

function egwGridViewRow_reloadChildren()
{
	// Remove the child grid container
	if (this.childGrid)
	{
		this.grid.removeContainer(this.childGrid);
		this.childGrid = null;

		// Remove all the data from the data object
		this.item.empty();

		// Recreate the child grid
		this.setOpen(this.opened, true);
	}
}


/** -- egwGridViewSpacer Class -- **/

function egwGridViewSpacer(_grid, _heightChangeProc, _itemHeight)
{
	if (typeof _itemHeight == "undefined")
	{
		_itemHeight = 20;
	}

	var container = new egwGridViewContainer(_grid, _heightChangeProc);

	// Add some new functions/properties to the container
	container.itemHeight = _itemHeight;
	container.domNode = null;
	container.items = [];
	container.setItemList = egwGridViewSpacer_setItemList;
	container.containerClass = "spacer";

	// Overwrite the inherited functions
	container.doInsertIntoDOM = egwGridViewSpacer_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewSpacer_doSetViewArea;

	return container;
}

function egwGridViewSpacer_setItemList(_items)
{
	this.items = _items;

	if (this.domNode)
	{
		this.domNode.css("height", (this.items.length * this.itemHeight) + "px");
		this.callHeightChangeProc();
	}
}

/**
 * Creates the spacer DOM-Node and inserts it into the DOM-Tree.
 */
function egwGridViewSpacer_doInsertIntoDOM()
{
	this.domNode = $j(document.createElement("td"));
	this.domNode.addClass("egwGridView_spacer");
	this.domNode.addClass(this.grid.getOuter().uniqueId + "_spacer_fullRow");
	this.domNode.css("height", (this.items.length * this.itemHeight) + "px");
	this.domNode.attr("colspan", this.columns.length);

	this.parentNode.append(this.domNode);
}

/**
 * Checks which elements this spacer contains are inside the given area and
 * creates those.
 */
function egwGridViewSpacer_doSetViewArea()
{
	if (this.items.length > 0)
	{
		var avgHeight = this.grid.getOuter().avgRowHeight;

		// Get all items which are in the view area
		var top = Math.max(0, Math.floor(this.viewArea.top / this.itemHeight));
		var bot = Math.min(this.items.length, Math.ceil(this.viewArea.bottom / this.itemHeight));

		// Split the item list into three parts
		var it_top = this.items.slice(0, top);
		var it_mid = this.items.slice(top, bot);
		var it_bot = this.items.slice(bot, this.items.length);

		this.items = [];
		var idx = this.index;

		// Insert the new rows in the parent grid in front of the spacer container
		for (var i = it_mid.length - 1; i >= 0; i--)
		{
			this.grid.insertContainer(idx - 1, it_mid[i].type, it_mid[i]);
		}

		// If top was greater than 0, insert a new spacer in front of the newly
		// created elements.
		if (it_top.length > 0)
		{
			// this.itemHeight has to be passed to the new top spacer - otherwise the
			// scroll position might change and we'll go into a nasty setViewArea
			// loop.
			var spacer = this.grid.insertContainer(idx - 1, egwGridViewSpacer, this.itemHeight);
			spacer.setItemList(it_top);
		}

		// If there are items left at the bottom of the spacer, set theese as items of this spacer
		if (it_bot.length > 0)
		{
			// The height of this (the bottom) spacer can be set to the average height
			this.itemHeight = avgHeight;
			this.setItemList(it_bot);
		}
		else
		{
			this.grid.removeContainer(this);
		}
	}
}




/** -- egwGridViewFullRow Class -- **/

/**
 * The egwGridViewFullRow Class has only one td which contains a single caption
 */

function egwGridViewFullRow(_grid, _heightChangeProc, _item)
{
	var container = new egwGridViewContainer(_grid, _heightChangeProc);

	// Copy the item parameter, which is used when fetching data from the data
	// source
	container.item = _item;

	// Set a few new functions/properties - use the row aoi functions
	container.aoiSetup = egwGridViewRow_aoiSetup;
	container.getAOI = egwGridViewRow_getAOI;
	container.containerClass = "row";
	container._columnClick = egwGridViewRow__columnClick;
	container.td = null;
	container.cont = null;

	// Overwrite the inherited abstract functions
	container.doInsertIntoDOM = egwGridViewFullRow_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewFullRow_doSetViewArea;
	container.doUpdateData = egwGridViewFullRow_doUpdateData;

	return container;
}

function egwGridViewFullRow_doInsertIntoDOM()
{
	this.parentNode.empty();
	this.parentNode.addClass("row");
	this.parentNode.addClass("fullRow");

	// Setup the aoi and inform the item about it
	if (!this.aoi)
	{
		this.aoiSetup();
		this.item.setGridViewObj(this);
	}

	var td = this.td = $j(document.createElement("td"));
	td.attr("colspan", this.columns.length);

	var cont = this.cont = $j(document.createElement("div"));
	cont.addClass("innerContainer");
	cont.addClass(this.grid.getOuter().uniqueId + '_div_fullRow');

	td.append(cont);
	this.parentNode.append(td);

	this.doUpdateData(true);

	this.checkViewArea();
}

function egwGridViewFullRow_doUpdateData(_immediate)
{
	this.cont.empty();

	if (this.item.caption)
	{
		// Insert the indentation spacer
		var depth = this.item.getDepth();
		if (depth > 0)
		{
				// Build the indentation object
			var indentation = $j(document.createElement("span"));
			indentation.addClass("indentation");
			indentation.css("width", (depth * 20) + "px");
			this.cont.append(indentation);
		}

		// Insert the caption
		var caption = $j(document.createElement("span"));
		caption.addClass("caption");
		caption.html(this.item.caption);
		this.cont.append(caption);
	}

	// If the call is not from inside the doInsertIntoDOM function, we have to
	// inform the parent about a possible height change
	if (!_immediate && (this.height || this.assumedHeight))
	{
		this.callHeightChangeProc();
	}
}

function egwGridViewFullRow_doSetViewArea()
{
	//
}

/**
 * Temporary AOI which has to be assigned to invisible grid objects in order
 * to give them the possiblity to make them visible when using e.g. keyboard navigation
 */
function egwGridTmpAOI(_grid, _index)
{
	var aoi = new egwActionObjectDummyInterface();

	// Assign the make visible function
	aoi.grid = _grid;
	aoi.index = _index;
	aoi.doMakeVisible = egwGridTmpAOI_makeVisible;

	return aoi;
}

function egwGridTmpAOI_makeVisible()
{
	// Assume an area for the element (this code is not optimal, but it should
	// work in most cases - problem is that the elements in the grid may have equal
	// sizes and the grid is scrolled to some area where the element is not)
	var avgHeight = this.grid.getOuter().avgRowHeight;
	var area = egwArea(this.grid.getAbsolutePosition() + this.index * avgHeight,
		avgHeight);

	egwGridView_scrollToArea(this.grid.getOuter().grid.scrollarea, area);
}

function egwGridView_scrollToArea(_scrollarea, _visarea)
{
	// Get the current view area
	var va = egwArea(_scrollarea.scrollTop(), _scrollarea.height());

	// Calculate the assumed position of this element
	var pos = _visarea;

	// Check whether it is currently (completely) visible, if not scroll the
	// scroll area to that position
	if (!(pos.top >= va.top && pos.bottom <= va.bottom))
	{
		if (pos.top < va.top)
		{
			_scrollarea.scrollTop(pos.top);
		}
		else
		{
			_scrollarea.scrollTop(va.top + pos.bottom - va.bottom);
		}
	}
}

