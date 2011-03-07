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

/**
 * View Classes for the egw grid component.
 */


/**
 * Common functions used in all classes
 */

function egwArea(_top, _height)
{
	return {
		"top": _top,
		"bottom": _top + _height
	}
}

function egwAreaIntersect(_ar1, _ar2)
{
	return ! (_ar1.bottom < _ar2.top || _ar1.top > _ar2.bottom);
}

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

/**
 * TODO
 */
function egwGridViewOuter()
{
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
	this.index = 0;
	this.viewArea = false;

	this.doInsertIntoDOM = null;
	this.doUpdateColumns = null;
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

		// While the element has been invisible, the viewarea might have changed,
		// so check it now
		this.checkViewArea();

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

egwGridViewContainer.prototype.updateColumns = function(_columns)
{
	this.columns = _columns;
	if (_parentNode)
	{
		return egwCallAbstract(this, this.doUpdateColumns, arguments);
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
 */
egwGridViewContainer.prototype.getHeight = function()
{
	if (this.visible && this.parentNode)
	{
		if (this.height === false)
		{
			this.height = this.parentNode.outerHeight();
		}
		return this.height;
	}
	else
	{
		return 0;
	}
}

egwGridViewContainer.prototype.invalidateHeightCache = function()
{
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

/**
 * egwGridViewGrid is the container for egwGridViewContainer objects, but itself
 * implements the egwGridViewContainer interface.
 */
function egwGridViewGrid(_grid, _heightChangeProc, _scrollable)
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
	container.didUpdate = false;
	container.setupContainer = egwGridViewGrid_setupContainer;
	container.insertContainer = egwGridViewGrid_insertContainer;
	container.removeContainer = egwGridViewGrid_removeContainer;
	container.addContainer = egwGridViewGrid_addContainer;
	container.heightChangeHandler = egwGridViewGrid_heightChangeHandler;
	container.setScrollHeight = egwGridViewGrid_setScrollHeight;
	container.scrollCallback = egwGridViewGrid_scrollCallback;
	container.children = [];

	// Overwrite the abstract container interface functions
	container.invalidateHeightCache = egwGridViewGrid_invalidateHeightCache;
	container.getHeight = egwGridViewGrid_getHeight;
	container.doUpdateColumns = egwGridViewGrid_doUpdateColumns;
	container.doInsertIntoDOM = egwGridViewGrid_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewGrid_doSetviewArea;

	return container;
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

	if (this.scrollable)
	{
		this.scrollarea = $(document.createElement("div"));
		this.scrollarea.addClass("egwGridView_scrollarea");
		this.scrollarea.css("height", this.scrollHeight + "px");
		this.scrollarea.scroll(this, function(e) {
			window.setTimeout(function() {
				e.data.scrollEvents++;
				e.data.scrollCallback(e.data.scrollEvents);
			}, 50);
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

var
	EGW_GRID_VIEW_EXT = 50;
	EGW_GRID_MAX_CYCLES = 10;

function egwGridViewGrid_scrollCallback(_event)
{
	if ((typeof _event == "undefined" || _event == this.scrollEvents) && this.scrollarea)
	{
		var cnt = 0;
		var area = egwArea(this.scrollarea.scrollTop() - EGW_GRID_VIEW_EXT,
			this.scrollHeight + EGW_GRID_VIEW_EXT * 2);
		do {
			cnt++;
			this.didUpdate = false;
			this.setViewArea(area);
		} while (this.didUpdate && cnt < EGW_GRID_MAX_CYCLES);

//		console.log(cnt);

		if (cnt == EGW_GRID_MAX_CYCLES)
		{
			if (this.console && this.console.info)
			{
				this.console.info("Too many update cycles. Aborting.")
			}
		}

		this.scrollEvents = 0;
	}
}

function egwGridViewGrid_insertContainer(_after, _class, _params)
{
	this.didUpdate = true;

	var container = new _class(this, this.heightChangeHandler, _params);

	var idx = this.children.length;
	if (typeof _after == "number")
	{
		idx = Math.max(-1, Math.min(this.children.length, _after)) + 1;
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
	var height = container.getHeight();
	for (var i = idx + 1; i < this.children.length; i++)
	{
		this.children[i].offsetPosition(height);
		this.children[i].index++;
	}

	return container;
}

function egwGridViewGrid_removeContainer(_container)
{
	this.didUpdate = true;

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

	this.height = false;

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

	// Get the height-change
	var oldHeight = _elem.height === false ? 0 : _elem.height;
	_elem.invalidateHeightCache(false);
	var newHeight = _elem.getHeight();
	var offs = newHeight - oldHeight;

	// Set the offset of all elements succeding the given element correctly
	for (var i = _elem.index + 1; i < this.children.length; i++)
	{
		this.children[i].offsetPosition(offs);
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

	this.doUpdateColumns();
}

function egwGridViewGrid_doUpdateColumns(_columns)
{
	this.outerNode.attr("colspan", this.columns.length);

	for (var i = 0; i < this.children.length; i++)
	{
		this.children[i].doUpdateColumns(_columns)
	}
}

function egwGridViewGrid_doSetviewArea(_area)
{
	// Do a binary search for elements which are inside the given area
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

	this.inUpdate = true;

	// Call the setViewArea function of visible child elements
	// Imporant: The setViewArea function has to work on a copy of children,
	// as the container may start to remove themselves or add new elements using
	// the insertAfter function.
	for (var i = 0; i < elems.length; i++)
	{
		elems[i].setViewArea(_area, true);
	}

	this.inUpdate = false;
}

/** -- egwGridViewRow Class -- **/

function egwGridViewRow(_grid, _heightChangeProc, _item)
{
	var container = new egwGridViewContainer(_grid, _heightChangeProc);

	// Copy the item parameter, which is used when fetching data from the data
	// source
	container.item = _item;

	// Overwrite the inherited abstract functions
	container.doInsertIntoDOM = egwGridViewRow_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewRow_doSetViewArea;
	container.doUpdateColumns = egwGridViewRow_doUpdateColumns;

	return container;
}

function egwGridViewRow_doInsertIntoDOM()
{
	this.doUpdateColumns();
}

function egwGridViewRow_doUpdateColumns()
{
	this.parentNode.empty();

	for (var i = 0; i < this.columns.length; i++)
	{
		var td = $(document.createElement("td"));
		td.text(this.item + ", col" + i);

		this.parentNode.append(td);
	}

	this.checkViewArea();
}

function egwGridViewRow_doSetViewArea()
{
	//TODO: Load the data for the columns and load it.
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

	// Overwrite the inherited functions
	container.doInsertIntoDOM = egwGridViewSpacer_doInsertIntoDOM;
	container.doSetViewArea = egwGridViewSpacer_doSetViewArea;
	container.doUpdateColumns = egwGridViewSpacer_doUpdateColumns;

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

function egwGridViewSpacer_doInsertIntoDOM()
{
	this.domNode = $(document.createElement("td"));
	this.domNode.addClass("egwGridView_spacer");
	this.domNode.css("height", (this.items.length * this.itemHeight) + "px");

	this.parentNode.append(this.domNode);

	this.doUpdateColumns();
}

function egwGridViewSpacer_doSetViewArea()
{
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
		this.grid.insertContainer(idx - 1, egwGridViewRow, it_mid[i]);
	}

	// If top was greater than 0, insert a new spacer in front of the 
	if (it_top.length > 0)
	{
		var spacer = this.grid.insertContainer(idx - 1, egwGridViewSpacer, this.itemHeight);
		spacer.setItemList(it_top)
	}

	// If there are items left at the bottom of the spacer, set theese as items of this spacer
	if (it_bot.length > 0)
	{
		this.setItemList(it_bot);
	}
	else
	{
		this.grid.removeContainer(this);
	}
}

function egwGridViewSpacer_doUpdateColumns()
{
	this.domNode.attr("colspan", this.columns.length);
}


