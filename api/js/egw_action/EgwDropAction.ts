import {EgwAction} from "./EgwAction";

/**
 * The egwDropAction class overwrites the egwAction class and adds the "acceptedTypes"
 * property. This array should contain all "dragTypes" the drop action is allowed to
 *
 * @param {EgwAction} _id
 * @param {string} _handler
 * @param {string} _caption
 * @param {string} _icon
 * @param {(string|function)} _onExecute
 * @param {bool} _allowOnMultiple
 * @returns {egwDropAction}
 */
export class EgwDropAction {

    constructor(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple) {

    const action = new EgwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

    action.type = "drop";
    action.acceptedTypes = ["default"];
    action.canHaveChildren = ["drag", "popup"];
    action["default"] = false;
    action.order = 0;
    action.group = 0;

    action.set_default = function (_value) {
        action["default"] = _value;
    };

    action.set_order = function (_value) {
        action.order = _value;
    };

    action.set_group = function (_value) {
        action.group = _value;
    };

    /**
     * The acceptType property allows strings as well as arrays - strings are
     * automatically included in an array.
     *
     * @param {(string|array)} _value
     */
    action.set_acceptedTypes = function (_value) {
        if (_value instanceof Array) {
            action.acceptedTypes = _value;
        } else {
            action.acceptedTypes = [_value];
        }
    };

    return action;}
}