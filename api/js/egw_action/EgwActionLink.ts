/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */


import {EgwActionManager} from "./EgwActionManager";
import {egwActionStoreJSON} from "./egw_action_common";

/**
 * The egwActionLink is used to interconnect egwActionObjects and egwActions.
 * This gives each action object the possibility to decide, whether the action
 * should be active in this context or not.
 *
 * @param _manager is a reference to the egwActionManager which contains the action
 *    the object wants to link to.
 */
export class EgwActionLink {
    enabled = true;
    visible = true;
    actionId = "";
    actionObj = null;
    manager:EgwActionManager;

    constructor(_manager) {
        this.manager = _manager;
    }
    updateLink(_data)
    {
        egwActionStoreJSON(_data, this, true);
    }
    set_enabled(_value) {
        this.enabled = _value;
    };

    set_visible(_value) {
        this.visible = _value;
    };
    set_actionId(_value)
    {
        this.actionId = _value;
        this.actionObj = this.manager.getActionById(_value);

        if (!this.actionObj)
            throw "Action object with id '"+_value+"' does not exist!";
    };
}