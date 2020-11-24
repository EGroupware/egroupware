"use strict";
/**
 * EGroupware eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
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
    /vendor/bower-asset/jquery/dist/jquery.js;
    et2_core_interfaces;
    et2_core_baseWidget;
    expose;
    /vendor/bower-asset/cropper/dist/cropper.min.js;
*/
var et2_core_baseWidget_1 = require("./et2_core_baseWidget");
var et2_core_widget_1 = require("./et2_core_widget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
/**
 * Class which implements the "image" XET-Tag
 *
 * @augments et2_baseWidget
 */
var et2_image = /** @class */ (function (_super) {
    __extends(et2_image, _super);
    /**
     * Constructor
     */
    function et2_image(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_image._attributes, _child || {})) || this;
        _this.image = null;
        // Create the image or a/image tag
        _this.image = jQuery(document.createElement("img"));
        if (_this.options.label) {
            _this.image.attr("alt", _this.options.label).attr("title", _this.options.label);
        }
        if (_this.options.href) {
            _this.image.addClass('et2_clickable');
        }
        if (_this.options["class"]) {
            _this.image.addClass(_this.options["class"]);
        }
        _this.setDOMNode(_this.image[0]);
        return _this;
    }
    et2_image.prototype.click = function (_ev) {
        if (this.options.href) {
            this.egw().open_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
        }
        else {
            _super.prototype.click.call(this, _ev);
        }
    };
    et2_image.prototype.transformAttributes = function (_attrs) {
        _super.prototype.transformAttributes.call(this, _attrs);
        // Check to expand name
        if (typeof _attrs["src"] != "undefined") {
            var manager = this.getArrayMgr("content");
            if (manager && _attrs["src"]) {
                var src = manager.getEntry(_attrs["src"], false, true);
                if (typeof src != "undefined" && src !== null) {
                    if (typeof src == "object") {
                        src = egw().link('/index.php', src);
                    }
                    _attrs["src"] = src;
                }
            }
        }
    };
    et2_image.prototype.set_label = function (_value) {
        this.options.label = _value;
        _value = this.egw().lang(_value);
        // label is NOT the alt attribute in eTemplate, but the title/tooltip
        this.image.attr("alt", _value).attr("title", _value);
    };
    et2_image.prototype.setValue = function (_value) {
        // Value is src, images don't get IDs
        this.set_src(_value);
    };
    et2_image.prototype.set_href = function (_value) {
        if (!this.isInTree()) {
            return false;
        }
        this.options.href = _value;
        this.image.wrapAll('<a href="' + _value + '"></a>"');
        var href = this.options.href;
        var popup = this.options.extra_link_popup;
        var target = this.options.extra_link_target;
        var self = this;
        this.image.click(function (e) {
            if (self.options.expose_view) {
                /*
                TODO: Fix after implementing EXPOSE mixin class
                 */
                //self._init_blueimp_gallery(e,_value);
                e.stopImmediatePropagation();
            }
            else {
                egw.open_link(href, target, popup);
            }
            e.preventDefault();
            return false;
        });
        return true;
    };
    /**
     * Set image src
     *
     * @param {string} _value image, app/image or url
     * @return {boolean} true if image was found, false if not (image is either not displayed or default_src is used)
     */
    et2_image.prototype.set_src = function (_value) {
        if (!this.isInTree()) {
            return false;
        }
        this.options.src = _value;
        // allow url's too
        if (_value[0] == '/' || _value.substr(0, 4) == 'http' || _value.substr(0, 5) == 'data:') {
            this.image.attr('src', _value).show();
            return true;
        }
        var src = this.egw().image(_value);
        if (src) {
            this.image.attr("src", src).show();
            return true;
        }
        src = null;
        if (this.options.default_src) {
            src = this.egw().image(this.options.default_src);
        }
        if (src) {
            this.image.attr("src", src).show();
        }
        else {
            this.image.css("display", "none");
        }
        return false;
    };
    /**
     * Function to get media content to feed the expose
     * @param {type} _value
     */
    et2_image.prototype.getMedia = function (_value) {
        var base_url = egw.webserverUrl.match(/^\/ig/) ? egw(window).window.location.origin + egw.webserverUrl + '/' : egw.webserverUrl + '/';
        var mediaContent = [];
        if (_value) {
            mediaContent = [{
                    title: this.options.label,
                    href: base_url + _value,
                    type: this.options.type + "/*",
                    thumbnail: base_url + _value
                }];
        }
        return mediaContent;
    };
    /**
     * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
     *
     * @param {array} _attrs
     */
    et2_image.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("src", "label", "href");
    };
    et2_image.prototype.getDetachedNodes = function () {
        return [this.image[0]];
    };
    et2_image.prototype.setDetachedAttributes = function (_nodes, _values) {
        // Set the given DOM-Nodes
        this.image = jQuery(_nodes[0]);
        // Set the attributes
        if (_values["src"]) {
            this.set_src(_values["src"]);
        }
        // Not valid, but we'll deal
        if (_values["value"]) {
            this.setValue(_values["value"]);
        }
        if (_values["label"]) {
            this.set_label(_values["label"]);
        }
        if (_values["href"]) {
            this.image.addClass('et2_clickable');
            this.set_href(_values["href"]);
        }
    };
    et2_image._attributes = {
        "src": {
            "name": "Image",
            "type": "string",
            "description": "Displayed image"
        },
        default_src: {
            name: "Default image",
            type: "string",
            description: "Image to use if src is not found"
        },
        "href": {
            "name": "Link Target",
            "type": "string",
            "description": "Link URL, empty if you don't wan't to display a link.",
            "default": et2_no_init
        },
        "extra_link_target": {
            "name": "Link target",
            "type": "string",
            "default": "_self",
            "description": "Link target descriptor"
        },
        "extra_link_popup": {
            "name": "Popup",
            "type": "string",
            "description": "widthxheight, if popup should be used, eg. 640x480"
        },
        "imagemap": {
            // TODO: Do something with this
            "name": "Image map",
            "description": "Currently not implemented"
        },
        "label": {
            "name": "Label",
            "type": "string",
            "description": "Label for image"
        },
        "expose_view": {
            name: "Expose view",
            type: "boolean",
            default: false,
            description: "Clicking on an image with href value would popup an expose view, and will show image referenced by href."
        }
    };
    et2_image.legacyOptions = ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"];
    return et2_image;
}(et2_core_baseWidget_1.et2_baseWidget));
et2_core_widget_1.et2_register_widget(et2_image, ["image"]);
/**
* Widget displaying an application icon
*/
var et2_appicon = /** @class */ (function (_super) {
    __extends(et2_appicon, _super);
    function et2_appicon() {
        return _super !== null && _super.apply(this, arguments) || this;
    }
    et2_appicon.prototype.set_src = function (_app) {
        if (!_app)
            _app = this.egw().app_name();
        this.image.addClass('et2_appicon');
        return _super.prototype.set_src.call(this, _app == 'sitemgr-link' ? 'sitemgr/sitemgr-link' : // got removed from jdots
            (this.egw().app(_app, 'icon_app') || _app) + '/' + (this.egw().app(_app, 'icon') || 'navbar'));
    };
    et2_appicon._attributes = {
        default_src: {
            name: "Default image",
            type: "string",
            default: "nonav",
            description: "Image to use if there is no application icon"
        }
    };
    return et2_appicon;
}(et2_image));
et2_core_widget_1.et2_register_widget(et2_appicon, ["appicon"]);
/**
* Avatar widget to display user profile picture or
* user letter avatar based on user's firstname lastname.
*
* @augments et2_baseWidget
*/
var et2_avatar = /** @class */ (function (_super) {
    __extends(et2_avatar, _super);
    function et2_avatar(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_avatar._attributes, _child || {})) || this;
        if (_this.options.frame == 'circle') {
            _this.image.attr('style', 'border-radius:50%');
        }
        if (_this.options.contact_id)
            _this.setValue(_this.options.contact_id);
        return _this;
    }
    /**
     * Generate letter avatar with given data
     * @param {type} _fname
     * @param {type} _lname
     * @param {type} _id
     * @returns {string} return data url
     */
    et2_avatar.lavatar = function (_fname, _lname, _id) {
        var str = _fname + _lname + _id;
        var getBgColor = function (_str) {
            var hash = 0;
            for (var i = 0; i < _str.length; i++) {
                hash = _str[i].charCodeAt(0) + hash;
            }
            return et2_avatar.LAVATAR_BG_COLORS[hash % et2_avatar.LAVATAR_BG_COLORS.length];
        };
        var bg = getBgColor(str);
        var size = et2_avatar.LAVATAR_SIZE * (window.devicePixelRatio ? window.devicePixelRatio : 1);
        var text = (_fname ? _fname[0].toUpperCase() : "") + (_lname ? _lname[0].toUpperCase() : "");
        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        var context = canvas.getContext("2d");
        context.fillStyle = bg;
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.font = Math.round(canvas.width / 2) + "px Arial";
        context.textAlign = "center";
        context.fillStyle = et2_avatar.LAVATAR_TEXT_COLOR;
        context.fillText(text, size / 2, size / 1.5);
        var dataURL = canvas.toDataURL();
        canvas.remove();
        return dataURL;
    };
    /**
     * Function runs after uplaod in avatar dialog is finished and it tries to
     * update image and cropper container.
     * @param {type} e
     */
    et2_avatar.uploadAvatar_onFinish = function (e) {
        var file = e.data.resumable.files[0].file;
        var reader = new FileReader();
        reader.onload = function (e) {
            jQuery('#_cropper_image').attr('src', e.target.result);
            jQuery('#_cropper_image').cropper('replace', e.target.result);
        };
        reader.readAsDataURL(file);
    };
    /**
     * Function to set contact id
     * contact id could be in one of these formats:
     *		'number', will be consider as contact_id
     *		'contact:number', similar to above
     *		'account:number', will be consider as account id
     * @example: contact_id = "account:4"
     *
     * @param {string} _contact_id contact id could be as above mentioned formats
     */
    et2_avatar.prototype.set_contact_id = function (_contact_id) {
        var params = {};
        var id = 'contact_id';
        this.image.addClass('et2_avatar');
        if (!_contact_id) {
            _contact_id = this.egw().user('account_id');
        }
        else if (_contact_id.match(/account:/)) {
            id = 'account_id';
            _contact_id = _contact_id.replace('account:', '');
        }
        else {
            id = 'contact_id';
            _contact_id = _contact_id.replace('contact:', '');
        }
        // if our src (incl. cache-buster) already includes the correct id, use that one
        if (this.options.src && this.options.src.match("(&|\\?)contact_id=" + _contact_id + "(&|\\$)")) {
            return;
        }
        params[id] = _contact_id;
        this.set_src(egw.link('/api/avatar.php', params));
    };
    /**
     * Function to set value
     */
    et2_avatar.prototype.setValue = function (_value) {
        this.set_contact_id(_value);
    };
    /**
     * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
     */
    et2_avatar.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("contact_id", "label", "href");
    };
    et2_avatar.prototype.setDetachedAttributes = function (_nodes, _values) {
        // Set the given DOM-Nodes
        this.image = jQuery(_nodes[0]);
        if (_values["contact_id"]) {
            this.set_contact_id(_values["contact_id"]);
        }
        if (_values["label"]) {
            this.set_label(_values["label"]);
        }
        if (_values["href"]) {
            this.image.addClass('et2_clickable');
            this.set_href(_values["href"]);
        }
    };
    /**
     * Build Editable Mask Layer (EML) in order to show edit/delete actions
     * on top of profile picture.
     * @param {boolean} _noDelete disable delete button in initialization
     */
    et2_avatar.prototype._buildEditableLayer = function (_noDelete) {
        var self = this;
        // editable mask layer (eml)
        var eml = jQuery(document.createElement('div'))
            .addClass('eml')
            .insertAfter(this.image);
        // edit button
        jQuery(document.createElement('div'))
            .addClass('emlEdit')
            .click(function () {
            var buttons = [
                { "button_id": 1, "text": self.egw().lang('save'), id: 'save', image: 'check', "default": true },
                { "button_id": 0, "text": self.egw().lang('cancel'), id: 'cancel', image: 'cancelled' }
            ];
            var dialog = function (_title, _value, _buttons, _egw_or_appname) {
                return et2_createWidget("dialog", {
                    callback: function (_buttons, _value) {
                        if (_buttons == 'save') {
                            var canvas = jQuery('#_cropper_image').cropper('getCroppedCanvas');
                            self.image.attr('src', canvas.toDataURL("image/jpeg", 1.0));
                            self.egw().json('addressbook.addressbook_ui.ajax_update_photo', [self.getInstanceManager().etemplate_exec_id, canvas.toDataURL('image/jpeg', 1.0)], function (res) {
                                if (res) {
                                    del.show();
                                }
                            }).sendRequest();
                        }
                    },
                    title: _title || egw.lang('Input required'),
                    buttons: _buttons || et2_dialog.BUTTONS_OK_CANCEL,
                    value: {
                        content: _value
                    },
                    width: "90%",
                    height: "450",
                    resizable: false,
                    position: "top+10",
                    template: egw.webserverUrl + '/api/templates/default/avatar_edit.xet?2'
                }, et2_dialog._create_parent(_egw_or_appname));
            };
            dialog(egw.lang('Edit avatar'), { photo: self.options.contact_id }, buttons, null);
        })
            .appendTo(eml);
        // delete button
        var del = jQuery(document.createElement('div'))
            .addClass('emlDelete')
            .click(function () {
            et2_dialog.show_dialog(function (_btn) {
                if (_btn == et2_dialog.YES_BUTTON) {
                    self.egw().json('addressbook.addressbook_ui.ajax_update_photo', [self.getInstanceManager().etemplate_exec_id, null], function (res) {
                        if (res) {
                            self.image.attr('src', '');
                            del.hide();
                            egw.refresh('Avatar Deleted.', egw.app_name());
                        }
                    }).sendRequest();
                }
            }, egw.lang('Delete this photo?'), egw.lang('Delete'), null, et2_dialog.BUTTONS_YES_NO);
        })
            .appendTo(eml);
        if (_noDelete)
            del.hide();
        // invisible the mask
        eml.css('opacity', '0');
        eml.parent().css('position', "relative");
        // bind handler for activating actions on editable mask
        eml.on({
            mouseover: function () { eml.css('opacity', '0.9'); },
            mouseout: function () { eml.css('opacity', '0'); }
        });
    };
    /**
     * We need to build the Editable Mask Layer after widget gets loaded
     */
    et2_avatar.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        var self = this;
        if (this.options.contact_id && this.options.editable) {
            egw(window).json('addressbook.addressbook_ui.ajax_noPhotoExists', [this.options.contact_id], function (noPhotoExists) {
                if (noPhotoExists)
                    self.image.attr('src', '');
                self._buildEditableLayer(noPhotoExists);
            }).sendRequest(true);
        }
        if (this.options.crop) {
            jQuery(this.image).cropper({
                aspectRatio: 1 / 1,
                crop: function (e) {
                    console.log(e);
                }
            });
        }
        return true;
    };
    et2_avatar._attributes = {
        "contact_id": {
            name: "Contact id",
            type: "string",
            default: "",
            description: "Contact id should be either user account_id {account:number} or contact_id {contact:number or number}"
        },
        "default_src": {
            "ignore": true
        },
        "frame": {
            name: "Avatar frame",
            type: "string",
            default: "circle",
            description: "Define the shape of frame that avatar will be shown inside it. it can get {circle,rectangle} values which default value is cicle."
        },
        editable: {
            name: "Edit avatar",
            type: "boolean",
            default: false,
            description: "Make avatar widget editable to be able to crop profile picture or upload a new photo"
        },
        crop: {
            name: "Crop avatar",
            type: "boolean",
            default: false,
            description: "Create crop container and cropping feature"
        }
    };
    /**
     * background oolor codes
     */
    et2_avatar.LAVATAR_BG_COLORS = [
        '#5a8770', '#b2b7bb', '#6fa9ab', '#f5af29',
        '#0088b9', '#f18636', '#d93a37', '#a6b12e',
        '#0088b9', '#f18636', '#d93a37', '#a6b12e',
        '#5c9bbc', '#f5888d', '#9a89b5', '#407887',
        '#9a89b5', '#5a8770', '#d33f33', '#a2b01f',
        '#f0b126', '#0087bf', '#f18636', '#0087bf',
        '#b2b7bb', '#72acae', '#9c8ab4', '#5a8770',
        '#eeb424', '#407887'
    ];
    /**
     * Text color
     */
    et2_avatar.LAVATAR_TEXT_COLOR = '#ffffff';
    et2_avatar.LAVATAR_SIZE = 128;
    return et2_avatar;
}(et2_image));
et2_core_widget_1.et2_register_widget(et2_avatar, ["avatar"]);
/**
* Avatar readonly widget to only display user profile picture or
* user letter avatar based on user's firstname lastname.
*/
var et2_avatar_ro = /** @class */ (function (_super) {
    __extends(et2_avatar_ro, _super);
    function et2_avatar_ro(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_avatar_ro._attributes, _child || {})) || this;
        _this.options.editable = false;
        return _this;
    }
    return et2_avatar_ro;
}(et2_avatar));
et2_core_widget_1.et2_register_widget(et2_avatar_ro, ["avatar_ro"]);
/**
* Letter Avatar widget to display user profile picture (given url) or
* user letter avatar based on user's firstname lastname.
*
* It will use client-side lavatar if all the following conditions are met:
*  - contact_id, lname and fname are all set.
*  - the given src url includes flag of lavatar=1 which means there's
*    no personal avatar set for the contact yet.
*
* @augments et2_baseWidget
*/
var et2_lavatar = /** @class */ (function (_super) {
    __extends(et2_lavatar, _super);
    function et2_lavatar(_parent, _attrs, _child) {
        // Call the inherited constructor
        return _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_lavatar._attributes, _child || {})) || this;
    }
    et2_lavatar.prototype.set_src = function (_url) {
        if (_url && decodeURIComponent(_url).match("lavatar=1") && (this.options.fname || this.options.lname) && this.options.contact_id) {
            this.set_src(et2_avatar.lavatar(this.options.fname, this.options.lname, this.options.contact_id));
            return false;
        }
        _super.prototype.set_src.call(this, _url);
    };
    et2_lavatar._attributes = {
        lname: {
            name: "last name",
            type: "string",
            default: "",
            description: ""
        },
        fname: {
            name: "first name",
            type: "string",
            default: "",
            description: ""
        },
        contact_id: {
            name: "contact id",
            type: "string",
            default: "",
            description: ""
        }
    };
    return et2_lavatar;
}(et2_image));
et2_core_widget_1.et2_register_widget(et2_lavatar, ["lavatar"]);
//# sourceMappingURL=et2_widget_image.js.map