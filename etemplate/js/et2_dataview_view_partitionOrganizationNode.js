/**
 * eGroupWare eTemplate2 - Class which implements the organization node
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
	et2_core_common; // for et2_range functions
	et2_core_inheritance;
	et2_dataview_interfaces;
	et2_dataview_view_partitionNode;
*/

/**
 * The ET2_PARTITION_TREE_WIDTH defines the count of children a node will be
 * created with.
 */
var ET2_PARTITION_TREE_WIDTH = 10;

/**
 * An partition tree organization node can contain child nodes and organizes
 * those.
 */
var et2_dataview_partitionOrganizationNode = et2_dataview_partitionNode.extend(
	/*et2_dataview_IIndexOperations, */{

	init: function(_root, _parent, _pidx) {

		if (typeof _parent == "undefined")
		{
			_parent = null;
		}

		if (typeof _pidx == "undefined")
		{
			_pidx = 0;
		}

		// Call the parent constructor
		this._super(_root);

		this._children =  [];

		// Set the given parent and parent-index
		this.setParent(_parent);
		this.setPIdx(_pidx);

	},

	destroy: function() {
		// Free all child nodes
		for (var i = this._children.length - 1; i >= 0; i--)
		{
			this._children[i].free();
		}

		this._super();
	},

	/**
	 * Delete the buffered element count
	 */
	doInvalidate: function() {
		this._super();

		this._count = false;
		this._depth = false;
		this._avgHeightData = false;
	},

	/**
	 * Calculates the count of elements by accumulating the counts of the child
	 * elements.
	 */
	getCount: function() {
		if (this._count === false)
		{
			// Calculate the count of nodes
			this._count = 0;
			for (var i = 0; i < this._children.length; i++)
			{
				this._count += this._children[i].getCount();
			}
		}

		return this._count;
	},

	/**
	 * Calculates the height of this node by accumulating the height of the
	 * child nodes.
	 */
	calculateHeight: function() {
		var result = 0;
		for (var i = 0; i < this._children.length; i++)
		{
			result += this._children[i].getHeight();
		}

		return result;
	},

	/**
	 * Removes the given node from the tree
	 */
	removeNode: function(_node) {
		// Search the element on this level
		for (var i = 0; i < this._children.length; i++)
		{
			if (this._children[i] == _node)
			{
				this.removePIdxNode(i);
				return true;
			}
		}

		// Search the element on a lower level
		for (var i = 0; i < this._children.length; i++)
		{
			if (this._children[i] instanceof et2_dataview_partitionOrganizationNode &&
			    this._children[i].removeNode(_node))
			{
				return true;
			}
		}

		return false;
	},

	/**
	 * Removes the child with the given index in the _children list
	 */
	removePIdxNode: function(_pidx) {
		// Invalidate this element
		this.invalidate();

		// Delete the element at the given pidx and remove the parent reference
		this._children.splice(_pidx, 1)[0].setParent(null);

		// Recalculate the pidx of the children behind the one removed
		for (var i = _pidx; i < this._children.length; i++)
		{
			this._children[i]._pidx--;
		}

		return true;
	},

	/**
	 * Removes the child with the given overall index
	 */
	removeIdxNode: function(_idx) {
		return this._iterateToIndex(_idx, function(ei, bi, child) {
			if (child.implements(et2_dataview_IIndexOperations))
			{
				return child.removeIdxNode(_idx);
			}

			return this.removePIdxNode(i);
		}, false);
	},

	/**
	 * Returns the node with the given overall index and null if it is not found
	 */
	getIdxNode: function(_idx) {
		return this._iterateToIndex(_idx, function(ei, bi, child) {
			if (child.implements(et2_dataview_IIndexOperations))
			{
				return child.getIdxNode()
			}

			if (idx == bi)
			{
				return child;
			}
		}, null);
	},

	/**
	 * getNodeAt returns the DOM node at the given index
	 */
	getNodeAt: function(_idx) {
		return this._iterateToIndex(_idx, function(ei, bi, child) {
			return child.getNodeAt(_idx);
		}, null);
	},

	/**
	 * Returns all nodes in the given range
	 */
	getRangeNodes: function(_range, _create) {

		if (typeof _create == "undefined")
		{
			_create = true;
		}

		var result = [];

		// Create a copy of the children of this element, as the child list may
		// change due to new children being inserted.
		var children = this._copyChildren();

		// We did not have a intersect in the range now
		var hadIntersect = false;
		for (var i = 0; i < children.length; i++)
		{
			if (children[i].inRange(_range))
			{
				hadIntersect = true;

				var res = children[i].getRangeNodes(_range, _create);

				if (res === false)
				{
					return this.getRangeNodes(_range, _create);
				}

				// Append the search results of the given element
				result = result.concat(res);
			}
			else
			{
				// Abort as we are out of the range where intersects can happen
				if (hadIntersect)
				{
					break;
				}
			}
		}

		return result;
	},

	/**
	 * Returns the nodes which are inside the given range
	 */
	getIdxRangeNodes: function(_idxRange, _create) {

		if (typeof _create == "undefined")
		{
			_create = true;
		}

		var result = [];

		// Create a copy of the children of this element, as the child list may
		// change due to new children being inserted.
		var children = this._copyChildren();

		// We did not have a intersect in the range now
		var hadIntersect = false;
		for (var i = 0; i < children.length; i++)
		{
			if (children[i].inIdxRange(_idxRange))
			{
				hadIntersect = true;

				// Append the search results of the given element
				var res = children[i].getIdxRangeNodes(_idxRange,
					_create);

				if (res === false)
				{
					return this.getIdxRangeNodes(_idxRange, _create);
				}

				result = result.concat(res);
			}
			else
			{
				// Abort as we are out of the range where intersects can happen
				if (hadIntersect)
				{
					break;
				}
			}
		}

		return result;
	},

	/**
	 * Reduces the given range to a spacer
	 */
	reduceRange: function(_range) {
		this._reduce(this.getRangeNodes(_range, false))
	},

	/**
	 * Reduces the given index range to a spacer
	 */
	reduceIdxRange: function(_range) {
		this._reduce(this.getIdxRangeNodes(_range, false));
	},

	getDepth: function() {
		if (this._depth === false)
		{
			this._depth = 0;

			// Get the maximum depth and increase it by one
			for (var i = 0; i < this._children.length; i++)
			{
				this._depth = Math.max(this._depth, this._children[i].getDepth());
			}
			this._depth++;
		}

		return this._depth;
	},

	_insertLeft: function(_idx, _nodes) {
		// Check whether the node left to the given index can still take some
		// nodes - if yes, insert the maximum amount of nodes into this node
		if (_idx > 0 && this._children[_idx - 1] instanceof et2_dataview_partitionOrganizationNode
		    && this._children[_idx - 1]._children.length < ET2_PARTITION_TREE_WIDTH)
		{
			// Calculate how many children can be inserted into the left node
			var child = this._children[_idx - 1];
			var c = Math.min(ET2_PARTITION_TREE_WIDTH - child._children.length, _nodes.length);

			// Insert the remaining children into the left node
			if (c > 0)
			{
				var nodes = _nodes.splice(0, c);
				child.insertNodes(child._children.length, nodes);
			}
		}
	},

	_insertRight: function(_idx, _nodes) {
		// Check whether the node right to the given index can still take some
		// nodes - if yes, insert the nodes there
		if (_idx < this._children.length &&
		    this._children[_idx] instanceof et2_dataview_partitionOrganizationNode &&
		    this._children[_idx]._children.length < ET2_PARTITION_TREE_WIDTH)
		{
			var child = this._children[_idx];
			var c = Math.min(ET2_PARTITION_TREE_WIDTH - child._children.length, _nodes.length);

			// Insert the remaining children into the left node
			if (c > 0)
			{
				var nodes = _nodes.splice(_nodes.length - c, c);
				child.insertNodes(0, nodes);
			}
		}
	},

	/**
	 * Groups the nodes which should be inserted by packages of ten and insert
	 * those as children. If there are more than ET2_PARTITION_TREE_WIDTH
	 * children as a result of this action, this node gets destroyed and the
	 * children are given to the parent node.
	 */
	insertNodes: function(_idx, _nodes) {
		// Break if no nodes are to be inserted
		if (_nodes.length == 0)
		{
			return;
		}

		// Invalidate this node
		this.invalidate();

		// Try to insert the given objects into an organization node at the left
		// or right side of the given index
		this._insertLeft(_idx, _nodes);
		this._insertRight(_idx, _nodes);

		// Update the pidx of the nodes after _idx
		for (var i = _idx; i < this._children.length; i++)
		{
			this._children[i].setPIdx(i + _nodes.length);
		}

		// Set the parent and the pidx of the new nodes
		for (var i = 0; i < _nodes.length; i++)
		{
			_nodes[i].setParent(this);
			_nodes[i].setPIdx(_idx + i);
		}

		// Simply insert the nodes at the given position
		this._children.splice.apply(this._children, [_idx, 0].concat(_nodes));

		// Check whether the width of this element is greater than ET2_PARTITION_TREE_WIDTH
		// If yes, split the children into groups of ET2_PARTITION_TREE_WIDTH and
		// insert those into this node
		/*if (this._children.length > ET2_PARTITION_TREE_WIDTH)
		{
			var insertNodes = [];

			while (_nodes.length > 0)
			{
				var orgaNode = new et2_dataview_partitionOrganizationNode(this,
					insertNodes.length);

				// Get groups of ET2_PARTITION_TREE_WIDTH from the nodes while
				// reading the first level of nodes from organization nodes
				var newNodes = [];
				var isPartial = false;
				while (newNodes.length < ET2_PARTITION_TREE_WIDTH && _nodes.length > 0)
				{
					var node = _nodes[0];

					if (!(node instanceof et2_dataview_partitionOrganizationNode))
					{
						newNodes.push(_nodes.shift());
						isPartial = true;
					}
					else
					{
						if (node._children.length == 0)
						{
							// Remove the node from the list and free it
							_nodes.shift().free();
						}
						else
						{
							if (!isPartial && node._children.length == ET2_PARTITION_TREE_WIDTH)
							{
								newNodes.push(_nodes.shift());
							}
							else
							{
								newNodes = newNodes.concat(node._children.splice(0,
									ET2_PARTITION_TREE_WIDTH - newNodes.length));
								isPartial = true;
							}
						}
					}
				}

				orgaNode.insertNodes(0, newNodes);

				insertNodes.push(orgaNode);
			}

			this._children = [];
			this.insertNodes(0, insertNodes);
		}*/
	},

	rebuild: function() {
		// Get all leafs
		var children = [];
		this._getFlatList(children);

		// Free all organization nodes
		this._clear();

		this.insertNodes(0, children);
	},

	/**
	 * Accumulates the "average height" data
	 */
	getAvgHeightData: function(_data) {
		if (this._avgHeightData == false)
		{
			this._avgHeightData = {"count": 0, "height": 0};

			for (var i = 0; i < this._children.length; i++)
			{
				this._children[i].getAvgHeightData(this._avgHeightData);
			}

			this._invalid = false;
		}

		// Increment the data object entries by the buffered avg height data
		_data.count += this._avgHeightData.count;
		_data.height += this._avgHeightData.height;
	},

	debug: function() {
		var children = [];
		var offs = false;
		this._getFlatList(children);

		for (var i = 0; i < children.length; i++)
		{
			var idx = children[i].getStartIndex();
			var node = children[i].getNodeAt(idx);

			if (node)
			{
				if (offs === false)
				{
					offs = node.offset().top;
				}

				var actualTop = node.offset().top - offs;
				var calculatedTop = children[i].getPosTop();

				if (Math.abs(actualTop - calculatedTop) > 1)
				{
					et2_debug("warn", i, "Position missmatch at idx ", idx,
						actualTop, calculatedTop, node);
				}

				var actualHeight = node.outerHeight(true);
				var calculateHeight = children[i].getHeight();

				if (Math.abs(actualHeight - calculateHeight) > 1)
				{
					et2_debug("warn", i, "Height missmatch at idx ", idx,
						actualHeight, calculateHeight, node);
				}
			}
		}
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_copyChildren: function() {
		// Copy the child array as querying the child nodes may change the tree
		var children = new Array(this._children.length);
		for (var i = 0; i < this._children.length; i++)
		{
			children[i] = this._children[i];
		}

		return children;
	},

	_iterateToIndex: function(_idx, _func, _res) {
		for (var i = 0; i < this._children.length; i++)
		{
			var child = this._children[i];

			var bi = child.getStartIndex();
			var ei = child.getStopIndex();

			if (bi > _idx)
			{
				return res;
			}

			if (bi <= _idx && ei > _idx)
			{
				return _func.call(this, bi, ei, child);
			}
		}

		return res;
	},

	/**
	 * Reduces the given nodes to a single spacer
	 */
	_reduce: function(_nodes) {
/*		if (_nodes.length == 0)
		{
			return;
		}

		// Check whether the first or last node is a spacer, if not create
		// a new one
		var ph;
		if (_nodes[0] instanceof et2_dataview_partitionSpacerNode)
		{
			ph = _nodes[0]
		}
		else if (_nodes[_nodes.length - 1] instanceof et2_dataview_partitionSpacerNode)
		{
			ph = _nodes[_nodes.length - 1];
		}
		else
		{
			// Create a new spacer node and insert it at the place of the
			// first node of the range
			ph = new et2_dataview_partitionSpacerNode(this.getRoot(), 0, 0);
			this.getRoot().insertNodes(_nodes[0].getStartIndex(), [ph]);
		}

		// Get the height of the resulting spacer
		var height = _nodes[_nodes.length - 1].getPosBottom() - _nodes[0].getPosTop();

		// Get the count of actual elements in the nodes
		var count = 0;
		for (var i = 0; i < _nodes.length; i++)
		{
			count += _nodes[i].getCount();
		}

		// Update the spacer parameters
		et2_debug("log", "Spacer new height, count: ", height, count);
		ph.setAvgHeight(height / count);
		ph.setCount(count);

		// Free all elements (except for the spacer)
		for (var i = _nodes.length - 1; i >= 0; i--)
		{
			if (_nodes[i] != ph)
			{
				_nodes[i].free();
			}
		}*/
	},

	/**
	 * Used when rebuilding the tree
	 */
	_getFlatList: function(_res) {
		for (var i = 0; i < this._children.length; i++)
		{
			if (this._children[i] instanceof et2_dataview_partitionOrganizationNode)
			{
				this._children[i]._getFlatList(_res);
			}
			else
			{
				_res.push(this._children[i]);
			}
		}
	},

	_clear: function() {
		for (var i = this._children.length - 1; i >= 0; i--)
		{
			if (this._children[i] instanceof et2_dataview_partitionOrganizationNode)
			{
				this._children[i].free();
			}
		}

		this._children = [];
	}
});

