"use strict";
/**
 * EGroupware eTemplate2 - JS Diff object
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
    /vendor/bower-asset/jquery/dist/jquery.js;
    /vendor/bower-asset/jquery-ui/jquery-ui.js;
    /vendor/bower-asset/diff2html/dist/diff2html.min.js;
    et2_core_valueWidget;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
/**
 * Class that displays the diff between two [text] values
 *
 * @augments et2_valueWidget
 */
var et2_diff = /** @class */ (function (_super) {
    __extends(et2_diff, _super);
    /**
     * Constructor
     */
    function et2_diff(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_diff._attributes, _child || {})) || this;
        _this.mini = true;
        // included via etemplate2.css
        //this.egw().includeCSS('../../../vendor/bower-asset/dist/dist2html.css');
        _this.div = document.createElement("div");
        jQuery(_this.div).addClass('et2_diff');
        return _this;
    }
    et2_diff.prototype.set_value = function (value) {
        jQuery(this.div).empty();
        if (typeof value == 'string') {
            // Diff2Html likes to have files, we don't have them
            if (value.indexOf('---') !== 0) {
                value = "--- diff\n+++ diff\n" + value;
            }
            // @ts-ignore
            var diff = Diff2Html.getPrettyHtml(value, this.diff_options);
            //	var ui = new Diff2HtmlUI({diff: diff});
            //	ui.draw(jQuery(this.div), this.diff_options);
            jQuery(this.div).append(diff);
        }
        else if (typeof value != 'object') {
            jQuery(this.div).append(value);
        }
        this.check_mini();
    };
    et2_diff.prototype.check_mini = function () {
        if (!this.mini) {
            return false;
        }
        var view = jQuery(this.div).children();
        this.minify(view);
        var self = this;
        jQuery('<span class="ui-icon ui-icon-circle-plus">&nbsp;</span>')
            .appendTo(self.div)
            .css("cursor", "pointer")
            .click({ diff: view, div: self.div, label: self.options.label }, function (e) {
            var diff = e.data.diff;
            var div = e.data.div;
            self.un_minify(diff);
            var dialog_div = jQuery('<div>')
                .append(diff);
            dialog_div.dialog({
                title: e.data.label,
                width: 'auto',
                modal: true,
                buttons: [{ text: self.egw().lang('ok'), click: function () { jQuery(this).dialog("close"); } }],
                open: function () {
                    if (jQuery(this).parent().height() > jQuery(window).height()) {
                        jQuery(this).height(jQuery(window).height() * 0.7);
                    }
                    jQuery(this).addClass('et2_diff').dialog({ position: "center" });
                },
                close: function (event, ui) {
                    // Need to destroy the dialog, etemplate widget needs divs back where they were
                    dialog_div.dialog("destroy");
                    self.minify(this);
                    // Put it back where it came from, or et2 will error when clear() is called
                    diff.prependTo(div);
                }
            });
        });
    };
    et2_diff.prototype.set_label = function (_label) {
        this.options.label = _label;
    };
    /**
     * Make the diff into a mini-diff
     *
     * @param {DOMNode|String} view
     */
    et2_diff.prototype.minify = function (view) {
        view = jQuery(view)
            .addClass('mini')
            // Dialog changes these, if resized
            .width('100%').css('height', 'inherit')
            .show();
        jQuery('th', view).hide();
        jQuery('td.equal', view).hide()
            .prevAll().hide();
    };
    /**
     * Expand mini-diff
     *
     * @param {DOMNode|String} view
     */
    et2_diff.prototype.un_minify = function (view) {
        jQuery(view).removeClass('mini').show();
        jQuery('th', view).show();
        jQuery('td.equal', view).show();
    };
    /**
     * Code for implementing et2_IDetachedDOM
     * Fast-clonable read-only widget that only deals with DOM nodes, not the widget tree
     */
    /**
     * Build a list of attributes which can be set when working in the
     * "detached" mode in the _attrs array which is provided
     * by the calling code.
     *
     * @param {object} _attrs
     */
    et2_diff.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value", "label");
    };
    /**
     * Returns an array of DOM nodes. The (relativly) same DOM-Nodes have to be
     * passed to the "setDetachedAttributes" function in the same order.
     */
    et2_diff.prototype.getDetachedNodes = function () {
        return [this.div];
    };
    /**
     * Sets the given associative attribute->value array and applies the
     * attributes to the given DOM-Node.
     *
     * @param _nodes is an array of nodes which has to be in the same order as
     *      the nodes returned by "getDetachedNodes"
     * @param _values is an associative array which contains a subset of attributes
     *      returned by the "getDetachedAttributes" function and sets them to the
     *      given values.
     */
    et2_diff.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.div = _nodes[0];
        if (typeof _values['label'] != 'undefined') {
            this.set_label(_values['label']);
        }
        if (typeof _values['value'] != 'undefined') {
            this.set_value(_values['value']);
        }
    };
    et2_diff._attributes = {
        "value": {
            "type": "any"
        }
    };
    return et2_diff;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_diff = et2_diff;
et2_core_widget_1.et2_register_widget(et2_diff, ["diff"]);
//# sourceMappingURL=et2_widget_diff.js.map