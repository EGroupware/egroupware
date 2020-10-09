"use strict";
/**
 * EGroupware eTemplate2 - JS widget class containing raw HTML
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
    jsapi.jsapi; // Needed for egw_seperateJavaScript
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_baseWidget;
*/
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * @augments et2_valueWidget
 */
var et2_html = /** @class */ (function (_super) {
    __extends(et2_html, _super);
    /**
     * Constructor
     *
     * @memberOf et2_html
     */
    function et2_html(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_html._attributes, _child || {})) || this;
        _this.htmlNode = null;
        // Allow no child widgets
        _this.supportedWidgetClasses = [];
        _this.htmlNode = jQuery(document.createElement("span"));
        if (_this.getType() == 'htmlarea') {
            _this.htmlNode.addClass('et2_textbox_ro');
        }
        if (_this.options.label) {
            _this.htmlNode.append('<span class="et2_label">' + _this.options.label + '</span>');
        }
        _this.setDOMNode(_this.htmlNode[0]);
        return _this;
    }
    et2_html.prototype.loadContent = function (_data) {
        // Create an object containg the given value and an empty js string
        var html = { html: _data ? _data : '', js: '' };
        // Seperate the javascript from the given html. The js code will be
        // written to the previously created empty js string
        egw_seperateJavaScript(html);
        // Append the html to the parent element
        if (this.options.label) {
            this.htmlNode.append('<span class="et2_label">' + this.options.label + '</span>');
        }
        this.htmlNode.append(html.html);
        this.htmlNode.append(html.js);
    };
    et2_html.prototype.set_value = function (_value) {
        this.htmlNode.empty();
        this.loadContent(_value);
    };
    /**
     * Code for implementing et2_IDetachedDOM
     *
     * @param {array} _attrs
     */
    et2_html.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value", "class");
    };
    et2_html.prototype.getDetachedNodes = function () {
        return [this.htmlNode[0]];
    };
    et2_html.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.htmlNode = jQuery(_nodes[0]);
        if (typeof _values['value'] !== 'undefined') {
            this.set_value(_values['value']);
        }
    };
    et2_html._attributes = {
        'label': {
            'default': "",
            description: "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
            ignore: false,
            name: "Label",
            translate: true,
            type: "string"
        },
        "needed": {
            "ignore": true
        },
        value: {
            name: "Value",
            description: "The value of the widget",
            type: "html",
            default: et2_no_init
        }
    };
    return et2_html;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_html = et2_html;
et2_core_widget_1.et2_register_widget(et2_html, ["html", "htmlarea_ro"]);
//# sourceMappingURL=et2_widget_html.js.map