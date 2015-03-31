/**
 * EGroupware eTemplate2 - JS widget for HTML editing
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
 */

"use strict";

/*egw:uses
	jsapi.jsapi; // Needed for egw_seperateJavaScript
	jquery.jquery;
	/phpgwapi/js/ckeditor/ckeditor.js;
	/phpgwapi/js/ckeditor/config.js;
	/phpgwapi/js/ckeditor/adapters/jquery.js;
	et2_core_baseWidget;
*/

/**
 * @augments et2_inputWidget
 */
var et2_htmlarea = et2_inputWidget.extend([et2_IResizeable],
{
	modes: ['ascii','simple','extended','advanced'],

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
		'expand_toolbar': {
			'name': 'Expand Toolbar',
			'default': true,
			'type':'boolean',
			'description': 'Have the toolbar expanded (visible)'
		},
		'base_href': {
			'name': 'Image base path',
			'default': et2_no_init,
			'type': 'string',
			'description': 'activates the browser for images at the path (relative to the docroot)'
		},
		'config': {
			// internal default configuration
			'name': 'Internal configuration',
			'type':'any',
			'default': et2_no_init,
			'description': 'Internal configuration - managed by preferences & framework, passed in here',
			'translate': 'no_lang'
		},
		value: {
			name: "Value",
			description: "The value of the widget",
			type: "html",	// "string" would remove html tags by running html_entity_decode
			default: et2_no_init
		},
		imageDataUrl: {
			name: "imageDataUrl",
			description: "Allow images dragged in as data-url, default false = handle them as fileupload",
			type: "boolean",
			default: false
		}
	},

	legacyOptions: ['mode','height','width','expand_toolbar','base_href'],

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_htmlarea
	 */
	init: function(_parent, _attrs) {
		// _super.apply is responsible for the actual setting of the params (some magic)
		this._super.apply(this, arguments);

		// CK instance
		this.ckeditor = null;

		// Allow no child widgets
		this.supportedWidgetClasses = [];
		this.htmlNode = $j(document.createElement("textarea"))
			.css('height', this.options.height)
			.addClass('et2_textbox_ro');
		this.setDOMNode(this.htmlNode[0]);
	},

	transformAttributes: function(_attrs) {

		// Check mode, some apps jammed everything in there
		if(_attrs['mode'] && jQuery.inArray(_attrs['mode'], this.modes) < 0)
		{
			this.egw().debug("warn", "'%s' is an invalid mode for htmlarea '%s'. Valid options:", _attrs['mode'],_attrs['id'], this.modes);
			var list = _attrs['mode'].split(',');
			for(var i = 0; i < list.length && i < this.legacyOptions.length; i++)
			{
				_attrs[this.legacyOptions[i]] = list[i];
			}
		}
		this._super.apply(this, arguments);
	},

	doLoadingFinished: function() {
		this._super.apply(this, arguments);
		if(this.mode == 'ascii' || this.ckeditor != null) return;

		var self = this;
		try
		{
			this.ckeditor = CKEDITOR.replace(this.dom_id,jQuery.extend({},this.options.config,this.options));
			this.ckeditor.setData(self.value);
			delete self.value;
		}
		catch (e)
		{
			if(CKEDITOR.instances[this.dom_id])
			{
				CKEDITOR.instances[this.dom_id].destroy();
			}
			if(this.htmlNode.ckeditor)
			{
				this.ckeditor = CKEDITOR.replace(this.dom_id,this.options.config);
				this.ckeditor.setData(self.value);
				delete self.value;
			}
		}

		if(this.ckeditor && this.options.config.preference_style)
		{
			var editor = this.ckeditor;
			this.ckeditor.on('instanceReady', function(e) {

				// Add in user font preferences
				if (self.options.config.preference_style && !e.editor.getData())
				{
					e.editor.document.getBody().setHtml(self.options.config.preference_style);
					delete self.options.config.preference_style;
				}
			});

			// Drag & drop of images inline won't work, because of database
			// field sizes.  For some reason FF ignored just changing the cursor
			// when dragging, so we replace dropped images with error icon.
			var replaceImgText = function(html) {
				var ret = html.replace( /<img[^>]*src="(data:.*;base64,.*?)"[^>]*>/gi, function( img, src ){
					return '';
				});
				return ret;
			};

			var chkImg = function(e) {
				// don't execute code if the editor is readOnly
				if (editor.readOnly)
					return;

				// allow data-URL, returning false to stop regular upload
				if (self.options.imageDataUrl)
				{
					return false;
				}
				// Remove the image from the text
				setTimeout( function() {
					editor.document.$.body.innerHTML = replaceImgText(editor.document.$.body.innerHTML);
				},200);

				// Try to pass the image into the first et2_file that will accept it
				if(e.data.$.dataTransfer)
				{
					self.getRoot().iterateOver(function(widget) {
						if(widget.options.drop_target)
						{
							widget.set_value(e.data.$.dataTransfer.files,e.data.$);
							return;
						}
					},e.data.$,et2_file);
				}
			};

			editor.on( 'contentDom', function() {
				// For Firefox
				editor.document.on('drop', chkImg);
				// For IE
				editor.document.getBody().on('drop', chkImg);
			});
		}

	},

	destroy: function() {
		try
		{
			//this.htmlNode.ckeditorGet().destroy(true);
			if (this.ckeditor) this.ckeditor.destroy(true);
			this.ckeditor = null;
		}
		catch (e)
		{
			this.egw().debug("warn","Removing CKEDITOR: " + e.message, this,e);
			// Finish it
			delete CKEDITOR.instances[this.dom_id];
		}
		this.htmlNode.remove();
		this.htmlNode = null;
		this._super.apply(this, arguments);
	},
	set_value: function(_value) {
		this._oldValue = _value;

		try {
			//this.htmlNode.ckeditorGet().setData(_value);
			var ckeditor = CKEDITOR.instances[this.dom_id];
			if (ckeditor)
			{
				ckeditor.setData(_value);
			}
			else
			{
				this.htmlNode.val(_value);
				this.value = _value;
			}
		} catch (e) {
			// CK editor not ready - callback will do it
			this.value = _value;
		}
	},

	getValue: function() {
		try
		{
			//return this.htmlNode.ckeditorGet().getData();
			var ckeditor = CKEDITOR.instances[this.dom_id];
			return ckeditor ? ckeditor.getData() : this.htmlNode.val();
		}
		catch (e)
		{
			// CK Error
			this.egw().debug("error",e);
			return null;
		}
	},

	/**
	 * Resize htmlNode tag according to window size
	 * @param {type} _height excess height which comes from window resize
	 */
	resize: function (_height)
	{
		if (_height)
		{
			// apply the ratio
			_height = (this.options.resize_ratio != '')? _height * this.options.resize_ratio: _height;
			if (_height != 0) this.htmlNode.height(this.htmlNode.height() + _height);
		}
	}
});
et2_register_widget(et2_htmlarea, ["htmlarea"]);

