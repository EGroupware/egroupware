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
	egw_action;
	egw_grid_columns;
	egw_grid_data;
	egw_grid_view;
*/

function egwGrid(_parentNode, _columns, _objectManager, _fetchCallback,
	_columnChangeCallback, _context)
{
	this.parentNode = _parentNode;
	this.objectManager = _objectManager;

	this.columnChangeCallback = _columnChangeCallback;
	this.context = _context;

	this.width = 0;
	this.height = 0;

	// Create the column handler and connect its update event to this object
	this.columns = new egwGridColumns(_columns, this.columnsUpdate, this);

	// Create the read queue
	this.readQueue = new egwGridDataQueue(_fetchCallback, _context);

	this.selectedChangeCallback = null;
	this.sortColsCallback = null;

	// Create the root data element
	this.dataRoot = new egwGridDataElement("", null, this.columns, this.readQueue, 
		_objectManager);
	var self = this;
	this.dataRoot.actionObject.setSelectedCallback = function() {
		if (self.gridOuter.checkbox || self.selectedChangeCallback)
		{
			var allSelected = this.getAllSelected();
			if (self.gridOuter.checkbox)
			{
				self.gridOuter.checkbox.attr("checked", allSelected)
			}

			if (self.selectedChangeCallback)
			{
				self.selectedChangeCallback.call(self.context, allSelected);
			}
		}
	};

	// Create the outer view component and pass the dataRoot element so that
	// the grid outer element will be capable of fetching the root data and
	// can create a spacer for that.
	this.gridOuter = new egwGridViewOuter(_parentNode, this.dataRoot,
		this.selectcolsClick, this.toggleAllClick, this.sortColsClick, this);
	this.gridOuter.updateColumns(this.columns.getColumnData());
}

var EGW_SELECTMODE_DEFAULT = 0;
var EGW_SELECTMODE_TOGGLE = 1;

egwGrid.prototype.setSelectmode = function(_mode)
{
	this.gridOuter.grid.selectmode = _mode;
}

egwGrid.prototype.setActionLinkGroup = function(_group, _links)
{
	this.dataRoot.actionLinkGroups[_group] = _links;
}

/**
 * Updates the action link groups.
 *
 * @param object _groups is an object used as associative array, which will be
 * 	merged into the existing actionLinkGroups
 * @param boolean _replace specifies whether existing action link groups will
 * 	be deleted. Defaults to false.
 */
egwGrid.prototype.setActionLinkGroups = function(_groups, _replace)
{
	if (typeof _replace == "undefined")
	{
		_replace = false;
	}

	if (_replace)
	{
		this.dataRoot.actionLinkGroups = {};
	}

	for (var k in _groups)
	{
		this.dataRoot.actionLinkGroups[k] = _groups[k];
	}
}

egwGrid.prototype.resize = function(_w, _h)
{
//	if (_w != this.width)
	{
		this.columns.setTotalWidth(_w - this.gridOuter.scrollbarWidth - 2);
		this.gridOuter.updateColumns(this.columns.getColumnData());
		this.height = -1;
	}

//	if (_h != this.height)
	{
		this.gridOuter.setHeight(_h);
	}

	this.height = _h;
	this.width = _w;
}

/**
 * If the columns have changed, call the gridOuter "updateColumns" function,
 * which will rebuild the view.
 */
egwGrid.prototype.columnsUpdate = function(_column)
{
	if (this.gridOuter)
	{
		this.gridOuter.updateColumns(this.columns.getColumnData());
	}
}

/**
 * Handle the selectcols callback
 */
egwGrid.prototype.selectcolsClick = function(_at)
{
	var column_data = this.columns.getColumnVisibilitySet();

	// Create a menu which contains these elements and show it
	var menu_data = [];
	for (var k in column_data)
	{
		var col = column_data[k];
		// strip html from caption
		var strippedCaption = col.caption.replace(/&(lt|gt);/g, function (strMatch, p1) {
			return (p1 == "lt")? "<" : ">";});
		strippedCaption = strippedCaption.replace(/<\/?[^>]+(>|$)/g,"");
		menu_data.push(
			{
				"id": k,
				"caption": strippedCaption,
				"enabled": col.enabled,
				"checkbox": true,
				"checked": col.visible
			}
		);
	}

	var menu = new egwMenu();
	menu.loadStructure(menu_data);

	var self = this;
	menu.setGlobalOnClick(function(_elem) {
		column_data[_elem.id].visible = _elem.checked;

		if (self.columnChangeCallback)
		{
			// Create the user data column visibility set
			var set = {};
			for (var k in column_data)
			{
				set[k] = {
					"visible": column_data[k].visible
				};
			}

			// Call the column change callback with the user data
			if (self.columnChangeCallback)
			{
				self.columnChangeCallback.call(self.context, set);
			}
		}

		self.columns.setColumnVisibilitySet(column_data);
	});

	menu.showAt(_at.offset().left, _at.offset().top);
}

/**
 * Handles the toggle all click
 */
egwGrid.prototype.toggleAllClick = function(_checked)
{
	this.dataRoot.actionObject.toggleAllSelected(_checked);
}

/**
 * Handles clicking on a sortable column header
 */
egwGrid.prototype.sortColsClick = function(_columnIdx)
{
	var col = this.columns.columns[_columnIdx];
	if (col.sortable == EGW_COL_SORTABLE_EXTERNAL)
	{
		if (this.sortColsCallback)
		{
			this.sortColsCallback.call(this.context, col.id);
		}
	}
	else
	{
		var dir = EGW_COL_SORTMODE_ASC;

		if (col.sortmode == EGW_COL_SORTMODE_ASC)
		{
			dir = EGW_COL_SORTMODE_DESC
		}

		this.sortData(col.id, dir);
	}
}

egwGrid.prototype.sortData = function(_columnId, _dir)
{
	var col = this.columns.getColumnById(_columnId);

	if (col && col.sortable != EGW_COL_SORTABLE_NONE && col.sortable != EGW_COL_SORTABLE_EXTERNAL)
	{
		this.dataRoot.sortChildren(col.id, _dir, col.sortable, function() {
			// Set the new sort direction
			col.set_sortmode(_dir);

			this.displaySortMode();

			// Rebuild the inner grid
			this.reload();
		}, this);
	}
}

egwGrid.prototype.displaySortMode = function()
{
	// Update the column data of the grid
	this.gridOuter.updateColumns(this.columns.getColumnData());

	// Update the column header
	for (var i = 0; i < this.columns.columns.length; i++)
	{
		this.gridOuter.updateColSortmode(i);
	}
}

egwGrid.prototype.resetSort = function()
{
	for (var i = 0; i < this.columns.columns.length; i++)
	{
		fileGrid.columns.columns[i].set_sortmode(EGW_COL_SORTMODE_NONE);
	}

	this.displaySortMode();
}

/**
 * Emptys the grid
 */
egwGrid.prototype.empty = function()
{
	this.dataRoot.empty();

	this.gridOuter.grid.empty();
}

egwGrid.prototype.reload = function()
{
	this.gridOuter.empty();
}

/**
 * Returns the height of the data inserted into the grid
 */
egwGrid.prototype.getDataHeight = function()
{
	return this.gridOuter.grid.getHeight();
}


