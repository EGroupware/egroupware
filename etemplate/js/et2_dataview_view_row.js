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

		this.tr = this.rowProvider.getPrototype("default");
		this.appendNode(this.tr);
	},

	setIdx: function(_idx) {
		this._idx = _idx;

		$j("div:first", this.tr)
			.text(_idx + ":")
			.height((_idx % 10) * 10 + 20);
	},

	updateData: function(_data) {
	}

});

