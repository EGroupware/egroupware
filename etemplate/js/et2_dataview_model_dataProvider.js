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

	getCount: function() {
		return 10;
	},

	registerDataRow: function(_idx, _dataRow) {
		var row = {
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
			Math.round(rnd / 2));
	},

	unregisterDataRow: function(_dataRow) {
		//
	}

});


