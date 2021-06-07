/**
 * EGroupware eTemplate2 - Contains interfaces used inside the dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright EGroupware GmbH 2011-2021
 */

/*egw:uses
	et2_core_inheritance;
*/

import {implements_methods,et2_implements_registry} from "./et2_core_interfaces";
import type {et2_widget} from "./et2_core_widget";

export interface et2_dataview_IInvalidatable
{
	invalidate()
}
export const et2_dataviewIInvalidatable = "et2_dataview_IInvalidatable";
et2_implements_registry.et2_dataview_IInvalidatable = function(obj : et2_widget)
{
	return implements_methods(obj, ["invalidate"]);
}
export interface et2_dataview_IViewRange
{
	setViewRange(_range)
}
export const et2_dataview_IViewRange = "et2_dataview_IViewRange";
et2_implements_registry.et2_dataview_IViewRange = function(obj : et2_widget)
{
	return implements_methods(obj, ["setViewRange"]);
}

/**
 * Interface a data provider has to implement. The data provider functions are
 * called by the et2_dataview_controller class. The data provider basically acts
 * like the egw api egw_data extension, but some etemplate specific stuff has
 * been stripped away -- the implementation (for the nextmatch widget that is
 * et2_extension_nextmatch_dataprovider) has to take care of that.
 */
export interface et2_IDataProvider
{

	/**
	 * This function is used by the et2_dataview_controller to fetch data for
	 * a certain range. The et2_dataview_controller provides data which allows
	 * to only update elements which really have changed.
	 *
	 * @param _queriedRange is an object of the following form:
	 * {
	 *    start: <START INDEX>,
	 *    num_rows: <COUNT OF ENTRIES>
	 * }
	 * @param _knownRange is an array of the above form and informs the
	 * implementation which range is already known to the client. This parameter
	 * may be null in order to indicate that the client currently has no valid
	 * data.
	 * @param _lastModification is the last timestamp that was returned from the
	 * data provider and for which the client has data. It may be null in order
	 * to indicate, that the client currently has no data or needs a complete
	 * refresh.
	 * @param _callback is the function that should get called, once the data
	 * is available. The data passed to the callback function has the
	 * following form:
	 * {
	 *     order: [uid, ...],
	 *     total: <TOTAL COUNT>,
	 *     lastModification: <LAST MODIFICATION TIMESTAMP>
	 * 	}
	 * @param _context is the context in which the callback function will get
	 * 	called.
	 */
	dataFetch (_queriedRange : {start: number, num_rows:number}, _lastModification, _callback : Function, _context : object)

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
	dataRegisterUID (_uid : string, _callback : Function, _context : object)

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
	dataUnregisterUID (_uid : string, _callback : Function, _context : object)

}
export const et2_IDataProvider = "et2_IDataProvider";
et2_implements_registry.et2_IDataProvider = function(obj : et2_widget)
{
	return implements_methods(obj, ["dataFetch", "dataRegisterUID", "dataUnregisterUID"]);
}


