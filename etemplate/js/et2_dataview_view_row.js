/**
 * eGroupWare eTemplate2 - dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011-2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	egw_action.egw_action;

	et2_dataview_view_container;
*/

var et2_dataview_row = et2_dataview_container.extend({

	/**
	 * Creates the row container. Use the "setRow" function to load the actual
	 * row content.
	 *
	 * @param _parent is the row parent container.
	 */
	init: function(_parent) {
		// Call the inherited constructor
		this._super(_parent);

		// Create the outer "tr" tag and append it to the container
		this.tr = $j(document.createElement("tr"));
		this.appendNode(this.tr);
	},

	clear: function() {
		this.tr.empty();
	},

	getDOMNode: function() {
		return this.tr[0];
	},

	getJNode: function() {
		return this.tr;
	}

});

