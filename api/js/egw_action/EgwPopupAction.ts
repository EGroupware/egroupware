/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {EgwAction} from "./EgwAction";
import {EgwFnct} from "./egw_action_common";

export class EgwPopupAction extends EgwAction {
    default = false;
    order = 0;
    group = 0;
    hint = false;
    checkbox = false;
    radioGroup = 0;
    checked = false;
    confirm_mass_selection = null;
    shortcut = null;
    singleClick = false;
    private isChecked: EgwFnct;

    constructor(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple) {
        super(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
        //var action = new EgwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);
        this.type = "popup";
        this.canHaveChildren = ["popup"];

    }

    set_singleClick (_value) {
        this.singleClick = _value;
    };

    set_default (_value) {
        this.default = _value;
    };

    set_order (_value) {
        this.order = _value;
    };

    set_group (_value) {
        this.group = _value;
    };

    set_hint (_value) {
        this.hint = _value;
    };

    // If true, the action will be rendered as checkbox
    set_checkbox (_value) {
        this.checkbox = _value;
    };

    set_checked (_value) {
        this.checked = _value;
    };

    /**
     * Set either a confirmation prompt, or TRUE to indicate that this action
     * cares about large selections and to ask the confirmation prompt(s)
     *
     * @param {String|Boolean} _value
     */
    set_confirm_mass_selection (_value) {
        this.confirm_mass_selection = _value;
    };

    // Allow checkbox to be set from context using the given function
    set_isChecked (_value) {
        this.isChecked = new EgwFnct(this, null, []);
        if (_value !== null) {
            this.isChecked.setValue(_value);
        }
    };

    // If radioGroup is >0 and the element is a checkbox, radioGroup specifies
    // the group of radio buttons this one belongs to
    set_radioGroup (_value) {
        this.radioGroup = _value;
    };

    set_shortcut (_value) {
        if (_value) {
            const sc = {
                "keyCode": -1,
                "shift": false,
                "ctrl": false,
                "alt": false,
                "caption":""
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

}

/**
 * @deprecated
 * use uppercase class
 */
export class egwPopupAction extends EgwPopupAction {
}