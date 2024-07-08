/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {EGW_AO_STATE_NORMAL, EGW_AO_STATE_VISIBLE, EGW_AO_STATES} from './egw_action_constants';
import {egwSetBit} from './egw_action_common';
import "./egw_menu_dhtmlx";
import {EgwAction} from "./EgwAction";
import {EgwActionManager} from "./EgwActionManager";
import {EgwActionImplementation} from "./EgwActionImplementation";
import {EgwActionLink} from "./EgwActionLink";
import {EgwActionObject} from "./EgwActionObject";
import {EgwActionObjectInterface} from "./EgwActionObjectInterface";
import {EgwActionObjectManager} from "./EgwActionObjectManager";
import {EgwDragAction} from "./EgwDragAction";
import {EgwDragActionImplementation} from "./egwDragActionImplementation";
import {EgwDropAction} from "./EgwDropAction";
import {egwDropActionImplementation} from "./EgwDropActionImplementation";
import "./egwGlobal"
import {EgwPopupAction} from "./EgwPopupAction";
import {getPopupImplementation} from "./EgwPopupActionImplementation";


/**
 * Getter functions for the global egwActionManager and egwObjectManager objects
 */

let egw_globalActionManager: EgwActionManager = null;
export var egw_globalObjectManager: EgwActionObjectManager = null;

/**
 * Returns the action manager for the given application - each application has its
 * own sub-ActionManager in the global action manager object to prevent collisions
 * from happening
 *
 * @param _id is the name of the sub-actionManager which should be returned.
 *    If the action manager does not exist right now, it is created. If the
 *    parameter is omitted or null, the global action manager is returned.
 * @param {boolean} [_create=true] If an objectManager with the given id is not
 *    found, it will be created at the top level.
 * @param {number} [_search_depth=Infinite] How deep into existing action children
 *    to search.
 */
export function egw_getActionManager(_id?: string, _create: boolean = true, _search_depth: number = Number.MAX_VALUE) {

    // Check whether the global action manager had been created, if not do so
    let res = egw_globalActionManager;
    if (egw_globalActionManager == null) {
        res = egw_globalActionManager = new EgwActionManager();
    }

    // Check whether the sub-action manager exists, if not, create it
    if (typeof _id != 'undefined' && _id != null) {
        res = egw_globalActionManager.getActionById(_id, _search_depth);
        if (res == null && _create) {
            res = egw_globalActionManager.addAction("actionManager", _id);
        }
    }

    return res;
}

/**
 * Returns the object manager for the given application - each application may
 * have its own object manager where it can place action objects or containers.
 *
 * @param _id is the name of the sub-object manager should be returned. If the
 *    object manager does not exist right now, it is created. If the parameter
 *    is ommited or null, the global object manager is returned.
 * @param {boolean} [_create=true] If an objectManager with the given id is not
 *    found, it will be created at the top level.
 * @param {number} [_search_depth=Infinite] How deep into existing action children
 *    to search.
 */
export function egw_getObjectManager(_id, _create = true, _search_depth = Number.MAX_VALUE): EgwActionObjectManager {

    // Check whether the global object manager exists
    let res = egw_globalObjectManager;
    if (res == null) {
        res = egw_globalObjectManager = new EgwActionObjectManager("_egwGlobalObjectManager", egw_getActionManager());
    }

    // Check whether the sub-object manager exists, if not, create it
    if (typeof _id != 'undefined' && _id != null) {
        res = egw_globalObjectManager.getObjectById(_id, _search_depth);
        if (res == null && _create) {
            res = new EgwActionObjectManager(_id, egw_getActionManager(_id, true, _search_depth));
            egw_globalObjectManager.addObject(res);
        }
    }

    return res;
}

/**
 * Returns the object manager for the current application
 *
 * @param {boolean} _create
 * @param {string} _appName //appName might not always be the current app, e.g. running app content under admin tab
 * @return {EgwActionObjectManager}
 */
export function egw_getAppObjectManager(_create = true, _appName = "") {
    return egw_getObjectManager(_appName ? _appName : window.egw(window).app_name(), _create, 1);
}

/**
 * Returns the action manager for the current application
 *
 * @param {boolean} _create
 * @return {EgwActionManager}
 */
// this function is never used
export function egw_getAppActionManager(_create) {
    return egw_getActionManager(window.egw_getAppName(), _create, 1);
}


/** egwActionHandler Interface **/

/**
 * Constructor for the egwActionHandler interface which (at least) should have the
 * execute function implemented.
 *
 * @param {function} _executeEvent
 * @return {egwActionHandler}
 * TODO no usage?
 */
export function egwActionHandler(_executeEvent) {
    //Copy the executeEvent parameter
    this.execute = _executeEvent;
}


/** egwAction Object
 * @deprecated use EgwAction
 * **/
export class egwAction extends EgwAction {

}


/** egwActionManager Object **/

/**
 * @deprecated
 */
export class egwActionManager extends EgwActionManager {
}

/**
 * Associative array where action classes may register themselves
 *
 */
if (typeof window._egwActionClasses == "undefined") {
    window._egwActionClasses = {
        actionManager: undefined,
        default: undefined,
        drag: undefined,
        drop: undefined,
        popup: undefined
    };
}
if (typeof window._egwActionClasses.actionManager == "undefined") {
    window._egwActionClasses.actionManager = {actionConstructor: EgwActionManager, implementation: null}
}
if (typeof window._egwActionClasses.default == "undefined") {
    window._egwActionClasses.default = {actionConstructor: EgwAction, implementation: null}
}
if (typeof window._egwActionClasses.drag == "undefined") {
    window._egwActionClasses.drag = {actionConstructor: EgwDragAction, implementation: getDragImplementation()}
}
if (typeof window._egwActionClasses.drop == "undefined") {
    window._egwActionClasses.drop = {actionConstructor: EgwDropAction, implementation: getDropImplementation()}
}



