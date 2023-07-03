/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {
    EGW_AI_DRAG_ENTER,
    EGW_AI_DRAG_OUT,
    EGW_AI_DRAG_OVER,
    EGW_AO_EXEC_THIS,
    EGW_AO_STATE_NORMAL,
    EGW_AO_STATE_VISIBLE,
    EGW_AO_STATES
} from './egw_action_constants';
import {egwSetBit} from './egw_action_common';
import '././egw_action_popup';
import "././egw_action_dragdrop";
import "./egw_menu_dhtmlx";
import {getPopupImplementation} from "././egw_action_popup";
import {EgwAction} from "./EgwAction";
import {EgwActionManager} from "./EgwActionManager";
import {EgwActionImplementation} from "./EgwActionImplementation";
import {EgwActionLink} from "./EgwActionLink";
import {EgwActionObject} from "./EgwActionObject";
import {EgwActionObjectInterface} from "./EgwActionObjectInterface";
import {EgwActionObjectManager} from "./EgwActionObjectManager";
import {EgwDragAction} from "./EgDragAction";
import {egwDragActionImplementation} from "./egwDragActionImplementation";


/**
 * Getter functions for the global egwActionManager and egwObjectManager objects
 */

let egw_globalActionManager:EgwActionManager = null;
export var egw_globalObjectManager:EgwActionObjectManager = null;

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
 *    object manager does not exists right now, it is created. If the parameter
 *    is ommited or null, the global object manager is returned.
 * @param {boolean} [_create=true] If an objectManager with the given id is not
 *    found, it will be created at the top level.
 * @param {number} [_search_depth=Infinite] How deep into existing action children
 *    to search.
 */
