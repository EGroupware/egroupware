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
	egw_action_common;
*/

/**
 * Contains logic for the column class. The column class represents the unique set
 * of columns a grid view owns. The parameters of the columns (except for visibility)
 * di normaly not change.
 */

var EGW_COL_TYPE_DEFAULT = 0;
var EGW_COL_TYPE_NAME_ICON_FIXED = 1;
var EGW_COL_TYPE_CHECKBOX = 2;

var EGW_COL_VISIBILITY_ALWAYS = 0;
var EGW_COL_VISIBILITY_VISIBLE = 1;
var EGW_COL_VISIBILITY_INVISIBLE = 2;
var EGW_COL_VISIBILITY_ALWAYS_NOSELECT = 3;

var EGW_COL_SORTABLE_NONE = 0;
var EGW_COL_SORTABLE_ALPHABETIC = 1;
var EGW_COL_SORTABLE_NUMERICAL = 2;
var EGW_COL_SORTABLE_NATURAL = 3;
var EGW_COL_SORTABLE_EXTERNAL = 4;


var EGW_COL_SORTMODE_NONE = 0;
var EGW_COL_SORTMODE_ASC = 1;
var EGW_COL_SORTMODE_DESC = 2;

var EGW_COL_DEFAULT_FETCH = -10000;


/**
 * Class representing a single grid column.
 */
function egwGridColumn(_context, _visiblityChangeCallback, _sortmodeChangeCallback)
{
	if (typeof _context == "undefined")
	{
		_context = null;
	}

	if (typeof _visiblityChangeCallback == "undefined")
	{
		_visiblityChangeCallback == null;
	}

	this.id = "";
	this.fixedWidth = false;
	this.relativeWidth = false;
	this.maxWidth = false;
	this.caption = "";
	this.type = EGW_COL_TYPE_DEFAULT;
	this.visibility = EGW_COL_VISIBILITY_VISIBLE;
	this.sortable = EGW_COL_SORTABLE_ALPHABETIC;
	this.sortmode = EGW_COL_SORTMODE_NONE;
	this["default"] = EGW_COL_DEFAULT_FETCH;

	this.context = _context;
	this.visibilityChangeCallback = _visiblityChangeCallback;
	this.sortmodeChangeCallback = _sortmodeChangeCallback;
}

egwGridColumn.prototype.loadData = function(_data)
{
	egwActionStoreJSON(_data, this, true);
}

egwGridColumn.prototype.set_width = function(_value)
{
	// Parse the width parameter. Posible values are:
	// 	1. "100" => fixedWidth 100px
	// 	2. "100px" => fixedWidth 100px
	// 	3. "50%" => relativeWidth 50%
	if (_value)
	{
		if (typeof _value == "string")
		{
			var w = _value;
			if (w.charAt(w.length - 1) == "%" && !isNaN(w.substr(0, w.length - 1)))
			{
				this.relativeWidth = parseInt(w.substr(0, w.length - 1)) / 100;

				// Relative widths with more than 100% are not allowed!
				if (this.relativeWidth > 1)
				{
					this.relativeWidth = false;
				}
			}
			else if (w.substr(w.length - 2, 2) == "px" && !isNaN(w.substr(0, w.length - 2)))
			{
				this.fixedWidth = parseInt(w.substr(0, w.length - 2));
			}
			else if (!isNaN(w))
			{
				this.fixedWidth = parseInt(w);
			}
		}
	}
}

egwGridColumn.prototype.set_maxWidth = function(_value)
{
	if (!isNaN(_value) && _value > 0)
	{
		this.maxWidth = _value;
	}
}

egwGridColumn.prototype.set_default = function(_value)
{
	if (typeof _value == "string")
	{
		this["default"] = _value;
	}
	else if (typeof _value == "number" && (_value == EGW_COL_DEFAULT_FETCH))
	{
		this["default"] = _value;
	}
}

