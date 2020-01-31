"use strict";
/**
 * EGroupware eTemplate2 - dataview code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2014
 * @version $Id: et2_dataview_view_container_1.js 46338 2014-03-20 09:40:37Z ralfbecker $
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_dataview_interfaces;
*/
/**
 * Displays tiles or thumbnails (squares) instead of full rows.
 *
 * It's important that the template specifies a fixed width and height (via CSS)
 * so that the rows and columns work out properly.
 *
 */
var et2_dataview_tile = /** @class */ (function (_super) {
    __extends(et2_dataview_tile, _super);
    /**
     * Creates the row container. Use the "setRow" function to load the actual
     * row content.
     *
     * @param _parent is the row parent container.
     * @memberOf et2_dataview_row
     */
    function et2_dataview_tile(_parent) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent) || this;
        _this.columns = 4;
        // Make sure the needed class is there to get the CSS
        _this.tr.addClass('tile');
        return _this;
    }
    et2_dataview_tile.prototype.makeExpandable = function (_expandable, _callback, _context) {
        // Nope.  It mostly works, it's just weird.
    };
    et2_dataview_tile.prototype.getAvgHeightData = function () {
        var res = {
            "avgHeight": this.getHeight() / this.columns,
            "avgCount": this.columns
        };
        return res;
    };
    /**
     * Returns the height for the tile.
     *
     * This is where we do the magic.  If a new row should start, we return the proper
     * height.  If this should be another tile in the same row, we say it has 0 height.
     * @returns {Number}
     */
    et2_dataview_tile.prototype.getHeight = function () {
        if (this._index % this.columns == 0) {
            return _super.prototype.getHeight.call(this);
        }
        else {
            return 0;
        }
    };
    /**
     * Broadcasts an invalidation through the container tree. Marks the own
     * height as invalid.
     */
    et2_dataview_tile.prototype.invalidate = function () {
        if (this._inTree && this.tr) {
            var template_width = jQuery('.innerContainer', this.tr).children().outerWidth(true);
            if (template_width) {
                this.tr.css('width', template_width + (this.tr.outerWidth(true) - this.tr.width()));
            }
        }
        this._recalculate_columns();
        _super.prototype.invalidate.call(this);
    };
    /**
     * Recalculate how many columns we can fit in a row.
     * While browser takes care of the actual layout, we need this for proper
     * pagination.
     */
    et2_dataview_tile.prototype._recalculate_columns = function () {
        if (this._inTree && this.tr && this.tr.parent()) {
            this.columns = Math.max(1, parseInt(this.tr.parent().innerWidth() / this.tr.outerWidth(true)));
        }
    };
    return et2_dataview_tile;
}(et2_dataview_row));
exports.et2_dataview_tile = et2_dataview_tile;
//# sourceMappingURL=et2_dataview_view_tile.js.map