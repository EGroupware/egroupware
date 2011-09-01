/**
 * eGroupWare eTemplate2 - Class which contains a factory method for rows
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
	jquery.jquery;
	et2_core_inheritance;
*/

/**
 * The row provider contains prototypes (full clonable dom-trees) 
 * for all registered row types.
 */
var et2_dataview_rowProvider = Class.extend({

	init: function(_outerId, _columnIds) {
		// Copy the given parameters
		this._outerId = _outerId;
		this._columnIds = _columnIds;
		this._prototypes = {};

		// Create the default row "prototypes"
		this._createFullRowPrototype();
		this._createDefaultPrototype();
		this._createEmptyPrototype();
	},

	/**
	 * Returns a clone of the prototype with the given name. If the generator
	 * callback function is given, this function is called if the prototype
	 * does not yet registered.
	 */
	getPrototype: function(_name, _generator, _context) {
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
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_createFullRowPrototype: function() {
		var tr = $j(document.createElement("tr"));
		var td = $j(document.createElement("td"))
			.attr("span", this._columnIds.length)
			.appendTo(tr);
		var div = $j(document.createElement("div"))
			.addClass(this._outerId + "_div_fullRow")
			.appendTo(td);

		this._prototypes["fullRow"] = tr;
	},

	_createDefaultPrototype: function() {
		var tr = $j(document.createElement("tr"));

		// Append a td for each column
		for (var i = 0; i < this._columnIds.length; i++)
		{
			var td = $j(document.createElement("td"))
				.addClass(this._outerId + "_td_" + this._columnIds[i])
				.appendTo(tr);
			var div = $j(document.createElement("div"))
				.addClass(this._outerId + "_div_" + this._columnIds[i])
				.addClass("innerContainer")
				.appendTo(td);
		}

		this._prototypes["default"] = tr;
	},

	_createEmptyPrototype: function() {
		this._prototypes["empty"] = $j(document.createElement("tr"));
	}

});

