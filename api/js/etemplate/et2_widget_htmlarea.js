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

/*egw:uses
	jsapi.jsapi; // Needed for egw_seperateJavaScript
	/api/js/tinymce/tinymce.min.js;
	et2_core_baseWidget;
*/

/**
 * @augments et2_inputWidget
 */
var et2_htmlarea = (function(){ "use strict"; return et2_inputWidget.extend([et2_IResizeable],
{
	attributes: {
		'mode': {
			'name': 'Mode',
			'description': 'One of {ascii|simple|extended|advanced}',
			'default': '',
			'type': 'string'
		},
		'height': {
			'name': 'Height',
			'default': et2_no_init,
			'type': 'string'
		},
		'width': {
			'name': 'Width',
			'default': et2_no_init,
			'type': 'string'
		},
		value: {
			name: "Value",
			description: "The value of the widget",
			type: "html",	// "string" would remove html tags by running html_entity_decode
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
			description: "Enable/disable status bar on the bottom of ediotr",
			type: "boolean",
			default: true
		}
	},

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_htmlarea
	 */
	init: function(_parent, _attrs) {
		this._super.apply(this, arguments);
		this.editor = null; // TinyMce editor instance
		this.supportedWidgetClasses = []; // Allow no child widgets
		this.htmlNode = jQuery(document.createElement("textarea"))
			.css('height', this.options.height)
			.addClass('et2_textbox_ro');
		this.setDOMNode(this.htmlNode[0]);
	},

	/**
	 *
	 * @returns {undefined}
	 */
	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		if(this.mode == 'ascii' || this.editor != null) return;
		var imageUpload = '';
		var self = this;
		if (this.options.imageUpload && this.options.imageUpload[0] !== '/' && this.options.imageUpload.substr(0, 4) != 'http')
		{
			imageUpload = egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_htmlarea_upload")+
						'&request_id='+this.getInstanceManager().etemplate_exec_id+'&widget_id='+this.options.imageUpload+'&type=htmlarea';
			imageUpload = imageUpload.substr(egw.webserverUrl.length+1);
		}
		else if (imageUpload)
		{
			imageUpload = this.options.imageUpload.substr(egw.webserverUrl.length+1);
		}
		else
		{
			imageUpload = egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_htmlarea_upload")+
						'&request_id='+this.getInstanceManager().etemplate_exec_id+'&type=htmlarea';
		}
		// default settings for initialization
		var settings = {
			target: this.htmlNode[0],
			body_id: this.dom + '_htmlarea',
			menubar: false,
			statusbar: this.options.statusbar,
			branding: false,
			resize: false,
			height: this.options.height,
			width: this.options.width,
			min_height: 100,
			language: et2_htmlarea.LANGUAGE_CODE[egw.preference('lang', 'common')],
			paste_data_images: true,
			browser_spellcheck: true,
			contextmenu: false,
			images_upload_url: imageUpload,
			file_picker_callback: jQuery.proxy(this._file_picker_callback, this),
			images_upload_handler: this.options.images_upload_handler,
			init_instance_callback : jQuery.proxy(this._instanceIsReady, this),
			plugins: [
				"print searchreplace autolink directionality "+
				"visualblocks visualchars image link media template "+
				"codesample table charmap hr pagebreak nonbreaking anchor toc "+
				"insertdatetime advlist lists textcolor wordcount imagetools "+
				"colorpicker textpattern help paste code searchreplace"
			],
			toolbar: "formatselect | fontselect fontsizeselect | bold italic strikethrough forecolor backcolor | "+
					"link | alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent  | removeformat | image",
			block_formats: "Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;"+
					"Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre",
			font_formats: "Andale Mono=andale mono,times;Arial=arial,helvetica,"+
					"sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book "+
					"antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;"+
					"Courier New=courier new,courier;Georgia=georgia,palatino;"+
					"Helvetica=helvetica;Impact=impact,chicago;Symbol=symbol;"+
					"Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,"+
					"monaco;Times New Roman=times new roman,times;Trebuchet "+
					"MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;"+
					"Wingdings=wingdings,zapf dingbats",
			fontsize_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
		};

		// extend default settings with configured options and preferences
		jQuery.extend(settings, this._extendedSettings());
		this.tinymce = tinymce.init(settings);
		// make sure value gets set in case of widget gets loaded by delay like
		// inside an inactive tabs
		this.tinymce.then(()=> {
			self.set_value(self.htmlNode.val());
			if (self.editor && self.editor.editorContainer)
			{
				jQuery(self.editor.editorContainer).height(self.options.height);
			}
		});
	},

	/**
	 * set disabled
	 *
	 * @param {type} _value
	 * @returns {undefined}
	 */
	set_disabled: function(_value)
	{
		this._super.apply(this, arguments);
		if (_value)
		{
			jQuery(this.tinymce_container).css('display', 'none');
		}
		else
		{
			jQuery(this.tinymce_container).css('display', 'flex');
		}
	},

	/**
	 * Callback function runs when the filepicker in image dialog is clicked
	 *
	 * @param {type} _callback
	 * @param {type} _value
	 * @param {type} _meta
	 * @returns {unresolved}
	 */
	_file_picker_callback: function(_callback, _value, _meta) {
		if (typeof this.file_picker_callback == 'function') return this.file_picker_callback.call(arguments, this);
		var callback = _callback;

		// Don't rely only on app_name to fetch et2 object as app_name may not
		// always represent current app of the window, e.g.: mail admin account.
		// Try to fetch et2 from its template name.
		var etemplate = jQuery('form').data('etemplate');
		var et2 = {};
		if (etemplate && etemplate.name && !app[egw(window).app_name()])
		{
			et2 = etemplate2.getByTemplate(etemplate.name)[0]['widgetContainer'];
		}
		else
		{
			et2 = app[egw(window).app_name()].et2;
		}

		var vfsSelect = et2_createWidget('vfs-select', {
			id:'upload',
			mode: 'open',
			name: '',
			button_caption:"Link",
			button_label:"Link",
			dialog_title: "Link file",
			method: "download"
		}, et2);
		jQuery(vfsSelect.getDOMNode()).on('change', function (){
			callback(vfsSelect.get_value(), {alt:vfsSelect.get_value()});
		});

		// start the file selector dialog
		vfsSelect.click();
	},

	/**
	 * Callback when instance is ready
	 *
	 * @param {type} _editor
	 */
	_instanceIsReady: function(_editor) {
		console.log("Editor: " + _editor.id + " is now initialized.");
		this.editor = _editor;
		this.editor.execCommand('fontName', true, egw.preference('rte_font', 'common'));
		this.editor.execCommand('fontSize',	true, egw.preference('rte_font_size', 'common')
				+ egw.preference('rte_font_unit', 'common'));
		if (!this.disabled) jQuery(this.editor.editorContainer).css('display', 'flex');
		this.tinymce_container = this.editor.editorContainer;
	},

	/**
	 * Takes all relevant preferences into account and set settings accordingly
	 *
	 * @returns {object} returns a object including all settings
	 */
	_extendedSettings: function () {

		var rte_menubar = egw.preference('rte_menubar', 'common');
		var rte_toolbar = egw.preference('rte_toolbar', 'common');
		var settings = {
			fontsize_formats: et2_htmlarea.FONT_SIZE_FORMATS[egw.preference('rte_font_unit', 'common')],
			menubar: parseInt(rte_menubar) && this.menubar ? true : typeof rte_menubar != 'undefined' ? false : this.menubar
		};

		var mode = this.mode || egw.preference('rte_features', 'common');
		switch (mode)
		{
			case 'simple':
				settings.toolbar = et2_htmlarea.TOOLBAR_SIMPLE;
				break;
			case 'extended':
				settings.toolbar = et2_htmlarera.TOOLBAR_EXTENDED;
				break;
			case 'advanced':
				settings.toolbar = et2_htmlarea.TOOLBAR_ADVANCED;
				break;
		}

		// take rte_toolbar into account if no mode restrictly set from template
		if (rte_toolbar && !this.mode)
		{
			var toolbar_diff = et2_htmlarea.TOOLBAR_LIST.filter((i) => {return !(rte_toolbar.indexOf(i) > -1);});
			settings.toolbar = et2_htmlarea.TOOLBAR_ADVANCED;
			toolbar_diff.forEach((a) => {
				let r = new RegExp(a);
				settings.toolbar = settings.toolbar.replace(r, '');
			});
		}
		return settings;
	},

	destroy: function() {
		if (this.editor)
		{
			this.editor.destroy();
		}
		this.editor = null;
		this.tinymce = null;
		this.tinymce_container = null;
		this.htmlNode.remove();
		this.htmlNode = null;
		this._super.apply(this, arguments);
	},
	set_value: function(_value) {
		this._oldValue = _value;
		if (this.editor)
		{
			this.editor.setContent(_value);
		}
		else
		{
			this.htmlNode.val(_value);
			this.value = _value;
		}
	},

	getValue: function() {
		return this.editor ? this.editor.getContent() : this.htmlNode.val();
	},

	/**
	 * Resize htmlNode tag according to window size
	 * @param {type} _height excess height which comes from window resize
	 */
	resize: function (_height)
	{
		if (_height && this.options.resize_ratio !== '0')
		{
			// apply the ratio
			_height = (this.options.resize_ratio != '')? _height * this.options.resize_ratio: _height;
			if (_height != 0)
			{
				if (this.editor) // TinyMCE HTML
				{
					var h = 0;
					if (typeof this.editor.iframeElement !='undefined' && this.editor.editorContainer.clientHeight > 0)
					{
						h = (this.editor.editorContainer.clientHeight + _height) > 0 ?
						(this.editor.editorContainer.clientHeight) + _height: this.editor.settings.min_height;
					}
					else // fallback height size
					{
						h = this.editor.settings.min_height + _height;
					}
					jQuery(this.editor.editorContainer).height(h);
					jQuery(this.editor.iframeElement).height(h - (this.editor.editorContainer.getElementsByClassName('tox-toolbar')[0].clientHeight +
							this.editor.editorContainer.getElementsByClassName('tox-statusbar')[0].clientHeight));
				}
				else // No TinyMCE
				{
					this.htmlNode.height(this.htmlNode.height() + _height);
				}
			}
		}
	}
});}).call(this);
et2_register_widget(et2_htmlarea, ["htmlarea"]);

