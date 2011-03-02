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
 * Contains classes which are able to display an dynamic data view which
 */

/*
uses
	egw_action,
	egw_action_common,
	egw_menu,
	jquery;
*/

/**
 * Main class for the grid view. The grid view is a combination of a classic
 * list view with multiple columns and the tree view.
 *
 * @param object _parentNode is the DOM-Node the grid-view should be inserted into
 * @param array _columns is an array of all colloumns the grid should be able to
 * 	display.
 * TODO: A column should be an object and become much more mighty (sorting,
 * 	visibility, etc.)
 */
function egwGrid(_parentNode, _columns)
{
	this.parentNode = _parentNode;
	this.columns = _columns;

	this.updateElems = [];
	this.children = [];
	this.inUpdate = false;
	this.tbody = null;

	// Append the grid base elements to the parent node
	$(this.parentNode).append(this._buildBase());
}

/**
 * Adds a new item to the grid and returns it
 *
 * @param string _id is an unique identifier of the new grid item. It can be searched
 * 	lateron with the "getItemById" function.
 * @param string _caption is the caption of the new element
 * @param string _icon is the URL to the icon image
 * @param object _columns is an object which can contain string entries with html
 * 	for every column_id available in the grid.
 * @returns object the newly created egwGridItem
 */
egwGrid.prototype.addItem = function(_id, _caption, _icon, _columns)
{
	var item = new egwGridItem(this, null, _id, _caption, _icon, _columns);
	this.children.push(item);
	this.update(item);

	return item;
}

/**
 * Updates the given element - if the element is visible, it is added to the
 * DOM-Tree if it hasn't been attached to it yet. If the object is already in
 * the DOM-Tree, it will be rebuilt, which means, that the DOM-Data of its row
 * will be replaced by a new one.
 * If multiple updates are in progress, it is wise to group those by using
 * the beginUpdate and endUpdate functions, as this saves some redundancy
 * like re-colorizing the rows if a new one has been added.
 *
 * @param object _elem is the egwGridItem object, which will be updated
 */
egwGrid.prototype.update = function(_elem)
{
	if (_elem.isVisible())
	{
		if (!this.inUpdate)
		{
			if (this._updateElement(_elem))
			{
				this._colorizeRows();
			}
		}
		else
		{
			if (this.updateElems.indexOf(_elem) == -1)
			{
				this.updateElems.push(_elem);
			}
		}
	}
}

/**
 * Starts a group of updates: After beginUpdate has been called, the update function
 * will collect the update wishes and execute them as soon as endUpdate is called.
 * This brings a major performance improvement if lots of elements are added.
 */
egwGrid.prototype.beginUpdate = function()
{
	this.inUpdate = true;
}

/**
 * Ends the update grouping and actually executes the updates.
 */
egwGrid.prototype.endUpdate = function()
{
	// Call the update function for all elements which wanted to be updated
	// since the last beginUpdate call.
	var added = false;
	this.inUpdate = false;
	for (var i = 0; i < this.updateElems.length; i++)
	{
		added = this._updateElement(this.updateElems[i]) || added;
	}
	this.updateElems = [];

	// If elements have been (visibly) added to the tree, call the colorize rows
	// function.
	if (added)
	{
		this._colorizeRows();
	}
}

/**
 * Builds the base DOM-Structure of the grid and returns the outer object.
 */
egwGrid.prototype._buildBase = function()
{
	var table = $(document.createElement("table"));
	table.addClass("grid");

	this.tbody = $(document.createElement("tbody"));
	table.append(this.tbody);

	this.tbody.append(this._buildHeader());

	return table;
}

/**
 * Builds the header row and returns it
 */
egwGrid.prototype._buildHeader = function()
{
	var row = document.createElement("tr");

	for (var i = 0; i < this.columns.length; i++)
	{
		var column = $(document.createElement("th"));
		if (i == 0)
		{
			column.addClass("front");
		}
		column.html(this.columns[i].caption);
		if (typeof this.columns[i].width != "undefined")
		{
			column.css("width", this.columns[i].width);
		}
		$(row).append(column);
	}

	return row;
}

/**
 * Gives odd rows the additional "odd" CSS-class which creates the "zebra" structure.
 */
egwGrid.prototype._colorizeRows = function()
{
	this.tbody.children().removeClass("odd");
	$("tr:not(.hidden):odd", this.tbody).addClass("odd");
}

/**
 * Internal function which actually performs the update of the given element as
 * it is described in the update function.
 */
