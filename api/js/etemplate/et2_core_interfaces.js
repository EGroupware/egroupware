/**
 * EGroupware eTemplate2 - File which contains all interfaces
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Andreas Stöckel
 */
export var et2_implements_registry = {};
/**
 * Checks if an object / et2_widget implements given methods
 *
 * @param obj
 * @param methods
 */
export function implements_methods(obj, methods) {
    for (let i = 0; i < methods.length; ++i) {
        if (typeof obj[methods[i]] !== 'function') {
            return false;
        }
    }
    return true;
}
export const et2_IDOMNode = "et2_IDOMNode";
et2_implements_registry.et2_IDOMNode = function (obj) {
    return implements_methods(obj, ["getDOMNode"]);
};
export const et2_IInputNode = "et2_IInputNode";
et2_implements_registry.et2_IInputNode = function (obj) {
    return implements_methods(obj, ["getInputNode"]);
};
export const et2_IInput = "et2_IInput";
et2_implements_registry.et2_IInput = function (obj) {
    return implements_methods(obj, ["getValue", "isDirty", "resetDirty", "isValid"]);
};
export const et2_IResizeable = "et2_IResizeable";
et2_implements_registry.et2_IResizeable = function (obj) {
    return implements_methods(obj, ["resize"]);
};
export const et2_IAligned = "et2_IAligned";
et2_implements_registry.et2_IAligned = function (obj) {
    return implements_methods(obj, ["get_align"]);
};
export const et2_ISubmitListener = "et2_ISubmitListener";
et2_implements_registry.et2_ISubmitListener = function (obj) {
    return implements_methods(obj, ["submit"]);
};
export const et2_IDetachedDOM = "et2_IDetachedDOM";
et2_implements_registry.et2_IDetachedDOM = function (obj) {
    return implements_methods(obj, ["getDetachedAttributes", "getDetachedNodes", "setDetachedAttributes"]);
};
export const et2_IPrint = "et2_IPrint";
et2_implements_registry.et2_IPrint = function (obj) {
    return implements_methods(obj, ["beforePrint", "afterPrint"]);
};
export const et2_IExposable = "et2_IExposable";
et2_implements_registry.et2_IExposable = function (obj) {
    return implements_methods(obj, ["getMedia"]);
};
//# sourceMappingURL=et2_core_interfaces.js.map