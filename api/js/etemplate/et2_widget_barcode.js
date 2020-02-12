"use strict";
/**
 * EGroupware eTemplate2 - JS barcode widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @copyright Stylite AG
 * @version $Id:$
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
    /api/js/jquery/barcode/jquery-barcode.min.js;
    et2_core_interfaces;
    et2_core_baseWidget;
*/
/**
 * This widget creates barcode out of a given text
 *
 * The widget can be created in the following ways:
 * <code>
 * var barcodeTag = et2_createWidget("barcode", {
 *	code_type:et2_barcode.TYPE_CSS,
 *	bgColor:"#FFFFFF",
 *	barColor:"#000000",
 *	format:et2_barcode.FORMAT_SVG,
 *	barWidth:"1",
 *	barHeight:"50"
 * });
 * </code>
 * Or by adding XET-tag in your template (.xet) file:
 * <code>
 * <barcode [attributes...]/>
 * </code>
 *
 * Further information about types and formats are defined in static part of the class at the end
 */
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
/**
 * Class which implements the "barcode" XET-Tag
 *
 */
var et2_barcode = /** @class */ (function (_super) {
    __extends(et2_barcode, _super);
    /**
     * Constructor
     */
    function et2_barcode(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_barcode._attributes, _child || {})) || this;
        _this.div = jQuery(document.createElement('div')).attr({ class: 'et2_barcode' });
        // Set domid
        _this.set_id(_this.id);
        _this.setDOMNode(_this.div[0]);
        _this.createWidget();
        return _this;
    }
    et2_barcode.prototype.createWidget = function () {
        this.settings = {
            output: this.options.format,
            bgColor: this.options.bgColor,
            color: this.options.barColor,
            barWidth: this.options.barWidth,
            barHeight: this.options.barHeight,
        };
        if (this.get_value()) {
            // @ts-ignore
            this.div.barcode(this.get_value(), this.options.code_type, this.settings);
        }
    };
    et2_barcode.prototype.set_value = function (_val) {
        if (typeof _val !== 'undefined') {
            this.value = _val;
            this.createWidget();
        }
    };
    et2_barcode.prototype.get_value = function () {
        return this.value;
    };
    // Class Constants
    /*
     * type const
     */
    et2_barcode.TYPE_CODEBAR = "codebar";
    et2_barcode.TYPE_CODE11 = "code11"; //(code 11)
    et2_barcode.TYPE_CODE39 = "code39"; //(code 39)
    et2_barcode.TYPE_CODE128 = "code128"; //(code 128)
    et2_barcode.TYPE_EAN8 = "ean8"; //(ean 8) - http://barcode-coder.com/en/ean-8-specification-101.html
    et2_barcode.TYPE_EAN13 = "ean13"; //(ean 13) - http://barcode-coder.com/en/ean-13-specification-102.html
    et2_barcode.TYPE_STD25 = "std25"; //(standard 2 of 5 - industrial 2 of 5) - http://barcode-coder.com/en/standard-2-of-5-specification-103.html
    et2_barcode.TYPE_INT25 = "int25"; //(interleaved 2 of 5)
    et2_barcode.TYPE_MSI = "msi";
    et2_barcode.TYPE_DATAMATRIX = "datamatrix"; //(ASCII + extended) - http://barcode-coder.com/en/datamatrix-specification-104.html
    /**
     * Formats consts
     */
    et2_barcode.FORMAT_CSS = "css";
    et2_barcode.FORMAT_SVG = "svg";
    et2_barcode.FORMAT_bmp = "bmp";
    et2_barcode.FORMAT_CANVAS = "canvas";
    et2_barcode._attributes = {
        "code_type": {
            "name": "code type",
            "type": "string",
            "default": et2_barcode.TYPE_DATAMATRIX,
            "description": "Barcode type to be generated, default is QR barcode"
        },
        bgColor: {
            "name": "bgColor",
            "type": "string",
            "default": '#FFFFFF',
            "description": "Defines backgorund color of barcode container"
        },
        barColor: {
            "name": "barColor",
            "type": "string",
            "default": '#000000',
            "description": "Defines color of the bars in barcode."
        },
        format: {
            "name": "format",
            "type": "string",
            "default": 'css',
            "description": "Defines in which format the barcode should be rendered. Default is SVG."
        },
        barWidth: {
            "name": "bar width",
            "type": "string",
            "default": '1',
            "description": "Defines width of each bar in the barcode."
        },
        barHeight: {
            "name": "bar height",
            "type": "string",
            "default": '50',
            "description": "Defines heigh of each bar in the barcode."
        },
    };
    return et2_barcode;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_barcode = et2_barcode;
et2_core_widget_1.et2_register_widget(et2_barcode, ["barcode"]);
//# sourceMappingURL=et2_widget_barcode.js.map