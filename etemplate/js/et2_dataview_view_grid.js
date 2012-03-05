/**
 * eGroupWare eTemplate2 - Class which contains the "grid" base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_common;

	et2_dataview_interfaces;
	et2_dataview_view_partitionTree;
	et2_dataview_view_row;
	et2_dataview_view_spacer;
	et2_dataview_view_rowProvider;
*/

/**
 * Determines how many pixels the view range of the gridview is extended.
 */
var ET2_GRID_VIEW_EXT = 25;

/**
 * Determines the timeout after which the scroll-event is processed.
 */
var ET2_GRID_SCROLL_TIMEOUT = 25;

var partitionTree = null;

var et2_dataview_grid = Class.extend(et2_dataview_IViewRange, {

	/**
	 * Creates the grid.
	 *
	 * @param _parent is the parent grid class - if null, this means that this
	 * 	is the outer grid which manages the scrollarea. If not null, all other
	 * 	parameters are ignored and copied from the given grid instance.
	 * @param _outerId is the id of the grid container it uses for the css
	 * 	classes.
	 * @param _columnIds is the id of the individual columns used for the css
	 * 	classes.
	 * @param _avgHeight is the starting average height of the column rows.
	 */
	init: function(_parent, _outerId, _columnIds, _dataProvider, _rowProvider, 
		_avgHeight) {

		// If the parent is given, copy all other parameters from it
		if (_parent != null)
		{
			this._outerId = _parent._outerId;
			this._columnIds = _parent._columnIds;
			this._dataProvider = _parent._dataProvider;
			this._avgHeight = _parent._partitionTree.getAverageHeight();
			this._rowProvider = _parent._rowProvider;
		}
		else
		{
			// Otherwise copy the given parameters
			this._outerId = _outerId;
			this._columnIds = _columnIds;
			this._dataProvider = _dataProvider;
			this._rowProvider = _rowProvider;
			this._avgHeight = _avgHeight;

			this._scrollHeight = 0;
			this._scrollTimeout = null;
		}

		// The "treeChanged" variable is called whenever the viewrange has been
		// set - changing the viewrange is the function which causes new elements
		// to be generated and thus the partition tree to degenerate
		this._treeChanged = false;

		// Count of elements which are buffered at top and bottom of the viewrange
		// before they get replaced with placeholders
		this._holdCount = 50;

		// The current range holds the currently visible nodes
		this._currentRange = et2_range(0, 0);

		// Build the grid outer nodes
		this._createNodes();

		// Create the partition tree object which is used to organize the tree
		// items.
		this._partitionTree = new et2_dataview_partitionTree(this._dataProvider, 
			this._rowProvider, this._avgHeight, this.innerTbody);

		// Setup the "rebuild" timer - it rebuilds the complete partition tree
		// if any change has been done to it. Rebuilding the partition tree is
		// necessary as the tree structure happens to degenerate.
		var self = this;
		this._rebuildTimer = window.setInterval(function() {
			self._checkTreeRebuild();
		}, 10 * 1000);
	},

	destroy: function() {
		// Stop the scroll timeout
		if (this._scrollTimeout)
		{
			window.clearTimeout(this._scrollTimeout);
		}

		// Stop the rebuild timer
		window.clearInterval(this._rebuildTimer);

		// Free the partition tree
		this._partitionTree.free();
	},

	clear: function() {
		// Free the partition tree and recreate it
		this._avgHeight = this._partitionTree.getAverageHeight();
		this._partitionTree.free();
		this._partitionTree = new et2_dataview_partitionTree(this._dataProvider, 
			this._rowProvider, this._avgHeight, this.innerTbody);

		// Set the viewrange again
		this.setViewRange(this._currentRange);
	},

	/**
	 * The setViewRange function updates the range in which columns are shown.
	 */
	setViewRange: function(_range) {
		this._treeChanged = true;

		// Copy the given range
		this._currentRange = et2_bounds(_range.top, _range.bottom);

		// Display all elements in the given range
		var nodes = this._partitionTree.getRangeNodes(_range);

		for (var i = 0; i < nodes.length; i++)
		{
			if (nodes[i].implements(et2_dataview_IViewRange))
			{
				nodes[i].setViewRange(_range);
			}
		}

		// Calculate the range of the actually shown elements
		var displayTop = _range.top;
		var displayBottom = _range.bottom;

		if (nodes.length > 0)
		{
			displayTop = nodes[0].getPosTop();
			displayBottom = nodes[nodes.length - 1].getPosBottom();
		}

		// Hide everything except for _holdCount elements at the top and bottom
		// of the viewrange
		var ah = this._partitionTree.getAverageHeight();
		var reduceHeight = ah * this._holdCount;

		if (displayTop > reduceHeight)
		{
			this._partitionTree.reduceRange(et2_bounds(0, displayTop - reduceHeight));
		}

		if (displayBottom + reduceHeight < this._partitionTree.getHeight())
		{
			this._partitionTree.reduceRange(et2_bounds(displayBottom + reduceHeight,
				this._partitionTree.getHeight()));
		}
	},

	/**
	 * Updates the scrollheight
	 */
	setScrollHeight: function(_height) {
		this._height = _height;

		// Update the height of the outer container
		if (this.scrollarea)
		{
			this.scrollarea.height(_height);
		}

		// Update the viewing range
		this.setViewRange(et2_range(this._currentRange.top, this._height));
	},

	/**
	 * Returns the JQuery outer DOM-Node
	 */
	getJNode: function() {
		return this.outerCell;
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	/**
	 * Checks whether the partition tree has to be rebuilt and if yes, does
	 * that.
	 */
	_checkTreeRebuild: function() {
		if (this._treeChanged)
		{
			var depth = this._partitionTree.getDepth();
			var count = this._partitionTree.getManagedCount();

			// Check whether the depth of the tree is very unproportional
			// regarding to the count of elements managed in it
			if (count < Math.pow(ET2_PARTITION_TREE_WIDTH, depth - 1))
			{
				egw.debug("info", "Rebuilding dataview partition tree");
				this._partitionTree.rebuild();
				egw.debug("info", "Done.");
			}

			// Reset the "treeChanged" function.
			this._treeChanged = false;
		}
	},

	/**
	 * Creates the grid DOM-Nodes
	 */
	_createNodes: function() {
		this.outerCell = $j(document.createElement("td"))
			.addClass("frame")
			.attr("colspan", this._columnIds.length + (this._parent ? 0 : 1));

		// Create the scrollarea div if this is the outer grid
		this.scrollarea = null;
		if (this._parent == null)
		{
			this.scrollarea = $j(document.createElement("div"))
				.addClass("egwGridView_scrollarea")
				.scroll(this, function(e) {

						// Clear any older scroll timeout
						if (e.data._scrollTimeout)
						{
							window.clearTimeout(e.data._scrollTimeout);
						}

						// Set a new timeout which calls the setViewArea
						// function
						e.data._scrollTimeout = window.setTimeout(function() {
							if (typeof ET2_GRID_PAUSE != "undefined")
								return;

							e.data.setViewRange(et2_range(
								e.data.scrollarea.scrollTop() - ET2_GRID_VIEW_EXT,
								e.data._height + ET2_GRID_VIEW_EXT * 2
							));
						}, ET2_GRID_SCROLL_TIMEOUT);
					})
				.height(this._scrollHeight)
				.appendTo(this.outerCell);
		}

		// Create the inner table
		var table = $j(document.createElement("table"))
			.addClass("egwGridView_grid")
			.appendTo(this.scrollarea ? this.scrollarea : this.outerCell);

		this.innerTbody = $j(document.createElement("tbody"))
			.appendTo(table);
	}

});

