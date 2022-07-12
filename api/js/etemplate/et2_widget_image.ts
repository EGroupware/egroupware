/**
 * EGroupware eTemplate2 - JS Description object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2011
 */

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	et2_core_interfaces;
	et2_core_baseWidget;
	expose;
	/vendor/bower-asset/cropper/dist/cropper.min.js;
*/

import {et2_baseWidget} from './et2_core_baseWidget';
import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_IDetachedDOM} from "./et2_core_interfaces";
import {et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";
import '../../../vendor/bower-asset/cropper/dist/cropper.min.js';
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";

/**
 * Class which implements the "image" XET-Tag
 *
 * @deprecated use Et2Image
 * @ToDo remove when all extending classes are ported to web-components
 */
export class et2_image extends et2_baseWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
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
	};

	public static readonly legacyOptions : string[] = ["href", "extra_link_target", "imagemap", "extra_link_popup", "id"];

	image : JQuery = null;

	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_image._attributes, _child || {}));

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
	}

	click(_ev : any)
	{
		if(this.options.href)
		{
			this.egw().open_link(this.options.href, this.options.extra_link_target, this.options.extra_link_popup);
		}
		else
		{
			super.click(_ev);
		}
	}

	transformAttributes(_attrs : any)
	{
		super.transformAttributes(_attrs);

		// Check to expand name
		if (typeof _attrs["src"] != "undefined")
		{
			let manager = this.getArrayMgr("content");
			if(manager && _attrs["src"]) {
				let src = manager.getEntry(_attrs["src"], false, true);
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
	}

	set_label(_value : string)
	{
		this.options.label = _value;
		_value = this.egw().lang(_value);
		// label is NOT the alt attribute in eTemplate, but the title/tooltip
		this.image.attr("alt", _value).attr("title", _value);
	}

	setValue(_value)
	{
		// Value is src, images don't get IDs
		this.set_src(_value);
	}

	set_href(_value) : boolean
	{
		if (!this.isInTree())
		{
			return false;
		}

		this.options.href = _value;
		this.image.wrapAll('<a href="'+_value+'"></a>"');

		let href = this.options.href;
		let popup = this.options.extra_link_popup;
		let target = this.options.extra_link_target;
		let self = this;
		this.image.click(function(e)
		{
			if (self.options.expose_view)
			{
				/*
				TODO: Fix after implementing EXPOSE mixin class
				 */
				//self._init_blueimp_gallery(e,_value);
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
	}

	/**
	 * Set image src
	 *
	 * @param {string} _value image, app/image or url
	 * @return {boolean} true if image was found, false if not (image is either not displayed or default_src is used)
	 */
	set_src(_value : string) : boolean
	{
		if(!this.isInTree())
		{
			return false;
		}

		this.options.src = _value;

		// allow url's too
		if (_value[0] == '/' || _value.substr(0,4) == 'http' || _value.substr(0,5) == 'data:')
		{
			this.image.attr('src', _value).show();
			return true;
		}
		let src = this.egw().image(_value);
		if (src)
		{
			this.image.attr("src", src).show();
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
	}

	/**
	 * Function to get media content to feed the expose
	 * @param {type} _value
	 */
	getMedia(_value) : object[]
	{
		let base_url = egw.webserverUrl.match(/^\/ig/)?egw(window).window.location.origin + egw.webserverUrl + '/':egw.webserverUrl + '/';
		let mediaContent = [];
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
	}

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("src", "label", "href");
	}

	getDetachedNodes()
	{
		return [this.image[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		// Set the given DOM-Nodes
		this.image = jQuery(_nodes[0]);

		// Set the attributes
		if (_values["src"])
		{
			this.set_src(_values["src"]);
		}
		// Not valid, but we'll deal
		if (_values["value"])
		{
			this.setValue(_values["value"]);
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
}

/**
* Widget displaying an application icon
*/
export class et2_appicon extends et2_image
{
	static readonly _attributes: any = {
		default_src: {
			name: "Default image",
			type: "string",
			default: "nonav",
			description: "Image to use if there is no application icon"
		}
	};

	set_src(_app) : boolean
	{
		if (!_app) _app = this.egw().app_name();
		this.image.addClass('et2_appicon');
		return super.set_src(_app == 'sitemgr-link' ? 'sitemgr/sitemgr-link' :	// got removed from jdots
			(this.egw().app(_app, 'icon_app') || _app)+'/'+(this.egw().app(_app, 'icon') || 'navbar'));
	}
}
et2_register_widget(et2_appicon, ["appicon"]);

/**
* Avatar widget to display user profile picture or
* user letter avatar based on user's firstname lastname.
*
* @augments et2_baseWidget
*/

export class et2_avatar extends et2_image
{
	static readonly _attributes : any = {
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
	static LAVATAR_BG_COLORS : string[] = [
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
	static LAVATAR_TEXT_COLOR: string = '#ffffff';

	static LAVATAR_SIZE: number = 128;

	/**
	 * Generate letter avatar with given data
	 * @param {type} _fname
	 * @param {type} _lname
	 * @param {type} _id
	 * @returns {string} return data url
	 */
	static lavatar(_fname, _lname, _id)
	{
		let str = _fname + _lname + _id;
		let getBgColor = function(_str)
		{
			let hash = 0;
			for (let i=0; i< _str.length; i++)
			{
				hash = _str[i].charCodeAt(0) + hash;
			}
			return et2_avatar.LAVATAR_BG_COLORS[hash % et2_avatar.LAVATAR_BG_COLORS.length];
		};
		let bg = getBgColor(str);
		let size = et2_avatar.LAVATAR_SIZE * (window.devicePixelRatio ? window.devicePixelRatio : 1);
		let text = (_fname ? _fname[0].toUpperCase() : "")+(_lname ? _lname[0].toUpperCase() : "");
		let canvas = document.createElement('canvas');
		canvas.width = size;
		canvas.height = size;
		let context = canvas.getContext("2d");
		context.fillStyle = bg;
		context.fillRect (0, 0, canvas.width, canvas.height);
		context.font = Math.round(canvas.width/2)+"px Arial";
		context.textAlign = "center";
		context.fillStyle = et2_avatar.LAVATAR_TEXT_COLOR;
		context.fillText(text, size / 2, size / 1.5);
		let dataURL = canvas.toDataURL();
		canvas.remove();
		return dataURL;
	}

	/**
	 * Function runs after uplaod in avatar dialog is finished and it tries to
	 * update image and cropper container.
	 * @param {type} e
	 */
	static uploadAvatar_onFinish(e)
	{
		let file = e.data.resumable.files[0].file;
		let reader = new FileReader();
		reader.onload = function (e)
		{
			jQuery('#_cropper_image').attr('src', e.target.result);
			jQuery('#_cropper_image').cropper('replace',e.target.result);
		};
		reader.readAsDataURL(file);
	}

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_avatar._attributes, _child || {}));
		if (this.options.frame == 'circle')
		{
			this.image.attr('style', 'border-radius:50%');
		}
		if (this.options.contact_id) this.setValue(this.options.contact_id);
	}

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
	set_contact_id(_contact_id : string) : void
	{
		let params = {};
		let id = 'contact_id';

		this.image.addClass('et2_avatar');

		if (!_contact_id)
		{
			_contact_id = this.egw().user('account_id');
		}
		else if(_contact_id.match(/account:/))
		{
			id = 'account_id';
			_contact_id = _contact_id.replace('account:','');
		}
		else
		{
			id = 'contact_id';
			_contact_id = _contact_id.replace('contact:', '');
		}

		// if our src (incl. cache-buster) already includes the correct id, use that one
		if (this.options.src && this.options.src.match("(&|\\?)contact_id="+_contact_id+"(&|\\$)"))
		{
			return;
		}
		params[id] = _contact_id;
		this.set_src(egw.link('/api/avatar.php',params));
	}

	/**
	 * Function to set value
	 */
	setValue(_value : string)
	{
		this.set_contact_id(_value);
	}

	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 */
	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("contact_id", "label", "href");
	}

	setDetachedAttributes(_nodes, _values)
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
	}

	/**
	 * Build Editable Mask Layer (EML) in order to show edit/delete actions
	 * on top of profile picture.
	 * @param {boolean} _noDelete disable delete button in initialization
	 */
	private _buildEditableLayer(_noDelete : boolean)
	{
		let self = this;
		// editable mask layer (eml)
		let wrapper = jQuery(document.createElement('div')).addClass('avatar').insertAfter(this.image);
		this.image.appendTo(wrapper);
		let eml = jQuery(document.createElement('div'))
			.addClass('eml')
			.insertAfter(this.image);

		// edit button
		jQuery(document.createElement('div'))
			.addClass('emlEdit')
			.click(function(){
				let buttons = [
					{"button_id": 1, label: self.egw().lang('save'), id: 'save', image: 'check', "default": true},
					{"button_id": 0, label: self.egw().lang('cancel'), id: 'cancel', image: 'cancelled'}
				];
				let dialog = function(_title, _value, _buttons, _egw_or_appname)
				{
					let dialog = new Et2Dialog(self.egw());
					dialog.transformAttributes({
						callback: function(_buttons, _value)
						{
							if(_buttons == 1)
							{
								let canvas = jQuery('#_cropper_image').cropper('getCroppedCanvas');
								self.image.attr('src', canvas.toDataURL("image/jpeg", 1.0));
								self.egw().json('addressbook.addressbook_ui.ajax_update_photo',
									[self.getInstanceManager().etemplate_exec_id, canvas.toDataURL('image/jpeg', 1.0)],
									function(res)
									{
										if(res)
										{
											del.show();
										}
									}).sendRequest();
							}
						},
						title: _title || egw.lang('Input required'),
						buttons: _buttons || Et2Dialog.BUTTONS_OK_CANCEL,
						value: {
							content: _value
						},
						width: "90%",
						height: "450",
						resizable: false,
						position: "top+10",
						template: egw.webserverUrl + '/api/templates/default/avatar_edit.xet?2'
					});
					document.body.appendChild(dialog);
					return dialog;
				};

				dialog(egw.lang('Edit avatar'),self.options, buttons, null);
			})
			.appendTo(eml);

		// delete button
		var del = jQuery(document.createElement('div'))
			.addClass('emlDelete')
			.click(function()
			{
				Et2Dialog.show_dialog(function(_btn)
				{
					if(_btn == Et2Dialog.YES_BUTTON)
					{
						self.egw().json('addressbook.addressbook_ui.ajax_update_photo',
							[self.getInstanceManager().etemplate_exec_id, null],
							function(res)
							{
								if(res)
								{
									self.image.attr('src', '');
									del.hide();
									egw.refresh('Avatar Deleted.', egw.app_name());
								}
							}).sendRequest();
					}
				}, egw.lang('Delete this photo?'), egw.lang('Delete'), null, Et2Dialog.BUTTONS_YES_NO);
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
	}

	/**
	 * We need to build the Editable Mask Layer after widget gets loaded
	 */
	doLoadingFinished() : boolean
	{
		super.doLoadingFinished();
		let self = this;
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
		if (this.options.crop && !this.options.readonly)
		{
			jQuery(this.image).cropper({
				aspectRatio: 1/1,
				crop: function (e){
					console.log (e);
				}
			});
		}
		return true;
	}
}
et2_register_widget(et2_avatar, ["avatar"]);
window['et2_avatar'] = et2_avatar;
/**
* Avatar readonly widget to only display user profile picture or
* user letter avatar based on user's firstname lastname.
*/
export class et2_avatar_ro extends et2_avatar
{
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_avatar_ro._attributes, _child || {}));
		this.options.editable = false;
	}
}
et2_register_widget(et2_avatar_ro, ["avatar_ro"]);

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
export class et2_lavatar extends et2_image
{
	static readonly _attributes : any = {
		lname: {
			name: "last name",
			type: "string",
			default: "",
			description:""
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

	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_lavatar._attributes, _child || {}));
	}

	set_src(_url){
		if (_url && decodeURIComponent(_url).match("lavatar=1") && (this.options.fname || this.options.lname) && this.options.contact_id)
		{
			this.set_src(et2_avatar.lavatar(this.options.fname, this.options.lname, this.options.contact_id));
			return false;
		}
		super.set_src(_url);
	}
}
et2_register_widget(et2_lavatar, ["lavatar"]);