egwGrid.prototype._updateElement = function(_elem)
{
	// If the element has to be inserted into the dom tree first, search for the
	// proper position:
	var insertAfter = null;
	var oldRow = null;
	if (_elem.isVisible())
	{
		if (!_elem.domData)
		{
			var parentChildren = _elem.parent ? _elem.parent.children : this.children;
			var index = _elem.index();

			// Fetch the node after which this one should be inserted
			if (index > 0)
			{
				insertAfter = parentChildren[index - 1].lastInsertedChild().domData.row;
			}
			else
			{
				insertAfter = _elem.parent ? _elem.parent.domData.row :
					this.tbody.children().get(0);
			}
		}

		// Check whether the element already has a row attached to it
		var row = _elem.buildRow(_elem.domData ? _elem.domData.row : null);

		// Insert the row after the fetched element
		if (insertAfter)
		{
			$(row).insertAfter(insertAfter);
			return _elem.isVisible();
		}
	}

	return false;
}



/** egwGridItem Class **/

/**
 * The egwGridItem represents a single row inside an egwGrid. Each egwGridItem
 * contains an egwActionObjectInterface-Object (can be recieved by calling getAOI()),
 * which is used to interconnect with the egw_action framework.
 *
 * Don't create new egwGridItems yourself, use the addItem functions supplied by
 * egwGrid and egwGridItem.
 *
 * @param object _grid is the parent egwGrid object
 * @param object _parent is the parent egwGridItem. Null if no parent exists.
 * @param string _caption is the caption of the grid item
 * @param string _icon is the url to the icon image
 * @param object _columns is an object which can contain string entries with html
 * 	for every column_id available in the grid.
 * TODO: Remove _canHaveChildren and replace with something better
 */
function egwGridItem(_grid, _parent, _id, _caption, _icon, _columns, _canHaveChildren)
{
	if (typeof _canHaveChildren == "undefined")
	{
		_canHaveChildren = true;
	}

	// Setup the egwActionObjectInterface
	this._setupAOI();

	this.clickCallback = null;

	this.grid = _grid;
	this.parent = _parent;
	this.id = _id;
	this.caption = _caption;
	this.icon = _icon;
	this.columns = _columns;
	if (_canHaveChildren)
	{
		this.children = [];
	}
	else
	{
		this.children = false;
	}

	this.opened = false;
	this.domData = null;
}

/**
 * Creates AOI instance of the item and overwrites all necessary functions.
 */
egwGridItem.prototype._setupAOI = function()
{
	this.aoi = new egwActionObjectInterface;

	// The default state of an aoi is EGW_AO_STATE_NORMAL || EGW_AO_STATE_VISIBLE -
	// egwGridItems are not necessarily visible by default
	this.aoi._state = EGW_AO_STATE_NORMAL;

	this.aoi.gridItem = this;

	this.aoi.doSetState = gridAOIDoSetState;
	this.aoi.getDOMNode = gridAOIGetDOMNode;
}

function gridAOIDoSetState(_state, _shiftState)
{
	if (this.gridItem.domData)
	{
		$(this.gridItem.domData.row).toggleClass("selected", egwBitIsSet(_state,
			EGW_AO_STATE_SELECTED));
		$(this.gridItem.domData.row).toggleClass("focused", egwBitIsSet(_state,
			EGW_AO_STATE_FOCUSED));
	}
}

function gridAOIGetDOMNode()
{
	return this.gridItem.domData ? this.gridItem.domData.row : null;
}



/**
 * Returns the actionObjectInterface object of this grid item.
 */
egwGridItem.prototype.getAOI = function()
{
	return this.aoi;
}

/**
 * Returns whether the grid item is actually visible - in this case this means,
 * whether the element is a child of an item which is currently not opened.
 */
egwGridItem.prototype.isVisible = function()
{
	return (this.parent ? this.parent.opened && this.parent.isVisible() : true);
}

/**
 * Returns the depth of this entry inside the item tree.
 */
egwGridItem.prototype.getDepth = function()
{
	return (this.parent ? this.parent.getDepth() + 1 : 0);
}

/**
 * Returns the last child which is inserted into the DOM-Tree. Used by the update
 * function to determine where new rows should be inserted into the dom tree.
 */
egwGridItem.prototype.lastInsertedChild = function()
{
	for (var i = (this.children.length - 1); i >= 0; i--)
	{
		if (this.children[i].domData)
		{
			return this.children[i].lastInsertedChild();
		}
	}

	return this;
}

/**
 * Adds a new child item to this item. Parameters are equivalent to those of
 * egwGrid.addItem.
 */
egwGridItem.prototype.addItem = function(_id, _caption, _icon, _columns)
{
	//If the element was not designed to have children, update it in the grid
	if (!this.children)
	{
		this.children = [];
		this.grid.update(this);
	}

	var item = new egwGridItem(this.grid, this, _id, _caption, _icon, _columns);
	this.children.push(item);
	this.grid.update(item);

	return item;
}

/**
 * Returns the position of this element inside it's parent children list.
 */
egwGridItem.prototype.index = function()
{
	if (this.parent)
	{
		return this.parent.children.indexOf(this);
	}
	else
	{
		return this.grid.children.indexOf(this);
	}
}

