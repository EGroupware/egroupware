"use strict";
/**
 * EGroupware eTemplate2 - JS Portlet object - used by Home
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package home
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
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
        et2_core_baseWidget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_DOMWidget_1 = require("./et2_core_DOMWidget");
/**
 * Class which implements the UI of a Portlet
 *
 * This manages the frame and decoration, but also provides the UI for properties.
 *
 * Portlets are only internal to EGroupware.
 *
 * Home does not fully implement WSRP, but tries not to conflict, ether.
 * @link http://docs.oasis-open.org/wsrp/v2/wsrp-2.0-spec-os-01.html
 * @augments et2_baseWidget
 */
var et2_portlet = /** @class */ (function (_super) {
    __extends(et2_portlet, _super);
    /**
     * Constructor
     *
     * @memberOf et2_portlet
     */
    function et2_portlet(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_portlet._attributes, _child || {})) || this;
        _this.GRID = 55;
        /**
         * These are the "normal" actions that every portlet is expected to have.
         * The widget provides default actions for all of these, but they can
         * be added to or overridden if needed by setting the action attribute.
         */
        _this.default_actions = {
            edit_settings: {
                icon: "edit",
                caption: "Configure",
                "default": true,
                hideOnDisabled: true,
                group: "portlet"
            },
            remove_portlet: {
                icon: "delete",
                caption: "Remove",
                group: "portlet"
            }
        };
        var self = _this;
        // Create DOM nodes
        _this.div = jQuery(document.createElement("div"))
            .addClass(_this.options.class)
            .addClass("ui-widget ui-widget-content ui-corner-all")
            .addClass("et2_portlet")
            /* Gridster */
            .attr("data-sizex", _this.options.width)
            .attr("data-sizey", _this.options.height)
            .attr("data-row", _this.options.row)
            .attr("data-col", _this.options.col)
            .resizable({
            autoHide: true,
            grid: _this.GRID,
            //containment: this.getParent().getDOMNode(),
            stop: function (event, ui) {
                self.set_width(Math.round(ui.size.width / self.GRID));
                self.set_height(Math.round(ui.size.height / self.GRID));
                self.egw().jsonq("home.home_ui.ajax_set_properties", [self.id, {}, {
                        width: self.options.width,
                        height: self.options.height
                    }], null, self);
                // Tell children
                self.iterateOver(function (widget) { widget.resize(); }, null, et2_IResizeable);
            }
        });
        _this.header = jQuery(document.createElement("div"))
            .attr('id', _this.getInstanceManager().uniqueId + '_' + _this.id.replace(/\./g, '-') + '_header')
            .addClass("ui-widget-header ui-corner-all")
            .appendTo(_this.div)
            .html(_this.options.title);
        _this.content = jQuery(document.createElement("div"))
            .attr('id', _this.getInstanceManager().uniqueId + '_' + _this.id.replace(/\./g, '-') + '_content')
            .appendTo(_this.div);
        _this.setDOMNode(_this.div[0]);
        return _this;
    }
    et2_portlet.prototype.destroy = function () {
        for (var i = 0; i < this._children.length; i++) {
            // Check for child is a different template and clear it,
            // since it won't be cleared by destroy()
            if (this._children[i].getInstanceManager() != this.getInstanceManager()) {
                this._children[i].getInstanceManager().clear();
            }
        }
        _super.prototype.destroy.call(this);
    };
    et2_portlet.prototype.doLoadingFinished = function () {
        this.set_color(this.options.color);
        return true;
    };
    /**
     * If anyone asks, return the content node, so content goes inside
     */
    et2_portlet.prototype.getDOMNode = function (_sender) {
        if (typeof _sender != 'undefined' && _sender != this) {
            return this.content[0];
        }
        return _super.prototype.getDOMNode.call(this, _sender);
    };
    /**
     * Overriden from parent to add in default actions
     */
    et2_portlet.prototype.set_actions = function (actions) {
        // Set targets for actions
        var defaults = {};
        for (var action_name in this.default_actions) {
            defaults[action_name] = this.default_actions[action_name];
            // Translate caption here, as translations aren't available earlier
            defaults[action_name].caption = this.egw().lang(this.default_actions[action_name].caption);
            if (typeof this[action_name] == "function") {
                defaults[action_name].onExecute = jQuery.proxy(this[action_name], this);
            }
        }
        // Add in defaults, but let provided actions override them
        this.options.actions = jQuery.extend(true, {}, defaults, actions);
        _super.prototype.set_actions.call(this, this.options.actions);
    };
    /**
     * Override _link_actions to remove edit action, if there is no settings
     *
     * @param actions
     */
    et2_portlet.prototype._link_actions = function (actions) {
        // Get the top level element
        var objectManager = egw_getAppObjectManager(true);
        var widget_object = objectManager.getObjectById(this.id);
        if (widget_object == null) {
            // Add a new container to the object manager which will hold the widget
            // objects
            widget_object = objectManager.insertObject(false, new egwActionObject(this.id, objectManager, new et2_core_DOMWidget_1.et2_action_object_impl(this).getAOI(), this._actionManager || objectManager.manager.getActionById(this.id) || objectManager.manager));
        }
        // Delete all old objects
        widget_object.clear();
        // Go over the widget & add links - this is where we decide which actions are
        // 'allowed' for this widget at this time
        var action_links = [];
        for (var i in actions) {
            var id = typeof actions[i].id != 'undefined' ? actions[i].id : i;
            var action = {
                actionId: id,
                enabled: true
            };
            // If there are no settings, there can be no customization, so remove the edit action
            if (id == 'edit_settings' && (!this.options.settings || jQuery.isEmptyObject(this.options.settings))) {
                this.egw().debug("log", "No settings for portlet %o, edit_settings action removed", this);
                action.enabled = false;
            }
            action_links.push(action);
        }
        widget_object.updateActionLinks(action_links);
    };
    /**
     * Create & show a dialog for customizing this portlet
     *
     * Properties for customization are sent in the 'settings' attribute
     */
    et2_portlet.prototype.edit_settings = function () {
        var dialog = et2_createWidget("dialog", {
            callback: jQuery.proxy(this._process_edit, this),
            template: this.options.edit_template,
            value: {
                content: this.options.settings
            },
            buttons: et2_dialog.BUTTONS_OK_CANCEL
        }, this);
        // Set seperately to avoid translation
        dialog.set_title(this.egw().lang("Edit") + " " + (this.options.title || ''));
    };
    et2_portlet.prototype._process_edit = function (button_id, value) {
        if (button_id != et2_dialog.OK_BUTTON)
            return;
        // Save settings - server might reply with new content if the portlet needs an update,
        // but ideally it doesn't
        this.div.addClass("loading");
        // Pass updated settings, unless we're removing
        var settings = (typeof value == 'string') ? {} : this.options.settings || {};
        this.egw().jsonq("home.home_ui.ajax_set_properties", [this.id, settings, value, this.settings ? this.settings.group : false], function (data) {
            // This section not for us
            if (!data || typeof data.attributes == 'undefined')
                return false;
            this.div.removeClass("loading");
            this.set_value(data.content);
            for (var key in data.attributes) {
                if (typeof this["set_" + key] == "function") {
                    this["set_" + key].call(this, data.attributes[key]);
                }
                else if (this.attributes[key]) {
                    this.options[key] = data.attributes[key];
                }
            }
            // Flagged as needing to edit settings?  Open dialog
            if (typeof data.edit_settings != 'undefined' && data.edit_settings) {
                this.edit_settings();
            }
            // Only resize once, and only if needed
            if (data.attributes.width || data.attributes.height) {
                // Tell children
                try {
                    this.iterateOver(function (widget) { widget.resize(); }, null, et2_IResizeable);
                }
                catch (e) {
                    // Something went wrong, but do not stop
                    egw.debug('warn', e, this);
                }
            }
        }, this);
        // Extend, not replace, because settings has types while value has just value
        if (typeof value == 'object') {
            jQuery.extend(this.options.settings, value);
        }
    };
    /**
     * Remove this portlet from the home page
     */
    et2_portlet.prototype.remove_portlet = function () {
        var self = this;
        et2_dialog.show_dialog(function (button_id) {
            if (button_id != et2_dialog.OK_BUTTON)
                return;
            self._process_edit(button_id, '~remove~');
            self.getParent().removeChild(self);
            self.destroy();
        }, this.egw().lang("Remove"), this.options.title, {}, et2_dialog.BUTTONS_OK_CANCEL, et2_dialog.QUESTION_MESSAGE);
    };
    /**
     * Set the HTML content of the portlet
     *
     * @param value String HTML fragment
     */
    et2_portlet.prototype.set_value = function (value) {
        this.content.html(value);
    };
    /**
     * Set the content of the header
     *
     * @param value String HTML fragment
     */
    et2_portlet.prototype.set_title = function (value) {
        this.header.contents()
            .filter(function () {
            return this.nodeType === 3;
        })
            .remove();
        this.options.title = value;
        this.header.append(value);
    };
    /**
     * Let this portlet stand out a little by allowing a custom color
     */
    et2_portlet.prototype.set_color = function (color) {
        this.options.color = color;
        this.header.css("backgroundColor", color);
        this.header.css('color', jQuery.Color(this.header.css("backgroundColor")).lightness() > 0.5 ? 'black' : 'white');
        this.content.css("backgroundColor", color);
    };
    /**
     * Set the number of grid cells this widget spans
     *
     * @param value int Number of horizontal grid cells
     */
    et2_portlet.prototype.set_width = function (value) {
        this.options.width = value;
        this.div.attr("data-sizex", value);
        // Clear what's there from jQuery, we get width from CSS according to sizex
        this.div.css('width', '');
    };
    /**
     * Set the number of vertical grid cells this widget spans
     *
     * @param value int Number of vertical grid cells
     */
    et2_portlet.prototype.set_height = function (value) {
        this.options.height = value;
        this.div.attr("data-sizey", value);
        // Clear what's there from jQuery, we get width from CSS according to sizey
        this.div.css('height', '');
    };
    et2_portlet._attributes = {
        "title": {
            "name": "Title",
            "description": "Goes in the little bit at the top with the icons",
            "type": "string",
            "default": ""
        },
        "edit_template": {
            "name": "Edit template",
            "description": "Custom eTemplate used to customize / set up the portlet",
            "type": "string",
            "default": egw.webserverUrl + "/home/templates/default/edit.xet"
        },
        "color": {
            "name": "Color",
            "description": "Set the portlet color",
            "type": "string",
            "default": ''
        },
        "settings": {
            "name": "Customization settings",
            "description": "Array of customization settings, similar in structure to preference settings",
            "type": "any",
            "default": et2_no_init
        },
        "actions": {
            default: {}
        },
        "width": { "default": 2, "ignore": true },
        "height": { "default": 1, "type": "integer" },
        "rows": { "ignore": true, default: et2_no_init },
        "cols": { "ignore": true, default: et2_no_init },
        "resize_ratio": { "ignore": true, default: et2_no_init },
        "row": {
            "name": "Row",
            "description": "Home page location (row) - handled by home app",
            "default": 1
        },
        "col": {
            "name": "Column",
            "description": "Home page location(column) - handled by home app",
            "default": 1
        }
    };
    return et2_portlet;
}(et2_core_valueWidget_1.et2_valueWidget));
et2_core_widget_1.et2_register_widget(et2_portlet, ["portlet"]);
//# sourceMappingURL=et2_widget_portlet.js.map