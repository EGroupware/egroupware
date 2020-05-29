"use strict";
/**
 * EGroupware eTemplate2 - JS Template base class
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
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
    et2_core_xml;
    et2_core_DOMWidget;
*/
require("./et2_core_interfaces");
require("./et2_core_common");
var et2_core_DOMWidget_1 = require("./et2_core_DOMWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
var etemplate2_1 = require("./etemplate2");
/**
 * Class which implements the "template" XET-Tag. When the id parameter is set,
 * the template class checks whether another template with this id already
 * exists. If yes, this template is removed from the DOM tree, copied and
 * inserted in place of this template.
 */
var et2_template = /** @class */ (function (_super) {
    __extends(et2_template, _super);
    /**
     * Constructor
     */
    function et2_template(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_template._attributes, _child || {})) || this;
        // Set this early, so it's available for creating namespace
        if (_attrs.content) {
            _this.content = _attrs.content;
        }
        // constructor was called here before!
        _this.div = document.createElement("div");
        // Deferred object so we can load via AJAX
        _this.loading = jQuery.Deferred();
        // run transformAttributes now, to get server-side modifications (url!)
        if (_attrs.template) {
            _this.id = _attrs.template;
            _this.transformAttributes(_attrs);
            _this.options = et2_cloneObject(_attrs);
            _attrs = {};
        }
        if (_this.id != "" || _this.options.template) {
            var parts = (_this.options.template || _this.id).split('?');
            var cache_buster = parts.length > 1 ? parts.pop() : null;
            var template_name = parts.pop();
            // Check to see if XML is known
            var xml = null;
            var templates = etemplate2_1.etemplate2.templates; // use global eTemplate cache
            if (!(xml = templates[template_name])) {
                // Check to see if ID is short form --> prepend parent/top-level name
                if (template_name.indexOf('.') < 0) {
                    var root = _parent ? _parent.getRoot() : null;
                    var top_name = root && root._inst ? root._inst.name : null;
                    if (top_name && template_name.indexOf('.') < 0)
                        template_name = top_name + '.' + template_name;
                }
                xml = templates[template_name];
                if (!xml) {
                    // Ask server
                    var url = _this.options.url;
                    if (!_this.options.url) {
                        var splitted = template_name.split('.');
                        var app = splitted.shift();
                        // use template base url from initial template, to continue using webdav, if that was loaded via webdav
                        url = _this.getRoot()._inst.template_base_url + app + "/templates/default/" +
                            splitted.join('.') + ".xet" + (cache_buster ? '?download=' + cache_buster : '');
                    }
                    // if server did not give a cache-buster, fall back to current time
                    if (url.indexOf('?') == -1)
                        url += '?download=' + (new Date).valueOf();
                    if (_this.options.url || splitted.length) {
                        var fetch_url_callback = function (_xmldoc) {
                            // Scan for templates and store them
                            for (var i = 0; i < _xmldoc.childNodes.length; i++) {
                                var template = _xmldoc.childNodes[i];
                                if (template.nodeName.toLowerCase() != "template")
                                    continue;
                                templates[template.getAttribute("id")] = template;
                            }
                            // Read the XML structure of the requested template
                            if (typeof templates[template_name] != 'undefined')
                                this.loadFromXML(templates[template_name]);
                            // Update flag
                            this.loading.resolve();
                        };
                        et2_loadXMLFromURL(url, fetch_url_callback, _this, function (error) {
                            url = egw.link('/' + app + "/templates/default/" +
                                splitted.join('.') + ".xet", { download: cache_buster ? cache_buster : (new Date).valueOf() });
                            et2_loadXMLFromURL(url, fetch_url_callback, this);
                        });
                    }
                    return _this;
                }
            }
            if (xml !== null && typeof xml !== "undefined") {
                _this.egw().debug("log", "Loading template from XML: ", template_name);
                _this.loadFromXML(xml);
                // Don't call this here - done by caller, or on whole widget tree
                //this.loadingFinished();
                // But resolve the promise
                _this.loading.resolve();
            }
            else {
                _this.egw().debug("warn", "Unable to find XML for ", template_name);
                _this.loading.reject();
            }
        }
        else {
            // No actual template
            _this.loading.resolve();
        }
        return _this;
    }
    /**
     * Override parent to support content attribute
     * Templates always have ID set, but seldom do we want them to
     * create a namespace based on their ID.
     */
    et2_template.prototype.checkCreateNamespace = function (_attrs) {
        if (_attrs.content) {
            var old_id = _attrs.id;
            this.id = _attrs.content;
            _super.prototype.checkCreateNamespace.apply(this, arguments);
            this.id = old_id;
        }
    };
    et2_template.prototype._createNamespace = function () {
        return true;
    };
    et2_template.prototype.getDOMNode = function () {
        return this.div;
    };
    et2_template.prototype.attachToDOM = function () {
        if (this.div) {
            jQuery(this.div)
                .off('.et2_template')
                .bind("load.et2_template", this, function (e) {
                e.data.load.call(e.data, this);
            });
        }
        return _super.prototype.attachToDOM.call(this);
    };
    /**
     * Called after the template is fully loaded to handle any onload handlers
     */
    et2_template.prototype.load = function () {
        if (typeof this.options.onload == 'function') {
            // Make sure function gets a reference to the widget
            var args = Array.prototype.slice.call(arguments);
            if (args.indexOf(this) == -1)
                args.push(this);
            return this.options.onload.apply(this, args);
        }
    };
    /**
     * Override to return the promise for deferred loading
     */
    et2_template.prototype.doLoadingFinished = function () {
        // Apply parent now, which actually puts into the DOM
        _super.prototype.doLoadingFinished.call(this);
        // Fire load event when done loading
        this.loading.done(jQuery.proxy(function () { jQuery(this).trigger("load"); }, this.div));
        // Not done yet, but widget will let you know
        return this.loading.promise();
    };
    et2_template._attributes = {
        "template": {
            "name": "Template",
            "type": "string",
            "description": "Name / ID of template with optional cache-buster ('?'+filemtime of template on server)",
            "default": et2_no_init
        },
        "group": {
            // TODO: Not implemented
            "name": "Group",
            "description": "Not implemented",
            //"default": 0
            "default": et2_no_init
        },
        "version": {
            "name": "Version",
            "type": "string",
            "description": "Version of the template"
        },
        "lang": {
            "name": "Language",
            "type": "string",
            "description": "Language the template is written in"
        },
        "content": {
            "name": "Content index",
            "default": et2_no_init,
            "description": "Used for passing in specific content to the template other than what it would get by ID."
        },
        url: {
            name: "URL of template",
            type: "string",
            description: "full URL to load template incl. cache-buster"
        },
        "onload": {
            "name": "onload",
            "type": "js",
            "default": et2_no_init,
            "description": "JS code which is executed after the template is loaded."
        }
    };
    return et2_template;
}(et2_core_DOMWidget_1.et2_DOMWidget));
exports.et2_template = et2_template;
et2_core_widget_1.et2_register_widget(et2_template, ["template"]);
//# sourceMappingURL=et2_widget_template.js.map