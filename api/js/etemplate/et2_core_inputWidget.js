"use strict";
/**
 * EGroupware eTemplate2 - JS Widget base class
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
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_interfaces;
    et2_core_valueWidget;
*/
require("./et2_core_common");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
/**
 * et2_inputWidget derrives from et2_simpleWidget and implements the IInput
 * interface. When derriving from this class, call setDOMNode with an input
 * DOMNode.
 */
var et2_inputWidget = /** @class */ (function (_super) {
    __extends(et2_inputWidget, _super);
    /**
     * Constructor
     */
    function et2_inputWidget(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_inputWidget._attributes, _child || {})) || this;
        // mark value as not initialised, so set_value can determine if it is necessary to trigger change event
        _this._oldValue = et2_no_init;
        _this._labelContainer = null;
        return _this;
    }
    et2_inputWidget.prototype.destroy = function () {
        var node = this.getInputNode();
        if (node) {
            jQuery(node).unbind("change.et2_inputWidget");
            jQuery(node).unbind("focus");
        }
        _super.prototype.destroy.call(this);
        this._labelContainer = null;
    };
    /**
     * Make sure dirty flag is properly set
     */
    et2_inputWidget.prototype.doLoadingFinished = function () {
        var result = _super.prototype.doLoadingFinished.call(this);
        this.resetDirty();
        return result;
    };
    /**
     * Load the validation errors from the server
     *
     * @param {object} _attrs
     */
    et2_inputWidget.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
        // Check whether an validation error entry exists
        if (this.id && this.getArrayMgr("validation_errors")) {
            var val = this.getArrayMgr("validation_errors").getEntry(this.id);
            if (val) {
                _attrs["validation_error"] = val;
            }
        }
    };
    et2_inputWidget.prototype.attachToDOM = function () {
        var node = this.getInputNode();
        if (node) {
            jQuery(node)
                .off('.et2_inputWidget')
                .bind("change.et2_inputWidget", this, function (e) {
                e.data.change.call(e.data, this);
            })
                .bind("focus.et2_inputWidget", this, function (e) {
                e.data.focus.call(e.data, this);
            });
        }
        return _super.prototype.attachToDOM.call(this);
        //		jQuery(this.getInputNode()).attr("novalidate","novalidate"); // Stop browser from getting involved
        //		jQuery(this.getInputNode()).validator();
    };
    et2_inputWidget.prototype.detatchFromDOM = function () {
        //		if(this.getInputNode()) {
        //			jQuery(this.getInputNode()).data("validator").destroy();
        //		}
        _super.prototype.detachFromDOM.call(this);
    };
    et2_inputWidget.prototype.change = function (_node, _widget, _value) {
        var messages = [];
        var valid = this.isValid(messages);
        // Passing false will clear any set messages
        this.set_validation_error(valid ? false : messages);
        if (valid && this.onchange) {
            if (typeof this.onchange == 'function') {
                // Make sure function gets a reference to the widget
                var args = Array.prototype.slice.call(arguments);
                if (args.indexOf(this) == -1)
                    args.push(this);
                return this.onchange.apply(this, args);
            }
            else {
                return (et2_compileLegacyJS(this.options.onchange, this, _node))();
            }
        }
        return valid;
    };
    et2_inputWidget.prototype.focus = function (_node) {
        if (typeof this.options.onfocus == 'function') {
            // Make sure function gets a reference to the widget
            var args = Array.prototype.slice.call(arguments);
            if (args.indexOf(this) == -1)
                args.push(this);
            return this.options.onfocus.apply(this, args);
        }
    };
    /**
     * Set value of widget and trigger for real changes a change event
     *
     * First initialisation (_oldValue === et2_no_init) is NOT considered a change!
     *
     * @param {string} _value value to set
     */
    et2_inputWidget.prototype.set_value = function (_value) {
        var node = this.getInputNode();
        if (node) {
            jQuery(node).val(_value);
            if (this.isAttached() && this._oldValue !== et2_no_init && this._oldValue !== _value) {
                jQuery(node).change();
            }
        }
        this._oldValue = _value;
    };
    et2_inputWidget.prototype.set_id = function (_value) {
        this.id = _value;
        this.dom_id = _value && this.getInstanceManager() ? this.getInstanceManager().uniqueId + '_' + this.id : _value;
        // Set the id of the _input_ node (in contrast to the default
        // implementation, which sets the base node)
        var node = this.getInputNode();
        if (node) {
            // Unique ID to prevent DOM collisions across multiple templates
            if (_value != "") {
                node.setAttribute("id", this.dom_id);
                node.setAttribute("name", _value);
            }
            else {
                node.removeAttribute("id");
                node.removeAttribute("name");
            }
        }
    };
    et2_inputWidget.prototype.set_needed = function (_value) {
        var node = this.getInputNode();
        if (node) {
            if (_value && !this.options.readonly) {
                jQuery(node).attr("required", "required");
            }
            else {
                node.removeAttribute("required");
            }
        }
    };
    et2_inputWidget.prototype.set_validation_error = function (_value) {
        var node = this.getInputNode();
        if (node) {
            if (_value === false) {
                this.hideMessage();
                jQuery(node).removeClass("invalid");
            }
            else {
                this.showMessage(_value, "validation_error");
                jQuery(node).addClass("invalid");
                // If on a tab, switch to that tab so user can see it
                var widget = this;
                while (widget.getParent() && widget.getType() != 'tabbox') {
                    widget = widget.getParent();
                }
                if (widget.getType() == 'tabbox')
                    widget.activateTab(this);
            }
        }
    };
    /**
     * Set tab index
     *
     * @param {number} index
     */
    et2_inputWidget.prototype.set_tabindex = function (index) {
        jQuery(this.getInputNode()).attr("tabindex", index);
    };
    et2_inputWidget.prototype.getInputNode = function () {
        return this.node;
    };
    et2_inputWidget.prototype.get_value = function () {
        return this.getValue();
    };
    et2_inputWidget.prototype.getValue = function () {
        var node = this.getInputNode();
        if (node) {
            var val = jQuery(node).val();
            return val;
        }
        return this._oldValue;
    };
    et2_inputWidget.prototype.isDirty = function () {
        var value = this.getValue();
        if (typeof value !== typeof this._oldValue) {
            return true;
        }
        if (this._oldValue === value) {
            return false;
        }
        switch (typeof this._oldValue) {
            case "object":
                if (typeof this._oldValue.length !== "undefined" &&
                    this._oldValue.length !== value.length) {
                    return true;
                }
                for (var key in this._oldValue) {
                    if (this._oldValue[key] !== value[key])
                        return true;
                }
                return false;
            default:
                return this._oldValue != value;
        }
    };
    et2_inputWidget.prototype.resetDirty = function () {
        this._oldValue = this.getValue();
    };
    et2_inputWidget.prototype.isValid = function (messages) {
        var ok = true;
        // Check for required
        if (this.options && this.options.needed && !this.options.readonly && !this.disabled &&
            (this.getValue() == null || this.getValue().valueOf() == '')) {
            messages.push(this.egw().lang('Field must not be empty !!!'));
            ok = false;
        }
        return ok;
    };
    /**
     * Called whenever the template gets submitted. We return false if the widget
     * is not valid, which cancels the submission.
     *
     * @param _values contains the values which will be sent to the server.
     * 	Listeners may change these values before they get submitted.
     */
    et2_inputWidget.prototype.submit = function (_values) {
        var messages = [];
        var valid = this.isValid(messages);
        // Passing false will clear any set messages
        this.set_validation_error(valid ? false : messages);
        return valid;
    };
    et2_inputWidget._attributes = {
        "needed": {
            "name": "Required",
            "default": false,
            "type": "boolean",
            "description": "If required, the user must enter a value before the form can be submitted"
        },
        "onchange": {
            "name": "onchange",
            "type": "js",
            "default": et2_no_init,
            "description": "JS code which is executed when the value changes."
        },
        "onfocus": {
            "name": "onfocus",
            "type": "js",
            "default": et2_no_init,
            "description": "JS code which get executed when wiget receives focus."
        },
        "validation_error": {
            "name": "Validation Error",
            "type": "string",
            "default": et2_no_init,
            "description": "Used internally to store the validation error that came from the server."
        },
        "tabindex": {
            "name": "Tab index",
            "type": "integer",
            "default": et2_no_init,
            "description": "Specifies the tab order of a widget when the 'tab' button is used for navigating."
        },
        readonly: {
            name: "readonly",
            type: "boolean",
            "default": false,
            description: "Does NOT allow user to enter data, just displays existing data"
        }
    };
    return et2_inputWidget;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_inputWidget = et2_inputWidget;
//# sourceMappingURL=et2_core_inputWidget.js.map