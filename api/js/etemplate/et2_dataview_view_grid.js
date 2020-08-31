"use strict";
/**
 * EGroupware eTemplate2 - Class which contains the "grid" base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 *

/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_common;

    et2_dataview_interfaces;
    et2_dataview_view_container;
    et2_dataview_view_spacer;
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
var et2_dataview_view_container_1 = require("./et2_dataview_view_container");
var et2_dataview_view_spacer_1 = require("./et2_dataview_view_spacer");
var et2_dataview_grid = /** @class */ (function (_super_1) {
    __extends(et2_dataview_grid, _super_1);
    /**
     * Creates the grid.
     *
     * @param _parent is the parent grid class - if null, this means that this
     * is the outer grid which manages the scrollarea. If not null, all other
     * parameters are ignored and copied from the given grid instance.
     * @param _parentGrid
     * @param _egw
     * @param _rowProvider
     * @param _avgHeight is the starting average height of the column rows.
     * @memberOf et2_dataview_grid
     */
    function et2_dataview_grid(_parent, _parentGrid, _egw, _rowProvider, _avgHeight) {
        var _this = 
        // Call the inherited constructor
        _super_1.call(this, _parent) || this;
        // If the parent is given, copy all other parameters from it
        if (_parentGrid != null) {
            _this.egw = _parent.egw;
            _this._orgAvgHeight = false;
            _this._rowProvider = _parentGrid._rowProvider;
        }
        else {
            // Otherwise copy the given parameters
            _this.egw = _egw;
            _this._orgAvgHeight = _avgHeight;
            _this._rowProvider = _rowProvider;
            // As this grid instance has no parent, we need a scroll container
            _this._scrollHeight = 0;
            _this._scrollTimeout = null;
        }
        _this._parentGrid = _parentGrid;
        _this._scrollTimeout = null;
        _this._invalidateTimeout = null;
        _this._invalidateCallback = null;
        _this._invalidateContext = null;
        // Flag for stopping invalidate while working
        _this.doInvalidate = true;
        // _map contains a mapping between the grid indices and the elements
        // associated to it. The first element in the array always refers to the
        // element starting at index zero (being a spacer if the grid currently
        // displays another range).
        _this._map = [];
        // _viewRange contains the current pixel-range of the grid which is
        // visible.
        _this._viewRange = et2_range(0, 0);
        // Holds the maximum count of elements
        _this._total = 0;
        // Holds data used for storing the current average height data
        _this._avgHeight = false;
        _this._avgCount = -1;
        // Build the outer grid nodes
        _this._createNodes();
        return _this;
    }
    et2_dataview_grid.prototype.destroy = function () {
        // Destroy all containers
        this.setTotalCount(0);
        // Stop the scroll timeout
        if (this._scrollTimeout) {
            window.clearTimeout(this._scrollTimeout);
        }
        // Stop the invalidate timeout
        if (this._invalidateTimeout) {
            window.clearTimeout(this._invalidateTimeout);
        }
        _super_1.prototype.destroy.call(this);
    };
    et2_dataview_grid.prototype.clear = function () {
        // Store the old total count and rescue the current average height in
        // form of the "original average height"
        var oldTotalCount = this._total;
        this._orgAvgHeight = this.getAverageHeight();
        // Set the total count to zero
        this.setTotalCount(0);
        // Reset the total count value
        this.setTotalCount(oldTotalCount);
    };
    /**
     * Throws all elements away which are outside the current view range
     */
    et2_dataview_grid.prototype.cleanup = function () {
        // Update the pixel positions
        this._recalculateElementPosition();
        // Get the visible mapping indices and recalculate index and pixel
        // position of the containers.
        var mapVis = this._calculateVisibleMappingIndices();
        // Delete all invisible elements -- if anything changed, we have to
        // recalculate the pixel positions again
        this._cleanupOutOfRangeElements(mapVis, 0);
    };
    /**
     * The insertRow function can be called to insert the given container(s) at
     * the given row index. If there currently is another container at that
     * given position, the new container(s) will be inserted above the old
     * container. Yet the "total count" of the grid will be preserved by
     * removing the correct count of elements from the next possible spacer. If
     * no spacer is found, the last containers will be removed. This causes
     * inserting new containers at the end of a grid to be immediately removed
     * again.
     *
     * @param _index is the row index at which the given container(s) should be
     * inserted.
     * @param _container is eiter a single et2_dataview_container instance
     * which should be inserted at the given position. Or an array of
     * et2_dataview_container instances. If you want to remove the container
     * don't do that manually by calling its "destroy" function but use the
     * deleteRow function.
     */
    et2_dataview_grid.prototype.insertRow = function (_index, _container) {
        // Calculate the map element the given index refers to
        var idx = this._calculateMapIndex(_index);
        if (idx !== false) {
            // Wrap the container inside an array
            if (_container instanceof et2_dataview_view_container_1.et2_dataview_container) {
                _container = [_container];
            }
            // Fetch the average height
            var avg = this.getAverageHeight();
            // Call the internal _doInsertContainer function
            for (var i = 0; i < _container.length; i++) {
                this._doInsertContainer(_index, idx, _container[i], avg);
            }
            // Schedule an "invalidate" event
            this.invalidate();
        }
    };
    /**
     * The deleteRow function can be used to remove the element at the given
     * index.
     *
     * @param _index is the index from which should be deleted. If the given
     * index is outside the so called "managedRange" nothing will happen, as the
     * container has already been destroyed by the grid instance.
     */
    et2_dataview_grid.prototype.deleteRow = function (_index) {
        // Calculate the map element the given index refers to
        var idx = this._calculateMapIndex(_index);
        if (idx !== false) {
            this._doDeleteContainer(idx, false);
            // Schedule an "invalidate" event
            this.invalidate();
        }
    };
    /**
     * The given callback gets called whenever the scroll position changed or
     * the visible element range changed. The element indices are passed to the
     * function as et2_range.
     */
    et2_dataview_grid.prototype.setInvalidateCallback = function (_callback, _context) {
        this._invalidateCallback = _callback;
        this._invalidateContext = _context;
    };
    /**
     * The setDataCallback function is used to set the callback that will be
     * called when the grid requires new data.
     *
     * @param _callback is the callback function which gets called when the grid
     * needs some new rows.
     * @param _context is the context in which the callback function gets
     * called.
     */
    et2_dataview_grid.prototype.setDataCallback = function (_callback, _context) {
        this._callback = _callback;
        this._context = _context;
    };
    /**
     * The updateTotalCount function can be used to update the total count of
     * rows that are displayed inside the grid. Changing the count always causes
     * the spacer at the bottom (if it exists) to be
     *
     * @param _count specifies how many entries the grid can show.
     */
    et2_dataview_grid.prototype.setTotalCount = function (_count) {
        // Abort if the total count has not changed
        if (_count === this._total)
            return;
        // Calculate how many elements have to be removed/added
        var delta = Math.max(0, _count) - this._total;
        if (delta > 0) {
            this._appendEmptyRows(delta);
        }
        else {
            this._decreaseTotal(-delta);
        }
        this._total = Math.max(0, _count);
        // Schedule an invalidate
        this.invalidate();
    };
    /**
     * Returns the current "total" count.
     */
    et2_dataview_grid.prototype.getTotalCount = function () {
        return this._total;
    };
    /**
     * The setViewRange function updates the range in which rows are shown.
     */
    et2_dataview_grid.prototype.setViewRange = function (_range) {
        // Set the new view range
        this._viewRange = _range;
        // Immediately call the "invalidate" function
        this._doInvalidate();
    };
    /**
     * Return the indices of the currently visible rows.
     */
    et2_dataview_grid.prototype.getVisibleIndexRange = function (_viewRange) {
        function getElemIdx(_elem, _px) {
            if (_elem instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
                return _elem.getIndex()
                    + Math.floor((_px - _elem.getTop()) / this.getAverageHeight());
            }
            return _elem.getIndex();
        }
        var idxTop = 0;
        var idxBottom = 0;
        var vr;
        if (_viewRange) {
            vr = _viewRange;
        }
        else {
            // Calculate the "correct" view range by removing ET2_GRID_VIEW_EXT
            vr = et2_bounds(this._viewRange.top + et2_dataview_grid.ET2_GRID_VIEW_EXT, this._viewRange.bottom - et2_dataview_grid.ET2_GRID_VIEW_EXT);
        }
        // Get the elements at the top and the bottom of the view
        var topElem = null;
        var botElem = null;
        for (var i = 0; i < this._map.length; i++) {
            if (!topElem && this._map[i].getBottom() > vr.top) {
                topElem = this._map[i];
            }
            if (this._map[i].getTop() > vr.bottom) {
                botElem = this._map[i];
                break;
            }
        }
        if (!botElem) {
            botElem = this._map[this._map.length - 1];
        }
        if (topElem) {
            idxTop = getElemIdx.call(this, topElem, vr.top);
            idxBottom = getElemIdx.call(this, botElem, vr.bottom);
        }
        // Return the calculated index top and bottom
        return et2_bounds(idxTop, idxBottom);
    };
    /**
     * Returns index range of all currently managed rows.
     */
    et2_dataview_grid.prototype.getIndexRange = function () {
        var idxTop = false;
        var idxBottom = false;
        for (var i = 0; i < this._map.length; i++) {
            if (!(this._map[i] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer)) {
                var idx = this._map[i].getIndex();
                if (idxTop === false) {
                    idxTop = idx;
                }
                idxBottom = idx;
            }
        }
        return et2_bounds(idxTop, idxBottom);
    };
    /**
     * Updates the scrollheight
     */
    et2_dataview_grid.prototype.setScrollHeight = function (_height) {
        this._scrollHeight = _height;
        // Update the height of the outer container
        if (this.scrollarea) {
            this.scrollarea.height(_height);
        }
        // Update the viewing range
        this.setViewRange(et2_range(this._viewRange.top, this._scrollHeight));
    };
    /**
     * Returns the average row height data, overrides the corresponding function
     * of the et2_dataview_container.
     */
    et2_dataview_grid.prototype.getAvgHeightData = function () {
        if (this._avgHeight === false) {
            var avgCount = 0;
            var avgSum = 0;
            for (var i = 0; i < this._map.length; i++) {
                var data = this._map[i].getAvgHeightData();
                if (data !== null) {
                    avgSum += data.avgHeight * data.avgCount;
                    avgCount += data.avgCount;
                }
            }
            // Calculate the average height, but only if we have a height
            if (avgCount > 0 && avgSum > 0) {
                this._avgHeight = avgSum / avgCount;
                this._avgCount = avgCount;
            }
        }
        // Return the calculated average height if it is available
        if (this._avgHeight !== false) {
            return {
                "avgCount": this._avgCount,
                "avgHeight": this._avgHeight
            };
        }
        // Otherwise return the parent average height
        if (this._parent) {
            return this._parent.getAvgHeightData();
        }
        // Otherwise return the original average height given in the constructor
        if (this._orgAvgHeight !== false) {
            return {
                "avgCount": 1,
                "avgHeight": this._orgAvgHeight
            };
        }
        return null;
    };
    /**
     * Returns the average row height in pixels.
     */
    et2_dataview_grid.prototype.getAverageHeight = function () {
        var data = this.getAvgHeightData();
        return data ? data.avgHeight : 19;
    };
    /**
     * Returns the row provider.
     */
    et2_dataview_grid.prototype.getRowProvider = function () {
        return this._rowProvider;
    };
    /**
     * Called whenever the size of this or another element in the container tree
     * changes.
     */
    et2_dataview_grid.prototype.invalidate = function () {
        // Clear any existing "invalidate" timeout
        if (this._invalidateTimeout) {
            window.clearTimeout(this._invalidateTimeout);
        }
        if (!this.doInvalidate) {
            return;
        }
        var self = this;
        var _super = _super_1.prototype.invalidate.call(this);
        this._invalidateTimeout = window.setTimeout(function () {
            egw.debug("log", "Dataview grid timed invalidate");
            // Clear the "_avgHeight"
            self._avgHeight = false;
            self._avgCount = -1;
            self._invalidateTimeout = null;
            self._doInvalidate(_super);
        }, et2_dataview_grid.ET2_GRID_INVALIDATE_TIMEOUT);
    };
    /**
     * Makes the given index visible: TODO: Propagate this to the parent grid.
     */
    et2_dataview_grid.prototype.makeIndexVisible = function (_idx) {
        // Get the element range
        var elemRange = this._getElementRange(_idx);
        // Abort if the index was out of range
        if (!elemRange) {
            return false;
        }
        // Calculate the current visible range
        var visibleRange = et2_bounds(this._viewRange.top + et2_dataview_grid.ET2_GRID_VIEW_EXT, this._viewRange.bottom - et2_dataview_grid.ET2_GRID_VIEW_EXT);
        // Check whether the element is currently completely visible -- if yes,
        // do nothing
        if (visibleRange.top < elemRange.top
            && visibleRange.bottom > elemRange.bottom) {
            return true;
        }
        if (elemRange.top < visibleRange.top) {
            this.scrollarea.scrollTop(elemRange.top);
        }
        else {
            var h = elemRange.bottom - elemRange.top;
            this.scrollarea.scrollTop(elemRange.top - this._scrollHeight + h);
        }
    };
    /* ---- PRIVATE FUNCTIONS ---- */
    /*	_inspectStructuralIntegrity: function() {
            var idx = 0;
            for (var i = 0; i < this._map.length; i++)
            {
                if (this._map[i].getIndex() != idx)
                {
                    throw "Index missmatch!";
                }
                idx += this._map[i].getCount();
            }
    
            if (idx !== this._total)
            {
                throw "Total count missmatch!";
            }
        },*/
    /**
     * Translates the given index to a range, returns false if the index is
     * out of range.
     */
    et2_dataview_grid.prototype._getElementRange = function (_idx) {
        // Recalculate the element positions
        this._recalculateElementPosition();
        // Translate the given index to the map index
        var mapIdx = this._calculateMapIndex(_idx);
        // Do nothing if the given index is out of range
        if (mapIdx === false) {
            return false;
        }
        // Get the map element
        var elem = this._map[mapIdx];
        // Get the element range
        if (elem instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
            var avg = this.getAverageHeight();
            return et2_range(elem.getTop() + avg * (elem.getIndex() - _idx), avg);
        }
        return elem.getRange();
    };
    /**
     * Recalculates the position of the currently managed containers. This
     * routine only updates the pixel position of the elements -- the index of
     * the elements is guaranteed to be maintained correctly by all high level
     * functions of the grid, as the index position is needed to be correct for
     * the "deleteRow" and "insertRow" functions, and we cannot effort to call
     * this calculation method after every change in the grid mapping.
     */
    et2_dataview_grid.prototype._recalculateElementPosition = function () {
        for (var i = 0; i < this._map.length; i++) {
            if (i == 0) {
                this._map[i].setTop(0);
            }
            else {
                this._map[i].setTop(this._map[i - 1].getBottom());
            }
        }
    };
    /**
     * The "_calculateVisibleMappingIndices" function calculates the indices of
     * the _map array, which refer to containers that are currently (partially)
     * visible. This function is used internally by "_doInvalidate".
     */
    et2_dataview_grid.prototype._calculateVisibleMappingIndices = function () {
        // First update the "top" and "bottom", and "index" values of all
        // managed elements, and at the same time calculate the mapping indices
        // of the elements which are inside the current view range.
        var mapVis = { "top": -1, "bottom": -1 };
        for (var i = 0; i < this._map.length; i++) {
            // Update the top of the "map visible index" -- set it to the first
            // element index, where the bottom line is beneath the top line
            // of the view range.
            if (mapVis.top === -1
                && this._map[i].getBottom() > this._viewRange.top) {
                mapVis.top = i;
            }
            // Update the bottom of the "map visible index" -- set it to the
            // first element index, where the top line is beneath the bottom
            // line of the view range.
            if (mapVis.bottom === -1
                && this._map[i].getTop() > this._viewRange.bottom) {
                mapVis.bottom = i;
                break;
            }
        }
        return mapVis;
    };
    /**
     * Deletes all elements which are "out of the view range". This function is
     * internally used by "_doInvalidate". How many elements that are out of the
     * view range get preserved fully depends on the _holdCount parameter
     * variable.
     *
     * @param _mapVis contains the _map indices of the just visible containers.
     * @param _holdCount contains the number of elements that should be kept,
     * if not given, this parameter defaults to ET2_GRID_HOLD_COUNT
     */
    et2_dataview_grid.prototype._cleanupOutOfRangeElements = function (_mapVis, _holdCount) {
        // Iterates over the map from and to the given indices and pushes all
        // elements onto the given array, which are more than _holdCount
        // elements remote from the start.
        function searchElements(_arr, _start, _stop, _dir) {
            var dist = 0;
            for (var i_1 = _start; _dir > 0 ? i_1 <= _stop : i_1 >= _stop; i_1 += _dir) {
                if (dist > _holdCount) {
                    _arr.push(i_1);
                }
                else {
                    dist += this._map[i_1].getCount();
                }
            }
        }
        // Set _holdCount to ET2_GRID_HOLD_COUNT if the parameters is not given
        _holdCount = typeof _holdCount === "undefined" ? et2_dataview_grid.ET2_GRID_HOLD_COUNT :
            _holdCount;
        // Collect all elements that will be deleted at the top and at the
        // bottom of the grid
        var deleteTop = [];
        var deleteBottom = [];
        if (_mapVis.top !== -1) {
            searchElements.call(this, deleteTop, _mapVis.top, 0, -1);
        }
        if (_mapVis.bottom !== -1) {
            searchElements.call(this, deleteBottom, _mapVis.bottom, this._map.length - 1, 1);
        }
        // The offset variable specifies how many elements have been deleted
        // from the map -- this variable is needed as deleting elements from the
        // map shifts the map indices. We iterate in oposite direction over the
        // elements, as this allows the _doDeleteContainer/ container function
        // to extend the (possibly) existing spacer at the top of the grid
        var offs = 0;
        for (var i = deleteTop.length - 1; i >= 0; i--) {
            // Delete the container and calculate the new offset
            var mapLength = this._map.length;
            this._doDeleteContainer(deleteTop[i] - offs, true);
            offs += mapLength - this._map.length;
        }
        for (var i = deleteBottom.length - 1; i >= 0; i--) {
            this._doDeleteContainer(deleteBottom[i] - offs, true);
        }
        return deleteBottom.length + deleteTop.length > 0;
    };
    /**
     * The _updateContainers function is used internally by "_doInvalidate" in
     * order to call the "setViewRange" function of all containers the implement
     * that interfaces (needed for nested grids), and to request new elements
     * for all currently visible spacers.
     */
    et2_dataview_grid.prototype._updateContainers = function () {
        var _loop_1 = function (i) {
            var container = this_1._map[i];
            // Check which type the container object has
            var isSpacer = container instanceof et2_dataview_view_spacer_1.et2_dataview_spacer;
            var hasIViewRange = !isSpacer
                && implements_et2_dataview_IViewRange(container);
            // If the container has one of those special types, calculate the
            // view range and use that to update the view range of the element
            // or to request new elements for the spacer
            if (isSpacer || hasIViewRange) {
                // Calculate the relative view range and check whether
                // the element is really visible
                var elemRange = container.getRange();
                // Abort if the element is not inside the visible range
                if (!et2_rangeIntersect(this_1._viewRange, elemRange)) {
                    return "continue";
                }
                if (hasIViewRange) {
                    // Update the view range of the container
                    container.setViewRange(et2_bounds(this_1._viewRange.top - elemRange.top, this_1._viewRange.bottom - elemRange.top));
                }
                else // This container is a spacer
                 {
                    // Obtain the average element height
                    var avg = container._rowHeight;
                    // Get the visible container range (vcr)
                    var vcr_top = Math.max(this_1._viewRange.top, elemRange.top);
                    var vcr_bot = Math.min(this_1._viewRange.bottom, elemRange.bottom);
                    // Calculate the indices of the elements which will be
                    // requested
                    var cidx = container.getIndex();
                    var ccnt = container.getCount();
                    // Calculate the start index -- prevent vtop from getting
                    // negative (and so idxStart being smaller than cidx) and
                    // ensure that idxStart is not larger than the maximum
                    // container index.
                    var vtop = Math.max(0, vcr_top);
                    var idxStart_1 = Math.floor(Math.min(cidx + ccnt - 1, cidx + (vtop - elemRange.top) / avg, this_1._total));
                    // Calculate the end index -- prevent vtop from getting
                    // negative (and so idxEnd being smaller than cidx) and
                    // ensure that idxEnd is not larger than the maximum
                    // container index.
                    var vbot = Math.max(0, vcr_bot);
                    var idxEnd_1 = Math.ceil(Math.min(cidx + ccnt - 1, cidx + (vbot - elemRange.top) / avg, this_1._total));
                    // Initial resize while the grid is hidden will give NaN
                    // This is an important optimisation, as it is involved in not
                    // loading all rows, so we override in that case so
                    // there are more than the 2-3 that fit in the min height.
                    if (isNaN(idxStart_1) && isSpacer)
                        idxStart_1 = cidx - 1;
                    if (isNaN(idxEnd_1) && isSpacer && this_1._scrollHeight > 0 && elemRange.bottom == 0) {
                        idxEnd_1 = Math.min(ccnt, cidx + Math.ceil((this_1._viewRange.bottom - container._top) / (this_1._orgAvgHeight || 0)));
                    }
                    // Call the data callback
                    if (this_1._callback) {
                        var self_1 = this_1;
                        egw.debug("log", "Dataview grid flag for update: ", { start: idxStart_1, end: idxEnd_1 });
                        window.setTimeout(function () {
                            // If row template changes, self._callback might disappear
                            if (typeof self_1._callback != "undefined") {
                                self_1._callback.call(self_1._context, idxStart_1, idxEnd_1);
                            }
                        }, 0);
                    }
                }
            }
        };
        var this_1 = this;
        for (var i = 0; i < this._map.length; i++) {
            _loop_1(i);
        }
    };
    /**
     * Invalidate iterates over the "mapping" array. It calculates which
     * containers have to be removed and where new containers should be added.
     */
    et2_dataview_grid.prototype._doInvalidate = function (_super) {
        if (!this.doInvalidate)
            return;
        // Not visible?
        if (jQuery(":visible", this.outerCell).length == 0) {
            return;
        }
        // Update the pixel positions
        this._recalculateElementPosition();
        // Call the callback
        if (this._invalidateCallback) {
            var range = this.getVisibleIndexRange(et2_range(this.scrollarea.scrollTop(), this._scrollHeight));
            this._invalidateCallback.call(this._invalidateContext, range);
        }
        // Get the visible mapping indices and recalculate index and pixel
        // position of the containers.
        var mapVis = this._calculateVisibleMappingIndices();
        // Delete all invisible elements -- if anything changed, we have to
        // recalculate the pixel positions again
        if (this._cleanupOutOfRangeElements(mapVis)) {
            this._recalculateElementPosition();
        }
        // Update the view range of all visible elements that implement the
        // corresponding interface and request elements for all visible spacers
        this._updateContainers();
        // Call the inherited invalidate function, broadcast the invalidation
        // through the container tree.
        if (this._parent && _super) {
            _super._doInvalidate();
        }
    };
    /**
     * Translates the given grid index into the element index of the map. If the
     * given index is completely out of the range, "false" is returned.
     */
    et2_dataview_grid.prototype._calculateMapIndex = function (_index) {
        var top = 0;
        var bot = this._map.length - 1;
        while (top <= bot) {
            var idx = Math.floor((top + bot) / 2);
            var elem = this._map[idx];
            var realIdx = elem.getIndex();
            var realCnt = elem.getCount();
            if (_index >= realIdx && _index < realIdx + realCnt) {
                return idx;
            }
            else if (_index < realIdx) {
                bot = idx - 1;
            }
            else {
                top = idx + 1;
            }
        }
        return false;
    };
    et2_dataview_grid.prototype._insertContainerAtSpacer = function (_index, _mapIndex, _mapElem, _container, _avg) {
        // Set the index of the new container
        _container.setIndex(_index);
        // Calculate at which position the spacer has to be splitted
        var splitIdx = _index - _mapElem.getIndex();
        // Get the count of elements that remain at the top of the splitter
        var cntTop = splitIdx;
        // Get the count of elements that remain at the bottom of the splitter
        // -- it has to be one element less than before
        var cntBottom = _mapElem.getCount() - splitIdx - 1;
        // Split the containers if cntTop and cntBottom are larger than zero
        if (cntTop > 0 && cntBottom > 0) {
            // Set the new count of the currently existing container, preserving
            // its height as it was
            _mapElem.setCount(cntTop);
            // Add the new element after the old container
            _container.insertIntoTree(_mapElem.getLastNode());
            // Create a new spacer and add it after the newly inserted container
            var newSpacer = new et2_dataview_view_spacer_1.et2_dataview_spacer(this, this._rowProvider);
            newSpacer.setCount(cntBottom, _avg);
            newSpacer.setIndex(_index + 1);
            newSpacer.insertIntoTree(_container.getLastNode());
            // Insert the container and the new spacer into the map
            this._map.splice(_mapIndex + 1, 0, _container, newSpacer);
        }
        else if (cntTop === 0 && cntBottom > 0) {
            // Simply adjust the size of the old spacer and insert the new
            // container in front of it
            _container.insertIntoTree(_mapElem.getFirstNode(), true);
            _mapElem.setIndex(_index + 1);
            _mapElem.setCount(cntBottom, _avg);
            this._map.splice(_mapIndex, 0, _container);
        }
        else if (cntTop > 0 && cntBottom === 0) {
            // Simply add the new container to the end of the old container and
            // adjust the count of the old spacer to the remaining count.
            _container.insertIntoTree(_mapElem.getLastNode());
            _mapElem.setCount(cntTop);
            this._map.splice(_mapIndex + 1, 0, _container);
        }
        else // if (cntTop === 0 && cntBottom === 0)
         {
            // Append the new container to the current container and then
            // destroy the old container
            _container.insertIntoTree(_mapElem.getLastNode());
            _mapElem.destroy();
            this._map.splice(_mapIndex, 1, _container);
        }
    };
    et2_dataview_grid.prototype._insertContainerAtElement = function (_index, _mapIndex, _mapElem, _container, _avg) {
        // In a first step, simply insert the element at the specified position,
        // in front of the element _mapElem.
        _container.setIndex(_index);
        _container.insertIntoTree(_mapElem.getFirstNode(), true);
        this._map.splice(_mapIndex, 0, _container);
        // Search for the next spacer and increment the indices of all other
        // elements until there
        var _newIndex = _index + 1;
        for (var i = _mapIndex + 1; i < this._map.length; i++) {
            // Update the index of the element
            this._map[i].setIndex(_newIndex++);
            // We've found a spacer -- decrement its element count and abort
            if (this._map[i] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
                this._decrementSpacerCount(i, _avg);
                return;
            }
        }
        // We've found no spacer so far, remove the last element from the map in
        // order to obtain the "totalCount" (especially the last element is no
        // spacer, so the following code cannot remove a spacer)
        this._map.pop().destroy();
    };
    /**
     * Inserts the given container at the given index.
     */
    et2_dataview_grid.prototype._doInsertContainer = function (_index, _mapIndex, _container, _avg) {
        // Check whether the given element at the map index is a spacer. If
        // yes, we have to split the spacer at that position.
        var mapElem = this._map[_mapIndex];
        if (mapElem instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
            this._insertContainerAtSpacer(_index, _mapIndex, mapElem, _container, _avg);
        }
        else {
            this._insertContainerAtElement(_index, _mapIndex, mapElem, _container, _avg);
        }
    };
    /**
     * Replaces the container at the given index with a spacer. The function
     * tries to extend any spacer lying above or below the given _mapIndex.
     * This code does not destroy the given container, but maintains its map
     * index.
     *
     * @param _mapIndex is the index of _mapElem in the _map array.
     * @param _mapElem is the container which should be replaced.
     */
    et2_dataview_grid.prototype._replaceContainerWithSpacer = function (_mapIndex, _mapElem) {
        var newAvg;
        var spacer;
        var totalHeight;
        var totalCount;
        // Check whether a spacer can be extended above or below the given
        // _mapIndex
        var spacerAbove = null;
        var spacerBelow = null;
        if (_mapIndex > 0
            && this._map[_mapIndex - 1] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
            spacerAbove = this._map[_mapIndex - 1];
        }
        if (_mapIndex < this._map.length - 1
            && this._map[_mapIndex + 1] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
            spacerBelow = this._map[_mapIndex + 1];
        }
        if (!spacerAbove && !spacerBelow) {
            // No spacer can be extended -- simply create a new one
            spacer = new et2_dataview_view_spacer_1.et2_dataview_spacer(this, this._rowProvider);
            spacer.setIndex(_mapElem.getIndex());
            spacer.addAvgHeight(_mapElem.getHeight());
            spacer.setCount(1, _mapElem.getHeight());
            // Insert the new spacer at the correct place into the DOM tree and
            // the mapping
            spacer.insertIntoTree(_mapElem.getLastNode());
            this._map.splice(_mapIndex + 1, 0, spacer);
        }
        else if (spacerAbove && spacerBelow) {
            // We're going to consolidate the upper and the lower spacer. To do
            // that we'll calculate a new count of elements and a new average
            // height, so that the upper container can get the height of all
            // three elements together
            totalHeight = spacerAbove.getHeight() + spacerBelow.getHeight()
                + _mapElem.getHeight();
            totalCount = spacerAbove.getCount() + spacerBelow.getCount()
                + 1;
            newAvg = totalHeight / totalCount;
            // Update the upper spacer
            spacerAbove.addAvgHeight(_mapElem.getHeight());
            spacerAbove.setCount(totalCount, newAvg);
            // Delete the lower spacer and remove it from the mapping
            spacerBelow.destroy();
            this._map.splice(_mapIndex + 1, 1);
        }
        else {
            // One of the two spacers is available
            spacer = spacerAbove || spacerBelow;
            // Calculate the new count and the new average height of that spacer
            totalCount = spacer.getCount() + 1;
            totalHeight = spacer.getHeight() + _mapElem.getHeight();
            newAvg = totalHeight / totalCount;
            // Set the new container height
            spacer.setIndex(Math.min(spacer.getIndex(), _mapElem.getIndex()));
            spacer.addAvgHeight(_mapElem.getHeight());
            spacer.setCount(totalCount, newAvg);
        }
    };
    /**
     * Checks whether there is another spacer below the given map index and if
     * yes, consolidates the two.
     */
    et2_dataview_grid.prototype._consolidateSpacers = function (_mapIndex) {
        if (_mapIndex < this._map.length - 1
            && this._map[_mapIndex] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer
            && this._map[_mapIndex + 1] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
            var spacerAbove = this._map[_mapIndex];
            var spacerBelow = this._map[_mapIndex + 1];
            // Calculate the new height/count of both containers
            var totalHeight = spacerAbove.getHeight() + spacerBelow.getHeight();
            var totalCount = spacerAbove.getCount() + spacerBelow.getCount();
            var newAvg = totalCount / totalHeight;
            // Extend the new spacer
            spacerAbove.setCount(totalCount, newAvg);
            // Delete the old spacer
            spacerBelow.destroy();
            this._map.splice(_mapIndex + 1, 1);
        }
    };
    /**
     * Decrements the count of the spacer at the given _mapIndex by one. If the
     * given spacer has no more elements, it will be removed from the mapping.
     * Note that this function does not update the indices of the following
     * elements, this function is only used internally by the
     * _insertContainerAtElement function and the _doDeleteContainer function
     * where appropriate adjustments to the map data structure are done.
     *
     * @param _mapIndex is the index of the spacer in the "map" data structure.
     * @param _avg is the new average height of the container, may be
     * "undefined" in which case the height of the spacer rows is kept as it
     * was.
     */
    et2_dataview_grid.prototype._decrementSpacerCount = function (_mapIndex, _avg) {
        var cnt = this._map[_mapIndex].getCount() - 1;
        if (cnt > 0) {
            this._map[_mapIndex].setCount(cnt, _avg);
        }
        else {
            this._map[_mapIndex].destroy();
            this._map.splice(_mapIndex, 1);
        }
    };
    /**
     * Deletes the container at the given index.
     */
    et2_dataview_grid.prototype._doDeleteContainer = function (_mapIndex, _replaceWithSpacer) {
        // _replaceWithSpacer defaults to false
        _replaceWithSpacer = _replaceWithSpacer ? _replaceWithSpacer : false;
        // Fetch the element at the given map index
        var mapElem = this._map[_mapIndex];
        // Indicates whether an element has really been removed -- if yes, the
        // bottom spacer will be extended
        var removedElement = false;
        // Check whether the map element is a spacer -- if yes, we have to do
        // some special treatment
        if (mapElem instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
            // Do nothing if the "_replaceWithSpacer" flag is true as the
            // element already is a spacer
            if (!_replaceWithSpacer) {
                this._decrementSpacerCount(_mapIndex);
                removedElement = true;
            }
        }
        else {
            if (_replaceWithSpacer) {
                this._replaceContainerWithSpacer(_mapIndex, mapElem);
            }
            else {
                removedElement = true;
            }
            // Remove the complete (current) container, decrement the _mapIndex
            this._map[_mapIndex].destroy();
            this._map.splice(_mapIndex, 1);
            _mapIndex--;
            // The delete operation may have created two joining spacers -- this
            // is highly suboptimal, so we'll consolidate those two spacers
            this._consolidateSpacers(_mapIndex);
        }
        // Update the indices of all elements after the current one, if we've
        // really removed an element
        if (removedElement) {
            for (var i = _mapIndex + 1; i < this._map.length; i++) {
                this._map[i].setIndex(this._map[i].getIndex() - 1);
            }
            // Extend the last spacer as we have to maintain the spacer count
            this._appendEmptyRows(1);
        }
    };
    /**
     * The appendEmptyRows function is used internally to append empty rows to
     * the end of the table. This functionality is needed in order to maintain
     * the "total count" in the _doDeleteContainer function and to increase the
     * "total count" in the "setCount" function.
     *
     * @param _count specifies the count of empty rows that will be added to the
     * end of the table.
     */
    et2_dataview_grid.prototype._appendEmptyRows = function (_count) {
        // Special case -- the last element in the "_map" is no spacer -- this
        // means, that the "managedRange" is currently at the bottom of the list
        // -- so we have to insert a new spacer
        var spacer = null;
        var lastIndex = this._map.length - 1;
        if (this._map.length === 0 ||
            !(this._map[lastIndex] instanceof et2_dataview_view_spacer_1.et2_dataview_spacer)) {
            // Create a new spacer
            spacer = new et2_dataview_view_spacer_1.et2_dataview_spacer(this, this._rowProvider);
            // Insert the spacer -- we have a special case if there currently is
            // no element inside the mapping
            if (this._map.length === 0) {
                // Add a dummy element to the grid
                var dummy = jQuery(document.createElement("tr"));
                this.innerTbody.append(dummy);
                // Append the spacer to the grid
                spacer.setIndex(0);
                spacer.insertIntoTree(dummy, false);
                // Remove the dummy element
                dummy.remove();
            }
            else {
                // Insert the new spacer after the last element
                spacer.setIndex(this._map[lastIndex].getIndex() + 1);
                spacer.insertIntoTree(this._map[lastIndex].getLastNode());
            }
            // Add the new spacer to the mapping
            this._map.push(spacer);
        }
        else {
            // Get the spacer at the bottom of the mapping
            spacer = this._map[lastIndex];
        }
        // Update the spacer count
        spacer.setCount(_count + spacer.getCount(), this.getAverageHeight());
    };
    /**
     * The _decreaseTotal function is used to decrease the total row count in
     * the grid. It tries to remove the given count of rows from the spacer
     * located at the bottom of the grid, if this is not possible, it starts
     * removing complete rows.
     *
     * @param _delta specifies how many rows should be removed.
     */
    et2_dataview_grid.prototype._decreaseTotal = function (_delta) {
        // Iterate over the current mapping, starting at the bottom and delete
        // rows. _delta is decreased for each removed row. Abort when delta is
        // zero or the map is empty
        while (_delta > 0 && this._map.length > 0) {
            var cont = this._map[this._map.length - 1];
            // Remove as many containers as possible from spacers
            if (cont instanceof et2_dataview_view_spacer_1.et2_dataview_spacer) {
                var diff = cont.getCount() - _delta;
                if (diff > 0) {
                    // We're done as the spacer still has entries left
                    _delta = 0;
                    cont.setCount(diff, this.getAverageHeight());
                    break;
                }
                else {
                    // Decrease _delta by the count of rows the spacer had
                    _delta -= diff + _delta;
                }
            }
            else {
                // We're going to remove a single row: remove it
                _delta -= 1;
            }
            // Destroy the container if there are no rows left
            cont.destroy();
            this._map.pop();
        }
        // Check whether _delta is really zero
        if (_delta > 0) {
            this.egw.debug('error', "Error while decreasing the total count - requested to remove more rows than available.");
        }
    };
    /**
     * Creates the grid DOM-Nodes
     */
    et2_dataview_grid.prototype._createNodes = function () {
        this.tr = jQuery(document.createElement("tr"));
        this.outerCell = jQuery(document.createElement("td"))
            .addClass("frame")
            .attr("colspan", this._rowProvider.getColumnCount()
            + (this._parentGrid ? 0 : 1))
            .appendTo(this.tr);
        // Create the scrollarea div if this is the outer grid
        this.scrollarea = null;
        if (this._parentGrid == null) {
            this.scrollarea = jQuery(document.createElement("div"))
                .addClass("egwGridView_scrollarea")
                .scroll(this, function (e) {
                // Clear any older scroll timeout
                if (e.data._scrollTimeout) {
                    window.clearTimeout(e.data._scrollTimeout);
                }
                // Clear any existing "invalidate" timeout (as the
                // "setViewRange" function triggered by the scroll event
                // forces an "invalidate").
                if (e.data._invalidateTimeout) {
                    window.clearTimeout(e.data._invalidateTimeout);
                    e.data._invalidateTimeout = null;
                }
                // Set a new timeout which calls the setViewArea
                // function
                e.data._scrollTimeout = window.setTimeout(jQuery.proxy(function () {
                    var newRange = et2_range(this.scrollarea.scrollTop() - et2_dataview_grid.ET2_GRID_VIEW_EXT, this._scrollHeight + et2_dataview_grid.ET2_GRID_VIEW_EXT * 2);
                    if (!et2_rangeEqual(newRange, this._viewRange)) {
                        this.setViewRange(newRange);
                    }
                }, e.data), et2_dataview_grid.ET2_GRID_SCROLL_TIMEOUT);
            })
                .height(this._scrollHeight)
                .appendTo(this.outerCell);
        }
        // Create the inner table
        var table = jQuery(document.createElement("table"))
            .addClass("egwGridView_grid")
            .appendTo(this.scrollarea ? this.scrollarea : this.outerCell);
        this.innerTbody = jQuery(document.createElement("tbody"))
            .appendTo(table);
        // Set the tr as container element
        this.appendNode(jQuery(this.tr[0]));
    };
    /**
     * Determines how many pixels the view range of the gridview is extended inside
     * the scroll callback.
     */
    et2_dataview_grid.ET2_GRID_VIEW_EXT = 50;
    /**
     * Determines the timeout after which the scroll-event is processed.
     */
    et2_dataview_grid.ET2_GRID_SCROLL_TIMEOUT = 50;
    /**
     * Determines the timeout after which the invalidate-request gets processed.
     */
    et2_dataview_grid.ET2_GRID_INVALIDATE_TIMEOUT = 25;
    /**
     * Determines how many elements are kept displayed outside of the current view
     * range until they get removed.
     */
    et2_dataview_grid.ET2_GRID_HOLD_COUNT = 50;
    return et2_dataview_grid;
}(et2_dataview_view_container_1.et2_dataview_container));
exports.et2_dataview_grid = et2_dataview_grid;
//# sourceMappingURL=et2_dataview_view_grid.js.map