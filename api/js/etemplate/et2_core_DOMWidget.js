"use strict";
/**
 * EGroupware eTemplate2 - JS DOM Widget class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
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
    et2_core_interfaces;
    et2_core_widget;
    /api/js/egw_action/egw_action.js;
*/
var et2_core_inheritance_1 = require("./et2_core_inheritance");
require("./et2_core_interfaces");
require("./et2_core_common");
var et2_core_widget_1 = require("./et2_core_widget");
require("../egw_action/egw_action.js");
/**
 * Abstract widget class which can be inserted into the DOM. All widget classes
 * deriving from this class have to care about implementing the "getDOMNode"
 * function which has to return the DOM-Node.
 *
 * @augments et2_widget
 */
var et2_DOMWidget = /** @class */ (function (_super) {
    __extends(et2_DOMWidget, _super);
    /**
     * When the DOMWidget is initialized, it grabs the DOM-Node of the parent
     * object (if available) and passes it to its own "createDOMNode" function
     *
     * @memberOf et2_DOMWidget
     */
    function et2_DOMWidget(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_DOMWidget._attributes, _child || {})) || this;
        _this.parentNode = null;
        _this.disabled = false;
        _this._attachSet = {
            "node": null,
            "parent": null
        };
        _this._surroundingsMgr = null;
        return _this;
    }
    /**
     * Detatches the node from the DOM and clears all references to the parent
     * node or the dom node of this widget.
     */
    et2_DOMWidget.prototype.destroy = function () {
        this.detachFromDOM();
        this.parentNode = null;
        this._attachSet = {};
        if (this._actionManager) {
            var app_om = egw_getObjectManager(this.egw().getAppName(), false, 1);
            if (app_om) {
                var om = app_om.getObjectById(this.id);
                if (om)
                    om.remove();
            }
            this._actionManager.remove();
            this._actionManager = null;
        }
        if (this._surroundingsMgr) {
            this._surroundingsMgr.destroy();
            this._surroundingsMgr = null;
        }
        _super.prototype.destroy.call(this);
    };
    /**
     * Attaches the container node of this widget to the DOM-Tree
     */
    et2_DOMWidget.prototype.doLoadingFinished = function () {
        // Check whether the parent implements the et2_IDOMNode interface. If
        // yes, grab the DOM node and create our own.
        if (this.getParent() && this.getParent().implements(et2_IDOMNode)) {
            if (this.options.parent_node) {
                this.set_parent_node(this.options.parent_node);
            }
            else {
                this.setParentDOMNode(this.getParent().getDOMNode(this));
            }
        }
        return true;
    };
    /**
     * Detaches the widget from the DOM tree, if it had been attached to the
     * DOM-Tree using the attachToDOM method.
     */
    et2_DOMWidget.prototype.detachFromDOM = function () {
        if (this._attachSet && this._attachSet.node && this._attachSet.parent) {
            // Remove the current node from the parent node
            try {
                this._attachSet.parent.removeChild(this._attachSet.node);
            }
            catch (e) {
                // Don't throw a DOM error if the node wasn't in the parent
            }
            // Reset the "attachSet"
            this._attachSet = {
                "node": null,
                "parent": null
            };
            return true;
        }
        return false;
    };
    /**
     * Attaches the widget to the DOM tree. Fails if the widget is already
     * attached to the tree or no parent node or no node for this widget is
     * defined.
     */
    et2_DOMWidget.prototype.attachToDOM = function () {
        // Attach the DOM node of this widget (if existing) to the new parent
        var node = this.getDOMNode(this);
        if (node && this.parentNode &&
            (!this._attachSet || this._attachSet && node != this._attachSet.node ||
                this.parentNode != this._attachSet.parent)) {
            // If the surroundings manager exists, surround the DOM-Node of this
            // widget with the DOM-Nodes inside the surroundings manager.
            if (this._surroundingsMgr) {
                node = this._surroundingsMgr.getDOMNode(node);
            }
            // Append this node at its index
            var idx = this.getDOMIndex();
            if (idx < 0 || idx >= this.parentNode.childNodes.length - 1) {
                this.parentNode.appendChild(node);
            }
            else {
                this.parentNode.insertBefore(node, this.parentNode.childNodes[idx]);
            }
            // Store the currently attached nodes
            this._attachSet = {
                "node": node,
                "parent": this.parentNode
            };
            return true;
        }
        return false;
    };
    /**
     * Inserts a child at the given index.
     *
     * @param _node is the node which should be added. It has to be an instance
     * 	of et2_widget
     * @param _idx is the position at which the element should be added.
     */
    et2_DOMWidget.prototype.insertChild = function (_node, _idx) {
        _super.prototype.insertChild.call(this, _node, _idx);
        if (_node.instanceOf(et2_DOMWidget) && typeof _node.hasOwnProperty('parentNode') && this.getDOMNode(this)) {
            try {
                _node.setParentDOMNode(this.getDOMNode(_node));
            }
            catch (_a) {
                // Not ready to be added, usually due to construction order,
                // will probably try again in doLoadingFinished()
            }
        }
    };
    et2_DOMWidget.prototype.isAttached = function () {
        return this.parentNode != null;
    };
    et2_DOMWidget.prototype.getSurroundings = function () {
        if (!this._surroundingsMgr) {
            this._surroundingsMgr = new et2_surroundingsMgr(this);
        }
        return this._surroundingsMgr;
    };
    /**
     * Get data for the tab this widget is on.
     *
     * Will return null if the widget is not on a tab or tab data containing
     * - id
     * - label
     * - widget (top level widget)
     * - contentDiv (jQuery object for the div the tab content is in)
     *
     * @returns {Object|null} Data for tab the widget is on
     */
    et2_DOMWidget.prototype.get_tab_info = function () {
        var parent = this;
        do {
            parent = parent.getParent();
        } while (parent !== this.getRoot() && parent.getType() !== 'tabbox');
        // No tab
        if (parent === this.getRoot()) {
            return null;
        }
        var tabbox = parent;
        // Find the tab index
        for (var i = 0; i < tabbox.tabData.length; i++) {
            // Find the tab by DOM heritage
            // @ts-ignore
            if (tabbox.tabData[i].contentDiv.has(this.div).length) {
                return tabbox.tabData[i];
            }
        }
        // On a tab, but we couldn't find it by DOM nodes  Maybe tab template is
        // not loaded yet.  Try checking IDs.
        var template = this;
        do {
            template = template.getParent();
            // @ts-ignore
        } while (template !== tabbox && template.getType() !== 'template');
        for (var i = tabbox.tabData.length - 1; i >= 0; i--) {
            if (template && template.id && template.id === tabbox.tabData[i].id) {
                return tabbox.tabData[i];
            }
        }
        // Fallback
        var fallback = this.getParent();
        if (typeof fallback.get_tab_info === 'function') {
            return fallback.get_tab_info();
        }
        return null;
    };
    /**
     * Set the parent DOM node of this element.  Takes a wider variety of types
     * than setParentDOMNode(), and matches the set_<attribute> naming convention.
     *
     * @param _node String|DOMNode DOM node to contain the widget, or the ID of the DOM node.
     */
    et2_DOMWidget.prototype.set_parent_node = function (_node) {
        if (typeof _node == "string") {
            var parent = jQuery('#' + _node);
            if (parent.length === 0 && window.parent) {
                // Could not find it, try again with wider context
                // (in case there's an iframe in admin, for example)
                parent = jQuery('#' + _node, window.parent.document);
            }
            if (parent.length === 0) {
                this.egw().debug('warn', 'Unable to find DOM parent node with ID "%s" for widget %o.', _node, this);
            }
            else {
                this.setParentDOMNode(parent.get(0));
            }
        }
        else {
            this.setParentDOMNode(_node);
        }
    };
    /**
     * Set the parent DOM node of this element. If another parent node is already
     * set, this widget removes itself from the DOM tree
     *
     * @param _node
     */
    et2_DOMWidget.prototype.setParentDOMNode = function (_node) {
        if (_node != this.parentNode) {
            // Detatch this element from the DOM tree
            this.detachFromDOM();
            this.parentNode = _node;
            // And attatch the element to the DOM tree
            this.attachToDOM();
        }
    };
    /**
     * Returns the parent node.
     */
    et2_DOMWidget.prototype.getParentDOMNode = function () {
        return this.parentNode;
    };
    /**
     * Returns the index of this element in the DOM tree
     */
    et2_DOMWidget.prototype.getDOMIndex = function () {
        if (this.getParent()) {
            var idx = 0;
            var children = this.getParent().getChildren();
            if (children && children.indexOf)
                return children.indexOf(this);
            egw.debug('warn', 'No Array.indexOf(), falling back to looping. ');
            for (var i = 0; i < children.length; i++) {
                if (children[i] == this) {
                    return idx;
                }
                else if (children[i].isInTree()) {
                    idx++;
                }
            }
        }
        return -1;
    };
    /**
     * Sets the id of the DOM-Node.
     *
     * DOM id's have dots "." replaced with dashes "-"
     *
     * @param {string} _value id to set
     */
    et2_DOMWidget.prototype.set_id = function (_value) {
        this.id = _value;
        this.dom_id = _value ? this.getInstanceManager().uniqueId + '_' + _value.replace(/\./g, '-') : _value;
        var node = this.getDOMNode(this);
        if (node) {
            if (_value != "") {
                node.setAttribute("id", this.dom_id);
            }
            else {
                node.removeAttribute("id");
            }
        }
    };
    et2_DOMWidget.prototype.set_disabled = function (_value) {
        var node = this._surroundingsMgr != null ? this._surroundingsMgr.getDOMNode(this.getDOMNode(this)) : this.getDOMNode(this);
        if (node && this.disabled != _value) {
            this.disabled = _value;
            if (_value) {
                jQuery(node).hide();
            }
            else {
                jQuery(node).show();
            }
        }
    };
    et2_DOMWidget.prototype.set_width = function (_value) {
        this.width = _value;
        var node = this.getDOMNode(this);
        if (node) {
            jQuery(node).css("width", _value);
        }
    };
    et2_DOMWidget.prototype.set_height = function (_value) {
        this.height = _value;
        var node = this.getDOMNode(this);
        if (node) {
            jQuery(node).css("height", _value);
        }
    };
    et2_DOMWidget.prototype.set_class = function (_value) {
        var node = this.getDOMNode(this);
        if (node) {
            if (this["class"]) {
                jQuery(node).removeClass(this["class"]);
            }
            jQuery(node).addClass(_value);
        }
        this["class"] = _value;
    };
    et2_DOMWidget.prototype.set_overflow = function (_value) {
        this.overflow = _value;
        var node = this.getDOMNode(this);
        if (node) {
            jQuery(node).css("overflow", _value);
        }
    };
    et2_DOMWidget.prototype.set_data = function (_value) {
        var node = this.getDOMNode(this);
        if (node && _value) {
            var pairs = _value.split(/,/g);
            for (var i = 0; i < pairs.length; ++i) {
                var name_value = pairs[i].split(':');
                jQuery(node).attr('data-' + name_value[0], name_value[1]);
            }
        }
    };
    et2_DOMWidget.prototype.set_background = function (_value) {
        var node = this.getDOMNode(this);
        var values = '';
        if (_value && node) {
            values = _value.split(',');
            jQuery(node).css({
                "background-image": 'url("' + values[0] + '")',
                "background-position-x": values[1],
                "background-position-y": values[2],
                "background-scale": values[3]
            });
        }
    };
    /**
     * Set Actions on the widget
     *
     * Each action is defined as an object:
     *
     * move: {
     *      type: "drop",
     *      acceptedTypes: "mail",
     *      icon:   "move",
     *      caption:	"Move to"
     *      onExecute:      javascript:mail_move"
     * }
     *
     * This will turn the widget into a drop target for "mail" drag types.  When "mail" drag types are dropped,
     * the global function mail_move(egwAction action, egwActionObject sender) will be called.  The ID of the
     * dragged "mail" will be in sender.id, some information about the sender will be in sender.context.  The
     * etemplate2 widget involved can typically be found in action.parent.data.widget, so your handler
     * can operate in the widget context easily.  The location varies depending on your action though.  It
     * might be action.parent.parent.data.widget
     *
     * To customise how the actions are handled for a particular widget, override _link_actions().  It handles
     * the more widget-specific parts.
     *
     * @param {object} actions {ID: {attributes..}+} map of egw action information
     * @see api/src/Etemplate/Widget/Nextmatch.php egw_actions() method
     */
    et2_DOMWidget.prototype.set_actions = function (actions) {
        if (this.id == "" || typeof this.id == "undefined") {
            this.egw().debug("warn", "Widget should have an ID if you want actions", this);
            return;
        }
        // Initialize the action manager and add some actions to it
        // Only look 1 level deep
        var gam = window.egw_getActionManager(this.egw().appName, true, 1);
        if (typeof this._actionManager != "object") {
            if (gam.getActionById(this.getInstanceManager().uniqueId, 1) !== null) {
                gam = gam.getActionById(this.getInstanceManager().uniqueId, 1);
            }
            if (gam.getActionById(this.id, 1) != null) {
                this._actionManager = gam.getActionById(this.id, 1);
            }
            else {
                this._actionManager = gam.addAction("actionManager", this.id);
            }
        }
        this._actionManager.updateActions(actions, this.egw().appName);
        if (this.options.default_execute)
            this._actionManager.setDefaultExecute(this.options.default_execute);
        // Put a reference to the widget into the action stuff, so we can
        // easily get back to widget context from the action handler
        this._actionManager.data = { widget: this };
        // Link the actions to the DOM
        this._link_actions(actions);
    };
    et2_DOMWidget.prototype.set_default_execute = function (_default_execute) {
        this.options.default_execute = _default_execute;
        if (this._actionManager)
            this._actionManager.setDefaultExecute(null, _default_execute);
    };
    /**
     * Get all action-links / id's of 1.-level actions from a given action object
     *
     * This can be overwritten to not allow all actions, by not returning them here.
     *
     * @param actions
     * @returns {Array}
     */
    et2_DOMWidget.prototype._get_action_links = function (actions) {
        var action_links = [];
        for (var i in actions) {
            var action = actions[i];
            action_links.push(typeof action.id != 'undefined' ? action.id : i);
        }
        return action_links;
    };
    /**
     * Link the actions to the DOM nodes / widget bits.
     *
     * @param {object} actions {ID: {attributes..}+} map of egw action information
     */
    et2_DOMWidget.prototype._link_actions = function (actions) {
        // Get the top level element for the tree
        var objectManager = egw_getAppObjectManager(true);
        var widget_object = objectManager.getObjectById(this.id);
        if (widget_object == null) {
            // Add a new container to the object manager which will hold the widget
            // objects
            widget_object = objectManager.insertObject(false, new egwActionObject(this.id, objectManager, (new et2_action_object_impl(this)).getAOI(), this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager));
        }
        else {
            widget_object.setAOI((new et2_action_object_impl(this, this.getDOMNode())).getAOI());
        }
        // Delete all old objects
        widget_object.clear();
        widget_object.unregisterActions();
        // Go over the widget & add links - this is where we decide which actions are
        // 'allowed' for this widget at this time
        var action_links = this._get_action_links(actions);
        widget_object.updateActionLinks(action_links);
    };
    et2_DOMWidget._attributes = {
        "disabled": {
            "name": "Disabled",
            "type": "boolean",
            "description": "Defines whether this widget is visible.  Not to be confused with an input widget's HTML attribute 'disabled'.",
            "default": false
        },
        "width": {
            "name": "Width",
            "type": "dimension",
            "default": et2_no_init,
            "description": "Width of the element in pixels, percentage or 'auto'"
        },
        "height": {
            "name": "Height",
            "type": "dimension",
            "default": et2_no_init,
            "description": "Height of the element in pixels, percentage or 'auto'"
        },
        "class": {
            "name": "CSS Class",
            "type": "string",
            "default": et2_no_init,
            "description": "CSS Class which is applied to the dom element of this node"
        },
        "overflow": {
            "name": "Overflow",
            "type": "string",
            "default": et2_no_init,
            "description": "If set, the css-overflow attribute is set to that value"
        },
        "parent_node": {
            "name": "DOM parent",
            "type": "string",
            "default": et2_no_init,
            "description": "Insert into the target DOM node instead of the normal location"
        },
        "actions": {
            "name": "Actions list",
            "type": "any",
            "default": et2_no_init,
            "description": "List of egw actions that can be done on the widget.  This includes context menu, drag and drop.  TODO: Link to action documentation"
        },
        default_execute: {
            name: "Default onExecute for actions",
            type: "js",
            default: et2_no_init,
            description: "Set default onExecute javascript method for action not specifying their own"
        },
        resize_ratio: {
            name: "Resize height of the widget on callback resize",
            type: "string",
            default: '',
            description: "Allow Resize height of the widget based on exess height and given ratio"
        },
        data: {
            name: "comma-separated name:value pairs set as data attributes on DOM node",
            type: "string",
            default: '',
            description: 'data="mime:${row}[mime]" would generate data-mime="..." in DOM, eg. to use it in CSS on a parent'
        },
        background: {
            name: "Add background image",
            type: "string",
            default: '',
            description: "Sets background image, left, right and scale on DOM",
        }
    };
    return et2_DOMWidget;
}(et2_core_widget_1.et2_widget));
exports.et2_DOMWidget = et2_DOMWidget;
/**
 * The surroundings manager class allows to append or prepend elements around
 * an widget node.
 */
