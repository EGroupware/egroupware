/**
 * eGroupWare eTemplate2 - Contains the dataview base object.
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
	et2_core_inheritance;
*/

var et2_dataview_IInvalidatable = new Interface({

	invalidate: function() {}

});

var et2_dataview_IDataRow = new Interface({

	updateData: function(_data) {}

});

var et2_dataview_IViewRange = new Interface({

	setViewRange: function(_range) {}

});

/**
 * Interface which objects have to implement, that want to act as low level
 * datasource.
 */
var et2_IRowFetcher = new Interface({

	/**
	 * @param _fetchList is an array consisting of objects whith the entries
	 * 	"startIdx" and "count"
	 * @param _callback is the callback which is called when the data is ready
	 * 	(may be immediately or deferred). The callback has the following
	 * 	signature:
	 * 		function (_rows)
	 * 	where _rows is an associative array which contains the data for that row.
	 * @param _context is the context in which the callback should run.
	 */
	getRows: function(_fetchList, _callback, _context) {}

});

/**
 * Interface the data provider has to implement
 */
var et2_IDataProvider = new Interface({

	/**
	 * Returns the total count of grid elements
	 */
	getCount: function() {},

	/**
	 * Registers the given dataRow for the given index. Calls _dataRow.updateData
	 * as soon as data is available for that row.
	 */
	registerDataRow: function(_dataRow, _idx) {},

	/**
	 * Stops calling _dataRow.updateData for the dataRow registered for the given
	 * index.
	 */
	unregisterDataRow: function(_idx) {}

});


