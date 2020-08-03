"use strict";
/*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package
 * @subpackage
 * @link http://www.egroupware.org
 * @author Nathan Gray
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
    et2_widget_taglist;
*/
var et2_core_widget_1 = require("../../api/js/etemplate/et2_core_widget");
/**
 * Tag list widget customised for calendar owner, which can be a user
 * account or group, or an entry from almost any app, or an email address
 *
 * A cross between auto complete, selectbox and chosen multiselect
 *
 * Uses MagicSuggest library
 * @see http://nicolasbize.github.io/magicsuggest/
 * @augments et2_selectbox
 */
var et2_calendar_owner = /** @class */ (function (_super) {
    __extends(et2_calendar_owner, _super);
    function et2_calendar_owner() {
        var _this = _super !== null && _super.apply(this, arguments) || this;
        // Allows sub-widgets to override options to the library
        _this.lib_options = {
            autoSelect: false,
            groupBy: 'app',
            minChars: 2,
            selectFirst: true,
            // This option will also expand when the selection is changed
            // via code, which we do not want
            //expandOnFocus: true
            toggleOnClick: true
        };
        return _this;
    }
    et2_calendar_owner.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        var widget = this;
        // onChange fired when losing focus, which is different from normal
        this._oldValue = this.taglist.getValue();
        return true;
    };
    et2_calendar_owner.prototype.selectionRenderer = function (item) {
        if (this && this.options && this.options.allowFreeEntries) {
            return _super.prototype.selectionRenderer.call(this, item);
        }
        else {
            var label = jQuery('<span>').text(item.label);
            if (item.class)
                label.addClass(item.class);
            if (typeof item.title != 'undefined')
                label.attr('title', item.title);
            if (typeof item.data != 'undefined')
                label.attr('data', item.data);
            if (typeof item.icon != 'undefined') {
                var wrapper = jQuery('<div>').addClass('et2_taglist_tags_icon_wrapper');
                jQuery('<span/>')
                    .addClass('et2_taglist_tags_icon')
                    .css({ "background-image": "url(" + (item.icon.match(/^(http|https|\/)/) ? item.icon : egw.image(item.icon, item.app)) + ")" })
                    .appendTo(wrapper);
                label.appendTo(wrapper);
                return wrapper;
            }
            return label;
        }
    };
    et2_calendar_owner.prototype.getValue = function () {
        if (this.taglist == null)
            return null;
        return this.taglist.getValue();
    };
    /**
     * Override parent to handle our special additional data types (c#,r#,etc.) when they
     * are not available client side.
     *
     * @param {string|string[]} _value array of selected owners, which can be a number,
     *	or a number prefixed with one character indicating the resource type.
     */
    et2_calendar_owner.prototype.set_value = function (_value) {
        _super.prototype.set_value.call(this, _value);
        // If parent didn't find a label, label will be the same as ID so we
        // can find them that way
        var missing_labels = [];
        for (var i = 0; i < this.options.value.length; i++) {
            var value = this.options.value[i];
            if (value.id == value.label) {
                missing_labels.push(value.id);
            }
        }
        if (Object.keys(missing_labels).length > 0) {
            // Proper label was not found by parent - ask directly
            egw.json('calendar_owner_etemplate_widget::ajax_owner', [missing_labels], function (data) {
                var _loop_1 = function (owner) {
                    if (!owner || typeof owner == "undefined")
                        return "continue";
                    var idx = this_1.options.value.find(function (element) { return element.id == owner; });
                    if (idx) {
                        idx = jQuery.extend(idx, data[owner]);
                    }
                    // Put it in the list of options for next time
                    this_1.options.select_options.push(data[owner]);
                };
                var this_1 = this;
                for (var owner in data) {
                    _loop_1(owner);
                }
                this.set_value(this.options.value);
            }, this, true, this).sendRequest();
        }
        if (this.taglist) {
            this.taglist.clear(true);
            this.taglist.addToSelection(this.options.value, true);
        }
    };
    et2_calendar_owner._attributes = {
        "autocomplete_url": {
            "default": "calendar_owner_etemplate_widget::ajax_owner"
        },
        "autocomplete_params": {
            "name": "Autocomplete parameters",
            "type": "any",
            "default": {},
            "description": "Extra parameters passed to autocomplete URL.  It should be a stringified JSON object."
        },
        allowFreeEntries: {
            "default": false,
            ignore: true
        },
        select_options: {
            "type": "any",
            "name": "Select options",
            // Set to empty object to use selectbox's option finding
            "default": {},
            "description": "Internally used to hold the select options."
        }
    };
    return et2_calendar_owner;
}(et2_taglist_email));
exports.et2_calendar_owner = et2_calendar_owner;
et2_core_widget_1.et2_register_widget(et2_calendar_owner, ["calendar-owner"]);
//# sourceMappingURL=et2_widget_owner.js.map