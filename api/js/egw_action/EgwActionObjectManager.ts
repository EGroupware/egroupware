/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {EGW_AO_FLAG_IS_CONTAINER} from "./egw_action_constants";
import {EgwActionObject} from "./EgwActionObject";
import {egwActionObjectInterface} from "./egw_action";

/**
 * The egwActionObjectManager is a dummy class which only contains a dummy
 * AOI. It may be used as root object or as object containers.
 *
 * @param {string} _id
 * @param {string} _manager
 * @return {EgwActionObjectManager}
 */
export class EgwActionObjectManager extends EgwActionObject {
    constructor(_id: string, _manager: any) {
        const aoi = new egwActionObjectInterface();
        //const ao = new egwActionObject(_id, null, aoi, _manager, EGW_AO_FLAG_IS_CONTAINER)
        super(_id, null, aoi, _manager, EGW_AO_FLAG_IS_CONTAINER);
        this.triggerCallback = function () {
            return false;
        }
    }
}