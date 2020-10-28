"use strict";
/**
 * EGroupware eTemplate2 - JS URL object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
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
    et2_textbox;
    et2_valueWidget;
    /api/js/jquery/jquery.base64.js;
*/
var et2_core_widget_1 = require("./et2_core_widget");
var et2_widget_textbox_1 = require("./et2_widget_textbox");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_valueWidget_1 = require("./et2_core_valueWidget");
/**
 * Class which implements the "url" XET-Tag, which covers URLs, email & phone
 *
 * @augments et2_textbox
 */
var et2_url = /** @class */ (function (_super_1) {
    __extends(et2_url, _super_1);
    function et2_url() {
        var _this = _super_1 !== null && _super_1.apply(this, arguments) || this;
        _this._button = null;
        _this.value = "";
        return _this;
    }
    /**
     * @memberOf et2_url
     */
    et2_url.prototype.createInputWidget = function () {
        this.input = jQuery(document.createElement("input"))
            .blur(this, this.validate)
            .blur(this, function (e) { e.data.set_value(e.data.getValue()); });
        this._button = null;
        if (this.size) {
            this.set_size(this.size);
        }
        this.setDOMNode(this.input[0]);
    };
    et2_url.prototype.destroy = function () {
        if (this.input) {
            this.input.unbind();
        }
        this._button = null;
        _super_1.prototype.destroy.call(this);
    };
    /**
     * Override parent to update href of 'button'
     *
     * @param _value value to set
     */
    et2_url.prototype.set_value = function (_value) {
        this.update_button(_value);
        _super_1.prototype.set_value.call(this, _value);
    };
    et2_url.prototype.update_button = function (_value) {
        if (this.value == _value)
            return;
        if (_value) {
            // Create button if it doesn't exist yet
            if (this._button == null) {
                this._button = jQuery(document.createElement("a")).addClass("et2_url");
                this.getSurroundings().insertDOMNode(this._button[0]);
                this.getSurroundings().update();
            }
            this._button.removeClass("url phone email").removeAttr("href");
            _value = this.get_link(this.getType(), _value);
            switch (this.getType()) {
                case "url":
                    // Silently use http if no protocol
                    this._button.attr("href", _value).attr("target", "_blank").addClass("url");
                    break;
                case "url-phone":
                case "url-fax":
                    if (_value) {
                        if (typeof _value == 'function') {
                            this._button.click(this, _value).addClass("phone").show();
                        }
                        else {
                            this._button.attr("href", _value).addClass("phone").show();
                        }
                    }
                    else if (_value === false) {
                        // Can't make a good handler, hide button
                        this._button.hide();
                    }
                    break;
                case "url-email":
                    if (typeof _value == 'function') {
                        this._button.click(this, _value).addClass("email");
                    }
                    else {
                        this._button.attr("href", _value).addClass("email");
                    }
                    break;
            }
        }
        else {
            if (this._button)
                this._button.hide();
            if (this._button && this.getSurroundings && this.getSurroundings().removeDOMNode) {
                this.getSurroundings().removeDOMNode(this._button[0]);
            }
            this._button = null;
        }
    };
    et2_url.prototype.get_link = function (type, value) {
        if (!value)
            return false;
        // convert fax numbers to email, if configured
        if (type === 'url-fax' && this.egw().config('fax_email') &&
            (value = value.replace('&#9829;', '').replace('(0)', '').replace(/[^0-9+]/g, ''))) {
            value = value.replace(new RegExp(this.egw().config('fax_email_regexp') || '(.*)'), this.egw().config('fax_email'));
            type = 'url-email';
        }
        switch (type) {
            case "url":
                // Silently use http if no protocol
                if (value.indexOf("://") == -1)
                    value = "http://" + value;
                break;
            case "url-phone":
            case "url-fax":
                // Clean number
                value = value.replace('&#9829;', '').replace('(0)', '');
                value = value.replace(/[abc]/gi, 2).replace(/[def]/gi, 3).replace(/[ghi]/gi, 4).replace(/[jkl]/gi, 5).replace(/[mno]/gi, 6);
                value = value.replace(/[pqrs]/gi, 7).replace(/[tuv]/gi, 8).replace(/[wxyz]/gi, 9);
                // remove everything but numbers and plus, as telephon software might not like it
                value = value.replace(/[^0-9+]/g, '');
                // movile Webkit (iPhone, Android) have precedence over server configuration!
                if (navigator.userAgent.indexOf('AppleWebKit') !== -1 &&
                    (navigator.userAgent.indexOf("iPhone") !== -1 || navigator.userAgent.indexOf("Android") !== -1) &&
                    value.indexOf("tel:") == -1) {
                    value = "tel:" + value;
                }
                else if (this.egw().config("call_link")) {
                    var link = this.egw().config("call_link")
                        // tel: links use no URL encoding according to rfc3966 section-5.1.4
                        .replace("%1", this.egw().config("call_link").substr(0, 4) == 'tel:' ?
                        value : encodeURIComponent(value))
                        .replace("%u", this.egw().user('account_lid'))
                        .replace("%t", this.egw().user('account_phone'));
                    var popup = this.egw().config("call_popup");
                    value = function (ev) {
                        if (popup && popup !== '_self' || !link.match(/^https?:/)) // execute non-http(s) links eg. tel: like before
                         {
                            egw.open_link(link, '_phonecall', popup);
                        }
                        else {
                            // No popup, use AJAX.  We don't care about the response.
                            (egw.window ? egw.window.jQuery : jQuery).ajax({
                                url: link,
                                async: true,
                                dataType: 'json',
                                type: "GET"
                            });
                            ev.preventDefault();
                            return false;
                        }
                    };
                }
                else {
                    // Can't make a good handler
                    return false;
                }
                break;
            case "url-email":
                if (value.indexOf("mailto:") == -1) {
                    value = "mailto:" + value;
                }
                if ((this.egw().user('apps').mail || this.egw().user('apps').felamimail) &&
                    this.egw().preference('force_mailto', 'addressbook') != '1') {
                    return function () { egw.open_link(value); };
                }
                break;
        }
        return value;
    };
    et2_url.prototype.validate = function (e) {
        e.data.hideMessage();
        if (e.data._super) {
            e.data._super.apply(this, arguments);
        }
        // Check value, and correct if possible
        var value = jQuery.trim(e.data.getValue());
        if (value == "")
            return;
        switch (e.data._type) {
            case "url":
                if (value.indexOf("://") == -1 && !e.data.options.allow_path) {
                    e.data.set_value("http://" + value);
                    e.data.showMessage(e.data.egw().lang("Protocol is required"), "hint", true);
                }
                else if (e.data.options.allow_path && value[0] !== '/') {
                    e.data.showMessage(e.data.egw().lang("Path must start with '/'"), "hint", true);
                }
                // Adjust trailing slash - required or forbidden
                if (e.data.options.trailing_slash === true && value[value.length - 1] !== '/') {
                    e.data.set_value(value + '/');
                }
                else if (e.data.options.trailing_slash === false && value[value.length - 1] === '/') {
                    e.data.set_value(value.substr(0, value.length - 1));
                }
                break;
            case "url-email":
                if (!et2_url.EMAIL_PREG.test(value) ||
                    // If they use Text <email>, make sure the <> match
                    (value.indexOf("<") > 0 && value.indexOf(">") != value.length - 1) ||
                    (value.indexOf(">") > 0 && value.indexOf("<") < 0)) {
                    e.data.showMessage("Invalid email", "validation_error", true);
                }
        }
    };
    et2_url.prototype.attachToDOM = function () {
        var res = _super_1.prototype.attachToDOM.call(this);
        if (this.input[0].parentNode)
            jQuery(this.input[0].parentNode).addClass('et2_url_span');
        return res;
    };
    et2_url._attributes = {
        "multiline": {
            "ignore": true
        },
        "allow_path": {
            type: "boolean",
            name: "Allow path",
            description: "Allow a path instead of a URL, path must start with /",
            default: false
        },
        "trailing_slash": {
            type: "boolean",
            name: "Trailing slash",
            description: "Require (or forbid) that the path ends with a /",
            default: et2_no_init
        }
    };
    /**
     * Regexes for validating email addresses incl. email in angle-brackets eg.
     * + "Ralf Becker <rb@stylite.de>"
     * + "Ralf Becker (Stylite AG) <rb@stylite.de>"
     * + "<rb@stylite.de>" or "rb@stylite.de"
     * + '"Becker, Ralf" <rb@stylite.de>'
     * + "'Becker, Ralf' <rb@stylite.de>"
     * but NOT:
     * - "Becker, Ralf <rb@stylite.de>" (contains comma outside " or ' enclosed block)
     * - "Becker < Ralf <rb@stylite.de>" (contains <    ----------- " ---------------)
     *
     * About umlaut or IDN domains: we currently only allow German umlauts in domain part!
     * We forbid all non-ascii chars in local part, as Horde does not yet support SMTPUTF8 extension (rfc6531)
     * and we get a "SMTP server does not support internationalized header data" error otherwise.
     *
     * Using \042 instead of " to NOT stall minifyer!
     *
     * Similar, but not identical, preg is in Etemplate\Widget\Url PHP class!
     * We can not use "(?<![.\s])", used to check that name-part does not end in
     * a dot or white-space. The expression is valid in recent Chrome, but fails
     * eg. in Safari 11.0 or node.js 4.8.3 and therefore grunt uglify!
     * Server-side will fail in that case because it uses the full regexp.
     */
    et2_url.EMAIL_PREG = new RegExp(/^(([^\042',<][^,<]+|\042[^\042]+\042|\'[^\']+\'|"(?:[^"\\]|\\.)*")\s?<)?[^\x00-\x20()\xe2\x80\x8b<>@,;:\042\[\]\x80-\xff]+@([a-z0-9ÄÖÜäöüß](|[a-z0-9ÄÖÜäöüß_-]*[a-z0-9ÄÖÜäöüß])\.)+[a-z]{2,}>?$/i);
    return et2_url;
}(et2_widget_textbox_1.et2_textbox));
et2_core_widget_1.et2_register_widget(et2_url, ["url", "url-email", "url-phone", "url-fax"]);
/**
* et2_url_ro is the readonly implementation of the url, email & phone.
* It renders things as links, when possible
*
* @augments et2_valueWidget
*/
var et2_url_ro = /** @class */ (function (_super_1) {
    __extends(et2_url_ro, _super_1);
    /**
     * Constructor
     *
     * @memberOf et2_url_ro
     */
    function et2_url_ro(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super_1.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_url_ro._attributes, _child || {})) || this;
        _this.value = "";
        _this.span = null;
        _this.value = "";
        _this.span = jQuery(document.createElement("a"))
            .addClass("et2_textbox readonly");
        // Do not a tag if no call_link is set and not in mobile, empty a tag may conflict
        // with some browser telephony addons (eg. telify in FF)
        if (!egw.config('call_link') && _this.getType() == 'url-phone' && !egwIsMobile()) {
            _this.span = jQuery(document.createElement("span"))
                .addClass("et2_textbox readonly");
        }
        if (_this.getType() == 'url-email') {
            _this.span.addClass('et2_email');
        }
        _this.setDOMNode(_this.span[0]);
        return _this;
    }
    et2_url_ro.prototype.set_value = function (_value) {
        this.value = _value;
        var link = et2_url.prototype.get_link(this.getType(), _value);
        if (!link) {
            this.span.text(_value);
            this.span.removeAttr("href");
            return;
        }
        this.span.text(_value);
        switch (this.getType()) {
            case "url":
                this.span.attr("href", link).attr("target", "_blank");
                break;
            case "url-phone":
            case "url-fax":
                if (typeof link == 'function') {
                    this.span.off('click.et2_url');
                    this.span.on('click.et2_url', link);
                    this.span.attr("href", "#");
                }
                else if (link) {
                    this.span.attr("href", link);
                }
                break;
            case "url-email":
                if (typeof link == 'function') {
                    this.span.off('click.et2_url');
                    this.span.on('click.et2_url', link);
                    this.span.removeAttr("href");
                }
                else {
                    this.span.attr("href", link);
                    if (!this.span.attr("target")) {
                        this.span.attr("target", "_blank");
                    }
                }
                // wrap email address if there's a name
                if (this.span.text() && this.span.text().split("<") && this.options.full_email) {
                    var val = this.span.text().split("<");
                    val = val[0] != "" ? val[0] : val[2];
                    // need to preserve the original value somehow
                    // as it's been used for add contact plus feature
                    this.span.attr('title', _value);
                    this.span.text(val.replace(/"/g, ''));
                    this.span.append("<span class='noemail'>" +
                        _value.replace(val, '')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                        + "</span>");
                }
                // Add contact_plus button
                if (this.options.contact_plus) {
                    // If user doesn't have access to addressbook, stop
                    if (!egw.app('addressbook'))
                        return;
                    // Bind onmouseenter event on <a> tag in order to add contact plus
                    // Need to keep span & value so it works inside nextmatch
                    this.span.on('mouseenter', jQuery.proxy(function (event) {
                        event.stopImmediatePropagation();
                        this.widget._add_contact_tooltip.call(this, et2_url_ro.email_cache[this.value]);
                        if (typeof et2_url_ro.email_cache[this.value] === 'undefined') {
                            // Ask server if we know this email
                            this.widget.egw().jsonq('EGroupware\\Api\\Etemplate\\Widget\\Url::ajax_contact', this.value, this.widget._add_contact_tooltip, this);
                        }
                    }, { widget: this, span: this.span, value: this.value }));
                }
                break;
        }
    };
    /**
     * Add a button to add the email address as a contact
     *
     * @param {boolean} email_exists True, or else nothing happens
     */
    et2_url_ro.prototype._add_contact_tooltip = function (email_exists) {
        var value = this.value || this.widget.value || null;
        et2_url_ro.email_cache[value] = email_exists;
        // Close all the others
        jQuery('.et2_email').each(function () {
            if (jQuery(this).tooltip('instance')) {
                jQuery(this).tooltip('close');
            }
        });
        if (email_exists || !this.span.is(':hover'))
            return;
        this.span.tooltip({
            items: 'a.et2_email',
            position: { my: "right top", at: "left top", collision: "flipfit" },
            tooltipClass: "et2_email_popup",
            content: function () {
                // Here we could do all sorts of things
                var extra = {
                    'presets[email]': jQuery(this).attr('title') ? jQuery(this).attr('title') : jQuery(this).text()
                };
                return jQuery('<a href="#" class= "et2_url_email_contactPlus" title="' + egw.lang('Add a new contact') + '"><img src="'
                    + (typeof email_exists === 'undefined' ? egw.image("loading") : egw.image("new")) + '"/></a>')
                    .on('click', function () {
                    egw.open('', 'addressbook', 'add', extra);
                });
            },
            close: function (event, ui) {
                ui.tooltip.hover(function () {
                    jQuery(this).stop(true).fadeTo(400, 1);
                    //.fadeIn("slow"); // doesn't work because of stop()
                }, function () {
                    jQuery(this).fadeOut("400", function () { jQuery(this).remove(); });
                });
            }
        })
            .tooltip("open");
        jQuery('.egwGridView_scrollarea').one('scroll', jQuery.proxy(function () {
            if (this.tooltip("instance")) {
                this.tooltip('destroy');
            }
        }, this.span));
    };
    /**
     * Code for implementing et2_IDetachedDOM
     *
     * @param {array} _attrs array to add further attributes to
     */
    et2_url_ro.prototype.getDetachedAttributes = function (_attrs) {
        _attrs.push("value", "class", "statustext");
    };
    et2_url_ro.prototype.getDetachedNodes = function () {
        return [this.span[0]];
    };
    et2_url_ro.prototype.setDetachedAttributes = function (_nodes, _values) {
        // Update the properties
        this.span = jQuery(_nodes[0]);
        if (typeof _values["value"] != "undefined") {
            this.set_value(_values["value"]);
        }
        if (typeof _values["class"] != "undefined") {
            _nodes[0].setAttribute("class", _values["class"]);
        }
        // Set to original status text if not set for this row
        this.span.attr('title', _values.statustext ? _values.statustext : this.options.statustext);
    };
    et2_url_ro._attributes = {
        "contact_plus": {
            "name": "Add contact button",
            "type": "boolean",
            "default": false,
            "description": "Allow to add email as contact to addressbook"
        },
        "full_email": {
            "name": "Show full email address",
            "type": "boolean",
            "default": true,
            "description": "Allow to show full email address if ture otherwise show only name"
        }
    };
    et2_url_ro.email_cache = [];
    return et2_url_ro;
}(et2_core_valueWidget_1.et2_valueWidget));
et2_core_widget_1.et2_register_widget(et2_url_ro, ["url_ro", "url-email_ro", "url-phone_ro", "url-fax_ro"]);
//# sourceMappingURL=et2_widget_url.js.map