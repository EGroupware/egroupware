"use strict";
/**
 * EGroupware - Addressbook - Javascript UI
 *
 * @link: https://www.egroupware.org
 * @package addressbook
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
/**
 * UI for Addressbook CRM view
 *
 */
var CRMView = /** @class */ (function (_super) {
    __extends(CRMView, _super);
    /**
     * Constructor
     *
     * CRM is part of addressbook
     */
    function CRMView() {
        var _this = 
        // call parent
        _super.call(this, 'addressbook') || this;
        // Reference to the list
        _this.nm = null;
        // Which addressbook contact id(s) we are showing entries for
        _this.contact_ids = [];
        // Private js for the list
        _this.app_obj = null;
        // Push data key(s) to check for our contact ID
        _this.push_contact_ids = ["contact_id"];
        return _this;
    }
    /**
     * Destructor
     */
    CRMView.prototype.destroy = function (_app) {
        this.nm = null;
        if (this.app_obj != null) {
            this.app_obj.destroy(_app);
        }
        // call parent
        _super.prototype.destroy.call(this, _app);
    };
    /**
     * A template from an app is ready, looks like it might be a CRM view.
     * Check it, get CRM ready, and bind accordingly
     *
     * @param et2
     * @param appname
     */
    CRMView.view_ready = function (et2, app_obj) {
        // Check to see if the template is for a CRM view
        if (et2.app == app_obj.appname) {
            return false;
        }
        // Make sure object is there, etemplate2 will pick it up and call our et2_ready
        if (typeof et2.app_obj.crm == "undefined" && app.classes.crm) {
            et2.app_obj.crm = new app.classes.crm();
        }
        if (typeof et2.app_obj.crm == "undefined") {
            egw.debug("error", "CRMView object is missing");
            return false;
        }
        var crm = et2.app_obj.crm;
        // We can set this now
        crm.set_view_obj(app_obj);
    };
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  The associated app [is supposed to have] already called its own et2_ready(),
     * so any changes done here will override the app.
     *
     * @param {etemplate2} et2 newly ready object
     * @param {string} name Template name
     */
    CRMView.prototype.et2_ready = function (et2, name) {
        // call parent
        _super.prototype.et2_ready.call(this, et2, name);
    };
    /**
     * Set the associated private app JS
     * We try and pull the needed info here
     */
    CRMView.prototype.set_view_obj = function (app_obj) {
        this.app_obj = app_obj;
        // For easy reference later
        this.nm = app_obj.et2.getDOMWidgetById('nm');
        var contact_ids = app_obj.et2.getArrayMgr("content").getEntry("action_id") || "";
        if (typeof contact_ids == "string") {
            contact_ids = contact_ids.split(",");
        }
        this.set_contact_ids(contact_ids);
        // Override the push handler
        this._override_push(app_obj);
    };
    /**
     * Set or change which contact IDs we are showing entries for
     */
    CRMView.prototype.set_contact_ids = function (ids) {
        this.contact_ids = ids;
        var filter = { action_id: this.contact_ids };
        if (this.nm !== null) {
            this.nm.applyFilters(filter);
        }
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
     * - add: ask server for data, add in intelligently
     * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
     * @param {number} pushData.account_id User that caused the notification
     */
    CRMView.prototype.push = function (pushData) {
        var _this = this;
        if (pushData.app !== this.app_obj.appname || !this.nm)
            return;
        // If we know about it and it's an update, just update.
        // This must be before all ACL checks, as contact might have changed and entry needs to be removed
        // (server responds then with null / no entry causing the entry to disapear)
        if (pushData.type !== "add" && this.egw.dataHasUID(this.uid(pushData))) {
            return this.nm.refresh(pushData.id, pushData.type);
        }
        // Check if it's for one of our contacts
        for (var _i = 0, _a = this.push_contact_ids; _i < _a.length; _i++) {
            var field = _a[_i];
            if (pushData.acl && pushData.acl[field]) {
                var val = typeof pushData.acl[field] == "string" ? [pushData.acl[field]] : pushData.acl[field];
                if (val.filter(function (v) { return _this.contact_ids.indexOf(v) >= 0; }).length > 0) {
                    return this._app_obj_push(pushData);
                }
            }
        }
    };
    /**
     * Override the list's push handler to do nothing, we'll call it if we want it.
     *
     * @param app_obj
     * @private
     */
    CRMView.prototype._override_push = function (app_obj) {
        this._app_obj_push = app_obj.push.bind(app_obj);
        app_obj.push = function (pushData) { return false; };
    };
    return CRMView;
}(egw_app_1.EgwApp));
exports.CRMView = CRMView;
app.classes.crm = CRMView;
//# sourceMappingURL=CRM.js.map