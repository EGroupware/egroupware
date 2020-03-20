"use strict";
/**
 * EGroupware - Resources - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package resources
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: app.js 44390 2013-11-04 20:54:23Z ralfbecker $
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
var egw_app_1 = require("../../api/js/jsapi/egw_app");
/**
 * UI for resources
 */
var resourcesApp = /** @class */ (function (_super) {
    __extends(resourcesApp, _super);
    /**
     * Constructor
     */
    function resourcesApp() {
        return _super.call(this, 'resources') || this;
    }
    /**
     * Destructor
     */
    resourcesApp.prototype.destroy = function (_app) {
        delete this.et2;
        _super.prototype.destroy.call(this, _app);
    };
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  If you must store a reference to the et2 object,
     * make sure to clean it up in destroy().
     */
    resourcesApp.prototype.et2_ready = function (et2, name) {
        _super.prototype.et2_ready.call(this, et2, name);
    };
    /**
     * call calendar planner by selected resources
     *
     * @param {action} _action actions
     * @param {action} _senders selected action
     *
     */
    resourcesApp.prototype.view_calendar = function (_action, _senders) {
        var res_ids = [];
        var matches = [];
        var nm = _action.parent.data.nextmatch;
        var selection = nm.getSelection();
        var show_calendar = function (res_ids) {
            egw(window).message(this.egw.lang('%1 resource(s) View calendar', res_ids.length));
            var current_owners = (app.calendar ? app.calendar.state.owner || [] : []).join(',');
            if (current_owners) {
                current_owners += ',';
            }
            this.egw.open_link('calendar.calendar_uiviews.index&view=planner&sortby=user&owner=' + current_owners + 'r' + res_ids.join(',r') + '&ajax=true');
        }.bind(this);
        if (selection && selection.all) {
            // Get selected ids from nextmatch - it will ask server if user did 'select all'
            fetchAll(res_ids, nm, show_calendar);
        }
        else {
            for (var i = 0; i < _senders.length; i++) {
                res_ids.push(_senders[i].id);
                matches = res_ids[i].match(/^(?:resources::)?([0-9]+)(:([0-9]+))?$/);
                if (matches) {
                    res_ids[i] = matches[1];
                }
            }
            show_calendar(res_ids);
        }
    };
    /**
     * Calendar sidebox hook change handler
     *
     */
    resourcesApp.prototype.sidebox_change = function (ev, widget) {
        if (ev[0] != 'r') {
            widget.setSubChecked(ev, widget.getValue()[ev].value || false);
        }
        var owner = jQuery.extend([], app.calendar.state.owner) || [];
        for (var i = owner.length - 1; i >= 0; i--) {
            if (owner[i][0] == 'r') {
                owner.splice(i, 1);
            }
        }
        var value = widget.getValue();
        for (var key in value) {
            if (key[0] !== 'r')
                continue;
            if (value[key].value && owner.indexOf(key) === -1) {
                owner.push(key);
            }
        }
        app.calendar.update_state({ owner: owner });
    };
    /**
     * Book selected resource for calendar
     *
     * @param {action} _action actions
     * @param {action} _senders selected action
     */
    resourcesApp.prototype.book = function (_action, _senders) {
        var res_ids = [], matches = [];
        for (var i = 0; i < _senders.length; i++) {
            res_ids.push(_senders[i].id);
            matches = res_ids[i].match(/^(?:resources::)?([0-9]+)(:([0-9]+))?$/);
            if (matches) {
                res_ids[i] = matches[1];
            }
        }
        egw(window).message(this.egw.lang('%1 resource(s) booked', res_ids.length));
        this.egw.open_link('calendar.calendar_uiforms.edit&participants=r' + res_ids.join(',r'), '_blank', '700x700');
    };
    /**
     * set the picture_src to own_src by uploding own file
     *
     */
    resourcesApp.prototype.select_picture_src = function () {
        var rBtn = this.et2.getWidgetById('picture_src');
        if (typeof rBtn != 'undefined') {
            rBtn.set_value('own_src');
        }
    };
    return resourcesApp;
}(egw_app_1.EgwApp));
app.classes.resources = resourcesApp;
//# sourceMappingURL=app.js.map