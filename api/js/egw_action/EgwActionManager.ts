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
 * egwActionManager manages a list of actions - it overwrites the egwAction class
 * and allows child actions to be added to it.
 *
 * @param {EgwAction} _parent
 * @param {string} _id
 * @return {EgwActionManager}
 */
export class EgwActionManager extends EgwAction {
    constructor(_parent = null, _id = "") {
        super(_parent, _id);
        this.type = "actionManager";
        this.canHaveChildren = true;
    }
}