/**
 * eGroupWare eTemplate2 - Class which contains the spacer container
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
	et2_dataview_view_container;
*/

var et2_dataview_spacer = et2_dataview_container.extend({

	init: function(_dataProvider, _rowProvider, _invalidationElem) {

		// Call the inherited container constructor
		this._super(_dataProvider, _rowProvider, _invalidationElem);

		// Get the spacer row and append it to the container
		this.spacerNode = this.rowProvider.getPrototype("spacer",
			this._createSpacerPrototype, this);
		this._phDiv = $j("td", this.spacerNode);
		this.appendNode(this.spacerNode);
	},

	setHeight: function(_height) {
		this._phDiv.height(_height);
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_createSpacerPrototype: function(_outerId, _columnIds) {
		var tr = $j(document.createElement("tr"));

		var td = $j(document.createElement("td"))
			.addClass("egwGridView_spacer")
			.addClass(_outerId + "_spacer_fullRow")
			.attr("colspan", _columnIds.length)
			.appendTo(tr);

		return tr;
	}

});