if (typeof window._egwActionClasses.popup == "undefined") {
    window._egwActionClasses.popup = {
        "actionConstructor": EgwPopupAction,
        "implementation": getPopupImplementation
    };
}


/** EgwActionImplementation Interface **/

/**
 * @deprecated implement upperCase interface EgwActionImplementation instead
 */
export class egwActionImplementation implements EgwActionImplementation {

    type: string;

    doRegisterAction = function (...args) {
        throw "Abstract function call: registerAction";
    };

    doUnregisterAction = function (...args) {
        throw "Abstract function call: unregisterAction";
    };

    doExecuteImplementation = function (...args) {
        throw "Abstract function call: executeImplementation";
    };

    executeImplementation(_context: any, _selected: any, _links: any): any {
        return this.doExecuteImplementation(_context, _selected, _links);
    }

    registerAction(_actionObjectInterface: EgwActionObjectInterface, _triggerCallback: Function, _context: object = null): boolean {
        return this.doRegisterAction(_actionObjectInterface, _triggerCallback, _context);
    }

    unregisterAction(_actionObjectInterface: EgwActionObjectInterface): boolean {
        return this.doUnregisterAction(_actionObjectInterface);
    }

}

/** egwActionLink Object **/

/**
 * @deprecated implement upperCase class instead
 */
export class egwActionLink extends EgwActionLink {
}

/**
 * @deprecated implement upperCase interface EgwActionImplementation instead
 */
export class egwActionObject extends EgwActionObject {
}

/** egwActionObjectInterface Interface **/

/**
 * @deprecated This is just a default wrapper class for the EgwActionObjectInterface interface.
 * Please directly implement it instead!
 * ... implements EgwActionObjectInterface{
 *     getDomNode(){...}
 * }
 * instead of className{
 *     var aoi = new egwActionObjectInterface()
 *     aoi.doGetDomNode = function ...
 * }
 *
 * @return {egwActionObjectInterface}
 */
export class egwActionObjectInterface implements EgwActionObjectInterface {
    //Preset the iface functions

    _state = EGW_AO_STATE_NORMAL || EGW_AO_STATE_VISIBLE;

    // _outerCall may be used to determine, whether the state change has been
    // evoked from the outside and the stateChangeCallback has to be called
    stateChangeCallback = null;

    // The doTriggerEvent function may be overwritten by the aoi if it wants to
    // support certain action implementation specific events like EGW_AI_DRAG_OVER
    stateChangeContext = null;
    reconnectActionsCallback = null;
    reconnectActionsContext = null;

    doGetDOMNode() {
        return null;
    };

    // or not.
    doSetState(_state) {
    };

    // or EGW_AI_DRAG_OUT
    doTriggerEvent(_event, _data) {
        return false;
    };

    doMakeVisible() {
    };

    getDOMNode(): Element {
        return this.doGetDOMNode();
    }

    getState(): number {
        return this._state;
    }

    makeVisible(): void {
        return this.doMakeVisible();
    }

    reconnectActions(): void {
        if (this.reconnectActionsCallback) {
            this.reconnectActionsCallback.call(this.reconnectActionsContext);
        }
    }

    setReconnectActionsCallback(_callback: Function, _context: any): void {
        this.reconnectActionsCallback = _callback;
        this.reconnectActionsContext = _context;
    }

    setState(_state: any): void {
        //Call the doSetState function with the new state (if it has changed at all)
        if (_state != this._state) {
            this._state = _state;
            this.doSetState(_state);
        }
    }

    setStateChangeCallback(_callback: Function, _context: any): void {
        this.stateChangeCallback = _callback;
        this.stateChangeContext = _context;
    }

    triggerEvent(_event: any, _data: any = null): boolean {

        return this.doTriggerEvent(_event, _data);
    }

    updateState(_stateBit: number, _set: boolean, _shiftState: boolean): void {
        // Calculate the new state
        //this does not guarantee a valid state at runtime
        const newState: EGW_AO_STATES = <EGW_AO_STATES>egwSetBit(this._state, _stateBit, _set);

        // Call the stateChangeCallback if the state really changed
        if (this.stateChangeCallback) {
            this._state = this.stateChangeCallback.call(this.stateChangeContext, newState, _stateBit, _shiftState);
        } else {
            this._state = newState;
        }
    }
}

/** egwActionObjectManager Object **/


/**
 * @deprecated implement upperCase class instead
 */
export class egwActionObjectManager extends EgwActionObjectManager {
}


/**
 * dragdrop
 */

/**
 * Register the drag and drop handlers
 */
if (typeof window._egwActionClasses == "undefined")
    window._egwActionClasses = {
        actionManager: undefined,
        default: undefined,
        drag: undefined,
        drop: undefined,
        popup: undefined
    };

/**
 * @deprecated
 */
export class egwDropAction extends EgwDropAction {
}

window._egwActionClasses["drop"] = {
    "actionConstructor": EgwDropAction,
    "implementation": getDropImplementation
};

/**
 * @deprecated
 */
export class egwDragAction extends EgwDragAction {
}

(() => {
    window._egwActionClasses.drag = {
        "actionConstructor": EgwDragAction, "implementation": getDragImplementation
    };
})()

let _dragActionImpl = null;

export function getDragImplementation() {
    if (!_dragActionImpl) {
        _dragActionImpl = new EgwDragActionImplementation();
    }
    return _dragActionImpl;
}


let _dropActionImpl = null;

export function getDropImplementation() {
    if (!_dropActionImpl) {
        _dropActionImpl = new egwDropActionImplementation();
    }
    return _dropActionImpl;
}

