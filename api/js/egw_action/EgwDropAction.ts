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
export class EgwDropAction extends EgwAction{
     acceptedTypes: string[];
     order: number;
     group: number;

    constructor(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple) {

    super(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

    this.type = "drop";
    this.acceptedTypes = ["default"];
    this.canHaveChildren = ["drag", "popup"];
    this["default"] = false;
    this.order = 0;
    this.group = 0;
    }

    set_default(_value) {
        this["default"] = _value;
    };

    set_order(_value) {
        this.order = _value;
    };

    set_group(_value) {
        this.group = _value;
    };

    /**
     * The acceptType property allows strings as well as arrays - strings are
     * automatically included in an array.
     *
     * @param {(string|array)} _value
     */
    set_acceptedTypes(_value) {
        if (_value instanceof Array) {
            this.acceptedTypes = _value;
        } else {
            this.acceptedTypes = [_value];
        }
    };
}