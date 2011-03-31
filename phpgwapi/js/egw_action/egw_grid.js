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

/*
uses
	egw_action,
	egw_action_common,
	egw_action_view,
	egw_action_data,
	egw_action_columns
*/

function egwGrid(_parentNode, _columns, _objectManager, _fetchCallback, _columnChangeCallback, _context)
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
				self.selectedChangeCallback.call(self.context, allSelected)
			}
		}
	};

	// Create the outer view component and pass the dataRoot element so that
	// the grid outer element will be capable of fetching the root data and
	// can create a spacer for that.
	this.gridOuter = new egwGridViewOuter(_parentNode, this.dataRoot,
		this.selectcolsClick, this.toggleAllClick, this);
	this.gridOuter.updateColumns(this.columns.getColumnData());
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
	if (_w != this.width)
	{
		this.columns.setTotalWidth(_w - this.gridOuter.scrollbarWidth);
		this.gridOuter.updateColumns(this.columns.getColumnData());
		this.height = -1;
	}

	if (_h != this.height)
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

		menu_data.push(
			{
				"id": k,
				"caption": col.caption,
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
			for (k in column_data)
			{
				set[k] = {
					"visible": column_data[k].visible
				};
			}

			// Call the column change callback with the user data
			self.columnChangeCallback.call(self.context, set);
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


