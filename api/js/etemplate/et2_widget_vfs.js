"use strict";
/**
 * EGroupware eTemplate2 - JS VFS widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
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
var _a;
Object.defineProperty(exports, "__esModule", { value: true });
/*egw:uses
    /vendor/bower-asset/jquery/dist/jquery.js;
    vfsSelectUI;
    et2_core_inputWidget;
    et2_core_valueWidget;
    et2_widget_description;
    et2_widget_file;
    expose;
*/
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_widget_textbox_1 = require("./et2_widget_textbox");
var et2_widget_description_1 = require("./et2_widget_description");
var et2_widget_selectAccount_1 = require("./et2_widget_selectAccount");
var et2_widget_file_1 = require("./et2_widget_file");
var et2_widget_dialog_1 = require("./et2_widget_dialog");
var et2_core_inputWidget_1 = require("./et2_core_inputWidget");
/**
 * Class which implements the "vfs" XET-Tag
 *
 * @augments et2_valueWidget
 */
var et2_vfs = /** @class */ (function (_super) {
    __extends(et2_vfs, _super);
    /**
     * Constructor
     *
     * @memberOf et2_vfs
     */
    function et2_vfs(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfs._attributes, _child || {})) || this;
        _this.span = null;
        _this.value = "";
        _this.span = jQuery(document.createElement("ul"))
            .addClass('et2_vfs');
        _this.setDOMNode(_this.span[0]);
        return _this;
    }
    et2_vfs.prototype.getValue = function () {
        return this.value;
    };
    et2_vfs.prototype.set_value = function (_value) {
        if (typeof _value !== 'object') {
            // Only warn if it's an actual value, just blank for falsy values
            if (_value) {
                this.egw().debug("warn", "%s only has path, needs full array", this.id, _value);
            }
            this.span.empty().text(_value);
            return;
        }
        this.span.empty();
        this.value = _value;
        var path = _value.path ? _value.path : '/';
        // calculate path as parent of name, which can contain slashes
        // eg. _value.path=/home/ralf/sub/file, _value.name=sub/file --> path=/home/ralf
        // --> generate clickable fields for sub/ + file
        var sub_path = path.substring(0, _value.path.length - _value.name.length - 1);
        var path_offset, path_parts;
        if (_value.path.indexOf(_value.name) >= 0 && sub_path[sub_path.length - 1] === '/') {
            path = sub_path;
            path_offset = path.split('/').length;
            path_parts = _value.path.split('/');
        }
        else {
            if (_value.path.indexOf(_value.name) >= 0) {
                // Remove name from end, so we can add it again later
                path = sub_path;
            }
            path_offset = 0;
            path_parts = _value.name.split('/');
        }
        var text;
        var _loop_1 = function (i) {
            path += (path == '/' ? '' : '/') + path_parts[i];
            text = egw.decodePath(path_parts[i]);
            // Nice human-readable stuff for apps
            if (path_parts[1] == 'apps') {
                switch (path_parts.length) {
                    case 2:
                        if (i == 1) {
                            text = this_1.egw().lang('applications');
                        }
                        break;
                    case 3:
                        if (i == 2) {
                            text = this_1.egw().lang(path_parts[2]);
                        }
                        break;
                    case 4:
                        if (!isNaN(text)) {
                            var link_title = this_1.egw().link_title(path_parts[2], path_parts[3], function (title) {
                                if (!title || this.value.name == title)
                                    return;
                                jQuery('li', this.span).last().text(title);
                            }, this_1);
                            if (link_title && typeof link_title !== 'undefined')
                                text = link_title;
                        }
                        break;
                }
            }
            var self_1 = this_1;
            data = { path: path, type: i < path_parts.length - 1 ? et2_vfs.DIR_MIME_TYPE : _value.mime };
            node = jQuery(document.createElement("li"))
                .addClass("vfsFilename")
                .text(text + (i < path_parts.length - 1 ? '/' : ''))
                //.attr('title', egw.decodePath(path))
                .addClass("et2_clickable et2_link")
                .click({ data: data, egw: this_1.egw() }, function (e) {
                if (!self_1.onclick) {
                    e.data.egw.open(e.data.data, "file");
                }
                else if (self_1.click(e)) {
                    e.data.egw.open(e.data.data, "file");
                }
            })
                .appendTo(this_1.span);
        };
        var this_1 = this, data, node;
        for (var i = path_offset; i < path_parts.length; i++) {
            _loop_1(i);
        }
        // Last part of path do default action
        this._bind_default_action(node, data);
    };
    et2_vfs.prototype._bind_default_action = function (node, data) {
        var links = [];
        var widget = this;
        var defaultAction = null;
        var object = null;
        var app = this.getInstanceManager().app;
        while (links.length === 0 && widget.getParent()) {
            object = egw_getAppObjectManager(app).getObjectById(widget.id);
            if (object && object.manager && object.manager.children) {
                links = object.manager.children;
            }
            widget = widget.getParent();
        }
        for (var k in links) {
            if (links[k].default && links[k].enabled.exec(links[k])) {
                defaultAction = links[k];
                break;
            }
        }
        if (defaultAction && !this.onclick) {
            node.off('click').click({ data: data, egw: this.egw() }, function (e) {
                // Wait until object selection happens
                window.setTimeout(function () {
                    // execute default action
                    egw_keyHandler(EGW_KEY_ENTER, false, false, false);
                });
                // Select row
                return true;
            }.bind({ data: data, object: object }));
        }
    };
    /**
     * Code for implementing et2_IDetachedDOM (data grid)
     *
     * @param {array} _attrs array of attribute-names to push further names onto
     */
    et2_vfs.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value");
    };
    et2_vfs.prototype.getDetachedNodes = function () {
        return [this.span[0]];
    };
    et2_vfs.prototype.setDetachedAttributes = function (_nodes, _values) {
        this.span = jQuery(_nodes[0]);
        if (typeof _values["value"] != 'undefined') {
            this.set_value(_values["value"]);
        }
    };
    et2_vfs._attributes = {
        "value": {
            "type": "any",
            "description": "Array of (stat) information about the file"
        }
    };
    /**
     * Mime type of directories
     */
    et2_vfs.DIR_MIME_TYPE = 'httpd/unix-directory';
    return et2_vfs;
}(et2_core_valueWidget_1.et2_valueWidget));
exports.et2_vfs = et2_vfs;
et2_core_widget_1.et2_register_widget(et2_vfs, ["vfs"]);
/**
* vfs-name
* filename automatically urlencoded on return (urldecoded on display to user)
*
* @augments et2_textbox
*/
var et2_vfsName = /** @class */ (function (_super) {
    __extends(et2_vfsName, _super);
    /**
     * Constructor
     *
     * @memberOf et2_vfsName
     */
    function et2_vfsName(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsName._attributes, _child || {})) || this;
        _this.input.addClass("et2_vfs");
        return _this;
    }
    et2_vfsName.prototype.set_value = function (_value) {
        if (_value.path) {
            _value = _value.path;
        }
        try {
            _value = egw.decodePath(_value);
        }
        catch (e) {
            _value = 'Error! ' + _value;
        }
        _super.prototype.set_value.call(this, _value);
    };
    et2_vfsName.prototype.getValue = function () {
        return egw.encodePath(_super.prototype.getValue.call(this) || '');
    };
    return et2_vfsName;
}(et2_widget_textbox_1.et2_textbox));
et2_core_widget_1.et2_register_widget(et2_vfsName, ["vfs-name"]);
/**
* vfs-name
* filename automatically urlencoded on return (urldecoded on display to user)
*
* @augments et2_textbox
*/
var et2_vfsPath = /** @class */ (function (_super) {
    __extends(et2_vfsPath, _super);
    /**
     * Constructor
     *
     * @memberOf et2_vfsName
     */
    function et2_vfsPath(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsPath._attributes, _child || {})) || this;
    }
    et2_vfsPath.prototype.createInputWidget = function () {
        _super.prototype.createInputWidget.call(this);
        this.div = jQuery(document.createElement("div"))
            .addClass('et2_vfsPath');
        this.span = jQuery(document.createElement("ul"))
            .appendTo(this.div);
        this.div.prepend(this.input);
        this.setDOMNode(this.div[0]);
        this.span.on('wheel', function (e) {
            var delta = e.originalEvent["deltaY"] > 0 ? 30 : -30;
            this.scrollLeft = this.scrollLeft - delta;
        });
        this.span.on('mouseover', function (e) {
            if (this.scrollWidth > this.clientWidth) {
                jQuery(this).addClass('scrollable');
            }
            else {
                jQuery(this).removeClass('scrollable');
            }
        });
        this.input.on('focus', function () {
            this.input.val(this.options.value);
            this.span.hide();
        }.bind(this))
            .on('focusout', function () {
            // Can't use show() because it uses the wrong display
            this.span.css('display', 'flex');
            this.input.val('');
        }.bind(this));
    };
    et2_vfsPath.prototype.change = function (_node) {
        if (this.input.val()) {
            this.set_value(this.input.val());
        }
        return _super.prototype.change.call(this, _node);
    };
    et2_vfsPath.prototype.set_value = function (_value) {
        if (_value.path) {
            _value = _value.path;
        }
        if (_value === this.options.value && this._oldValue !== et2_no_init)
            return;
        var path_parts = _value.split('/');
        if (_value === '/')
            path_parts = [''];
        var path = "/";
        var text = '';
        if (this.span)
            this.span.empty().css('display', 'flex');
        this.input.val('');
        var _loop_2 = function (i) {
            path += (path == '/' ? '' : '/') + path_parts[i];
            text = egw.decodePath(path_parts[i]);
            var image = path == '/' ? this_2.egw().image('navbar', 'api') : this_2.egw().image(text);
            // Nice human-readable stuff for apps
            if (path_parts[1] == 'apps') {
                if (i === 1) {
                    text = this_2.egw().lang('applications');
                }
                else if (i === 2) {
                    text = this_2.egw().lang(path_parts[2]);
                    image = this_2.egw().image('navbar', path_parts[2].toLowerCase());
                }
                else if (!isNaN(text)) {
                    var link_title = this_2.egw().link_title(path_parts[2], path_parts[3], function (title) {
                        if (!title)
                            return;
                        jQuery('li', this.span).first().text(title);
                    }, this_2);
                    if (link_title && typeof link_title !== 'undefined')
                        text = link_title;
                }
            }
            var self_2 = this_2;
            var node = jQuery(document.createElement("li"))
                .addClass("vfsPath et2_clickable")
                .text(text)
                //.attr('title', egw.decodePath(path))
                .click({ data: path, egw: this_2.egw() }, function (e) {
                return self_2.set_value(e.data.data);
            })
                .prependTo(this_2.span);
            if (image && !this_2.options.noicon) {
                node.prepend(this_2.egw().image_element(image));
            }
            jQuery(this_2.getDOMNode()).append(this_2.span);
        };
        var this_2 = this;
        for (var i = 0; i < path_parts.length; i++) {
            _loop_2(i);
        }
        if (this.isAttached() && this.options.value !== _value) {
            this._oldValue = this.options.value;
            this.options.value = _value;
            this.change();
        }
    };
    et2_vfsPath.prototype.getValue = function () {
        return this.options ? this.options.value : null;
    };
    et2_vfsPath._attributes = {
        noicon: {
            type: "boolean",
            description: "suppress folder icons",
            default: true
        }
    };
    return et2_vfsPath;
}(et2_vfsName));
exports.et2_vfsPath = et2_vfsPath;
et2_core_widget_1.et2_register_widget(et2_vfsPath, ["vfs-path"]);
/**
* vfs-name
* filename automatically urlencoded on return (urldecoded on display to user)
*
* @augments et2_textbox_ro
*/
var et2_vfsName_ro = /** @class */ (function (_super) {
    __extends(et2_vfsName_ro, _super);
    /**
     * Constructor
     *
     * @memberOf et2_vfsName_ro
     */
    /**
     * Constructor
     */
    function et2_vfsName_ro(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsName_ro._attributes, _child || {})) || this;
    }
    et2_vfsName_ro.prototype.set_value = function (_value) {
        if (_value.path) {
            _value = _value.path;
        }
        try {
            _value = egw.decodePath(_value);
        }
        catch (e) {
            _value = 'Error! ' + _value;
        }
        _super.prototype.set_value.call(this, _value);
    };
    et2_vfsName_ro.prototype.getValue = function () {
        return egw.encodePath(_super.prototype.getValue.call(this) || '');
    };
    return et2_vfsName_ro;
}(et2_widget_textbox_1.et2_textbox_ro));
et2_core_widget_1.et2_register_widget(et2_vfsName_ro, ["vfs-name_ro"]);
/**
* vfs-mime: icon for mimetype of file, or thumbnail
* incl. optional link overlay icon, if file is a symlink
*
* Creates following structure
* <span class="iconOverlayContainer">
*   <img class="et2_vfs vfsMimeIcon" src="..."/>
*   <span class="overlayContainer">
*      <img class="overlay" src="etemplate/templates/default/images/link.png"/>
*   </span>
* </span>
*
* span.overlayContainer is optional and only generated for symlinks
* @augments et2_valueWidget
*/
var et2_vfsMime = /** @class */ (function (_super) {
    __extends(et2_vfsMime, _super);
    function et2_vfsMime() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    return et2_vfsMime;
}(expose((_a = /** @class */ (function (_super) {
        __extends(et2_vfsMime, _super);
        /**
         * Constructor
         *
         * @memberOf et2_vfsMime
         */
        function et2_vfsMime(_parent, _attrs, _child) {
            var _this = 
            // Call the inherited constructor
            _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsMime._attributes, _child || {})) || this;
            _this.iconOverlayContainer = null;
            _this.image = null;
            _this.iconOverlayContainer = jQuery(document.createElement('span')).addClass('iconOverlayContainer');
            _this.image = jQuery(document.createElement("img"));
            _this.image.addClass("et2_vfs vfsMimeIcon");
            _this.iconOverlayContainer.append(_this.image);
            _this.setDOMNode(_this.iconOverlayContainer[0]);
            return _this;
        }
        /**
         * Handler for expose slide action, from expose
         * Returns data needed for the given index, or false to let expose handle it
         *
         * @param {Gallery} gallery
         * @param {integer} index
         * @param {DOMNode} slide
         * @return {Array} array of objects consist of media contnet
         */
        et2_vfsMime.prototype.expose_onslide = function (gallery, index, slide) {
            var content = false;
            if (this.options.expose_callback && typeof this.options.expose_callback == 'function') {
                //Call the callback to load more items
                content = this.options.expose_callback.call(this, [gallery, index]);
                if (content)
                    this.add(content);
            }
            return content;
        };
        /**
         * Function to get media content to feed the expose
         *
         * @param {type} _value
         * @returns {Array} return an array of object consists of media content
         */
        et2_vfsMime.prototype.getMedia = function (_value) {
            var base_url = egw.webserverUrl.match(/^\/ig/) ? egw(window).window.location.origin + egw.webserverUrl : egw.webserverUrl;
            var mediaContent = [{
                    title: _value.name,
                    type: _value.mime,
                    href: _value.download_url
                }];
            // check if download_url is not already an url (some stream-wrappers allow to specify that!)
            if (_value.download_url && (_value.download_url[0] == '/' || _value.download_url.substr(0, 4) != 'http')) {
                mediaContent[0].href = base_url + _value.download_url;
                if (mediaContent[0].href && mediaContent[0].href.match(/\/webdav.php/, 'ig')) {
                    mediaContent[0]["download_href"] = mediaContent[0].href + '?download';
                }
            }
            if (_value && _value.mime && _value.mime.match(/video\//, 'ig')) {
                mediaContent[0]["thumbnail"] = this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime);
            }
            else {
                mediaContent[0]["thumbnail"] = _value.path && _value.mime ?
                    this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime) :
                    this.image.attr('src') + '&thheight=128';
            }
            return mediaContent;
        };
        et2_vfsMime.prototype.set_value = function (_value) {
            if (typeof _value !== 'object') {
                this.egw().debug("warn", "%s only has path, needs array with path & mime", this.id, _value);
                // Keep going, will be 'unknown type'
            }
            var src = this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime);
            if (src) {
                // Set size of thumbnail
                if (src.indexOf("thumbnail.php") > -1) {
                    if (this.options.size) {
                        src += "&thsize=" + this.options.size;
                    }
                    else if (this.options.thumb_mime_size) {
                        var mime_size = this.options.thumb_mime_size.split(',');
                        var mime_regex = RegExp(_value.mime.split('/')[0]);
                        if (typeof mime_size != 'undefined' && jQuery.isArray(mime_size)
                            && !isNaN(mime_size[mime_size.length - 1]) && isNaN(mime_size[0]) && this.options.thumb_mime_size.match(mime_regex[0], 'ig')) {
                            src += "&thsize=" + mime_size[mime_size.length - 1];
                        }
                    }
                    this.image.css("max-width", "100%");
                }
                this.image.attr("src", src);
                // tooltip for mimetypes with available detailed thumbnail
                if (_value.mime && _value.mime.match(/application\/vnd\.oasis\.opendocument\.(text|presentation|spreadsheet|chart)/)) {
                    var tooltip_target = this.image.parent().parent().parent().length > 0 ?
                        // Nextmatch row
                        this.image.parent().parent().parent() :
                        // Not in nextmatch
                        this.image.parent();
                    tooltip_target.tooltip({
                        items: "img",
                        position: { my: "right top", at: "left top", collision: "flipfit" },
                        content: function () {
                            return '<img src="' + this.src + '&thsize=512"/>';
                        }
                    });
                }
            }
            // add/remove link icon, if file is (not) a symlink
            if ((_value.mode & et2_vfsMode.types.l) == et2_vfsMode.types.l) {
                if (typeof this.overlayContainer == 'undefined') {
                    this.overlayContainer = jQuery(document.createElement('span')).addClass('overlayContainer');
                    this.overlayContainer.append(jQuery(document.createElement('img'))
                        .addClass('overlay').attr('src', this.egw().image('link', 'etemplate')));
                    this.iconOverlayContainer.append(this.overlayContainer);
                }
            }
            else if (typeof this.overlayContainer != 'undefined') {
                this.overlayContainer.remove();
                delete this.overlayContainer;
            }
        };
        /**
         * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
         * Override to add needed attributes
         *
         * @param {array} _attrs array of attribute-names to push further names onto
         */
        et2_vfsMime.prototype.getDetachedAttributes = function (_attrs) {
            _attrs.push("value", "class");
        };
        et2_vfsMime.prototype.getDetachedNodes = function () {
            return [this.node, this.iconOverlayContainer[0], this.image[0]];
        };
        et2_vfsMime.prototype.setDetachedAttributes = function (_nodes, _values) {
            this.iconOverlayContainer = jQuery(_nodes[1]);
            this.image = jQuery(_nodes[2]);
            this.node = _nodes[0];
            this.overlayContainer = _nodes[0].children[1];
            if (typeof _values['class'] != "undefined") {
                this.image.addClass(_values['class']);
            }
            if (typeof _values['value'] != "undefined") {
                this.set_value(_values['value']);
            }
        };
        return et2_vfsMime;
    }(et2_core_valueWidget_1.et2_valueWidget)),
    _a._attributes = {
        "value": {
            "type": "any",
            "description": "Array of (stat) information about the file"
        },
        "size": {
            "name": "Icon size",
            "type": "integer",
            "description": "Size of icon / thumbnail, in pixels",
            "default": et2_no_init
        },
        "expose_callback": {
            "name": "expose_callback",
            "type": "js",
            "default": et2_no_init,
            "description": "JS code which is executed when expose slides."
        },
        expose_view: {
            name: "Expose view",
            type: "boolean",
            default: true,
            description: "Clicking on an image would popup an expose view"
        },
        thumb_mime_size: {
            name: "Image thumbnail size",
            type: "string",
            default: "",
            description: " Size of thumbnail in pixel for specified mime type with syntax of: mime_type(s),size (eg. image,video,128)"
        }
    },
    _a.legacyOptions = ["size"],
    _a))));
