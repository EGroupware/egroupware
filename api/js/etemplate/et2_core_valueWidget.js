"use strict";
/**
 * EGroupware eTemplate2 - JS widget class with value attribute and auto loading
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
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_baseWidget;
*/
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
require("./et2_core_common");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * et2_valueWidget is the base class for et2_inputWidget - valueWidget introduces
 * the "value" attribute and automatically loads it from the "content" array
 * after loading from XML.
 */
var et2_valueWidget = /** @class */ (function (_super) {
    __extends(et2_valueWidget, _super);
    /**
     * Constructor
     */
    function et2_valueWidget(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_valueWidget._attributes, _child || {})) || this;
        _this.label = '';
        _this._labelContainer = null;
        return _this;
    }
    /**
     *
     * @param _attrs
     */
    et2_valueWidget.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
        if (this.id) {
            // Set the value for this element
            var contentMgr = this.getArrayMgr("content");
            if (contentMgr != null) {
                var val = contentMgr.getEntry(this.id, false, true);
                if (val !== null) {
                    _attrs["value"] = val;
                }
            }
            // Check for already inside namespace
            if (this._createNamespace() && this.getArrayMgr("content").perspectiveData.owner == this) {
                _attrs["value"] = this.getArrayMgr("content").data;
            }
        }
    };
    et2_valueWidget.prototype.set_label = function (_value) {
        // Abort if there was no change in the label
        if (_value == this.label) {
            return;
        }
        if (_value) {
            // Create the label container if it didn't exist yet
            if (this._labelContainer == null) {
                this._labelContainer = jQuery(document.createElement("label"))
                    .addClass("et2_label");
                this.getSurroundings().insertDOMNode(this._labelContainer[0]);
            }
            // Clear the label container.
            this._labelContainer.empty();
            // Create the placeholder element and set it
            var ph = document.createElement("span");
            this.getSurroundings().setWidgetPlaceholder(ph);
            // Split the label at the "%s"
            var parts = et2_csvSplit(_value, 2, "%s");
            // Update the content of the label container
            for (var i = 0; i < parts.length; i++) {
                if (parts[i]) {
                    this._labelContainer.append(document.createTextNode(parts[i]));
                }
                if (i == 0) {
                    this._labelContainer.append(ph);
                }
            }
            // add class if label is empty
            this._labelContainer.toggleClass('et2_label_empty', !_value || !parts[0]);
        }
        else {
            // Delete the labelContainer from the surroundings object
            if (this._labelContainer) {
                this.getSurroundings().removeDOMNode(this._labelContainer[0]);
            }
            this._labelContainer = null;
        }
        // Update the surroundings in order to reflect the change in the label
        this.getSurroundings().update();
        // Copy the given value
        this.label = _value;
    };
    et2_valueWidget._attributes = {
        "label": {
            "name": "Label",
            "default": "",
            "type": "string",
            "description": "The label is displayed by default in front (for radiobuttons behind) each widget (if not empty). If you want to specify a different position, use a '%s' in the label, which gets replaced by the widget itself. Eg. '%s Name' to have the label Name behind a checkbox. The label can contain variables, as descript for name. If the label starts with a '@' it is replaced by the value of the content-array at this index (with the '@'-removed and after expanding the variables).",
            "translate": true
        },
        "value": {
            "name": "Value",
            "description": "The value of the widget",
            "type": "rawstring",
            "default": et2_no_init
        }
    };
    return et2_valueWidget;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_valueWidget = et2_valueWidget;
//# sourceMappingURL=et2_core_valueWidget.js.map