"use strict";
/**
 * EGroupware eTemplate2 - JS Radiobox object
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
*/
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
/**
 * Class which implements the "radiobox" XET-Tag
 *
 * A radio button belongs to same group by giving all buttons of a group same id!
 *
 * set_value iterates over all of them and (un)checks them depending on given value.
 *
 * @augments et2_inputWidget
 */
var et2_radiobox = /** @class */ (function (_super) {
    __extends(et2_radiobox, _super);
    /**
     * Constructor
     *
     * @memberOf et2_radiobox
     */
    function et2_radiobox(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_radiobox._attributes, _child || {})) || this;
        _this.input = null;
        _this.id = "";
        _this.createInputWidget();
        return _this;
    }
    et2_radiobox.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
        var readonly = this.getArrayMgr('readonlys').getEntry(this.id);
        if (readonly && readonly.hasOwnProperty(_attrs.set_value)) {
            _attrs.readonly = readonly[_attrs.set_value];
        }
    };
    et2_radiobox.prototype.createInputWidget = function () {
        this.input = jQuery(document.createElement("input"))
            .val(this.options.set_value)
            .attr("type", "radio")
            .attr("disabled", this.options.readonly);
        this.input.addClass("et2_radiobox");
        this.setDOMNode(this.input[0]);
    };
    /**
     * Overwritten to set different DOM level ids by appending set_value
     *
     * @param _id
     */
    et2_radiobox.prototype.set_id = function (_id) {
        _super.prototype.set_id.call(this, _id);
        this.dom_id = this.dom_id.replace('[]', '') + '-' + this.options.set_value;
        if (this.input)
            this.input.attr('id', this.dom_id);
    };
    /**
     * Default for radio buttons is label after button
     *
     * @param _label String New label for radio button.  Use %s to locate the radio button somewhere else in the label
     */
    et2_radiobox.prototype.set_label = function (_label) {
        if (_label.length > 0 && _label.indexOf('%s') == -1) {
            _label = '%s' + _label;
        }
        _super.prototype.set_label.call(this, _label);
    };
    /**
     * Override default to match against set/unset value AND iterate over all siblings with same id
     *
     * @param {string} _value
     */
    et2_radiobox.prototype.set_value = function (_value) {
        this.getRoot().iterateOver(function (radio) {
            if (radio.id == this.id) {
                radio.input.prop('checked', _value == radio.options.set_value);
            }
        }, this, et2_radiobox);
    };
    /**
     * Override default to iterate over all siblings with same id
     *
     * @return {string}
     */
    et2_radiobox.prototype.getValue = function () {
        var val = this.options.value; // initial value, when form is loaded
        var values = [];
        this.getRoot().iterateOver(function (radio) {
            values.push(radio.options.set_value);
            if (radio.id == this.id && radio.input && radio.input.prop('checked')) {
                val = radio.options.set_value;
            }
        }, this, et2_radiobox);
        return val && val.indexOf(values) ? val : null;
    };
    /**
     * Overridden from parent so if it's required, only 1 in a group needs a value
     *
     * @param {array} messages
     * @returns {Boolean}
     */
    et2_radiobox.prototype.isValid = function (messages) {
        var ok = true;
        // Check for required
        if (this.options && this.options.needed && !this.options.readonly && !this.disabled &&
            (this.getValue() == null || this.getValue().valueOf() == '')) {
            if (jQuery.isEmptyObject(this.getInstanceManager().getValues(this.getInstanceManager().widgetContainer)[this.id.replace('[]', '')])) {
                messages.push(this.egw().lang('Field must not be empty !!!'));
                ok = false;
            }
        }
        return ok;
    };
    /**
     * Set radio readonly attribute.
     *
     * @param _readonly Boolean
     */
    et2_radiobox.prototype.set_readonly = function (_readonly) {
        this.options.readonly = _readonly;
        this.getRoot().iterateOver(function (radio) {
            if (radio.id == this.id) {
                radio.input.prop('disabled', _readonly);
            }
        }, this, et2_radiobox);
    };
    et2_radiobox._attributes = {
        "set_value": {
            "name": "Set value",
            "type": "string",
            "default": "true",
            "description": "Value when selected"
        },
        "ro_true": {
            "name": "Read only selected",
            "type": "string",
            "default": "x",
            "description": "What should be displayed when readonly and selected"
        },
        "ro_false": {
            "name": "Read only unselected",
            "type": "string",
            "default": "",
            "description": "What should be displayed when readonly and not selected"
        }
    };
    et2_radiobox.legacyOptions = ["set_value", "ro_true", "ro_false"];
    return et2_radiobox;
}(et2_core_inputWidget_1.et2_inputWidget));
exports.et2_radiobox = et2_radiobox;
et2_core_widget_1.et2_register_widget(et2_radiobox, ["radio"]);
/**
 * @augments et2_valueWidget
 */
