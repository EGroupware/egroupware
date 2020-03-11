"use strict";
/**
 * EGroupware eTemplate2 - JS Widget base class
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
    et2_core_inputWidget;
*/
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * et2_editableWidget derives from et2_inputWidget and adds the ability to start
 * readonly, then turn editable on double-click.  If we decide to do this with
 * more widgets, it should just be merged with et2_inputWidget.
 *
 * @augments et2_inputWidget
 */
var et2_editableWidget = /** @class */ (function (_super) {
    __extends(et2_editableWidget, _super);
    /**
     * Constructor
     */
    function et2_editableWidget(_parent, _attrs, _child) {
        var _this = this;
        // 'Editable' really should be boolean for everything else to work
        if (_attrs.readonly && typeof _attrs.readonly === 'string') {
            _attrs.readonly = true;
            var toggle_readonly = _attrs.toggle_readonly;
        }
        // Call the inherited constructor
        _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_editableWidget._attributes, _child || {})) || this;
        if (typeof toggle_readonly != 'undefined')
            _this._toggle_readonly = toggle_readonly;
        return _this;
    }
    et2_editableWidget.prototype.destroy = function () {
        var node = this.getInputNode();
        if (node) {
            jQuery(node).off('.et2_editableWidget');
        }
        _super.prototype.destroy.call(this);
    };
    /**
     * Load the validation errors from the server
     *
     * @param {object} _attrs
     */
    et2_editableWidget.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
    };
    et2_editableWidget.prototype.attachToDOM = function () {
        var res = _super.prototype.attachToDOM.call(this);
        var node = this.getDOMNode();
        if (node && this._toggle_readonly) {
            jQuery(node)
                .off('.et2_editableWidget')
                .on("dblclick.et2_editableWidget", this, function (e) {
                e.data.dblclick.call(e.data, this);
            })
                .addClass('et2_clickable et2_editable');
        }
        else {
            jQuery(node).addClass('et2_editable_readonly');
        }
        return res;
    };
    et2_editableWidget.prototype.detatchFromDOM = function () {
        _super.prototype.detatchFromDOM.call(this);
    };
    /**
     * Handle double click
     *
     * Turn widget editable
     *
     * @param {DOMNode} _node
     */
    et2_editableWidget.prototype.dblclick = function (_node) {
        // Turn off readonly
        this.set_readonly(false);
        jQuery('body').on("click.et2_editableWidget", this, function (e) {
            // Make sure click comes from body, not a popup
            if (jQuery.contains(this, e.target) && e.target.type != 'textarea') {
                jQuery(this).off("click.et2_editableWidget");
                e.data.focusout.call(e.data, this);
            }
        });
    };
    /**
     * User clicked somewhere else, save and turn back to readonly
     *
     * @param {DOMNode} _node Body node
     * @returns {et2_core_editableWidgetet2_editableWidget.et2_core_editableWidgetAnonym$0@call;getInstanceManager@call;submit}
     */
    et2_editableWidget.prototype.focusout = function (_node) {
        var value = this.get_value();
        var oldValue = this._oldValue;
        // Change back to readonly
        this.set_readonly(true);
        // No change, do nothing
        if (value == oldValue)
            return;
        // Submit
        if (this.options.save_callback) {
            var params = [value];
            if (this.options.save_callback_params) {
                params = params.concat(this.options.save_callback_params.split(','));
            }
            egw.json(this.options.save_callback, params, function () {
            }, this, true, this).sendRequest();
        }
        else {
            this.set_value(value);
            return this.getInstanceManager().submit();
        }
    };
    /**
     * Called whenever the template gets submitted.
     * If we have a save_callback, we call that before the submit (no check on
     * the result)
     *
     * @param _values contains the values which will be sent to the server.
     * 	Listeners may change these values before they get submitted.
     */
    et2_editableWidget.prototype.submit = function (_values) {
        if (this.options.readonly) {
            // Not currently editing, just continue on
            return true;
        }
        // Change back to readonly
        this.set_readonly(true);
        var params = [this.get_value()];
        if (this.options.save_callback_params) {
            params = params.concat(this.options.save_callback_params.split(','));
        }
        if (this.options.save_callback) {
            egw.json(this.options.save_callback, params, function () {
            }, this, true, this).sendRequest();
        }
        return true;
    };
    et2_editableWidget._attributes = {
        readonly: {
            name: "readonly",
            type: "string",
            default: false,
            description: "If set to 'editable' will start readonly, double clicking will make it editable and clicking out will save",
            ignore: true // todo: not sure why this used to be ignored before migration by default but not anymore
        },
        toggle_readonly: {
            name: "toggle_readonly",
            type: "boolean",
            default: true,
            description: "Double clicking makes widget editable.  If off, must be made editable in some other way."
        },
        save_callback: {
            name: "save_callback",
            type: "string",
            default: et2_no_init,
            description: "Ajax callback to save changed value when readonly is 'editable'.  If not provided, a regular submit is done."
        },
        save_callback_params: {
            name: "readonly",
            type: "string",
            default: et2_no_init,
            description: "Additional parameters passed to save_callback"
        },
        editable_height: {
            name: "Editable height",
            description: "Set height for widget while in edit mode",
            type: "string"
        }
    };
    return et2_editableWidget;
}(et2_core_inputWidget_1.et2_inputWidget));
exports.et2_editableWidget = et2_editableWidget;
//# sourceMappingURL=et2_core_editableWidget.js.map