"use strict";
/**
 * EGroupware eTemplate2 - JS Itempicker object
 * derived from et2_link_entry widget @copyright 2011 Nathan Gray
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Christian Binder
 * @author Nathan Gray
 * @copyright 2012 Christian Binder
 * @copyright 2011 Nathan Gray
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
    et2_core_inputWidget;
    et2_core_valueWidget;
    et2_extension_itempicker_actions;
    egw_action.egw_action_common;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the "itempicker" XET-Tag
 *
 * @augments et2_inputWidget
 */
var et2_itempicker = /** @class */ (function (_super) {
    __extends(et2_itempicker, _super);
    /**
     * Constructor
     *
     * @memberOf et2_itempicker
     */
    function et2_itempicker(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_itempicker._attributes, _child || {})) || this;
        _this.last_search = ""; // Remember last search value
        _this.action = null; // Action function for button
        _this.current_app = ""; // Remember currently chosen application
        _this.div = null;
        _this.left = null;
        _this.right = null;
        _this.right_container = null;
        _this.app_select = null;
        _this.search = null;
        _this.button_action = null;
        _this.itemlist = null;
        _this.div = null;
        _this.left = null;
        _this.right = null;
        _this.right_container = null;
        _this.app_select = null;
        _this.search = null;
        _this.button_action = null;
        _this.itemlist = null;
        if (_this.options.action !== null && typeof _this.options.action == "string") {
            _this.action = new egwFnct(_this, "javaScript:" + _this.options.action);
        }
        else {
            console.log("itempicker widget: no action provided for button");
        }
        _this.createInputWidget();
        return _this;
    }
    et2_itempicker.prototype.clearSearchResults = function () {
        this.search.val("");
        this.itemlist.html("");
        this.search.focus();
        this.clear.hide();
    };
    et2_itempicker.prototype.createInputWidget = function () {
        var _self = this;
        this.div = jQuery(document.createElement("div"));
        this.left = jQuery(document.createElement("div"));
        this.right = jQuery(document.createElement("div"));
        this.right_container = jQuery(document.createElement("div"));
        this.app_select = jQuery(document.createElement("ul"));
        this.search = jQuery(document.createElement("input"));
        this.clear = jQuery(document.createElement("span"));
        this.itemlist = jQuery(document.createElement("div"));
        // Container elements
        this.div.addClass("et2_itempicker");
        this.left.addClass("et2_itempicker_left");
        this.right.addClass("et2_itempicker_right");
        this.right_container.addClass("et2_itempicker_right_container");
        // Application select
        this.app_select.addClass("et2_itempicker_app_select");
        var item_count = 0;
        for (var key in this.options.select_options) {
            var img_icon = this.egw().image(key + "/navbar");
            if (img_icon === null) {
                continue;
            }
            var img = jQuery(document.createElement("img"));
            img.attr("src", img_icon);
            var item = jQuery(document.createElement("li"));
            item.attr("id", key)
                .click(function () {
                _self.selectApplication(jQuery(this));
            })
                .append(img);
            if (item_count == 0) {
                this.selectApplication(item); // select first item by default
            }
            this.app_select.append(item);
            item_count++;
        }
        // Search input field
        this.search.addClass("et2_itempicker_search");
        this.search.keyup(function () {
            var request = {};
            request.term = jQuery(this).val();
            _self.query(request);
        });
        this.set_blur(this.options.blur, this.search);
        // Clear button for search
        this.clear
            .addClass("et2_itempicker_clear ui-icon ui-icon-close")
            .click(function () {
            _self.clearSearchResults();
        })
            .hide();
        // Action button
        this.button_action = et2_createWidget("button");
        jQuery(this.button_action.getDOMNode()).addClass("et2_itempicker_button_action");
        this.button_action.set_label(this.egw().lang(this.options.action_label));
        this.button_action.click = function () { _self.doAction(); };
        // Itemlist
        this.itemlist.attr("id", "itempicker_itemlist");
        this.itemlist.addClass("et2_itempicker_itemlist");
        // Put everything together
        this.left.append(this.app_select);
        this.right_container.append(this.search);
        this.right_container.append(this.clear);
        this.right_container.append(this.button_action.getDOMNode());
        this.right_container.append(this.itemlist);
        this.right.append(this.right_container);
        this.div.append(this.right); // right before left to have a natural
        this.div.append(this.left); // z-index for left div over right div
        this.setDOMNode(this.div[0]);
    };
    et2_itempicker.prototype.doAction = function () {
        if (this.action !== null) {
            var data = {};
            data.app = this.current_app;
            data.value = this.options.value;
            data.checked = this.getSelectedItems();
            return this.action.exec(this, data);
        }
        return false;
    };
    et2_itempicker.prototype.getSelectedItems = function () {
        var items = [];
        jQuery(this.itemlist).children("ul").children("li.selected").each(function (index) {
            items[index] = jQuery(this).attr("id");
        });
        return items;
    };
    /**
     * Ask server for entries matching selected app/type and filtered by search string
     */
    et2_itempicker.prototype.query = function (request) {
        if (request.term.length < 3) {
            return true;
        }
        // Remember last search
        this.last_search = request.term;
        // Allow hook / tie in
        if (this.options.query && typeof this.options.query == 'function') {
            if (!this.options.query(request, response))
                return false;
        }
        //if(request.term in this.cache) {
        //	return response(this.cache[request.term]);
        //}
        this.itemlist.addClass("loading");
        this.clear.css("display", "inline-block");
        egw.json("EGroupware\\Api\\Etemplate\\Widget\\ItemPicker::ajax_item_search", [this.current_app, '', request.term, request.options], this.queryResults, this, true, this).sendRequest();
    };
    /**
     * Server found some results for query
     */
    et2_itempicker.prototype.queryResults = function (data) {
        this.itemlist.removeClass("loading");
        this.updateItemList(data);
    };
    et2_itempicker.prototype.selectApplication = function (app) {
        this.clearSearchResults();
        jQuery(".et2_itempicker_app_select li").removeClass("selected");
        app.addClass("selected");
        this.current_app = app.attr("id");
        return true;
    };
    et2_itempicker.prototype.set_blur = function (_value, input) {
        if (typeof input == 'undefined')
            input = this.search;
        if (_value) {
            input.attr("placeholder", _value); // HTML5
            if (!input[0].placeholder) {
                // Not HTML5
                if (input.val() == "")
                    input.val(_value);
                input.focus(input, function (e) {
                    var placeholder = _value;
                    if (e.data.val() == placeholder)
                        e.data.val("");
                }).blur(input, function (e) {
                    var placeholder = _value;
                    if (e.data.val() == "")
                        e.data.val(placeholder);
                });
                if (input.val() == "")
                    input.val(_value);
            }
        }
        else {
            this.search.removeAttr("placeholder");
        }
    };
    et2_itempicker.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
        _attrs["select_options"] = {};
        if (_attrs["application"]) {
            var apps = et2_csvSplit(_attrs["application"], null, ",");
            for (var i = 0; i < apps.length; i++) {
                _attrs["select_options"][apps[i]] = this.egw().lang(apps[i]);
            }
        }
        else {
            _attrs["select_options"] = this.egw().link_app_list('query');
        }
        // Check whether the options entry was found, if not read it from the
        // content array.
        if (_attrs["select_options"] == null) {
            _attrs["select_options"] = this.getArrayMgr('content')
                .getEntry("options-" + this.id);
        }
        // Default to an empty object
        if (_attrs["select_options"] == null) {
            _attrs["select_options"] = {};
        }
    };
    et2_itempicker.prototype.updateItemList = function (data) {
        var list = jQuery(document.createElement("ul"));
        var item_count = 0;
        for (var id in data) {
            var item = jQuery(document.createElement("li"));
            if (item_count % 2 == 0) {
                item.addClass("row_on");
            }
            else {
                item.addClass("row_off");
            }
            item.attr("id", id)
                .html(data[id])
                .click(function (e) {
                var _a;
                if (e.ctrlKey || e.metaKey) {
                    // add to selection
                    jQuery(this).addClass("selected");
                }
                else if (e.shiftKey) {
                    // select range
                    var start = jQuery(this).siblings(".selected").first();
                    if (((_a = start) === null || _a === void 0 ? void 0 : _a.length) == 0) {
                        // no start item - cannot select range - select single item
                        jQuery(this).addClass("selected");
                        return true;
                    }
                    var end = jQuery(this);
                    // swap start and end if start appears after end in dom hierarchy
                    if (start.index() > end.index()) {
                        var startOld = start;
                        start = end;
                        end = startOld;
                    }
                    // select start to end
                    start.addClass("selected");
                    start.nextUntil(end).addClass("selected");
                    end.addClass("selected");
                }
                else {
                    // select single item
                    jQuery(this).siblings(".selected").removeClass("selected");
                    jQuery(this).addClass("selected");
                }
            });
            list.append(item);
            item_count++;
        }
        this.itemlist.html(list);
    };
    et2_itempicker._attributes = {
        "action": {
            "name": "Action callback",
            "type": "string",
            "default": false,
            "description": "Callback for action.  Must be a function(context, data)"
        },
        "action_label": {
            "name": "Action label",
            "type": "string",
            "default": "Action",
            "description": "Label for action button"
        },
        "application": {
            "name": "Application",
            "type": "string",
            "default": "",
            "description": "Limit to the listed application or applications (comma separated)"
        },
        "blur": {
            "name": "Placeholder",
            "type": "string",
            "default": et2_no_init,
            "description": "This text get displayed if an input-field is empty and does not have the input-focus (blur). It can be used to show a default value or a kind of help-text."
        },
        "value": {
            "name": "value",
            "type": "any",
            "default": "",
            "description": "Optional itempicker value(s) - can be used for e.g. environmental information"
        },
        "query": {
            "name": "Query callback",
            "type": "any",
            "default": false,
            "description": "Callback before query to server.  Must return true, or false to abort query."
        }
    };
    et2_itempicker.legacyOptions = ["application"];
    return et2_itempicker;
}(et2_core_inputWidget_1.et2_inputWidget));
et2_core_widget_1.et2_register_widget(et2_itempicker, ["itempicker"]);
//# sourceMappingURL=et2_widget_itempicker.js.map