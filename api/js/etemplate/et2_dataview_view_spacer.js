"use strict";
/**
 * EGroupware eTemplate2 - Class which contains the spacer container
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
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
    et2_dataview_view_container;
*/
/**
 * @augments et2_dataview_container
 */
var et2_dataview_spacer = /** @class */ (function (_super) {
    __extends(et2_dataview_spacer, _super);
    /**
     * Constructor
     *
     * @param _parent
     * @param _rowProvider
     * @memberOf et2_dataview_spacer
     */
    function et2_dataview_spacer(_parent, _rowProvider) {
        var _this = 
        // Call the inherited container constructor
        _super.call(this, _parent) || this;
        // Initialize the row count and the row height
        _this._count = 0;
        _this._rowHeight = 19;
        _this._avgSum = 0;
        _this._avgCount = 0;
        // Get the spacer row and append it to the container
        _this.spacerNode = _rowProvider.getPrototype("spacer", _this._createSpacerPrototype, _this);
        _this._phDiv = jQuery("td", _this.spacerNode);
        _this.appendNode(_this.spacerNode);
        return _this;
    }
    et2_dataview_spacer.prototype.setCount = function (_count, _rowHeight) {
        // Set the new count and _rowHeight if given
        this._count = _count;
        if (typeof _rowHeight !== "undefined") {
            this._rowHeight = _rowHeight;
        }
        // Update the element height
        this._phDiv.height(this._count * this._rowHeight);
        // Call the invalidate function
        this.invalidate();
    };
    et2_dataview_spacer.prototype.getCount = function () {
        return this._count;
    };
    et2_dataview_spacer.prototype.getHeight = function () {
        // Set the calculated height, so that "invalidate" will work correctly
        this._height = this._count * this._rowHeight;
        return this._height;
    };
    et2_dataview_spacer.prototype.getAvgHeightData = function () {
        if (this._avgCount > 0) {
            return {
                "avgHeight": this._avgSum / this._avgCount,
                "avgCount": this._avgCount
            };
        }
        return null;
    };
    et2_dataview_spacer.prototype.addAvgHeight = function (_height) {
        this._avgSum += _height;
        this._avgCount++;
    };
    /* ---- PRIVATE FUNCTIONS ---- */
    et2_dataview_spacer.prototype._createSpacerPrototype = function (_outerId, _columnIds) {
        var tr = jQuery(document.createElement("tr"));
        var td = jQuery(document.createElement("td"))
            .addClass("egwGridView_spacer")
            .addClass(_outerId + "_spacer_fullRow")
            .attr("colspan", _columnIds.length)
            .appendTo(tr);
        return tr;
    };
    return et2_dataview_spacer;
}(et2_dataview_container));
exports.et2_dataview_spacer = et2_dataview_spacer;
//# sourceMappingURL=et2_dataview_view_spacer.js.map