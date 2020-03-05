"use strict";
/**
 * EGroupware eTemplate2 - dataview code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2012
 * @version $Id$
 */
Object.defineProperty(exports, "__esModule", { value: true });
/**
 * The et2_dataview_container class is the main object each dataview consits of.
 * Each row, spacer as well as the grid itself are containers. A container is
 * described by its parent element and a certain height. On the DOM-Level a
 * container may consist of multiple "tr" nodes, which are treated as a unit.
 * Some containers (like grid containers) are capable of managing a set of child
 * containers. Each container can indicate, that it thinks that it's height
 * might have changed. In that case it informs its parent element about that.
 * The only requirement for the parent element is, that it implements the
 * et2_dataview_IInvalidatable interface.
 * A container does not know where it resides inside the grid, or whether it is
 * currently visible or not -- this information is efficiently managed by the
 * et2_dataview_grid container.
 *
 * @augments Class
 */
var et2_dataview_container = /** @class */ (function () {
    /**
     * Initializes the container object.
     *
     * @param _parent is an object which implements the IInvalidatable
     * 	interface. _parent may not be null.
     * @memberOf et2_dataview_container
     */
    function et2_dataview_container(_parent) {
        // Copy the given invalidation element
        this._parent = _parent;
        this._nodes = [];
        this._inTree = false;
        this._attachData = { "node": null, "prepend": false };
        this._destroyCallback = null;
        this._destroyContext = null;
        this._height = -1;
        this._index = 0;
        this._top = 0;
    }
    /**
     * Destroys this container. Classes deriving from et2_dataview_container
     * should override this method and take care of unregistering all event
     * handlers etc.
     */
    et2_dataview_container.prototype.destroy = function () {
        // Remove the nodes from the tree
        this.removeFromTree();
        // Call the callback function (if one is registered)
        if (this._destroyCallback) {
            this._destroyCallback.call(this._destroyContext, this);
        }
    };
    /**
     * Sets the "destroyCallback" -- the given function gets called whenever
     * the container is destroyed. This instance is passed as an parameter to
     * the callback.
     *
     * @param {function} _callback
     * @param {object} _context
     */
    et2_dataview_container.prototype.setDestroyCallback = function (_callback, _context) {
        this._destroyCallback = _callback;
        this._destroyContext = _context;
    };
    /**
     * Inserts all container nodes into the DOM tree after or before the given
     * element.
     *
     * @param _node is the node after/before which the container "tr"s should
     * get inserted. _node should be a simple DOM node, not a jQuery object.
     * @param _prepend specifies whether the container should be inserted before
     * or after the given node. Inserting before is needed for inserting the
     * first element in front of an spacer.
     */
    et2_dataview_container.prototype.insertIntoTree = function (_node, _prepend) {
        if (!this._inTree && _node != null && this._nodes.length > 0) {
            // Store the parent node and indicate that this element is now in
            // the tree.
            this._attachData = { node: _node, prepend: _prepend };
            this._inTree = true;
            for (var i = 0; i < this._nodes.length; i++) {
                if (i == 0) {
                    if (_prepend) {
                        _node.before(this._nodes[0]);
                    }
                    else {
                        _node.after(this._nodes[0]);
                    }
                }
                else {
                    // Insert all following nodes after the previous node
                    this._nodes[i - 1].after(this._nodes[i]);
                }
            }
            // Invalidate this element in order to update the height of the
            // parent
            this.invalidate();
        }
    };
    /**
     * Removes all container nodes from the tree.
     */
    et2_dataview_container.prototype.removeFromTree = function () {
        if (this._inTree) {
            // Call the jQuery remove function to remove all nodes from the tree
            // again.
            for (var i = 0; i < this._nodes.length; i++) {
                this._nodes[i].remove();
            }
            // Reset the "attachData"
            this._inTree = false;
            this._attachData = { "node": null, "prepend": false };
        }
    };
    /**
     * Appends a node to the container.
     *
     * @param _node is the DOM-Node which should be appended.
     */
    et2_dataview_container.prototype.appendNode = function (_node) {
        // Add the given node to the "nodes" array
        this._nodes.push(_node);
        // If the container is already in the tree, attach the given node to the
        // tree.
        if (this._inTree) {
            if (this._nodes.length === 1) {
                if (this._attachData.prepend) {
                    this._attachData.node.before(_node);
                }
                else {
                    this._attachData.node.after(_node);
                }
            }
            else {
                this._nodes[this._nodes.length - 2].after(_node);
            }
            this.invalidate();
        }
    };
    /**
     * Removes a certain node from the container
     *
     * @param {HTMLElement} _node
     */
    et2_dataview_container.prototype.removeNode = function (_node) {
        // Get the index of the node in the nodes array
        var idx = this._nodes.indexOf(_node);
        if (idx >= 0) {
            // Remove the node if the container is currently attached
            if (this._inTree) {
                _node.parentNode.removeChild(_node);
            }
            // Remove the node from the nodes array
            this._nodes.splice(idx, 1);
        }
    };
    /**
     * Returns the last node of the container - new nodes have to be appended
     * after it.
     */
    et2_dataview_container.prototype.getLastNode = function () {
        if (this._nodes.length > 0) {
            return this._nodes[this._nodes.length - 1];
        }
        return null;
    };
    /**
     * Returns the first node of the container.
     */
    et2_dataview_container.prototype.getFirstNode = function () {
        return this._nodes.length > 0 ? this._nodes[0] : null;
    };
    /**
     * Returns the accumulated height of all container nodes. Only visible nodes
     * (without "display: none" etc.) are taken into account.
     */
    et2_dataview_container.prototype.getHeight = function () {
        if (this._height === -1 && this._inTree) {
            this._height = 0;
            // Setting this before measuring height helps with issues getting the
            // wrong height due to margins & collapsed borders
            this.tr.css('display', 'block');
            // Increment the height value for each visible container node
            for (var i = 0; i < this._nodes.length; i++) {
                if (et2_dataview_container._isVisible(this._nodes[i][0])) {
                    this._height += et2_dataview_container._nodeHeight(this._nodes[i][0]);
                }
            }
            this.tr.css('display', '');
        }
        return (this._height === -1) ? 0 : this._height;
    };
    /**
     * Returns a datastructure containing information used for calculating the
     * average row height of a grid.
     * The datastructure has the
     * {
     *     avgHeight: <the calculated average height of this element>,
     *     avgCount: <the element count this calculation was based on>
     * }
     */
    et2_dataview_container.prototype.getAvgHeightData = function () {
        return {
            "avgHeight": this.getHeight(),
            "avgCount": 1
        };
    };
    /**
     * Returns the previously set "pixel top" of the container.
     */
    et2_dataview_container.prototype.getTop = function () {
        return this._top;
    };
    /**
     * Returns the "pixel bottom" of the container.
     */
    et2_dataview_container.prototype.getBottom = function () {
        return this._top + this.getHeight();
    };
    /**
     * Returns the range of the element.
     */
    et2_dataview_container.prototype.getRange = function () {
        return et2_bounds(this.getTop(), this.getBottom());
    };
    /**
     * Returns the index of the element.
     */
    et2_dataview_container.prototype.getIndex = function () {
        return this._index;
    };
    /**
     * Returns how many elements this container represents.
     */
    et2_dataview_container.prototype.getCount = function () {
        return 1;
    };
    /**
     * Sets the top of the element.
     *
     * @param {number} _value
     */
    et2_dataview_container.prototype.setTop = function (_value) {
        this._top = _value;
    };
    /**
     * Sets the index of the element.
     *
     * @param {number} _value
     */
    et2_dataview_container.prototype.setIndex = function (_value) {
        this._index = _value;
    };
    /* -- et2_dataview_IInvalidatable -- */
    /**
     * Broadcasts an invalidation through the container tree. Marks the own
     * height as invalid.
     */
    et2_dataview_container.prototype.invalidate = function () {
        // Abort if this element is already marked as invalid.
        if (this._height !== -1) {
            // Delete the own, probably computed height
            this._height = -1;
            // Broadcast the invalidation to the parent element
            this._parent.invalidate();
        }
    };
    /* -- PRIVATE FUNCTIONS -- */
    /**
     * Used to check whether an element is visible or not (non recursive).
     *
     * @param _obj is the element which should be checked for visibility, it is
     * only checked whether some stylesheet makes the element invisible, not if
     * the given object is actually inside the DOM.
     */
    et2_dataview_container._isVisible = function (_obj) {
        // Check whether the element is localy invisible
        if (_obj.style && (_obj.style.display === "none"
            || _obj.style.visibility === "none")) {
            return false;
        }
        // Get the computed style of the element
        var style = window.getComputedStyle ? window.getComputedStyle(_obj, null)
            // @ts-ignore
            : _obj.currentStyle;
        if (style.display === "none" || style.visibility === "none") {
            return false;
        }
        return true;
    };
    /**
     * Returns the height of a node in pixels and zero if the element is not
     * visible. The height is clamped to positive values.
     *
     * @param {HTMLElement} _node
     */
    et2_dataview_container._nodeHeight = function (_node) {
        return _node.offsetHeight;
    };
    return et2_dataview_container;
}());
exports.et2_dataview_container = et2_dataview_container;
//# sourceMappingURL=et2_dataview_view_container.js.map