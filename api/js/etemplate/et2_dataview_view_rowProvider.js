"use strict";
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
Object.defineProperty(exports, "__esModule", { value: true });
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
var et2_dataview_rowProvider = /** @class */ (function () {
    /**
     *
     * @param _outerId
     * @param _columnIds
     */
    function et2_dataview_rowProvider(_outerId, _columnIds) {
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
    et2_dataview_rowProvider.prototype.destroy = function () {
        this._template = null;
        this._mgrs = null;
        this._rootWidget = null;
        this._prototypes = {};
        this._columnIds = [];
    };
    et2_dataview_rowProvider.prototype.getColumnCount = function () {
        return this._columnIds.length;
    };
    /**
     * Returns a clone of the prototype with the given name. If the generator
     * callback function is given, this function is called if the prototype
     * does not yet registered.
     *
     * @param {string} _name
     * @param {function} _generator
     * @param {object} _context
     */
    et2_dataview_rowProvider.prototype.getPrototype = function (_name, _generator, _context) {
        if (typeof this._prototypes[_name] == "undefined") {
            if (typeof _generator != "undefined") {
                this._prototypes[_name] = _generator.call(_context, this._outerId, this._columnIds);
            }
            else {
                return null;
            }
        }
        return this._prototypes[_name].clone();
    };
    /* ---- PRIVATE FUNCTIONS ---- */
    et2_dataview_rowProvider.prototype._createFullRowPrototype = function () {
        var tr = jQuery(document.createElement("tr"));
        var td = jQuery(document.createElement("td"))
            .addClass(this._outerId + "_td_fullRow")
            .attr("colspan", this._columnIds.length)
            .appendTo(tr);
        var div = jQuery(document.createElement("div"))
            .addClass(this._outerId + "_div_fullRow")
            .appendTo(td);
        this._prototypes["fullRow"] = tr;
    };
    et2_dataview_rowProvider.prototype._createDefaultPrototype = function () {
        var tr = jQuery(document.createElement("tr"));
        // Append a td for each column
        for (var _i = 0, _a = this._columnIds; _i < _a.length; _i++) {
            var column = _a[_i];
            if (!column)
                continue;
            var td = jQuery(document.createElement("td"))
                .addClass(this._outerId + "_td_" + column)
                .appendTo(tr);
            var div = jQuery(document.createElement("div"))
                .addClass(this._outerId + "_div_" + column)
                .addClass("innerContainer")
                .appendTo(td);
        }
        this._prototypes["default"] = tr;
    };
    et2_dataview_rowProvider.prototype._createEmptyPrototype = function () {
        this._prototypes["empty"] = jQuery(document.createElement("tr"));
    };
    et2_dataview_rowProvider.prototype._createLoadingPrototype = function () {
        var fullRow = this.getPrototype("fullRow");
        jQuery("div", fullRow).addClass("loading");
        this._prototypes["loading"] = fullRow;
    };
    return et2_dataview_rowProvider;
}());
exports.et2_dataview_rowProvider = et2_dataview_rowProvider;
//# sourceMappingURL=et2_dataview_view_rowProvider.js.map