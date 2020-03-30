"use strict";
/**
 * EGroupware eTemplate2 - JS Progrss object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker
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
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the "progress" XET-Tag
 *
 * @augments et2_valueWidget
 */
var et2_progress = /** @class */ (function (_super) {
    __extends(et2_progress, _super);
    /**
     * Constructor
     *
     * @memberOf et2_progress
     */
    function et2_progress(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_progress._attributes, _child || {})) || this;
        _this.progress = null;
        var outer = document.createElement("div");
        outer.className = "et2_progress";
        _this.progress = document.createElement("div");
        _this.progress.style.width = "0";
        outer.appendChild(_this.progress);
        if (_this.options.href) {
            outer.className += ' et2_clickable';
        }
        if (_this.options["class"]) {
            outer.className += ' ' + _this.options["class"];
        }
        _this.setDOMNode(outer); // set's this.node = outer
        return _this;
    }
    et2_progress.prototype.click = function (e) {
        _super.prototype.click.call(this, e);
        if (this.options.href) {
            this.egw().open_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
        }
    };
    // setting the value as width of the progress-bar
    et2_progress.prototype.set_value = function (_value) {
        _value = parseInt(_value) + "%"; // make sure we have percent attached
        this.progress.style.width = _value;
        if (!this.options.label)
            this.set_label(_value);
    };
    // set's label as title of this.node
    et2_progress.prototype.set_label = function (_value) {
        this.node.title = _value;
    };
    // set's class of this.node; preserve baseclasses et2_progress and if this.options.href is set et2_clickable
    et2_progress.prototype.set_class = function (_value) {
        var baseClass = "et2_progress";
        if (this.options.href) {
            baseClass += ' et2_clickable';
        }
        this.node.setAttribute('class', baseClass + ' ' + _value);
    };
    et2_progress.prototype.set_href = function (_value) {
        if (!this.isInTree()) {
            return false;
        }
        this.options.href = _value;
        if (_value) {
            jQuery(this.node).addClass('et2_clickable')
                .wrapAll('<a href="' + _value + '"></a>"');
            var href_1 = this.options.href;
            var popup_1 = this.options.extra_link_popup;
            var target_1 = this.options.extra_link_target;
            jQuery(this.node).parent().click(function (e) {
                egw.open_link(href_1, target_1, popup_1);
                e.preventDefault();
                return false;
            });
        }
        else if (jQuery(this.node).parent('a').length) {
            jQuery(this.node).removeClass('et2_clickable')
                .unwrap();
        }
        return true;
    };
    /**
     * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
     *
     * * @param {array} _attrs array to add further attributes to
     */
    et2_progress.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value", "label", "href");
    };
    et2_progress.prototype.getDetachedNodes = function () {
        return [this.node, this.progress];
    };
    et2_progress.prototype.setDetachedAttributes = function (_nodes, _values) {
        // Set the given DOM-Nodes
        this.node = _nodes[0];
        this.progress = _nodes[1];
        // Set the attributes
        if (_values["label"]) {
            this.set_label(_values["label"]);
        }
        if (_values["value"]) {
            this.set_value(_values["value"]);
        }
        else if (_values["label"]) {
            this.set_value(_values["label"]);
        }
        if (_values["href"]) {
            jQuery(this.node).addClass('et2_clickable');
            this.set_href(_values["href"]);
        }
    };
    et2_progress._attributes = {
        "href": {
            "name": "Link Target",
            "type": "string",
            "description": "Link URL, empty if you don't wan't to display a link."
        },
        "extra_link_target": {
            "name": "Link target",
            "type": "string",
            "default": "_self",
            "description": "Link target descriptor"
        },
        "extra_link_popup": {
            "name": "Popup",
            "type": "string",
            "description": "widthxheight, if popup should be used, eg. 640x480"
        },
        "label": {
            "name": "Label",
            "default": "",
            "type": "string",
            "description": "The label is displayed as the title.  The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
            "translate": true
        }
    };
    et2_progress.legacyOptions = ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"];
    return et2_progress;
}(et2_core_valueWidget_1.et2_valueWidget));
et2_core_widget_1.et2_register_widget(et2_progress, ["progress"]);
//# sourceMappingURL=et2_widget_progress.js.map