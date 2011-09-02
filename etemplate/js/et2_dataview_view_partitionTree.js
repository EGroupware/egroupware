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

	et2_dataview_view_partitionOrganizationNode;
	et2_dataview_view_partitionContainerNodes;
*/

/**
 * Main class for the usage of the partition tree
 */
var et2_dataview_partitionTree = et2_dataview_partitionOrganizationNode.extend({

	init: function(_dataProvider, _rowProvider, _avgHeight, _node) {
		this._super(this);

		this._avgHeight = _avgHeight;
		this._node = _node;
		this._count = _dataProvider.getCount();

		this._dataProvider = _dataProvider;
		this._rowProvider = _rowProvider;

		et2_debug("Creating partition tree with ", this._count,
			" elements of avgHeight ", this._avgHeight);

		// Append a spacer node to the children
		var ph = new et2_dataview_partitionSpacerNode(this, this._count,
			this._avgHeight);
		ph.setParent(this);
		ph.initializeContainer();

		this._children = [ph];
	},

	getAverageHeight: function() {
		var data = {"count": 0, "height": 0};

		this.getAvgHeightData(data);

		if (data.count == 0)
		{
			return this._avgHeight;
		}

		return data.height / data.count;
	},

	/**
	 * Returns the actual count of managed objects inside of the tree - getCount
	 * in contrast returns the count of "virtual" objects including the
	 * spacers.
	 */
	getManagedCount: function() {
		var data = {"count": 0, "height": 0};
		this.getAvgHeightData(data);

		return data.count;
	},

	/**
	 * Returns the node after which new nodes have to be inserted for the given
	 * index.
	 */
	getNodeAt: function(_idx) {

		// Insert the given node to the top of the parent container
		if (_idx < 0)
		{
			return this._node;
		}

		// Otherwise search for the tree node with that index
		return this._super(_idx);
	},

	getRowProvider: function() {
		return this._rowProvider;
	},

	getDataProvider: function() {
		return this._dataProvider;
	}

});


