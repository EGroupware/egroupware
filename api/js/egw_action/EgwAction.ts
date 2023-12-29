/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {egwActionStoreJSON, EgwFnct} from "./egw_action_common";
import {IegwAppLocal} from "../jsapi/egw_global";
import {egw_getObjectManager} from "./egw_action";

export class EgwAction {
    public id: string;
    public caption: string;
    group: number;
    order: number;

    public set_caption(_value) {
        this.caption = _value;
    }

    public iconUrl: string;

    public set_iconUrl(_value) {
        this.iconUrl = _value;
    }

    allowOnMultiple: boolean | string | number;

    /**
     * The allowOnMultiple property may be true, false, "only" (> 1) or number of select, e.g. 2
     *
     * @param {(boolean|string|number)} _value
     */
    public set_allowOnMultiple(_value: boolean | string | number) {
        this.allowOnMultiple = _value
    }

    public enabled: EgwFnct;

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
    readonly parent: EgwAction;
    children: EgwAction[] = []; //i guess

    public onExecute = new EgwFnct(this, null, []);

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
     **/
    constructor(_parent: EgwAction, _id: string, _caption: string = "", _iconUrl: string = "", _onExecute: string | Function = null, _allowOnMultiple: boolean = true) {
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
    public getActionById(_id: string, _search_depth: number = Number.MAX_VALUE): EgwAction {
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

    public addAction(_type: string, _id: string, _caption: string = "", _iconUrl: string = "", _onExecute: string | Function = null, _allowOnMultiple: boolean = true): EgwAction {
        //Get the constructor for the given action type
        if (!(_type in window._egwActionClasses)) {
            _type = "popup"
        }

        // Only allow adding new actions, if this action class allows it.
        if (this.canHaveChildren) {
            const constructor: any = window._egwActionClasses[_type]?.actionConstructor;

            if (typeof constructor == "function") {
                const action: EgwAction = new constructor(this, _id, _caption, _iconUrl, _onExecute, _allowOnMultiple);
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
            const localEgw: IegwAppLocal = window.egw(_app, window);
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
    public not_disableClass(_action: EgwAction, _senders: any, _target: any) {
        if (_target.iface.getDOMNode()) {
            return !(_target.iface.getDOMNode()).classList.contains(_action.data.disableClass);
        } else if (_target.id) {
            // Checking on a something that doesn't have a DOM node, like a nm row
            // that's not currently rendered
            const data = window.egw.dataGetUIDdata(_target.id);
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
    public enableClass(_action: EgwAction, _senders: any[], _target: any) {
        if (typeof _target == 'undefined') {
            return false;
        } else if (_target.iface.getDOMNode()) {
            return (_target.iface.getDOMNode()).classList.contains(_action.data.enableClass);
        } else if (_target.id) {
            // Checking on a something that doesn't have a DOM node, like a nm row
            // that's not currently rendered.  Not as good as an actual DOM node check
            // since things can get missed, but better than nothing.
            const data = window.egw.dataGetUIDdata(_target.id);
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
    public enableId(_action: EgwAction, _senders: any[], _target: any) {
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
    public _check_confirm_mass_selections(_senders, _target) {
        const obj_manager: any = egw_getObjectManager(this.getManager().parent.id, false);
        if (!obj_manager) {
            return false;
        }

        // Action needs to care about mass selection - check for parent that cares too
        let confirm_mass_needed = false;
        let action: EgwAction = this;
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
            const msg = window.egw.lang(check.confirm_mass_selection, obj_manager.getAllSelected() ? window.egw.lang('all') : _senders.length);
            const callback = (_button) => {
                // YES = unchecked, NO = checked
                check.set_checked(_button === window.Et2Dialog.NO_BUTTON);
                if (_button !== window.Et2Dialog.CANCEL_BUTTON) {
                    this._check_confirm(_senders, _target);
                }
            };
            window.Et2Dialog.show_dialog(callback, msg, this.data.hint, {}, window.Et2Dialog.BUTTONS_YES_NO_CANCEL, window.Et2Dialog.QUESTION_MESSAGE);
            return true;
        }
        return false;
    };


    /**
     * Check to see if action needs to be confirmed by user before we do it
     */
    public _check_confirm(_senders, _target) {
        // check if actions needs to be confirmed first
        if (this.data && (this.data.confirm || this.data.confirm_multiple) &&
            this.onExecute.functionToPerform != window.nm_action && typeof window.Et2Dialog != 'undefined')	// let old eTemplate run its own confirmation from nextmatch_action.js
        {
            let msg = this.data.confirm || '';
            if (_senders.length > 1) {
                if (this.data.confirm_multiple) {
                    msg = this.data.confirm_multiple;
                }
                // check if we have all rows selected
                const obj_manager = egw_getObjectManager(this.getManager().parent.id, false);
                if (obj_manager && obj_manager.getAllSelected()) {
                    msg += "\n\n" + window.egw.lang('attention: action will be applied to all rows, not only visible ones!');
                }
            }
            //no longer needed because of '=>' notation
            //var self = this;
            if (msg.trim().length > 0) {
                if (this.data.policy_confirmation && window.egw.app('policy')) {
                    import(window.egw.link('/policy/js/app.min.js')).then(() => {
                            if (typeof window.app.policy === 'undefined' || typeof window.app.policy.confirm === 'undefined') {
                                window.app.policy = new window.app.classes.policy();
                            }
                            window.app.policy.confirm(this, _senders, _target);
                        }
                    );
                    return;
                }
                window.Et2Dialog.show_dialog((_button) => {
                    if (_button == window.Et2Dialog.YES_BUTTON) {
                        // @ts-ignore
                        return this.onExecute.exec(this, _senders, _target);
                    }
                }, msg, this.data.hint, {}, window.Et2Dialog.BUTTONS_YES_NO, window.Et2Dialog.QUESTION_MESSAGE);
                return;
            }
        }
        // @ts-ignore
        return this.onExecute.exec(this, _senders, _target);
    };


    public updateAction(_data: Object) {
        egwActionStoreJSON(_data, this, "data")
    }

    /**
     * Returns the parent action manager
     */
    getManager(): EgwAction {
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
     * @param {not an array} _tree contains the tree structure - pass an object containing {root:Tree}
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


    set_hint(hint: string) {
        
    }
    public clone():EgwAction{
        const clone:EgwAction = Object.assign(Object.create(Object.getPrototypeOf(this)), this)
        clone.onExecute = this.onExecute.clone()
        if(this.enabled){
            clone.enabled = this.enabled.clone()
        }
        return clone
    }
}


type TreeElem = { action: EgwAction, children: Tree }
type Tree = TreeElem[]

/**
 * finds an egwAction in the given tree
 * @param {Tree}_tree where to search
 * @param {EgwAction}_elem elem to search
 * @returns {TreeElem} the treeElement for corresponding _elem if found, null else
 */
function _egwActionTreeFind(_tree: Tree, _elem: EgwAction): TreeElem {
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