var et2_radiobox_ro = /** @class */ (function (_super) {
    __extends(et2_radiobox_ro, _super);
    /**
     * Constructor
     *
     * @memberOf et2_radiobox_ro
     */
    function et2_radiobox_ro(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_radiobox_ro._attributes, _child || {})) || this;
        _this.value = "";
        _this.span = null;
        _this.span = jQuery(document.createElement("span"))
            .addClass("et2_radiobox");
        _this.setDOMNode(_this.span[0]);
        return _this;
    }
    /**
     * Override default to match against set/unset value
     *
     * @param {string} _value
     */
    et2_radiobox_ro.prototype.set_value = function (_value) {
        this.value = _value;
        if (_value == this.options.set_value) {
            this.span.text(this.options.ro_true);
        }
        else {
            this.span.text(this.options.ro_false);
        }
    };
    et2_radiobox_ro.prototype.set_label = function (_label) {
        // no label for ro radio, we show label of checked option as content
    };
    /**
     * Code for implementing et2_IDetachedDOM
     *
     * @param {array} _attrs
     */
    et2_radiobox_ro.prototype.getDetachedAttributes = function (_attrs) {
        // Show label in nextmatch instead of just x
        this.options.ro_true = this.options.label;
        _attrs.push("value");
    };
    et2_radiobox_ro.prototype.getDetachedNodes = function () {
        return [this.span[0]];
    };
    et2_radiobox_ro.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.span = jQuery(_nodes[0]);
        this.set_value(_values["value"]);
    };
    et2_radiobox_ro._attributes = {
        "set_value": {
            "name": "Set value",
            "type": "string",
            "default": "true",
            "description": "Value when selected"
        },
        "ro_true": {
            "name": "Read only selected",
            "type": "string",
            "default": "x",
            "description": "What should be displayed when readonly and selected"
        },
        "ro_false": {
            "name": "Read only unselected",
            "type": "string",
            "default": "",
            "description": "What should be displayed when readonly and not selected"
        },
        "label": {
            "name": "Label",
            "default": "",
            "type": "string"
        }
    };
    et2_radiobox_ro.legacyOptions = ["set_value", "ro_true", "ro_false"];
    return et2_radiobox_ro;
}(et2_core_valueWidget_1.et2_valueWidget));
et2_core_widget_1.et2_register_widget(et2_radiobox_ro, ["radio_ro"]);
/**
 * A group of radio buttons
 *
 * @augments et2_valueWidget
 */
