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

//TODO (minor): Do auto cleanup - remove elements from the view again after they
//	haven't been viewed for a certain time.

/*
uses
	egw_action_common,
	egw_action_data,
	jquery;
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

/**
 * Base view class which is responsible for displaying a grid view element.
 *
 * @param object _parentNode is the DOM-Node into which the grid view will be inserted
 * @param object _data is the data-provider object which contains/loads the grid rows
 * 	and contains their data.
 */
function egwGridViewOuter(_parentNode, _dataRoot)
{
	this.parentNode = $(_parentNode);
	this.dataRoot = _dataRoot;

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
	this.headerHeight = 0;
	this.scrollbarWidth = 0;

	this.headerColumns = [];

	this.buildBase();
	this.parentNode.append(this.outer_table);

	// Read the scrollbar width
	this.scrollbarWidth = Math.max(10, this.getScrollbarWidth());

	// Start value for the average row height
	this.avgRowHeight = 19.0;
	this.avgRowCnt = 1;

	// Insert the base grid container into the DOM-Tree
	this.grid = new egwGridViewGrid(null, null, true, this); // (No parent grid, no height change callback, scrollable)
	this.grid.insertIntoDOM(this.outer_tr, []);
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
	if (this.avgRowCnt > 1)
	{
		var sum = this.avgRowHeight * this.avgRowCnt - _value;
		this.avgRowCnt--;
		this.avgRowCount = sum / this.avgRowCnt;
	}
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

	// Rebuild the header
	this.buildBaseHeader();

	// Set the grid width
	this.grid.outerNode.attr("colspan", _columns.length + 1);

	// Empty the grid
	this.empty();
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

	this.outer_table = $(document.createElement("table"));
	this.outer_table.addClass("egwGridView_outer");
	this.outer_thead = $(document.createElement("thead"));
	this.outer_tbody = $(document.createElement("tbody"));
	this.outer_tr = $(document.createElement("tr"));
	this.outer_head_tr = $(document.createElement("tr"));

	this.outer_table.append(this.outer_thead, this.outer_tbody);
	this.outer_tbody.append(this.outer_tr);
	this.outer_thead.append(this.outer_head_tr);
}

egwGridViewOuter.prototype.buildBaseHeader = function()
{
	// Build the "option-column", if this hasn't been done yet
	if (!this.optcol)
	{
		// Build the "select columns" icon
		this.selectcols = $(document.createElement("span"));
		this.selectcols.addClass("selectcols");

		// Build the option column
		this.optcol = $(document.createElement("th"));
		this.optcol.addClass("optcol");
		this.optcol.append(this.selectcols);
	}

	// Create the head columns
	this.outer_head_tr.empty();
	this.headerColumns = [];

	for (var i = 0; i < this.columns.length; i++)
	{
		col = this.columns[i];

		// Create the column element and insert it into the DOM-Tree
		var column = $(document.createElement("th"));
		column.html(col.caption);
		this.outer_head_tr.append(column);

		// Set the width of the column
		var border = column.outerWidth() - column.width();
		column.css("width", (col.width - border) + "px");
		col.drawnWidth = column.outerWidth();

		this.headerColumns.push(column);
	}

	// Append the option column and set its width of the last column
	this.outer_head_tr.append(this.optcol);
	
	this.optcol.css("width", this.scrollbarWidth - this.optcol.outerWidth()
		+ this.optcol.width() + 1); // The "1" is a "security pixel" which prevents a horizontal scrollbar form occuring on IE7

	this.headerHeight = this.outer_thead.height();
}

egwGridViewOuter.prototype.getScrollbarWidth = function()
{
	if (EGW_GRID_SCROLLBAR_WIDTH === false)
	{
		// Create a temporary td and two div, which are inserted into the dom-tree
		var td = $(document.createElement("td"));
		var div_outer = $(document.createElement("div"));
		var div_inner = $(document.createElement("div"));

		// The outer div has a fixed size and "overflow" set to auto. When the second
		// div is inserted, it will be forced to display a scrollbar.
		div_outer.css("height", "100px");
		div_outer.css("width", "100px");
		div_outer.css("overflow", "auto");

		div_inner.css("height", "1000px");

		this.outer_tr.append(td);
		td.append(div_outer);
		div_outer.append(div_inner);

		// Store the scrollbar width statically.
		EGW_GRID_SCROLLBAR_WIDTH = div_outer.outerWidth() - div_inner.outerWidth();

		// Remove the temporary elements again.
		this.outer_tr.empty();
	}

	return EGW_GRID_SCROLLBAR_WIDTH;
}