// Static class stuff
jQuery.extend(et2_htmlarea, {
	/**
	 * Array of toolbars
	 * @constant
	 */
	TOOLBAR_LIST: ['undo', 'redo', 'formatselect', 'fontselect', 'fontsizeselect',
		'bold', 'italic', 'strikethrough', 'forecolor', 'backcolor', 'link',
		'alignleft', 'aligncenter', 'alignright', 'alignjustify', 'numlist',
		'bullist', 'outdent', 'indent', 'ltr', 'rtl', 'removeformat', 'code', 'image', 'searchreplace'
	],
	/**
	 * arranged toolbars as simple mode
	 * @constant
	 */
	TOOLBAR_SIMPLE: "fontselect fontsizeselect | bold italic forecolor backcolor | "+
					"alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent | link image",
	/**
	 * arranged toolbars as extended mode
	 * @constant
	 */
	TOOLBAR_EXTENDED: "fontselect fontsizeselect | bold italic strikethrough forecolor backcolor | "+
					"link | alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent  | removeformat | image",
	/**
	 * arranged toolbars as advanced mode
	 * @constant
	 */
	TOOLBAR_ADVANCED: "undo redo| formatselect | fontselect fontsizeselect | bold italic strikethrough forecolor backcolor | "+
					"link | alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent ltr rtl | removeformat code| image | searchreplace",
	/**
	 * font size formats
	 * @constant
	 */
	FONT_SIZE_FORMATS: {
		pt: "8pt 10pt 12pt 14pt 18pt 24pt 36pt 48pt 72pt",
		px:"8px 10px 12px 14px 18px 24px 36px 48px 72px"
	},

	/**
	 * language code represention for TinyMCE lang code
	 */
	LANGUAGE_CODE: {
		bg: "bg_BG", ca: "ca",	cs: "cs", da: "da", de: "de",	en:"en_CA",
		el:"el", "es-es":"es",	et: "et", eu: "eu" , fa: "fa_IR", fi: "fi",
		fr: "fr_FR", hi:"",	hr:"hr", hu:"hu_HU", id: "id", it: "it", iw: "",
		ja: "ja", ko: "ko_KR", lo: "", lt: "lt", lv: "lv",	nl: "nl", no: "nb_NO",
		pl: "pl", pt: "pt_PT", "pt-br": "pt_BR", ru: "ru", sk: "sk", sl: "sl_SI",
		sv: "sv_SE", th: "th_TH", tr: "tr_TR", uk: "en_GB", vi: "vi_VN", zh: "zh_CN",
		"zh-tw": "zh_TW"
	}
});