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

var et2_dataview_row = et2_dataview_container.extend(et2_dataview_IDataRow, {

	init: function(_dataProvider, _rowProvider, _invalidationElem, _idx) {

		this._super(_dataProvider, _rowProvider, _invalidationElem);

		this.tr = this.rowProvider.getPrototype("default");
		$j("div", this.tr).text("Blub");
		this.appendNode(this.tr);

		// Register this row in the dataprovider - if data is available for this
		// row the "updateData" function will be called immediately.
		//this.dataProvider.registerDataRow(_idx, this);

//		if (this.tr == null)
//		{
//		}
	},

	destroy: function() {
		//this.dataProvider.unregisterDataRow(_idx);
	},

	updateData: function(_data) {
	}

});

