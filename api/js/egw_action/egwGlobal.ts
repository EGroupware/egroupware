/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import type {Iegw} from "../jsapi/egw_global";
import type {nm_action} from "../etemplate/et2_extension_nextmatch_actions";

/**
 * holds the constructor and implementation of an EgwActionClass
 */
export type EgwActionClassData = {
    //type EgwAction["constructor"],
    actionConstructor: Function,
    implementation: any
}

/**
 * holds all possible Types of a egwActionClass
 */
type EgwActionClasses = {
    default: EgwActionClassData,//
    actionManager: EgwActionClassData, drag: EgwActionClassData, drop: EgwActionClassData, popup: EgwActionClassData
}
declare global {
    interface Window {
        _egwActionClasses: EgwActionClasses;
        egw: Iegw //egw returns instance of client side api -- set in egw_core.js
        egwIsMobile: () => boolean // set in egw_action_commons.ts
        nm_action: typeof nm_action
        egw_getAppName: () => string
        Et2Dialog: any
        app: any
    }
}