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
    lib/tooltip;
    et2_core_DOMWidget;
*/
//import { ClassWithAttributes } from './et2_core_inheritance';
require("./et2_core_interfaces");
require("./et2_core_common");
var et2_core_DOMWidget_1 = require("./et2_core_DOMWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which manages the DOM node itself. The simpleWidget class is derrived
 * from et2_DOMWidget and implements the getDOMNode function. A setDOMNode
 * function is provided, which attatches the given node to the DOM if possible.
 *
 * @augments et2_DOMWidget
 */
var et2_baseWidget = /** @class */ (function (_super) {
    __extends(et2_baseWidget, _super);
    /**
     * Constructor
     */
    function et2_baseWidget(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_core_DOMWidget_1.et2_DOMWidget._attributes, _child || {})) || this;
        _this.align = 'left';
        _this.node = null;
        _this.statustext = '';
        _this._messageDiv = null;
        _this._tooltipElem = null;
        return _this;
    }
    et2_baseWidget.prototype.destroy = function () {
        _super.prototype.destroy.call(this);
        this.node = null;
        this._messageDiv = null;
    };
    /**
     * The setMessage function can be used to attach a small message box to the
     * widget. This is e.g. used to display validation errors or success messages
     *
     * @param _text is the text which should be displayed as a message
     * @param _type is an css class which is attached to the message box.
     * 	Currently available are "hint", "success" and "validation_error", defaults
     * 	to "hint"
     * @param _floating if true, the object will be in one row with the element,
     * 	defaults to true
     * @param _prepend if set, the message is displayed behind the widget node
     * 	instead of before. Defaults to false.
     */
    et2_baseWidget.prototype.showMessage = function (_text, _type, _floating, _prepend) {
        // Preset the parameters
        if (typeof _type == "undefined") {
            _type = "hint";
        }
        if (typeof _floating == "undefined") {
            _floating = true;
        }
        if (typeof _prepend == "undefined") {
            _prepend = false;
        }
        var surr = this.getSurroundings();
        // Remove the message div from the surroundings before creating a new
        // one
        this.hideMessage(false, true);
        // Create the message div and add it to the "surroundings" manager
        this._messageDiv = jQuery(document.createElement("div"))
            .addClass("message")
            .addClass(_type)
            .addClass(_floating ? "floating" : "")
            .text(_text.valueOf() + "");
        // Decide whether to prepend or append the div
        if (_prepend) {
            surr.prependDOMNode(this._messageDiv[0]);
        }
        else {
            surr.appendDOMNode(this._messageDiv[0]);
        }
        surr.update();
    };
    /**
     * The hideMessage function can be used to hide a previously shown message.
     *
     * @param _fade if true, the message div will fade out, otherwise the message
     * 	div is removed immediately. Defaults to true.
     * @param _noUpdate is used internally to prevent an update of the surroundings
     * 	manager.
     */
    et2_baseWidget.prototype.hideMessage = function (_fade, _noUpdate) {
        if (typeof _fade == "undefined") {
            _fade = true;
        }
        if (typeof _noUpdate == "undefined") {
            _noUpdate = false;
        }
        // Remove the message from the surroundings manager and remove the
        // reference to it
        if (this._messageDiv != null) {
            var surr = this.getSurroundings();
            var self = this;
            var messageDiv = this._messageDiv;
            self._messageDiv = null;
            var _done = function () {
                surr.removeDOMNode(messageDiv[0]);
                // Update the surroundings manager
                if (!_noUpdate) {
                    surr.update();
                }
            };
            // Either fade out or directly call the function which removes the div
            if (_fade) {
                messageDiv.fadeOut("fast", _done);
            }
            else {
                _done();
            }
        }
    };
    et2_baseWidget.prototype.detachFromDOM = function () {
        // Detach this node from the tooltip node
        if (this._tooltipElem) {
            this.egw().tooltipUnbind(this._tooltipElem);
            this._tooltipElem = null;
        }
        // Remove the binding to the click handler
        if (this.node) {
            jQuery(this.node).unbind("click.et2_baseWidget");
        }
        return _super.prototype.detachFromDOM.call(this);
    };
    et2_baseWidget.prototype.attachToDOM = function () {
        var ret = _super.prototype.attachToDOM.call(this);
        // Add the binding for the click handler
        if (this.node) {
            jQuery(this.node).bind("click.et2_baseWidget", this, function (e) {
                return e.data.click.call(e.data, e, this);
            });
            if (typeof this.onclick == 'function')
                jQuery(this.node).addClass('et2_clickable');
        }
        // Update the statustext
        this.set_statustext(this.statustext);
        return ret;
    };
    et2_baseWidget.prototype.setDOMNode = function (_node) {
        if (_node != this.node) {
            // Deatch the old node from the DOM
            this.detachFromDOM();
            // Set the new DOM-Node
            this.node = _node;
            // Attatch the DOM-Node to the tree
            return this.attachToDOM();
        }
        return false;
    };
    et2_baseWidget.prototype.getDOMNode = function (_sender) {
        return this.node;
    };
    et2_baseWidget.prototype.getTooltipElement = function () {
        return this.getDOMNode(this);
    };
    /**
     * Click handler calling custom handler set via onclick attribute to this.onclick
     *
     * @param _ev
     * @returns
     */
    et2_baseWidget.prototype.click = function (_ev) {
        if (typeof this.onclick == 'function') {
            // Make sure function gets a reference to the widget, splice it in as 2. argument if not
            var args = Array.prototype.slice.call(arguments);
            if (args.indexOf(this) == -1)
                args.splice(1, 0, this);
            return this.onclick.apply(this, args);
        }
        return true;
    };
    et2_baseWidget.prototype.set_statustext = function (_value) {
        // Tooltip should not be shown in mobile view
        if (egwIsMobile())
            return;
        // Don't execute the code below, if no tooltip will be attached/detached
        if (_value == "" && !this._tooltipElem) {
            return;
        }
        // allow statustext to contain multiple translated sub-strings eg: {Firstname}.{Lastname}
        if (_value.indexOf('{') !== -1) {
            var egw = this.egw();
            _value = _value.replace(/{([^}]+)}/g, function (str, p1) {
                return egw.lang(p1);
            });
        }
        this.statustext = _value;
        //Get the domnode the tooltip should be attached to
        var elem = jQuery(this.getTooltipElement());
        if (elem) {
            //If a tooltip is already attached to the element, remove it first
            if (this._tooltipElem) {
                this.egw().tooltipUnbind(this._tooltipElem);
                this._tooltipElem = null;
            }
            if (_value && _value != '') {
                this.egw().tooltipBind(elem, _value, this.options.statustext_html);
                this._tooltipElem = elem;
            }
        }
    };
    et2_baseWidget.prototype.set_align = function (_value) {
        this.align = _value;
    };
    et2_baseWidget.prototype.get_align = function () {
        return this.align;
    };
    et2_baseWidget._attributes = {
        "statustext": {
            "name": "Tooltip",
            "type": "string",
            "description": "Tooltip which is shown for this element",
            "translate": true
        },
        "statustext_html": {
            "name": "Tooltip is html",
            "type": "boolean",
            "description": "Flag to allow html content in tooltip",
            "default": false
        },
        "align": {
            "name": "Align",
            "type": "string",
            "default": "left",
            "description": "Position of this element in the parent hbox"
        },
        "onclick": {
            "name": "onclick",
            "type": "js",
            "default": et2_no_init,
            "description": "JS code which is executed when the element is clicked."
        }
    };
    return et2_baseWidget;
}(et2_core_DOMWidget_1.et2_DOMWidget));
exports.et2_baseWidget = et2_baseWidget;
/**
 * Simple container object
 */
