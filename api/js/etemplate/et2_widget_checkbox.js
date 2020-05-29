"use strict";
/**
 * EGroupware eTemplate2 - JS Checkbox object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
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
    et2_core_inputWidget;
    et2_core_valueWidget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the "checkbox" XET-Tag
 *
 * @augments et2_inputWidget
 */
var et2_checkbox = /** @class */ (function (_super) {
    __extends(et2_checkbox, _super);
    /**
     * Constructor
     *
     * @memberOf et2_checkbox
     */
    function et2_checkbox(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_checkbox._attributes, _child || {})) || this;
        _this.input = null;
        _this.toggle = null;
        _this.input = null;
        _this.createInputWidget();
        return _this;
    }
    et2_checkbox.prototype.createInputWidget = function () {
        this.input = jQuery(document.createElement("input")).attr("type", "checkbox");
        this.input.addClass("et2_checkbox");
        if (this.options.toggle_on || this.options.toggle_off) {
            var self_1 = this;
            // checkbox container
            this.toggle = jQuery(document.createElement('span'))
                .addClass('et2_checkbox_slideSwitch')
                .append(this.input);
            // update switch status on change
            this.input.change(function () {
                self_1.getValue();
                return true;
            });
            // switch container
            var area = jQuery(document.createElement('span')).addClass('slideSwitch_container').appendTo(this.toggle);
            // on span tag
            var on = jQuery(document.createElement('span')).addClass('on').appendTo(area);
            // off span tag
            var off = jQuery(document.createElement('span')).addClass('off').appendTo(area);
            on.text(this.options.toggle_on);
            off.text(this.options.toggle_off);
            // handle a tag
            jQuery(document.createElement('a')).appendTo(area);
            this.setDOMNode(this.toggle[0]);
        }
        else {
            this.setDOMNode(this.input[0]);
        }
    };
    /**
     * Override default to place checkbox before label, if there is no %s in the label
     *
     * @param {string} label
     */
    et2_checkbox.prototype.set_label = function (label) {
        if (label.length && label.indexOf('%s') < 0) {
            label = '%s' + label;
        }
        _super.prototype.set_label.call(this, label);
        jQuery(this.getSurroundings().getWidgetSurroundings()).addClass('et2_checkbox_label');
    };
    /**
     * Override default to match against set/unset value
     *
     * @param {string|boolean} _value
     */
    et2_checkbox.prototype.set_value = function (_value) {
        // in php, our database storage and et2_checkType(): "0" == false
        if (_value === "0" && this.options.selected_value != "0") {
            _value = false;
        }
        if (_value != this.value) {
            if (_value == this.options.selected_value ||
                _value && this.options.selected_value == this.attributes["selected_value"]["default"] &&
                    _value != this.options.unselected_value) {
                if (this.options.toggle_on || this.options.toggle_off)
                    this.toggle.addClass('switchOn');
                this.input.prop("checked", true);
            }
            else {
                this.input.prop("checked", false);
                if (this.options.toggle_on || this.options.toggle_off)
                    this.toggle.removeClass('switchOn');
            }
        }
    };
    /**
     * Disable checkbox on runtime
     *
     * @param {boolean} _ro
     */
    et2_checkbox.prototype.set_readonly = function (_ro) {
        jQuery(this.getDOMNode()).attr('disabled', _ro);
        this.input.prop('disabled', _ro);
    };
    /**
     * Override default to return unchecked value
     */
    et2_checkbox.prototype.getValue = function () {
        if (this.input.prop("checked")) {
            if (this.options.toggle_on || this.options.toggle_off)
                this.toggle.addClass('switchOn');
            return this.options.selected_value;
        }
        else {
            if (this.options.toggle_on || this.options.toggle_off)
                this.toggle.removeClass('switchOn');
            return this.options.unselected_value;
        }
    };
    et2_checkbox.prototype.set_disabled = function (_value) {
        var parentNode = jQuery(this.getDOMNode()).parent();
        if (parentNode[0] && parentNode[0].nodeName == "label" && parentNode.hasClass('.et2_checkbox_label')) {
            if (_value) {
                parentNode.hide();
            }
            else {
                parentNode.show();
            }
        }
        _super.prototype.set_disabled.call(this, _value);
    };
    et2_checkbox._attributes = {
        "selected_value": {
            "name": "Set value",
            "type": "string",
            "default": "true",
            "description": "Value when checked"
        },
        "unselected_value": {
            "name": "Unset value",
            "type": "string",
            "default": "",
            "description": "Value when not checked"
        },
        "ro_true": {
            "name": "Read only selected",
            "type": "string",
            "default": "X ",
            "description": "What should be displayed when readonly and selected"
        },
        "ro_false": {
            "name": "Read only unselected",
            "type": "string",
            "default": "",
            "description": "What should be displayed when readonly and not selected"
        },
        "value": {
            // Stop framework from messing with value
            "type": "any"
        },
        "toggle_on": {
            "name": "Toggle on caption",
            "type": "string",
            "default": "",
            "description": "String caption to show for ON status",
            "translate": true
        },
        "toggle_off": {
            "name": "Toggle off caption",
            "type": "string",
            "default": "",
            "description": "String caption to show OFF status",
            "translate": true
        }
    };
    et2_checkbox.legacyOptions = ["selected_value", "unselected_value", "ro_true", "ro_false"];
    return et2_checkbox;
}(et2_core_inputWidget_1.et2_inputWidget));
exports.et2_checkbox = et2_checkbox;
et2_core_widget_1.et2_register_widget(et2_checkbox, ["checkbox"]);
/**
* et2_checkbox_ro is the dummy readonly implementation of the checkbox
* @augments et2_checkbox
*/
var et2_checkbox_ro = /** @class */ (function (_super) {
    __extends(et2_checkbox_ro, _super);
    /**
     * Constructor
     *
     * @memberOf et2_checkbox_ro
     */
    function et2_checkbox_ro(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_checkbox_ro._attributes, _child || {})) || this;
        _this.span = null;
        _this.value = "";
        _this.span = jQuery(document.createElement("span"))
            .addClass("et2_checkbox_ro");
        _this.setDOMNode(_this.span[0]);
        return _this;
    }
    /**
     * note: checkbox is checked if even there is a value but not only if the _value is only "true"
     * it's an exceptional validation for cases that we pass non boolean values as checkbox _value
     *
     * @param {string|boolean} _value
     */
    et2_checkbox_ro.prototype.set_value = function (_value) {
        if (_value == this.options.selected_value || _value && this.options.selected_value == this.attributes["selected_value"]["default"] &&
            _value != this.options.unselected_value) {
            this.span.text(this.options.ro_true);
            this.value = _value;
        }
        else {
            this.span.text(this.options.ro_false);
        }
    };
    /**
     * Code for implementing et2_IDetachedDOM
     *
     * @param {array} _attrs
     */
    et2_checkbox_ro.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value", "class");
    };
    et2_checkbox_ro.prototype.getDetachedNodes = function () {
        return [this.span[0]];
    };
    et2_checkbox_ro.prototype.setDetachedAttributes = function (_nodes, _values) {
        // Update the properties
        if (typeof _values["value"] != "undefined") {
            this.span = jQuery(_nodes[0]);
            this.set_value(_values["value"]);
        }
        if (typeof _values["class"] != "undefined") {
            _nodes[0].setAttribute("class", _values["class"]);
        }
    };
    /**
     * Ignore unset value
     */
    et2_checkbox_ro._attributes = {
        "unselected_value": {
            "ignore": true
        }
    };
    return et2_checkbox_ro;
}(et2_checkbox));
et2_core_widget_1.et2_register_widget(et2_checkbox_ro, ["checkbox_ro"]);
//# sourceMappingURL=et2_widget_checkbox.js.map