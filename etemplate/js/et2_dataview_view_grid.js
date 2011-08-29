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
	et2_dataview_view_row;
	et2_dataview_view_partitionTree;
*/

var et2_dataview_grid = Class.extend({

	init: function(_dataProvider, _count, _avgHeight) {
		// Create the partition tree object which is used to organize the tree
		// items.
		this._partitionTree = new et2_dataview_partitionTree(_dataProvider, 
			_count, _avgHeight);
	},

	destroy: function() {
		// Free the partition tree
		this._partitionTree.free();
	},

	

});
