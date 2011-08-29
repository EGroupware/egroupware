/**
 * eGroupWare eTemplate2 - Class which contains an management tree for the grid rows
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
*/

/**
 * The ET2_PARTITION_TREE_WIDTH defines the count of children a node will be
 * created with.
 */
var ET2_PARTITION_TREE_WIDTH = 10;

/**
 * The partition node tree manages all rows in a dataview. As a dataview may have
 * many thousands of lines, the rows are organized in an a tree. The leafs of the
 * tree represent the single rows, upper layers represent groups of nodes.
 * Each node has a "height" value and is capable of calculate the exact position
 * of a row and its top and bottom value.
 * Additionaly, a leaf can represent an unlimited number of rows. In this way
 * the partition tree is built dynamically and is also capable of "forgetting"
 * information about the rows by simply reducing the tree nodes at a certain
 * position.
 */

var et2_dataview_IPartitionHeight = new Interface({

	calculateHeight: function() {}

});

/**
 * Abstract base class for partition nodes - contains the code for calculating
 * the top, bottom, height and (start) index of the node
 */
var et2_dataview_partitionNode = Class.extend([et2_dataview_IPartitionHeight,
	et2_dataview_IInvalidatable], {

	init: function() {

		this._parent = null;
		this._pidx = 0;

		// Initialize the temporary storage elements
		this.doInvalidate();
		this._invalid = true;

	},

	destroy: function() {

		// Remove this element from the parent children list
		if (this._parent)
		{
			this._parent.removePIdxNode(this._pidx);
		}

	},

	setParent: function(_parent) {
		if (this._parent != _parent)
		{
			this._parent = _parent;
			this.invalidate();
		}
	},

	setPIdx: function(_pidx) {
		if (this._pidx != _pidx)
		{
			this._pidx = _pidx;
			this.invalidate();
		}
	},

	/**
	 * Invalidates cached values - override the "doInvalidate" function.
	 *
	 * @param _sender is the node wich originally triggerd the invalidation, can
	 * 	be ommited when calling this function.
	 */
	invalidate: function(_sender) {

		// If the _sender parameter is not given, assume that this element is
		// the one which triggered the invalidation
		var origin = typeof _sender == "undefined";

		if ((origin || _sender != this) && !this._invalid)
		{
			this.doInvalidate();
			this._invalid = true;

			// Invalidate the parent node
			if (this._parent)
			{
				this._parent.invalidate(origin ? this : _sender);
			}
		}
	},

	/**
	 * Performs the actual invalidation.
	 */
	doInvalidate: function() {
		this._height = false;
		this._posTop = false;
		this._posBottom = false;
		this._startIdx = false;
		this._stopIdx = false;
	},

	/**
	 * Returns the root node of the partition tree
	 */
	getRoot: function() {
		if (this.parent != null)
		{
			return this.parent.getRoot();
		}

		return this;
	},

	/**
	 * Returns the height of this node
	 */
	getHeight: function() {
		// Calculate the height value if it is currently invalid
		if (this._height === false)
		{
			this._height = this.calculateHeight();

			// Do a sanity check for the value - if the height wasn't a number
			// it could easily destroy the posTop and posBottom values of the
			// complete tree!
			if (isNaN(this._height))
			{
				et2_debug("error", "calculateHeight returned a NaN value!");
				this._height = 0;
			}

			this._invalid = false;
		}

		return this._height;
	},

	/**
	 * Returns the top position of the node in px
	 */
	getPosTop: function() {
		if (this._posTop === false)
		{
			this._posTop = this._accumulateValue(this.getPosTop, this.getPosBottom);

			this._invalid = false;
		}

		return this._posTop;
	},

	/**
	 * Returns the bottom position of the node in px
	 */
	getPosBottom: function() {
		if (this._posBottom === false)
		{
			this._posBottom = this.getPosTop() + this.getHeight();

			this._invalid = false;
		}

		return this._posBottom;
	},

	/**
	 * Returns an range object
	 */
	getRange: function() {
		return {
			"top": this.getPosTop(),
			"bottom": this.getPosBottom()
		};
	},


	/**
	 * Returns true if the node intersects with the given range
	 */
	inRange: function(_ar) {
		return et2_rangeIntersect(this.getRange(), _ar);
	},

	/**
	 * Returns the overall start index of the node
	 */
	getStartIndex: function() {
		if (this._startIdx === false)
		{
			this._startIdx = this._accumulateValue(this.getStartIndex,
				this.getStopIndex);

			this._invalid = false;
		}

		return this._startIdx;
	},

	/**
	 * Returns the overall stop index of the node
	 */
	getStopIndex: function() {
		if (this._stopIdx === false)
		{
			this._stopIdx = this.getStartIndex() + this.getCount();

			this._invalid = false;
		}

		return this._stopIdx;
	},

	/**
	 * Returns the index range object
	 */
	getIdxRange: function() {
		return {
			"top": this.getStartIndex(),
			"bottom": this.getStopIndex()
		};
	},

	/**
	 * Checks whether this element is inside the given index range
	 */
	inIdxRange: function(_idxRange) {
		return et2_rangeIntersect(this.getIdxRange, _idxRange);
	},

	/**
	 * Returns the count of leafs which are below this node
	 */
	getCount: function() {
		return 1;
	},

	/**
	 * Returns the nodes which reside in the given range
	 */
	getRangeNodes: function(_range, _create) {
		if (this.inRange(_range))
		{
			return [this];
		}

		return [];
	},

	/**
	 * Returns the nodes which are inside the given index range
	 */
	getIdxRangeNodes: function(_idxRange, _create) {
		if (this.inIdxRange(_idxRange))
		{
			return [this];
		}

		return [];
	},

	/**
	 * Returns the (maximum) depth of the tree
	 */
	getDepth: function() {
		return 1;
	},

	getAvgHeightData: function(_data) {
		_data.cnt++;
		_data.height += this.getHeight();
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_accumulateValue: function(_f1, _f2) {
		if (this._parent)
		{
			if (this._pidx == 0)
			{
				return _f1.call(this._parent);
			}
			else
			{
				return _f2.call(this._parent._children[this._pidx - 1]);
			}
		}

		return 0;
	}

});

/*var et2_dataview_IIndexOperations = new Interface({
	getIdxNode: function(_idx),
	removeIdxNode: function(_idx),
	insertNodes: function(_idx, _nodes)
});*/

/**
 * An partition tree organization node can contain child nodes and organizes
 * those.
 */
var et2_dataview_partitionOrganizationNode = et2_dataview_partitionNode.extend(
	/*et2_dataview_IIndexOperations, */{

	init: function(_parent, _pidx) {

		if (typeof _parent == "undefined")
		{
			_parent = null;
		}

		if (typeof _pidx == "undefined")
		{
			_pidx = 0;
		}

		// Call the parent constructor
		this._super();

		this._children =  [];

		// Set the given parent and parent-index
		this.setParent(_parent);
		this.setPIdx(_pidx);

	},

	destroy: function() {
		// Free all child nodes
		for (var i = this._children.length - 1; i >= 0; i++)
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
		this._iterateToIndx(_idx, function(ei, bi, child) {
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
		this._iterateToIndx(_idx, function(ei, bi, child) {
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
	 * Reduces the given range to a placeholder
	 */
	reduceRange: function(_range) {
		this._reduce(this.getRangeNodes(_range, false))
	},

	/**
	 * Reduces the given index range to a placeholder
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

	_iterateToIndx: function(_idx, _func, _res) {
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
	 * Reduces the given nodes to a single placeholder
	 */
	_reduce: function(_nodes) {
		if (_nodes.length == 0)
		{
			return;
		}

		// Check whether the first or last node is a placeholder, if not create
		// a new one
		var ph;
		if (_nodes[0] instanceof et2_dataview_partitionPlaceholderNode)
		{
			ph = _nodes[0]
		}
		else if (_nodes[_nodes.length - 1] instanceof et2_dataview_partitionPlaceholderNode)
		{
			ph = _nodes[_nodes.length - 1];
		}
		else
		{
			// Create a new placeholder node an insert it at the place of the
			// first node of the range
			ph = new et2_dataview_partitionPlaceholderNode();
			this.getRoot().insertNodes(_nodes[0].getStartIndex(), [ph]);
		}

		// Get the height of the resulting placeholder
		var height = _nodes[_nodes.length - 1].getBottom() - _nodes[0].getTop();

		// Get the count of actual elements in the nodes
		var count = 0;
		for (var i = 0; i < _nodes.length; i++)
		{
			count += _nodes[i].getCount();
		}

		// Update the placeholder parameters
		ph.setAvgHeight(height / count);
		ph.setCount(count);

		// Free all elements (except for the placeholder)
		for (var i = _nodes.length - 1; i >= 0; i--)
		{
			if (_nodes[i] != ph)
			{
				_nodes[i].free();
			}
		}
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

/**
 * Node which represents a placeholder. Complete parts of the tree can be
 * transformed into placeholder nodes.
 */
var et2_dataview_partitionPlaceholderNode = et2_dataview_partitionNode.extend({

	init: function(_count, _avgHeight) {

		// Call the inherited constructor
		this._super();

		this._count = _count;
		this._avgHeight = _avgHeight;
	},

	getCount: function() {
		return this._count;
	},

	setCount: function(_count) {
		if (_count != this._count)
		{
			this._count = _count;
			this.invalidate();
		}
	},

	setAvgHeight: function(_height) {
		if (_height != this._avgHeight)
		{
			this._avgHeight = _height;
			this.invalidate();
		}
	},

	calculateHeight: function() {
		return this._count * this._avgHeight;
	},

	/**
	 * Creates the nodes which fall in the given range and returns them
	 */
	getRangeNodes: function(_range) {
		var insertNodes = [];

		// Copy parent and pidx as we'll have to access those objects after this
		// one gets freed
		var parent = this._parent;
		var pidx = this._pidx;

		// Get the top and bottom of this node
		var t = this.getPosTop();
		var b = this.getPosBottom();

		// Get the start and stop index of the elements which have to be
		// created.
		var ah = this._avgHeight;
		var startIdx = Math.max(0, Math.floor((_range.top - t) / ah));
		var stopIdx = Math.min(this._count - 1, Math.ceil((_range.bottom - t) / ah));

		if (startIdx > 0 && startIdx < this._count)
		{
			// Create a placeholder which contains the elements until startIdx
			insertNodes.push(new et2_dataview_partitionPlaceholderNode(startIdx, ah));
		}

		// Create the elements from start to stop index
		for (var i = startIdx; i < stopIdx; i++)
		{
			var rowNode = new et2_dataview_partitionRowNode(ah);
			insertNodes.push(rowNode);
		}

		if (stopIdx < this._count - 1 && stopIdx > 0)
		{
			// Create a placeholder which contains the elements starting from
			// stop index
			var l = this._count - stopIdx;
			insertNodes.push(new et2_dataview_partitionPlaceholderNode(l, ah));
		}

		// Check whether insertNodes really has entrys - this is not the case
		// if the given range is just outside the range of this element
		if (insertNodes.length > 0)
		{
			// Free this element
			this.free();

			// Insert the newly created nodes at the original place of this node
			parent.insertNodes(pidx, insertNodes);

			return false;
		}

		return [];
	},

	getAvgHeightData: function(_data) {
		// Do nothing here, as the placeholders should not be inside the average
		// height statistic.
	},

});

/**
 * Main class for the usage of the partition tree
 */
var et2_dataview_partitionTree = et2_dataview_partitionOrganizationNode.extend({

	init: function(_count, _avgHeight) {
		this._super();

		// Append a placeholder node to the children
		var ph = new et2_dataview_partitionPlaceholderNode(_count, _avgHeight);
		ph.setParent(this);

		this._children = [ph];
	}

});

var et2_dataview_partitionRowNode = et2_dataview_partitionNode.extend({

	init: function(_avgHeight) {
		this._avgHeight = _avgHeight;
	},

	calculateHeight: function() {
		return this._avgHeight;
	}

});

/*
var tree = new et2_dataview_partitionTree(1000, 20);
tree.getRangeNodes(et2_range(0, 100));
//tree.getRangeNodes(et2_range(0, 1000));
*/

