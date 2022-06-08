/**
 * EGroupware egw_action framework - TS declarations
 *
 * Generated with:
 * mkdir /tmp/egw_action
 * cd api/js/egw_action
 * tsc --declaration --allowJS --outDir /tmp/egw_action *.js
 * cat /tmp/egw_action/*.d.ts > egw_action.d.ts
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */
/**
 * Returns the action manager for the given application - each application has its
 * own sub-ActionManager in the global action manager object to prevent collisions
 * from happening
 *
 * @param _id is the name of the sub-actionManager which should be returned.
 * 	If the action manager does not exist right now, it is created. If the
 * 	parameter is ommited or null, the global action manager is returned.
 * @param {boolean} [_create=true] If an objectManager with the given id is not
 *	found, it will be created at the top level.
 * @param {number} [_search_depth=Infinite] How deep into existing action children
 *	to search.
 */
declare function egw_getActionManager(_id: any, _create?: boolean, _search_depth?: number): any;
/**
 * Returns the object manager for the given application - each application may
 * have its own object manager where it can place action objects or containers.
 *
 * @param _id is the name of the sub-object manager should be returned. If the
 * 	object manager does not exists right now, it is created. If the parameter
 *	is ommited or null, the global object manager is returned.
 * @param {boolean} [_create=true] If an objectManager with the given id is not
 *	found, it will be created at the top level.
 * @param {number} [_search_depth=Infinite] How deep into existing action children
 *	to search.
 */
declare function egw_getObjectManager(_id: any, _create?: boolean, _search_depth?: number): any;
/**
 * Returns the object manager for the current application
 *
 * @param {boolean} _create
 * @param {string} _appName
 * @return {egwActionObjectManager}
 */
declare function egw_getAppObjectManager(_create?: boolean, _appName?: string): typeof egwActionObjectManager;
/**
 * Returns the action manager for the current application
 *
 * @param {boolean} _create
 * @return {egwActionManager}
 */
declare function egw_getAppActionManager(_create: boolean): typeof egwActionManager;
/** egwActionHandler Interface **/
/**
 * Constructor for the egwActionHandler interface which (at least) should have the
 * execute function implemented.
 *
 * @param {function} _executeEvent
 * @return {egwActionHandler}
 */
declare function egwActionHandler(_executeEvent: Function): egwActionHandler;
declare class egwActionHandler {
    /** egwActionHandler Interface **/
    /**
     * Constructor for the egwActionHandler interface which (at least) should have the
     * execute function implemented.
     *
     * @param {function} _executeEvent
     * @return {egwActionHandler}
     */
    constructor(_executeEvent: Function);
    execute: Function;
}
/**
 * Constructor for egwAction object
 *
 * @param {egwAction} _parent
 * @param {string} _id
 * @param {string} _caption
 * @param {string} _iconUrl
 * @param {(string|function)} _onExecute
 * @param {boolean} _allowOnMultiple
 * @returns {egwAction}
 */
