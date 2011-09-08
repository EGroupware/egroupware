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

	init: function(_dataProvider, _rowProvider, _invalidationElem, _avgHeight) {

		this._super(_dataProvider, _rowProvider, _invalidationElem);

		this._avgHeight = _avgHeight;
		this._idx = null;

		this.rowWidget = null;
		this.hasAvgHeight = false;

		// Get the default row object and scale the row to the average height
		this.tr = this.rowProvider.getPrototype("dataRow");

		// Append the row
		this.appendNode(this.tr);
	},

	destroy: function() {

		// Unregister the row from the data provider
		if (this._idx !== null)
		{
			this.dataProvider.unregisterDataRow(this._idx);
		}

		// Free the row widget first, if it has been set
		if (this.rowWidget)
		{
			this.rowWidget.free();
		}

		this._super();
	},

	setIdx: function(_idx) {
		this._idx = _idx;

		// Register the row in the data provider
		this.dataProvider.registerDataRow(this, _idx);

		// Set the default height of the rowWidget has not been immediately
		// created
		if (!this.rowWidget)
		{
			$j("td:first", this.tr).height(this._avgHeight);
			this.hasAvgHeight = true;
		}
	},

	updateData: function(_data) {

		// Reset the height
		if (this.hasAvgHeight)
		{
			$j("td:first", this.tr).height("auto");
			this.hasAvgHeight = false;
		}

		// Free the row widget if it already existed
		if (this.rowWidget != null)
		{
			this.rowWidget.free();
		}

		// Create the row widget - it automatically generates the widgets and
		// attaches the given data to them
		this.rowWidget = this.rowProvider.getDataRow(_data, this.tr, this._idx);

		// Invalidate this element
		this.invalidate();
	}

});

