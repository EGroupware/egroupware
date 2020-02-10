"use strict";
/**
 * EGroupware eTemplate2 - JS Groupbox object
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
    et2_core_baseWidget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the groupbox tag
 *
 * @augments et2_baseWidget
 */
var et2_groupbox = /** @class */ (function (_super) {
    __extends(et2_groupbox, _super);
    /**
     * Constructor
     *
     * @memberOf et2_groupbox
     */
    function et2_groupbox(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_groupbox._attributes, _child || {})) || this;
        _this.setDOMNode(document.createElement("fieldset"));
        return _this;
    }
    return et2_groupbox;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_groupbox, ["groupbox"]);
/**
 * @augments et2_baseWidget
 */
var et2_groupbox_legend = /** @class */ (function (_super) {
    __extends(et2_groupbox_legend, _super);
    /**
     * Constructor
     *
     * @memberOf et2_groupbox_legend
     */
    function et2_groupbox_legend(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_groupbox_legend._attributes, _child || {})) || this;
        var legend = jQuery(document.createElement("legend")).text(_this.options.label);
        _this.setDOMNode(legend[0]);
        return _this;
    }
    et2_groupbox_legend._attributes = {
        "label": {
            "name": "Label",
            "type": "string",
            "default": "",
            "description": "Label for group box",
            "translate": true
        }
    };
    return et2_groupbox_legend;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_groupbox_legend, ["caption"]);
//# sourceMappingURL=et2_widget_groupbox.js.map