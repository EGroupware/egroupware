/**
 * eGroupWare eTemplate2 - Class which contains a the data model
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
	et2_inheritance;
	et2_dataview_interfaces;
*/

var et2_dataview_dataProvider = Class.extend({

	init: function() {
		this.updateQueue = 0;
	},

	getCount: function() {
		return 10000;
	},

	registerDataRow: function(_dataRow, _idx) {
/*		var row = {
			"type": "dataRow",
			"data": {
				"ts_title": "Row " + _idx
			}
		};

		// Get a random value which is used to simulate network latency and time
		// it needs to load the data.
		var rnd = Math.round(Math.random() * 1000);

		if (rnd < 200)
		{
			_dataRow.updateData(row);
		}

		window.setTimeout(function() {_dataRow.updateData(row); },
			Math.round(rnd / 2));*/


		// All data rows are updated independently of all others - this allows
		// user input between generation of the widgets.
		window.setTimeout(function() {_dataRow.updateData({"readonlys": {"__ALL__": true}});}, 0);
	},

	unregisterDataRow: function(_dataRow) {
		//
	}

});


