/**
 * EGroupware eTemplate2 - Class which contains a the columns model
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

/*egw:uses
	et2_core_inheritance;
	et2_inheritance;
*/


/**
 * Class which stores the data of a single column.
 *
 * @augments Class
 */
export class et2_dataview_column
{

	public static readonly ET2_COL_TYPE_DEFAULT = 0;
	public static readonly ET2_COL_TYPE_NAME_ICON_FIXED = 1;

	public static readonly ET2_COL_VISIBILITY_ALWAYS = 0;
	public static readonly ET2_COL_VISIBILITY_VISIBLE = 1;
	public static readonly ET2_COL_VISIBILITY_INVISIBLE = 2;
	public static readonly ET2_COL_VISIBILITY_ALWAYS_NOSELECT = 3;
	public static readonly ET2_COL_VISIBILITY_DISABLED = 4;

	static readonly _attributes: any = {
		"id": {
			"name": "ID",
			"type": "string",
			"description": "Unique identifier for this column. It is used to " +
				"store changed column widths or visibilities."
		},
		"visibility": {
			"name": "Visibility",
			"type": "integer",
			"default": et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE,
			"description": "Defines the visibility state of this column."
		},
		"caption": {
			"name": "Caption",
			"type": "string",
			"description": "Caption of the column as it is displayed in the " +
				"select columns popup."
		},
		"type": {
			"name": "Column type",
			"type": "integer",
			"default": et2_dataview_column.ET2_COL_TYPE_DEFAULT,
			"description": "Type of the column"
		},
		"width": {
			"name": "Width",
			"type": "dimension",
			"default": "80px",
			"description": "Width of the column."
		},
		"minWidth": {
			"name": "Minimum width",
			"type": "integer",
			"default": 20,
			"description": "Minimum width of the column, in pixels.  Values below this are rejected."
		},
		"maxWidth": {
			"name": "Maximum width",
			"type": "integer",
			"default": 0,
			"description": "Maximum width of the column"
		}
	};

	fixedWidth: number | boolean;
	relativeWidth: number | boolean;

	/**
	 * Unique identifier for this column. It is used to store changed column widths or visibilities.
	 */
	public id: string;

	/**
	 * Defines the visibility state of this column.
	 */
	public visibility: number = et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE;

	public caption: string = '';

	/**
	 * Column type - Type of the column
	 *
	 * One of ET2_COL_TYPE_DEFAULT or ET2_COL_TYPE_NAME_ICON_FIXED
	 */
	public type: number = et2_dataview_column.ET2_COL_TYPE_DEFAULT;

	/**
	 * Width of the column
	 */
	public width: number = 80;

	/**
	 * Maximum width of the column
	 */
	public maxWidth: number = 0;

	/**
	 * Minimum width of the column, in pixels.  Values below this are rejected.
	 */
	public minWidth: number = 20;

	/**
	 * Constructor
	 */
	 constructor(_attrs)
	{
		this.id = _attrs.id;

		if(typeof _attrs.visibility !== "undefined")
		{
			this.visibility = _attrs.visibility;
		}
		this.caption = _attrs.caption;
		if(typeof _attrs.type !== "undefined")
		{
			this.type = _attrs.type;
		}
		if(typeof _attrs.width !== "undefined")
		{
			this.set_width( _attrs.width );
		}
		if(typeof _attrs.maxWidth !== "undefined")
		{
			this.maxWidth = _attrs.maxWidth;
		}
		if(typeof _attrs.minWidth !== "undefined")
		{
			this.minWidth = _attrs.minWidth;
		}
	}

	/**
	 * Set the column width
	 *
	 * Posible value types are:
	 * 	1. "100" => fixedWidth 100px
	 * 	2. "100px" => fixedWidth 100px
	 *	3. "50%" => relativeWidth 50%
	 *	4. 0.5 => relativeWidth 50%
	 *
	 * @param {float|string} _value
	 */
	set_width(_value)
	{
		// Parse the width parameter.
		this.relativeWidth = false;
		this.fixedWidth = false;
		var w = _value;

		if (typeof w == 'number')
		{
			this.relativeWidth = parseFloat(w.toFixed(3));
		}
		else if (w.charAt(w.length - 1) == "%" && !isNaN(w.substr(0, w.length - 1)))
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
		else if (typeof w == 'string' && !isNaN(parseFloat(w)))
		{
			this.fixedWidth = parseInt(w);
		}
	}

