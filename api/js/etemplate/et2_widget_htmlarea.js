"use strict";
/**
 * EGroupware eTemplate2 - JS widget for HTML editing
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn@egroupware.org>
 * @copyright Hadi Nategh <hn@egroupware.org>
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
exports.et2_htmlarea = void 0;
/*egw:uses
    jsapi.jsapi; // Needed for egw_seperateJavaScript
    /vendor/tinymce/tinymce/tinymce.min.js;
    et2_core_editableWidget;
*/
var et2_core_editableWidget_1 = require("./et2_core_editableWidget");
var et2_core_inheritance_1 = require("./et2_core_inheritance");
var et2_core_widget_1 = require("./et2_core_widget");
/**
 * @augments et2_inputWidget
 */
var et2_htmlarea = /** @class */ (function (_super) {
    __extends(et2_htmlarea, _super);
    /**
     * Constructor
     */
    function et2_htmlarea(_parent, _attrs, _child) {
        var _this = 
        // Call the inherited constructor
        _super.call(this, _parent, _attrs, et2_core_inheritance_1.ClassWithAttributes.extendAttributes(et2_htmlarea._attributes, _child || {})) || this;
        _this.editor = null;
        _this.htmlNode = null;
        _this.editor = null; // TinyMce editor instance
        _this.supportedWidgetClasses = []; // Allow no child widgets
        _this.htmlNode = jQuery(document.createElement(_this.options.readonly ? "div" : "textarea"))
            .addClass('et2_textbox_ro');
        if (_this.options.height) {
            _this.htmlNode.css('height', _this.options.height);
        }
        _this.setDOMNode(_this.htmlNode[0]);
        return _this;
    }
    /**
     *
     * @returns {undefined}
     */
    et2_htmlarea.prototype.doLoadingFinished = function () {
        _super.prototype.doLoadingFinished.call(this);
        this.init_editor();
        return true;
    };
    et2_htmlarea.prototype.init_editor = function () {
        if (this.mode == 'ascii' || this.editor != null || this.options.readonly)
            return;
        var imageUpload;
        var self = this;
        if (this.options.imageUpload && this.options.imageUpload[0] !== '/' && this.options.imageUpload.substr(0, 4) != 'http') {
            imageUpload = egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_htmlarea_upload") +
                '&request_id=' + this.getInstanceManager().etemplate_exec_id + '&widget_id=' + this.options.imageUpload + '&type=htmlarea';
            imageUpload = imageUpload.substr(egw.webserverUrl.length + 1);
        }
        else if (imageUpload) {
            imageUpload = this.options.imageUpload.substr(egw.webserverUrl.length + 1);
        }
        else {
            imageUpload = egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_htmlarea_upload") +
                '&request_id=' + this.getInstanceManager().etemplate_exec_id + '&type=htmlarea';
        }
        // default settings for initialization
        var settings = {
            target: this.htmlNode[0],
            body_id: this.dom_id + '_htmlarea',
            menubar: false,
            statusbar: this.options.statusbar,
            toolbar_mode: this.options.toolbar_mode,
            branding: false,
            resize: false,
            height: this.options.height,
            width: this.options.width,
            end_container_on_empty_block: true,
            mobile: {
                theme: 'silver'
            },
            formats: {
                customparagraph: { block: 'p', styles: { "margin-block-start": "0px", "margin-block-end": "0px" } }
            },
            min_height: 100,
            convert_urls: false,
            language: et2_htmlarea.LANGUAGE_CODE[egw.preference('lang', 'common')],
            language_url: egw.webserverUrl + '/api/js/tinymce/langs/' + et2_htmlarea.LANGUAGE_CODE[egw.preference('lang', 'common')] + '.js',
            paste_data_images: true,
            paste_filter_drop: true,
            browser_spellcheck: true,
            contextmenu: false,
            images_upload_url: imageUpload,
            file_picker_callback: jQuery.proxy(this._file_picker_callback, this),
            images_upload_handler: this.options.images_upload_handler,
            init_instance_callback: jQuery.proxy(this._instanceIsReady, this),
            auto_focus: false,
            valid_children: this.options.valid_children,
            plugins: [
                "print searchreplace autolink directionality ",
                "visualblocks visualchars image link media template fullscreen",
                "codesample table charmap hr pagebreak nonbreaking anchor toc ",
                "insertdatetime advlist lists textcolor wordcount imagetools ",
                "colorpicker textpattern help paste code searchreplace tabfocus"
            ],
            toolbar: et2_htmlarea.TOOLBAR_SIMPLE,
            block_formats: "Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;" +
                "Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre;Custom Paragraph=customparagraph",
            font_formats: "Andale Mono=andale mono,times;Arial=arial,helvetica," +
                "sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book " +
                "antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;" +
                "Courier New=courier new,courier;Georgia=georgia,palatino;" +
                "Helvetica=helvetica;Impact=impact,chicago;Segoe=segoe,segoe ui;Symbol=symbol;" +
                "Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal," +
                "monaco;Times New Roman=times new roman,times;Trebuchet " +
                "MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;" +
                "Wingdings=wingdings,zapf dingbats",
            fontsize_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
            setup: function (ed) {
                ed.on('init', function () {
                    this.focus();
                    this.execCommand('fontName', false, egw.preference('rte_font', 'common'));
                    this.execCommand('fontSize', false, egw.preference('rte_font_size', 'common')
                        + egw.preference('rte_font_unit', 'common'));
                });
            }
        };
        // extend default settings with configured options and preferences
        jQuery.extend(settings, this._extendedSettings());
        this.tinymce = tinymce.init(settings);
        // make sure value gets set in case of widget gets loaded by delay like
        // inside an inactive tabs
        this.tinymce.then(function () {
            self.set_value(self.htmlNode.val());
            self.resetDirty();
            if (self.editor && self.editor.editorContainer) {
                self.editor.formatter.toggle(egw.preference('rte_formatblock', 'common'));
                jQuery(self.editor.editorContainer).height(self.options.height);
                self.editor.execCommand('fontName', false, egw.preference('rte_font', 'common'));
                self.editor.execCommand('fontSize', false, egw.preference('rte_font_size', 'common')
                    + egw.preference('rte_font_unit', 'common'));
                jQuery(self.editor.iframeElement.contentWindow.document).on('dragenter', function () {
                    if (jQuery('#dragover-tinymce').length < 1)
                        jQuery("<style id='dragover-tinymce'>.dragover:after {height:calc(100% - " + jQuery(this).height() + "px) !important;}</style>").appendTo('head');
                });
            }
        });
    };
    /**
     * set disabled
     *
     * @param {type} _value
     * @returns {undefined}
     */
    et2_htmlarea.prototype.set_disabled = function (_value) {
        _super.prototype.set_disabled.call(this, _value);
        if (_value) {
            jQuery(this.tinymce_container).css('display', 'none');
        }
        else {
            jQuery(this.tinymce_container).css('display', 'flex');
        }
    };
    et2_htmlarea.prototype.set_readonly = function (_value) {
        if (this.options.readonly === _value)
            return;
        var value = this.get_value();
        this.options.readonly = _value;
        if (this.options.readonly) {
            if (this.editor)
                this.editor.remove();
            this.htmlNode = jQuery(document.createElement(this.options.readonly ? "div" : "textarea"))
                .addClass('et2_textbox_ro');
            if (this.options.height) {
                this.htmlNode.css('height', this.options.height);
            }
            this.editor = null;
            this.setDOMNode(this.htmlNode[0]);
            this.set_value(value);
        }
        else {
            if (!this.editor) {
                this.htmlNode = jQuery(document.createElement("textarea"))
                    .val(value);
                if (this.options.height || this.options.editable_height) {
                    this.htmlNode.css('height', (this.options.editable_height ? this.options.editable_height : this.options.height));
                }
                this.setDOMNode(this.htmlNode[0]);
                this.init_editor();
            }
        }
    };
    /**
     * Callback function runs when the filepicker in image dialog is clicked
     *
     * @param {type} _callback
     * @param {type} _value
     * @param {type} _meta
     */
    et2_htmlarea.prototype._file_picker_callback = function (_callback, _value, _meta) {
        if (typeof this.file_picker_callback == 'function')
            return this.file_picker_callback.call(arguments, this);
        var callback = _callback;
        // Don't rely only on app_name to fetch et2 object as app_name may not
        // always represent current app of the window, e.g.: mail admin account.
        // Try to fetch et2 from its template name.
        var etemplate = jQuery('form').data('etemplate');
        var et2;
        if (etemplate && etemplate.name && !app[egw(window).app_name()]) {
            et2 = etemplate2.getByTemplate(etemplate.name)[0]['widgetContainer'];
        }
        else {
            et2 = app[egw(window).app_name()].et2;
        }
        var vfsSelect = et2_createWidget('vfs-select', {
            id: 'upload',
            mode: 'open',
            name: '',
            button_caption: "Link",
            button_label: "Link",
            dialog_title: "Link file",
            method: "download"
        }, et2);
        jQuery(vfsSelect.getDOMNode()).on('change', function () {
            callback(vfsSelect.get_value(), { alt: vfsSelect.get_value() });
        });
        // start the file selector dialog
        vfsSelect.click();
    };
    /**
     * Callback when instance is ready
     *
     * @param {type} _editor
     */
    et2_htmlarea.prototype._instanceIsReady = function (_editor) {
        console.log("Editor: " + _editor.id + " is now initialized.");
        // try to reserve focus state as running command on editor may steal the
        // current focus.
        var focusedEl = jQuery(':focus');
        this.editor = _editor;
        this.editor.on('drop', function (e) {
            e.preventDefault();
        });
        if (!this.disabled)
            jQuery(this.editor.editorContainer).css('display', 'flex');
        this.tinymce_container = this.editor.editorContainer;
        // go back to reserved focused element
        focusedEl.focus();
    };
    /**
     * Takes all relevant preferences into account and set settings accordingly
     *
     * @returns {object} returns a object including all settings
     */
    et2_htmlarea.prototype._extendedSettings = function () {
        var rte_menubar = egw.preference('rte_menubar', 'common');
        var rte_toolbar = egw.preference('rte_toolbar', 'common');
        // we need to have rte_toolbar values as an array
        if (rte_toolbar && typeof rte_toolbar == "object" && this.toolbar == '') {
            rte_toolbar = Object.keys(rte_toolbar).map(function (key) { return rte_toolbar[key]; });
        }
        else if (this.toolbar != '') {
            rte_toolbar = this.toolbar.split(',');
        }
        var settings = {
            fontsize_formats: et2_htmlarea.FONT_SIZE_FORMATS[egw.preference('rte_font_unit', 'common')],
            menubar: parseInt(rte_menubar) && this.menubar ? true : typeof rte_menubar != 'undefined' ? false : this.menubar
        };
        switch (this.mode) {
            case 'simple':
                settings['toolbar'] = et2_htmlarea.TOOLBAR_SIMPLE;
                break;
            case 'extended':
                settings['toolbar'] = et2_htmlarea.TOOLBAR_EXTENDED;
                break;
            case 'advanced':
                settings['toolbar'] = et2_htmlarea.TOOLBAR_ADVANCED;
                break;
            default:
                this.mode = '';
        }
        // take rte_toolbar into account if no mode restrictly set from template
        if (rte_toolbar && !this.mode) {
            var toolbar_diff = et2_htmlarea.TOOLBAR_LIST.filter(function (i) { return !(rte_toolbar.indexOf(i) > -1); });
            settings['toolbar'] = et2_htmlarea.TOOLBAR_ADVANCED;
            toolbar_diff.forEach(function (a) {
                var r = new RegExp(a);
                settings['toolbar'] = settings['toolbar'].replace(r, '');
            });
        }
        return settings;
    };
    et2_htmlarea.prototype.destroy = function () {
        if (this.editor) {
            try {
                this.editor.destroy();
            }
            catch (e) {
                egw().debug("Error destroying editor", e);
            }
        }
        this.editor = null;
        this.tinymce = null;
        this.tinymce_container = null;
        this.htmlNode.remove();
        this.htmlNode = null;
        _super.prototype.destroy.call(this);
    };
    et2_htmlarea.prototype.set_value = function (_value) {
        this._oldValue = _value;
        if (this.editor) {
            this.editor.setContent(_value);
        }
        else {
            if (this.options.readonly) {
                this.htmlNode.empty().append(_value);
            }
            else {
                this.htmlNode.val(_value);
            }
        }
        this.value = _value;
    };
    et2_htmlarea.prototype.getValue = function () {
        return this.editor ? this.editor.getContent() : (this.options.readonly ? this.value : this.htmlNode.val());
    };
    /**
     * Resize htmlNode tag according to window size
     * @param {type} _height excess height which comes from window resize
     */
    et2_htmlarea.prototype.resize = function (_height) {
        var _a, _b;
        if (_height && this.options.resize_ratio !== '0') {
            // apply the ratio
            _height = (this.options.resize_ratio != '') ? _height * this.options.resize_ratio : _height;
            if (_height != 0) {
                if (this.editor) // TinyMCE HTML
                 {
                    var h = void 0;
                    if (typeof this.editor.iframeElement != 'undefined' && this.editor.editorContainer.clientHeight > 0) {
                        h = (this.editor.editorContainer.clientHeight + _height) > 0 ?
                            (this.editor.editorContainer.clientHeight) + _height : this.editor.settings.min_height;
                    }
                    else // fallback height size
                     {
                        h = this.editor.settings.min_height + _height;
                    }
                    jQuery(this.editor.editorContainer).height(h);
                    jQuery(this.editor.iframeElement).height(h - (((_a = this.editor.editorContainer.getElementsByClassName('tox-editor-header')[0]) === null || _a === void 0 ? void 0 : _a.clientHeight) + ((_b = this.editor.editorContainer.getElementsByClassName('tox-statusbar')[0]) === null || _b === void 0 ? void 0 : _b.clientHeight)));
                }
                else // No TinyMCE
                 {
                    this.htmlNode.height(this.htmlNode.height() + _height);
                }
            }
        }
    };
    et2_htmlarea._attributes = {
        mode: {
            'name': 'Mode',
            'description': 'One of {ascii|simple|extended|advanced}',
            'default': '',
            'type': 'string'
        },
        height: {
            'name': 'Height',
            'default': et2_no_init,
            'type': 'string'
        },
        width: {
            'name': 'Width',
            'default': et2_no_init,
            'type': 'string'
        },
        value: {
            name: "Value",
            description: "The value of the widget",
            type: "html",
            default: et2_no_init
        },
        imageUpload: {
            name: "imageUpload",
            description: "Url to upload images dragged in or id of link_to widget to it's vfs upload. Can also be just a name for which content array contains a path to upload the picture.",
            type: "string",
            default: null
        },
        file_picker_callback: {
            name: "File picker callback",
            description: "Callback function to get called when file picker is clicked",
            type: 'js',
            default: et2_no_init
        },
        images_upload_handler: {
            name: "Images upload handler",
            description: "Callback function for handling image upload",
            type: 'js',
            default: et2_no_init
        },
        menubar: {
            name: "Menubar",
            description: "Display menubar at the top of the editor",
            type: "boolean",
            default: true
        },
        statusbar: {
            name: "Status bar",
            description: "Enable/disable status bar on the bottom of editor",
            type: "boolean",
            default: true
        },
        valid_children: {
            name: "Valid children",
            description: "Enables to control what child tag is allowed or not allowed of the present tag. For instance: +body[style], makes style tag allowed inside body",
            type: "string",
            default: "+body[style]"
        },
        toolbar: {
            'name': 'Toolbar',
            'description': 'Comma separated string of toolbar actions. It will only be considered if no Mode is restricted.',
            'default': '',
            'type': 'string'
        },
        toolbar_mode: {
            'name': 'toolbar mode',
            'type': 'string',
            'default': 'floating',
            'description': 'It allows to extend the toolbar to accommodate the overflowing toolbar buttons. {floating, sliding, scrolling, wrap}'
        }
    };
    /**
     * Array of toolbars
     * @constant
     */
    et2_htmlarea.TOOLBAR_LIST = ['undo', 'redo', 'formatselect', 'fontselect', 'fontsizeselect',
        'bold', 'italic', 'strikethrough', 'forecolor', 'backcolor', 'link',
        'alignleft', 'aligncenter', 'alignright', 'alignjustify', 'numlist',
        'bullist', 'outdent', 'indent', 'ltr', 'rtl', 'removeformat', 'code', 'image', 'searchreplace', 'fullscreen', 'table'
    ];
    /**
     * arranged toolbars as simple mode
     * @constant
     */
    et2_htmlarea.TOOLBAR_SIMPLE = "undo redo|formatselect fontselect fontsizeselect | bold italic underline removeformat forecolor backcolor | " +
        "alignleft aligncenter alignright alignjustify | bullist " +
        "numlist outdent indent| link image pastetext | table";
    /**
     * arranged toolbars as extended mode
     * @constant
     */
    et2_htmlarea.TOOLBAR_EXTENDED = "fontselect fontsizeselect | bold italic underline strikethrough forecolor backcolor | " +
        "link | alignleft aligncenter alignright alignjustify  | numlist " +
        "bullist outdent indent | removeformat | image | fullscreen | table";
    /**
     * arranged toolbars as advanced mode
     * @constant
     */
    et2_htmlarea.TOOLBAR_ADVANCED = "undo redo| formatselect | fontselect fontsizeselect | bold italic underline strikethrough forecolor backcolor | " +
        "alignleft aligncenter alignright alignjustify | bullist " +
        "numlist outdent indent ltr rtl | removeformat code| link image pastetext | searchreplace | fullscreen | table";
    /**
     * font size formats
     * @constant
     */
    et2_htmlarea.FONT_SIZE_FORMATS = {
        pt: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt",
        px: "8px 9px 10px 11px 12px 14px 16px 18px 20px 22px 24px 26px 28px 36px 48px 72px"
    };
    /**
     * language code represention for TinyMCE lang code
     */
    et2_htmlarea.LANGUAGE_CODE = {
        bg: "bg_BG", ca: "ca", cs: "cs", da: "da", de: "de", en: "en_CA",
        el: "el", "es-es": "es", et: "et", eu: "eu", fa: "fa_IR", fi: "fi",
        fr: "fr_FR", hi: "", hr: "hr", hu: "hu_HU", id: "id", it: "it", iw: "",
        ja: "ja", ko: "ko_KR", lo: "", lt: "lt", lv: "lv", nl: "nl", no: "nb_NO",
        pl: "pl", pt: "pt_PT", "pt-br": "pt_BR", ru: "ru", sk: "sk", sl: "sl_SI",
        sv: "sv_SE", th: "th_TH", tr: "tr_TR", uk: "en_GB", vi: "vi_VN", zh: "zh_CN",
        "zh-tw": "zh_TW"
    };
    return et2_htmlarea;
}(et2_core_editableWidget_1.et2_editableWidget));
exports.et2_htmlarea = et2_htmlarea;
et2_core_widget_1.et2_register_widget(et2_htmlarea, ["htmlarea"]);
//# sourceMappingURL=et2_widget_htmlarea.js.map