"use strict";
/**
 * EGroupware eTemplate2 - dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011-2012
 * @version $Id$
 *

/*egw:uses
    egw_action.egw_action;

    et2_dataview_view_container;
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
var et2_dataview_row = /** @class */ (function (_super) {
    __extends(et2_dataview_row, _super);
    /**
     * Creates the row container. Use the "setRow" function to load the actual
     * row content.
     *
     * @param _parent is the row parent container.
     */
    function et2_dataview_row(_parent) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent) || this;
        // Create the outer "tr" tag and append it to the container
        _this.tr = jQuery(document.createElement("tr"));
        _this.appendNode(_this.tr);
        // Grid row which gets expanded when clicking on the corresponding
        // button
        _this.expansionContainer = null;
        _this.expansionVisible = false;
        // Toggle button which is used to show and hide the expansionContainer
        _this.expansionButton = null;
        return _this;
    }
    et2_dataview_row.prototype.destroy = function () {
        if (this.expansionContainer != null) {
            this.expansionContainer.destroy();
        }
        _super.prototype.destroy.call(this);
    };
    et2_dataview_row.prototype.clear = function () {
        this.tr.empty();
    };
    et2_dataview_row.prototype.makeExpandable = function (_expandable, _callback, _context) {
        if (_expandable) {
            // Create the tr and the button if this has not been done yet
            if (!this.expansionButton) {
                this.expansionButton = jQuery(document.createElement("span"));
                this.expansionButton.addClass("arrow closed");
            }
            // Update context
            var self = this;
            this.expansionButton.off("click").on("click", function (e) {
                self._handleExpansionButtonClick(_callback, _context);
                e.stopImmediatePropagation();
            });
            jQuery("td:first", this.tr).prepend(this.expansionButton);
        }
        else {
            // If the row is made non-expandable, remove the created DOM-Nodes
            if (this.expansionButton) {
                this.expansionButton.remove();
            }
            if (this.expansionContainer) {
                this.expansionContainer.destroy();
            }
            this.expansionButton = null;
            this.expansionContainer = null;
        }
    };
    et2_dataview_row.prototype.removeFromTree = function () {
        if (this.expansionContainer) {
            this.expansionContainer.removeFromTree();
        }
        this.expansionContainer = null;
        this.expansionButton = null;
        _super.prototype.removeFromTree.call(this);
    };
    et2_dataview_row.prototype.getDOMNode = function () {
        return this.tr[0];
    };
    et2_dataview_row.prototype.getJNode = function () {
        return this.tr;
    };
    et2_dataview_row.prototype.getHeight = function () {
        var h = _super.prototype.getHeight.call(this);
        if (this.expansionContainer && this.expansionVisible) {
            h += this.expansionContainer.getHeight();
        }
        return h;
    };
    et2_dataview_row.prototype.getAvgHeightData = function () {
        // Only take the height of the own tr into account
        //var oldVisible = this.expansionVisible;
        this.expansionVisible = false;
        var res = {
            "avgHeight": this.getHeight(),
            "avgCount": 1
        };
        this.expansionVisible = true;
        return res;
    };
    /** -- PRIVATE FUNCTIONS -- **/
    et2_dataview_row.prototype._handleExpansionButtonClick = function (_callback, _context) {
        // Create the "expansionContainer" if it does not exist yet
        if (!this.expansionContainer) {
            this.expansionContainer = _callback.call(_context);
            this.expansionContainer.insertIntoTree(this.tr);
            this.expansionVisible = false;
        }
        // Toggle the visibility of the expansion tr
        this.expansionVisible = !this.expansionVisible;
        jQuery(this.expansionContainer._nodes[0]).toggle(this.expansionVisible);
        // Set the class of the arrow
        if (this.expansionVisible) {
            this.expansionButton.addClass("opened");
            this.expansionButton.removeClass("closed");
        }
        else {
            this.expansionButton.addClass("closed");
            this.expansionButton.removeClass("opened");
        }
        this.invalidate();
    };
    /** -- Implementation of et2_dataview_IViewRange -- **/
    et2_dataview_row.prototype.setViewRange = function (_range) {
        if (this.expansionContainer && this.expansionVisible
            && implements_et2_dataview_IViewRange(this.expansionContainer)) {
            // Substract the height of the own row from the container
            var oh = jQuery(this._nodes[0]).height();
            _range.top -= oh;
            // Proxy the setViewRange call to the expansion container
            this.expansionContainer.setViewRange(_range);
        }
    };
    return et2_dataview_row;
}(et2_dataview_container));
exports.et2_dataview_row = et2_dataview_row;
//# sourceMappingURL=et2_dataview_view_row.js.map