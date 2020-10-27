"use strict";
/**
 * EGroupware eTemplate2 - JS Box object
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
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
/**
 * Class which implements box and vbox tag
 *
 * Auto-repeat: In order to get box auto repeat to work we need to have another
 * box as a wrapper with an id set.
 *
 * @augments et2_baseWidget
 */
var et2_box = /** @class */ (function (_super) {
    __extends(et2_box, _super);
    /**
     * Constructor
     *
     * @memberOf et2_box
     */
    function et2_box(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, _child) || this;
        _this.div = jQuery(document.createElement("div"))
            .addClass("et2_" + _this.getType())
            .addClass("et2_box_widget");
        _this.setDOMNode(_this.div[0]);
        return _this;
    }
    et2_box.prototype._createNamespace = function () {
        return true;
    };
    /**
     * Overriden so we can check for autorepeating children.  We only check for
     * $ in the immediate children & grandchildren of this node.
     *
     * @param {object} _node
     */
    et2_box.prototype.loadFromXML = function (_node) {
        if (this.getType() != "box") {
            return _super.prototype.loadFromXML.call(this, _node);
        }
        // Load the child nodes.
        var childIndex = 0;
        var repeatNode = null;
        for (var i = 0; i < _node.childNodes.length; i++) {
            var node = _node.childNodes[i];
            var widgetType = node.nodeName.toLowerCase();
            if (widgetType == "#comment") {
                continue;
            }
            if (widgetType == "#text") {
                if (node.data.replace(/^\s+|\s+$/g, '')) {
                    this.loadContent(node.data);
                }
                continue;
            }
            // Create the new element, if no expansion needed
            var id = et2_readAttrWithDefault(node, "id", "");
            if (id.indexOf('$') < 0 || ['box', 'grid'].indexOf(widgetType) == -1) {
                this.createElementFromNode(node);
                childIndex++;
            }
            else {
                repeatNode = node;
            }
        }
        // Only the last child repeats(?)
        if (repeatNode != null) {
            var currentPerspective = this.getArrayMgr("content").perspectiveData;
            // Extra content
            for (childIndex; typeof this.getArrayMgr("content").data[childIndex] != "undefined" && this.getArrayMgr("content").data[childIndex]; childIndex++) {
                // Adjust for the row
                var mgrs = this.getArrayMgrs();
                for (var name in mgrs) {
                    if (this.getArrayMgr(name).getEntry(childIndex)) {
                        this.getArrayMgr(name).setRow(childIndex);
                    }
                }
                this.createElementFromNode(repeatNode);
            }
            // Reset
            for (var name in this.getArrayMgrs()) {
                this.getArrayMgr(name).setPerspectiveData(currentPerspective);
            }
        }
    };
    /**
     * Code for implementing et2_IDetachedDOM
     * This doesn't need to be implemented.
     * Individual widgets are detected and handled by the grid, but the interface is needed for this to happen
     *
     * @param {array} _attrs array to add further attributes to
     */
    et2_box.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push('data');
    };
    et2_box.prototype.getDetachedNodes = function () {
        return [this.getDOMNode()];
    };
    et2_box.prototype.setDetachedAttributes = function (_nodes, _values) {
        if (_values.data) {
            var pairs = _values.data.split(/,/g);
            for (var i = 0; i < pairs.length; ++i) {
                var name_value = pairs[i].split(':');
                jQuery(_nodes[0]).attr('data-' + name_value[0], name_value[1]);
            }
        }
    };
    et2_box._attributes = {
        // Not needed
        "rows": { "ignore": true },
        "cols": { "ignore": true }
    };
    return et2_box;
}(et2_core_baseWidget_1.et2_baseWidget));
exports.et2_box = et2_box;
et2_core_widget_1.et2_register_widget(et2_box, ["vbox", "box"]);
/**
 * Details widget implementation
 * widget name is "details" and can be use as a wrapping container
 * in order to make its children collapsible.
 *
 * Note: details widget does not represent html5 "details" tag in DOM
 *
 * <details>
 *		<widgets>
 *		....
 * <details/>
 *
 */
var et2_details = /** @class */ (function (_super) {
    __extends(et2_details, _super);
    function et2_details(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, _child) || this;
        _this.div = jQuery(document.createElement('div')).addClass('et2_details');
        _this.title = jQuery(document.createElement('span'))
            .addClass('et2_label et2_details_title')
            .appendTo(_this.div);
        _this.span = jQuery(document.createElement('span'))
            .addClass('et2_details_toggle')
            .appendTo(_this.div);
        _this.wrapper = jQuery(document.createElement('div'))
            .addClass('et2_details_wrapper')
            .appendTo(_this.div);
        _this._createWidget();
        return _this;
    }
    /**
     * Function happens on toggle action
     */
    et2_details.prototype._toggle = function () {
        this.div.toggleClass('et2_details_expanded');
    };
    /**
     * Create widget, set contents, and binds handlers
     */
    et2_details.prototype._createWidget = function () {
        var self = this;
        this.span.on('click', function (e) {
            self._toggle();
        });
        //Set header title
        if (this.options.title) {
            this.title
                .click(function () {
                self._toggle();
            })
                .text(this.options.title);
        }
        // Align toggle button left/right
        if (this.options.toggle_align === "left")
            this.span.css({ float: 'left' });
    };
    et2_details.prototype.getDOMNode = function (_sender) {
        if (!_sender || _sender === this) {
            return this.div[0];
        }
        else {
            return this.wrapper[0];
        }
    };
    et2_details._attributes = {
        "toggle_align": {
            name: "Toggle button alignment",
            description: " Defines where to align the toggle button, default is right alignment",
            type: "string",
            default: "right"
        },
        title: {
            name: "title",
            description: "Set a header title for box and shows it next to toggle button, default is no title",
            type: "string",
            default: "",
            translate: true
        }
    };
    return et2_details;
}(et2_box));
exports.et2_details = et2_details;
et2_core_widget_1.et2_register_widget(et2_details, ["details"]);
//# sourceMappingURL=et2_widget_box.js.map