egwGridColumn.prototype.set_id = function(_value)
{
	this.id = _value;
}

/**
 * Setter for the column type.
 */
egwGridColumn.prototype.set_type = function(_value)
{
	if (typeof _value == "number" && (_value == EGW_COL_TYPE_DEFAULT ||
	    _value == EGW_COL_TYPE_NAME_ICON_FIXED || _value == EGW_COL_TYPE_CHECKBOX))
	{
		this.type = _value;

		if (this.type == EGW_COL_TYPE_CHECKBOX)
		{
			this.set_width("30px");
		}
	}
}

/**
 * Setter for the visibility of the column. Checks whether the given value is in
 * the allowed range and calls the visibilityChangeCallback.
 */ 
egwGridColumn.prototype.set_visibility = function(_value)
{
	if (typeof _value == "number" && (_value == EGW_COL_VISIBILITY_ALWAYS ||
	    _value == EGW_COL_VISIBILITY_INVISIBLE || _value == EGW_COL_VISIBILITY_VISIBLE ||
	    _value == EGW_COL_VISIBILITY_ALWAYS_NOSELECT))
	{
		if (_value != this.visibility)
		{
			this.visibility = _value;

			if (this.visibilityChangeCallback)
			{
				this.visibilityChangeCallback.call(this.context, this);
			}
		}
	}
}

/**
 * Sets the sortmode of the column and informs the parent about it.
 */
egwGridColumn.prototype.set_sortmode = function(_value)
{
	if (typeof _value == "number" && (_value == EGW_COL_SORTMODE_NONE ||
	    _value == EGW_COL_SORTMODE_ASC || _value == EGW_COL_SORTMODE_DESC))
	{
		if (this.sortable == EGW_COL_SORTABLE_NONE)
		{
			this.sortmode = EGW_COL_SORTMODE_NONE;
		}
		else
		{
			if (_value != this.sortmode)
			{
				if (this.sortmode)
				{
					this.sortmodeChangeCallback.call(this.context, this);
				}
				this.sortmode = _value;
			}
		}
	}
}

egwGridColumn.prototype.set_sortable = function(_value)
{
	if (typeof _value == "number" && (_value == EGW_COL_SORTABLE_ALPHABETIC ||
	    _value == EGW_COL_SORTABLE_NONE || _value == EGW_COL_SORTABLE_NATURAL ||
	    _value == EGW_COL_SORTABLE_NUMERICAL || _value == EGW_COL_SORTABLE_EXTERNAL))
	{
		if (_value == EGW_COL_SORTABLE_NONE)
		{
			this.sortmode = EGW_COL_SORTABLE_NONE;
		}

		this.sortable = _value;
	}
}


egwGridColumn.prototype.set_caption = function(_value)
{
	this.caption = _value;
}


/**
 * Object which is used inside egwGrid to manage the grid columns.
 */
function egwGridColumns(_columns, _updateCallback, _context, _columnSpace)
{
	// Default the coulumn padding value to two
	if (typeof _columnSpace == "undefined")
	{
		this.columnSpace = 2;
	}
	else
	{
		this.columnSpace = _columnSpace;
	}

	this.totalWidth = false;
	this.inUpdate = false;
	this.sortChanged = null;
	this.visibilityChanged = false;
	this.columnWidths = [];

	this.context = _context;
	this.updateCallback = _updateCallback;

	this._beginUpdate();

	this.columns = [];
	for (var i = 0; i < _columns.length; i++)
	{
		var column = new egwGridColumn(this, this._visibilityCallback, this._sortCallback);
		column.loadData(_columns[i]);

		this.columns.push(column);
	}

	this._endUpdate();
}

egwGridColumns.prototype._beginUpdate = function()
{
	this.inUpdate = true;
	this.sortChanged = null;
	this.visibilityChanged = false;
}

