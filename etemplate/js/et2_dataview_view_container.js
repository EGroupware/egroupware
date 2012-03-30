/**
 * eGroupWare eTemplate2 - dataview code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2012
 * @version $Id$
 */

"use strict"

/*egw:uses
	jquery.jquery;
	et2_dataview_interfaces;
*/

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
 */
var et2_dataview_container = Class.extend(et2_dataview_IInvalidatable, {

	/**
	 * Initializes the container object.
	 *
	 * @param _parent is an object which implements the IInvalidatable
	 * 	interface. _parent may not be null.
	 */
	init: function(_parent) {
		// Copy the given invalidation element
		this._parent = _parent;

		this._nodes = []; // contains all DOM-Nodes this container exists of
		this._inTree = false; //
		this._attachData = {"node": null, "prepend": false};
		this._destroyCallback = null;
		this._destroyContext = null;

		this._height = false;
		this._index = 0;
		this._top = 0;
	},

	/**
	 * Destroys this container. Classes deriving from et2_dataview_container
	 * should override this method and take care of unregistering all event
	 * handlers etc.
	 */
	destroy: function() {
		// Remove the nodes from the tree
		this.removeFromTree();

		// Call the callback function (if one is registered)
		if (this._destroyCallback)
		{
			this._destroyCallback.call(this._destroyContext, this);
		}
	},

	/**
	 * Sets the "destroyCallback" -- the given function gets called whenever
	 * the container is destroyed. This instance is passed as an parameter to
	 * the callback.
	 */
	setDestroyCallback: function(_callback, _context) {
		this._destroyCallback = _callback;
		this._destroyContext = _context;
	},

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
	insertIntoTree: function(_node, _prepend) {

		if (!this._inTree && _node != null && this._nodes.length > 0)
		{
			// Store the parent node and indicate that this element is now in
			// the tree.
			this._attachData = {"node": _node, "prepend": _prepend};
			this._inTree = true;

			for (var i = 0; i < this._nodes.length; i++)
			{
				if (i == 0)
				{
					if (_prepend)
					{
						_node.before(this._nodes[0]);
					}
					else
					{
						_node.after(this._nodes[0]);
					}
				}
				else
				{
					// Insert all following nodes after the previous node
					this._nodes[i - 1].after(this._nodes[i]);
				}
			}

			// Invalidate this element in order to update the height of the
			// parent
			this.invalidate();
		}
	},

	/**
	 * Removes all container nodes from the tree.
	 */
	removeFromTree: function() {
		if (this._inTree)
		{
			// Call the jQuery remove function to remove all nodes from the tree
			// again.
			for (var i = 0; i < this._nodes.length; i++)
			{
				this._nodes[i].remove();
			}

			// Reset the "attachData"
			this._inTree = false;
			this._attachData = {"node": null, "prepend": false};
		}
	},

	/**
	 * Appends a node to the container.
	 *
	 * @param _node is the DOM-Node which should be appended.
	 */
	appendNode: function(_node) {
		// Add the given node to the "nodes" array
		this._nodes.push(_node);

		// If the container is already in the tree, attach the given node to the
		// tree.
		if (this._inTree)
		{
			if (this._nodes.length === 1)
			{
				if (this._attachData.prepend)
				{
					this._attachData.node.before(_node);
				}
				else
				{
					this._attachData.node.after(_node);
				}
			}
			else
			{
				this._nodes[this._nodes.length - 2].after(_node);
			}

			this.invalidate();
		}
	},

	/**
	 * Removes a certain node from the container
	 */
	removeNode: function(_node) {
		// Get the index of the node in the nodes array
		var idx = this._nodes.indexOf(_node);

		if (idx >= 0)
		{
			// Remove the node if the container is currently attached
			if (this._inTree)
			{
				_node.parentNode.removeChild(_node);
			}

			// Remove the node from the nodes array
			this._nodes.splice(idx, 1);
		}
	},

	/**
	 * Returns the last node of the container - new nodes have to be appended
	 * after it.
	 */
	getLastNode: function() {

		if (this._nodes.length > 0)
		{
			return this._nodes[this._nodes.length - 1];
		}

		return null;
	},

	/**
	 * Returns the first node of the container.
	 */
	getFirstNode: function() {
		return this._nodes.length > 0 ? this._nodes[0] : null;
	},

	/**
	 * Returns the accumulated height of all container nodes. Only visible nodes
	 * (without "display: none" etc.) are taken into account.
	 */
	getHeight: function() {
		if (this._height === false && this._inTree)
		{
			this._height = 0;

			// Increment the height value for each visible container node
			for (var i = 0; i < this._nodes.length; i++)
			{
				if (this._isVisible(this._nodes[i][0]))
				{
					this._height += this._nodeHeight(this._nodes[i][0]);
				}
			}
		}

		return this._height === false ? 0 : this._height;
	},

	/**
	 * Returns a datastructure containing information used for calculating the
	 * average row height of a grid.
	 * The datastructure has the
	 * {
	 *     avgHeight: <the calculated average height of this element>,
	 *     avgCount: <the element count this calculation was based on>
	 * }
	 */
	getAvgHeightData: function() {
		return {
			"avgHeight": this.getHeight(),
			"avgCount": 1
		}
	},

	/**
	 * Returns the previously set "pixel top" of the container.
	 */
	getTop: function() {
		return this._top;
	},

	/**
	 * Returns the "pixel bottom" of the container.
	 */
	getBottom: function() {
		return this._top + this.getHeight();
	},

	/**
	 * Returns the range of the element.
	 */
	getRange: function() {
		return et2_bounds(this.getTop(), this.getBottom());
	},

	/**
	 * Returns the index of the element.
	 */
	getIndex: function() {
		return this._index;
	},

	/**
	 * Returns how many elements this container represents.
	 */
	getCount: function() {
		return 1;
	},

	/**
	 * Sets the top of the element.
	 */
	setTop: function(_value) {
		this._top = _value;
	},

	/**
	 * Sets the index of the element.
	 */
	setIndex: function(_value) {
		this._index = _value;
	},

	/* -- et2_dataview_IInvalidatable -- */

	/**
	 * Broadcasts an invalidation through the container tree. Marks the own
	 * height as invalid.
	 */
	invalidate: function() {
		// Abort if this element is already marked as invalid.
		if (this._height !== false)
		{
			// Delete the own, probably computed height
			this._height = false;

			// Broadcast the invalidation to the parent element
			this._parent.invalidate();
		}
	},


	/* -- PRIVATE FUNCTIONS -- */


	/**
	 * Used to check whether an element is visible or not (non recursive).
	 *
	 * @param _obj is the element which should be checked for visibility, it is
	 * only checked whether some stylesheet makes the element invisible, not if
	 * the given object is actually inside the DOM.
	 */
	_isVisible: function(_obj) {

		// Check whether the element is localy invisible
		if (_obj.style && (_obj.style.display === "none"
		    || _obj.style.visiblity === "none"))
		{
			return false;
		}

		// Get the computed style of the element
		var style = window.getComputedStyle ? window.getComputedStyle(_obj, null)
			: _obj.currentStyle;
		if (style.display === "none" || style.visibility === "none")
		{
			return false;
		}

		return true;
	}

});

/**
 * Returns the height of a node in pixels and zero if the element is not
 * visible. The height is clamped to positive values.
 * The browser switch is placed at this position as the _nodeHeight function is
 * one of the most frequently called functions in the whole grid code and should
 * stay quite fast.
 */
/*if ($j.browser.mozilla)
{
	et2_dataview_container.prototype._nodeHeight = function(_node)
	{
		var height = 0;
		// Firefox sometimes provides fractional pixel values - we are
		// forced to use those - we can obtain the fractional pixel height
		// by using the window.getComputedStyle function
		var compStyle = getComputedStyle(_node, null);
		if (compStyle)
		{
			var styleHeightStr = compStyle.getPropertyValue("height");
			height = parseFloat(styleHeightStr.substr(0,
				styleHeightStr.length - 2));

			if (isNaN(height) || height < 1)
			{
				height = 0;
			}
		}

		return height;
	}
}
else
{*/
	et2_dataview_container.prototype._nodeHeight = function(_node)
	{
		return _node.offsetHeight;
	}
//}