declare function egwAction(_parent: egwAction, _id: string, _caption: string, _iconUrl: string, _onExecute: TimerHandler, _allowOnMultiple: boolean): egwAction;
declare class egwAction {
    /**
     * Constructor for egwAction object
     *
     * @param {egwAction} _parent
     * @param {string} _id
     * @param {string} _caption
     * @param {string} _iconUrl
     * @param {(string|function)} _onExecute
     * @param {boolean} _allowOnMultiple
     * @returns {egwAction}
     */
    constructor(_parent: egwAction, _id: string, _caption: string, _iconUrl: string, _onExecute: TimerHandler, _allowOnMultiple: boolean);
    id: string;
    caption: string;
    iconUrl: string;
    allowOnMultiple: boolean;
    enabled: any;
    hideOnDisabled: boolean;
	data : { [key : string] : any };
	type: string;
    canHaveChildren: boolean;
    parent: egwAction;
    children: any[];
    onExecute: egwFnct;
    hideOnMobile: boolean;
    disableIfNoEPL: boolean;
    remove(): void;
    getActionById(_id: string | number, _search_depth?: number): egwAction;
    getActionsByAttr(_attr: string, _val: any): egwAction[];
    addAction(_type: string, _id: string, _caption: string, _iconUrl: any, _onExecute: TimerHandler, _allowOnMultiple: boolean): any;
    /**
     * Default icons for given id
     */
    defaultIcons: {
        view: string;
        edit: string;
        open: string;
        add: string;
        "new": string;
        "delete": string;
        cat: string;
        document: string;
        print: string;
        copy: string;
        move: string;
        cut: string;
        paste: string;
        save: string;
        apply: string;
        cancel: string;
        'continue': string;
        next: string;
        finish: string;
        back: string;
        previous: string;
        close: string;
    };
    updateActions(_actions: any, _app?: string): void;
    not_disableClass(_action: any, _senders: any, _target: any): boolean;
    enableClass(_action: any, _senders: any, _target: any): boolean;
    enableId(_action: any, _senders: any, _target: any): any;
    setDefaultExecute(_value: TimerHandler): void;
    execute(_senders: any[], _target: any): void;
    _check_confirm_mass_selections(_senders: any, _target: any): boolean;
    _check_confirm(_senders: any, _target: any): any;
    set_onExecute(_value: string | boolean | Function): void;
    set_caption(_value: any): void;
    set_iconUrl(_value: any): void;
    set_enabled(_value: any): void;
    set_allowOnMultiple(_value: string | number | boolean): void;
    set_hideOnDisabled(_value: any): void;
    set_hideOnMobile(_value: any): void;
    set_disableIfNoEPL(_value: any): void;
    set_data(_value: any): void;
    updateAction(_data: any): void;
    appendToTree(_tree: any[], _addChildren: boolean): {
        "action": egwAction;
        "children": any[];
    };
    getManager(): any;
}
declare function _egwActionTreeContains(_tree: any, _elem: any): any;
/** egwActionManager Object **/
/**
 * egwActionManager manages a list of actions - it overwrites the egwAction class
 * and allows child actions to be added to it.
 *
 * @param {egwAction} _parent
 * @param {string} _id
 * @return {egwActionManager}
 */
declare function egwActionManager(_parent: egwAction, _id: string): typeof egwActionManager;
/** egwActionImplementation Interface **/
/**
 * Abstract interface for the egwActionImplementation object. The egwActionImplementation
 * object is responsible for inserting the actual action representation (context menu,
 * drag-drop code) into the DOM Tree by using the egwActionObjectInterface object
 * supplied by the object.
 * To write a "class" which derives from this object, simply write a own constructor,
 * which replaces "this" with a "new egwActionImplementation" and implement your
 * code in "doRegisterAction" und "doUnregisterAction".
 * Register your own implementation within the _egwActionClasses object.
 *
 * @return {egwActionImplementation}
 */
declare function egwActionImplementation(): egwActionImplementation;
declare class egwActionImplementation {
    doRegisterAction: () => never;
    doUnregisterAction: () => never;
    doExecuteImplementation: () => never;
    type: string;
    registerAction(_actionObjectInterface: any, _triggerCallback: Function, _context: any): any;
    unregisterAction(_actionObjectInterface: egwActionObjectInterface): any;
    executeImplementation(_context: any, _selected: any, _links: any): any;
}
/** egwActionLink Object **/
/**
 * The egwActionLink is used to interconnect egwActionObjects and egwActions.
 * This gives each action object the possibility to decide, whether the action
 * should be active in this context or not.
 *
 * @param _manager is a reference to the egwActionManager whic contains the action
 * 	the object wants to link to.
 */
