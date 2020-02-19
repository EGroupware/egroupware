/**
 * EGroupware eTemplate2 - File which contains all interfaces
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 */
/**
 * Checks if an object / et2_widget implements given methods
 *
 * @param obj
 * @param methods
 */
function implements_methods(obj, methods) {
    for (var i = 0; i < methods.length; ++i) {
        if (typeof obj[methods[i]] !== 'function') {
            return false;
        }
    }
    return true;
}
var et2_IDOMNode = "et2_IDOMNode";
function implements_et2_IDOMNode(obj) {
    return implements_methods(obj, ["getDOMNode"]);
}
var et2_IInput = "et2_IInput";
function implements_et2_IInput(obj) {
    return implements_methods(obj, ["getValue", "isDirty", "resetDirty", "isValid"]);
}
var et2_IResizeable = "et2_IResizeable";
function implements_et2_IResizeable(obj) {
    return implements_methods(obj, ["resize"]);
}
var et2_IAligned = "et2_IAligned";
function implements_et2_IAligned(obj) {
    return implements_methods(obj, ["get_align"]);
}
var et2_ISubmitListener = "et2_ISubmitListener";
function implements_et2_ISubmitListener(obj) {
    return implements_methods(obj, ["submit"]);
}
var et2_IDetachedDOM = "et2_IDetachedDOM";
function implements_et2_IDetachedDOM(obj) {
    return implements_methods(obj, ["getDetachedAttributes", "getDetachedNodes", "setDetachedAttributes"]);
}
var et2_IPrint = "et2_IPrint";
function implements_et2_IPrint(obj) {
    return implements_methods(obj, ["beforePrint", "afterPrint"]);
}
var et2_IExposable = "et2_IExposable";
function implements_et2_IExposable(obj) {
    return implements_methods(obj, ["getMedia"]);
}
//# sourceMappingURL=et2_core_interfaces.js.map