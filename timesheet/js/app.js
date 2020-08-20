"use strict";
/**
 * EGroupware - Timesheet - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package timesheet
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
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
require("jquery");
require("jqueryui");
require("../jsapi/egw_global");
require("../etemplate/et2_types");
var egw_app_1 = require("../../api/js/jsapi/egw_app");
var etemplate2_1 = require("../../api/js/etemplate/etemplate2");
/**
 * UI for timesheet
 *
 * @augments AppJS
 */
var TimesheetApp = /** @class */ (function (_super) {
    __extends(TimesheetApp, _super);
    function TimesheetApp() {
        return _super.call(this, 'timesheet') || this;
    }
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  If you must store a reference to the et2 object,
     * make sure to clean it up in destroy().
     *
     * @param et2 etemplate2 Newly ready object
     * @param string name
     */
    TimesheetApp.prototype.et2_ready = function (et2, name) {
        // call parent
        _super.prototype.et2_ready.call(this, et2, name);
        if (name == 'timesheet.index') {
            this.filter_change();
            this.filter2_change();
        }
    };
    /**
     *
     */
    TimesheetApp.prototype.filter_change = function () {
        var filter = this.et2.getWidgetById('filter');
        var dates = this.et2.getWidgetById('timesheet.index.dates');
        var nm = this.et2.getDOMWidgetById('nm');
        if (filter && dates) {
            dates.set_disabled(filter.get_value() !== "custom");
            if (filter.get_value() == 0)
                nm.activeFilters.startdate = null;
            if (filter.value == "custom") {
                jQuery(this.et2.getWidgetById('startdate').getDOMNode()).find('input').focus();
            }
        }
        return true;
    };
    /**
     * show or hide the details of rows by selecting the filter2 option
     * either 'all' for details or 'no_description' for no details
     *
     */
    TimesheetApp.prototype.filter2_change = function () {
        var nm = this.et2.getWidgetById('nm');
        var filter2 = this.et2.getWidgetById('filter2');
        if (nm && filter2) {
            egw.css("#timesheet-index span.timesheet_titleDetails", "font-weight:" + (filter2.getValue() == '1' ? "bold;" : "normal;"));
            // Show / hide descriptions
            egw.css(".et2_label.ts_description", "display:" + (filter2.getValue() == '1' ? "block;" : "none;"));
        }
    };
    /**
     * Wrapper so add action in the context menu can pass current
     * filter values into new edit dialog
     *
     * @see add_with_extras
     *
     * @param {egwAction} action
     * @param {egwActionObject[]} selected
     */
    TimesheetApp.prototype.add_action_handler = function (action, selected) {
        var nm = action.getManager().data.nextmatch || false;
        if (nm) {
            this.add_with_extras(nm);
        }
    };
    /**
     * Opens a new edit dialog with some extra url parameters pulled from
     * nextmatch filters.
     *
     * @param {et2_widget} widget Originating/calling widget
     */
    TimesheetApp.prototype.add_with_extras = function (widget) {
        var nm = widget.getRoot().getWidgetById('nm');
        var nm_value = nm.getValue() || {};
        var extras = {};
        if (nm_value.cat_id) {
            extras.cat_id = nm_value.cat_id;
        }
        if (nm_value.col_filter && nm_value.col_filter.linked) {
            var split = nm_value.col_filter.linked.split(':') || '';
            extras.link_app = split[0] || '';
            extras.link_id = split[1] || '';
        }
        if (nm_value.col_filter && nm_value.col_filter.pm_id) {
            extras.link_app = 'projectmanager';
            extras.link_id = nm_value.col_filter.pm_id;
        }
        else if (nm_value.col_filter && nm_value.col_filter.ts_project) {
            extras.ts_project = nm_value.col_filter.ts_project;
        }
        egw.open('', 'timesheet', 'add', extras);
    };
    /**
     * Change handler for project selection to set empty ts_project string, if project get deleted
     *
     * @param {type} _egw
     * @param {et2_widget_link_entry} _widget
     * @returns {undefined}
     */
    TimesheetApp.prototype.pm_id_changed = function (_egw, _widget) {
        // Update price list
        var ts_pricelist = _widget.getRoot().getWidgetById('pl_id');
        egw.json('projectmanager_widget::ajax_get_pricelist', [_widget.getValue()], function (value) {
            ts_pricelist.set_select_options(value || {});
        }).sendRequest(true);
        var ts_project = this.et2.getWidgetById('ts_project');
        if (ts_project) {
            ts_project.set_blur(_widget.getValue() ? _widget.search.val() : '');
        }
    };
    /**
     * Update custom filter timespan, without triggering a change
     */
    TimesheetApp.prototype.update_timespan = function (start, end) {
        if (this && this.et2) {
            var nm = this.et2.getWidgetById('nm');
            if (nm) {
                // Toggle update_in_progress to avoid another request
                nm.update_in_progress = true;
                this.et2.getWidgetById('startdate').set_value(start);
                this.et2.getWidgetById('enddate').set_value(end);
                nm.activeFilters.startdate = start;
                nm.activeFilters.enddate = end;
                nm.update_in_progress = false;
            }
        }
    };
    /**
     * Get title in order to set it as document title
     * @returns {string}
     */
    TimesheetApp.prototype.getWindowTitle = function () {
        var widget = this.et2.getWidgetById('ts_title');
        if (widget)
            return widget.options.value;
    };
    /**
     * Handle a push notification about entry changes from the websocket
     *
     * @param  pushData
     * @param {string} pushData.app application name
     * @param {(string|number)} pushData.id id of entry to refresh or null
     * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
     * - update: request just modified data from given rows.  Sorting is not considered,
     *		so if the sort field is changed, the row will not be moved.
     * - edit: rows changed, but sorting may be affected.  Requires full reload.
     * - delete: just delete the given rows clientside (no server interaction neccessary)
     * - add: requires full reload for proper sorting
     * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
     * @param {number} pushData.account_id User that caused the notification
     */
    TimesheetApp.prototype.push = function (pushData) {
        var _a, _b, _c;
        // timesheed does NOT care about other apps data
        if (pushData.app !== this.appname)
            return;
        if (pushData.type === 'delete') {
            return _super.prototype.push.call(this, pushData);
        }
        // This must be before all ACL checks, as owner might have changed and entry need to be removed
        // (server responds then with null / no entry causing the entry to disapear)
        if (pushData.type !== "add" && this.egw.dataHasUID(this.uid(pushData))) {
            return etemplate2_1.etemplate2.app_refresh("", pushData.app, pushData.id, pushData.type);
        }
        // all other cases (add, edit, update) are handled identical
        // check visibility
        if (typeof this._grants === 'undefined') {
            this._grants = egw.grants(this.appname);
        }
        if (typeof this._grants[pushData.acl.ts_owner] === 'undefined')
            return;
        // check if we might not see it because of an owner filter
        var nm = (_a = this.et2) === null || _a === void 0 ? void 0 : _a.getWidgetById('nm');
        var nm_value = (_b = nm) === null || _b === void 0 ? void 0 : _b.getValue();
        if (nm && nm_value && ((_c = nm_value.col_filter) === null || _c === void 0 ? void 0 : _c.ts_owner) && nm_value.col_filter.ts_owner != pushData.acl.ts_owner) {
            return;
        }
        etemplate2_1.etemplate2.app_refresh("", pushData.app, pushData.id, pushData.type);
    };
    /**
     * Run action via ajax
     *
     * @param _action
     * @param _senders
     */
    TimesheetApp.prototype.ajax_action = function (_action, _senders) {
        var _a;
        var all = (_a = _action.parent.data.nextmatch) === null || _a === void 0 ? void 0 : _a.getSelection().all;
        var ids = [];
        for (var i = 0; i < _senders.length; i++) {
            ids.push(_senders[i].id.split("::").pop());
        }
        egw.json("timesheet.timesheet_ui.ajax_action", [_action.id, ids, all]).sendRequest(true);
    };
    return TimesheetApp;
}(egw_app_1.EgwApp));
app.classes.timesheet = TimesheetApp;
//# sourceMappingURL=app.js.map