/**
 * EGroupware eTemplate2 - Class which contains the spacer container
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

/**
 * @augments et2_dataview_container
 */
var et2_dataview_spacer = et2_dataview_container.extend(
{
	/**
	 * Constructor
	 * 
	 * @param _parent
	 * @param _rowProvider
	 * @memberOf et2_dataview_spacer
	 */
	init: function (_parent, _rowProvider) {
		// Call the inherited container constructor
		this._super(_parent);

		// Initialize the row count and the row height
		this._count = 0;
		this._rowHeight = 19;
		this._avgSum = 0;
		this._avgCount = 0;

		// Get the spacer row and append it to the container
		this.spacerNode = _rowProvider.getPrototype("spacer",
			this._createSpacerPrototype, this);
		this._phDiv = $j("td", this.spacerNode);
		this.appendNode(this.spacerNode);
	},

	setCount: function (_count, _rowHeight) {
		// Set the new count and _rowHeight if given
		this._count = _count;
		if (typeof _rowHeight !== "undefined")
		{
			this._rowHeight = _rowHeight;
		}

		// Update the element height
		this._phDiv.height(this._count * this._rowHeight);

		// Call the invalidate function
		this.invalidate();
	},

	getCount: function () {
		return this._count;
	},

	getHeight: function () {
		// Set the calculated height, so that "invalidate" will work correctly
		this._height = this._count * this._rowHeight;

		return this._height;
	},

	getAvgHeightData: function () {
		if (this._avgCount > 0)
		{
			return {
				"avgHeight": this._avgSum / this._avgCount,
				"avgCount": this._avgCount
			};
		}

		return null;
	},

	addAvgHeight: function (_height) {
		this._avgSum += _height;
		this._avgCount++;
	},

	/* ---- PRIVATE FUNCTIONS ---- */

	_createSpacerPrototype: function (_outerId, _columnIds) {
		var tr = $j(document.createElement("tr"));

		var td = $j(document.createElement("td"))
			.addClass("egwGridView_spacer")
			.addClass(_outerId + "_spacer_fullRow")
			.attr("colspan", _columnIds.length)
			.appendTo(tr);

		return tr;
	}

});