var et2_surroundingsMgr = /** @class */ (function (_super) {
    __extends(et2_surroundingsMgr, _super);
    /**
     * Constructor
     *
     * @memberOf et2_surroundingsMgr
     * @param _widget
     */
    function et2_surroundingsMgr(_widget) {
        var _this = _super.call(this) || this;
        _this._widgetContainer = null;
        _this._widgetSurroundings = [];
        _this._widgetPlaceholder = null;
        _this._widgetNode = null;
        _this._ownPlaceholder = true;
        _this._surroundingsUpdated = false;
        _this.widget = _widget;
        return _this;
    }
    et2_surroundingsMgr.prototype.destroy = function () {
        this._widgetContainer = null;
        this._widgetSurroundings = null;
        this._widgetPlaceholder = null;
        this._widgetNode = null;
    };
    et2_surroundingsMgr.prototype.prependDOMNode = function (_node) {
        this._widgetSurroundings.unshift(_node);
        this._surroundingsUpdated = true;
    };
    et2_surroundingsMgr.prototype.appendDOMNode = function (_node) {
        // Append an placeholder first if none is existing yet
        if (this._ownPlaceholder && this._widgetPlaceholder == null) {
            this._widgetPlaceholder = document.createElement("span");
            this._widgetSurroundings.push(this._widgetPlaceholder);
        }
        // Append the given node
        this._widgetSurroundings.push(_node);
        this._surroundingsUpdated = true;
    };
    et2_surroundingsMgr.prototype.insertDOMNode = function (_node) {
        if (!this._ownPlaceholder || this._widgetPlaceholder == null) {
            this.appendDOMNode(_node);
            return;
        }
        // Get the index of the widget placeholder and delete it, insert the
        // given node instead
        var idx = this._widgetSurroundings.indexOf(this._widgetPlaceholder);
        this._widgetSurroundings.splice(idx, 1, _node);
        // Delete the reference to the own placeholder
        this._widgetPlaceholder = null;
        this._ownPlaceholder = false;
    };
    et2_surroundingsMgr.prototype.removeDOMNode = function (_node) {
        for (var i = 0; this._widgetSurroundings && i < this._widgetSurroundings.length; i++) {
            if (this._widgetSurroundings[i] == _node) {
                this._widgetSurroundings.splice(i, 1);
                this._surroundingsUpdated = true;
                break;
            }
        }
    };
    et2_surroundingsMgr.prototype.setWidgetPlaceholder = function (_node) {
        if (_node != this._widgetPlaceholder) {
            if (_node != null && this._ownPlaceholder && this._widgetPlaceholder != null) {
                // Delete the current placeholder which was created by the
                // widget itself
                var idx = this._widgetSurroundings.indexOf(this._widgetPlaceholder);
                this._widgetSurroundings.splice(idx, 1);
                // Delete any reference to the own placeholder and set the
                // _ownPlaceholder flag to false
                this._widgetPlaceholder = null;
                this._ownPlaceholder = false;
            }
            this._ownPlaceholder = (_node == null);
            this._widgetPlaceholder = _node;
            this._surroundingsUpdated = true;
        }
    };
    et2_surroundingsMgr.prototype._rebuildContainer = function () {
        // Return if there has been no change in the "surroundings-data"
        if (!this._surroundingsUpdated) {
            return false;
        }
        // Build the widget container
        if (this._widgetSurroundings.length > 0) {
            // Check whether the widgetPlaceholder is really inside the DOM-Tree
            var hasPlaceholder = et2_hasChild(this._widgetSurroundings, this._widgetPlaceholder);
            // If not, append another widget placeholder
            if (!hasPlaceholder) {
                this._widgetPlaceholder = document.createElement("span");
                this._widgetSurroundings.push(this._widgetPlaceholder);
                this._ownPlaceholder = true;
            }
            // If the surroundings array only contains one element, set this one
            // as the widget container
            if (this._widgetSurroundings.length == 1) {
                if (this._widgetSurroundings[0] == this._widgetPlaceholder) {
                    this._widgetContainer = null;
                }
                else {
                    this._widgetContainer = this._widgetSurroundings[0];
                }
            }
            else {
                // Create an outer "span" as widgetContainer
                this._widgetContainer = document.createElement("span");
                // Append the children inside the widgetSurroundings array to
                // the widget container
                for (var i = 0; i < this._widgetSurroundings.length; i++) {
                    this._widgetContainer.appendChild(this._widgetSurroundings[i]);
                }
            }
        }
        else {
            this._widgetContainer = null;
            this._widgetPlaceholder = null;
        }
        this._surroundingsUpdated = false;
        return true;
    };
    et2_surroundingsMgr.prototype.update = function () {
        if (this._surroundingsUpdated) {
            var attached = this.widget ? this.widget.isAttached() : false;
            // Reattach the widget - this will call the "getDOMNode" function
            // and trigger the _rebuildContainer function.
            if (attached && this.widget) {
                this.widget.detachFromDOM();
                this.widget.attachToDOM();
            }
        }
    };
    et2_surroundingsMgr.prototype.getDOMNode = function (_widgetNode) {
        // Update the whole widgetContainer if this is not the first time this
        // function has been called but the widget node has changed.
        if (this._widgetNode != null && this._widgetNode != _widgetNode) {
            this._surroundingsUpdated = true;
        }
        // Copy a reference to the given node
        this._widgetNode = _widgetNode;
        // Build the container if it didn't exist yet.
        var updated = this._rebuildContainer();
        // Return the widget node itself if there are no surroundings arround
        // it
        if (this._widgetContainer == null) {
            return _widgetNode;
        }
        // Replace the widgetPlaceholder with the given widget node if the
        // widgetContainer has been updated
        if (updated) {
            this._widgetPlaceholder.parentNode.replaceChild(_widgetNode, this._widgetPlaceholder);
            if (!this._ownPlaceholder) {
                this._widgetPlaceholder = _widgetNode;
            }
        }
        // Return the widget container
        return this._widgetContainer;
    };
    et2_surroundingsMgr.prototype.getWidgetSurroundings = function () {
        return this._widgetSurroundings;
    };
    return et2_surroundingsMgr;
}(et2_core_inheritance_1.ClassWithAttributes));
/**
 * The egw_action system requires an egwActionObjectInterface Interface implementation
 * to tie actions to DOM nodes.  This one can be used by any widget.
 *
 * The class extension is different than the widgets
 *
 * @param {et2_DOMWidget} widget
 * @param {Object} node
 *
 */