declare function egwActionLink(_manager: any): void;
declare class egwActionLink {
    /** egwActionLink Object **/
    /**
     * The egwActionLink is used to interconnect egwActionObjects and egwActions.
     * This gives each action object the possibility to decide, whether the action
     * should be active in this context or not.
     *
     * @param _manager is a reference to the egwActionManager whic contains the action
     * 	the object wants to link to.
     */
    constructor(_manager: any);
    enabled: boolean;
    visible: boolean;
    actionId: string;
    actionObj: any;
    manager: any;
    updateLink(_data: any): void;
    set_enabled(_value: any): void;
    set_visible(_value: any): void;
    set_actionId(_value: any): void;
}
/**
 * The egwActionObject represents an abstract object to which actions may be
 * applied. Communication with the DOM tree is established by using the
 * egwActionObjectInterface (AOI), which is passed in the constructor.
 * egwActionObjects are organized in a tree structure.
 *
 * @param {string} _id is the identifier of the object which
 * @param {egwActionObject} _parent is the parent object in the hirachy. This may be set to NULL
 * @param {egwActionObjectInterface} _iface is the egwActionObjectInterface which connects the object
 * 	to the outer world.
 * @param {egwActionManager} _manager is the action manager this object is connected to
 * 	this object to the DOM tree. If the _manager isn't supplied, the parent manager
 * 	is taken.
 * @param {number} _flags a set of additional flags being applied to the object,
 * 	defaults to 0
 */
declare function egwActionObject(_id: string, _parent: egwActionObject, _iface?: egwActionObjectInterface, _manager?: typeof egwActionManager, _flags?: number): void;
declare class egwActionObject {
    /**
     * The egwActionObject represents an abstract object to which actions may be
     * applied. Communication with the DOM tree is established by using the
     * egwActionObjectInterface (AOI), which is passed in the constructor.
     * egwActionObjects are organized in a tree structure.
     *
     * @param {string} _id is the identifier of the object which
     * @param {egwActionObject} _parent is the parent object in the hirachy. This may be set to NULL
     * @param {egwActionObjectInterface} _iface is the egwActionObjectInterface which connects the object
     * 	to the outer world.
     * @param {egwActionManager} _manager is the action manager this object is connected to
     * 	this object to the DOM tree. If the _manager isn't supplied, the parent manager
     * 	is taken.
     * @param {number} _flags a set of additional flags being applied to the object,
     * 	defaults to 0
     */
    constructor(_id: string, _parent: egwActionObject, _iface?: egwActionObjectInterface, _manager?: typeof egwActionManager, _flags?: number);
    id: string;
    parent: egwActionObject;
    children: any[];
    actionLinks: any[];
    manager: typeof egwActionManager;
    flags: number;
    data: any;
    setSelectedCallback: any;
    registeredImpls: any[];
    selectedChildren: any[];
    focusedChild: string | egwActionObject;
    setAOI(_aoi: egwActionObjectInterface): void;
    iface: egwActionObjectInterface;
    getObjectById(_id: string, _search_depth?: number): egwActionObject;
    addObject(_id: any, _interface: any, _flags?: number): any;
    insertObject(_index: number | boolean, _id: any, _iface?: any, _flags?: number): any;
    clear(): void;
    remove(): void;
    getRootObject(): any;
    getParentList(): any;
    getContainerRoot(): any;
    getSelectedObjects(_test: Function, _list: any[]): any;
    getAllSelected(): boolean;
    toggleAllSelected(_select: any): any;
    flatList(_visibleOnly: boolean, _obj: any): any[];
    traversePath(_to: any): any[];
    getIndex(): number;
    getFocusedObject(): string | egwActionObject;
    _ifaceCallback(_newState: number, _changedBit: number, _shiftState: number): number;
    handleKeyPress(_keyCode: number, _shift: boolean, _ctrl: boolean, _alt: boolean): boolean;
    getPrevious(_intval: any): any;
    getNext(_intval: any): any;
    getSelected(): boolean;
    getFocused(): boolean;
    getVisible(): boolean;
    getState(): number;
    setFocused(_focused: boolean): void;
    setSelected(_selected: boolean): void;
    setAllSelected(_selected: boolean, _informParent: boolean): void;
    updateSelectedChildren(_child: string | egwActionObject, _selected: boolean): void;
    updateFocusedChild(_child: string | egwActionObject, _focused: boolean): void;
    updateActionLinks(_actionLinks: any[], _recursive?: boolean, _doCreate?: boolean): void;
    _reconnectCallback(): void;
    registerActions(): void;
    unregisterActions(): void;
    triggerCallback(): any;
    makeVisible(): void;
    executeActionImplementation(_implContext: any, _implType: string, _execType: number): any;
    forceSelection(): void;
    getSelectedLinks(_actionType: any): any;
    _getLinks(_objs: any[], _actionType: string): any;
    getActionLink(_actionId: string): any;
    getActionImplementationGroups(_test: Function, _groups: any): any;
    isDragOut(_event: Event): boolean;
    isSelection(_event: any): boolean;
}
/** egwActionObjectInterface Interface **/
/**
 * The egwActionObjectInterface has to be implemented for each actual object in
 * the browser. E.g. for the object "DataGridRow", there has to be an
 * egwActionObjectInterface which is responsible for returning the outer DOMNode
 * of the object to which JS-Events may be attached by the egwActionImplementation
 * object, and to do object specific stuff like highlighting the object in the
 * correct way and to route state changes (like: "object has been selected")
 * to the egwActionObject object the interface is associated to.
 *
 * @return {egwActionObjectInterface}
 */
