"use strict";
/**
 * EGroupware eTemplate2 - JS widget class containing styles
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 * @copyright Stylite 2011
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
    et2_core_widget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Function which appends the encapsulated style data to the head tag of the
 * page.
 *
 * TODO: The style data could be parsed for rules and appended using the JS
 * stylesheet interface, allowing the style only to modifiy nodes of the current
 * template.
 *
 * @augments et2_widget
 */
var et2_styles = /** @class */ (function (_super) {
    __extends(et2_styles, _super);
    /**
     * Constructor
     *
     * @memberOf et2_styles
     */
    function et2_styles(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_styles._attributes, _child || {})) || this;
        // Allow no child widgets
        _this.supportedWidgetClasses = [];
        // Create the style node and append it to the head node
        _this.styleNode = document.createElement("style");
        _this.styleNode.setAttribute("type", "text/css");
        _this.head = _this.egw().window.document.getElementsByTagName("head")[0];
        _this.head.appendChild(_this.styleNode);
        return _this;
    }
    et2_styles.prototype.destroy = function () {
        // Remove the style node again and delete any reference to it
        this.head.removeChild(this.styleNode);
        _super.prototype.destroy.call(this);
    };
    et2_styles.prototype.loadContent = function (_content) {
        // @ts-ignore
        if (this.styleNode.styleSheet) {
            // IE
            // @ts-ignore
            this.styleNode.styleSheet.cssText += _content;
        }
        else {
            this.styleNode.appendChild(document.createTextNode(_content));
        }
    };
    /**
     * Sets the id of the DOM-Node.
     *
     * DOM id's have dots "." replaced with dashes "-"
     *
     * @param {string} _value id to set
     */
    et2_styles.prototype.set_id = function (_value) {
        this.id = _value;
        this.dom_id = _value ? this.getInstanceManager().uniqueId + '_' + _value.replace(/\./g, '-') : _value;
        if (this.styleNode) {
            if (_value != "") {
                this.styleNode.setAttribute("id", this.dom_id);
            }
            else {
                this.styleNode.removeAttribute("id");
            }
        }
    };
    return et2_styles;
}(et2_core_widget_1.et2_widget));
exports.et2_styles = et2_styles;
et2_core_widget_1.et2_register_widget(et2_styles, ["styles"]);
//# sourceMappingURL=et2_widget_styles.js.map