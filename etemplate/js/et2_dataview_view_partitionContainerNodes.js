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
	et2_dataview_view_partitionNode;
*/

/**
 * The partition container node base class implements most functionality for
 * nodes which have a container.
 */
var et2_dataview_partitionContainerNode = et2_dataview_partitionNode.extend({

	/**
	 * Copies the given container object and inserts the container into the tree
	 * - if it is not already in there.
	 */
	init: function(_root, _container, _args) {
		this._super(_root);

		// Copy the container parameter and set its "invalidationElement" to
		// this node
		this.container = _container;
		this.container.setInvalidationElement(this);
	},

	/**
	 * Inserts the container into the tree if it is not already inserted.
	 */
	initializeContainer: function() {
		// Obtain the node index and insert the container nodes at the node
		// returned by getNodeAt. If idx is zero, the returned node will be
		// the outer container, so the container nodes have to be prepended
		// to it.
		var idx = this.getStartIndex();
		this.container.insertIntoTree(this.getRoot().getNodeAt(idx - 1),
			idx == 0);

		this.invalidate();
	},

	/**
	 * Destroys the container if it is still set - e.g. the spacer node
	 * sets the container to null before "free" ist called in some cases, in
	 * order to pass the container object to another spacer node.
	 */
	destroy: function() {
		if (this.container)
		{
			this.container.free();
		}

		this._super();
	},

	/**
	 * Returns the height of the container
	 */
	calculateHeight: function() {
		return this.container.getHeight();
	},

	/**
	 * Calls the "insertNodeAfter" function of the container to insert the node.
	 */
	insertNodeAfter: function(_node) {
		this.container.insertNodeAfter(_node);
	},

	/**
	 * Returns the "lastNode" of the container
	 */
	getNodeAt: function(_idx) {
		if (_idx >= this.getStartIndex() && _idx < this.getStopIndex())
		{
			return this.container.getLastNode();
		}

		return null;
	}

});

/**
 * Node which represents a spacer. Complete parts of the tree can be
 * transformed into spacer nodes.
 */
var et2_dataview_partitionSpacerNode = et2_dataview_partitionContainerNode.extend({

	init: function(_root, _count, _avgHeight, _container) {

		// Create the container if it has not been passed as a third parameter
		var container;
		if (typeof _container != "undefined")
		{
			container = _container;
		}
		else
		{
			container = new et2_dataview_spacer(_root.getDataProvider(),
				_root.getRowProvider(), this);
		}

		// Call the inherited constructor
		this._super(_root, container);

		// Copy the count and average height parameters - this updates the height
		// of the outer container
		this.setParameters(_count, _avgHeight);
	},

	getCount: function() {
		return this._count;
	},

	getAvgHeight: function() {
		return this._avgHeight;
	},

	setParameters: function(_count, _avgHeight) {
		if (_count != this._count || _avgHeight != this._avgHeight)
		{

			// Copy the given parameters
			this._count = _count;
			this._avgHeight = _avgHeight;

			// Invalidate this element
			this.invalidate();

			// Call the container function to set the total height
			this.container.setHeight(this._count * this._avgHeight);
		}
	},

	/**
	 * Creates the nodes which fall in the given range and returns them
	 */
	getRangeNodes: function(_range, _create) {

		// If no new nodes should be created, simply return this node
		if (!_create)
		{
			return this._super(_range);
		}

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
		var stopIdx = Math.min(this._count, Math.ceil((_range.bottom - t) / ah));

		if (startIdx > 0 && startIdx < this._count)
		{
			// Create a spacer which contains the elements until startIdx
			insertNodes.push(new et2_dataview_partitionSpacerNode(this.getRoot(),
				startIdx, ah, this.container));
			this.container = null;
		}

		// Calculate the current average height
		ah = this.getRoot().getAverageHeight();

		// Create the elements from start to stop index
		for (var i = startIdx; i < stopIdx; i++)
		{
			var rowNode = new et2_dataview_partitionRowNode(this.getRoot(), ah);
			insertNodes.push(rowNode);
		}

		if (stopIdx < this._count - 1 && stopIdx > 0)
		{
			// Create a spacer which contains the elements starting from
			// stop index
			var l = this._count - stopIdx;
			insertNodes.push(new et2_dataview_partitionSpacerNode(this.getRoot(),
				l, ah));
		}

		// Check whether insertNodes really has entrys - this is not the case
		// if the given range is just outside the range of this element
		if (insertNodes.length > 0)
		{
			// Free this element
			this.free();

			// Insert the newly created nodes at the original place of this node
			parent.insertNodes(pidx, insertNodes);

			// Insert the newly created elements into the DOM-Tree
			for (var i = 0; i < insertNodes.length; i++)
			{
				insertNodes[i].initializeContainer();
			}

			return false;
		}

		return [];
	},

	getAvgHeightData: function(_data) {
		// Do nothing here, as the spacers should not be inside the average
		// height statistic.
	}

});

var et2_dataview_partitionRowNode = et2_dataview_partitionContainerNode.extend({

	init: function(_root, _avgHeight) {

		var container = new et2_dataview_row(_root.getDataProvider(),
			_root.getRowProvider(), this);

		this._super(_root, container);

		this._avgHeight = _avgHeight;
	},

	initializeContainer: function() {
		this._super();

		this.container.setIdx(this.getStartIndex());
	},

	getIdxNode: function(_node, _create) {
		return this.node;
	}

});


