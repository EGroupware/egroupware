"use strict";
/**
 * EGroupware eTemplate2 - JS Dropdown Button object
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
    et2_baseWidget;
*/
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * A split button - a button with a dropdown list
 *
 * There are several parts to the button UI:
 * - Container: This is what is percieved as the dropdown button, the whole package together
 *   - Button: The part on the left that can be clicked
 *   - Arrow: The button to display the choices
 *   - Menu: The list of choices
 *
 * Menu options are passed via the select_options.  They are normally ID => Title pairs,
 * as for a select box, but the title can also be full HTML if needed.
 *
 * @augments et2_inputWidget
 */
var et2_dropdown_button = /** @class */ (function (_super) {
    __extends(et2_dropdown_button, _super);
    /**
     * Constructor
     *
     * @memberOf et2_dropdown_button
     */
    function et2_dropdown_button(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_dropdown_button._attributes, _child || {})) || this;
        _this.internal_ids = {
            div: "",
            button: "",
            menu: ""
        };
        _this.div = null;
        _this.buttons = null;
        _this.button = null;
        _this.arrow = null;
        _this.menu = null;
        _this.image = null;
        _this.clicked = false;
        _this.label_updates = true;
        _this.value = null;
        /**
         * Default menu, so there is something for the widget browser / editor to show
         */
        _this.default_menu = '<ul> \
<li data-id="opt_1.1"><a href="#">Option-1.1</a></li>\
<li data-id="opt_1.2"><a href="#">Option-1.2</a></li>\
<li data-id="opt_1.3"><a href="#">Option-1.3</a></li>\
<li data-id="opt_1.4"><a href="#">Option-1.4<br>\
	<small>with second line</small>\
</a></li>\
<li data-id="opt_1.5"><a href="#">Option-1.5</a></li>\
</ul>';
        _this.clicked = false;
        var self = _this;
        // Create the individual UI elements
        // Menu is a UL
        _this.menu = jQuery(_this.default_menu).attr("id", _this.internal_ids.menu)
            .hide()
            .menu({
            select: function (event, ui) {
                self.onselect.call(self, event, ui.item);
            }
        });
        _this.buttons = jQuery(document.createElement("div"))
            .addClass("et2_dropdown");
        // Main "wrapper" div
        _this.div = jQuery(document.createElement("div"))
            .attr("id", _this.internal_ids.div)
            .append(_this.buttons)
            .append(_this.menu);
        // Left side - activates click action
        _this.button = jQuery(document.createElement("button"))
            .attr("id", _this.internal_ids.button)
            .attr("type", "button")
            .addClass("ui-widget ui-corner-left").removeClass("ui-corner-all")
            .appendTo(_this.buttons);
        // Right side - shows dropdown
        _this.arrow = jQuery(document.createElement("button"))
            .addClass("ui-widget ui-corner-right").removeClass("ui-corner-all")
            .attr("type", "button")
            .click(function () {
            // ignore click on readonly button
            if (self.options.readonly)
                return false;
            // Clicking it again hides menu
            if (self.menu.is(":visible")) {
                self.menu.hide();
                return false;
            }
            // Show menu dropdown
            var menu = self.menu.show().position({
                my: "left top",
                at: "left bottom",
                of: self.buttons
            });
            // Hide menu if clicked elsewhere
            jQuery(document).one("click", function () {
                menu.hide();
            });
            return false;
        })
            // This is the actual down arrow icon
            .append("<div class='ui-icon ui-icon-triangle-1-s'/>")
            .appendTo(_this.buttons);
        // Common button UI
        _this.buttons.children("button")
            .addClass("ui-state-default")
            .hover(function () { jQuery(this).addClass("ui-state-hover"); }, function () { jQuery(this).removeClass("ui-state-hover"); });
        // Icon
        _this.image = jQuery(document.createElement("img"));
        _this.setDOMNode(_this.div[0]);
        return _this;
    }
    et2_dropdown_button.prototype.destroy = function () {
        // Destroy widget
        if (this.menu && this.menu.data('ui-menu'))
            this.menu.menu("destroy");
        // Null children
        this.image = null;
        this.button = null;
        this.arrow = null;
        this.buttons = null;
        this.menu = null;
        // Remove
        this.div.empty().remove();
    };
    et2_dropdown_button.prototype.set_id = function (_id) {
        _super.prototype.set_id.call(this, _id);
        // Update internal IDs - not really needed since we refer by internal
        // javascript reference, but good to keep up to date
        this.internal_ids = {
            div: this.dom_id + "_wrapper",
            button: this.dom_id,
            menu: this.dom_id + "_menu"
        };
        for (var key in this.internal_ids) {
            if (this[key] == null)
                continue;
            this[key].attr("id", this.internal_ids[key]);
        }
    };
    /**
     * Set if the button label changes to match the selected option
     *
     * @param updates boolean Turn updating on or off
     */
    et2_dropdown_button.prototype.set_label_updates = function (updates) {
        this.label_updates = updates;
    };
    et2_dropdown_button.prototype.set_accesskey = function (key) {
        jQuery(this.node).attr("accesskey", key);
    };
    et2_dropdown_button.prototype.set_ro_image = function (_image) {
        if (this.options.readonly) {
            this.set_image(_image);
        }
    };
    et2_dropdown_button.prototype.set_image = function (_image) {
        if (!this.isInTree() || this.image == null)
            return;
        if (!_image.trim()) {
            this.image.hide();
        }
        else {
            this.image.show();
        }
        var src = this.egw().image(_image);
        if (src) {
            this.image.attr("src", src);
        }
        // allow url's too
        else if (_image[0] == '/' || _image.substr(0, 4) == 'http') {
            this.image.attr('src', _image);
        }
        else {
            this.image.hide();
        }
    };
    /**
     * Overwritten to maintain an internal clicked attribute
     *
     * @param _ev
     * @returns {Boolean}
     */
    et2_dropdown_button.prototype.click = function (_ev) {
        // ignore click on readonly button
        if (this.options.readonly)
            return false;
        this.clicked = true;
        if (!_super.prototype.click.call(this, _ev)) {
            this.clicked = false;
            return false;
        }
        this.clicked = false;
        return true;
    };
    et2_dropdown_button.prototype.onselect = function (event, selected_node) {
        this.set_value(selected_node.attr("data-id"));
        this.change(selected_node);
    };
    et2_dropdown_button.prototype.attachToDOM = function () {
        var res = _super.prototype.attachToDOM.call(this);
        // Move the parent's handler to the button, or we can't tell the difference between the clicks
        jQuery(this.node).unbind("click.et2_baseWidget");
        this.button.off().bind("click.et2_baseWidget", this, function (e) {
            return e.data.click.call(e.data, this);
        });
        return res;
    };
    et2_dropdown_button.prototype.set_label = function (_value) {
        if (this.button) {
            this.label = _value;
            this.button.text(_value)
                .prepend(this.image);
        }
    };
    /**
     * Set the options for the dropdown
     *
     * @param options Object ID => Label pairs
     */
    et2_dropdown_button.prototype.set_select_options = function (options) {
        this.menu.first().empty();
        // Allow more complicated content, if passed
        if (typeof options == "string") {
            this.menu.append(options);
        }
        else {
            var add_complex_1 = function (node, options) {
                for (var key in options) {
                    var item = void 0;
                    if (typeof options[key] == "string") {
                        item = jQuery("<li data-id='" + key + "'><a href='#'>" + options[key] + "</a></li>");
                    }
                    else if (options[key]["label"]) {
                        item = jQuery("<li data-id='" + key + "'><a href='#'>" + options[key]["label"] + "</a></li>");
                    }
                    // Optgroup
                    else {
                        item = jQuery("<li><a href='#'>" + key + "</a></li>");
                        add_complex_1(node.append("<ul>"), options[key]);
                    }
                    node.append(item);
                    if (item && options[key].icon) {
                        // we supply a applicable class for item images
                        jQuery('a', item).prepend('<img class="et2_button_icon" src="' + (options[key].icon.match(/^(http|https|\/)/) ? options[key].icon : egw.image(options[key].icon)) + '"/>');
                    }
                }
            };
            add_complex_1(this.menu.first(), options);
        }
        this.menu.menu("refresh");
    };
    /**
     * Set tab index
     */
    et2_dropdown_button.prototype.set_tabindex = function (index) {
        jQuery(this.button).attr("tabindex", index);
    };
    et2_dropdown_button.prototype.set_value = function (new_value) {
        var menu_item = jQuery("[data-id='" + new_value + "']", this.menu);
        if (menu_item.length) {
            this.value = new_value;
            if (this.label_updates) {
                this.set_label(menu_item.text());
            }
        }
        else {
            this.value = null;
            if (this.label_updates) {
                this.set_label(this.options.label);
            }
        }
    };
    et2_dropdown_button.prototype.getValue = function () {
        return this.value;
    };
    /**
     * Set options.readonly
     *
     * @param {boolean} _ro
     */
    et2_dropdown_button.prototype.set_readonly = function (_ro) {
        if (_ro != this.options.readonly) {
            this.options.readonly = _ro;
            // don't make readonly dropdown buttons clickable
            if (this.buttons) {
                this.buttons.find('button')
                    .toggleClass('et2_clickable', !_ro)
                    .toggleClass('et2_button_ro', _ro)
                    .css('cursor', _ro ? 'default' : 'pointer');
            }
        }
    };
    et2_dropdown_button.attributes = {
        "label": {
            "name": "caption",
            "type": "string",
            "description": "Label of the button",
            "translate": true,
            "default": "Select..."
        },
        "label_updates": {
            "name": "Label updates",
            "type": "boolean",
            "description": "Button label updates when an option is selected from the menu",
            "default": true
        },
        "image": {
            "name": "Icon",
            "type": "string",
            "description": "Add an icon"
        },
        "ro_image": {
            "name": "Read-only Icon",
            "type": "string",
            "description": "Use this icon instead of hiding for read-only"
        },
        "onclick": {
            "description": "JS code which gets executed when the button is clicked"
        },
        "select_options": {
            "type": "any",
            "name": "Select options",
            "default": {},
            "description": "Select options for dropdown.  Can be a simple key => value list, or value can be full HTML",
            // Skip normal initialization for this one
            "ignore": true
        },
        "accesskey": {
            "name": "Access Key",
            "type": "string",
            "default": et2_no_init,
            "description": "Alt + <key> activates widget"
        },
        "tabindex": {
            "name": "Tab index",
            "type": "integer",
            "default": et2_no_init,
            "description": "Specifies the tab order of a widget when the 'tab' button is used for navigating."
        },
        // No such thing as a required button
        "required": {
            "ignore": true
        }
    };
    return et2_dropdown_button;
}(et2_core_inputWidget_1.et2_inputWidget));
exports.et2_dropdown_button = et2_dropdown_button;
et2_core_widget_1.et2_register_widget(et2_dropdown_button, ["dropdown_button"]);
//# sourceMappingURL=et2_widget_dropdown_button.js.map