export function egw_getObjectManager(_id, _create = true, _search_depth = Number.MAX_VALUE): EgwActionObjectManager
{

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
export function egw_getAppObjectManager(_create, _appName="") {
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
export class egwActionManager extends EgwActionManager{}

/**
 * Associative array where action classes may register themselves
 *
 */
if (typeof window._egwActionClasses == "undefined") {
    window._egwActionClasses = {
        actionManager: {actionConstructor: null, implementation: null},
        default: {actionConstructor: null, implementation: null},
        drag: undefined,
        drop: undefined,
        popup: {actionConstructor: null, implementation: null}
    };
}
if (typeof window._egwActionClasses.actionManager == "undefined") {
    window._egwActionClasses.actionManager = {actionConstructor: EgwActionManager, implementation: null}
}
if (typeof window._egwActionClasses.default == "undefined") {
    window._egwActionClasses.default = {actionConstructor: EgwAction, implementation: null}
}

/** EgwActionImplementation Interface **/

/**
 * @deprecated implement upperCase interface EgwActionImplementation instead
 */
export class egwActionImplementation implements EgwActionImplementation {

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

    type: string;

    unregisterAction(_actionObjectInterface: EgwActionObjectInterface): boolean {
        return this.doUnregisterAction(_actionObjectInterface);
    }

}

/** egwActionLink Object **/

/**
 * @deprecated implement upperCase class instead
 */
export class egwActionLink extends  EgwActionLink{}

/**
 * @deprecated implement upperCase interface EgwActionImplementation instead
 */
export class egwActionObject extends EgwActionObject{}

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

    doGetDOMNode() {
        return null;
    };

    // _outerCall may be used to determine, whether the state change has been
    // evoked from the outside and the stateChangeCallback has to be called
    // or not.
    doSetState(_state) {
    };

    // The doTiggerEvent function may be overritten by the aoi if it wants to
    // support certain action implementation specific events like EGW_AI_DRAG_OVER
    // or EGW_AI_DRAG_OUT
    doTriggerEvent(_event, _data) {
        return false;
    };

    doMakeVisible() {
    };

    _state = EGW_AO_STATE_NORMAL || EGW_AO_STATE_VISIBLE;

    stateChangeCallback = null;
    stateChangeContext = null;
    reconnectActionsCallback = null;
    reconnectActionsContext = null;

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
export class egwActionObjectManager extends  EgwActionObjectManager{}





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



window._egwActionClasses["drop"] = {
    "actionConstructor": egwDropAction,
    "implementation": getDropImplementation
};

/**
 * @deprecated
 */
export class egwDragAction extends EgwDragAction
{}

(() => {
    window._egwActionClasses.drag = {
        "actionConstructor": egwDragAction, "implementation": getDragImplementation
    };
})()

let _dragActionImpl = null;

export function getDragImplementation()
{
    if (!_dragActionImpl)
    {
        _dragActionImpl = new egwDragActionImplementation();
    }
    return _dragActionImpl;
}



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
export function egwDropAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
{
    const action = new EgwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);

    action.type = "drop";
    action.acceptedTypes = ["default"];
    action.canHaveChildren = ["drag","popup"];
    action["default"] = false;
    action.order = 0;
    action.group = 0;

    action.set_default = function(_value) {
        action["default"] = _value;
    };

    action.set_order = function(_value) {
        action.order = _value;
    };

    action.set_group = function(_value) {
        action.group = _value;
    };

    /**
     * The acceptType property allows strings as well as arrays - strings are
     * automatically included in an array.
     *
     * @param {(string|array)} _value
     */
    action.set_acceptedTypes = function(_value) {
        if (_value instanceof Array)
        {
            action.acceptedTypes = _value;
        }
        else
        {
            action.acceptedTypes = [_value];
        }
    };

    return action;
}

let _dropActionImpl = null;

export function getDropImplementation()
{
    if (!_dropActionImpl)
    {
        _dropActionImpl = new egwDropActionImplementation();
    }
    return _dropActionImpl;
}

export function egwDropActionImplementation()
{
    const ai = new egwActionImplementation();

    //keeps track of current drop element where dragged item's entered.
    // it's necessary for dragenter/dragleave issue correction.
    let currentDropEl = null;

    ai.type = "drop";

    ai.doRegisterAction = function(_aoi, _callback, _context)
    {
        const node = _aoi.getDOMNode() && _aoi.getDOMNode()[0] ? _aoi.getDOMNode()[0] : _aoi.getDOMNode();
        const self = this;
        if (node)
        {
            node.classList.add('et2dropzone');
            const dragover = function (event) {
                if (event.preventDefault) {
                    event.preventDefault();
                }
                if (!self.getTheDraggedDOM()) return ;

                const data = {
                    event: event,
                    ui: self.getTheDraggedData()
                };
                _aoi.triggerEvent(EGW_AI_DRAG_OVER, data);

                return true;

            };

            const dragenter = function (event) {
                event.stopImmediatePropagation();
                // don't trigger dragenter if we are entering the drag element
                // don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
                if (!self.getTheDraggedDOM() || self.isTheDraggedDOM(this) || this == currentDropEl) return;

                currentDropEl = event.currentTarget;
                event.dataTransfer.dropEffect = 'link';

                const data = {
                    event: event,
                    ui: self.getTheDraggedData()
                };

                _aoi.triggerEvent(EGW_AI_DRAG_ENTER, data);

                // cleanup drop hover class from all other DOMs if there's still anything left
                Array.from(document.getElementsByClassName('et2dropzone drop-hover')).forEach(_i=>{_i.classList.remove('drop-hover')})

                this.classList.add('drop-hover');

                // stop the event from being fired for its children
                event.preventDefault();
                return false;
            };

            const drop = function (event) {
                event.preventDefault();
                // don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
                if (!self.getTheDraggedDOM()) return ;

                // remove the hover class
                this.classList.remove('drop-hover');

                const helper = self.getHelperDOM();
                let ui = self.getTheDraggedData();
                ui.position = {top: event.clientY, left: event.clientX};
                ui.offset = {top: event.offsetY, left: event.offsetX};


                let data = JSON.parse(event.dataTransfer.getData('application/json'));

                if (!self.isAccepted(data, _context, _callback) || self.isTheDraggedDOM(this))
                {
                    // clean up the helper dom
                    if (helper) helper.remove();
                    return;
                }

                let selected = data.selected.map((item) => {
                    return egw_getObjectManager(item.id, false)
                });


                const links = _callback.call(_context, "links", self, EGW_AO_EXEC_THIS);

                // Disable all links which only accept types which are not
                // inside ddTypes
                for (var k in links) {
                    const accepted = links[k].actionObj.acceptedTypes;

                    let enabled = false;
                    for (let i = 0; i < data.ddTypes.length; i++) {
                        if (accepted.indexOf(data.ddTypes[i]) != -1) {
                            enabled = true;
                            break;
                        }
                    }
                    // Check for allowing multiple selected
                    if (!links[k].actionObj.allowOnMultiple && selected.length > 1) {
                        enabled = false;
                    }
                    if (!enabled) {
                        links[k].enabled = false;
                        links[k].visible = !links[k].actionObj.hideOnDisabled;
                    }
                }

                // Check whether there is only one link
                let cnt = 0;
                let lnk = null;
                for (var k in links) {
                    if (links[k].enabled && links[k].visible) {
                        lnk = links[k];
                        cnt += 1 + links[k].actionObj.children.length;

                        // Add ui, so you know what happened where
                        lnk.actionObj.ui = ui;

                    }
                }

                if (cnt == 1) {
                    window.setTimeout(function () {
                        lnk.actionObj.execute(selected, _context);
                    }, 0);
                }

                if (cnt > 1) {
                    // More than one drop action link is associated
                    // to the drop event - show those as a popup menu
                    // and let the user decide which one to use.
                    // This is possible as the popup and the popup action
                    // object and the drop action object share same
                    // set of properties.
                    const popup = getPopupImplementation();
                    const pos = popup._getPageXY(event);

                    // Don't add paste actions, this is a drop
                    popup.auto_paste = false;

                    window.setTimeout(function () {
                        popup.doExecuteImplementation(pos, selected, links,
                            _context);
                        // Reset, popup is reused
                        popup.auto_paste = true;
                    }, 0); // Timeout is needed to have it working in IE
                }
                // Set cursor back to auto. Seems FF can't handle cursor reversion
                jQuery('body').css({cursor: 'auto'});

                _aoi.triggerEvent(EGW_AI_DRAG_OUT, {event: event, ui: self.getTheDraggedData()});

                // clean up the helper dom
                if (helper) helper.remove();
                self.getTheDraggedDOM().classList.remove('drag--moving');
            };

            const dragleave = function (event) {
                event.stopImmediatePropagation();

                // don't trigger dragleave if we are leaving the drag element
                // don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
                if (!self.getTheDraggedDOM() || self.isTheDraggedDOM(this) || this == currentDropEl) return;

                const data = {
                    event: event,
                    ui: self.getTheDraggedData()
                };

                _aoi.triggerEvent(EGW_AI_DRAG_OUT, data);

                this.classList.remove('drop-hover');

                event.preventDefault();
                return false;
            };

            // DND Event listeners
            node.addEventListener('dragover', dragover, false);

            node.addEventListener('dragenter', dragenter, false);

            node.addEventListener('drop', drop, false);

            node.addEventListener('dragleave', dragleave, false);

            return true;
        }
        return false;
    };

    ai.isTheDraggedDOM = function (_dom)
    {
        return _dom.classList.contains('drag--moving');
    }

    ai.getTheDraggedDOM = function ()
    {
        return document.querySelector('.drag--moving');
    }

    ai.getHelperDOM = function ()
    {
        return document.querySelector('.et2_egw_action_ddHelper');
    }

    ai.getTheDraggedData = function()
    {
        let data = this.getTheDraggedDOM().dataset.egwactionobjid;
        let selected = [];
        if (data)
        {
            data = JSON.parse(data);
            selected = data.map((item)=>{return egw_getObjectManager(item.id, false)});
        }
        return {
            draggable: this.getTheDraggedDOM(),
            helper: this.getHelperDOM(),
            selected: selected

        }
    }

    // check if given draggable is accepted for drop
    ai.isAccepted = function(_data, _context, _callback, _node)
    {
        if (_node && !_node.classList.contains('et2dropzone')) return false;
        if (typeof _data.ddTypes != "undefined")
        {
            const accepted = this._fetchAccepted(
                _callback.call(_context, "links", this, EGW_AO_EXEC_THIS));

            // Check whether all drag types of the selected objects
            // are accepted
            const ddTypes = _data.ddTypes;

            for (let i = 0; i < ddTypes.length; i++)
            {
                if (accepted.indexOf(ddTypes[i]) != -1)
                {
                    return true;
                }
            }
        }
        return false;
    };

    ai.doUnregisterAction = function(_aoi)
    {
        const node = _aoi.getDOMNode();

        if (node) {
            node.classList.remove('et2dropzone');
        }
    };

    ai._fetchAccepted = function(_links)
    {
        // Accumulate the accepted types
        const accepted = [];
        for (let k in _links)
        {
            for (let i = 0; i < _links[k].actionObj.acceptedTypes.length; i++)
            {
                const type = _links[k].actionObj.acceptedTypes[i];

                if (accepted.indexOf(type) == -1)
                {
                    accepted.push(type);
                }
            }
        }

        return accepted;
    };

    /**
     * Builds the context menu and shows it at the given position/DOM-Node.
     *
     * @param {string} _context
     * @param {array} _selected
     * @param {object} _links
     */
    ai.doExecuteImplementation = function(_context, _selected, _links)
    {
        if (_context == "links")
        {
            return _links;
        }
    };

    return ai;
}
