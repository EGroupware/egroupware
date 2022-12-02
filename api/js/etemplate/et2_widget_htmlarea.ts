/**
 * EGroupware eTemplate2 - JS widget for HTML editing
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh <hn@egroupware.org>
 * @copyright Hadi Nategh <hn@egroupware.org>
 */

/*egw:uses
	jsapi.jsapi; // Needed for egw_seperateJavaScript
	/vendor/tinymce/tinymce/tinymce.min.js;
	et2_core_editableWidget;
*/

import {et2_editableWidget} from "./et2_core_editableWidget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {WidgetConfig, et2_register_widget, et2_createWidget} from "./et2_core_widget";
import {et2_IResizeable} from "./et2_core_interfaces";
import {et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";
import {et2_vfsSelect} from "./et2_widget_vfs";
import "../../../vendor/tinymce/tinymce/tinymce.min.js";
import {etemplate2} from "./etemplate2";

/**
 * @augments et2_inputWidget
 */
export class et2_htmlarea extends et2_editableWidget implements et2_IResizeable
{
	static readonly _attributes : any = {
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
	public static readonly TOOLBAR_LIST : string[] = ['undo', 'redo', 'formatselect', 'fontselect', 'fontsizeselect',
		'bold', 'italic', 'strikethrough', 'forecolor', 'backcolor', 'link',
		'alignleft', 'aligncenter', 'alignright', 'alignjustify', 'numlist',
		'bullist', 'outdent', 'indent', 'ltr', 'rtl', 'removeformat', 'code', 'image', 'searchreplace', 'fullscreen', 'table'
	];

	/**
	 * arranged toolbars as simple mode
	 * @constant
	 */
	public static readonly TOOLBAR_SIMPLE : string = "undo redo|formatselect fontselect fontsizeselect | bold italic underline removeformat forecolor backcolor | "+
	"alignleft aligncenter alignright alignjustify | bullist "+
	"numlist outdent indent| link image pastetext | table";

	/**
	 * arranged toolbars as extended mode
	 * @constant
	 */
	public static readonly TOOLBAR_EXTENDED : string = "fontselect fontsizeselect | bold italic underline strikethrough forecolor backcolor | "+
	"link | alignleft aligncenter alignright alignjustify  | numlist "+
	"bullist outdent indent | removeformat | image | fullscreen | table";

	/**
	 * arranged toolbars as advanced mode
	 * @constant
	 */
	public static readonly TOOLBAR_ADVANCED : string = "undo redo| formatselect | fontselect fontsizeselect | bold italic underline strikethrough forecolor backcolor | "+
	"alignleft aligncenter alignright alignjustify | bullist "+
	"numlist outdent indent ltr rtl | removeformat code| link image pastetext | searchreplace | fullscreen | table";

	/**
	 * font size formats
	 * @constant
	 */
	public static readonly FONT_SIZE_FORMATS : {pt : string, px : string} = {
		pt: "8pt 9pt 10pt 11pt 12pt 14pt 16pt 18pt 20pt 22pt 24pt 26pt 28pt 36pt 48pt 72pt",
		px: "8px 9px 10px 11px 12px 14px 16px 18px 20px 22px 24px 26px 28px 36px 48px 72px"
	};

	/**
	 * language code represention for TinyMCE lang code
	 */
	public static readonly LANGUAGE_CODE : {} =  {
		bg: "bg_BG", ca: "ca",	cs: "cs", da: "da", de: "de",	en:"en_CA",
		el:"el", "es-es":"es",	et: "et", eu: "eu" , fa: "fa_IR", fi: "fi",
		fr: "fr_FR", hi:"",	hr:"hr", hu:"hu_HU", id: "id", it: "it", iw: "",
		ja: "ja", ko: "ko_KR", lo: "", lt: "lt", lv: "lv",	nl: "nl", no: "nb_NO",
		pl: "pl", pt: "pt_PT", "pt-br": "pt_BR", ru: "ru", sk: "sk", sl: "sl_SI",
		sv: "sv_SE", th: "th_TH", tr: "tr_TR", uk: "en_GB", vi: "vi_VN", zh: "zh_CN",
		"zh-tw": "zh_TW"
	};

	editor : any = null;
	supportedWidgetClasses : any;
	htmlNode : JQuery = null;
	mode : string;
	toolbar: string;
	tinymce : any;
	tinymce_container : HTMLElement;
	file_picker_callback : Function;
	menubar : boolean;
	protected value : string;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_htmlarea._attributes, _child || {}));
		this.editor = null; // TinyMce editor instance
		this.supportedWidgetClasses = []; // Allow no child widgets
		this.htmlNode = jQuery(document.createElement(this.options.readonly ? "div" : "textarea"))
			.addClass('et2_textbox_ro');
		if(this.options.height)
		{
			this.htmlNode.css('height', this.options.height);
		}
		this.setDOMNode(this.htmlNode[0]);
	}

	/**
	 *
	 * @returns {undefined}
	 */
	doLoadingFinished()
	{
		super.doLoadingFinished();
		this.init_editor();
		return true;
	}

	init_editor() {
		if(this.mode == 'ascii' || this.editor != null || this.options.readonly) return;
		let imageUpload;
		let self = this;
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
		let settings : any = {
			base_url: egw.webserverUrl + '/vendor/tinymce/tinymce',
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
				// setting p (and below also the preferred formatblock) to the users font and -size preference
				p: { block: 'p', styles: {
					"font-family": (egw.preference('rte_font', 'common') || 'arial, helvetica, sans-serif'),
					"font-size": (<string>egw.preference('rte_font_size', 'common') || '10')+
						(<string>egw.preference('rte_font_unit', 'common') || 'pt')
				}},
				customparagraph: { block: 'p', styles: {"margin-block-start": "0px", "margin-block-end": "0px"}}
			},
			min_height: 100,
			convert_urls: false,
			language: et2_htmlarea.LANGUAGE_CODE[<string><unknown>egw.preference('lang', 'common')],
			language_url: egw.webserverUrl+'/api/js/tinymce/langs/'+et2_htmlarea.LANGUAGE_CODE[<string><unknown>egw.preference('lang', 'common')]+'.js',
			paste_data_images: true,
			paste_filter_drop: true,
			browser_spellcheck: true,
			contextmenu: false,
			images_upload_url: imageUpload,
			file_picker_callback: jQuery.proxy(this._file_picker_callback, this),
			images_upload_handler: this.options.images_upload_handler,
			init_instance_callback : jQuery.proxy(this._instanceIsReady, this),
			auto_focus: false,
			valid_children : this.options.valid_children,
			plugins: [
				"print searchreplace autolink directionality ",
				"visualblocks visualchars image link media template fullscreen",
				"codesample table charmap hr pagebreak nonbreaking anchor toc ",
				"insertdatetime advlist lists textcolor wordcount imagetools ",
				"colorpicker textpattern help paste code searchreplace tabfocus"
			],
			toolbar: et2_htmlarea.TOOLBAR_SIMPLE,
			block_formats: "Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;"+
				"Heading 4=h4;Heading 5=h5;Heading 6=h6;Preformatted=pre",
			font_formats: "Andale Mono=andale mono,times;Arial=arial,helvetica,"+
				"sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book "+
				"antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;"+
				"Courier New=courier new,courier;Georgia=georgia,palatino;"+
				"Helvetica=helvetica;Impact=impact,chicago;Segoe=segoe,segoe ui;Symbol=symbol;"+
				"Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,"+
				"monaco;Times New Roman=times new roman,times;Trebuchet "+
				"MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;"+
				"Wingdings=wingdings,zapf dingbats",
			fontsize_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
			content_css: egw.webserverUrl+'/api/tinymce.php?'+	// use the 3 prefs as cache-buster
				btoa(egw.preference('rte_font', 'common')+'::'+
					egw.preference('rte_font_size', 'common')+'::'+
					egw.preference('rte_font_unit', 'common')),
		};
		let rte_formatblock = <string>(egw.preference('rte_formatblock', 'common') || 'p');
		if (rte_formatblock === 'customparagraph')
		{
			settings.forced_root_block = false;
			settings.force_br_newlines = true;
			settings.force_p_newlines = false;
			rte_formatblock = 'p';
		}
		else if (rte_formatblock !== 'p')
		{
			settings.formats[rte_formatblock] = jQuery.extend(true, {}, settings.formats.p);
			settings.formats[rte_formatblock].block = rte_formatblock;
		}
		// extend default settings with configured options and preferences
		jQuery.extend(settings, this._extendedSettings());
		this.tinymce = tinymce.init(settings);
		// make sure value gets set in case of widget gets loaded by delay like
		// inside an inactive tabs
		this.tinymce.then(function() {
			self.set_value(self.htmlNode.val());
			self.resetDirty();
			if (self.editor && self.editor.editorContainer)
			{
				const activeElement = document.activeElement;
				self.editor.formatter.toggle(rte_formatblock);
				jQuery(self.editor.editorContainer).height(self.options.height);
				jQuery(self.editor.iframeElement.contentWindow.document).on('dragenter', function(){
					if (jQuery('#dragover-tinymce').length < 1) jQuery("<style id='dragover-tinymce'>.dragover:after {height:calc(100% - "+jQuery(this).height()+"px) !important;}</style>").appendTo('head');
				});
				// give focus back
				activeElement && activeElement.focus && activeElement.focus();
			}
		});
	}

	/**
	 * set disabled
	 *
	 * @param {type} _value
	 * @returns {undefined}
	 */
	set_disabled(_value)
	{
		super.set_disabled(_value);
		if (_value)
		{
			jQuery(this.tinymce_container).css('display', 'none');
		}
		else
		{
			jQuery(this.tinymce_container).css('display', 'flex');
		}
	}

	set_readonly(_value)
	{
		if(this.options.readonly === _value) return;
		let value = this.get_value();
		this.options.readonly = _value;
		if(this.options.readonly)
		{
			if (this. editor) this.editor.remove();
			this.htmlNode = jQuery(document.createElement(this.options.readonly ? "div" : "textarea"))
				.addClass('et2_textbox_ro');
			if(this.options.height)
			{
				this.htmlNode.css('height', this.options.height)
			}
			this.editor = null;
			this.setDOMNode(this.htmlNode[0]);
			this.set_value(value);
		}
		else
		{
			if(!this.editor)
			{
				this.htmlNode = jQuery(document.createElement("textarea"))
					.val(value);
				if(this.options.height || this.options.editable_height)
				{
					this.htmlNode.css('height', (this.options.editable_height ? this.options.editable_height : this.options.height));
				}
				this.setDOMNode(this.htmlNode[0]);
				this.init_editor();
			}
		}
	}

	/**
	 * Callback function runs when the filepicker in image dialog is clicked
	 *
	 * @param {type} _callback
	 * @param {type} _value
	 * @param {type} _meta
	 */
	private _file_picker_callback(_callback : Function, _value, _meta)
	{
		if (typeof this.file_picker_callback == 'function') return this.file_picker_callback.call(arguments, this);
		let callback = _callback;

		// Don't rely only on app_name to fetch et2 object as app_name may not
		// always represent current app of the window, e.g.: mail admin account.
		// Try to fetch et2 from its template name.
		let etemplate = jQuery('form').data('etemplate');
		let et2;
		if (etemplate && etemplate.name && !app[egw(window).app_name()])
		{
			et2 = etemplate2.getByTemplate(etemplate.name)[0]['widgetContainer'];
		}
		else
		{
			et2 = app[egw(window).app_name()].et2;
		}

		let vfsSelect = <et2_vfsSelect>et2_createWidget('vfs-select', {
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
	}

	/**
	 * Callback when instance is ready
	 *
	 * @param {type} _editor
	 */
	private _instanceIsReady(_editor)
	{
		console.log("Editor: " + _editor.id + " is now initialized.");
		// try to reserve focus state as running command on editor may steal the
		// current focus.
		let focusedEl = jQuery(':focus');
		this.editor = _editor;

		this.editor.on('drop', function(e){
			e.preventDefault();
		});

		if (!this.disabled) jQuery(this.editor.editorContainer).css('display', 'flex');
		this.tinymce_container = this.editor.editorContainer;
		// go back to reserved focused element
		focusedEl.focus();
	}

	/**
	 * Takes all relevant preferences into account and set settings accordingly
	 *
	 * @returns {object} returns a object including all settings
	 */
	private _extendedSettings() : object
	{
		let rte_menubar = <string>egw.preference('rte_menubar', 'common');
		let rte_toolbar = egw.preference('rte_toolbar', 'common');
		// we need to have rte_toolbar values as an array
		if (rte_toolbar && typeof rte_toolbar == "object" && this.toolbar == '')
		{
			rte_toolbar = Object.keys(rte_toolbar).map(function(key){return rte_toolbar[key]});
		}
		else if(this.toolbar != '')
		{
			rte_toolbar = this.toolbar.split(',');
		}
		let settings = {
			fontsize_formats: et2_htmlarea.FONT_SIZE_FORMATS[<string>egw.preference('rte_font_unit', 'common')],
			menubar: parseInt(rte_menubar) && this.menubar ? true : typeof rte_menubar != 'undefined' ? false : this.menubar
		};

		switch (this.mode)
		{
			case 'simple':
				settings['toolbar'] = et2_htmlarea.TOOLBAR_SIMPLE;
				break;
			case 'extended':
				settings['toolbar']= et2_htmlarea.TOOLBAR_EXTENDED;
				break;
			case 'advanced':
				settings['toolbar'] = et2_htmlarea.TOOLBAR_ADVANCED;
				break;
			default:
				this.mode = '';
		}

		// take rte_toolbar into account if no mode restrictly set from template
		if (rte_toolbar && !this.mode)
		{
			let toolbar_diff = et2_htmlarea.TOOLBAR_LIST.filter(function(i){return !((<string[]>rte_toolbar).indexOf(i) > -1);});
			settings['toolbar'] = et2_htmlarea.TOOLBAR_ADVANCED;
			toolbar_diff.forEach(function(a){
				let r = new RegExp(a);
				settings['toolbar'] = settings['toolbar'].replace(r, '');
			});
		}
		return settings;
	}

	destroy()
	{
		if (this.editor)
		{
			try
			{
				this.editor.destroy();
			}
			catch(e)
			{
				egw().debug("Error destroying editor",e);
			}
		}
		this.editor = null;
		this.tinymce = null;
		this.tinymce_container = null;
		this.htmlNode.remove();
		this.htmlNode = null;
		super.destroy();
	}
	set_value(_value)
	{
		this._oldValue = _value;
		if (this.editor)
		{
			this.editor.setContent(_value);
		}
		else
		{
			if(this.options.readonly)
			{
				this.htmlNode.empty().append(_value);
			}
			else
			{
				this.htmlNode.val(_value);
			}
		}
		this.value = _value;
	}

	getValue()
	{
		if (this.editor)
		{
			return this.editor.getContent();
		}
		return this.options.readonly ? this.value : this.htmlNode.val();
	}

	/**
	 * Apply default font and -size
	 */
	applyDefaultFont()
	{
		const edit_area = this.editor.editorContainer.querySelector('iframe').contentDocument;
		const font_family = egw.preference('rte_font', 'common') || 'arial, helvetica, sans-serif';
		edit_area.querySelectorAll('h1:not([style*="font-family"]),h2:not([style*="font-family"]),h3:not([style*="font-family"]),h4:not([style*="font-family"]),h5:not([style*="font-family"]),h6:not([style*="font-family"]),' +
			'div:not([style*="font-family"]),li:not([style*="font-family"]),p:not([style*="font-family"]),blockquote:not([style*="font-family"]),' +
			'td:not([style*="font-family"]),th:not([style*="font-family"]').forEach((elem) =>
		{
			elem.style.fontFamily = font_family;
		});
		const font_size = (<string>egw.preference('rte_font_size', 'common') || '10')+(egw.preference('rte_font_unit', 'common') || 'pt');
		edit_area.querySelectorAll('div:not([style*="font-size"]),li:not([style*="font-size"]),p:not([style*="font-size"]),blockquote:not([style*="font-size"]),' +
			'td:not([style*="font-size"]),th:not([style*="font-size"])').forEach((elem) =>
		{
			elem.style.fontSize = font_size;
		});
	}

	/**
	 * Resize htmlNode tag according to window size
	 * @param {type} _height excess height which comes from window resize
	 */
	resize(_height)
	{
		if (_height && this.options.resize_ratio !== '0')
		{
			// apply the ratio
			_height = (this.options.resize_ratio != '')? _height * this.options.resize_ratio: _height;
			if (_height != 0)
			{
				if (this.editor) // TinyMCE HTML
				{
					let h;
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
					jQuery(this.editor.iframeElement).height(h - (this.editor.editorContainer.getElementsByClassName('tox-editor-header')[0]?.clientHeight +
						this.editor.editorContainer.getElementsByClassName('tox-statusbar')[0]?.clientHeight));
				}
				else // No TinyMCE
				{
					this.htmlNode.height(this.htmlNode.height() + _height);
				}
			}
		}
	}
}
et2_register_widget(et2_htmlarea, ["htmlarea"]);