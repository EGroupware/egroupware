/**
 * eGroupWare eTemplate2 - Class which contains a factory method for rows
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
	et2_dataview_interfaces;
*/

var et2_dataview_row = et2_dataview_container.extend({

	init: function(_dataProvider, _rowProvider, _idx) {
		this._dataProvider = _dataProvider;
		this._rowProvider = _rowProvider;
		this._idx = _idx;
		this._node = null;
		this._rowImpl = null;

		// Register this row in the dataprovider - if data is available for this
		// row the "updateData" function will be called immediately.
		this._dataProvider.registerDataRow(_idx, this);

		if (this._node == null)
		{
		}
	},

	destroy: function() {
		this._dataProvider.unregisterDataRow(_idx);
	},

	updateData: function(_data) {
	}

});
