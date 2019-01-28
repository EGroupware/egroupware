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

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_interfaces;
	et2_core_baseWidget;
	expose;
	/vendor/bower-asset/cropper/dist/cropper.min.js;
*/

/**
 * Class which implements the "image" XET-Tag
 *
 * @augments et2_baseWidget
 */
var et2_image = (function(){ "use strict"; return expose(et2_baseWidget.extend([et2_IDetachedDOM],
{
	attributes: {
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
		"imagemap":{
			// TODO: Do something with this
			"name": "Image map",
			"description": "Currently not implemented"
		},
		"label": {
			"name": "Label",
			"type": "string",
			"description": "Label for image"
		},
		"expose_view":{
			name: "Expose view",
			type: "boolean",
			default: false,
			description: "Clicking on an image with href value would popup an expose view, and will show image referenced by href."
		}
	},
	legacyOptions: ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_image
	 */
	init: function() {
		this._super.apply(this, arguments);

		// Create the image or a/image tag
		this.image = jQuery(document.createElement("img"));
		if (this.options.label)
		{
			this.image.attr("alt", this.options.label).attr("title", this.options.label);
		}
		if (this.options.href)
		{
			this.image.addClass('et2_clickable');
		}
		if(this.options["class"])
		{
			this.image.addClass(this.options["class"]);
		}
		this.setDOMNode(this.image[0]);
	},

	click: function()
	{
		if(this.options.href)
		{
			this.egw().open_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
		}
		else
		{
			this._super.apply(this,arguments);
		}
	},

	transformAttributes: function(_attrs) {
		this._super.apply(arguments);

		// Check to expand name
		if (typeof _attrs["src"] != "undefined")
		{
			var manager = this.getArrayMgr("content");
			if(manager) {
				var src = manager.getEntry(_attrs["src"]);
				if (typeof src != "undefined" && src !== null)
				{
					if(typeof src == "object")
					{
						src = egw().link('/index.php', src);
					}
					_attrs["src"] = src;
				}
			}
		}
	},

	set_label: function(_value) {
		this.options.label = _value;
		_value = this.egw().lang(_value);
		// label is NOT the alt attribute in eTemplate, but the title/tooltip
		this.image.attr("alt", _value).attr("title", _value);
	},

	setValue: function(_value) {
		// Value is src, images don't get IDs
		this.set_src(_value);
	},

	set_href: function (_value)
	{
		if (!this.isInTree())
		{
			return false;
		}

		this.options.href = _value;
		this.image.wrapAll('<a href="'+_value+'"></a>"');

		var href = this.options.href;
		var popup = this.options.extra_link_popup;
		var target = this.options.extra_link_target;
		var self = this;
		this.image.click(function(e)
		{
			if (self.options.expose_view)
			{
				self._init_blueimp_gallery(e,_value);
				e.stopImmediatePropagation();
			}
			else
			{
				egw.open_link(href,target,popup);
			}

			e.preventDefault();
			return false;
		});

		return true;
	},

	/**
	 * Set image src
	 *
	 * @param {string} _value image, app/image or url
	 * @return {boolean} true if image was found, false if not (image is either not displayed or default_src is used)
	 */
	set_src: function(_value) {
		if(!this.isInTree())
		{
			return false;
		}

		this.options.src = _value;

		var src = this.egw().image(_value);
		if (src)
		{
			this.image.attr("src", src).show();
			return true;
		}
		// allow url's too
		else if (_value[0] == '/' || _value.substr(0,4) == 'http')
		{
			this.image.attr('src', _value).show();
			return true;
		}
		src = null;
		if (this.options.default_src)
		{
			src = this.egw().image(this.options.default_src);
		}
		if (src)
		{
			this.image.attr("src", src).show();
		}
		else
		{
			this.image.css("display","none");
		}
		return false;
	},

	/**
	 * Function to get media content to feed the expose
	 * @param {type} _value
	 * @returns {Array|Array.getMedia.mediaContent}
	 */
	getMedia: function (_value)
	{
		var base_url = egw.webserverUrl.match(/^\//,'ig')?egw(window).window.location.origin + egw.webserverUrl + '/':egw.webserverUrl + '/';
		var mediaContent = [];
		if (_value)
		{
			mediaContent = [{
				title: this.options.label,
				href: base_url + _value,
				type: this.options.type + "/*",
				thumbnail: base_url + _value
			}];
		}
		return mediaContent;
	},

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("src", "label", "href");
	},

	getDetachedNodes: function() {
		return [this.image[0]];
	},

	setDetachedAttributes: function(_nodes, _values) {
		// Set the given DOM-Nodes
		this.image = jQuery(_nodes[0]);

		// Set the attributes
		if (_values["src"])
		{
			this.set_src(_values["src"]);
		}

		if (_values["label"])
		{
			this.set_label(_values["label"]);
		}
		if(_values["href"])
		{
			this.image.addClass('et2_clickable');
			this.set_href(_values["href"]);
		}
	}
}));}).call(this);

