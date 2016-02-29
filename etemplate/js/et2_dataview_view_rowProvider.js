/**
 * EGroupware eTemplate2 - Class which contains a factory method for rows
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
	et2_core_interfaces;
	et2_core_arrayMgr;
	et2_core_widget;
*/

/**
 * The row provider contains prototypes (full clonable dom-trees)
 * for all registered row types.
 *
 * @augments Class
 */
var et2_dataview_rowProvider = (function(){ "use strict"; return Class.extend(
{
	/**
	 *
	 * @param _outerId
	 * @param _columnIds
	 * @memberOf et2_dataview_rowProvider
	 */
	init: function(_outerId, _columnIds) {
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
	},

	getColumnCount: function() {
		return this._columnIds.length;
	},

	/**
	 * Returns a clone of the prototype with the given name. If the generator
	 * callback function is given, this function is called if the prototype
	 * does not yet registered.
	 *
	 * @param {string} _name
	 * @param {function} _generator
	 * @param {object} _context
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
			.addClass(this._outerId + "_td_fullRow")
			.attr("colspan", this._columnIds.length)
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
	},

	_createLoadingPrototype: function() {
		var fullRow = this.getPrototype("fullRow");
		$j("div", fullRow).addClass("loading");

		this._prototypes["loading"] = fullRow;
	}

});}).call(this);

