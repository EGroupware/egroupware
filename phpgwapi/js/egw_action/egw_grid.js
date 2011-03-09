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
	egw_action_common,
	egw_action_view,
	egw_action_data,
	egw_action_columns
*/

function egwGrid(_parentNode, _columns, _objectManager, _fetchCallback, _context)
{
	this.parentNode = _parentNode;
	this.objectManager = _objectManager;

	this.width = 0;
	this.height = 0;

	// Create the column handler and connect its update event to this object
	this.columns = new egwGridColumns(_columns, this.columnsUpdate, this);

	// Create the read queue
	this.readQueue = new egwGridDataQueue(_fetchCallback, _context);

	// Create the root data element
	this.dataRoot = new egwGridDataElement("", null, this.columns, this.readQueue, 
		_objectManager);

	// Create the outer view component and pass the dataRoot element so that
	// the grid outer element will be capable of fetching the root data and
	// can create a spacer for that.
	this.gridOuter = new egwGridViewOuter(_parentNode, this.dataRoot);
	this.gridOuter.updateColumns(this.columns.getColumnData());
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