et2_register_widget(et2_image, ["image"]);

/**
 * Widget displaying an application icon
 */
var et2_appicon = (function(){ "use strict"; return et2_image.extend(
{
	attributes: {
		default_src: {
			name: "Default image",
			type: "string",
			default: "nonav",
			description: "Image to use if there is no application icon"
		}
	},

	set_src: function(_app)
	{
		if (!_app) _app = this.egw().app_name();
		this.image.addClass('et2_appicon');
		this._super.call(this, _app == 'sitemgr-link' ? 'sitemgr/sitemgr-link' :	// got removed from jdots
			(this.egw().app(_app, 'icon_app') || _app)+'/'+(this.egw().app(_app, 'icon') || 'navbar'));
	}
});}).call(this);
et2_register_widget(et2_appicon, ["appicon"]);

/**
 * Avatar widget to display user profile picture or
 * user letter avatar based on user's firstname lastname.
 *
 * @augments et2_baseWidget
 */

var et2_avatar = (function(){ "use strict"; return et2_image.extend(
{
	attributes: {
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
	},

	init: function ()
	{
		this._super.apply(this,arguments);
		if (this.options.frame == 'circle')
		{
			this.image.attr('style', 'border-radius:50%');
		}
		if (this.options.contact_id) this.setValue(this.options.contact_id);
	},

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
	set_contact_id: function(_contact_id)
	{
		var params = {
			menuaction: 'addressbook.addressbook_ui.photo'
		};
		var id = 'contact_id';

		if (!_contact_id)
		{
			_contact_id = this.egw().user('account_id');
		}
		else if(_contact_id.match(/account\:/))
		{
			id = 'account_id';
			_contact_id = _contact_id.replace('account:','');
		}
		else
		{
			id = 'contact_id';
			_contact_id = _contact_id.replace('contact:', '');
		}

		params[id] = _contact_id;
		this.image.addClass('et2_avatar');

		var url = egw.link('/index.php',params);
		this.set_src(url);
	},

	/**
	 * Function to set value
	 * @param {string} _value
	 */
	setValue: function(_value)
	{
		this.set_contact_id(_value);
	},

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("contact_id", "label", "href");
	},

	setDetachedAttributes: function (_nodes, _values)
	{
		// Set the given DOM-Nodes
		this.image = jQuery(_nodes[0]);

		if (_values["contact_id"])
		{
			this.set_contact_id(_values["contact_id"]);
		}

		if (_values["label"])
		{
			this.set_label(_values["label"]);
		}
		if(_values["href"])
		{
			this.image.addClass('et2_clickable');
			this.set_href(_values["href"]);
		}
	},

	/**
	 * Build Editable Mask Layer (EML) in order to show edit/delete actions
	 * on top of profile picture.
	 * @param {boolean} _noDelete disable delete button in initialization
	 */
	_buildEditableLayer: function (_noDelete)
	{
		var self = this;
		// editable mask layer (eml)
		var eml = jQuery(document.createElement('div'))
				.addClass('eml')
				.insertAfter(this.image);

		// edit button
		var edit = jQuery(document.createElement('div'))
				.addClass('emlEdit')
				.click(function(){
					var buttons = [
						{"button_id": 1,"text": self.egw().lang('save'), id: 'save', image: 'check', "default":true},
						{"button_id": 0,"text": self.egw().lang('cancel'), id: 'cancel', image: 'cancelled'}
					];
					var dialog = function(_title, _value, _buttons, _egw_or_appname)
					{
						return et2_createWidget("dialog",
						{
							callback: function(_buttons, _value)
							{
								if (_buttons == 'save')
								{
									var canvas = jQuery('#_cropper_image').cropper('getCroppedCanvas');
									self.image.attr('src', canvas.toDataURL("image/jpeg", 1.0));
									self.egw().json('addressbook.addressbook_ui.ajax_update_photo',
										[self.getInstanceManager().etemplate_exec_id, canvas.toDataURL('image/jpeg',1.0)],
										function(res)
										{
											if (res)
											{
												del.show();
											}
										}).sendRequest();
								}
							},
							title: _title||egw.lang('Input required'),
							buttons: _buttons||et2_dialog.BUTTONS_OK_CANCEL,
							value: {
								content: _value
							},
							width: "90%",
							height:"90%",
							resizable: false,
							position:"top+10",
							template: egw.webserverUrl+'/api/templates/default/avatar_edit.xet?2'
						}, et2_dialog._create_parent(_egw_or_appname));
					};

					dialog(egw.lang('Edit avatar'),{photo:self.options.contact_id},buttons);
				})
				.appendTo(eml);

		// delete button
		var del = jQuery(document.createElement('div'))
				.addClass('emlDelete')
				.click(function(){
					et2_dialog.show_dialog(function(_btn){
						if (_btn == et2_dialog.YES_BUTTON)
						{
							self.egw().json('addressbook.addressbook_ui.ajax_update_photo',
								[self.getInstanceManager().etemplate_exec_id, null],
								function(res)
								{
									if (res)
									{
										self.image.attr('src','');
										del.hide();
									egw.refresh('Avatar Deleted.', egw.app_name());
									}
								}).sendRequest();
						}
					}, egw.lang('Delete this photo?'), egw.lang('Delete'), null, et2_dialog.BUTTONS_YES_NO);
				})
				.appendTo(eml);
		if (_noDelete) del.hide();
		// invisible the mask
		eml.css('opacity','0');

		eml.parent().css('position', "relative");

		// bind handler for activating actions on editable mask
		eml.on({
			mouseover:function(){eml.css('opacity','0.9');},
			mouseout: function (){eml.css('opacity','0');}
		});
	},

	/**
	 * We need to build the Editable Mask Layer after widget gets loaded
	 */
	doLoadingFinished: function ()
	{
		this._super.apply(this,arguments);
		var self = this;
		if (this.options.contact_id && this.options.editable)
		{
			egw(window).json(
				'addressbook.addressbook_ui.ajax_noPhotoExists',
				[this.options.contact_id],
				function(noPhotoExists)
				{
					if (noPhotoExists) self.image.attr('src','');
					self._buildEditableLayer(noPhotoExists);
				}
			).sendRequest(true);
		}
		if (this.options.crop)
		{
			var cropped = jQuery(this.image).cropper({
				aspectRatio: 1/1,
				crop: function (e){
					console.log (e);
				}
			});

		}
	}

});}).call(this);
et2_register_widget(et2_avatar, ["avatar"]);

/**
 * Avatar readonly widget to only display user profile picture or
 * user letter avatar based on user's firstname lastname.
 *
 * @augments et2_baseWidget
 */
var et2_avatar_ro = (function(){ "use strict"; return et2_avatar.extend(
{
	init: function ()
	{
		this._super.apply(this,arguments);
		this.options.editable = false;
	}

});}).call(this);
et2_register_widget(et2_avatar_ro, ["avatar_ro"]);

jQuery.extend(et2_avatar,
{
	/**
	 * Function runs after uplaod in avatar dialog is finished and it tries to
	 * update image and cropper container.
	 *
	 * @param {type} e
	 * @returns {undefined}
	 */
	uploadAvatar_onFinish: function (e){
		var file = e.data.resumable.files[0].file;
		var reader = new FileReader();
		reader.onload = function (e)
		{
			jQuery('#_cropper_image').attr('src', e.target.result);
			jQuery('#_cropper_image').cropper('replace',e.target.result);
		};
		reader.readAsDataURL(file);
	}
});