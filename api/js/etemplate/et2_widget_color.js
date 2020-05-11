"use strict";
/**
 * EGroupware eTemplate2 - JS Color picker object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
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
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the "colorpicker" XET-Tag
 *
 */
var et2_color = /** @class */ (function (_super) {
    __extends(et2_color, _super);
    /**
     * Constructor
     */
    function et2_color(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_color._attributes, _child || {})) || this;
        _this.cleared = true;
        // included via etemplate2.css
        //this.egw().includeCSS("phpgwapi/js/jquery/jpicker/css/jPicker-1.1.6.min.css");
        _this.span = jQuery("<span class='et2_color'/>");
        _this.image = jQuery("<img src='" + _this.egw().image("non_loaded_bg") + "'/>")
            .appendTo(_this.span)
            .on("click", function () {
            this.input.trigger('click');
        }.bind(_this));
        _this.input = jQuery("<input type='color'/>").appendTo(_this.span)
            .on('change', function () {
            this.cleared = false;
            this.image.hide();
        }.bind(_this));
        if (!_this.options.readonly && !_this.options.needed) {
            _this.clear = jQuery("<span class='ui-icon clear'/>")
                .appendTo(_this.span)
                .on("click", function () {
                this.set_value('');
                return false;
            }.bind(_this));
        }
        _this.setDOMNode(_this.span[0]);
        return _this;
    }
    et2_color.prototype.getValue = function () {
        var value = this.input.val();
        if (this.cleared || value === '#FFFFFF' || value === '#ffffff') {
            return '';
        }
        return value;
    };
    et2_color.prototype.set_value = function (color) {
        if (!color) {
            color = '';
        }
        this.cleared = !color;
        this.image.toggle(!color);
        this.input.val(color);
    };
    return et2_color;
}(et2_core_inputWidget_1.et2_inputWidget));
exports.et2_color = et2_color;
et2_core_widget_1.et2_register_widget(et2_color, ["colorpicker"]);
/**
 * et2_textbox_ro is the dummy readonly implementation of the textbox.
 * @augments et2_valueWidget
 */
var et2_color_ro = /** @class */ (function (_super) {
    __extends(et2_color_ro, _super);
    /**
     * Constructor
     *
     * @memberOf et2_color_ro
     */
    function et2_color_ro(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, _child || {}) || this;
        _this.value = "";
        _this.$node = jQuery(document.createElement("div"))
            .addClass("et2_color");
        _this.setDOMNode(_this.$node[0]);
        return _this;
    }
    et2_color_ro.prototype.set_value = function (_value) {
        this.value = _value;
        if (!_value)
            _value = "inherit";
        this.$node.css("background-color", _value);
    };
    /**
     * Code for implementing et2_IDetachedDOM
     *
     * @param {array} _attrs array to add further attributes to
     */
    et2_color_ro.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value");
    };
    et2_color_ro.prototype.getDetachedNodes = function () {
        return [this.node];
    };
    et2_color_ro.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.$node = jQuery(_nodes[0]);
        if (typeof _values["value"] != 'undefined') {
            this.set_value(_values["value"]);
        }
    };
    return et2_color_ro;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_color_ro = et2_color_ro;
et2_core_widget_1.et2_register_widget(et2_color_ro, ["colorpicker_ro"]);
//# sourceMappingURL=et2_widget_color.js.map