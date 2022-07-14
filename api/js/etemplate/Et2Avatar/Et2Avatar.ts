/**
 * EGroupware eTemplate2 - Avatar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, SlotMixin} from "@lion/core";
import {SlAvatar} from "@shoelace-style/shoelace";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {egw} from "../../jsapi/egw_global";
import shoelace from "../Styles/shoelace";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import "../../../../vendor/bower-asset/cropper/dist/cropper.min.js";
import {cropperStyles} from "./cropperStyles";

export class Et2Avatar extends Et2Widget(SlotMixin(SlAvatar)) implements et2_IDetachedDOM
{
	private _contact_id;
	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			cropperStyles,
			css`
			
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * The label of the image
			 * Actually not used as label, but we put it as title
			 * Added here as there's no Lion parent
			 */
			label: {
				type: String
			},

			/**
			 * Contact id should be either user account_id {account:number} or contact_id {contact:number or number}
			 */
			contact_id:{type: String},

			/**
			 * Image
			 * Displayed image
			 */
			src: {type: String},


			shape: {
				type: String,
				reflect: true
			},

			/**
			 * Make avatar widget editable to be able to crop profile picture or upload a new photo
			 */
			editable: {type: Boolean},

			image: {
				type: String,
				reflect: true
			},

			crop: {type: Boolean},

			size: {type: String}
		}
	}

	constructor()
	{
		super();
		this.src = "";
		this.label = "";
		this.contact_id = "";
		this.editable = false;
		this.crop = false;
		this.size = "3em";
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	updated(changedProperties)
	{
		super.updated(changedProperties);

		if (changedProperties.has("crop")) {
			if (this.crop && !this.readonly && this._imageNode)
			{
				jQuery(this._imageNode).cropper({aspectRatio: 1/1});
			}
		}
		if (changedProperties.has("size"))
		{
			this.getDOMNode().setAttribute('style', `--size:${this.size}`);
		}
	}

	firstUpdated()
	{
		let self = this;
		if (this.contact_id && this.editable)
		{
			egw(window).json(
				'addressbook.addressbook_ui.ajax_noPhotoExists',
				[this.contact_id],
				function(noPhotoExists)
				{
					if (noPhotoExists) self.image="";
					self._buildEditableLayer(noPhotoExists);
				}
			).sendRequest(true);
		}
	}


	get contact_id()
	{
		return this._contact_id;
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
	set contact_id(_contact_id : string)
	{
		let params = {};
		let id = 'contact_id';

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
		let oldContactId = this._contact_id;
		this._contact_id = _contact_id;
		// if our src (incl. cache-buster) already includes the correct id, use that one
		if (!this.src || !this.src.match("(&|\\?)contact_id="+_contact_id+"(&|\\$)"))
		{
			params[id] = _contact_id;
			this.src  = egw.link('/api/avatar.php',params);
		}
		this.requestUpdate("contact_id", oldContactId);
	}

	set value(_value)
	{
		this.contact_id = _value;
	}

	set src(_value)
	{
		this.image = _value;
	}

	get _baseNode()
	{
		return this.shadowRoot.querySelector("[part='base']");
	}

	get _imageNode()
	{
		return this.shadowRoot.querySelector("[part='image']");
	}

	/**
	 * Build Editable Mask Layer (EML) in order to show edit/delete actions
	 * on top of profile picture.
	 * @param {boolean} _noDelete disable delete button in initialization
	 */
	private _buildEditableLayer(_noDelete : boolean)
	{
		let self = this;
		let editBtn = document.createElement('sl-icon-button');
		editBtn.setAttribute('name', 'pencil');
		let delBtn = document.createElement('sl-icon-button');
		delBtn.setAttribute('name', 'trash');
		this._baseNode.append(editBtn);
		this._baseNode.append(delBtn);

		delBtn.disabled = _noDelete;

		editBtn.addEventListener('click', function(){
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
						let widget = document.getElementById('_cropper_image');
						switch(_buttons)
						{
							case 1:
								let canvas = jQuery(widget._imageNode).cropper('getCroppedCanvas');
								self.image =  canvas.toDataURL("image/jpeg", 1.0)
								self.egw().json('addressbook.addressbook_ui.ajax_update_photo',
									[self.getInstanceManager().etemplate_exec_id,  canvas.toDataURL("image/jpeg", 1.0)],
									function(res)
									{
										if(res)
										{
											delBtn.style.visibility = 'visible';
										}
									}).sendRequest();
								break;
							case '_rotate_reset':
								jQuery(widget._imageNode).cropper('reset');
								return false;
							case '_rotate_l':
								jQuery(widget._imageNode).cropper('rotate', -90);
								return false;
							case '_rotate_r':
								jQuery(widget._imageNode).cropper('rotate', 90);
								return false;
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
					template: egw.webserverUrl + '/api/templates/default/avatar_edit.xet'
				});
				document.body.appendChild(dialog);
				return dialog;
			};

			dialog(egw.lang('Edit avatar'),self.options, buttons, null);
		});



		delBtn.addEventListener('click', function()
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
								self.image =  '';
								delBtn.style.visibility = 'none';
								egw.refresh('Avatar Deleted.', egw.app_name());
							}
						}).sendRequest();
				}
			}, egw.lang('Delete this photo?'), egw.lang('Delete'), null, Et2Dialog.BUTTONS_YES_NO);
		});
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
			let widget = document.getElementById('_cropper_image');
			widget.image = e.target.result;
			// Wait for everything to complete
			widget.getUpdateComplete().then(() =>
			{
				jQuery(widget._imageNode).cropper('replace',e.target.result)
			});

		};
		reader.readAsDataURL(file);
	}

	/**
	 *
	 */
	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("contact_id", "label", "href");
	}

	getDetachedNodes()
	{
		return [<HTMLElement><unknown>this];
	}

	setDetachedAttributes(_nodes, _values)
	{
		for(let attr in _values)
		{
			this[attr] = _values[attr];
		}
	}
}
customElements.define("et2-avatar", Et2Avatar as any);
// make et2_avatar publicly available as we need to call it from templates
{
	window['et2_avatar'] = Et2Avatar;
	window['Et2Avatar'] = Et2Avatar;
}