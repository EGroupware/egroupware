"use strict";
/**
 * EGroupware eTemplate2 - JS HRule object
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
    et2_core_baseWidget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the hrule tag
 *
 * @augments et2_baseWidget
 */
var et2_hrule = /** @class */ (function (_super) {
    __extends(et2_hrule, _super);
    /**
     * Constructor
     *
     * @memberOf et2_hrule
     */
    function et2_hrule(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_hrule._attributes, _child || {})) || this;
        _this.setDOMNode(document.createElement("hr"));
        return _this;
    }
    return et2_hrule;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_hrule, ["hrule"]);
//# sourceMappingURL=et2_widget_hrule.js.map