declare function egwActionObjectInterface(): egwActionObjectInterface;
declare class egwActionObjectInterface {
    doGetDOMNode: () => any;
    doSetState: (_state: any, _outerCall: any) => void;
    doTriggerEvent: (_event: any, _data: any) => boolean;
    doMakeVisible: () => void;
    _state: number;
    stateChangeCallback: Function;
    stateChangeContext: any;
    reconnectActionsCallback: Function;
    reconnectActionsContext: any;
    setStateChangeCallback(_callback: Function, _context: any): void;
    setReconnectActionsCallback(_callback: Function, _context: any): void;
    reconnectActions(): void;
    updateState(_stateBit: number, _set: boolean, _shiftState: boolean): void;
    getDOMNode(): any;
    setState(_state: any): void;
    getState(): number;
    triggerEvent(_event: any, _data: any): boolean;
    makeVisible(): void;
}
/** egwActionObjectManager Object **/
/**
 * The egwActionObjectManager is a dummy class which only contains a dummy
 * AOI. It may be used as root object or as object containers.
 *
 * @param {string} _id
 * @param {string} _manager
 * @return {egwActionObjectManager}
 */
declare function egwActionObjectManager(_id: egwAction, _manager: string): typeof egwActionObjectManager;
/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
/**
 * Getter functions for the global egwActionManager and egwObjectManager objects
 */
declare var egw_globalActionManager: any;
declare var egw_globalObjectManager: any;
/** egwActionObject Object **/
declare var EGW_AO_STATE_NORMAL: number;
declare var EGW_AO_STATE_SELECTED: number;
declare var EGW_AO_STATE_FOCUSED: number;
declare var EGW_AO_STATE_VISIBLE: number;
declare var EGW_AO_EVENT_DRAG_OVER_ENTER: number;
declare var EGW_AO_EVENT_DRAG_OVER_LEAVE: number;
declare var EGW_AO_SHIFT_STATE_NONE: number;
declare var EGW_AO_SHIFT_STATE_MULTI: number;
declare var EGW_AO_SHIFT_STATE_BLOCK: number;
declare var EGW_AO_FLAG_IS_CONTAINER: number;
declare var EGW_AO_FLAG_DEFAULT_FOCUS: number;
declare var EGW_AO_EXEC_SELECTED: number;
declare var EGW_AO_EXEC_THIS: number;
/** -- egwActionObjectDummyInterface Class -- **/
declare var egwActionObjectDummyInterface: typeof egwActionObjectInterface;
/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
/**
 * Sets properties given in _data in _obj. Checks whether the property keys
 * exists and if corresponding setter functions are available. Properties starting
 * with "_" are ignored.
 *
 * @param object _data may be an object with data that will be stored inside the
 * 	given object.
 * @param object _obj is the object where the data will be stored.
 * @param mixed _setterOnly false: store everything, true: only store when setter exists, "data" store rest in data property
 */