var et2_radioGroup = /** @class */ (function (_super) {
    __extends(et2_radioGroup, _super);
    /**
     * Constructor
     *
     * @param parent
     * @param attrs
     * @memberOf et2_radioGroup
     */
    function et2_radioGroup(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_radioGroup._attributes, _child || {})) || this;
        _this.node = null;
        _this.value = null;
        _this.node = jQuery(document.createElement("div"))
            .addClass("et2_vbox")
            .addClass("et2_box_widget");
        if (_this.options.needed) {
            // This isn't strictly allowed, but it works
            _this.node.attr("required", "required");
        }
        _this.setDOMNode(_this.node[0]);
        // The supported widget classes array defines a whitelist for all widget
        // classes or interfaces child widgets have to support.
        _this.supportedWidgetClasses = [et2_radiobox, et2_radiobox_ro];
        return _this;
    }
    et2_radioGroup.prototype.set_value = function (_value) {
        this.value = _value;
        for (var i = 0; i < this._children.length; i++) {
            var radio = this._children[i];
            radio.set_value(_value);
        }
    };
    et2_radioGroup.prototype.getValue = function () {
        return jQuery("input:checked", this.getDOMNode()).val();
    };
    /**
     * Set a bunch of radio buttons
     *
     * @param {object} _options object with value: label pairs
     */
    et2_radioGroup.prototype.set_options = function (_options) {
        // Call the destructor of all children
        for (var i = this._children.length - 1; i >= 0; i--) {
            this._children[i].destroy();
        }
        this._children = [];
        // create radio buttons for each option
        for (var key in _options) {
            var attrs = {
                // Add index so radios work properly
                "id": (this.options.readonly ? this.id : this.id + "[" + "]"),
                set_value: key,
                label: _options[key],
                ro_true: this.options.ro_true,
                ro_false: this.options.ro_false,
                readonly: this.options.readonly
            };
            if (typeof _options[key] === 'object' && _options[key].label) {
                attrs.set_value = _options[key].value;
                attrs.label = _options[key].label;
            }
            // Can't have a required readonly, it will warn & be removed later, so avoid the warning
            if (attrs.readonly === false) {
                attrs['needed'] = this.options.needed;
            }
            et2_createWidget("radio", attrs, this);
        }
        this.set_value(this.value);
    };
    /**
     * Set a label on the group of radio buttons
     *
     * @param {string} _value
     */
    et2_radioGroup.prototype.set_label = function (_value) {
        // Abort if ther was no change in the label
        if (_value == this.label) {
            return;
        }
        if (_value) {
            // Create the label container if it didn't exist yet
            if (this._labelContainer == null) {
                this._labelContainer = jQuery(document.createElement("label"));
                this.getSurroundings().insertDOMNode(this._labelContainer[0]);
            }
            // Clear the label container.
            this._labelContainer.empty();
            // Create the placeholder element and set it
            var ph = document.createElement("span");
            this.getSurroundings().setWidgetPlaceholder(ph);
            this._labelContainer
                .append(document.createTextNode(_value))
                .append(ph);
        }
        else {
            // Delete the labelContainer from the surroundings object
            if (this._labelContainer) {
                this.getSurroundings().removeDOMNode(this._labelContainer[0]);
            }
            this._labelContainer = null;
        }
    };
    /**
     * Code for implementing et2_IDetachedDOM
     * This doesn't need to be implemented.
     * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
     *
     * @param {object} _attrs
     */
    et2_radioGroup.prototype.getDetachedAttributes = function (_attrs) {
    };
    et2_radioGroup.prototype.getDetachedNodes = function () {
        return [this.getDOMNode()];
    };
    et2_radioGroup.prototype.setDetachedAttributes = function (_nodes, _values) {
    };
    et2_radioGroup._attributes = {
        "label": {
            "name": "Label",
            "default": "",
            "type": "string",
            "description": "The label is displayed above the list of radio buttons. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
            "translate": true
        },
        "value": {
            "name": "Value",
            "type": "string",
            "default": "true",
            "description": "Value for each radio button"
        },
        "ro_true": {
            "name": "Read only selected",
            "type": "string",
            "default": "x",
            "description": "What should be displayed when readonly and selected"
        },
        "ro_false": {
            "name": "Read only unselected",
            "type": "string",
            "default": "",
            "description": "What should be displayed when readonly and not selected"
        },
        "options": {
            "name": "Radio options",
            "type": "any",
            "default": {},
            "description": "Options for radio buttons.  Should be {value: label, ...}"
        },
        "needed": {
            "name": "Required",
            "default": false,
            "type": "boolean",
            "description": "If required, the user must select one of the options before the form can be submitted"
        }
    };
    return et2_radioGroup;
}(et2_core_valueWidget_1.et2_valueWidget));
// No such tag as 'radiogroup', but it needs something
et2_core_widget_1.et2_register_widget(et2_radioGroup, ["radiogroup"]);
//# sourceMappingURL=et2_widget_radiobox.js.map