var et2_container = /** @class */ (function (_super) {
    __extends(et2_container, _super);
    /**
     * Constructor
     */
    function et2_container(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_core_DOMWidget_1.et2_DOMWidget._attributes, _child || {})) || this;
    }
    /**
     * The destroy function destroys all children of the widget, removes itself
     * from the parents children list.
     * Overriden to not try to remove self from parent, as that's not possible.
     */
    et2_container.prototype.destroy = function () {
        // Call the destructor of all children
        for (var i = this._children.length - 1; i >= 0; i--) {
            this._children[i].destroy();
        }
        // Free the array managers if they belong to this widget
        for (var key in this._mgrs) {
            if (this._mgrs[key] && this._mgrs[key].owner == this) {
                this._mgrs[key].destroy();
            }
        }
    };
    return et2_container;
}(et2_baseWidget));
/**
 * Container object for not-yet supported widgets
 *
 * @augments et2_baseWidget
 */
var et2_placeholder = /** @class */ (function (_super) {
    __extends(et2_placeholder, _super);
    /**
     * Constructor
     */
    function et2_placeholder(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_core_DOMWidget_1.et2_DOMWidget._attributes, _child || {})) || this;
        _this.visible = false;
        _this.attrNodes = {};
        // Create the placeholder div
        _this.placeDiv = jQuery(document.createElement("span"))
            .addClass("et2_placeholder");
        var headerNode = jQuery(document.createElement("span"))
            .text(_this.getType() || "")
            .addClass("et2_caption")
            .appendTo(_this.placeDiv);
        var attrsCntr = jQuery(document.createElement("span"))
            .appendTo(_this.placeDiv)
            .hide();
        headerNode.click(_this, function (e) {
            e.data.visible = !e.data.visible;
            if (e.data.visible) {
                attrsCntr.show();
            }
            else {
                attrsCntr.hide();
            }
        });
        for (var key in _this.options) {
            if (typeof _this.options[key] != "undefined") {
                if (typeof _this.attrNodes[key] == "undefined") {
                    _this.attrNodes[key] = jQuery(document.createElement("span"))
                        .addClass("et2_attr");
                    attrsCntr.append(_this.attrNodes[key]);
                }
                _this.attrNodes[key].text(key + "=" + _this.options[key]);
            }
        }
        _this.setDOMNode(_this.placeDiv[0]);
        return _this;
    }
    et2_placeholder.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value");
    };
    et2_placeholder.prototype.getDetachedNodes = function () {
        return [this.placeDiv[0]];
    };
    et2_placeholder.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.placeDiv = jQuery(_nodes[0]);
    };
    return et2_placeholder;
}(et2_baseWidget));