egwGridViewOuter.prototype.setHeight = function(_h)
{
	this.grid.setScrollHeight(_h - this.headerHeight);
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
		$(this.parentNode).toggleClass("hidden", !_visible);

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
		this.parentNode = $(_parentNode);

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
	var relArea = {
		"top": _area.top - this.position,
		"bottom": _area.bottom - this.position
	};

	this.viewArea = relArea;

	if (this.containerClass == "grid" && this.grid != null)
	{
//		console.log(this, _area, this.viewArea);
	}

	this.checkViewArea(_force);
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
 *
 * TODO: This function consumes 70-80% of the update time! Do something to improve
 * 	this!
 */
egwGridViewContainer.prototype.getHeight = function()
{
	if (this.visible && this.parentNode)
	{
		if (this.height === false && this.assumedHeight === false)
		{
			this.height = this.parentNode.outerHeight();
		}

		return this.height !== false ? this.height : this.assumedHeight;
	}
	else
	{
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
	container.children = [];
	container.outer = _outer;
	container.containerClass = "grid";
	container.avgIconHeight = 16;
	container.avgIconCnt = 1;

	// Overwrite the abstract container interface functions
	container.invalidateHeightCache = egwGridViewGrid_invalidateHeightCache;
	container.getHeight = egwGridViewGrid_getHeight;
	container.doInsertIntoDOM = egwGridViewGrid_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewGrid_doSetviewArea;

	return container;
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

	this.outerNode = $(document.createElement("td"));
	this.outerNode.addClass("frame");

	if (this.scrollable)
	{
		this.scrollarea = $(document.createElement("div"));
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

	var table = $(document.createElement("table"));
	table.addClass("egwGridView_grid");

	this.innerNode = $(document.createElement("tbody"));

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
				child.invalidateHeightCache();
				var newHeight = child.getHeight();

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

				if (this.grid != null)
				{
//					console.log(child, delta, newHeight, oldHeight);
				}

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
	else
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
	this.beginUpdate();
	try
	{
		this.didUpdate = true;

		var container = new _class(this, this.heightChangeHandler, _params);

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
		var tr = $(document.createElement("tr"));

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

		return container;
	}
	finally
	{
		this.endUpdate();
	}

	this.callHeightChangeProc();
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
}

function egwGridViewGrid_addContainer(_class)
{
	// Insert the container at the beginning of the list.
	this.insertContainer(false, _class);
	return container;
}

function egwGridViewGrid_invalidateHeightCache(_children)
{
	if (typeof _children == "undefined")
	{
		_children = true;
	}

	// Call the inherited function
	egwGridViewContainer.prototype.invalidateHeightCache.call(this);

	if (_children)
	{
		for (var i = 0; i < this.children.length; i++)
		{
			this.children[i].invalidateHeightCache();
		}
	}
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

	if (this.grid != null)
	{
//		console.log("HCH for", _elem);
	}

	// The old height of the element is now only an assumed height - the next
	// time the "updateAssumedHeights" functions is triggered, this will be
	// updated.
	var oldHeight = _elem.assumedHeight !== false ? _elem.assumedHeight :
		(_elem.height === false ? this.getOuter().avgRowHeight : _elem.height);
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
	container.checkOdd = egwGridViewRow_checkOdd;
	container._columnClick = egwGridViewRow__columnClick;
	container.setOpen = egwGridViewRow_setOpen;
	container.tdObjects = [];
	container.containerClass = "row";
	container.childGrid = null;
	container.opened = false;

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
	this.aoi.getDOMNode = egwGridViewRow_aoiGetDOMNode;
}

function egwGridViewRow_aoiSetState(_state, _shiftState)
{
	if (this.row.parentNode)
	{
		this.row.parentNode.toggleClass("selected", egwBitIsSet(_state,
			EGW_AO_STATE_SELECTED));
		this.row.parentNode.toggleClass("focused", egwBitIsSet(_state,
			EGW_AO_STATE_FOCUSED));
	}
}

function egwGridViewRow_aoiGetDOMNode()
{
	return this.row.parentNode ? this.row.parentNode : null;
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

	this.aoi.updateState(EGW_AO_STATE_SELECTED, 
		!egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI) || !isSelected,
		_shiftState);
}

var
	EGW_GRID_VIEW_ROW_BORDER = false;

function egwGridViewRow_doInsertIntoDOM()
{
	this.parentNode.empty();

	// Setup the aoi and inform the item about it
	if (!this.aoi)
	{
		this.aoiSetup();
		this.item.setGridViewObj(this);
	}

	// Check whether this element is odd
	this.checkOdd();

	for (var i = 0; i < this.columns.length; i++)
	{
		var col = this.columns[i];
		var td = $(document.createElement("td"));

		this.parentNode.append(td);

		// Assign the click event to the column
		td.mousedown(egwPreventSelect);
		td.click({"item": this, "col": col.id}, function(e) {
			this.onselectstart = null;
			e.data.item._columnClick(egwGetShiftState(e), e.data.col);
		});

		if (i == 0)
		{
			td.addClass("first");
		}

		// Set the column width
		if (EGW_GRID_VIEW_ROW_BORDER === false)
		{
			EGW_GRID_VIEW_ROW_BORDER = td.outerWidth() - td.width();
		}
		td.css("width", col.drawnWidth - EGW_GRID_VIEW_ROW_BORDER);

		// Store the column in the td object array
		this.tdObjects.push({
			"col": col,
			"td": td
		});
	}

	this.doUpdateData(true);

	this.checkViewArea();
}

function egwGridViewRow_doUpdateData(_immediate)
{
	var ids = [];
	for (var i = 0; i < this.columns.length; i++)
	{
		ids.push(this.columns[i].id);
	}

	data = this.item.getData(ids);

	for (var i = 0; i < this.tdObjects.length; i++)
	{
		var td = this.tdObjects[i].td;
		var col = this.tdObjects[i].col;
		if (typeof data[col.id] != "undefined")
		{
			td.empty();
			if (col.type == EGW_COL_TYPE_NAME_ICON_FIXED)
			{
				// Insert the indentation spacer
				var depth = this.item.getDepth() - 1;
				if (depth > 0)
				{
						// Build the indentation object
					var indentation = $(document.createElement("span"));
					indentation.addClass("indentation");
					indentation.css("width", (depth * 12) + "px");
					td.append(indentation);
				}

				// Insert the open/close arrow
				if (this.item.canHaveChildren)
				{
					var arrow = $(document.createElement("span"));
					arrow.addClass("arrow");
					arrow.addClass(this.item.opened ? "opened" : "closed");
					arrow.click(this, function(e) {
						$this = $(this);

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
					});
					td.append(arrow);
				}

				// Insert the icon
				if (data[col.id].iconUrl)
				{
					// Build the icon element
					var icon = $(document.createElement("img"));

					// Default the image height to the average height - this attribute
					// is removed from the row as soon as the icon is loaded
					icon.attr("height", Math.round(this.grid.avgIconHeight));
					icon.attr("src", data[col.id].iconUrl);

					icon.load({"item": this, "col": td}, function(e) {
						var icon = $(this);
						icon.removeAttr("height");
						window.setTimeout(function() {
							e.data.item.grid.addIconHeightToAvg(icon.height());
						}, 100);
						e.data.item.callHeightChangeProc();
					});
					icon.addClass("icon");
					td.append(icon);
				}

				// Build the caption
				if (data[col.id].caption)
				{
					var caption = $(document.createElement("span"));
					caption.html(data[col.id].caption);
					td.append(caption);
				}
			}
			else
			{
				td.html(data[col.id]);
			}
			td.toggleClass("queued", false);
		}
		else
		{
			td.toggleClass("queued", true);
		}
	}

	// Set the open state
	this.setOpen(this.item.opened);

	// If the call is not from inside the doInsertIntoDOM function, we have to
	// inform the parent about a possible height change
	if (!_immediate)
	{
		this.callHeightChangeProc();
	}
}

function egwGridViewRow_checkOdd()
{
	if (this.item && this.parentNode)
	{
		// Update the "odd"-Class of the item
		var odd = this.item.isOdd();

		if (this.isOdd === 0 || this.isOdd != odd)
		{
			$(this.parentNode).toggleClass("odd", odd);
			this.isOdd = odd;
		}
	}
}

function egwGridViewRow_doSetViewArea()
{
	this.checkOdd();
}

function egwGridViewRow_setOpen(_open)
{
	if (_open != this.opened)
	{
		var inserted = false;

		if (_open)
		{
			if (!this.childGrid)
			{
				// Get the arrow and put it to "loading" state
				var arrow = $(".arrow", this.parentNode);
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
			this.childGrid.setVisible(_open);
		}

		this.opened = _open;
		this.item.opend = _open;
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
	this.domNode = $(document.createElement("td"));
	this.domNode.addClass("egwGridView_spacer");
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


