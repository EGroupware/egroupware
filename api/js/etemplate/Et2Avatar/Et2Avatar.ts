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
import {css} from "lit";
import {SlAvatar} from "@shoelace-style/shoelace";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {egw} from "../../jsapi/egw_global";
import shoelace from "../Styles/shoelace";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import "../../../../vendor/bower-asset/cropper/dist/cropper.min.js";
import {cropperStyles} from "./cropperStyles";

export class Et2Avatar extends Et2Widget(SlAvatar) implements et2_IDetachedDOM
{
	private _contactId;
	private _delBtn: HTMLElement;
	private _editBtn : HTMLElement;

	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			cropperStyles,
			css`
				:host::part(edit) {
					visibility: hidden;
					border-radius: 50%;
					margin: -4px;
					z-index: 1;
				}
				
				:host(:hover)::part(edit) {
					visibility: visible;
				}
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
			contactId: {type: String, noAccessor: true},

			/**
			 * Image
			 * Displayed image
			 * @deprecated
			 */
			src: {type: String},

			/**
			 * The shape of the avatar
			 * circle | square | rounded
			 */
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

			/**
			 * Explicitly specify the avatar size.
			 * Better to set the --size CSS variable in app.css, since it allows inheritance and overriding
			 */
			size: {type: String}
		}
	}

	constructor()
	{
		super();
		this.src = "";
		this.label = "";
		this.contactId = "";
		this.editable = false;
		this.crop = false;
		this.icon = "";
		this.shape = "rounded";
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
			if(this.size && this.size.match(/\d/))
			{
				this.getDOMNode().setAttribute('style', `--size:${this.size}`);
			}
			else
			{
				this.style.removeProperty("--size")
			}
		}
	}

	firstUpdated()
	{
		let self = this;
		if (this.contactId && this.editable)
		{
			egw(window).json(
				'addressbook.addressbook_ui.ajax_noPhotoExists',
				[this.contactId],
				function(noPhotoExists)
				{
					if (noPhotoExists) self.image="";
					self._buildEditableLayer(noPhotoExists);
				}
			).sendRequest(true);
		}
	}


	get contactId()
	{
		return this._contactId;
	}

	static RFC822EMAIL = /<([^<>]+)>$/;

	/**
	 * Function to set contactId
	 * contactId could be in one of these formats:
	 *		'number', will be considered as contact_id
	 *		'contact:number', similar to above
	 *		'account:number', will be considered as account id
	 *		'email:<email>', will be considered as email address
	 * @example: contactId = "account:4"
	 *
	 * @param {string} _contactId contact id could be as above-mentioned formats
	 */
	set contactId(_contactId : string)
	{
		let params = {};
		let id = 'contact_id';
		let parsedId = "";

		if (!_contactId)
		{
			parsedId = null;
		}
		else if(_contactId.substr(0, 8) === 'account:')
		{
			id = 'account_id';
			parsedId = _contactId.substr(8);
		}
		else if(_contactId.substr(0, 6) === 'email:')
		{
			id = 'email';
			const matches = Et2Avatar.RFC822EMAIL.exec(_contactId);
			parsedId = matches ? matches[1] : _contactId.substr(6);
		}
		else
		{
			id = 'contact_id';
			parsedId = _contactId.replace('contact:', '');
		}
		let oldContactId = this._contactId;
		this._contactId = _contactId;
		// if our image (incl. cache-buster) already includes the correct id, use that one
		if (!parsedId)
		{
			this.image = null;
		}
		else if(!this.image || !this.image.match("(&|\\?)" + id + "=" + encodeURIComponent(parsedId) + "(&|$)"))
		{
			params[id] = parsedId;
			this.image = egw.link('/api/avatar.php', params);
		}
		this.requestUpdate("contactId", oldContactId);
	}

	set value(_value)
	{
		this.contactId = _value;
	}

	/**
	 * set the image source
	 * @deprecated please use image instead
	 * @param _value
	 */
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
		this._editBtn = document.createElement('et2-button-icon');
		this._editBtn.setAttribute('image', 'pencil');
		this._editBtn.setAttribute('part', 'edit');
		this._delBtn = document.createElement('et2-button-icon');
		this._delBtn.setAttribute('image', 'delete');
		this._delBtn.setAttribute('part', 'edit');
		this._baseNode.append(this._editBtn);
		this._baseNode.append(this._delBtn);

		// disable the delete button if no delete is set
		this._delBtn.disabled = _noDelete;

		// bind click handler to edit button
		this._editBtn.addEventListener('click', this.editButtonClickHandler.bind(this));

		// bind click handler to del button
		this._delBtn.addEventListener('click', this.delButtonClickHandler.bind(this));
	}

	/**
	 * click handler to handle click on edit button
	 */
	editButtonClickHandler()
	{
		const buttons = [
			{"button_id": 1, label: this.egw().lang('save'), id: 'save', image: 'check', "default": true},
			{"button_id": 0, label: this.egw().lang('cancel'), id: 'cancel', image: 'cancelled'}
		];
		const value = {
			contactId: this.contactId,
			src: this.image
		}
		this._editDialog(egw.lang('Edit avatar'), value, buttons, null);
	}

	/**
	 * Build edit dialog
	 * @param _title
	 * @param _value
	 * @param _buttons
	 * @param _egw_or_appname
	 */
	private _editDialog(_title, _value, _buttons, _egw_or_appname)
	{
		let dialog = new Et2Dialog(this.egw());
		dialog.transformAttributes({
			callback: this.__editDialogCallback.bind(this),
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
	}

	/**
	 * Edit dialog callback function
	 * @param _buttons
	 * @param _value
	 */
	private __editDialogCallback(_buttons, _value)
	{
		let widget = document.getElementById('_cropper_image');
		switch(_buttons)
		{
			case 0:
				return true;
			case 1:
				let canvas = jQuery(widget._imageNode).cropper('getCroppedCanvas');
				this.image =  canvas.toDataURL("image/jpeg", 1.0)
				this.egw().json('addressbook.addressbook_ui.ajax_update_photo',
					[this.getInstanceManager().etemplate_exec_id,  canvas.toDataURL("image/jpeg", 1.0)],
					this.__editAjaxUpdatePhotoCallback.bind(this)).sendRequest();
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
			default:
				return false;
		}
	}

	/**
	 * Edit ajax update photo response callback
	 * @param response
	 */
	private __editAjaxUpdatePhotoCallback(response)
	{
		if(response)
		{
			this._delBtn.style.visibility = 'visible';
		}
	}

	/**
	 * click handler to handel click on delete button
	 */
	delButtonClickHandler()
	{
		//build delete dialog
		Et2Dialog.show_dialog(this._delBtnDialogCallback.bind(this), egw.lang('Delete this photo?'), egw.lang('Delete'),
			null, Et2Dialog.BUTTONS_YES_NO);
	}

	/**
	 * del dialog callback function
	 * @param _btn
	 */
	private _delBtnDialogCallback(_btn)
	{
		if(_btn == Et2Dialog.YES_BUTTON)
		{
			this.egw().json('addressbook.addressbook_ui.ajax_update_photo',
				[this.getInstanceManager().etemplate_exec_id, null],
				this.__delAjaxUpdatePhotoCallback.bind(this)).sendRequest();
		}
	}

	/**
	 * Del ajax update photo response callback
	 * @param response
	 */
	private __delAjaxUpdatePhotoCallback(response)
	{
		if(response)
		{
			this.image =  '';
			this._delBtn.style.visibility = 'none';
			egw.refresh('Avatar Deleted.', egw.app_name());
		}
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
		_attrs.push("contactId", "label", "href", "src", "image", "statustext");
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

customElements.define("et2-avatar", Et2Avatar);
// make et2_avatar publicly available as we need to call it from templates
{
	window['et2_avatar'] = Et2Avatar;
	window['Et2Avatar'] = Et2Avatar;
}