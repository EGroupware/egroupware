/**
 * EGroupware eTemplate2 - Class which contains a factory method for rows
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
	et2_core_inheritance;
	et2_core_interfaces;
	et2_core_arrayMgr;
	et2_core_widget;
*/

/**
 * The row provider contains prototypes (full clonable dom-trees)
 * for all registered row types.
 */
export class et2_dataview_rowProvider
{
	private _outerId: any;
	private _columnIds: any;
	private _prototypes: {};
	private _template: null;
	private _mgrs: null;
	private _rootWidget: null;
	/**
	 *
	 * @param _outerId
	 * @param _columnIds
	 */
	constructor( _outerId, _columnIds)
	{
		// Copy the given parameters
		this._outerId = _outerId;
		this._columnIds = _columnIds;
		this._prototypes = {};

		this._template = null;
		this._mgrs = null;
		this._rootWidget = null;

		// Create the default row "prototypes"
		this._createFullRowPrototype();
		this._createDefaultPrototype();
		this._createEmptyPrototype();
		this._createLoadingPrototype();
	}

	public destroy()
	{
		this._template = null;
		this._mgrs = null;
		this._rootWidget = null;
		this._prototypes = {};
		this._columnIds = [];
	}

	public getColumnCount()
	{
		return this._columnIds.length;
	}

	/**
	 * Returns a clone of the prototype with the given name. If the generator
	 * callback function is given, this function is called if the prototype
	 * does not yet registered.
	 *
	 * @param {string} _name
	 * @param {function} _generator
	 * @param {object} _context
	 */
	getPrototype( _name : string, _generator? : Function, _context? : any)
	{
		if (typeof this._prototypes[_name] == "undefined")
		{
			if (typeof _generator != "undefined")
			{
				this._prototypes[_name] = _generator.call(_context, this._outerId,
					this._columnIds);
			}
			else
			{
				return null;
			}
		}

		return this._prototypes[_name].clone();
	}


	/* ---- PRIVATE FUNCTIONS ---- */


	_createFullRowPrototype( )
	{
		var tr = jQuery(document.createElement("tr"));
		var td = jQuery(document.createElement("td"))
			.addClass(this._outerId + "_td_fullRow")
			.attr("colspan", this._columnIds.length)
			.appendTo(tr);
		var div = jQuery(document.createElement("div"))
			.addClass(this._outerId + "_div_fullRow")
			.appendTo(td);

		this._prototypes["fullRow"] = tr;
	}

	_createDefaultPrototype( )
	{
		var tr = jQuery(document.createElement("tr"));

		// Append a td for each column
		for (var column of this._columnIds)
		{
			if(!column) continue;

			var td = jQuery(document.createElement("td"))
				.addClass(this._outerId + "_td_" + column)
				.appendTo(tr);
			var div = jQuery(document.createElement("div"))
				.addClass(this._outerId + "_div_" + column)
				.addClass("innerContainer")
				.appendTo(td);
		}

		this._prototypes["default"] = tr;
	}

	_createEmptyPrototype( )
	{
		this._prototypes["empty"] = jQuery(document.createElement("tr"));
	}

	_createLoadingPrototype( )
	{
		var fullRow = this.getPrototype("fullRow");
		jQuery("div", fullRow).addClass("loading");

		this._prototypes["loading"] = fullRow;
	}

}

