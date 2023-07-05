import {EgwAction} from "./EgwAction";
import {egwFnct} from "./egw_action_common";

export function egwPopupAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple) {
    var action = new EgwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);
    action.type = "popup";
    action.canHaveChildren = ["popup"];
    action["default"] = false;
    action.order = 0;
    action.group = 0;
    action.hint = false;
    action.checkbox = false;
    action.radioGroup = 0;
    action.checked = false;
    action.confirm_mass_selection = null;
    action.shortcut = null;
    action.singleClick = false;

    action.set_singleClick = function (_value) {
        action["singleClick"] = _value;
    };

    action.set_default = function (_value) {
        action["default"] = _value;
    };

    action.set_order = function (_value) {
        action.order = _value;
    };

    action.set_group = function (_value) {
        action.group = _value;
    };

    action.set_hint = function (_value) {
        action.hint = _value;
    };

    // If true, the action will be rendered as checkbox
    action.set_checkbox = function (_value) {
        action.checkbox = _value;
    };

    action.set_checked = function (_value) {
        action.checked = _value;
    };

    /**
     * Set either a confirmation prompt, or TRUE to indicate that this action
     * cares about large selections and to ask the confirmation prompt(s)
     *
     * @param {String|Boolean} _value
     */
    action.set_confirm_mass_selection = function (_value) {
        action.confirm_mass_selection = _value;
    };

    // Allow checkbox to be set from context using the given function
    action.set_isChecked = function (_value) {
        action.isChecked = new egwFnct(this, null, []);
        if (_value !== null) {
            action.isChecked.setValue(_value);
        }
    };

    // If radioGroup is >0 and the element is a checkbox, radioGroup specifies
    // the group of radio buttons this one belongs to
    action.set_radioGroup = function (_value) {
        action.radioGroup = _value;
    };

    action.set_shortcut = function (_value) {
        if (_value) {
            var sc = {
                "keyCode": -1,
                "shift": false,
                "ctrl": false,
                "alt": false
            };

            if (typeof _value == "object" && typeof _value.keyCode != "undefined" &&
                typeof _value.caption != "undefined") {
                sc.keyCode = _value.keyCode;
                sc.caption = _value.caption;
                sc.shift = (typeof _value.shift == "undefined") ? false : _value.shift;
                sc.ctrl = (typeof _value.ctrl == "undefined") ? false : _value.ctrl;
                sc.alt = (typeof _value.alt == "undefined") ? false : _value.alt;
            }

            this.shortcut = sc;
        } else {
            this.shortcut = false;
        }
    };

    return action;
}