egwGridColumns.prototype._endUpdate = function()
{
	this.inUpdate = false;

	// Call the sort update again in order to update the other columns
	if (this.sortChanged)
	{
		this._sortCallback(this.sortCallback);
	}

	if (this.visibilityChanged || this.sortChanged)
	{
		this.updateCallback.call(this.context, this);
	}

	this.sortChanged = null;
	this.visibilityChanged = false;
}

egwGridColumns.prototype._visibilityCallback = function(_elem)
{
	if (this.inUpdate)
	{
		this.visibilityChanged = true;
	}
	else
	{
		this.updateCallback.call(this.context, this);
	}
}

egwGridColumns.prototype._sortCallback = function(_elem)
{
	if (this.inUpdate)
	{
		this.sortChanged = _elem;
	}
	else
	{
		// Reset the sortmode of all other elements.
		for (var i = 0; i < this.columns.length; i++)
		{
			if (this.columns[i] != _elem)
			{
				this.columns[i].sortmode = EGW_COL_SORTMODE_NONE;
			}
		}
	}
}

egwGridColumns.prototype._calculateWidths = function()
{
	// Reset some values which are used during the calculation
	for (var i = 0; i < this.columns.length; i++)
	{
		this.columns[i].larger = false;
		this.columns[i].newWidth = false;
	}

	// Remove the spacing between the columns from the total width
	var tw = this.totalWidth - (this.columns.length - 1) * this.columnSpace;

	// Calculate how many space is - relatively - not occupied with columns with
	// relative or fixed width
	var remRelWidth = 1;
	var noWidthCount = 0

	for (var i = 0; i < this.columns.length; i++)
	{
		var col = this.columns[i];
		if (col.visibility != EGW_COL_VISIBILITY_INVISIBLE)
		{
			if (col.relativeWidth)
			{
				remRelWidth -= col.relativeWidth;
			}
			else if (col.fixedWidth)
			{
				remRelWidth -= col.fixedWidth / tw;
			}
			else
			{
				noWidthCount++;
			}
		}
	}

	// Check whether the width of columns with relative width is larger than their
	// maxWidth
	var done = true;
	do
	{
		done = true;

		var noWidth = remRelWidth / noWidthCount;

		for (var i = 0; i < this.columns.length; i++)
		{
			var col = this.columns[i];

			if (col.visibility != EGW_COL_VISIBILITY_INVISIBLE)
			{
				if (col.maxWidth && !col.larger)
				{
					if (col.relativeWidth)
					{
						var w = col.relativeWidth * tw;
						col.larger = w > col.maxWidth;
						if (col.larger)
						{
							// Recalculate the remaining relative width:
							// col.maxWidth / w is the relative amount of space p which
							// is remaining for the element. E.g. an element with
							// w = 150px and maxWidth = 100px => p = 2/3
							// The space which got removed is 1 - p => 1/3
							// ==> we have to add 1/3 * oldRelWidth to the remRelWidth
							// variable.
							remRelWidth += col.relativeWidth * (1 - col.maxWidth / w);
							done = false;
							break;
						}
					}
					else
					{
						col.larger = noWidth * tw > col.maxWidth;
						if (col.larger)
						{
							remRelWidth -= col.maxWidth / tw;
							noWidthCount--;
							done = false;
							break;
						}
					}
				}
			}
		}
	// As some columns take their max width, new space might come available, which
	// requires other columns to take their maximum width.
	} while (!done);


	// Check whether the columns where a relative width is specified have more
	// space than the remaining columns - if yes, make the relative ones larger
	for (var i = 0; i < this.columns.length; i++)
	{
		var col = this.columns[i];

		if (col.visibility != EGW_COL_VISIBILITY_INVISIBLE)
		{
			if (col.relativeWidth && !col.larger)
			{
				if (col.relativeWidth < noWidth)
				{
					noWidthCount++;
					remRelWidth += col.relativeWidth;
					col.newWidth = true;
				}
				else
				{
					col.newWidth = false;
				}
			}
		}
	}

	// Now calculate the absolute width of the columns in pixels
	this.columnWidths = [];
	for (var i = 0; i < this.columns.length; i++)
	{
		var w = 0;
		var col = this.columns[i];
		if (col.visibility != EGW_COL_VISIBILITY_INVISIBLE)
		{
			if (col.larger)
			{
				w = col.maxWidth;
			}
			else if (col.fixedWidth)
			{
				w = col.fixedWidth;
			}
			else if (col.relativeWidth && !col.newWidth)
			{
				w = Math.round(tw * col.relativeWidth);
			}
			else
			{
				w = Math.round(tw * (remRelWidth / noWidthCount));
			}

			if (w < 0)
			{
				w = 0;
			}
		}
		this.columnWidths.push(w);
	}
}