declare function egwActionStoreJSON(_data: any, _obj: any, _setterOnly: any): void;
/**
 * Switches the given bit in the set on or off.
 *
 * @param int _set is the current set
 * @param int _bit is the position of the bit which should be switched on/off
 * @param boolean _state is whether the bit should be switched on or off
 * @returns the new set
 */
declare function egwSetBit(_set: any, _bit: any, _state: any): number;
/**
 * Returns whether the given bit is set in the set.
 */
declare function egwBitIsSet(_set: any, _bit: any): boolean;
declare function egwObjectLength(_obj: any): number;
/**
 * Isolates the shift state from an event object
 */
declare function egwGetShiftState(e: any): number;
declare function egwPreventSelect(e: any): boolean;
declare class egwPreventSelect {
    constructor(e: any);
    onselectstart: () => boolean;
}
declare function egwResetPreventSelect(elem: any): void;
declare function egwUnfocus(): void;
declare function egwCallAbstract(_obj: any, _fn: any, _args: any): any;
declare function egwArraysEqual(_ar1: any, _ar2: any): boolean;
declare function egwQueueCallback(_proc: any, _args: any, _context: any, _id: any): void;
/**
 * The eventQueue object is used to have control over certain events such as
 * ajax responses or timeouts. Sometimes it may happen, that a function attached
 * to such an event should no longer be called - with egwEventQueue one has
 * a simple possibility to control that.
 */
/**
 * Constructor for the egwEventQueue class. Initializes the queue object and the
 * internal data structures such as the internal key.
 */
declare function egwEventQueue(): void;
declare class egwEventQueue {
    events: {};
    key_id: number;
    flush(): void;
    queue(_proc: any, _context: any, _args: any, _id: any): string;
    run(_key: any): void;
    queueTimeout(_proc: any, _context: any, _args: any, _id: any, _timeout: any): void;
}
/**
 * Class which is used to be able to handle references to JavaScript functions
 * from strings.
 *
 * @param object _context is the context in which the function will be executed.
 * @param mixed _default is the default value which should be returned when no
 * 	function (string) has been set. If it is a function this function will be
 * 	called.
 * @param array _acceptedTypes is an array of types which contains the "typeof"
 * 	strings of accepted non-functions in setValue
 */
declare function egwFnct(_context: any, _default: any, _acceptedTypes?: any): void;
declare class egwFnct {
    /**
     * Class which is used to be able to handle references to JavaScript functions
     * from strings.
     *
     * @param object _context is the context in which the function will be executed.
     * @param mixed _default is the default value which should be returned when no
     * 	function (string) has been set. If it is a function this function will be
     * 	called.
     * @param array _acceptedTypes is an array of types which contains the "typeof"
     * 	strings of accepted non-functions in setValue
     */
    constructor(_context: any, _default: any, _acceptedTypes?: any);
    context: any;
    acceptedTypes: any;
    fnct: any;
    value: any;
    isDefault: boolean;
    hasHandler(): boolean;
    setValue(_value: any): void;
    exec(...args: any[]): any;
}
declare function egwIsMobile(): any;
/**
sprintf() for JavaScript 0.6

Copyright (c) Alexandru Marasteanu <alexaholic [at) gmail (dot] com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of sprintf() for JavaScript nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL Alexandru Marasteanu BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


Changelog:
2007.04.03 - 0.1:
 - initial release
2007.09.11 - 0.2:
 - feature: added argument swapping
2007.09.17 - 0.3:
 - bug fix: no longer throws exception on empty paramenters (Hans Pufal)
2007.10.21 - 0.4:
 - unit test and patch (David Baird)
2010.05.09 - 0.5:
 - bug fix: 0 is now preceeded with a + sign
 - bug fix: the sign was not at the right position on padded results (Kamal Abdali)
 - switched from GPL to BSD license
2010.05.22 - 0.6:
 - reverted to 0.4 and fixed the bug regarding the sign of the number 0
 Note:
 Thanks to Raphael Pigulla <raph (at] n3rd [dot) org> (http://www.n3rd.org/)
 who warned me about a bug in 0.5, I discovered that the last update was
 a regress. I appologize for that.
**/
declare function str_repeat(i: any, m: any): string;
declare function sprintf(...args: any[]): string;
declare var _egwQueuedCallbacks: {};
/**
 * Checks whether this is currently run on a mobile browser
 */