;
et2_core_widget_1.et2_register_widget(et2_vfsMime, ["vfs-mime"]);
/**
* vfs-size
* Human readable file sizes
*
* @augments et2_description
*/
var et2_vfsSize = /** @class */ (function (_super) {
    __extends(et2_vfsSize, _super);
    /**
     * Constructor
     *
     * @memberOf et2_vfsSize
     */
    function et2_vfsSize(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsSize._attributes, _child || {})) || this;
        _this.span.addClass("et2_vfs");
        return _this;
    }
    et2_vfsSize.prototype.human_size = function (size) {
        if (typeof size !== "number") {
            size = parseInt(size);
        }
        if (!size) {
            size = 0;
        }
        var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        var i = 0;
        while (size >= 1024) {
            size /= 1024;
            ++i;
        }
        return size.toFixed(i == 0 ? 0 : 1) + ' ' + units[i];
    };
    et2_vfsSize.prototype.set_value = function (_value) {
        if (_value.size) {
            _value = _value.size;
        }
        jQuery(this.node).text(this.human_size(_value));
    };
    et2_vfsSize.prototype.setDetachedAttributes = function (_nodes, _values) {
        if (typeof _values["value"] !== "undefined") {
            this.node = _nodes[0];
            this.set_value(_values["value"]);
            delete _values["value"];
        }
        _super.prototype.setDetachedAttributes.call(this, _nodes, _values);
    };
    et2_vfsSize._attributes = {
        "value": {
            "type": "integer"
        }
    };
    return et2_vfsSize;
}(et2_widget_description_1.et2_description));
et2_core_widget_1.et2_register_widget(et2_vfsSize, ["vfs-size"]);
/**
* vfs-mode: textual representation of permissions + extra bits
*
* @augments et2_description
*/
var et2_vfsMode = /** @class */ (function (_super) {
    __extends(et2_vfsMode, _super);
    /**
     * Constructor
     *
     * @memberOf et2_vfsMode
     */
    function et2_vfsMode(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsMode._attributes, _child || {})) || this;
        _this.span.addClass("et2_vfs");
        return _this;
    }
    /**
     * Get text for file stuff
     * Result will be like -rwxr--r--.  First char is type, then read, write, execute (or other bits) for
     * user, group, world
     *
     * @param {number} _value vfs mode
     */
    et2_vfsMode.prototype.text_mode = function (_value) {
        var text = [];
        if (typeof _value != "number") {
            _value = parseInt(_value);
        }
        if (!_value)
            return "----------";
        // Figure out type
        var type = 'u'; // unknown
        for (var flag in et2_vfsMode.types) {
            if ((_value & et2_vfsMode.types[flag]) == et2_vfsMode.types[flag]) {
                type = flag;
                break;
            }
        }
        // World, group, user - build string backwards
        for (var i = 0; i < 3; i++) {
            for (var perm in et2_vfsMode.perms) {
                if (_value & et2_vfsMode.perms[perm]) {
                    text.unshift(perm);
                }
                else {
                    text.unshift("-");
                }
            }
            _value = _value >> 3;
        }
        // Sticky / UID / GID
        for (var i = 0; i < et2_vfsMode.sticky.length; i++) {
            if (et2_vfsMode.sticky[i].mask & _value) {
                var current = text[et2_vfsMode.sticky[i].position];
                text[et2_vfsMode.sticky[i].position] = et2_vfsMode.sticky[i]["char"];
                if (current == 'x')
                    text[et2_vfsMode.sticky[i].position].toLowerCase();
            }
        }
        return type + text.join('');
    };
    et2_vfsMode.prototype.set_value = function (_value) {
        if (_value.size) {
            _value = _value.size;
        }
        var text = this.text_mode(_value);
        jQuery(this.node).text(text);
    };
    et2_vfsMode.prototype.setDetachedAttributes = function (_nodes, _values) {
        if (typeof _values["value"] !== "undefined") {
            this.node = _nodes[0];
            this.set_value(_values["value"]);
            delete _values["value"];
        }
        _super.prototype.setDetachedAttributes.call(this, _nodes, _values);
    };
    // Masks for file types
    et2_vfsMode.types = {
        'l': 0xA000,
        's': 0xC000,
        'p': 0x1000,
        'c': 0x2000,
        'd': 0x4000,
        'b': 0x6000,
        '-': 0x8000 // Regular
    };
    // Sticky / UID / GID
    et2_vfsMode.sticky = [
        { mask: 0x200, "char": "T", position: 9 },
        { mask: 0x400, "char": "S", position: 6 },
        { mask: 0x800, "char": "S", position: 3 } // SUID
    ];
    et2_vfsMode.perms = {
        'x': 0x1,
        'w': 0x2,
        'r': 0x4 // Read
    };
    return et2_vfsMode;
}(et2_widget_description_1.et2_description));
et2_core_widget_1.et2_register_widget(et2_vfsMode, ["vfs-mode"]);
/**
* vfs-uid / vfs-gid: Displays the name for an ID.
* Same as read-only selectAccount, except if there's no user it shows "root"
*
* @augments et2_selectAccount_ro
*/
var et2_vfsUid = /** @class */ (function (_super) {
    __extends(et2_vfsUid, _super);
    function et2_vfsUid() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    /**
     * @memberOf et2_vfsUid
     * @param _node
     * @param _value
     */
    et2_vfsUid.prototype.set_title = function (_node, _value) {
        if (_value == "") {
            arguments[1] = "root";
        }
        _super.prototype.set_title.call(this, _node, _value);
    };
    return et2_vfsUid;
}(et2_widget_selectAccount_1.et2_selectAccount_ro));
et2_core_widget_1.et2_register_widget(et2_vfsUid, ["vfs-uid", "vfs-gid"]);
/* vfs-upload aka VFS file:       displays either download and delete (x) links or a file upload
*   + ID is either a vfs path or colon separated $app:$id:$relative_path, eg: infolog:123:special/offer
*   + if empty($id) / new entry, file is created in a hidden temporary directory in users home directory
*     and calling app is responsible to move content of that dir to entry directory, after entry is saved
*   + option: required mimetype or regular expression for mimetype to match, eg. '/^text\//i' for all text files
*   + if path ends in a slash, multiple files can be uploaded, their original filename is kept then
*
* @augments et2_file
*/
var et2_vfsUpload = /** @class */ (function (_super) {
    __extends(et2_vfsUpload, _super);
    /**
     * Constructor
     *
     * @param _parent
     * @param attrs
     * @memberof et2_vfsUpload
     */
    function et2_vfsUpload(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsUpload._attributes, _child || {})) || this;
        _this.list = null;
        jQuery(_this.node).addClass("et2_vfs");
        if (!_this.options.path) {
            _this.options.path = _this.options.id;
        }
        // If the path is a directory, allow multiple uploads
        if (_this.options.path.substr(-1) == '/') {
            _this.set_multiple(true);
        }
        _this.list = jQuery(document.createElement('table')).appendTo(_this.node);
        return _this;
    }
    /**
     * Get any specific async upload options
     */
    et2_vfsUpload.prototype.getAsyncOptions = function (self) {
        return jQuery.extend({}, _super.prototype.getAsyncOptions.call(this, self), {
            target: egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_upload")
        });
    };
    /**
     * If there is a file / files in the specified location, display them
     * Value is the information for the file[s] in the specified location.
     *
     * @param {Object{}} _value
     */
    et2_vfsUpload.prototype.set_value = function (_value) {
        // Remove previous
        while (this._children.length > 0) {
            var node = this._children[this._children.length - 1];
            this.removeChild(node);
            node.destroy();
        }
        this.progress.empty();
        this.list.empty();
        // Set new
        if (typeof _value == 'object' && _value && Object.keys(_value).length) {
            for (var i in _value) {
                this._addFile(_value[i]);
            }
        }
        return true;
    };
    et2_vfsUpload.prototype.getDOMNode = function (sender) {
        if (sender && sender !== this && sender._type.indexOf('vfs') >= 0) {
            var value = sender.getValue && sender.getValue() || sender.options.value || {};
            var row = jQuery("[data-path='" + (value.path.replace(/'/g, '&quot')) + "']", this.list);
            if (sender._type === 'vfs-mime') {
                return jQuery('.icon', row).get(0) || null;
            }
            else {
                return jQuery('.title', row).get(0) || null;
            }
        }
        else {
            return _super.prototype.getDOMNode.call(this, sender);
        }
    };
    /**
     * Add in the request id
     *
     * @param {type} form
     */
    et2_vfsUpload.prototype.beforeSend = function (form) {
        var extra = _super.prototype.beforeSend.call(this, form);
        extra["path"] = this.options.path;
        return extra;
    };
    /**
     * A file upload is finished, update the UI
     *
     * @param {object} file
     * @param {string|object} response
     */
    et2_vfsUpload.prototype.finishUpload = function (file, response) {
        var result = _super.prototype.finishUpload.call(this, file, response);
        if (typeof response == 'string')
            response = jQuery.parseJSON(response);
        if (response.response[0] && typeof response.response[0].data.length == 'undefined') {
            for (var key in response.response[0].data) {
                var value = response.response[0].data[key];
                if (value && value.path) {
                    this._addFile(value);
                    jQuery("[data-file='" + file.fileName.replace(/'/g, '&quot') + "']", this.progress).hide();
                }
            }
        }
        return result;
    };
    et2_vfsUpload.prototype._addFile = function (file_data) {
        if (jQuery("[data-path='" + file_data.path.replace(/'/g, '&quot') + "']").remove().length) {
            for (var child_index = this._children.length - 1; child_index >= 0; child_index--) {
                var child = this._children[child_index];
                if (child.options.value.path === file_data.path) {
                    this.removeChild(child);
                    child.destroy();
                }
            }
        }
        // Set up for expose
        if (file_data && typeof file_data.download_url === "undefined") {
            file_data.download_url = "/webdav.php" + file_data.path;
        }
        var row = jQuery(document.createElement("tr"))
            .attr("data-path", file_data.path.replace(/'/g, '&quot'))
            .attr("draggable", "true")
            .appendTo(this.list);
        jQuery(document.createElement("td"))
            .addClass('icon')
            .appendTo(row);
        jQuery(document.createElement("td"))
            .addClass('title')
            .appendTo(row);
        var mime = et2_core_widget_1.et2_createWidget('vfs-mime', { value: file_data }, this);
        // Trigger expose on click, if supported
        var vfs_attrs = { value: file_data, onclick: undefined };
        if (file_data && (typeof file_data.download_url != 'undefined')) {
            var fe_mime = egw_get_file_editor_prefered_mimes(file_data.mime);
            // Check if the link entry is mime with media type, in order to open it in expose view
            if (typeof file_data.mime === 'string' &&
                (file_data.mime.match(mime.mime_regexp, 'ig') || (fe_mime && fe_mime.mime[file_data.mime]))) {
                vfs_attrs.onclick = function (ev) {
                    ev.stopPropagation();
                    // Pass it off to the associated vfsMime widget
                    jQuery('img', this.parentNode.parentNode).trigger("click");
                    return false;
                };
            }
        }
        var vfs = et2_core_widget_1.et2_createWidget('vfs', vfs_attrs, this);
        // If already attached, need to do this explicitly
        if (this.isAttached()) {
            mime.set_value(file_data);
            vfs.set_value(file_data);
            mime.doLoadingFinished();
            vfs.doLoadingFinished();
        }
        // Add in delete button
        if (!this.options.readonly) {
            var self_3 = this;
            var delete_button = jQuery(document.createElement("td"))
                .appendTo(row);
            jQuery("<div />")
                .appendTo(delete_button)
                // We don't use ui-icon because it assigns a bg image
                .addClass("delete icon")
                .bind('click', function () {
                et2_core_widget_1.et2_createWidget("dialog", {
                    callback: function (button) {
                        if (button == et2_widget_dialog_1.et2_dialog.YES_BUTTON) {
                            egw.json("filemanager_ui::ajax_action", [
                                'delete',
                                [row.attr('data-path').replace(/&quot/g, "'")],
                                ''
                            ], function (data) {
                                if (data && data.errs == 0) {
                                    row.slideUp(null, row.remove);
                                }
                                if (data && data.msg) {
                                    self_3.egw().message(data.msg, data.errs == 0 ? 'success' : 'error');
                                }
                            }).sendRequest();
                        }
                    },
                    message: self_3.egw().lang('Delete file') + '?',
                    title: self_3.egw().lang('Confirmation required'),
                    buttons: et2_widget_dialog_1.et2_dialog.BUTTONS_YES_NO,
                    dialog_type: et2_widget_dialog_1.et2_dialog.QUESTION_MESSAGE,
                    width: 250
                }, self_3);
            });
        }
    };
    et2_vfsUpload._attributes = {
        "value": {
            "type": "any" // Either nothing, or an object with file info
        },
        "path": {
            "name": "Path",
            "description": "Upload files to the specified VFS path",
            "type": "string",
            "default": ''
        }
    };
    et2_vfsUpload.legacyOptions = ["mime"];
    return et2_vfsUpload;
}(et2_widget_file_1.et2_file));
et2_core_widget_1.et2_register_widget(et2_vfsUpload, ["vfs-upload"]);
var et2_vfsSelect = /** @class */ (function (_super) {
    __extends(et2_vfsSelect, _super);
    /**
     * Constructor
     *
     * @param _parent
     * @param _attrs
     * @memberOf et2_vfsSelect
     */
    function et2_vfsSelect(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_vfsSelect._attributes, _child || {})) || this;
        // Allowed mode options
        _this.modes = ['open', 'open-multiple', 'saveas', 'select-dir'];
        // Allow no child widgets
        _this.supportedWidgetClasses = [];
        _this.button = jQuery(document.createElement("button"))
            .attr("title", _this.egw().lang("Select file(s) from VFS"))
            .addClass("et2_button et2_vfs_btn")
            .css("background-image", "url(" + _this.egw().image("filemanager/navbar") + ")");
        if (_this.options.readonly) {
            _this.button.hide();
        }
        if (_this.options.button_caption != "") {
            _this.button.text(_this.options.button_caption);
        }
        _this.setDOMNode(_this.button[0]);
        return _this;
    }
    et2_vfsSelect.prototype._content = function (_content, _callback) {
        egw(window).loading_prompt('vfs-select', true, '', 'body');
        var self = this;
        if (typeof app.vfsSelectUI != "undefined") {
            if (this.dialog && this.dialog.div)
                this.dialog.div.dialog('close');
            delete app.vfsSelectUI;
        }
        var attrs = {
            mode: this.options.mode,
            label: this.options.button_label,
            path: this.options.path || null,
            mime: this.options.mime || null,
            name: this.options.name,
            method: this.options.method,
            recentPaths: et2_vfsSelect._getRecentPaths()
        };
        var callback = _callback || this._buildDialog;
        egw(window).json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_content', [_content, attrs], function (_content) {
            egw(window).loading_prompt('vfs-select', false);
            callback.apply(self, arguments);
        }).sendRequest(true);
    };
    /**
     * Builds file navigator dialog
     *
     * @param {object} _data content
     */
    et2_vfsSelect.prototype._buildDialog = function (_data) {
        if (!_data.content.mode.match(/open|open-multiple|saveas|select-dir/)) {
            egw.debug('warn', 'Mode is not matched!');
            return;
        }
        var self = this;
        var buttons = [
            {
                text: egw.lang(_data.content.label),
                id: "submit",
                image: _data.content.mode.match(/saveas|select-dir/) ? "save" : this.options.button_icon
            }
        ];
        var extra_buttons_action = {};
        if (this.options.extra_buttons && this.options.method) {
            for (var i = 0; i < this.options.extra_buttons.length; i++) {
                delete (this.options.extra_buttons[i]['click']);
                buttons.push(this.options.extra_buttons[i]);
                extra_buttons_action[this.options.extra_buttons[i]['id']] = this.options.extra_buttons[i]['id'];
            }
        }
        buttons.push({ text: egw.lang("Close"), id: "close", image: "cancel" });
        // Don't rely only on app_name to fetch et2 object as app_name may not
        // always represent current app of the window, e.g.: mail admin account.
        // Try to fetch et2 from its template name.
        var etemplate = jQuery('form').data('etemplate');
        var et2;
        if (etemplate && etemplate.name && !app[egw(window).app_name()]) {
            et2 = etemplate2.getByTemplate(etemplate.name)[0];
        }
        else {
            et2 = etemplate2.getByApplication(egw(window).app_name())[0];
        }
        var data = jQuery.extend(_data, { 'currentapp': egw(window).app_name(), etemplate_exec_id: et2.etemplate_exec_id });
        // define a mini app object for vfs select UI
        app.vfsSelectUI = new app.classes.vfsSelectUI;
        // callback for dialog
        this.submit_callback = function (submit_button_id, submit_value, savemode) {
            if ((submit_button_id == 'submit' || (extra_buttons_action && extra_buttons_action[submit_button_id])) && submit_value) {
                var files = [];
                switch (_data.content.mode) {
                    case 'open-multiple':
                        if (submit_value.dir && submit_value.dir.selected) {
                            for (var key in Object.keys(submit_value.dir.selected)) {
                                if (submit_value.dir.selected[key] != "") {
                                    files.push(submit_value.path + '/' + submit_value.dir.selected[key]);
                                }
                            }
                        }
                        break;
                    case 'select-dir':
                        files = submit_value.path;
                        break;
                    default:
                        if (self.options.method === 'download')
                            submit_value.path = _data.content.download_baseUrl;
                        files = submit_value.path + '/' + submit_value.name;
                        if (self.options.mode === 'saveas' && !savemode) {
                            for (var p in _data.content.dir) {
                                if (_data.content.dir[p]['name'] == submit_value.name) {
                                    var saveModeDialogButtons = [
                                        { text: self.egw().lang("Yes"), id: "overwrite", class: "ui-priority-primary", "default": true, image: 'check' },
                                        { text: self.egw().lang("Rename"), id: "rename", image: 'edit' },
                                        { text: self.egw().lang("Cancel"), id: "cancel" }
                                    ];
                                    return et2_widget_dialog_1.et2_dialog.show_prompt(function (_button_id, _value) {
                                        switch (_button_id) {
                                            case "overwrite":
                                                return self.submit_callback(submit_button_id, submit_value, 'overwrite');
                                            case "rename":
                                                submit_value.name = _value;
                                                return self.submit_callback(submit_button_id, submit_value, 'rename');
                                        }
                                    }, self.egw().lang('Do you want to overwrite existing file %1 in directory %2?', submit_value.name, submit_value.path), self.egw().lang('File %1 already exists', submit_value.name), submit_value.name, saveModeDialogButtons, null);
                                }
                            }
                        }
                        break;
                }
                et2_vfsSelect._setRecentPaths(submit_value.path);
                self.value = files;
                if (self.options.method && self.options.method !== 'download') {
                    egw(window).request(self.options.method, [self.options.method_id, files, submit_button_id, savemode]).then(function (data) {
                        jQuery(self.node).change();
                    });
                }
                else {
                    jQuery(self.node).change();
                }
                delete app.vfsSelectUI;
                return true;
            }
        };
        this.dialog = et2_core_widget_1.et2_createWidget("dialog", {
            callback: this.submit_callback,
            title: this.options.dialog_title,
            buttons: buttons,
            minWidth: 500,
            minHeight: 400,
            width: 400,
            value: data,
            template: egw.webserverUrl + '/api/templates/default/vfsSelectUI.xet?1',
            resizable: false
        }, et2_widget_dialog_1.et2_dialog._create_parent('api'));
        this.dialog.template.uniqueId = 'api.vfsSelectUI';
        app.vfsSelectUI.et2 = this.dialog.template.widgetContainer;
        app.vfsSelectUI.vfsSelectWidget = this;
        // Keep the dialog always at the top
        this.dialog.div.parent().css({ "z-index": 100000 });
        this.dialog.div.on('load', function (e) {
            app.vfsSelectUI.et2_ready(app.vfsSelectUI.et2, 'api.vfsSelectUI');
        });
        // we need an etemplate_exec_id for better handling serverside parts of
        // widgets and since we can not have a etemplate_exec_id specifically
        // for dialog template our best shot is to inherit its parent etemplate_exec_id.
        this.dialog.template.etemplate_exec_id = et2.etemplate_exec_id;
    };
    /**
     * Set recent path into sessionStorage
     * @param {string} _path
     */
    et2_vfsSelect._setRecentPaths = function (_path) {
        var recentPaths = egw.getSessionItem('api', 'vfsRecentPaths') ?
            egw.getSessionItem('api', 'vfsRecentPaths').split(',') : [];
        if (recentPaths.indexOf(_path) == -1)
            recentPaths.push(_path);
        egw.setSessionItem('api', 'vfsRecentPaths', recentPaths);
    };
    /**
     * Get recent paths from sessionStorage
     * @returns {Array} returns an array of recent paths
     */
    et2_vfsSelect._getRecentPaths = function () {
        return egw.getSessionItem('api', 'vfsRecentPaths') ?
            egw.getSessionItem('api', 'vfsRecentPaths').split(',') : [];
    };
    /**
     * click handler
     * @param {event object} e
     */
    et2_vfsSelect.prototype.click = function (e) {
        this._content.call(this, null);
    };
    /**
     * Set the dialog's mode.
     * Valid options are in et2_vfsSelect.modes
     *
     * @param {string} mode 'open', 'open-multiple', 'saveas' or 'select-dir'
     */
    et2_vfsSelect.prototype.set_mode = function (mode) {
        // Check mode
        if (jQuery.inArray(mode, this.modes) < 0) {
            this.egw().debug("warn", "Invalid mode for '%s': %s Valid options:", this.id, mode, this.modes);
            return;
        }
        this.options.mode = mode;
    };
    /**
     * Set the label on the dialog's OK button.
     *
     * @param {string} label
     */
    et2_vfsSelect.prototype.set_button_label = function (label) {
        this.options.button_label = label;
    };
    /**
     * Set the caption for vfs-select button
     *
     * @param {string} caption string value as a caption
     */
    et2_vfsSelect.prototype.set_button_caption = function (caption) {
        this.options.button_caption = caption;
    };
    /**
     * Set the ID passed to the server side callback
     *
     * @param {string} id
     */
    et2_vfsSelect.prototype.set_method_id = function (id) {
        this.options.method_id = id;
    };
    et2_vfsSelect.prototype.set_readonly = function (readonly) {
        this.options.readonly = Boolean(readonly);
        if (this.options.readonly) {
            this.button.hide();
        }
        else {
            this.button.show();
        }
    };
    et2_vfsSelect.prototype.set_value = function (value) {
        this.value = value;
    };
    et2_vfsSelect.prototype.getValue = function () {
        return this.value;
    };
    et2_vfsSelect._attributes = {
        "mode": {
            name: "Dialog mode",
            type: "string",
            description: "One of {open|open-multiple|saveas|select-dir}",
            default: "open-multiple"
        },
        "method": {
            name: "Server side callback",
            type: "string",
            description: "Server side callback to process selected value(s) in \n\
		app.class.method or class::method format.  The first parameter will \n\
		be Method ID, the second the file list. 'download' is reserved and it \n\
		means it should use download_baseUrl instead of path in value (no method\n\
		 will be actually executed)."
        },
        "method_id": {
            name: "Method ID",
            type: "any",
            description: "optional parameter passed to server side callback.\n\
		Can be a string or a function.",
            default: ""
        },
        "path": {
            name: "Path",
            type: "string",
            description: "Start path in VFS.  Leave unset to use the last used path."
        },
        "mime": {
            name: "Mime type",
            type: "any",
            description: "Limit display to the given mime-type"
        },
        "button_label": {
            name: "Button label",
            description: "Set the label on the dialog's OK button.",
            default: "open"
        },
        "value": {
            type: "any",
            description: "Array of paths (strings)"
        },
        "button_caption": {
            name: "button caption",
            type: "string",
            default: "Select files from Filemanager ...",
            description: "Caption for vfs-select button.",
            translate: true
        },
        "button_icon": {
            name: "button icon",
            type: "string",
            default: "check",
            description: "Custom icon to show on submit button."
        },
        "name": {
            name: "File name",
            type: "any",
            description: "file name",
            default: ""
        },
        "dialog_title": {
            name: "dialog title",
            type: "string",
            default: "Save as",
            description: "Title of dialog",
            translate: true
        },
        "extra_buttons": {
            name: "extra action buttons",
            type: "any",
            description: "Extra buttons passed to dialog. It's co-related to method."
        }
    };
    return et2_vfsSelect;
}(et2_core_inputWidget_1.et2_inputWidget));
exports.et2_vfsSelect = et2_vfsSelect;
;
et2_core_widget_1.et2_register_widget(et2_vfsSelect, ["vfs-select"]);
//# sourceMappingURL=et2_widget_vfs.js.map