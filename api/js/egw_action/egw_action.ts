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
    EGW_AO_STATE_NORMAL,
    EGW_AO_STATE_VISIBLE,
    EGW_AO_STATE_SELECTED,
    EGW_AO_STATE_FOCUSED,
    EGW_AO_SHIFT_STATE_MULTI,
    EGW_AO_SHIFT_STATE_NONE,
    EGW_AO_FLAG_IS_CONTAINER,
    EGW_AO_SHIFT_STATE_BLOCK,
    EGW_KEY_ARROW_UP,
    EGW_KEY_ARROW_DOWN,
    EGW_KEY_PAGE_UP,
    EGW_KEY_PAGE_DOWN,
    EGW_AO_EXEC_THIS,
    EGW_AO_EXEC_SELECTED,
    EGW_KEY_A,
    EGW_KEY_SPACE, EGW_AO_STATES
} from './egw_action_constants';
import {
    EgwFnct, egwFnct, egwActionStoreJSON, egwBitIsSet, egwQueueCallback, egwSetBit, egwObjectLength
} from './egw_action_common';
import './egw_action_popup.js';
import "./egw_action_dragdrop.js";
import "./egw_menu_dhtmlx.js";
//import {app, egw, Iegw} from "../jsapi/egw_global";
//import {Et2Dialog} from "../etemplate/Et2Dialog/Et2Dialog";
import {nm_action} from "../etemplate/et2_extension_nextmatch_actions";
import type {Iegw} from "../jsapi/egw_global";

/**
 * holds all possible Types of a egwActionClass
 */
type EgwActionClasses = {
    default: EgwActionClassData,//
    actionManager: EgwActionClassData, drag: EgwActionClassData, drop: EgwActionClassData, popup: EgwActionClassData
}
/**
 * holds the constructor and implementation of an EgwActionClass
 */
type EgwActionClassData = {
    //type EgwAction["constructor"],
    actionConstructor: Function,
    implementation: any
}
//TODO egw global.js
declare global {
    interface Window {
        _egwActionClasses: EgwActionClasses;
        egw: Function //egw returns instance of client side api -- set in egw_core.js
        egwIsMobile: () => boolean // set in egw_action_commons.ts
        nm_action: typeof nm_action
        egw_getAppName: () => string
    }
}
/**
 * Getter functions for the global egwActionManager and egwObjectManager objects
 */