declare var _egw_mobileBrowser: any;
/**
 * The egwDragAction class overwrites the egwAction class and adds the new
 * "dragType" propery. The "onExecute" event of the drag action will be called
 * whenever dragging starts. The onExecute JS handler should return the
 * drag-drop helper object - otherwise an default helper will be generated.
 *
 * @param {egwAction} _id
 * @param {string} _handler
 * @param {string} _caption
 * @param {string} _icon
 * @param {(string|function)} _onExecute
 * @param {bool} _allowOnMultiple
 * @returns {egwDragAction}
 */
declare function egwDragAction(_id: egwAction, _handler: string, _caption: string, _icon: string, _onExecute: TimerHandler, _allowOnMultiple: any): typeof egwDragAction;
declare function getDragImplementation(): any;
declare function egwDragActionImplementation(): egwActionImplementation;
/**
 * The egwDropAction class overwrites the egwAction class and adds the "acceptedTypes"
 * property. This array should contain all "dragTypes" the drop action is allowed to
 *
 * @param {egwAction} _id
 * @param {string} _handler
 * @param {string} _caption
 * @param {string} _icon
 * @param {(string|function)} _onExecute
 * @param {bool} _allowOnMultiple
 * @returns {egwDropAction}
 */
declare function egwDropAction(_id: egwAction, _handler: string, _caption: string, _icon: string, _onExecute: TimerHandler, _allowOnMultiple: any): typeof egwDropAction;
declare function getDropImplementation(): any;
declare function egwDropActionImplementation(): egwActionImplementation;
declare var _dragActionImpl: any;
declare var _dropActionImpl: any;
declare var EGW_AI_DRAG: number;
declare var EGW_AI_DRAG_OUT: number;
declare var EGW_AI_DRAG_OVER: number;
declare function egwPopupAction(_id: any, _handler: any, _caption: any, _icon: any, _onExecute: any, _allowOnMultiple: any): egwAction;
declare function getPopupImplementation(): any;
declare function egwPopupActionImplementation(): egwActionImplementation;
declare var _popupActionImpl: any;
/**
 * eGroupWare egw_dragdrop_dhtmlxmenu - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
/**
* This file contains an egw_actionObjectInterface which allows a dhtmlx tree
* row to be a drag target and contains a function which transforms a complete
* dhtmlx tree into egw_actionObjects
*/
declare function dhtmlxTree_getNode(_tree: any, _itemId: any): JQuery;
declare function dhtmlxtreeItemAOI(_tree: any, _itemId: any): egwActionObjectInterface;
/**
 * Checks whether the given keycode is in the list of valid key codes. If not,
 * returns -1.
 */
declare function egw_keycode_makeValid(_keyCode: any): any;
declare function _egw_nodeIsInInput(_node: any): any;
/**
 * Creates an unique key for the given shortcut
 */
declare function egw_shortcutIdx(_keyCode: any, _shift: any, _ctrl: any, _alt: any): string;
/**
 * Registers a global shortcut. If the shortcut already exists, it is overwritten.
 * @param int _keyCode is one of the keycode constants
 * @param bool _shift whether shift has to be set
 * @param bool _ctrl whether ctrl has to be set
 * @param bool _alt whether alt has to be set
 * @param function _handler the function which will be called when the shortcut
 * 	is evoked. An object containing the shortcut data will be passed as first
 * 	parameter.
 * @param object _context is the context in which the function will be executed
 */
declare function egw_registerGlobalShortcut(_keyCode: any, _shift: any, _ctrl: any, _alt: any, _handler: any, _context: any): void;
/**
 * Unregisters the given shortcut.
 */