	set_visibility(_value)
	{
		// If visibility is always, don't turn it off
		if(this.visibility == et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS || this.visibility == et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT) return;

		if(_value === true)
		{
			this.visibility = et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE;
		}
		else if (_value === false)
		{
			this.visibility = et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE;
		}
		else if (typeof _value == "number")
		{
			this.visibility = _value;
		}
		else
		{
			egw().debug("warn", "Invalid visibility option for column: ", _value);
		}
	}
}

/**
 * Contains logic for the columns class. The columns class represents the unique set
 * of columns a grid view owns. The parameters of the columns (except for visibility)
 * do normaly not change.
 */

export class et2_dataview_columns
{
	private _totalWidth: number;
	private _totalFixed: number | boolean;
	private columnWidths: any[];
	private columns: et2_dataview_column[];
	private _updated: boolean;

	constructor(_columnData)
	{
		// Initialize some variables
		this._totalWidth = 0;
		this._totalFixed = 0;
		this.columnWidths = [];

		// Create the columns object
		this.columns = new Array(_columnData.length);
		for (var i = 0; i < _columnData.length; i++)
		{
			this.columns[i] = new et2_dataview_column(_columnData[i]);
		}

		this._updated = true;
	}

	destroy() {
		// Free all column objects
		for (var i = 0; i < this.columns.length; i++)
		{
			this.columns[i] = null;
		}
	}

	public updated()
	{
		this._updated = true;
	}

	columnCount() : number
	{
		return this.columns.length;
	}

	get totalWidth(): number
	{
		return this._totalWidth;
	}

	get totalFixed(): number {
		return this._totalFixed ? <number>this._totalFixed : 0;
	}

	/**
	 * Set the total width of the header row
	 *
	 * @param {(string|number)} _width
	 */
	setTotalWidth(_width)
	{
		if (_width != this._totalWidth && _width > 0)
		{
			this._totalWidth = _width;
			this._updated = true;
		}
	}

	/**
	 * Returns the index of the colum with the given id
	 *
	 * @param {string} _id
	 */
	getColumnIndexById( _id)
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

	/**
	 * Returns the column with the given id
	 *
	 * @param {string} _id
	 */
	getColumnById( _id)
	{
		var idx = this.getColumnIndexById(_id);
		return (idx == -1) ? null : this.columns[idx];
	}

	/**
	 * Returns the width of the column with the given index
	 *
	 * @param {number} _idx
	 */
	getColumnWidth( _idx)
	{
		if (this._totalWidth > 0 && _idx >= 0 && _idx < this.columns.length)
		{
			// Recalculate the column widths if something has changed.
			if (this._updated)
			{
				this._calculateWidths();
				this._updated = false;
			}

			// Return the calculated width for the column with the given index.
			return this.columnWidths[_idx];
		}

		return 0;
	}

	/**
	 * Returns an array containing the width of the column and its visibility
	 * state.
	 */
	getColumnData( )
	{
		var result = [];

		for (var i = 0; i < this.columns.length; i++)
		{
			result.push({
				"id": this.columns[i].id,
				"width": this.getColumnWidth(i),
				"visible": this.columns[i].visibility !== et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE &&
					this.columns[i].visibility !== et2_dataview_column.ET2_COL_VISIBILITY_DISABLED

			});
		}

		return result;
	}

	/**
	 * Returns an associative array which contains data about the visibility
	 * state of the columns.
	 */
	getColumnVisibilitySet()
	{
		var result = {};

		for (var i = 0; i < this.columns.length; i++)
		{
			if (this.columns[i].visibility != et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS_NOSELECT)
			{
				result[this.columns[i].id] = {
					"caption": this.columns[i].caption,
					"enabled": (this.columns[i].visibility != et2_dataview_column.ET2_COL_VISIBILITY_ALWAYS) &&
						(this.columns[i].visibility != et2_dataview_column.ET2_COL_VISIBILITY_DISABLED) &&
						(this.columns[i].type != et2_dataview_column.ET2_COL_TYPE_NAME_ICON_FIXED),
					"visible": this.columns[i].visibility != et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE
				};
			}
		}

		return result;
	}

	/**
	 * Sets a column visiblity set
	 *
	 * @param {object} _set
	 */
	setColumnVisibilitySet( _set)
	{
		for (var k in _set)
		{
			var col = this.getColumnById(k);
			if (col)
			{
				col.set_visibility(_set[k].visible ? et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE :
					et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE);
			}
		}

		this._updated = true;
	}

