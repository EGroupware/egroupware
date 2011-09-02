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

	init: function(_root) {

		this._root = _root;
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
		return this._root;
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
		_data.count++;
		_data.height += this.getHeight();
	},

	getNodeIdx: function(_idx, _create) {
		return null;
	},

	getRowProvider: function() {
		return this.getRoot().getRowProvider();
	},

	getDataProvider: function() {
		return this.getRoot().getDataProvider();
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

