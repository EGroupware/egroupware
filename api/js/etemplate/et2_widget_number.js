"use strict";
/**
 * EGroupware eTemplate2 - JS Number object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
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
    et2_widget_textbox;
*/
var et2_widget_textbox_1 = require("./et2_widget_textbox");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the "int" and textbox type=float XET-Tags
 *
 * @augments et2_textbox
 */
var et2_number = /** @class */ (function (_super) {
    __extends(et2_number, _super);
    /**
     * Constructor
     *
     * @memberOf et2_number
     */
    function et2_number(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_number._attributes, _child || {})) || this;
        _this.min = null;
        _this.max = null;
        _this.step = null;
        return _this;
    }
    et2_number.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
        if (typeof _attrs.validator == 'undefined') {
            _attrs.validator = _attrs.type == 'float' ? '/^-?[0-9]*[,.]?[0-9]*$/' : '/^-?[0-9]*$/';
        }
    };
    /**
     * Clientside validation using regular expression in "validator" attribute
     *
     * @param {array} _messages
     */
    et2_number.prototype.isValid = function (_messages) {
        var ok = true;
        // if we have a html5 validation error, show it, as this.input.val() will be empty!
        if (this.input && this.input[0] && this.input[0].validationMessage && !this.input[0].validity.stepMismatch) {
            _messages.push(this.input[0].validationMessage);
            ok = false;
        }
        return _super.prototype.isValid.call(this, _messages) && ok;
    };
    et2_number.prototype.createInputWidget = function () {
        this.input = jQuery(document.createElement("input"));
        this.input.attr("type", "number");
        this.input.addClass("et2_textbox");
        // bind invalid event to change, to trigger our validation
        this.input.on('invalid', jQuery.proxy(this.change, this));
        if (this.options.onkeypress && typeof this.options.onkeypress == 'function') {
            var self = this;
            this.input.keypress(function (_ev) {
                return self.options.onkeypress.call(this, _ev, self);
            });
        }
        this.setDOMNode(this.input[0]);
    };
    /**
     * Set input widget size
     *
     * Overwritten from et2_textbox as input type=number seems to ignore size,
     * therefore we set width in em instead, if not et2_fullWidth given.
     *
     * @param _size Rather arbitrary size units, approximately characters
     */
    et2_number.prototype.set_size = function (_size) {
        if (typeof _size != 'undefined' && _size != this.input.attr("size")) {
            this.size = _size;
            this.input.attr("size", this.size);
            if (typeof this.options.class == 'undefined' || this.options.class.search('et2_fullWidth') == -1) {
                this.input.css('width', _size + 'em');
            }
        }
    };
    et2_number.prototype.set_min = function (_value) {
        this.min = _value;
        if (this.min == null) {
            this.input.removeAttr("min");
        }
        else {
            this.input.attr("min", this.min);
        }
    };
    et2_number.prototype.set_max = function (_value) {
        this.max = _value;
        if (this.max == null) {
            this.input.removeAttr("max");
        }
        else {
            this.input.attr("max", this.max);
        }
    };
    et2_number.prototype.set_step = function (_value) {
        this.step = _value;
        if (this.step == null) {
            this.input.removeAttr("step");
        }
        else {
            this.input.attr("step", this.step);
        }
    };
    et2_number._attributes = {
        "value": {
            "type": "float"
        },
        // Override default width, numbers are usually shorter
        "size": {
            "default": 5
        },
        "min": {
            "name": "Minimum",
            "type": "integer",
            "default": et2_no_init,
            "description": "Minimum allowed value"
        },
        "max": {
            "name": "Maximum",
            "type": "integer",
            "default": et2_no_init,
            "description": "Maximum allowed value"
        },
        "step": {
            "name": "step value",
            "type": "integer",
            "default": et2_no_init,
            "description": "Step attribute specifies the interval between legal numbers"
        },
        "precision": {
            // TODO: Implement this in some nice way other than HTML5's step attribute
            "name": "Precision",
            "type": "integer",
            "default": et2_no_init,
            "description": "Allowed precision - # of decimal places",
            "ignore": true
        }
    };
    return et2_number;
}(et2_widget_textbox_1.et2_textbox));
exports.et2_number = et2_number;
et2_core_widget_1.et2_register_widget(et2_number, ["int", "integer", "float"]);
/**
 * Extend read-only to tell it to ignore special attributes, which
 * would cause warnings otherwise
 * @augments et2_textbox_ro
 * @class
 */
var et2_number_ro = /** @class */ (function (_super) {
    __extends(et2_number_ro, _super);
    function et2_number_ro() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    et2_number_ro.prototype.set_value = function (_value) {
        if (typeof this.options.precision != 'undefined' && "" + _value != "") {
            _value = parseFloat(_value).toFixed(this.options.precision);
        }
        _super.prototype.set_value.call(this, _value);
    };
    et2_number_ro._attributes = {
        min: { ignore: true },
        max: { ignore: true },
        precision: {
            name: "Precision",
            type: "integer",
            default: et2_no_init,
            description: "Allowed precision - # of decimal places",
            ignore: true
        },
        value: { type: "float" }
    };
    return et2_number_ro;
}(et2_textbox_ro));
et2_core_widget_1.et2_register_widget(et2_number_ro, ["int_ro", "integer_ro", "float_ro"]);
//# sourceMappingURL=et2_widget_number.js.map