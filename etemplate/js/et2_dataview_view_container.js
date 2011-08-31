/**
 * eGroupWare eTemplate2 - Class which contains the "row" base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict"

/*egw:uses
	jquery.jquery;
	et2_dataview_interfaces;
*/

var et2_dataview_container = Class.extend(et2_dataview_IInvalidatable, {

	/**
	 * Initializes the container object.
	 *
	 * @param _dataProvider is the data provider for the element
	 * @param _rowProvider is the "rowProvider" of the element
	 * @param _invalidationElem is the element of which the "invalidate" method 
	 * 	will be called if the height of the elements changes. It has to
	 * 	implement the et2_dataview_IInvalidatable interface.
	 */
	init: function(_dataProvider, _rowProvider, _invalidationElem) {
		this.dataProvider = _dataProvider;
		this.rowProvider = _rowProvider;
		this._invalidationElem = _invalidationElem;

		this._nodes = [];
		this._inTree = false;
		this._attachData = {"node": null, "prepend": false};
	},

	destroy: function() {
		// Remove the nodes from the tree
		this.removeFromTree();
	},

	/**
	 * Setter function which can be used to update the invalidation element.
	 *
	 * @param _invalidationElem is the element of which the "invalidate" method 
	 * 	will be called if the height of the elements changes. It has to
	 * 	implement the et2_dataview_IInvalidatable interface.
	 */
	setInvalidationElement: function(_invalidationElem) {
		this._invalidationElem = _invalidationElem;
	},

	/**
	 * Inserts all container nodes into the DOM tree after the given element
	 */
	insertIntoTree: function(_afterNode, _prepend) {
		if (!this._inTree && _afterNode != null)
		{
			for (var i = 0; i < this._nodes.length; i++)
			{
				if (i == 0)
				{
					if (_prepend)
					{
						_afterNode.prepend(this._nodes[i]);
					}
					else
					{
						_afterNode.after(this._nodes[i]);
					}
				}
				else
				{
					// Insert all following nodes after the previous node
					this._nodes[i - 1].after(this._nodes[i]);
				}
			}

			// Save the "attachData"
			this._inTree = true;
			this._attachData = {"node": _afterNode, "prepend": _prepend};

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
	 * Appends a jQuery node to the container
	 */
	appendNode: function(_node) {
		// Add the given node to the "nodes" array
		this._nodes.push(_node);

		// If the container is already in the tree, attach the given node to the
		// tree.
		if (this._inTree)
		{
			if (this._nodes.length == 1)
			{
				if (_prepend)
				{
					this._attachData.node.prepend(this._nodes[0]);
				}
				else
				{
					this._attachData.node.after(this._nodes[0]);
				}
			}
			else
			{
				this._nodes[_nodes.length - 2].after(_node);
			}

			this.invalidate();
		}
	},

	/**
	 * Removes a jQuery node from the container
	 */
	removeNode: function(_node) {
		// Get the index of the node in the nodes array
		var idx = this._nodes.indexOf(_node);

		if (idx >= 0)
		{
			// Remove the node if the container is currently attached
			if (this._inTree)
			{
				_node.remove();
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
	 * Returns the accumulated height of all container nodes. Only visible nodes
	 * (without "display: none") are taken into account.
	 */
	getHeight: function() {
		var height = 0;

		if (this._inTree)
		{
			// Increment the height value for each visible container node
			var self = this;
			$j(this._nodes, ":visible").each(function() {
				height += self._nodeHeight(this[0]);
			});
		}

		return height;
	},

	/**
	 * Calls the "invalidate" function of the connected invalidation element.
	 */
	invalidate: function() {
		this._invalidationElem.invalidate();
	}

});

/**
 * Returns the height of the container in pixels and zero if the element is not
 * visible. The height is clamped to positive values.
 * The browser switch is placed at this position as the getHeight function is one
 * of the mostly called functions in the whole grid code and should stay
 * quite fast.
 */
if ($j.browser.mozilla)
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
{
	et2_dataview_container.prototype._nodeHeight = function(_node)
	{
		return _node.offsetHeight;
	}
}

