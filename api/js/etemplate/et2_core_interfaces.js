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
function implements_et2_IDOMNode(obj) {
    return implements_methods(obj, ["getDOMNode"]);
}
function implements_et2_IInput(obj) {
    return implements_methods(obj, ["getValue", "isDirty", "resetDirty", "isValid"]);
}
function implements_et2_IResizeable(obj) {
    return implements_methods(obj, ["resize"]);
}
function implements_et2_IAligned(obj) {
    return implements_methods(obj, ["get_align"]);
}
function implements_et2_ISubmitListener(obj) {
    return implements_methods(obj, ["submit"]);
}
function implements_et2_IDetachedDOM(obj) {
    return implements_methods(obj, ["getDetachedAttributes", "getDetachedNodes", "setDetachedAttributes"]);
}
function implements_et2_IPrint(obj) {
    return implements_methods(obj, ["beforePrint", "afterPrint"]);
}
