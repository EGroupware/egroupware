"use strict";
/**
 * EGroupware eTemplate2 - Contains interfaces used inside the dataview
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage dataview
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
 * @version $Id$
 */
Object.defineProperty(exports, "__esModule", { value: true });
var et2_dataviewIInvalidatable = "et2_dataview_IInvalidatable";
function implements_et2_dataview_IInvalidatable(obj) {
    return implements_methods(obj, ["invalidate"]);
}
var et2_dataview_IViewRange = "et2_dataview_IViewRange";
function implements_et2_dataview_IViewRange(obj) {
    return implements_methods(obj, ["setViewRange"]);
}
var et2_IDataProvider = "et2_IDataProvider";
function implements_et2_IDataProvider(obj) {
    return implements_methods(obj, ["dataFetch", "dataRegisterUID", "dataUnregisterUID"]);
}
//# sourceMappingURL=et2_dataview_interfaces.js.map