declare function egw_unregisterGlobalShortcut(_keyCode: any, _shift: any, _ctrl: any, _alt: any): void;
/**
 * the egw_keyHandler function handles various key presses. The boolean
 * _shift, _ctrl, _alt values have been translated into platform independent
 * values (for apple devices).
 */
declare function egw_keyHandler(_keyCode: any, _shift: any, _ctrl: any, _alt: any): any;
/**
 * eGroupWare egw_action framework - Shortcut/Keyboard input manager
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
/**
 * Define the key constants (IE doesn't support "const" keyword)
 */
declare var EGW_KEY_BACKSPACE: number;
declare var EGW_KEY_TAB: number;
declare var EGW_KEY_ENTER: number;
declare var EGW_KEY_ESCAPE: number;
declare var EGW_KEY_DELETE: number;
declare var EGW_KEY_SPACE: number;
declare var EGW_KEY_PAGE_UP: number;
declare var EGW_KEY_PAGE_DOWN: number;
declare var EGW_KEY_ARROW_LEFT: number;
declare var EGW_KEY_ARROW_UP: number;
declare var EGW_KEY_ARROW_RIGHT: number;
declare var EGW_KEY_ARROW_DOWN: number;
declare var EGW_KEY_0: number;
declare var EGW_KEY_1: number;
declare var EGW_KEY_2: number;
declare var EGW_KEY_3: number;
declare var EGW_KEY_4: number;
declare var EGW_KEY_5: number;
declare var EGW_KEY_6: number;
declare var EGW_KEY_7: number;
declare var EGW_KEY_8: number;
declare var EGW_KEY_9: number;
declare var EGW_KEY_A: number;
declare var EGW_KEY_B: number;
declare var EGW_KEY_C: number;
declare var EGW_KEY_D: number;
declare var EGW_KEY_E: number;
declare var EGW_KEY_F: number;
declare var EGW_KEY_G: number;
declare var EGW_KEY_H: number;
declare var EGW_KEY_I: number;
declare var EGW_KEY_J: number;
declare var EGW_KEY_K: number;
declare var EGW_KEY_L: number;
declare var EGW_KEY_M: number;
declare var EGW_KEY_N: number;
declare var EGW_KEY_O: number;
declare var EGW_KEY_P: number;
declare var EGW_KEY_Q: number;
declare var EGW_KEY_R: number;
declare var EGW_KEY_S: number;
declare var EGW_KEY_T: number;
declare var EGW_KEY_U: number;
declare var EGW_KEY_V: number;
declare var EGW_KEY_W: number;
declare var EGW_KEY_X: number;
declare var EGW_KEY_Y: number;
declare var EGW_KEY_Z: number;
declare var EGW_KEY_MENU: number;
declare var EGW_KEY_F1: number;
declare var EGW_KEY_F2: number;
declare var EGW_KEY_F3: number;
declare var EGW_KEY_F4: number;
declare var EGW_KEY_F5: number;
declare var EGW_KEY_F6: number;
declare var EGW_KEY_F7: number;
declare var EGW_KEY_F8: number;
declare var EGW_KEY_F9: number;
declare var EGW_KEY_F10: number;
declare var EGW_KEY_F11: number;
declare var EGW_KEY_F12: number;
declare var EGW_VALID_KEYS: number[];
declare function egw_keycode_translation_function(_nativeKeyCode: any): any;
declare var egw_registeredShortcuts: {};
/**
 * Internal function which generates a menu item with the given parameters as used
 * in e.g. the egwMenu.addItem function.
 */
declare function _egwGenMenuItem(_parent: any, _id: any, _caption: any, _iconUrl: any, _onClick: any): egwMenuItem;
/**
 * Internal function which parses the given menu tree in _elements and adds the
 * elements to the given parent.
 */
declare function _egwGenMenuStructure(_elements: any, _parent: any): egwMenuItem[];
/**
 * Internal function which searches for the given ID inside an element tree.
 */
