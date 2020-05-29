"use strict";
/**
 * EGroupware - VFS SELECT Widget UI
 *
 * @link http://www.egroupware.org
 * @package et2_vfsSelect
 * @author Hadi Nategh <hn@egroupware.org>
 * @copyright (c) 2013-2017 by Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
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
    /api/js/jsapi/egw_app.js
 */
var egw_app_1 = require("../jsapi/egw_app");
require("../jsapi/egw_global");
var et2_widget_dialog_1 = require("./et2_widget_dialog");
var et2_widget_file_1 = require("./et2_widget_file");
var et2_widget_textbox_1 = require("./et2_widget_textbox");
var et2_widget_checkbox_1 = require("./et2_widget_checkbox");
/**
 * UI for VFS Select widget
 *
 */
var vfsSelectUI = /** @class */ (function (_super) {
    __extends(vfsSelectUI, _super);
    /**
     * Constructor
     *
     */
    function vfsSelectUI() {
        var _this = 
        // call parent
        _super.call(this, 'vfsSelectUI') || this;
        _this.egw.langRequireApp(_this.egw.window, 'filemanager');
        return _this;
    }
    /**
     * Destructor
     */
    vfsSelectUI.prototype.destroy = function (_app) {
        delete this.path_widget;
        delete this.vfsSelectWidget;
        // call parent
        _super.prototype.destroy.call(this, _app);
    };
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  If you must store a reference to the et2 object,
     * make sure to clean it up in destroy().
     *
     * @param et2 etemplate2 Newly ready object
     * @param {string} name template name
     */
    vfsSelectUI.prototype.et2_ready = function (et2, name) {
        this.path_widget = this.et2.getWidgetById('path');
        this.dirContent = this.et2.getArrayMgr('content').data.dir;
    };
    /**
     * Get directory of a path
     *
     * @param {string} _path
     * @returns string
     */
    vfsSelectUI.prototype.dirname = function (_path) {
        var parts = _path.split('/');
        parts.pop();
        return parts.join('/') || '/';
    };
    /**
     * Get name of a path
     *
     * @param {string} _path
     * @returns string
     */
    vfsSelectUI.prototype.basename = function (_path) {
        return _path.split('/').pop();
    };
    /**
     * Get current working directory
     *
     * @return string
     */
    vfsSelectUI.prototype.get_path = function () {
        return this.path_widget.get_value();
    };
    /**
     * Send names of uploaded files (again) to server,
     * to process them: either copy to vfs or ask overwrite/rename
     *
     * @param {event} _event
     */
    vfsSelectUI.prototype.storeFile = function (_event) {
        var path = this.get_path();
        if (!jQuery.isEmptyObject(_event.data.getValue())) {
            var widget = _event.data;
            egw(window).json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_storeFile', [widget.getValue(), path], this._storeFile_callback, this, true, this).sendRequest(true);
            widget.set_value('');
        }
    };
    /**
     * Callback for server response to storeFile request:
     * - display message and refresh list
     * - ask use to confirm overwritting existing files or rename upload
     *
     * @param {object} _data values for attributes msg, files, ...
     */
    vfsSelectUI.prototype._storeFile_callback = function (_data) {
        if (_data.msg || _data.uploaded)
            egw(window).message(_data.msg);
        var that = this;
        for (var file in _data.uploaded) {
            if (_data.uploaded[file].confirm && !_data.uploaded[file].confirmed) {
                var buttons = [
                    { text: this.egw.lang("Yes"), id: "overwrite", class: "ui-priority-primary", "default": true, image: 'check' },
                    { text: this.egw.lang("Rename"), id: "rename", image: 'edit' },
                    { text: this.egw.lang("Cancel"), id: "cancel" }
                ];
                if (_data.uploaded[file].confirm === "is_dir")
                    buttons.shift();
                var dialog = et2_widget_dialog_1.et2_dialog.show_prompt(function (_button_id, _value) {
                    var uploaded = {};
                    uploaded[this.my_data.file] = this.my_data.data;
                    switch (_button_id) {
                        case "overwrite":
                            uploaded[this.my_data.file].confirmed = true;
                        // fall through
                        case "rename":
                            uploaded[this.my_data.file].name = _value;
                            delete uploaded[this.my_data.file].confirm;
                            // send overwrite-confirmation and/or rename request to server
                            egw.json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_storeFile', [uploaded, this.my_data.path], that._storeFile_callback, that, true, that).sendRequest();
                            return;
                        case "cancel":
                            // Remove that file from every file widget...
                            that.et2.iterateOver(function (_widget) {
                                _widget.remove_file(this.my_data.data.name);
                            }, this, et2_widget_file_1.et2_file);
                    }
                }, _data.uploaded[file].confirm === "is_dir" ?
                    this.egw.lang("There's already a directory with that name!") :
                    this.egw.lang('Do you want to overwrite existing file %1 in directory %2?', _data.uploaded[file].name, _data.path), this.egw.lang('File %1 already exists', _data.uploaded[file].name), _data.uploaded[file].name, buttons, file);
                // setting required data for callback in as my_data
                dialog.my_data = {
                    file: file,
                    path: _data.path,
                    data: _data.uploaded[file],
                };
            }
            else {
                this.submit();
            }
        }
    };
    /**
     * Prompt user for directory to create
     *
     * @param {egwAction|undefined|jQuery.Event} action Action, event or undefined if called directly
     * @param {egwActionObject[] | undefined} selected Selected row, or undefined if called directly
     */
    vfsSelectUI.prototype.createdir = function (action, selected) {
        var self = this;
        et2_widget_dialog_1.et2_dialog.show_prompt(function (button, dir) {
            if (button && dir) {
                var path_1 = self.get_path();
                self.egw.json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_create_dir', [dir, path_1], function (msg) {
                    self.egw.message(msg);
                    self.change_dir((path_1 == '/' ? '' : path_1) + '/' + dir);
                }).sendRequest(false);
            }
        }, this.egw.lang('New directory'), this.egw.lang('Create directory'));
    };
    /**
     * Change directory
     *
     * @param {string} _dir directory to change to incl. '..' for one up
     */
    vfsSelectUI.prototype.change_dir = function (_dir) {
        if (_dir == '..') {
            _dir = this.dirname(this.get_path());
        }
        this.path_widget.set_value(_dir);
    };
    /**
     * Row or filename in select-file dialog clicked
     *
     * @param {jQuery.event} event
     * @param {et2_widget} widget
     */
    vfsSelectUI.prototype.select_clicked = function (event, widget) {
        if (!widget || typeof widget.value != 'object') {
        }
        else if (widget.value.is_dir) // true for "httpd/unix-directory" and "egw/*"
         {
            var path_2 = null;
            // Cannot do this, there are multiple widgets named path
            // widget.getRoot().getWidgetById("path");
            widget.getRoot().iterateOver(function (widget) {
                if (widget.id == "path")
                    path_2 = widget;
            }, null, et2_widget_textbox_1.et2_textbox);
            if (path_2) {
                path_2.set_value(widget.value.path);
            }
        }
        else if (this.et2 && this.et2.getArrayMgr('content').getEntry('mode') != 'open-multiple') {
            this.et2.setValueById('name', widget.value.name);
        }
        else {
            var file_1 = widget.value.name;
            widget.getParent().iterateOver(function (widget) {
                if (widget.options.selected_value == file_1) {
                    widget.set_value(widget.get_value() == file_1 ? widget.options.unselected_value : file_1);
                }
            }, null, et2_widget_checkbox_1.et2_checkbox);
        }
        // Stop event or it will toggle back off
        event.preventDefault();
        event.stopPropagation();
        return false;
    };
    /**
     * Handles action and offer it to the submit
     *
     * @param {string} action action name
     * @param {object} widget widget which action was called from
     */
    vfsSelectUI.prototype.do_action = function (action, widget) {
        if (!action)
            return;
        var field = '', value = '';
        switch (action) {
            case 'path':
                field = 'path';
                value = widget.getValue();
                break;
            case 'home':
                field = 'action';
                value = 'home';
                break;
            case 'app':
                field = 'app';
                value = widget.getValue();
                break;
            case 'mime':
                field = 'mime';
                value = widget.getValue();
                break;
        }
        this.submit(field, value);
    };
    /**
     * Sumbits content value after modification
     *
     * @param {string} _field content field to be modified
     * @param {any} _val value of field
     * @param {function} _callback
     */
    vfsSelectUI.prototype.submit = function (_field, _val, _callback) {
        var arrMgrs = this.et2.getArrayMgrs();
        if (_field) {
            arrMgrs.content.data[_field] = _val;
            jQuery.extend(arrMgrs.content.data, arrMgrs.modifications.data);
            this.et2.setArrayMgrs(arrMgrs);
        }
        // preserve value of the name
        if (arrMgrs && this.et2.getWidgetById('name')) {
            arrMgrs.content.data['name'] = this.et2.getWidgetById('name').get_value();
        }
        this.vfsSelectWidget._content(arrMgrs.content.data, _callback);
    };
    /**
     * search through dir content and set its content base on searched query
     * @returns
     */
    vfsSelectUI.prototype.search = function (_widget) {
        var dir = this.et2.getWidgetById('dir');
        var query = _widget.get_value();
        if (query == "") {
            dir.set_value({ content: this.dirContent });
            return;
        }
        var self = this;
        var searchQuery = function (_query) {
            var result = {};
            var reg = RegExp(_query, 'ig');
            var key = 0;
            for (var i in self.dirContent) {
                if (typeof self.dirContent[i]['name'] != 'undefined' && self.dirContent[i]['name'].match(reg)) {
                    result[key] = self.dirContent[i];
                    key++;
                }
                else if (typeof self.dirContent[i]['name'] == 'undefined' && isNaN(i)) {
                    result[i] = self.dirContent[i];
                }
            }
            return result;
        };
        dir.set_value({ content: searchQuery(query) });
    };
    return vfsSelectUI;
}(egw_app_1.EgwApp));
exports.vfsSelectUI = vfsSelectUI;
app.classes.vfsSelectUI = vfsSelectUI;
//# sourceMappingURL=vfsSelectUI.js.map