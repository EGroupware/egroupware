import type {EgwActionObjectInterface} from "./egw_action";

/**
 * Abstract interface for the EgwActionImplementation object. The EgwActionImplementation
 * object is responsible for inserting the actual action representation (context menu,
 * drag-drop code) into the DOM Tree by using the egwActionObjectInterface object
 * supplied by the object.
 * To write a "class" which derives from this object, simply write a own constructor,
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
    executeImplementation: (_context: any, _selected: any, _links: any) => any;
    type: string;
}
