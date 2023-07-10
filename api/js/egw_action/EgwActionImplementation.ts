/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */
import type {EgwActionObjectInterface} from "./EgwActionObjectInterface";

/**
 * Abstract interface for the EgwActionImplementation object. The EgwActionImplementation
 * object is responsible for inserting the actual action representation (context menu,
 * drag-drop code) into the DOM Tree by using the egwActionObjectInterface object
 * supplied by the object.
 * To write a "class" which derives from this object, simply write an own constructor,
 * which replaces "this" with a "new EgwActionImplementation" and implement your
 * code in "doRegisterAction" und "doUnregisterAction".
 * Register your own implementation within the _egwActionClasses object.
 *
 */
export interface EgwActionImplementation {
    /**
     * @param {object} _actionObjectInterface is the AOI in which the implementation
     *    should be registered.
     * @param {function} _triggerCallback is the callback function which will be triggered
     *    when the user triggeres this action implementatino (e.g. starts a drag-drop or
     *    right-clicks on an object.)
     * @param {object} _context in which the triggerCallback should get executed.
     * @returns {boolean} true if the Action had been successfully registered, false if it
     *    had not.
     */
    registerAction: (_actionObjectInterface: EgwActionObjectInterface, _triggerCallback: Function, _context: object) => boolean;
    /**
     * Unregister action will be called before an actionObjectInterface is destroyed,
     * which gives the EgwActionImplementation the opportunity to remove the previously
     * injected code.
     *
     * @param {egwActionObjectInterface} _actionObjectInterface
     * @returns true if the Action had been successfully unregistered, false if it
     *    had not.
     */
    unregisterAction: (_actionObjectInterface: EgwActionObjectInterface) => boolean;
    executeImplementation: (_context: any, _selected: any, _links: any, _target?:any) => any;
    type: string;
}
