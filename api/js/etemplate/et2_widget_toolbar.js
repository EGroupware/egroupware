"use strict";
/**
 * EGroupware eTemplate2 - JS toolbar object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
 * @version $Id$
 */
var __extends = (this && this.__extends) || (function () {
    var extendStatics = function (d, b) {
        extendStatics = Object.setPrototypeOf ||
            ({ __proto__: [] } instanceof Array && function (d, b) { d.__proto__ = b; }) ||
            function (d, b) { for (var p in b) if (b.hasOwnProperty(p)) d[p] = b[p]; };
        return extendStatics(d, b);
    };
    return function (d, b) {
        extendStatics(d, b);
        function __() { this.constructor = d; }
        d.prototype = b === null ? Object.create(b) : (__.prototype = b.prototype, new __());
    };
})();
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    /vendor/bower-asset/jquery-ui/jquery-ui.js;
    et2_DOMWidget;
*/
var et2_core_DOMWidget_1 = require("./et2_core_DOMWidget");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
require("../egw_action/egw_action.js");
var et2_widget_dialog_1 = require("./et2_widget_dialog");
/**
 * This toolbar gets its contents from its actions
 *
 * @augments et2_valueWidget
 */
var et2_toolbar = /** @class */ (function (_super) {
    __extends(et2_toolbar, _super);
    function et2_toolbar(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_toolbar._attributes, _child || {})) || this;
        /**
         * id of last action executed / value of toolbar if submitted
         */
        _this.value = null;
        /**
         * actionbox is a div for stored actions
         */
        _this.actionbox = null;
        /**
         * actionlist is a div for active actions
         */
        _this.actionlist = null;
        _this.div = null;
        _this.countActions = 0;
        _this.dropdowns = {};
        _this.preference = {};
        _this.menu = null;
        _this._objectManager = null;
        _this.div = jQuery(document.createElement('div'))
            .addClass('et2_toolbar ui-widget-header ui-corner-all');
        // Set proper id and dom_id for the widget
        _this.set_id(_this.id);
        _this.actionbox = jQuery(document.createElement('div'))
            .addClass("et2_toolbar_more")
            .attr('id', _this.id + '-' + 'actionbox');
        _this.actionlist = jQuery(document.createElement('div'))
            .addClass("et2_toolbar_actionlist")
            .attr('id', _this.id + '-' + 'actionlist');
        _this.countActions = 0;
        _this.dropdowns = {};
        _this.preference = {};
        _this._build_menu(et2_toolbar.default_toolbar, true);
        return _this;
    }
    et2_toolbar.prototype.destroy = function () {
        // Destroy widget
        if (this.div && this.div.data('ui-menu'))
            this.menu.menu("destroy");
        // Null children
        // Remove
        this.div.empty().remove();
        this.actionbox.empty().remove();
        this.actionlist.empty().remove();
    };
    /**
     * Fix function in order to fix toolbar preferences with the new preference structure
     * @param {action object} _action
     * @todo ** SEE IMPORTANT TODO **
     */
    et2_toolbar.prototype._fix_preference = function (_action) {
        // ** IMPORTANT TODO: This switch case should be removed for new release **
        // This is an ugly hack but we need to add this switch becuase to update and fix
        // current users toolbar preferences with the new structure which is:
        // - All actions should be stored in preference
        // - Actions inside menu set as true
        // - Actions outside menu set as false
        // - if an action gets added to toolbar it would be undefined in
        //  the preference which we need to consider to add it to the preference
        //  according to its toolbarDefault option.
        if (this.dom_id === 'mail-display_displayToolbar' || this.dom_id === 'mail-index_toolbar') {
            switch (_action.id) {
                // Actions newly added to mail index and display toolbar
                case 'read':
                case 'label1':
                case 'label2':
                case 'label3':
                case 'label4':
                case 'label5':
                    this.set_prefered(_action.id, !_action.toolbarDefault);
                    break;
                default:
                    // Fix structure and add the actions not the preference
                    // into the preference with value false, as they're already
                    // outside of the menu.
                    this.set_prefered(_action.id, false);
            }
        }
        else {
            // ** IMPORTANT TODO: This line needs to stay and be fixed with !toolbarDefault after the if condition
            // has been removed.
            this.set_prefered(_action.id, false /*!toolbarDefault*/);
        }
    };
    /**
     * Count number of actions including their children
     * @param {object} actions
     * @return {number} return total number of actions
     */
    et2_toolbar.prototype._countActions = function (actions) {
        var totalCount = 0;
        var childCounter = function (action, count) {
            var children = action.children || 0, returnCounter = count || 0;
            if (children) {
                returnCounter -= 1;
                for (var nChild in children) {
                    returnCounter += 1;
                    returnCounter = childCounter(children[nChild], returnCounter);
                }
            }
            else {
                returnCounter = count;
            }
            return returnCounter;
        };
        for (var nAction in actions) {
            if (this.options.flat_list) {
                totalCount += childCounter(actions[nAction], 1);
            }
            else {
                totalCount++;
            }
        }
        return totalCount;
    };
    /**
     * Go through actions and build buttons for the toolbar
     *
     * @param {Object} actions egw-actions to build menu from
     * @param {boolean} isDefault setting isDefault with true will
     *  avoid actions get into the preferences, for instandce, first
     *  time toolbar_default actions initialization.
     */
    et2_toolbar.prototype._build_menu = function (actions, isDefault) {
        // Clear existing
        this.div.empty();
        this.actionbox.empty();
        this.actionlist.empty();
        var admin_setting = this.options.is_admin ? '<span class="toolbar-admin-pref" title="' + egw.lang('Admin settings') + ' ..."></span>' : '';
        this.actionbox.append('<h class="ui-toolbar-menulistHeader">' + egw.lang('more') + ' ...' + admin_setting + '</h>');
        this.actionbox.append('<div id="' + this.id + '-menulist' + '" class="ui-toolbar-menulist" ></div>');
        var that = this;
        if (this.options.is_admin) {
            this.actionbox.find('.toolbar-admin-pref').click(function (e) {
                e.stopImmediatePropagation();
                egw.json('EGroupware\\Api\\Etemplate\\Widget\\Toolbar::ajax_get_default_prefs', [egw.app_name(), that.dom_id], function (_prefs) {
                    var prefs = [];
                    for (var p in _prefs) {
                        if (_prefs[p] === false)
                            prefs.push(p);
                    }
                    that._admin_settings_dialog.call(that, actions, prefs);
                }).sendRequest(true);
            });
        }
        var pref = (!egwIsMobile()) ? egw.preference(this.dom_id, this.egw().app_name()) : undefined;
        if (pref && !jQuery.isArray(pref))
            this.preference = pref;
        //Set the default actions for the first time
        if (typeof pref === 'undefined' && !isDefault) {
            for (var name in actions) {
                if ((typeof actions[name].children === 'undefined' || !this.options.flat_list) && actions[name].id) {
                    this.set_prefered(actions[name].id, !actions[name].toolbarDefault);
                }
            }
        }
        else if (!isDefault) {
            for (var name in actions) {
                // Check if the action is not in the preference, means it's an new added action
                // therefore it needs to be added to the preference with taking its toolbarDefault
                // option into account.
                if ((typeof actions[name].children === 'undefined' || !this.options.flat_list)
                    && typeof pref[name] === 'undefined') {
                    this._fix_preference(actions[name]);
                }
            }
        }
        var menuLen = 0;
        for (var key in this.preference) {
            if (this.preference[key])
                menuLen++;
        }
        this.countActions = this._countActions(actions) - menuLen;
        var last_group = null;
        var last_group_id = null;
        var _loop_1 = function (name_1) {
            var action = actions[name_1];
            if (typeof action == 'string')
                action = { id: name_1, caption: action };
            if (typeof action.group == 'undefined') {
                action.group = 'default';
            }
            // Add in divider
            if (last_group_id != action.group) {
                last_group = jQuery('[data-group="' + action.group + '"]', this_1.actionlist);
                if (last_group.length == 0) {
                    jQuery('<span data-group="' + action.group + '">').appendTo(this_1.actionlist);
                }
                last_group_id = action.group;
            }
            // Make sure there's something to display
            if (!action.caption && !action.icon && !action.iconUrl)
                return "continue";
            if (action.children) {
                var children = {};
                var add_children_1 = function (root, children) {
                    for (var id in root.children) {
                        var info = {
                            id: id || root.children[id].id,
                            label: root.children[id].caption
                        };
                        var childaction = {};
                        if (root.children[id].iconUrl) {
                            info['icon'] = root.children[id].iconUrl;
                        }
                        if (root.children[id].children) {
                            add_children_1(root.children[id], info);
                        }
                        children[id] = info;
                        if (that.options.flat_list) {
                            childaction = root.children[id];
                            if (typeof pref === 'undefined' && !isDefault) {
                                if (!childaction['toolbarDefault']) {
                                    that.set_prefered(childaction['id'], true);
                                }
                                else {
                                    that.set_prefered(childaction['id'], false);
                                }
                            }
                            else if (!isDefault) {
                                if (typeof pref[childaction['id']] === 'undefined') {
                                    that._fix_preference(childaction);
                                }
                            }
                            if (typeof root.children[id].group !== 'undefined' &&
                                typeof root.group !== 'undefined') {
                                childaction['group'] = root.group;
                            }
                            that._make_button(childaction);
                        }
                    }
                };
                add_children_1(action, children);
                if (this_1.options.flat_list && children) {
                    return "continue";
                }
                var dropdown = et2_core_widget_1.et2_createWidget("dropdown_button", {
                    id: action.id
                }, this_1);
                dropdown.set_select_options(children);
                dropdown.set_label(action.caption);
                //Set default selected action
                if (typeof action.children != 'undefined') {
                    for (var child in action.children) {
                        if (action.children[child].default) {
                            dropdown.set_label(action.children[child].caption);
                        }
                    }
                }
                dropdown.set_image(action.iconUrl || '');
                dropdown.onchange = jQuery.proxy(function (selected, dropdown) {
                    var action = that._actionManager.getActionById(selected.attr('data-id'));
                    dropdown.set_label(action.caption);
                    if (action) {
                        this.value = action.id;
                        action.execute([]);
                    }
                    //console.debug(selected, this, action);
                }, action);
                dropdown.onclick = jQuery.proxy(function (selected, dropdown) {
                    var action = that._actionManager.getActionById(this.getValue());
                    if (action) {
                        this.value = action.id;
                        action.execute([]);
                    }
                    //console.debug(selected, this, action);
                }, dropdown);
                jQuery(dropdown.getDOMNode())
                    .attr('id', this_1.id + '-' + dropdown.id)
                    .addClass(this_1.preference[action.id] ? 'et2_toolbar-dropdown et2_toolbar-dropdown-menulist' : 'et2_toolbar-dropdown')
                    .appendTo(this_1.preference[action.id] ? this_1.actionbox.children()[1] : jQuery('[data-group=' + action.group + ']', this_1.actionlist));
            }
            else {
                this_1._make_button(action);
            }
        };
        var this_1 = this;
        for (var name_1 in actions) {
            _loop_1(name_1);
        }
        // ************** Drag and Drop feature for toolbar *****
        this.actionlist.find('span[data-group]').sort(function (lg, g) {
            return +lg.getAttribute('data-group') - +g.getAttribute('data-group');
        }).appendTo(this.actionlist);
        this.actionlist.appendTo(this.div);
        this.actionbox.appendTo(this.div);
        var toolbar = this.actionlist.find('span[data-group]').children(), toolbox = this.actionbox, menulist = jQuery(this.actionbox.children()[1]);
        toolbar.draggable({
            cancel: '',
            zIndex: 1000,
            delay: 500,
            //revert:"invalid",
            containment: "document",
            cursor: "move",
            helper: "clone",
            appendTo: 'body',
            stop: function (event, ui) {
                that._build_menu(actions);
            }
        });
        menulist.children().draggable({
            cancel: '',
            containment: "document",
            helper: "clone",
            appendTo: 'body',
            zIndex: 1000,
            cursor: "move",
            start: function () {
                jQuery(that.actionlist).addClass('et2_toolbarDropArea');
            },
            stop: function () {
                jQuery(that.actionlist).removeClass('et2_toolbarDropArea');
            }
        });
        toolbox.children().droppable({
            accept: toolbar,
            drop: function (event, ui) {
                that.set_prefered(ui.draggable.attr('id').replace(that.id + '-', ''), true);
                ui.draggable.appendTo(menulist);
                if (that.actionlist.find(".ui-draggable").length == 0) {
                    that.preference = {};
                    egw.set_preference(that.egw().app_name(), that.dom_id, that.preference);
                }
            },
            tolerance: "touch"
        });
        this.actionlist.droppable({
            tolerance: "pointer",
            drop: function (event, ui) {
                that.set_prefered(ui.draggable.attr('id').replace(that.id + '-', ''), false);
                ui.draggable.appendTo(that.actionlist);
                that._build_menu(actions);
            }
        });
        toolbox.accordion({
            heightStyle: "fill",
            collapsible: true,
            active: 'none',
            activate: function (event, ui) {
                var menubox = event.target;
                if (ui.oldHeader.length == 0) {
                    jQuery('html').on('click.outsideOfMenu', function (event) {
                        jQuery(menubox).accordion("option", "active", 2);
                        jQuery(this).unbind(event);
                        // Remove the focus class, user clicked elsewhere
                        jQuery(menubox).children().removeClass('ui-state-focus');
                    });
                }
            },
            create: function (event, ui) {
                jQuery('html').unbind('click.outsideOfMenu');
            },
            beforeActivate: function () {
                if (egwIsMobile()) {
                    menulist.height(screen.availHeight - 50);
                }
                else {
                    menulist.css({ height: 'inherit' });
                }
                // Nothing to show in menulist
                if (menulist.children().length == 0)
                    return false;
            }
        });
    };
    /**
     * Add/Or remove an action from prefence
     *
     * @param {string} _action name of the action which needs to be stored in pereference
     * @param {boolean} _state if set to true action will be set to actionbox, false will set it to actionlist
     *
     */
    et2_toolbar.prototype.set_prefered = function (_action, _state) {
        this.preference[_action] = _state;
        if (egwIsMobile())
            return;
        egw.set_preference(this.egw().app_name(), this.dom_id, this.preference);
    };
    /**
     * Make a button based on the given action
     *
     * @param {Object} action action object with attributes icon, caption, ...
     */
    et2_toolbar.prototype._make_button = function (action) {
        var button_options = {};
        var button = jQuery(document.createElement('button'))
            .addClass("et2_button et2_button_text et2_button_with_image")
            .attr('id', this.id + '-' + action.id)
            .attr('type', 'button')
            .appendTo(this.preference[action.id] ? this.actionbox.children()[1] : jQuery('[data-group=' + action.group + ']', this.actionlist));
        this.egw().tooltipBind(button, action.hint ? action.hint : action.caption) + (action.shortcut ? ' (' + action.shortcut.caption + ')' : '');
        if (action && action.checkbox) {
            if (action.data.toggle_on || action.data.toggle_off) {
                var toggle = et2_core_widget_1.et2_createWidget('checkbox', {
                    id: this.id + '-' + action.id,
                    toggle_on: action.data.toggle_on,
                    toggle_off: action.data.toggle_off
                }, this);
                toggle.doLoadingFinished();
                toggle.set_value(action.checked);
                action.data.widget = toggle;
                var toggle_div = toggle.toggle;
                toggle_div.appendTo(button.parent())
                    .attr('id', this.id + '-' + action.id);
                button.remove();
                button = toggle_div;
            }
            else {
                if (this.checkbox(action.id))
                    button.addClass('toolbar_toggled' + (typeof action.toggledClass != 'undefined' ? " " + action.toggledClass : ''));
            }
        }
        if (action.iconUrl) {
            button.attr('style', 'background-image:url(' + action.iconUrl + ')');
        }
        if (action.caption) {
            if ((this.countActions <= parseInt(this.options.view_range) ||
                this.preference[action.id] || !action.iconUrl) &&
                typeof button[0] !== 'undefined' &&
                !(action.checkbox && action.data && (action.data.toggle_on || action.data.toggle_off))) // no caption for slideswitch checkboxes
             {
                button.addClass(action.iconUrl ? 'et2_toolbar_hasCaption' : 'et2_toolbar_onlyCaption');
                button[0].textContent = action.caption;
            }
        }
        if (action.icon) {
            button_options['icon'] = action.icon;
        }
        if (!jQuery.isEmptyObject(button_options)) {
            button.button(button_options);
        }
        var self = this;
        // Set up the click action
        var click = function (e) {
            var action = this._actionManager.getActionById(e.data);
            if (action) {
                if (action.checkbox) {
                    self.checkbox(action.id, !action.checked);
                }
                this.value = action.id;
                action.data.event = e;
                action.execute([]);
            }
        };
        button.click(action.id, jQuery.proxy(click, this));
    };
    /**
     * Link the actions to the DOM nodes / widget bits.
     *
     * @param {Object} actions egw-actions to build menu from
     */
    et2_toolbar.prototype._link_actions = function (actions) {
        this._build_menu(actions);
        var self = this;
        var gom = egw_getObjectManager(this.egw().app_name(), true, 1);
        if (this._objectManager == null) {
            this._objectManager = gom.addObject(new egwActionObjectManager(this.id, this._actionManager));
            this._objectManager.handleKeyPress = function (_keyCode, _shift, _ctrl, _alt) {
                for (var i = 0; i < self._actionManager.children.length; i++) {
                    var action = self._actionManager.children[i];
                    if (typeof action.shortcut === 'object' &&
                        action.shortcut &&
                        _keyCode == action.shortcut.keyCode &&
                        _ctrl == action.shortcut.ctrl &&
                        _alt == action.shortcut.alt &&
                        _shift == action.shortcut.shift) {
                        self.value = action.id;
                        action.execute([]);
                        return true;
                    }
                }
                return egwActionObject.prototype.handleKeyPress.call(this, _keyCode, _shift, _ctrl, _alt);
            };
            this._objectManager.parent.updateFocusedChild(this._objectManager, true);
        }
    };
    /**
     * Set/Get the checkbox toolbar action
     *
     * @param {string} _action action name of the selected toolbar
     * @param {boolean} _value value that needs to be set for the action true|false
     *	- if no value means checkbox value returns the current value
     *
     * @returns {boolean} returns boolean result of get checkbox value
     * or returns undefined as Set result or failure
     */
    et2_toolbar.prototype.checkbox = function (_action, _value) {
        if (!_action || typeof this._actionManager == 'undefined')
            return undefined;
        var action_event = this._actionManager.getActionById(_action);
        if (action_event && typeof _value != 'undefined') {
            action_event.set_checked(_value);
            var btn = jQuery('#' + this.id + '-' + _action);
            if (action_event.data && action_event.data.widget) {
                action_event.data.widget.set_value(_value);
            }
            else if (btn.length > 0) {
                btn.toggleClass('toolbar_toggled' + (typeof action_event.data.toggledClass != 'undefined' ? " " + action_event.data.toggledClass : ''), _value);
            }
        }
        else if (action_event) {
            return action_event.checked;
        }
        else {
            return undefined;
        }
    };
    et2_toolbar.prototype.getDOMNode = function () {
        return this.div[0];
    };
    /**
     * getValue has to return the value of the input widget
     */
    et2_toolbar.prototype.getValue = function () {
        return this.value;
    };
    /**
     * Is dirty returns true if the value of the widget has changed since it
     * was loaded.  We don't consider toolbars as dirtyable
     */
    et2_toolbar.prototype.isDirty = function () {
        return false;
    };
    /**
     * Causes the dirty flag to be reseted.
     */
    et2_toolbar.prototype.resetDirty = function () {
        this.value = null;
    };
    /**
     * Checks the data to see if it is valid, as far as the client side can tell.
     * Return true if it's not possible to tell on the client side, because the server
     * will have the chance to validate also.
     *
     * The messages array is to be populated with everything wrong with the data,
     * so don't stop checking after the first problem unless it really makes sense
     * to ignore other problems.
     *
     * @param {String[]} messages List of messages explaining the failure(s).
     *	messages should be fairly short, and already translated.
     *
     * @return {boolean} True if the value is valid (enough), false to fail
     */
    et2_toolbar.prototype.isValid = function (messages) {
        return true;
    };
    /**
     * Attach the container node of the widget to DOM-Tree
     * @returns {Boolean}
     */
    et2_toolbar.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        return false;
    };
    /**
     * Builds dialog for possible admin settings (e.g. default actions pref)
     *
     * @param {type} _actions
     * @param {object} _default_prefs
     */
    et2_toolbar.prototype._admin_settings_dialog = function (_actions, _default_prefs) {
        var buttons = [
            { text: egw.lang("Save"), id: "save" },
            { text: egw.lang("Close"), id: "close" }
        ];
        var self = this;
        var sel_options = { actions: [] };
        var content = { actions: [], reset: false };
        for (var key in _actions) {
            if (_actions[key]['children'] && this.options.flat_list) {
                for (var child in _actions[key]['children']) {
                    sel_options.actions.push({
                        id: child,
                        value: child,
                        label: _actions[key]['children'][child]['caption'],
                        app: egw.app_name(),
                        icon: _actions[key]['children'][child]['iconUrl']
                    });
                }
            }
            else {
                sel_options.actions.push({
                    id: key,
                    value: key,
                    label: _actions[key]['caption'],
                    app: egw.app_name(),
                    icon: _actions[key]['iconUrl']
                });
            }
            if ((!_default_prefs || _default_prefs.length == 0) && _actions[key]['toolbarDefault'])
                content.actions.push(key);
        }
        if (_default_prefs && _default_prefs.length > 0)
            content.actions = _default_prefs;
        et2_core_widget_1.et2_createWidget("dialog", {
            callback: function (_button_id, _value) {
                if (_button_id == 'save' && _value) {
                    if (_value.actions) {
                        var pref = jQuery.extend({}, self.preference);
                        for (var i in pref) {
                            pref[i] = true;
                            if (_value.actions.includes(i))
                                pref[i] = false;
                        }
                        _value.actions = pref;
                    }
                    egw.json('EGroupware\\Api\\Etemplate\\Widget\\Toolbar::ajax_setAdminSettings', [_value, self.dom_id, egw.app_name()], function (_result) {
                        egw.message(_result);
                    }).sendRequest(true);
                }
            },
            title: egw.lang('admin settings for %1', this.dom_id),
            buttons: buttons,
            minWidth: 600,
            minHeight: 300,
            value: { content: content, sel_options: sel_options },
            template: egw.webserverUrl + '/api/templates/default/toolbarAdminSettings.xet?1',
            resizable: false
        }, et2_widget_dialog_1.et2_dialog._create_parent('api'));
    };
    et2_toolbar._attributes = {
        "view_range": {
            "name": "View range",
            "type": "string",
            "default": "5",
            "description": "Define minimum action view range to show actions by both icons and caption"
        },
        "flat_list": {
            "name": "Flat list",
            "type": "boolean",
            "default": true,
            "description": "Define whether the actions with children should be shown as dropdown or flat list"
        }
    };
    /**
     * Default buttons, so there is something for the widget browser / editor to show
     */
    et2_toolbar.default_toolbar = {
        view: { caption: 'View', icons: { primary: 'ui-icon-check' }, group: 1, toolbarDefault: true },
        edit: { caption: 'Edit', group: 1, toolbarDefault: true },
        save: { caption: 'Save', group: 2, toolbarDefault: true }
    };
    return et2_toolbar;
}(et2_core_DOMWidget_1.et2_DOMWidget));
et2_core_widget_1.et2_register_widget(et2_toolbar, ["toolbar"]);
//# sourceMappingURL=et2_widget_toolbar.js.map