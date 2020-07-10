"use strict";
/**
 * EGroupware - Import/Export - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @author Nathan Gray
 * @copyright (c) 2013 Nathan Gray
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
require("jquery");
require("jqueryui");
require("../jsapi/egw_global");
require("../etemplate/et2_types");
var egw_app_1 = require("../../api/js/jsapi/egw_app");
/**
 * JS for Import/Export
 *
 * @augments AppJS
 */
var ImportExportApp = /** @class */ (function (_super) {
    __extends(ImportExportApp, _super);
    /**
     * Constructor
     *
     * @memberOf app.infolog
     */
    function ImportExportApp() {
        // call parent
        return _super.call(this, 'importexport') || this;
    }
    /**
     * Destructor
     */
    ImportExportApp.prototype.destroy = function (_app) {
        // call parent
        _super.prototype.destroy.call(this, _app);
    };
    /**
     * This function is called when the etemplate2 object is loaded
     * and ready.  If you must store a reference to the et2 object,
     * make sure to clean it up in destroy().
     *
     * @param {etemplate2} _et2 newly ready object
     * @param {string} _name template name
     */
    ImportExportApp.prototype.et2_ready = function (_et2, _name) {
        // call parent
        _super.prototype.et2_ready.call(this, _et2, _name);
        if (this.et2.getWidgetById('export')) {
            if (!this.et2.getArrayMgr("content").getEntry("definition")) {
                // et2 doesn't understand a disabled button in the normal sense
                jQuery(this.et2.getDOMWidgetById('export').getDOMNode()).attr('disabled', 'disabled');
                jQuery(this.et2.getDOMWidgetById('preview').getDOMNode()).attr('disabled', 'disabled');
            }
            if (!this.et2.getArrayMgr("content").getEntry("filter")) {
                jQuery('input[value="filter"]').parent().hide();
            }
            // Disable / hide definition filter if not selected
            if (this.et2.getArrayMgr("content").getEntry("selection") != 'filter') {
                jQuery('div.filters').hide();
            }
        }
    };
    /**
     * Callback to download the file without destroying the etemplate request
     *
     * @param data URL to get the export file
     */
    ImportExportApp.prototype.download = function (data) {
        // Try to get the file to download in the parent window
        var app_templates = this.egw.top.etemplate2.getByApplication(framework.activeApp.appName);
        if (app_templates.length > 0) {
            app_templates[0].download(data);
        }
        else {
            // Couldn't download in opener, download here before popup closes
            this.et2.getInstanceManager().download(data);
        }
    };
    ImportExportApp.prototype.export_preview = function (event, widget) {
        var preview = jQuery(widget.getRoot().getWidgetById('preview_box').getDOMNode());
        jQuery('.content', preview).empty()
            .append('<div class="loading" style="width:100%;height:100%"></div>');
        preview
            .show(100, jQuery.proxy(function () {
            widget.clicked = true;
            widget.getInstanceManager().submit(false, true);
            widget.clicked = false;
        }, this));
        return false;
    };
    ImportExportApp.prototype.import_preview = function (event, widget) {
        var test = widget.getRoot().getWidgetById('dry-run');
        if (test.getValue() == test.options.unselected_value)
            return true;
        // Show preview
        var preview = jQuery(widget.getRoot().getWidgetById('preview_box').getDOMNode());
        jQuery('.content', preview).empty();
        preview
            .addClass('loading')
            .show(100, jQuery.proxy(function () {
            widget.clicked = true;
            widget.getInstanceManager().submit(false, true);
            widget.clicked = false;
            jQuery(widget.getRoot().getWidgetById('preview_box').getDOMNode())
                .removeClass('loading');
        }, this));
        return false;
    };
    /**
     * Open a popup to run a given definition
     *
     * @param {egwAction} action
     * @param {egwActionObject[]} selected
     */
    ImportExportApp.prototype.run_definition = function (action, selected) {
        if (!selected || selected.length != 1)
            return;
        var id = selected[0].id || null;
        var data = egw.dataGetUIDdata(id).data;
        if (!data || !data.type)
            return;
        egw.open_link(egw.link('/index.php', {
            menuaction: 'importexport.importexport_' + data.type + '_ui.' + data.type + '_dialog',
            appname: data.application,
            definition: data.definition_id
        }), "", '850x440', data.application);
    };
    /**
     * Allowed users widget has been changed, if 'All users' or 'Just me'
     * was selected, turn off any other options.
     */
    ImportExportApp.prototype.allowed_users_change = function (node, widget) {
        var value = widget.getValue();
        // Only 1 selected, no checking needed
        if (value == null || value.length <= 1)
            return;
        // Don't jump it to the top, it's weird
        widget.selected_first = false;
        var index = null;
        var specials = ['', 'all'];
        for (var i = 0; i < specials.length; i++) {
            var special = specials[i];
            if ((index = value.indexOf(special)) >= 0) {
                if (window.event.target.value == special) {
                    // Just clicked all/private, clear the others
                    value = [special];
                }
                else {
                    // Just added another, clear special
                    value.splice(index, 1);
                }
                // A little highlight to call attention to the change
                jQuery('input[value="' + special + '"]', node).parent().parent().effect('highlight', {}, 500);
                break;
            }
        }
        if (index >= 0) {
            widget.set_value(value);
        }
    };
    return ImportExportApp;
}(egw_app_1.EgwApp));
app.classes.importexport = ImportExportApp;
//# sourceMappingURL=app.js.map