var et2_action_object_impl = /** @class */ (function () {
    function et2_action_object_impl(_widget, _node) {
        var widget = _widget;
        var objectNode = _node;
        this.aoi = new egwActionObjectInterface();
        this.aoi.getWidget = function () {
            return widget;
        };
        this.aoi.doGetDOMNode = function () {
            return objectNode ? objectNode : widget.getDOMNode();
        };
        // _outerCall may be used to determine, whether the state change has been
        // evoked from the outside and the stateChangeCallback has to be called
        // or not.
        this.aoi.doSetState = function (_state, _outerCall) {
        };
        // The doTiggerEvent function may be overritten by the aoi if it wants to
        // support certain action implementation specific events like EGW_AI_DRAG_OVER
        // or EGW_AI_DRAG_OUT
        this.aoi.doTriggerEvent = function (_event, _data) {
            switch (_event) {
                case EGW_AI_DRAG_OVER:
                    jQuery(this.node).addClass("ui-state-active");
                    break;
                case EGW_AI_DRAG_OUT:
                    jQuery(this.node).removeClass("ui-state-active");
                    break;
            }
        };
    }
    et2_action_object_impl.prototype.getAOI = function () {
        return this.aoi;
    };
    return et2_action_object_impl;
}());
exports.et2_action_object_impl = et2_action_object_impl;
//# sourceMappingURL=et2_core_DOMWidget.js.map