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
	font_size_formats: {
		pt: "8pt 10pt 12pt 14pt 18pt 24pt 36pt 48pt 72pt",
		px:"8px 10px 12px 14px 18px 24px 36px 48px 72px"
	},
	attributes: {
		'mode': {
			'name': 'Mode',
			'description': 'One of {ascii|simple|extended|advanced}',
			'default': 'simple',
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

		// default settings for initialization
		var settings = {
			target: this.htmlNode[0],
			body_id: this.dom + '_htmlarea',
			menubar: false,
			branding: false,
			resize: false,
			height: this.options.height,
			width: this.options.width,
			min_height: 100,
			language: egw.preference('lang', 'common'),
			paste_data_images: true,
			browser_spellcheck: true,
			images_upload_url: this.options.imageUpload,
			file_picker_callback: jQuery.proxy(this._file_picker_callback, this),
			images_upload_handler: jQuery.proxy(this._images_upload_handler, this),
			init_instance_callback : jQuery.proxy(this._instanceIsReady, this),
			plugins: [
				"print preview fullpage searchreplace autolink directionality "+
				"visualblocks visualchars fullscreen image link media template "+
				"codesample table charmap hr pagebreak nonbreaking anchor toc "+
				"insertdatetime advlist lists textcolor wordcount imagetools "+
				"colorpicker textpattern help paste"
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
	},

	/**
	 *
	 * @param {type} _callback
	 * @param {type} _value
	 * @param {type} _meta
	 * @returns {unresolved}
	 */
	_file_picker_callback: function(_callback, _value, _meta) {
		if (typeof this.file_picker_callback == 'function') return this.file_picker_callback.call(arguments, this);

	},

	/**
	 *
	 * @param {type} _blobInfo image blob info
	 * @param {type} _success success callback
	 * @param {type} _failure failure callback
	 * @returns {}
	 */
	_images_upload_handler: function(_blobInfo, _success, _failure) {
		if (typeof this.images_upload_handler == 'function') return this.images_upload_handler.call(arguments, this);
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
	},

	/**
	 *
	 * @returns {et2_widget_htmlareaet2_htmlarea.et2_widget_htmlareaAnonym$1._extendedSettings.settings}
	 */
	_extendedSettings: function () {

		var settings = {
			fontsize_formats: this.font_size_formats[egw.preference('rte_font_unit', 'common')],
		};

		var mode = this.mode || egw.preference('rte_features', 'common');
		switch (mode)
		{
			case 'simple':
				settings.toolbar = "formatselect | fontselect fontsizeselect | bold italic strikethrough forecolor backcolor | "+
					"alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent"
				break;
			case 'extended':
				settings.toolbar = "formatselect | fontselect fontsizeselect | bold italic strikethrough forecolor backcolor | "+
					"link | alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent  | removeformat | image"
				break;
			case 'advanced':
				settings.toolbar = "formatselect | fontselect fontsizeselect | bold italic strikethrough forecolor backcolor | "+
					"link | alignleft aligncenter alignright alignjustify  | numlist "+
					"bullist outdent indent  | removeformat | image"
				break;
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