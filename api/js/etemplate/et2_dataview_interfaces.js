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
import { implements_methods, et2_implements_registry } from "./et2_core_interfaces";
export const et2_dataviewIInvalidatable = "et2_dataview_IInvalidatable";
et2_implements_registry.et2_dataview_IInvalidatable = function (obj) {
    return implements_methods(obj, ["invalidate"]);
};
export const et2_dataview_IViewRange = "et2_dataview_IViewRange";
et2_implements_registry.et2_dataview_IViewRange = function (obj) {
    return implements_methods(obj, ["setViewRange"]);
};
export const et2_IDataProvider = "et2_IDataProvider";
et2_implements_registry.et2_IDataProvider = function (obj) {
    return implements_methods(obj, ["dataFetch", "dataRegisterUID", "dataUnregisterUID"]);
};
//# sourceMappingURL=et2_dataview_interfaces.js.map