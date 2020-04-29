"use strict";
/**
 * EGroupware eTemplate2 - JS widget class for an iframe
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2013
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
        et2_core_valueWidget;
*/
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * @augments et2_valueWidget
 */
var et2_iframe = /** @class */ (function (_super) {
    __extends(et2_iframe, _super);
    /**
     * Constructor
     *
     * @memberOf et2_iframe
     */
    function et2_iframe(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_iframe._attributes, _child || {})) || this;
        _this.htmlNode = null;
        // Allow no child widgets
        _this.supportedWidgetClasses = [];
        _this.htmlNode = jQuery(document.createElement("iframe"));
        if (_this.options.label) {
            _this.htmlNode.append('<span class="et2_label">' + _this.options.label + '</span>');
        }
        if (_this.options.fullscreen) {
            _this.htmlNode.attr('allowfullscreen', 1);
        }
        _this.setDOMNode(_this.htmlNode[0]);
        return _this;
    }
    /**
     * Set name of iframe (to be used as target for links)
     *
     * @param _name
     */
    et2_iframe.prototype.set_name = function (_name) {
        this.options.name = _name;
        this.htmlNode.attr('name', _name);
    };
    et2_iframe.prototype.set_allow = function (_allow) {
        this.options.allow = _allow;
        this.htmlNode.attr('allow', _allow);
    };
    /**
     * Make it look like part of the containing document
     *
     * @param _seamless boolean
     */
    et2_iframe.prototype.set_seamless = function (_seamless) {
        this.options.seamless = _seamless;
        this.htmlNode.attr("seamless", _seamless);
    };
    et2_iframe.prototype.set_value = function (_value) {
        if (typeof _value == "undefined")
            _value = "";
        if (_value.trim().indexOf("http") == 0 || _value.indexOf('about:') == 0 || _value[0] == '/') {
            // Value is a URL
            this.set_src(_value);
        }
        else {
            // Value is content
            this.set_srcdoc(_value);
        }
    };
    /**
     * Set the URL for the iframe
     *
     * Sets the src attribute to the given value
     *
     * @param _value String URL
     */
    et2_iframe.prototype.set_src = function (_value) {
        if (_value.trim() != "") {
            if (_value.trim() == 'about:blank') {
                this.htmlNode.attr("src", _value);
            }
            else {
                // Load the new page, but display a loader
                var loader_1 = jQuery('<div class="et2_iframe loading"/>');
                this.htmlNode
                    .before(loader_1);
                window.setTimeout(jQuery.proxy(function () {
                    this.htmlNode.attr("src", _value)
                        .one('load', function () {
                        loader_1.remove();
                    });
                }, this), 0);
            }
        }
    };
    /**
     * Sets the content of the iframe
     *
     * Sets the srcdoc attribute to the given value
     *
     * @param _value String Content of a document
     */
    et2_iframe.prototype.set_srcdoc = function (_value) {
        this.htmlNode.attr("srcdoc", _value);
    };
    et2_iframe._attributes = {
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
        "seamless": {
            name: "Seamless",
            'default': true,
            description: "Specifies that the iframe should be rendered in a manner that makes it appear to be part of the containing document",
            translate: false,
            type: "boolean"
        },
        "name": {
            name: "Name",
            "default": "",
            description: "Specifies name of frame, to be used as target for links",
            type: "string"
        },
        fullscreen: {
            name: "Fullscreen",
            "default": false,
            description: "Make the iframe compatible to be a fullscreen video player mode",
            type: "boolean"
        },
        src: {
            name: "Source",
            "default": "",
            description: "Specifies URL for the iframe",
            type: "string"
        },
        allow: {
            name: "Allow",
            "default": "",
            description: "Specifies list of allow features, e.g. camera",
            type: "string"
        }
    };
    return et2_iframe;
}(et2_core_valueWidget_1.et2_valueWidget));
et2_core_widget_1.et2_register_widget(et2_iframe, ["iframe"]);
//# sourceMappingURL=et2_widget_iframe.js.map