	/* ---- PRIVATE FUNCTIONS ---- */

	/**
	 * Calculates the absolute column width depending on the previously set
	 * "totalWidth" value. The calculated values are stored in the columnWidths
	 * array.
	 */
	_calculateWidths()
	{
		// Reset some values which are used during the calculation
		let _larger = Array(this.columns.length);
		for (var i = 0; i < this.columns.length; i++)
		{
			_larger[i] = false;
		}

		// Remove the spacing between the columns from the total width
		var tw = this._totalWidth;

		// Calculate how many space is - relatively - not occupied with columns with
		// relative or fixed width
		var totalRelative: number = 0;
		var fixedCount = 0;
		this._totalFixed = 0;

		for (var i = 0; i < this.columns.length; i++)
		{
			var col = this.columns[i];
			if (col.visibility !== et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE &&
				col.visibility !== et2_dataview_column.ET2_COL_VISIBILITY_DISABLED
			)
			{
				// Some bounds sanity checking
				if(col.fixedWidth > tw || col.fixedWidth < 0)
				{
					col.fixedWidth = false;
				}
				else if (col.relativeWidth > 1 || col.relativeWidth < 0)
				{
					col.relativeWidth = false;
				}
				if (col.relativeWidth)
				{
					totalRelative += <number>col.relativeWidth;
				}
				else if (col.fixedWidth)
				{
					this._totalFixed += <number>col.fixedWidth;
					fixedCount++;
				}
			}
		}

		// Now calculate the absolute width of the columns in pixels
		var usedTotal = 0;
		this.columnWidths = [];
		for (var i = 0; i < this.columns.length; i++)
		{
			var w = 0;
			var col = this.columns[i];
			if (col.visibility != et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE &&
				col.visibility !== et2_dataview_column.ET2_COL_VISIBILITY_DISABLED
			)
			{
				if (_larger[i])
				{
					w = col.maxWidth;
				}
				else if (col.fixedWidth)
				{
					w = <number>col.fixedWidth;
				}
				else if (col.relativeWidth)
				{
					// Reset relative to an actual percentage (of 1.00) or
					// resizing eventually sends them to 0
					col.relativeWidth = <number>col.relativeWidth / totalRelative;
					w = Math.round((tw-this._totalFixed) * col.relativeWidth);
				}
				if (w > tw || (col.maxWidth && w > col.maxWidth))
				{
					w = Math.min(tw - usedTotal, col.maxWidth);
				}
				if (w < 0 || w < col.minWidth)
				{
					w = Math.max(0, col.minWidth);
				}
			}
			this.columnWidths.push(w);
			usedTotal += w;
		}

		// Deal with any accumulated rounding errors
		if(usedTotal != tw)
		{
		    var column, columnIndex;
			var remaining_width = (usedTotal - tw);

			// Pick the first relative column and use it
			for(columnIndex = 0; columnIndex < this.columns.length; columnIndex++)
			{
				if(this.columns[columnIndex].visibility === et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE ||
					this.columns[columnIndex].visibility === et2_dataview_column.ET2_COL_VISIBILITY_DISABLED ||
					this.columnWidths[columnIndex] <= 0 ||
					remaining_width > 0 && this.columnWidths[columnIndex] <= this.columns[columnIndex].minWidth)
				{
					continue;
				}

				var col = this.columns[columnIndex];
				if(col.relativeWidth || !col.fixedWidth)
				{
					column = col;
					break;
				}
				else if (!col.fixedWidth)
				{
					column = col;
				}
			}
			if(!column)
			{
				// Distribute shortage over all fixed width columns
				var diff = Math.round(remaining_width / fixedCount);
				for(var i = 0; i < this.columns.length; i++)
				{
					var col = this.columns[i];
					var col_diff = (diff < 0 ?
						Math.max(remaining_width, diff) :
						Math.min(remaining_width, diff)
					);
					if(!col.fixedWidth) continue;
					var new_width = this.columnWidths[i] - col_diff;
					remaining_width -= col_diff;
					this.columnWidths[i] = Math.max(0, Math.min(new_width,tw));
				}
			}
			else
			{
				this.columnWidths[columnIndex] = Math.max(column.minWidth, this.columnWidths[columnIndex] - remaining_width);
			}
		}
	}

}