/**
 * Internal function which updates the visibility of this item and its children
 * if the open state of the element is changed.
 */
egwGridItem.prototype._updateVisibility = function(_visible)
{
	// Set the visibility of this object - if it is at all inserted in the dom-
	// tree.
	if (this.domData)
	{
		$(this.domData.row).toggleClass("hidden", !_visible);

		// Deselect this row, if it is no longer visible
		this.aoi.updateState(EGW_AO_STATE_VISIBLE, _visible);

		// Update the visibility of all children
		for (var i = 0; i < this.children.length; i++)
		{
			this.children[i]._updateVisibility(_visible && this.opened);
		}
	}
}

/**
 * Toggles the visibility of the child elements.
 *
 * @param boolean _open specifies whether this item is opened or closed.
 */
egwGridItem.prototype.setOpened = function(_open)
{
	var self = this;
	function doSetOpen()
	{
		// Set the arrow direction
		if (_open)
		{
			self.domData.arrow.addClass("opened");
			self.domData.arrow.removeClass("closed");
		}
		else
		{
			self.domData.arrow.addClass("closed");
			self.domData.arrow.removeClass("opened");
		}

		// Update all DOM rows
		if (_open)
		{
			self.grid.beginUpdate();
			for (var i = 0; i < self.children.length; i++)
			{
				var child = self.children[i];
				if (!child.domData)
				{
					self.grid.update(child);
				}
			}
			self.grid.endUpdate();
		}

		// And make them (in)visible
		self._updateVisibility(true);

		self.grid._colorizeRows();
	}

	if (this.opened != _open && this.children !== false)
	{
		this.opened = _open;
		if (this.children.length == 0 && _open)
		{
//			alert("JSON Callback")
		}
		else
		{
			doSetOpen();
		}
	}
}


/**
 * Builds the actual DOM-representation of the item and attaches all events to the
 * DOM-Nodes.
 *
 * @param object _row If an existing DOM-Item should be updated, it can be passed
 * 	here and the updated objects will be inserted inside of the row object. Defaults
 * 	to null.
 */
egwGridItem.prototype.buildRow = function(_row)
{
	// Build the container row
	var row = null;
	if (typeof _row == "undefined" || !_row)
	{
		row = document.createElement("tr");
	}
	else
	{
		row = _row;
		$(row).empty();
	}
	$(row).toggleClass("hidden", !this.isVisible())

	// Build the indentation object
	var indentation = $(document.createElement("span"));
	indentation.addClass("indentation");
	indentation.css("width", (this.getDepth() * 12) + "px");

	// Build the arrow element
	var arrow = $(document.createElement("span"));
	arrow.addClass("arrow");
	if (this.children !== false)
	{
		arrow.addClass(this.opened ? "opened" : "closed");
		arrow.click(this, function(e) {
			e.data.setOpened(!e.data.opened);
			return false;
		});
	}

	// Build the icon element
	icon = $(document.createElement("img"));
	if (this.icon)
	{
		icon.attr("src", this.icon);
	}
	icon.addClass("icon");

	// Build the caption
	var caption = $(document.createElement("span"));
	caption.text(this.caption);
	caption.addClass("caption");

	// Build the td surrounding those elements
	var column_caption = $(document.createElement("td"));
	column_caption.append(indentation, arrow, icon, caption);
	column_caption.mousedown(egwPreventSelect);
	column_caption.click(this, function(e) {
		egwResetPreventSelect(this);
		this.onselectstart = null;
		e.data._columnClick(egwGetShiftState(e), 0);
	});

	// Append the column to the row
	$(row).append(column_caption);

	for (var i = 1; i < this.grid.columns.length; i++) // Skips the front column
	{
		var content = "";
		var gridcol = this.grid.columns[i];

		if (typeof this.columns[gridcol.id] != "undefined")
		{
			content = this.columns[gridcol.id];
		}
		else
		{
			if (typeof gridcol["default"] != "undefined")
			{
				content = gridcol["default"];
			}
		}

		// Create a column and append it to the row
		var column = $(document.createElement("td"));
		column.html(content);
		column.mousedown(egwPreventSelect);
		column.click({"item": this, "col": gridcol.id}, function(e) {
			egwPreventSelect(this);
			e.data.item._columnClick(egwGetShiftState(e), e.data.col);
		});
		$(row).append(column);
	}

	this.domData = {
		"row": row,
		"arrow": arrow,
		"icon": icon,
		"caption": caption
	}

	// The item is now visible
	this.aoi.updateState(EGW_AO_STATE_VISIBLE, true);

	return row;
}

egwGridItem.prototype._columnClick = function(_shiftState, _column)
{
	var state = this.aoi.getState();
	var isSelected = egwBitIsSet(state, EGW_AO_STATE_SELECTED);

	this.aoi.updateState(EGW_AO_STATE_SELECTED, 
		!egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI) || !isSelected,
		_shiftState);
}