egwGridColumns.prototype.setTotalWidth = function(_value)
{
	if (_value < 100)
	{
		_value = 100;
	}

	this.totalWidth = _value;
	this._calculateWidths();
}

egwGridColumns.prototype.getColumnIndexById = function(_id)
{
	for (var i = 0; i < this.columns.length; i++)
	{
		if (this.columns[i].id == _id)
		{
			return i;
		}
	}
	return -1;
}

egwGridColumns.prototype.getColumnById = function(_id)
{
	var idx = this.getColumnIndexById(_id);
	return (idx == -1) ? null : this.columns[idx];
}

egwGridColumns.prototype.getVisibleCount = function()
{
	var cnt = 0;
	for (var i = 0; i < this.columns.length; i++)
	{
		if (this.columns[i].visibility != EGW_COL_VISIBILITY_INVISIBLE)
		{
			cnt++;
		}
	}
	return cnt;
}

egwGridColumns.prototype.getColumnVisibilitySet = function()
{
	var result = {};

	for (var i = 0; i < this.columns.length; i++)
	{
		if (this.columns[i].visibility != EGW_COL_VISIBILITY_ALWAYS_NOSELECT)
		{
			result[this.columns[i].id] = {
				"caption": this.columns[i].caption,
				"enabled": (this.columns[i].visibility != EGW_COL_VISIBILITY_ALWAYS) &&
					(this.columns[i].type == EGW_COL_TYPE_DEFAULT),
				"visible": this.columns[i].visibility != EGW_COL_VISIBILITY_INVISIBLE
			};
		}
	}

	return result;
}

egwGridColumns.prototype.setColumnVisibilitySet = function(_set)
{
	this._beginUpdate();

	for (k in _set)
	{
		var col = this.getColumnById(k);
		if (col)
		{
			col.set_visibility(_set[k].visible ? EGW_COL_VISIBILITY_VISIBLE :
				EGW_COL_VISIBILITY_INVISIBLE);
		}
	}

	if (this.visibilityChanged)
	{
		this._calculateWidths();
	}

	this._endUpdate();
}

egwGridColumns.prototype.getColumnData = function()
{
	var result = [];

	for (var i = 0; i < this.columns.length; i++)
	{
		result.push(
			{
				"id": this.columns[i].id,
				"caption": this.columns[i].caption,
				"sortable": this.columns[i].sortable != EGW_COL_SORTABLE_NONE,
				"sortmode": this.columns[i].sortmode,
				"default": this.columns[i]["default"],
				"width": this.columnWidths[i],
				"type": this.columns[i].type,
				"visible": this.columns[i].visibility != EGW_COL_VISIBILITY_INVISIBLE,
				"element": this.columns[i]
			}
		);
	}

	return result;
}

egwGridColumns.prototype.sortBy = function(_id, _mode)
{
	// Fetch the column and set its sortmode. If the column supports sorting,
	// it will call the callback function.
	var col = this.getColumnById(_id);
	if (col)
	{
		col.set_sortmode(_mode);
	}
}