declare function _egwSearchMenuItem(_elements: any, _id: any): any;
/**
 * Internal function which alows to set the onClick handler of multiple menu items
 */
declare function _egwSetMenuOnClick(_elements: any, _onClick: any): void;
/**
 * Constructor for the egwMenu object. The egwMenu object is a abstract representation
 * of a context/popup menu. The actual generation of the menu can by done by so
 * called menu implementations. Those are activated by simply including the JS file
 * of such an implementation.
 *
 * The currently available implementation is the "egwDhtmlxMenu.js" which is based
 * upon the dhtmlxmenu component.
 */
declare function egwMenu(): void;
declare class egwMenu {
    children: any[];
    instance: egwMenuImpl;
    _checkImpl(): boolean;
    showAt(_x: any, _y: any, _force: any): boolean;
    hide(): void;
    addItem(_id: any, _caption: any, _iconUrl: any, _onClick: any): egwMenuItem;
    clear(): void;
    loadStructure(_elements: any): void;
    getItem(_id: any): any;
    setGlobalOnClick(_onClick: any): void;
}
/**
 * Constructor for the egwMenuItem. Each entry in a menu (including seperators)
 * is represented by a menu item.
 */
declare function egwMenuItem(_parent: any, _id: any): void;
declare class egwMenuItem {
    /**
     * Constructor for the egwMenuItem. Each entry in a menu (including seperators)
     * is represented by a menu item.
     */
    constructor(_parent: any, _id: any);
    id: any;
    caption: string;
    checkbox: boolean;
    checked: boolean;
    groupIndex: number;
    enabled: boolean;
    iconUrl: string;
    onClick: any;
    default: boolean;
    data: any;
    shortcutCaption: any;
    children: any[];
    parent: any;
    getItem(_id: any): any;
    setGlobalOnClick(_onClick: any): void;
    addItem(_id: any, _caption: any, _iconUrl: any, _onClick: any): egwMenuItem;
    set_id(_value: any): void;
    set_caption(_value: any): void;
    set_checkbox(_value: any): void;
    set_checked(_value: any): void;
    set_groupIndex(_value: any): void;
    set_enabled(_value: any): void;
    set_onClick(_value: any): void;
    set_iconUrl(_value: any): void;
    set_default(_value: any): void;
    set_data(_value: any): void;
    set_hint(_value: any): void;
    hint: any;
    set_shortcutCaption(_value: any): void;
}
/**
 * eGroupWare egw_action framework - JS Menu abstraction
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
declare var _egw_active_menu: any;
/**
 * eGroupWare egw_action framework - JS Menu abstraction
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
/**
 *
 * @param {type} _structure
 */
declare function egwMenuImpl(_structure: any): void;
declare class egwMenuImpl {
    /**
     * eGroupWare egw_action framework - JS Menu abstraction
     *
     * @link http://www.egroupware.org
     * @author Andreas Stöckel <as@stylite.de>
     * @copyright 2011 by Andreas Stöckel
     * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
     * @package egw_action
     * @version $Id$
     */
    /**
     *
     * @param {type} _structure
     */
    constructor(_structure: any);
    dhtmlxmenu: any;
    _translateStructure(_structure: any, _parentId: any, _idCnt: any): number;
    showAt(_x: any, _y: any, _onHide: any): void;
    hide(): void;
}
/**
 * Main egwDynStyleSheet class - all egwDynStyleSheets share the same stylesheet
 * which is dynamically inserted into the head section of the DOM-Tree.
 * This stylesheet is created with the first egwDynStyleSheet class.
 */
declare function egwDynStyleSheet(): any;
declare class egwDynStyleSheet {
    styleSheet: any;
    selectors: {};
    selectorCount: number;
    updateRule(_selector: any, _rule: any): void;
}
/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */
/**
 * Contains the egwDynStyleSheet class which allows dynamic generation of stylesheet
 * rules - updating a single stylesheet rule is way more efficient than updating
 * the element style of many objects.
 */
declare var EGW_DYNAMIC_STYLESHEET: any;
