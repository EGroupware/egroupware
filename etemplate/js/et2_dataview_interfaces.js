/**
 * eGroupWare eTemplate2 - Contains interfaces used inside the dataview
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

var et2_dataview_IViewRange = new Interface({

	setViewRange: function(_range) {}

});

/**
 * Interface a data provider has to implement. The data provider functions are
 * called by the et2_dataview_controller class. The data provider basically acts
 * like the egw api egw_data extension, but some etemplate specific stuff has
 * been stripped away -- the implementation (for the nextmatch widget that is
 * et2_extension_nextmatch_dataprovider) has to take care of that.
 */
var et2_IDataProvider = new Interface({

	/**
	 * This function is used by the et2_dataview_controller to fetch data for
	 * a certain range. The et2_dataview_controller provides data which allows
	 * to only update elements which really have changed.
	 *
	 * @param queriedRange is an object of the following form:
	 * {
	 *    start: <START INDEX>,
	 *    num_rows: <COUNT OF ENTRIES>
	 * }
	 * @param knownRange is an array of the above form and informs the
	 * implementation which range is already known to the client. This parameter
	 * may be null in order to indicate that the client currently has no valid
	 * data.
	 * @param lastModification is the last timestamp that was returned from the
	 * data provider and for which the client has data. It may be null in order
	 * to indicate, that the client currently has no data or needs a complete
	 * refresh.
	 * @param callback is the function that should get called, once the data
	 * is available. The data passed to the callback function has the
	 * following form:
	 * {
	 *     order: [uid, ...],
	 *     total: <TOTAL COUNT>,
	 *     lastModification: <LAST MODIFICATION TIMESTAMP>
	 * 	}
	 * @param context is the context in which the callback function will get
	 * 	called.
	 */
	dataFetch: function (_queriedRange, _lastModification, _callback, _context) {},

	/**
	 * Registers the intrest in a certain uid for a callback function. If
	 * the data for that uid changes or gets loaded, the given callback
	 * function is called. If the data for the given uid is available at the
	 * time of registering the callback, the callback is called immediately.
	 *
	 * @param _uid is the uid for which the callback should be registered.
	 * @param _callback is the callback which should get called.
	 * @param _context is an optional parameter which can 
	 */
	dataRegisterUID: function (_uid, _callback, _context) {},

	/**
	 * Unregisters the intrest of updates for a certain data uid.
	 *
	 * @param _uid is the data uid for which the callbacks should be
	 * 	unregistered.
	 * @param _callback specifies the specific callback that should be
	 * 	unregistered. If it evaluates to false, all callbacks (or those
	 * 	matching the optionally given context) are removed.
	 * @param _context specifies the callback context that should be
	 * 	unregistered. If it evaluates to false, all callbacks (or those
	 * 	matching the optionally given callback function) are removed.
	 */
	dataUnregisterUID: function (_uid, _callback, _context) {}

});


