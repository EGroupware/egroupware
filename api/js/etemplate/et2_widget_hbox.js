"use strict";
/**
 * EGroupware eTemplate2 - JS HBox object
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
    et2_core_baseWidget;
*/
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
/**
 * Class which implements hbox tag
 *
 * @augments et2_baseWidget
 */
var et2_hbox = /** @class */ (function (_super) {
    __extends(et2_hbox, _super);
    /**
     * Constructor
     *
     * @memberOf et2_hbox
     */
    function et2_hbox(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_hbox._attributes, _child || {})) || this;
        _this.alignData = {
            "hasAlign": false,
            "hasLeft": false,
            "hasCenter": false,
            "hasRight": false,
            "lastAlign": "left"
        };
        _this.leftDiv = null;
        _this.rightDiv = null;
        _this.div = null;
        _this.leftDiv = null;
        _this.rightDiv = null;
        _this.div = jQuery(document.createElement("div"))
            .addClass("et2_" + _super.prototype.getType.call(_this))
            .addClass("et2_box_widget");
        _super.prototype.setDOMNode.call(_this, _this.div[0]);
        return _this;
    }
    et2_hbox.prototype._createNamespace = function () {
        return true;
    };
    et2_hbox.prototype._buildAlignCells = function () {
        if (this.alignData.hasAlign) {
            // Check whether we have more than one type of align
            var mto = (this.alignData.hasLeft && this.alignData.hasRight) ||
                (this.alignData.hasLeft && this.alignData.hasCenter) ||
                (this.alignData.hasCenter && this.alignData.hasRight);
            if (!mto) {
                // If there is only one type of align, we simply have to set
                // the align of the top container
                if (this.alignData.lastAlign != "left") {
                    this.div.addClass("et2_hbox_al_" + this.alignData.lastAlign);
                }
            }
            else {
                // Create an additional container for elements with align type
                // "right"
                if (this.alignData.hasRight) {
                    this.rightDiv = jQuery(document.createElement("div"))
                        .addClass("et2_hbox_right")
                        .appendTo(this.div);
                }
                // Create an additional container for elements with align type
                // left, as the top container is used for the centered elements
                if (this.alignData.hasCenter) {
                    // Create the left div if an element is centered
                    this.leftDiv = jQuery(document.createElement("div"))
                        .addClass("et2_hbox_left")
                        .appendTo(this.div);
                    this.div.addClass("et2_hbox_al_center");
                }
            }
        }
    };
    /**
     * The overwritten loadFromXML function checks whether any child element has
     * a special align value.
     *
     * @param {object} _node
     */
    et2_hbox.prototype.loadFromXML = function (_node) {
        // Check whether any child node has an alignment tag
        et2_filteredNodeIterator(_node, function (_node) {
            var align = _node.getAttribute("align");
            if (!align) {
                align = "left";
            }
            if (align != "left") {
                this.alignData.hasAlign = true;
            }
            this.alignData.lastAlign = align;
            switch (align) {
                case "left":
                    this.alignData.hasLeft = true;
                    break;
                case "right":
                    this.alignData.hasRight = true;
                    break;
                case "center":
                    this.alignData.hasCenter = true;
                    break;
            }
        }, this);
        // Build the align cells
        this._buildAlignCells();
        // Load the nodes as usual
        _super.prototype.loadFromXML.call(this, _node);
    };
    et2_hbox.prototype.assign = function (_obj) {
        // Copy the align data and the cells from the object which should be
        // assigned
        this.alignData = et2_cloneObject(_obj.alignData);
        this._buildAlignCells();
        // Call the inherited assign function
        _super.prototype.assign.call(this, _obj);
    };
    et2_hbox.prototype.getDOMNode = function (_sender) {
        // Return a special align container if this hbox needs it
        if (_sender != this && this.alignData.hasAlign) {
            // Check whether we've create a special container for the widget
            var align = (_sender.implements(et2_IAligned) ?
                _sender.get_align() : "left");
            if (align == "left" && this.leftDiv != null) {
                return this.leftDiv[0];
            }
            if (align == "right" && this.rightDiv != null) {
                return this.rightDiv[0];
            }
        }
        // Normally simply return the hbox-div
        return _super.prototype.getDOMNode.call(this, _sender);
    };
    /**
     * Tables added to the root node need to be inline instead of blocks
     *
     * @param {et2_widget} child child-widget to add
     */
    et2_hbox.prototype.addChild = function (child) {
        _super.prototype.addChild.call(this, child);
        if (child.instanceOf && child.instanceOf(et2_grid) && this.isAttached() || child._type == 'et2_grid' && this.isAttached()) {
            jQuery(child.getDOMNode(child)).css("display", "inline-table");
        }
    };
    return et2_hbox;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_hbox, ["hbox"]);
//# sourceMappingURL=et2_widget_hbox.js.map