let egw_globalActionManager = null;
export var egw_globalObjectManager = null;

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
        res = egw_globalActionManager = new egwActionManager();
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
export function egw_getObjectManager(_id, _create = true, _search_depth = Number.MAX_VALUE) {

    // Check whether the global object manager exists
    let res = egw_globalObjectManager;
    if (res == null) {
        res = egw_globalObjectManager = new egwActionObjectManager("_egwGlobalObjectManager", egw_getActionManager());
    }

    // Check whether the sub-object manager exists, if not, create it
    if (typeof _id != 'undefined' && _id != null) {
        res = egw_globalObjectManager.getObjectById(_id, _search_depth);
        if (res == null && _create) {
            res = new egwActionObjectManager(_id, egw_getActionManager(_id, true, _search_depth));
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
 * @return {egwActionObjectManager}
 */
export function egw_getAppObjectManager(_create, _appName) {
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



/** egwAction Object **/


/**
 * Constructor for EgwAction object
 *
 * @param {EgwAction} _parent
 * @param {string} _id
 * @param {string} _caption
 * @param {string} _iconUrl
 * @param {(string|function)} _onExecute
 * @param {boolean} _allowOnMultiple
 * @returns {EgwAction}
 */
export class egwAction {
    public readonly id: string;
    private caption: string;

    public set_caption(_value) {
        this.caption = _value;
    }

    private iconUrl: string;

    public set_iconUrl(_value) {
        this.iconUrl = _value;
    }

    private allowOnMultiple: boolean | string | number;

    /**
     * The allowOnMultiple property may be true, false, "only" (> 1) or number of select, eg. 2
     *
     * @param {(boolean|string|number)} _value
     */
    public set_allowOnMultiple(_value: boolean | string | number) {
        this.allowOnMultiple = _value
    }

    public readonly enabled: EgwFnct;

    public set_enabled(_value) {
        this.enabled.setValue(_value);
    }

    public hideOnDisabled = false;

    public data: any = {}; // Data which can be freely assigned to the action
    /**
     * @deprecated just set the data parameter with '=' sign to use its setter
     * @param _value
     */
    public set_data(_value) {
        this.data = _value
    }

    type = "default"; //All derived classes have to override this!
    canHaveChildren: boolean | string[] = false; //Has to be overwritten by inheriting action classes
    // this is not bool all the time. Can be ['popup'] e.g. List of egwActionClasses that are allowed to have children?
    private readonly parent: egwAction;
    private children: egwAction[] = []; //i guess

    private readonly onExecute = new EgwFnct(this, null, []);

    /**
     * Set to either a confirmation prompt, or TRUE to indicate that this action
     * cares about large selections and to ask the confirmation prompt(s)
     *
     * --set in egw_action_popup--
     * @param {String|Boolean} _value
     */
    public confirm_mass_selection: string | boolean = undefined

    /**
     * The set_onExecute function is the setter function for the onExecute event of
     * the EgwAction object. There are three possible types the passed "_value" may
     * take:
     *    1. _value may be a string with the word "javaScript:" prefixed. The function
     *       which is specified behind the colon and which has to be in the global scope
     *       will be executed.
     *    2. _value may be a boolean, which specifies whether the external onExecute handler
     *       (passed as "_handler" in the constructor) will be used.
     *    3. _value may be a JS function which will then be called.
     * In all possible situation, the called function will get the following parameters:
     *    1. A reference to this action
     *    2. The senders, an array of all objects (JS)/object ids (PHP) which evoked the event
     *
     * @param {(string|function|boolean)} _value
     */
    public set_onExecute(_value) {
        this.onExecute.setValue(_value)
    }

    public hideOnMobile = false;
    public disableIfNoEPL = false;

    /**
     * Default icons for given id
     */
    public defaultIcons = {
        view: 'view',
        edit: 'edit',
        open: 'edit',	// does edit if possible, otherwise view
        add: 'new',
        new: 'new',
        delete: 'delete',
        cat: 'attach',		// add as category icon to api
        document: 'etemplate/merge',
        print: 'print',
        copy: 'copy',
        move: 'move',
        cut: 'cut',
        paste: 'editpaste',
        save: 'save',
        apply: 'apply',
        cancel: 'cancel',
        continue: 'continue',
        next: 'continue',
        finish: 'finish',
        back: 'back',
        previous: 'back',
        close: 'close'
    };

    constructor(_parent: egwAction, _id: string, _caption: string = "", _iconUrl: string = "", _onExecute: string | Function = null, _allowOnMultiple: boolean = true) {
        if (_parent && (typeof _id != "string" || !_id) && _parent.type !== "actionManager") {
            throw "EgwAction _id must be a non-empty string!";
        }
        this.parent = _parent;
        this.id = _id;
        this.caption = _caption;
        this.iconUrl = _iconUrl;
        if (_onExecute !== null) {
            this.set_onExecute(_onExecute)
        }
        this.allowOnMultiple = _allowOnMultiple;
        this.enabled = new EgwFnct(this, true);

    }

    /**
     * Clears the element and removes it from the parent container
     */
    public remove() {
        // Remove all references to the child elements
        this.children = [];
        // Remove this element from the parent list
        if (this.parent) {
            const idx = this.parent.children.indexOf(this);
            if (idx >= 0) {
                this.parent.children.splice(idx, 1);
            }
        }
    }

    /**
     * Searches for a specific action with the given id
     *
     * @param {(string|number)} _id ID of the action to find
     * @param {number} [_search_depth=Infinite] How deep into existing action children
     *    to search.
     *
     * @return {(EgwAction|null)}
     */
    public getActionById(_id: string, _search_depth: number = Number.MAX_VALUE): egwAction {
        // If the current action object has the given id, return this object
        if (this.id == _id) {
            return this;
        }
        // If this element is capable of having children, search those for the given
        // action id
        if (this.canHaveChildren) {
            for (let i = 0; i < this.children.length && _search_depth > 0; i++) {
                const elem = this.children[i].getActionById(_id, _search_depth - 1);
                if (elem) {
                    return elem;
                }
            }
        }

        return null;
    };

    /**
     * Searches for actions having an attribute with a certain value
     *
     * Example: actionManager.getActionsByAttr("checkbox", true) returns all checkbox actions
     *
     * @param {string} _attr attribute name
     * @param _val attribute value
     * @return array
     */
    public getActionsByAttr(_attr: string | number, _val: any = undefined) {
        let _actions = [];

        // If the current action object has the given attr AND value, or no value was provided, return it
        if (typeof this[_attr] != "undefined" && (this[_attr] === _val || typeof _val === "undefined" && this[_attr] !== null)) {
            _actions.push(this);
        }

        // If this element is capable of having children, search those too
        if (this.canHaveChildren) {
            for (let i = 0; i < this.children.length; i++) {
                _actions = _actions.concat(this.children[i].getActionsByAttr(_attr, _val));
            }
        }

        return _actions;
    };

    /**
     * Adds a new action to the child elements.
     *
     * @param {string} _type
     * @param {string} _id
     * @param {string} _caption
     * @param {string} _iconUrl
     * @param {(string|function)} _onExecute
     * @param {boolean} _allowOnMultiple
     */

    public addAction(_type: string, _id: string, _caption: string, _iconUrl: string, _onExecute: string | Function, _allowOnMultiple: boolean): egwAction {
        //Get the constructor for the given action type
        if (!(_type in window._egwActionClasses)) {
            //TODO doesn't default instead of popup make more sense here??
            _type = "popup"
        }

        // Only allow adding new actions, if this action class allows it.
        if (this.canHaveChildren) {
            const constructor: any = window._egwActionClasses[_type]?.actionConstructor;

            if (typeof constructor == "function") {
                const action: egwAction = new constructor(this, _id, _caption, _iconUrl, _onExecute, _allowOnMultiple);
                this.children.push(action);

                return action;
            } else {
                throw "Given action type not registered.";
            }
        } else {
            throw "This action does not allow child elements!";
        }
    };


    /**
     * Updates the children of this element
     *
     * @param {object} _actions { id: action, ...}
     * @param {string} _app defaults to egw_getAppname()
     */
    public updateActions(_actions: any[] | Object, _app) {
        if (this.canHaveChildren) {
            if (typeof _app == "undefined") _app = window.egw(window).app_name()
            /*
            this is an egw Object as defined in egw_core.js
            probably not because it changes on runtime
             */
            const localEgw: Iegw = window.egw(_app);
            //replaced jQuery calls
            if (Array.isArray(_actions)) {
                //_actions is now an object for sure
                //happens in test website
                _actions = {..._actions};
            }
            for (const i in _actions) {
                let elem = _actions[i];

                if (typeof elem == "string") {
                    //changes type of elem to Object {caption:string}
                    _actions[i] = elem = {caption: elem};
                }
                if (typeof elem == "object") // isn't this always true because of step above? Yes if elem was a string before
                {
                    // use attr name as id, if none given
                    if (typeof elem.id != "string") elem.id = i;

                    // if no iconUrl given, check icon and default icons
                    if (typeof elem.iconUrl == "undefined") {
                        if (typeof elem.icon == "undefined") elem.icon = this.defaultIcons[elem.id]; // only works if default Icon is available
                        if (typeof elem.icon != "undefined") {
                            elem.iconUrl = localEgw.image(elem.icon);
                        }
                        //if there is no icon and none can be found remove icon tag from the object
                        delete elem.icon;
                    }

                    // always add shortcut for delete
                    if (elem.id == "delete" && typeof elem.shortcut == "undefined") {
                        elem.shortcut = {
                            keyCode: 46, shift: false, ctrl: false, alt: false, caption: localEgw.lang('Del')
                        };
                    }

                    // translate caption
                    if (elem.caption && (typeof elem.no_lang == "undefined" || !elem.no_lang)) {
                        elem.caption = localEgw.lang(elem.caption);
                        if (typeof elem.hint == "string") elem.hint = localEgw.lang(elem.hint);
                    }
                    delete elem.no_lang;

                    // translate confirm messages and place '?' at the end iff not there yet
                    for (const attr in {confirm: '', confirm_multiple: ''}) {
                        if (typeof elem[attr] == "string") {
                            elem[attr] = localEgw.lang(elem[attr]) + ((elem[attr].substr(-1) != '?') ? '?' : '');
                        }
                    }

                    // set certain enabled functions iff elem.enabled is not set so false
                    if (typeof elem.enabled == 'undefined' || elem.enabled === true) {
                        if (typeof elem.enableClass != "undefined") {
                            elem.enabled = this.enableClass;
                        } else if (typeof elem.disableClass != "undefined") {
                            elem.enabled = this.not_disableClass;
                        } else if (typeof elem.enableId != "undefined") {
                            elem.enabled = this.enableId;
                        }
                    }

                    //Check whether the action already exists, and if no, add it to the
                    //actions list
                    let action = this.getActionById(elem.id);
                    if (!action) {
                        //elem will be popup on default
                        if (typeof elem.type == "undefined") {
                            elem.type = "popup";
                        }

                        let constructor = null;

                        // Check whether the given type is inside the "canHaveChildren"
                        // array // here can have children is used as array where possible types of children are stored
                        if (this.canHaveChildren !== true && this.canHaveChildren.indexOf(elem.type) == -1) {
                            throw "This child type '" + elem.type + "' is not allowed!";
                        }

                        if (typeof window._egwActionClasses[elem.type] != "undefined") {
                            constructor = window._egwActionClasses[elem.type].actionConstructor;
                        } else {
                            throw "Given action type \"" + elem.type + "\" not registered, because type does not exist";
                        }

                        if (typeof constructor == "function" && constructor) action = new constructor(this, elem.id); else throw "Given action type \"" + elem.type + "\" not registered.";

                        this.children.push(action);
                    }

                    action.updateAction(elem);

                    // Add sub-actions to the action
                    if (elem.children) {
                        action.updateActions(elem.children, _app);
                    }
                }
            }
        } else {
            throw "This action element cannot have children!";
        }
    };


    /**
     * Callback to check if none of _senders rows has disableClass set
     *
     * @param _action EgwAction object, we use _action.data.disableClass to check
     * @param _senders array of egwActionObject objects
     * @param _target egwActionObject object, gets called for every object in _senders
     * @returns boolean true if none has disableClass, false otherwise
     */
    private not_disableClass(_action: egwAction, _senders: any, _target: any) {
        if (_target.iface.getDOMNode()) {
            return !(_target.iface.getDOMNode()).classList.contains(_action.data.disableClass);
        } else if (_target.id) {
            // Checking on a something that doesn't have a DOM node, like a nm row
            // that's not currently rendered
            const data = egw.dataGetUIDdata(_target.id);
            if (data && data.data && data.data.class) {
                return -1 === data.data.class.split(' ').indexOf(_action.data.disableClass);
            }
        }
    };

    /**
     * Callback to check if all of _senders rows have enableClass set
     *
     * @param _action EgwAction object, we use _action.data.enableClass to check
     * @param _senders array of egwActionObject objects
     * @param _target egwActionObject object, gets called for every object in _senders
     * @returns boolean true if none has disableClass, false otherwise
     */
    //TODO senders is never used in function body??
    private enableClass(_action: egwAction, _senders: any[], _target: any) {
        if (typeof _target == 'undefined') {
            return false;
        } else if (_target.iface.getDOMNode()) {
            return (_target.iface.getDOMNode()).classList.contains(_action.data.enableClass);
        } else if (_target.id) {
            // Checking on a something that doesn't have a DOM node, like a nm row
            // that's not currently rendered.  Not as good as an actual DOM node check
            // since things can get missed, but better than nothing.
            const data = egw.dataGetUIDdata(_target.id);
            if (data && data.data && data.data.class) {
                return -1 !== data.data.class.split(' ').indexOf(_action.data.enableClass);
            }
        }
    };

    /**
     * Enable an _action, if it matches a given regular expression in _action.data.enableId
     *
     * @param _action EgwAction object, we use _action.data.enableId to check
     * @param _senders array of egwActionObject objects
     * @param _target egwActionObject object, gets called for every object in _senders
     * @returns boolean true if _target.id matches _action.data.enableId
     */
    private enableId(_action: egwAction, _senders: any[], _target: any) {
        if (typeof _action.data.enableId == 'string') {
            _action.data.enableId = new RegExp(_action.data.enableId);
        }
        return _target.id.match(_action.data.enableId);
    };

    /**
     * Applies the same onExecute handler to all actions which don't have an execute
     * handler set.
     *
     * @param {(string|function)} _value
     */
    public setDefaultExecute(_value: string | Function): void {
        // Check whether the onExecute handler of this action should be set
        if (this.type != "actionManager" && !this.onExecute.hasHandler()) {
            this.onExecute.isDefault = true;
            this.onExecute.setValue(_value);
        }

        // Apply the value to all children
        if (this.canHaveChildren) {
            for (const elem of this.children) {
                elem.setDefaultExecute(_value);
            }
        }
    };

    /**
     * Executes this action by using the method specified in the onExecute setter.
     *
     * @param {array} _senders array with references to the objects which caused the action
     * @param {object} _target is an optional parameter which may represent e.g. a drag drop target
     */
    execute(_senders, _target = null): any {
        if (!this._check_confirm_mass_selections(_senders, _target)) {
            return this._check_confirm(_senders, _target);
        }
    };

    /**
     * If this action needs to confirm mass selections (attribute confirm_mass_selection = true),
     * check for any checkboxes that have a confirmation prompt (confirm_mass_selection is a string)
     * and are unchecked.  We then show the prompt, and set the checkbox to their answer.
     *
     * * This is only considered if there are more than 20 entries selected.
     *
     * * Only the first confirmation prompt / checkbox action will be used, others
     *        will be ignored.
     *
     * @param {type} _senders
     * @param {type} _target
     * @returns {Boolean}
     */
    private _check_confirm_mass_selections(_senders, _target) {
        const obj_manager: any = egw_getObjectManager(this.getManager().parent.id, false);
        if (!obj_manager) {
            return false;
        }

        // Action needs to care about mass selection - check for parent that cares too
        let confirm_mass_needed = false;
        let action: egwAction = this;
        while (action && action !== obj_manager.manager && !confirm_mass_needed) {
            confirm_mass_needed = !!action.confirm_mass_selection;
            action = action.parent;
        }
        if (!confirm_mass_needed) return false;

        // Check for confirm mass selection checkboxes
        const confirm_mass_selections = obj_manager.manager.getActionsByAttr("confirm_mass_selection");
        confirm_mass_needed = _senders.length > 20;
        //no longer needed because of '=>' notation
        //const self = this;

        // Find & show prompt
        for (let i = 0; confirm_mass_needed && i < confirm_mass_selections.length; i++) {
            const check = confirm_mass_selections[i];
            if (check.checkbox === false || check.checked === true) {
                continue
            }

            // Show the mass selection prompt
            const msg = egw.lang(check.confirm_mass_selection, obj_manager.getAllSelected() ? egw.lang('all') : _senders.length);
            const callback = (_button) => {
                // YES = unchecked, NO = checked
                check.set_checked(_button === Et2Dialog.NO_BUTTON);
                if (_button !== Et2Dialog.CANCEL_BUTTON) {
                    this._check_confirm(_senders, _target);
                }
            };
            Et2Dialog.show_dialog(callback, msg, this.data.hint, {}, Et2Dialog.BUTTONS_YES_NO_CANCEL, Et2Dialog.QUESTION_MESSAGE);
            return true;
        }
        return false;
    };


    /**
     * Check to see if action needs to be confirmed by user before we do it
     */
    private _check_confirm(_senders, _target) {
        // check if actions needs to be confirmed first
        if (this.data && (this.data.confirm || this.data.confirm_multiple) &&
            this.onExecute.functionToPerform != window.nm_action && typeof Et2Dialog != 'undefined')	// let old eTemplate run its own confirmation from nextmatch_action.js
        {
            let msg = this.data.confirm || '';
            if (_senders.length > 1) {
                if (this.data.confirm_multiple) {
                    msg = this.data.confirm_multiple;
                }
                // check if we have all rows selected
                const obj_manager = egw_getObjectManager(this.getManager().parent.id, false);
                if (obj_manager && obj_manager.getAllSelected()) {
                    msg += "\n\n" + egw().lang('Attention: action will be applied to all rows, not only visible ones!');
                }
            }
            //no longer needed because of '=>' notation
            //var self = this;
            if (msg.trim().length > 0) {
                if (this.data.policy_confirmation && egw.app('policy')) {
                    import(egw.link('/policy/js/app.min.js')).then(() => {
                            if (typeof app.policy === 'undefined' || typeof app.policy.confirm === 'undefined') {
                                app.policy = new app.classes.policy();
                            }
                            app.policy.confirm(this, _senders, _target);
                        }
                    );
                    return;
                }
                Et2Dialog.show_dialog((_button) => {
                    if (_button == Et2Dialog.YES_BUTTON) {
                        // @ts-ignore
                        return this.onExecute.exec(this, _senders, _target);
                    }
                }, msg, this.data.hint, {}, Et2Dialog.BUTTONS_YES_NO, Et2Dialog.QUESTION_MESSAGE);
                return;
            }
        }
        // @ts-ignore
        return this.onExecute.exec(this, _senders, _target);
    };


    private updateAction(_data: Object) {
        egwActionStoreJSON(_data, this, "data")
    }

    /**
     * Returns the parent action manager
     */
    getManager(): egwAction {
        if (this.type == "actionManager") {
            return this;
        } else if (this.parent) {
            return this.parent.getManager();
        } else {
            return null;
        }
    }

    /**
     * The appendToGraph function generates an action tree which automatically contains
     * all parent elements. If the appendToGraph function is called for a
     *
     * @param {not an array} _tree contains the tree structure - pass an object containing {root:Tree}??TODO
     *    the empty array "root" to this function {"root": []}. The result will be stored in
     *    this array.
     * @param {boolean} _addChildren is used internally to prevent parent elements from
     *    adding their children automatically to the tree.
     */
    public appendToTree(_tree: { root: Tree }, _addChildren: boolean = true) {

        if (typeof _addChildren == "undefined") {
            _addChildren = true;
        }

        // Preset some variables
        const root: Tree = _tree.root;
        let parentNode: TreeElem = null;
        let node: TreeElem = {
            "action": this, "children": []
        };


        if (this.parent && this.type != "actionManager") {
            // Check whether the parent container has already been added to the tree
            parentNode = _egwActionTreeFind(root, this.parent);

            if (!parentNode) {
                parentNode = this.parent.appendToTree(_tree, false);
            }

            // Check whether this element has already been added to the parent container
            let added = false;
            for (const child of parentNode.children) {
                if (child.action == this) {
                    node = child;
                    added = true;
                    break;
                }
            }

            if (!added) {
                parentNode.children.push(node);
            }
        } else {
            let added = false;
            for (const treeElem of root) {
                if (treeElem.action == this) {
                    node = treeElem;
                    added = true;
                    break;
                }
            }

            if (!added) {
                // Add this element to the root if it has no parent
                root.push(node);
            }
        }

        if (_addChildren) {
            for (const child of this.children) {
                child.appendToTree(_tree, true);
            }
        }

        return node;
    };

    /**
     * @deprecated directly set value instead
     * @param _value
     */
    set_hideOnDisabled(_value) {
        this.hideOnDisabled = _value;

    };

    /**
     * @deprecated directly set value instead
     * @param _value
     */
    set_hideOnMobile(_value) {
        this.hideOnMobile = _value;

    };

    /**
     * @deprecated directly set value instead
     * @param _value
     */
    set_disableIfNoEPL(_value) {
        this.disableIfNoEPL = _value;

    };


}

type TreeElem = { action: egwAction, children: Tree }
type Tree = TreeElem[]

/**
 * finds an egwAction in the given tree
 * @param {Tree}_tree where to search
 * @param {egwAction}_elem elem to search
 * @returns {TreeElem} the treeElement for corresponding _elem if found, null else
 */
function _egwActionTreeFind(_tree: Tree, _elem: egwAction): TreeElem {
    for (const current of _tree) {
        if (current.action == _elem) {
            return current;
        }

        if (typeof current.children != "undefined") {
            const elem = _egwActionTreeFind(current.children, _elem);
            if (elem) {
                return elem;
            }
        }
    }

    return null;
}


/** egwActionManager Object **/

/**
 * egwActionManager manages a list of actions - it overwrites the egwAction class
 * and allows child actions to be added to it.
 *
 * @param {egwAction} _parent
 * @param {string} _id
 * @return {egwActionManager}
 */
export class egwActionManager extends egwAction{
    constructor(_parent = null, _id = "") {
        super(_parent,_id);
        this.type = "actionManager";
        this.canHaveChildren = true;
    }
}

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
    window._egwActionClasses.actionManager = {actionConstructor: egwActionManager, implementation: null}
}
if (typeof window._egwActionClasses.default == "undefined") {
    window._egwActionClasses.default = {actionConstructor: egwAction, implementation: null}
}

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
export function egwActionImplementation() {
    this.doRegisterAction = function () {
        throw "Abstract function call: registerAction";
    };
    this.doUnregisterAction = function () {
        throw "Abstract function call: unregisterAction";
    };
    this.doExecuteImplementation = function () {
        throw "Abstract function call: executeImplementation";
    };
    this.type = "";
}

/**
 * Injects the implementation code into the DOM tree by using the supplied
 * actionObjectInterface.
 *
 * @param {object} _actionObjectInterface is the AOI in which the implementation
 *    should be registered.
 * @param {function} _triggerCallback is the callback function which will be triggered
 *    when the user triggeres this action implementatino (e.g. starts a drag-drop or
 *    right-clicks on an object.)
 * @param {object} _context in which the triggerCallback should get executed.
 * @returns true if the Action had been successfully registered, false if it
 *    had not.
 */
egwActionImplementation.prototype.registerAction = function (_actionObjectInterface, _triggerCallback, _context) {
    if (typeof _context == "undefined")
        _context = null;

    return this.doRegisterAction(_actionObjectInterface, _triggerCallback, _context);
};

/**
 * Unregister action will be called before an actionObjectInterface is destroyed,
 * which gives the egwActionImplementation the opportunity to remove the previously
 * injected code.
 *
 * @param {egwActionObjectInterface} _actionObjectInterface
 * @returns true if the Action had been successfully unregistered, false if it
 *    had not.
 */
egwActionImplementation.prototype.unregisterAction = function (_actionObjectInterface) {
    return this.doUnregisterAction(_actionObjectInterface);
};

egwActionImplementation.prototype.executeImplementation = function (_context, _selected, _links) {
    return this.doExecuteImplementation(_context, _selected, _links);
};


/** egwActionLink Object **/

/**
 * The egwActionLink is used to interconnect egwActionObjects and egwActions.
 * This gives each action object the possibility to decide, whether the action
 * should be active in this context or not.
 *
 * @param _manager is a reference to the egwActionManager whic contains the action
 *    the object wants to link to.
 */
export function egwActionLink(_manager) {
    this.enabled = true;
    this.visible = true;
    this.actionId = "";
    this.actionObj = null;
    this.manager = _manager;
}

egwActionLink.prototype.updateLink = function (_data) {
    egwActionStoreJSON(_data, this, true);
};

egwActionLink.prototype.set_enabled = function (_value) {
    this.enabled = _value;
};

egwActionLink.prototype.set_visible = function (_value) {
    this.visible = _value;
};

egwActionLink.prototype.set_actionId = function (_value) {
    this.actionId = _value;
    this.actionObj = this.manager.getActionById(_value);

    if (!this.actionObj)
        throw "Action object with id '" + _value + "' does not exist!";
};

/**
 * The egwActionObject represents an abstract object to which actions may be
 * applied. Communication with the DOM tree is established by using the
 * egwActionObjectInterface (AOI), which is passed in the constructor.
 * egwActionObjects are organized in a tree structure.
 *
 * @param {string} _id is the identifier of the object which
 * @param {egwActionObject} _parent is the parent object in the hirachy. This may be set to NULL
 * @param {egwActionObjectInterface} _iface is the egwActionObjectInterface which connects the object
 *    to the outer world.
 * @param {egwActionManager} _manager is the action manager this object is connected to
 *    this object to the DOM tree. If the _manager isn't supplied, the parent manager
 *    is taken.
 * @param {number} _flags a set of additional flags being applied to the object,
 *    defaults to 0
 */
export class egwActionObject {
    readonly id: string
    readonly parent: egwActionObject
    private readonly children: egwActionObject[] = []
    private actionLinks: egwActionLink[] = []
    iface: EgwActionObjectInterface
    readonly manager: egwActionManager
    private readonly flags: number
    data: any = null
    private readonly setSelectedCallback: any = null;
    private registeredImpls: any[] = [];
    // Two variables which help fast travelling through the object tree, when
    // searching for the selected/focused object.
    private selectedChildren = [];
    private focusedChild = null;
    private readonly onBeforeTrigger: Function = undefined
    _context: any = undefined


    constructor(_id: string, _parent, _interface, _manager?, _flags?: number) {
        if (typeof _manager == "undefined" && typeof _parent == "object" && _parent) _manager = _parent.manager;
        if (typeof _flags == "undefined") _flags = 0;


        this.id = _id
        this.parent = _parent
        this.iface = _interface
        this.manager = _manager
        this.flags = _flags

        this.setAOI(_interface)
    }

    /**
     * Sets the action object interface - if "NULL" is given, the iface is set
     * to a dummy interface which is used to store the temporary data.
     *
     * @param {egwActionObjectInterface} _aoi
     */
    setAOI(_aoi) {
        if (_aoi == null) {
            //TODo replace DummyInterface
            _aoi = new egwActionObjectDummyInterface();
        }

        // Copy the state from the old interface
        if (this.iface) {
            _aoi.setState(this.iface.getState());
        }

        // Replace the interface object
        this.iface = _aoi;
        this.iface.setStateChangeCallback(this._ifaceCallback, this);
        this.iface.setReconnectActionsCallback(this._reconnectCallback, this);
    };

    /**
     //     * Returns the object from the tree with the given ID
     //     *
     //     * @param {string} _id
     //     * @param {number} _search_depth
     //     * @return {egwActionObject} description
     //     * @todo Add search function to egw_action_commons.js
     //     */
    getObjectById(_id, _search_depth) {
        if (this.id == _id) {
            return this;
        }
        if (typeof _search_depth == "undefined") {
            _search_depth = Number.MAX_VALUE;
        }

        for (let i = 0; i < this.children.length && _search_depth > 0; i++) {
            const obj = this.children[i].getObjectById(_id, _search_depth - 1);
            if (obj) {
                return obj;
            }
        }

        return null;
    };


    /**
     * Adds an object as child to the actionObject and returns it - if the supplied
     * parameter is a object, the object will be added directly, otherwise an object
     * with the given id will be created.
     *
     * @param {(string|object)} _id Id of the object which will be created or the object
     *    that will be added.
     * @param {object} _interface if _id was an string, _interface defines the interface which
     *    will be connected to the newly generated object.
     * @param {number} _flags are the flags will which be supplied to the newly generated
     *    object. May be omitted.
     * @returns object the generated object
     */
    addObject(_id, _interface, _flags) {
        return this.insertObject(false, _id, _interface, _flags);
    };

    /**
     * Inserts an object as child to the actionObject and returns it - if the supplied
     * parameter is a object, the object will be added directly, otherwise an object
     * with the given id will be created.
     *
     * @param {number} _index Position where the object will be inserted, "false" will add it
     *    to the end of the list.
     * @param {string|object} _id Id of the object which will be created or the object
     *    that will be added.
     * @param {object} _iface if _id was an string, _iface defines the interface which
     *    will be connected to the newly generated object.
     * @param {number} _flags are the flags will which be supplied to the newly generated
     *    object. May be omitted.
     * @returns object the generated object
     */

    insertObject(_index, _id, _iface, _flags) {
        if (_index === false) _index = this.children.length;

        let obj = null;

        if (typeof _id == "object") {
            obj = _id;

            // Set the parent to null and reset the focus of the object
            obj.parent = null;
            obj.setFocused(false);

            // Set the parent to this object
            obj.parent = this;
        } else if (typeof _id == "string") {
            obj = new egwActionObject(_id, this, _iface, this.manager, _flags);
        }

        if (obj) {
            // Add the element to the children
            this.children.splice(_index, 0, obj);
        } else {
            throw "Error while adding new element to the ActionObjects!";
        }

        return obj;
    };

    /**
     * Deletes all children of the egwActionObject
     */

    clear() {
        // Remove all children
        while (this.children.length > 0) {
            this.children[0].remove();
        }

        // Delete all other references
        this.selectedChildren = [];
        this.focusedChild = null;

        // Remove links
        this.actionLinks = [];
    };

    /**
     * Deletes this object from the parent container
     */
    remove() {
        // Remove focus and selection from this element
        this.setFocused(false);
        this.setSelected(false);
        this.setAllSelected(false);

        // Unregister all registered action implementations
        this.unregisterActions();

        // Clear the child-list
        this.clear();

        // Remove this element from the parent list
        if (this.parent != null) {
            const idx = this.parent.children.indexOf(this);

            if (idx >= 0) {
                this.parent.children.splice(idx, 1);
            }
        }
    };

    /**
     * Searches for the root object in the action object tree and returns it.
     */
    getRootObject() {
        if (this.parent === null) {
            return this;
        } else {
            return this.parent.getRootObject();
        }
    };

    /**
     * Returns a list with all parents of this object.
     */
    getParentList() {
        if (this.parent === null) {
            return [];
        } else {
            const list = this.parent.getParentList();
            list.unshift(this.parent);
            return list;
        }
    };

    /**
     * Returns the first parent which has the container flag
     */
    getContainerRoot(): egwActionObject {
        if (egwBitIsSet(this.flags, EGW_AO_FLAG_IS_CONTAINER) || this.parent === null) {
            return this;
        } else {
            return this.parent.getContainerRoot();
        }
    };

    /**
     * Returns all selected objects which are in the current subtree.
     *
     * @param {function} _test is a function, which gets an object and checks whether
     *    it will be added to the list.
     * @param {array} _list is internally used to fetch all selected elements, please
     *    omit this parameter when calling the function.
     */
    getSelectedObjects(_test?, _list?) {
        if (typeof _test == "undefined") _test = null;

        if (typeof _list == "undefined") {
            _list = {"elements": []};
        }

        if ((!_test || _test(this)) && this.getSelected()) _list.elements.push(this);

        if (this.selectedChildren) {
            for (let i = 0; i < this.selectedChildren.length; i++) {
                this.selectedChildren[i].getSelectedObjects(_test, _list);
            }
        }

        return _list.elements;
    };

    /**
     * Returns whether all objects in this tree are selected
     */
    getAllSelected() {
        if (this.children.length == this.selectedChildren.length) {
            for (let i = 0; i < this.children.length; i++) {
                if (!this.children[i].getAllSelected()) return false;
            }
            // If this element is an container *and* does not have any children, we
            // should return false. If this element is not an container we have to
            // return true has this is the recursion base case
            return (!egwBitIsSet(this.flags, EGW_AO_FLAG_IS_CONTAINER)) || (this.children.length > 0);
        }

        return false;
    };

    /**
     * Toggles the selection of all objects.
     *
     * @param _select boolean specifies whether the objects should get selected or not.
     *    If this parameter is not supplied, the selection will be toggled.
     */
    toggleAllSelected(_select?) {
        if (typeof _select == "undefined") {
            _select = !this.getAllSelected();
        }

        // Check for a select_all action
        if (_select && this.manager && this.manager.getActionById('select_all')) {
            return this.manager.getActionById('select_all').execute(this);
        }
        this.setAllSelected(_select);
    };


    /**
     * Creates a list which contains all items of the element tree.
     *
     * @param {boolean} _visibleOnly
     * @param {object} _obj is used internally to pass references to the array inside
     *    the object.
     * @return {array}
     */
    flatList(_visibleOnly?: boolean, _obj?: { elements: egwActionObject[] }) {
        if (typeof (_obj) == "undefined") {
            _obj = {
                "elements": []
            };
        }

        if (typeof (_visibleOnly) == "undefined") {
            _visibleOnly = false;
        }

        if (!_visibleOnly || this.getVisible()) {
            _obj.elements.push(this);
        }

        for (const child of this.children) {
            child.flatList(_visibleOnly, _obj);
        }

        return _obj.elements;
    };

    /**
     * Returns a traversal list with all objects which are in between the given object
     * and this one. The operation returns an empty list, if a container object is
     * found on the way.
     *
     * @param {object} _to
     * @return {array}
     * @todo Remove flatList here!
     */
    traversePath(_to) {
        const contRoot: egwActionObject = this.getContainerRoot();

        if (contRoot) {
            // Get a flat list of all the hncp elements and search for this object
            // and the object supplied in the _to parameter.
            const flatList = contRoot.flatList();
            const thisId = flatList.indexOf(this);
            const toId = flatList.indexOf(_to);

            // Check whether both elements have been found in this part of the tree,
            // return the slice of that list.
            if (thisId !== -1 && toId !== -1) {
                const from = Math.min(thisId, toId);
                const to = Math.max(thisId, toId);

                return flatList.slice(from, to + 1);
            }
        }

        return [];
    };

    /**
     * Returns the index of this object in the children list of the parent object.
     */
    getIndex() {
        if (this.parent === null) {
            //TODO check: should be -1 for invalid
            return 0;
        } else {
            return this.parent.children.indexOf(this);
        }
    };

    /**
     * Returns the deepest object which is currently focused. Objects with the
     * "container"-flag will not be returned.
     */
    getFocusedObject() {
        return this.focusedChild || null;
    };

    /**
     * Internal function which is connected to the ActionObjectInterface associated
     * with this object in the constructor. It gets called, whenever the object
     * gets (de)selected.
     *
     * @param {number} _newState is the new state of the object
     * @param {number} _changedBit
     * @param {number} _shiftState is the status of extra keys being pressed during the
     *    selection process.
     *///TODO check
    _ifaceCallback(_newState: number, _changedBit: number, _shiftState?: number) {
        if (typeof _shiftState == "undefined") _shiftState = EGW_AO_SHIFT_STATE_NONE;

        let selected: boolean = egwBitIsSet(_newState, EGW_AO_STATE_SELECTED);
        const visible: boolean = egwBitIsSet(_newState, EGW_AO_STATE_VISIBLE);

        // Check whether the visibility of the object changed
        if (_changedBit == EGW_AO_STATE_VISIBLE && visible != this.getVisible()) {
            // Deselect the object
            if (!visible) {
                this.setSelected(false);
                this.setFocused(false);
                return EGW_AO_STATE_NORMAL;
            } else {
                // Auto-register the actions attached to this object
                this.registerActions();
            }
        }

        // Remove the focus from all children on the same level
        if (this.parent && visible && _changedBit == EGW_AO_STATE_SELECTED) {
            selected = egwBitIsSet(_newState, EGW_AO_STATE_SELECTED);
            let objs = [];

            if (selected) {
                // Deselect all other objects inside this container, if the "MULTI" shift-state is not set
                if (!egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_MULTI)) {
                    this.getContainerRoot().setAllSelected(false);
                }

                // If the LIST state is active, get all objects in between this one and the focused one
                // and set their select state.
                if (egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_BLOCK)) {
                    const focused = this.getFocusedObject();
                    if (focused) {
                        objs = this.traversePath(focused);
                        for (let i = 0; i < objs.length; i++) {
                            objs[i].setSelected(true);
                        }
                    }
                }
            }

            // If the focused element didn't belong to this container, or the "list"
            // shift-state isn't active, set the focus to this element.
            if (objs.length == 0 || !egwBitIsSet(_shiftState, EGW_AO_SHIFT_STATE_BLOCK)) {
                this.setFocused(true);
                _newState = egwSetBit(EGW_AO_STATE_FOCUSED, _newState, true);
            }

            this.setSelected(selected);
        }

        return _newState;
    };

    /**
     * Handler for key presses
     *
     * @param {number} _keyCode
     * @param {boolean} _shift
     * @param {boolean} _ctrl
     * @param {boolean} _alt
     * @returns {boolean}
     */
    handleKeyPress(_keyCode, _shift, _ctrl, _alt) {
        switch (_keyCode) {
            case EGW_KEY_ARROW_UP:
            case EGW_KEY_ARROW_DOWN:
            case EGW_KEY_PAGE_UP:
            case EGW_KEY_PAGE_DOWN:

                if (!_alt) {
                    const intval = (_keyCode == EGW_KEY_ARROW_UP || _keyCode == EGW_KEY_ARROW_DOWN) ? 1 : 10;

                    if (this.children.length > 0) {
                        // Get the focused object
                        const focused = this.getFocusedObject();

                        // Determine the object which should get selected
                        let selObj = null;
                        if (!focused) {
                            selObj = this.children[0];
                        } else {
                            selObj = (_keyCode == EGW_KEY_ARROW_UP || _keyCode == EGW_KEY_PAGE_UP) ?
                                focused.getPrevious(intval) : focused.getNext(intval);
                        }

                        if (selObj != null) {
                            if (!_shift && !(this.parent && this.parent.data && this.parent.data.keyboard_select)) {
                                this.setAllSelected(false);
                            } else if (!(this.parent && this.parent.data && this.parent.data.keyboard_select)) {
                                const objs = focused.traversePath(selObj);
                                for (let i = 0; i < objs.length; i++) {
                                    objs[i].setSelected(true);
                                }
                            }

                            if (!(this.parent.data && this.parent.data.keyboard_select)) {
                                selObj.setSelected(true);
                            }
                            selObj.setFocused(true);

                            // Tell the aoi of the object to make it visible
                            selObj.makeVisible();
                        }

                        return true;
                    }
                }

                break;

            // Space bar toggles selected for current row
            case EGW_KEY_SPACE:
                if (this.children.length <= 0) {
                    break;
                }
                // Mark that we're selecting by keyboard, or arrows will reset selection
                if (!this.parent.data) {
                    this.parent.data = {};
                }
                this.parent.data.keyboard_select = true;

                // Get the focused object
                const focused = this.getFocusedObject();

                focused.setSelected(!focused.getSelected());

                // Tell the aoi of the object to make it visible
                focused.makeVisible();
                return true;

            // Handle CTRL-A to select all elements in the current container
            case EGW_KEY_A:
                if (_ctrl && !_shift && !_alt) {
                    this.toggleAllSelected();
                    return true;
                }

                break;
        }

        return false;
    };

    getPrevious(_intval) {
        if (this.parent != null) {
            if (this.getFocused() && !this.getSelected()) {
                return this;
            }

            const flatTree = this.getContainerRoot().flatList();

            let idx = flatTree.indexOf(this);
            if (idx > 0) {
                idx = Math.max(1, idx - _intval);
                return flatTree[idx];
            }
        }

        return this;
    };


    getNext(_intval) {
        if (this.parent != null) {
            if (this.getFocused() && !this.getSelected()) {
                return this;
            }

            const flatTree = this.getContainerRoot().flatList(true);

            let idx = flatTree.indexOf(this);
            if (idx < flatTree.length - 1) {
                idx = Math.min(flatTree.length - 1, idx + _intval);
                return flatTree[idx];
            }
        }

        return this;
    };

    /**
     * Returns whether the object is currently selected.
     */

    getSelected() {
        return egwBitIsSet(this.getState(), EGW_AO_STATE_SELECTED);
    };

    /**
     * Returns whether the object is currently focused.
     */

    getFocused() {
        return egwBitIsSet(this.getState(), EGW_AO_STATE_FOCUSED);
    };

    /**
     * Returns whether the object currently is visible - visible means, that the
     * AOI has a dom node and is visible.
     */

    getVisible() {
        return egwBitIsSet(this.getState(), EGW_AO_STATE_VISIBLE);
    };

    /**
     * Returns the complete state of the object.
     */

    getState() {
        return this.iface.getState();
    };


    /**
     * Sets the focus of the element. The formerly focused element in the tree will
     * be de-focused.
     *
     * @param {boolean} _focused - whether to remove or set the focus. Defaults to true
     */

    setFocused(_focused) {
        if (typeof _focused == "undefined") _focused = true;

        const state = this.iface.getState();

        if (egwBitIsSet(state, EGW_AO_STATE_FOCUSED) != _focused) {
            // Un-focus the currently focused object
            const currentlyFocused = this.getFocusedObject();
            if (currentlyFocused && currentlyFocused != this) {
                currentlyFocused.setFocused(false);
            }

            this.iface.setState(egwSetBit(state, EGW_AO_STATE_FOCUSED, _focused));
            if (this.parent) {
                this.parent.updateFocusedChild(this, _focused);
            }
        }

        if (this.focusedChild != null && _focused == false) {
            this.focusedChild.setFocused(false);
        }
    };

    /**
     * Sets the selected state of the element.
     *
     * @param {boolean} _selected
     * @TODO Callback
     */

    setSelected(_selected) {
        const state = this.iface.getState();

        if ((egwBitIsSet(state, EGW_AO_STATE_SELECTED) != _selected) && egwBitIsSet(state, EGW_AO_STATE_VISIBLE)) {
            this.iface.setState(egwSetBit(state, EGW_AO_STATE_SELECTED, _selected));
            if (this.parent) {
                this.parent.updateSelectedChildren(this, _selected || this.selectedChildren.length > 0);
            }
        }
    };

    /**
     * Sets the selected state of all elements, including children
     *
     * @param {boolean} _selected
     * @param {boolean} _informParent
     */

    setAllSelected(_selected, _informParent = true) {

        const state = this.iface.getState();

        // Update this element
        if (egwBitIsSet(state, EGW_AO_STATE_SELECTED) != _selected) {
            this.iface.setState(egwSetBit(state, EGW_AO_STATE_SELECTED, _selected));
            if (_informParent && this.parent) {
                this.parent.updateSelectedChildren(this, _selected);
            }
            if (this.parent?.data && this.parent?.data?.keyboard_select) {
                this.parent.data.keyboard_select = false;
            }
        }

        // Update the children if the should be selected or if they should be
        // deselected and there are selected children.
        if (_selected || this.selectedChildren.length > 0) {
            for (let i = 0; i < this.children.length; i++) {
                this.children[i].setAllSelected(_selected, false);
            }
        }

        // Copy the selected children list
        this.selectedChildren = [];
        if (_selected) {
            for (let i = 0; i < this.children.length; i++) {
                this.selectedChildren.push(this.children[i]);
            }
        }

        // Call the setSelectedCallback
        egwQueueCallback(this.setSelectedCallback, [], this, "setSelectedCallback");
    };


    /**
     * Updates the selectedChildren array each actionObject has in order to determine
     * all selected children in a very fast manner.
     *
     * @param {(string|egwActionObject} _child
     * @param {boolean} _selected
     * @todo Has also to be updated, if an child is added/removed!
     */

    updateSelectedChildren(_child, _selected) {
        const id: number = this.selectedChildren.indexOf(_child); // TODO Replace by binary search, insert children sorted by index!
        const wasEmpty: boolean = this.selectedChildren.length == 0;

        // Add or remove the given child from the selectedChildren list
        if (_selected && id == -1) {
            this.selectedChildren.push(_child);
        } else if (!_selected && id != -1) {
            this.selectedChildren.splice(id, 1);
        }

        // If the emptieness of the selectedChildren array has changed, update the
        // parent selected children array.
        if (wasEmpty != (this.selectedChildren.length == 0) && this.parent) {
            this.parent.updateSelectedChildren(this, wasEmpty);
        }

        // Call the setSelectedCallback
        egwQueueCallback(this.setSelectedCallback, this.getContainerRoot().getSelectedObjects(), this, "setSelectedCallback");
    };

    /**
     * Updates the focusedChild up to the container boundary.
     *
     * @param {(string|egwActionObject} _child
     * @param {boolean} _focused
     */

    updateFocusedChild(_child, _focused) {
        if (_focused) {
            this.focusedChild = _child;
        } else {
            if (this.focusedChild == _child) {
                this.focusedChild = null;
            }
        }

        if (this.parent /*&& !egwBitIsSet(this.flags, EGW_AO_FLAG_IS_CONTAINER)*/) {
            this.parent.updateFocusedChild(_child, _focused);
        }
    };

    /**
     * Updates the actionLinks of the given ActionObject.
     *
     * @param {array} _actionLinks contains the information about the actionLinks which
     *    should be updated as an array of objects. Example
     *    [
     *        {
     * 			"actionId": "file_delete",
     * 			"enabled": true
     * 		}
     *    ]
     *    string[] or {actionID:string,enabled:boolean}[]
     *    If an supplied link doesn't exist yet, it will be created (if _doCreate is true)
     *    and added to the list. Otherwise the information will just be updated.
     * @param {boolean} _recursive If true, the settings will be applied to all child
     *    object (default false)
     * @param {boolean} _doCreate If true, not yet existing links will be created (default true)
     */

    updateActionLinks(_actionLinks: string[] | {
        actionId: string,
        enabled: boolean
    }[], _recursive: boolean = false, _doCreate: boolean = true) {
        for (let elem of _actionLinks) {

            // Allow single strings for simple action links.
            if (typeof elem == "string") {
                elem = {
                    actionId: elem,
                    enabled: true
                };
            }

            if (typeof elem.actionId != "undefined" && elem.actionId) {
                //Get the action link object, if it doesn't exist yet, create it
                let actionLink = this.getActionLink(elem.actionId);
                if (!actionLink && _doCreate) {
                    actionLink = new egwActionLink(this.manager);
                    this.actionLinks.push(actionLink);
                }

                //Set the supplied data
                if (actionLink) {
                    actionLink.updateLink(elem);
                }
            }
        }

        if (_recursive) {
            for (let i = 0; i < this.children.length; i++) {
                this.children[i].updateActionLinks(_actionLinks, true, _doCreate);
            }
        }

        if (this.getVisible() && this.iface != null) {
            this.registerActions();
        }
    };

    /**
     * Reconnects the actions.
     */

    _reconnectCallback() {
        this.registeredImpls = [];
        this.registerActions();
    };

    /**
     * Registers the action implementations inside the DOM-Tree.
     */
    registerActions() {
        const groups = this.getActionImplementationGroups();

        for (const group in groups) {
            // Get the action implementation for each group
            if (typeof window._egwActionClasses[group] != "undefined" && window._egwActionClasses[group].implementation && this.iface) {
                const impl = window._egwActionClasses[group].implementation();

                if (this.registeredImpls.indexOf(impl) == -1) {
                    // Register a handler for that action with the iface of that object,
                    // the callback and this object as context for the callback
                    if (impl.registerAction(this.iface, this.executeActionImplementation, this)) {
                        this.registeredImpls.push(impl);
                    }
                }
            }
        }
    };

    /**
     * Unregisters all action implementations registered to this element
     */
    unregisterActions() {
        while (this.registeredImpls.length > 0) {
            const impl = this.registeredImpls.pop();
            if (this.iface) {
                impl.unregisterAction(this.iface);
            }
        }
    };

    protected triggerCallback(): boolean {
        if (this.onBeforeTrigger) {
            return this.onBeforeTrigger()
        }
        return true;
    }

    makeVisible() {
        this.iface.makeVisible();
    };


    /**
     * Executes the action implementation which is associated to the given action type.
     *
     * @param {object} _implContext is data which should be delivered to the action implementation.
     *    E.g. in case of the popup action implementation, the x and y coordinates where the
     *    menu should open, and contextmenu event are transmitted.
     * @param {string} _implType is the action type for which the implementation should be
     *    executed.
     * @param {number} _execType specifies in which context the execution should take place.
     *    defaults to EGW_AO_EXEC_SELECTED
     */

    executeActionImplementation(_implContext, _implType, _execType) {
        if (typeof _execType == "undefined") {
            _execType = EGW_AO_EXEC_SELECTED;
        }

        if (typeof _implType == "string") {
            _implType = window._egwActionClasses[_implType].implementation();
        }

        if (typeof _implType == "object" && _implType) {
            let selectedActions;
            if (_execType == EGW_AO_EXEC_SELECTED) {
                if (!(egwBitIsSet(EGW_AO_FLAG_IS_CONTAINER, this.flags))) {
                    this.forceSelection();
                }
                selectedActions = this.getSelectedLinks(_implType.type);
            } else if (_execType == EGW_AO_EXEC_THIS) {
                selectedActions = this._getLinks([this], _implType.type);
            }

            if (selectedActions.selected.length > 0 && egwObjectLength(selectedActions.links) > 0) {
                return _implType.executeImplementation(_implContext, selectedActions.selected, selectedActions.links);
            }
        }

        return false;
    };

    /**
     * Forces the object to be inside the currently selected objects. If this is
     * not the case, the object will select itself and deselect all other objects.
     */

    forceSelection() {
        const selected = this.getContainerRoot().getSelectedObjects();

        // Check whether this object is in the list
        const thisInList: boolean = selected.indexOf(this) != -1;

        // If not, select it
        if (!thisInList) {
            this.getContainerRoot().setAllSelected(false);
            this.setSelected(true);
        }

        this.setFocused(true);
    };

    /**
     * Returns all selected objects, and all action links of those objects, which are
     * of the given implementation type, wheras actionLink properties such as
     * "enabled" and "visible" are accumulated.
     *
     * Objects have the chance to change their action links or to deselect themselves
     * in the onBeforeTrigger event, which is evaluated by the triggerCallback function.
     *
     * @param _actionType is the action type for which the actionLinks should be collected.
     * @returns object An object which contains a "links" and a "selected" section with
     *    an array of links/selected objects-
     */

    getSelectedLinks(_actionType) {
        // Get all objects in this container which are currently selected
        const selected = this.getContainerRoot().getSelectedObjects();

        return this._getLinks(selected, _actionType);
    };

    /**
     *
     * @param {array} _objs
     * @param {string} _actionType
     * @return {object} with attributes "selected" and "links"
     */

    _getLinks(_objs, _actionType) {
        const actionLinks = {};
        const testedSelected = [];

        const test = function (olink) {
            // Test whether the action type is of the given implementation type
            if (olink.actionObj.type == _actionType) {
                if (typeof actionLinks[olink.actionId] == "undefined") {
                    actionLinks[olink.actionId] = {
                        "actionObj": olink.actionObj,
                        "enabled": (testedSelected.length == 1),
                        "visible": false,
                        "cnt": 0
                    };
                }

                // Accumulate the action link properties
                const llink = actionLinks[olink.actionId];
                llink.enabled = llink.enabled && olink.actionObj.enabled.exec(olink.actionObj, _objs, _objs[i]) && olink.enabled && olink.visible;
                llink.visible = (llink.visible || olink.visible);
                llink.cnt++;

                // Add in children, so they can get checked for visible / enabled
                if (olink.actionObj && olink.actionObj.children.length > 0) {
                    for (let j = 0; j < olink.actionObj.children.length; j++) {
                        const child = olink.actionObj.children[j];
                        test({
                            actionObj: child, actionId: child.id, enabled: olink.enabled, visible: olink.visible
                        });
                    }
                }
            }
        };

        for (var i = 0; i < _objs.length; i++) {
            const obj = _objs[i];
            if (!egwBitIsSet(obj.flags, EGW_AO_FLAG_IS_CONTAINER) && obj.triggerCallback()) {
                testedSelected.push(obj);

                for (var j = 0; j < obj.actionLinks.length; j++) {
                    test(obj.actionLinks[j]); //object link
                }
            }
        }

        // Check whether all objects supported the action
        for (let k in actionLinks) {
            actionLinks[k].enabled = actionLinks[k].enabled && (actionLinks[k].cnt >= testedSelected.length) && ((actionLinks[k].actionObj.allowOnMultiple === true) || (actionLinks[k].actionObj.allowOnMultiple == "only" && _objs.length > 1) || (actionLinks[k].actionObj.allowOnMultiple == false && _objs.length === 1) || (typeof actionLinks[k].actionObj.allowOnMultiple === 'number' && _objs.length == actionLinks[k].actionObj.allowOnMultiple));
            if (!window.egwIsMobile()) actionLinks[k].actionObj.hideOnMobile = false;
            actionLinks[k].visible = actionLinks[k].visible && !actionLinks[k].actionObj.hideOnMobile && (actionLinks[k].enabled || !actionLinks[k].actionObj.hideOnDisabled);
        }

        // Return an object which contains the accumulated actionLinks and all selected
        // objects.
        return {
            "selected": testedSelected, "links": actionLinks
        };
    };

    /**
     * Returns the action link, which contains the association to the action with
     * the given actionId.
     *
     * @param {string} _actionId name of the action associated to the link
     */

    getActionLink(_actionId: string) {
        for (let i = 0; i < this.actionLinks.length; i++) {
            if (this.actionLinks[i].actionObj?.id == _actionId) {
                return this.actionLinks[i];
            }
        }

        return null;
    };

    /**
     * Returns all actions associated to the object tree, grouped by type.
     *
     * @param {function} _test gets an egwActionObject and should return, whether the
     *    actions of this object are added to the result. Defaults to a "always true"
     *    function.
     * @param {object} _groups is an internally used parameter, may be omitted.
     */

    getActionImplementationGroups(_test?, _groups?) {
        // If the _groups parameter hasn't been given preset it to an empty object
        // (associative array).
        if (typeof _groups == "undefined") _groups = {};
        if (typeof _test == "undefined") _test = function (_obj) {
            return true;
        };

        this.actionLinks.forEach(item => {
            const action = item.actionObj;
            if (typeof action != "undefined" && _test(this)) {
                if (typeof _groups[action.type] == "undefined") {
                    _groups[action.type] = [];
                }

                _groups[action.type].push({
                    "object": this, "link": item
                });
            }
        });

        // Recursively add the actions of the children to the result (as _groups is
        // an object, only the reference is passed).
        this.children.forEach(item => {
            item.getActionImplementationGroups(_test, _groups);
        });

        return _groups;
    };

    /**
     * Check if user tries to get dragOut action
     *
     * keys for dragOut:
     *    -Mac: Command + Shift
     *    -Others: Alt + Shift
     *
     * @param {event} _event
     * @return {boolean} return true if Alt+Shift keys and left mouse click arre pressed, otherwise false
     */

    isDragOut(_event) {
        return (_event.altKey || _event.metaKey) && _event.shiftKey && _event.which == 1;
    };

    /**
     * Check if user tries to get selection action
     *
     * Keys for selection:
     *    -Mac: Command key
     *    -Others: Ctrl key
     *
     * @param {type} _event
     * @returns {Boolean} return true if left mouse click and Ctrl/Alt key are pressed, otherwise false
     */

    isSelection(_event) {
        return !(_event.shiftKey) && _event.which == 1 && (_event.metaKey || _event.ctrlKey || _event.altKey);
    };

}

/** egwActionObjectInterface Interface **/

/**
 * The egwActionObjectInterface has to be implemented for each actual object in
 * the browser. E.g. for the object "DataGridRow", there has to be an
 * egwActionObjectInterface which is responsible for returning the outer DOMNode
 * of the object to which JS-Events may be attached by the EgwActionImplementation
 * object, and to do object specific stuff like highlighting the object in the
 * correct way and to route state changes (like: "object has been selected")
 * to the egwActionObject object the interface is associated to.
 *
 * @return {egwActionObjectInterface}
 */
export interface EgwActionObjectInterface {
    //properties
    _state: number;
    stateChangeCallback: Function;
    stateChangeContext: any;
    reconnectActionsCallback: Function;
    reconnectActionsContext: any;

    //functions
    /**
     * Sets the callback function which will be called when a user interaction changes
     * state of the object.
     *
     * @param {function} _callback
     * @param {object} _context
     */
    setStateChangeCallback(_callback: Function, _context: any): void;

    /**
     * Sets the reconnectActions callback, which will be called by the AOI if its
     * DOM-Node has been replaced and the actions have to be re-registered.
     *
     * @param {function} _callback
     * @param {object} _context
     */
    setReconnectActionsCallback(_callback: Function, _context: any): void;

    /**
     * Will be called by the aoi if the actions have to be re-registered due to a
     * DOM-Node exchange.
     */
    reconnectActions(): void;

    /**
     * Internal function which should be used whenever the select status of the object
     * has been changed by the user. This will automatically calculate the new state of
     * the object and call the stateChangeCallback (if it has been set)
     *
     * @param {number} _stateBit is the bit in the state bit which should be changed
     * @param {boolean} _set specifies whether the state bit should be set or not
     * @param {boolean} _shiftState
     */
    updateState(_stateBit: number, _set: boolean, _shiftState: boolean): void;


    /**
     * Returns the DOM-Node the ActionObject is actually a representation of.
     * Calls the internal "doGetDOMNode" function, which has to be overwritten
     * by implementations of this class.
     */
    getDOMNode(): Element;

    setState(_state: any): void;

    getState(): number;

    triggerEvent(_event: any, _data: any): boolean;

    /**
     * Scrolls the element into a visble area if it is currently hidden
     */
    makeVisible(): void;
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

    doGetDOMNode = function () {
        return null;
    };

    // _outerCall may be used to determine, whether the state change has been
    // evoked from the outside and the stateChangeCallback has to be called
    // or not.
    doSetState = function (_state) {
    };

    // The doTiggerEvent function may be overritten by the aoi if it wants to
    // support certain action implementation specific events like EGW_AI_DRAG_OVER
    // or EGW_AI_DRAG_OUT
    doTriggerEvent = function (_event, _data) {
        return false;
    };

    doMakeVisible = function () {
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


/** -- egwActionObjectDummyInterface Class -- **/

const egwActionObjectDummyInterface = egwActionObjectInterface;

/** egwActionObjectManager Object **/

/**
 * The egwActionObjectManager is a dummy class which only contains a dummy
 * AOI. It may be used as root object or as object containers.
 *
 * @param {string} _id
 * @param {string} _manager
 * @return {egwActionObjectManager}
 */
export class egwActionObjectManager extends egwActionObject {
    constructor(_id: string, _manager: any) {
        const aoi = new egwActionObjectInterface();
        //const ao = new egwActionObject(_id, null, aoi, _manager, EGW_AO_FLAG_IS_CONTAINER)
        super(_id, null, aoi, _manager, EGW_AO_FLAG_IS_CONTAINER);
        this.triggerCallback = function () {
            return false;
        }
    }

}

