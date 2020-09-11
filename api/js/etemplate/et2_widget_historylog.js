"use strict";
/**
 * EGroupware eTemplate2 - JS History log
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
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
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_dataview_1 = require("./et2_dataview");
var et2_dataview_model_columns_1 = require("./et2_dataview_model_columns");
var et2_dataview_controller_1 = require("./et2_dataview_controller");
var et2_widget_diff_1 = require("./et2_widget_diff");
/**
 * eTemplate history log widget displays a list of changes to the current record.
 * The widget is encapsulated, and only needs the record's ID, and a map of
 * fields:widgets for display.
 *
 * It defers its initialization until the tab that it's on is selected, to avoid
 * wasting time if the user never looks at it.
 *
 * @augments et2_valueWidget
 */
var et2_historylog = /** @class */ (function (_super) {
    __extends(et2_historylog, _super);
    /**
     * Constructor
     *
     * @memberOf et2_historylog
     */
    function et2_historylog(_parent, _attrs, _child) {
        var _this = _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_historylog._attributes, _child || {})) || this;
        _this.div = jQuery(document.createElement("div"))
            .addClass("et2_historylog");
        _this.innerDiv = jQuery(document.createElement("div"))
            .appendTo(_this.div);
        return _this;
    }
    et2_historylog.prototype.set_status_id = function (_new_id) {
        this.options.status_id = _new_id;
    };
    et2_historylog.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        // Find the tab
        var tab = this.get_tab_info();
        if (tab) {
            // Bind the action to when the tab is selected
            var handler = function (e) {
                e.data.div.unbind("click.history");
                // Bind on click tap, because we need to update history size
                // after a rezise happend and history log was not the active tab
                e.data.div.bind("click.history", { "history": e.data.history, div: tab.flagDiv }, function (e) {
                    if (e.data.history && e.data.history.dynheight) {
                        e.data.history.dynheight.update(function (_w, _h) {
                            e.data.history.dataview.resize(_w, _h);
                        });
                    }
                });
                if (typeof e.data.history.dataview == "undefined") {
                    e.data.history.finishInit();
                    if (e.data.history.dynheight) {
                        e.data.history.dynheight.update(function (_w, _h) {
                            e.data.history.dataview.resize(_w, _h);
                        });
                    }
                }
            };
            tab.flagDiv.bind("click.history", { "history": this, div: tab.flagDiv }, handler);
            // Display if history tab is selected
            if (tab.contentDiv.is(':visible') && typeof this.dataview == 'undefined') {
                tab.flagDiv.trigger("click.history");
            }
        }
        else {
            this.finishInit();
        }
        return true;
    };
    et2_historylog.prototype._createNamespace = function () {
        return true;
    };
    /**
     * Finish initialization which was skipped until tab was selected
     */
    et2_historylog.prototype.finishInit = function () {
        // No point with no ID
        if (!this.options.value || !this.options.value.id) {
            return;
        }
        this._filters = {
            record_id: this.options.value.id,
            appname: this.options.value.app,
            get_rows: this.options.get_rows
        };
        // Warn if status_id is the same as history id, that causes overlap and missing labels
        if (this.options.status_id === this.id) {
            this.egw().debug("warn", "status_id attribute should not be the same as historylog ID");
        }
        // Create the dynheight component which dynamically scales the inner
        // container.
        this.div.parentsUntil('.et2_tabs').height('100%');
        var parent = this.get_tab_info();
        this.dynheight = new et2_dynheight(parent ? parent.contentDiv : this.div.parent(), this.innerDiv, 250);
        // Create the outer grid container
        this.dataview = new et2_dataview_1.et2_dataview(this.innerDiv, this.egw());
        var dataview_columns = [];
        var _columns = typeof this.options.columns === "string" ?
            this.options.columns.split(',') : this.options.columns;
        for (var i = 0; i < et2_historylog.columns.length; i++) {
            dataview_columns[i] = {
                "id": et2_historylog.columns[i].id,
                "caption": et2_historylog.columns[i].caption,
                "width": et2_historylog.columns[i].width,
                "visibility": _columns.indexOf(et2_historylog.columns[i].id) < 0 ?
                    et2_dataview_model_columns_1.et2_dataview_column.ET2_COL_VISIBILITY_INVISIBLE : et2_dataview_model_columns_1.et2_dataview_column.ET2_COL_VISIBILITY_VISIBLE
            };
        }
        this.dataview.setColumns(dataview_columns);
        // Create widgets for columns that stay the same, and set up varying widgets
        this.createWidgets();
        // Create the gridview controller
        var linkCallback = function () {
        };
        this.controller = new et2_dataview_controller_1.et2_dataview_controller(null, this.dataview.grid);
        this.controller.setContext(this);
        this.controller.setDataProvider(this);
        this.controller.setLinkCallback(linkCallback);
        this.controller.setRowCallback(this.rowCallback);
        this.controller.setActionObjectManager(null);
        var total = typeof this.options.value.total !== "undefined" ?
            this.options.value.total : 0;
        // This triggers an invalidate, which updates the grid
        this.dataview.grid.setTotalCount(total);
        // Insert any data sent from server, so invalidate finds data already
        if (this.options.value.rows && this.options.value.num_rows) {
            this.controller.loadInitialData(this.options.value.dataStorePrefix, this.options.value.row_id, this.options.value.rows);
            // Remove, to prevent duplication
            delete this.options.value.rows;
            // This triggers an invalidate, which updates the grid
            this.dataview.grid.setTotalCount(total);
        }
        else {
            // Trigger the initial update
            this.controller.update();
        }
        // Write something inside the column headers
        for (var i = 0; i < et2_historylog.columns.length; i++) {
            jQuery(this.dataview.getHeaderContainerNode(i)).text(et2_historylog.columns[i].caption);
        }
        // Register a resize callback
        jQuery(window).on('resize.' + this.options.value.app + this.options.value.id, function () {
            if (this && typeof this.dynheight != 'undefined')
                this.dynheight.update(function (_w, _h) {
                    this.dataview.resize(_w, _h);
                }.bind(this));
        }.bind(this));
    };
    /**
     * Destroys all
     */
    et2_historylog.prototype.destroy = function () {
        // Unbind, if bound
        if (this.options.value && !this.options.value.id) {
            jQuery(window).off('.' + this.options.value.app + this.options.value.id);
        }
        // Free the widgets
        for (var i = 0; i < et2_historylog.columns.length; i++) {
            if (et2_historylog.columns[i].widget)
                et2_historylog.columns[i].widget.destroy();
        }
        for (var key in this.fields) {
            this.fields[key].widget.destroy();
        }
        // Free the grid components
        if (this.dataview)
            this.dataview.destroy();
        if (this.controller)
            this.controller.destroy();
        if (this.dynheight)
            this.dynheight.destroy();
        _super.prototype.destroy.call(this);
    };
    /**
     * Create all needed widgets for new / old values
     */
    et2_historylog.prototype.createWidgets = function () {
        // Constant widgets - first 3 columns
        for (var i_1 = 0; i_1 < et2_historylog.columns.length; i_1++) {
            if (et2_historylog.columns[i_1].widget_type) {
                // Status ID is allowed to be remapped to something else.  Only affects the widget ID though
                var attrs = { 'readonly': true, 'id': (i_1 == et2_historylog.FIELD ? this.options.status_id : et2_historylog.columns[i_1].id) };
                et2_historylog.columns[i_1].widget = et2_createWidget(et2_historylog.columns[i_1].widget_type, attrs, this);
                et2_historylog.columns[i_1].widget.transformAttributes(attrs);
                et2_historylog.columns[i_1].nodes = jQuery(et2_historylog.columns[i_1].widget.getDetachedNodes());
            }
        }
        // Add in handling for links
        if (typeof this.options.value['status-widgets']['~link~'] == 'undefined') {
            et2_historylog.columns[et2_historylog.FIELD].widget.optionValues['~link~'] = this.egw().lang('link');
            this.options.value['status-widgets']['~link~'] = 'link';
        }
        // Add in handling for files
        if (typeof this.options.value['status-widgets']['~file~'] == 'undefined') {
            et2_historylog.columns[et2_historylog.FIELD].widget.optionValues['~file~'] = this.egw().lang('File');
            this.options.value['status-widgets']['~file~'] = 'vfs';
        }
        // Add in handling for user-agent & action
        if (typeof this.options.value['status-widgets']['user_agent_action'] == 'undefined') {
            et2_historylog.columns[et2_historylog.FIELD].widget.optionValues['user_agent_action'] = this.egw().lang('User-agent & action');
        }
        // Per-field widgets - new value & old value
        this.fields = {};
        var labels = et2_historylog.columns[et2_historylog.FIELD].widget.optionValues;
        // Custom fields - Need to create one that's all read-only for proper display
        var cf_widget = et2_createWidget('customfields', { 'readonly': true }, this);
        cf_widget.loadFields();
        // Override this or it may damage the real values
        cf_widget.getValue = function () { return null; };
        for (var key_1 in cf_widget.widgets) {
            // Add label
            labels[cf_widget.prefix + key_1] = cf_widget.options.customfields[key_1].label;
            // If it doesn't support detached nodes, just treat it as text
            if (cf_widget.widgets[key_1].getDetachedNodes) {
                var nodes = cf_widget.widgets[key_1].getDetachedNodes();
                for (var i = 0; i < nodes.length; i++) {
                    if (nodes[i] == null)
                        nodes.splice(i, 1);
                }
                // Save to use for each row
                this.fields[cf_widget.prefix + key_1] = {
                    attrs: cf_widget.widgets[key_1].options,
                    widget: cf_widget.widgets[key_1],
                    nodes: jQuery(nodes)
                };
            }
        }
        // Add all cf labels
        et2_historylog.columns[et2_historylog.FIELD].widget.set_select_options(labels);
        // From app
        for (var key in this.options.value['status-widgets']) {
            var attrs_1 = jQuery.extend({ 'readonly': true, 'id': key }, this.getArrayMgr('modifications').getEntry(key));
            var field = attrs_1.type || this.options.value['status-widgets'][key];
            var options = null;
            var widget = this._create_widget(key, field, attrs_1, options);
            if (widget === null) {
                continue;
            }
            if (widget.instanceOf(et2_selectbox))
                widget.options.multiple = true;
            widget.transformAttributes(attrs_1);
            // Save to use for each row
            var nodes_1 = widget._children.length ? [] : jQuery(widget.getDetachedNodes());
            for (var i_2 = 0; i_2 < widget._children.length; i_2++) {
                // @ts-ignore
                nodes_1.push(jQuery(widget._children[i_2].getDetachedNodes()));
            }
            this.fields[key] = {
                attrs: attrs_1,
                widget: widget,
                nodes: nodes_1
            };
        }
        // Widget for text diffs
        var diff = et2_createWidget('diff', {}, this);
        this.diff = {
            // @ts-ignore
            widget: diff,
            nodes: jQuery(diff.getDetachedNodes())
        };
    };
    et2_historylog.prototype._create_widget = function (key, field, attrs, options) {
        var widget = null;
        // If field has multiple parts (is object) and isn't an obvious select box
        if (typeof field === 'object') {
            // Check for multi-part statuses needing multiple widgets
            var need_box = false; //!this.getArrayMgr('sel_options').getEntry(key);
            for (var j in field) {
                // Require widget to be a widget, to avoid invalid widgets
                // (and template, which is a widget and an infolog todo status)
                if (et2_registry[field[j]] && ['template'].indexOf(field[j]) < 0) // && (et2_registry[field[j]].prototype.instanceOf(et2_valueWidget))
                 {
                    need_box = true;
                    break;
                }
            }
            if (need_box) {
                // Multi-part value needs multiple widgets
                widget = et2_createWidget('vbox', attrs, this);
                for (var i in field) {
                    var type = field[i];
                    var child_attrs = jQuery.extend({}, attrs);
                    if (typeof type === 'object') {
                        child_attrs['select_options'] = field[i];
                        type = 'select';
                    }
                    else {
                        delete child_attrs['select_options'];
                    }
                    child_attrs.id = i;
                    var child = this._create_widget(i, type, child_attrs, options);
                    widget.addChild(child);
                    child.transformAttributes(child_attrs);
                }
            }
            else {
                attrs['select_options'] = field;
            }
        }
        // Check for options after the type, ex: link-entry:infolog
        else if (field.indexOf(':') > 0) {
            var options = field.split(':');
            field = options.shift();
        }
        if (widget === null) {
            widget = et2_createWidget(typeof field === 'string' ? field : 'select', attrs, this);
        }
        if (!widget.instanceOf(et2_IDetachedDOM)) {
            this.egw().debug("warn", this, "Invalid widget " + field + " for " + key + ".  Status widgets must implement et2_IDetachedDOM.");
            return null;
        }
        // Parse / set legacy options
        if (options) {
            var mgr = this.getArrayMgr("content");
            var legacy = widget.constructor.legacyOptions || [];
            for (var i_3 = 0; i_3 < options.length && i_3 < legacy.length; i_3++) {
                // Not set
                if (options[i_3] === "")
                    continue;
                var attr = widget.attributes[legacy[i_3]];
                var attrValue = options[i_3];
                // If the attribute is marked as boolean, parse the
                // expression as bool expression.
                if (attr.type === "boolean") {
                    attrValue = mgr.parseBoolExpression(attrValue);
                }
                else {
                    attrValue = mgr.expandName(attrValue);
                }
                attrs[legacy[i_3]] = attrValue;
                if (typeof widget['set_' + legacy[i_3]] === 'function') {
                    widget['set_' + legacy[i_3]].call(widget, attrValue);
                }
                else {
                    widget.options[legacy[i_3]] = attrValue;
                }
            }
        }
        return widget;
    };
    et2_historylog.prototype.getDOMNode = function (_sender) {
        if (_sender == this) {
            return this.div[0];
        }
        for (var i = 0; i < et2_historylog.columns.length; i++) {
            if (_sender == et2_historylog.columns[i].widget) {
                return this.dataview.getHeaderContainerNode(i);
            }
        }
        return null;
    };
    et2_historylog.prototype.dataFetch = function (_queriedRange, _callback, _context) {
        // Skip getting data if there's no ID
        if (!this.value.id)
            return;
        // Set num_rows to fetch via nextmatch
        if (this.options.value['num_rows'])
            _queriedRange['num_rows'] = this.options.value['num_rows'];
        var historylog = this;
        // Pass the fetch call to the API
        this.egw().dataFetch(this.getInstanceManager().etemplate_exec_id, _queriedRange, this._filters, this.id, function (_response) {
            _callback.call(this, _response);
        }, _context, []);
    };
    // Needed by interface
    et2_historylog.prototype.dataRegisterUID = function (_uid, _callback, _context) {
        this.egw().dataRegisterUID(_uid, _callback, _context, this.getInstanceManager().etemplate_exec_id, this.id);
    };
    et2_historylog.prototype.dataUnregisterUID = function (_uid, _callback, _context) {
        // Needed by interface
    };
    /**
     * The row callback gets called by the gridview controller whenever
     * the actual DOM-Nodes for a node with the given data have to be
     * created.
     *
     * @param {type} _data
     * @param {type} _row
     * @param {type} _idx
     * @param {type} _entry
     */
    et2_historylog.prototype.rowCallback = function (_data, _row, _idx, _entry) {
        var tr = _row.getDOMNode();
        jQuery(tr).attr("valign", "top");
        var row = this.dataview.rowProvider.getPrototype("default");
        var self = this;
        jQuery("div", row).each(function (i) {
            var nodes = [];
            var widget = et2_historylog.columns[i].widget;
            var value = _data[et2_historylog.columns[i].id];
            if (et2_historylog.OWNER === i && _data['share_email']) {
                // Show share email instead of owner
                widget = undefined;
                value = _data['share_email'];
            }
            // Get widget from list, unless it needs a diff widget
            if ((typeof widget == 'undefined' || widget == null) && typeof self.fields[_data.status] != 'undefined' && (i < et2_historylog.NEW_VALUE ||
                i >= et2_historylog.NEW_VALUE && (self.fields[_data.status].nodes || !self._needsDiffWidget(_data['status'], _data[et2_historylog.columns[et2_historylog.OLD_VALUE].id])))) {
                widget = self.fields[_data.status].widget;
                if (!widget._children.length) {
                    nodes = self.fields[_data.status].nodes.clone();
                }
                for (var j = 0; j < widget._children.length; j++) {
                    // @ts-ignore
                    nodes.push(self.fields[_data.status].nodes[j].clone());
                    if (widget._children[j].instanceOf(et2_widget_diff_1.et2_diff)) {
                        self._spanValueColumns(jQuery(this));
                    }
                }
            }
            else if (widget) {
                nodes = et2_historylog.columns[i].nodes.clone();
            }
            else if ((
            // Already parsed & cached
            typeof _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id] == "object" &&
                typeof _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id] != "undefined" &&
                _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id] !== null) || // typeof null === 'object'
                // Large old value
                self._needsDiffWidget(_data['status'], _data[et2_historylog.columns[et2_historylog.OLD_VALUE].id]) ||
                // Large new value
                self._needsDiffWidget(_data['status'], _data[et2_historylog.columns[et2_historylog.NEW_VALUE].id])) {
                // Large text value - span both columns, and show a nice diff
                var jthis = jQuery(this);
                if (i === et2_historylog.NEW_VALUE) {
                    // Diff widget
                    widget = self.diff.widget;
                    nodes = self.diff.nodes.clone();
                    if (widget)
                        widget.setDetachedAttributes(nodes, {
                            value: value,
                            label: jthis.parents("td").prev().text()
                        });
                    self._spanValueColumns(jthis);
                }
            }
            else {
                // No widget fallback - display actual value
                nodes = jQuery('<span>').text(value === null ? '' : value);
            }
            if (widget) {
                if (widget._children.length) {
                    // Multi-part values
                    var box = jQuery(widget.getDOMNode()).clone();
                    for (var j = 0; j < widget._children.length; j++) {
                        var id = widget._children[j].id;
                        var widget_value = value ? value[id] || "" : "";
                        widget._children[j].setDetachedAttributes(nodes[j], { value: widget_value });
                        box.append(nodes[j]);
                    }
                    nodes = box;
                }
                else {
                    widget.setDetachedAttributes(nodes, { value: value });
                }
            }
            jQuery(this).append(nodes);
        });
        jQuery(tr).append(row.children());
        return tr;
    };
    /**
     * How to tell if the row needs a diff widget or not
     *
     * @param {string} columnName
     * @param {string} value
     * @returns {Boolean}
     */
    et2_historylog.prototype._needsDiffWidget = function (columnName, value) {
        if (typeof value !== "string" && value) {
            this.egw().debug("warn", "Crazy diff value", value);
            return false;
        }
        return value === '***diff***';
    };
    /**
     * Make a single row's new value cell span across both new value and old value
     * columns.  Used for diff widget.
     *
     * @param {jQuery} row jQuery wrapped row node
     */
    et2_historylog.prototype._spanValueColumns = function (row) {
        // Stretch column 4
        row.parents("td").attr("colspan", 2)
            .css("border-right", "none");
        row.css("width", (this.dataview.getColumnMgr().getColumnWidth(et2_historylog.NEW_VALUE) +
            this.dataview.getColumnMgr().getColumnWidth(et2_historylog.OLD_VALUE) - 10) + 'px');
        // Skip column 5
        row.parents("td").next().remove();
    };
    et2_historylog.prototype.resize = function (_height) {
        if (typeof this.options != 'undefined' && _height
            && typeof this.options.resize_ratio != 'undefined') {
            // apply the ratio
            _height = (this.options.resize_ratio != '') ? _height * this.options.resize_ratio : _height;
            if (_height != 0) {
                // 250px is the default value for history widget
                // if it's not loaded yet and window is resized
                // then add the default height with excess_height
                if (this.div.height() == 0)
                    _height += 250;
                this.div.height(this.div.height() + _height);
                // trigger the history registered resize
                // in order to update the height with new value
                this.div.trigger('resize.' + this.options.value.app + this.options.value.id);
            }
        }
        if (this.dynheight) {
            this.dynheight.update();
        }
        // Resize diff widgets to match new space
        if (this.dataview) {
            var columns = this.dataview.getColumnMgr();
            jQuery('.et2_diff', this.div).closest('.innerContainer')
                .width(columns.getColumnWidth(et2_historylog.NEW_VALUE) + columns.getColumnWidth(et2_historylog.OLD_VALUE));
        }
    };
    et2_historylog._attributes = {
        "value": {
            "name": "Value",
            "type": "any",
            "description": "Object {app: ..., id: ..., status-widgets: {}} where status-widgets is a map of fields to widgets used to display those fields"
        },
        "status_id": {
            "name": "status_id",
            "type": "string",
            "default": "status",
            "description": "The history widget is traditionally named 'status'.  If you name another widget in the same template 'status', you can use this attribute to re-name the history widget.  "
        },
        "columns": {
            "name": "columns",
            "type": "string",
            "default": "user_ts,owner,status,new_value,old_value",
            "description": "Columns to display.  Default is user_ts,owner,status,new_value,old_value"
        },
        "get_rows": {
            "name": "get_rows",
            "type": "string",
            "default": "EGroupware\\Api\\Storage\\History::get_rows",
            "description": "Method to get rows"
        }
    };
    et2_historylog.legacyOptions = ["status_id"];
    et2_historylog.columns = [
        { 'id': 'user_ts', caption: 'Date', 'width': '120px', widget_type: 'date-time', widget: null, nodes: null },
        { 'id': 'owner', caption: 'User', 'width': '150px', widget_type: 'select-account', widget: null, nodes: null },
        { 'id': 'status', caption: 'Changed', 'width': '120px', widget_type: 'select', widget: null, nodes: null },
        { 'id': 'new_value', caption: 'New Value', 'width': '50%', widget: null, nodes: null },
        { 'id': 'old_value', caption: 'Old Value', 'width': '50%', widget: null, nodes: null }
    ];
    et2_historylog.TIMESTAMP = 0;
    et2_historylog.OWNER = 1;
    et2_historylog.FIELD = 2;
    et2_historylog.NEW_VALUE = 3;
    et2_historylog.OLD_VALUE = 4;
    return et2_historylog;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_historylog = et2_historylog;
et2_core_widget_1.et2_register_widget(et2_historylog, ['historylog']);
//# sourceMappingURL=et2_widget_historylog.js.map