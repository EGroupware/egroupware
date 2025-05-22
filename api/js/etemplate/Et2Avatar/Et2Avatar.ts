/**
 * EGroupware eTemplate2 - Avatar widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {css} from "lit";
import {property} from "lit/decorators/property.js";
import {SlAvatar} from "@shoelace-style/shoelace";
import {et2_IDetachedDOM} from "../et2_core_interfaces";
import {egw} from "../../jsapi/egw_global";
import shoelace from "../Styles/shoelace";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import "../../../../vendor/bower-asset/cropper/dist/cropper.min.js";
import {cropperStyles} from "./cropperStyles";
import type {Et2Button} from "../Et2Button/Et2Button";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {CachedQueueMixin} from "../Et2Widget/CachedQueueMixin";

/**
 * Avatars are used to represent a person or profile.
 *
 * @slot icon The default icon to use when no image or initials are provided.
 *
 * @event sl-error	The image could not be loaded. This may because of an invalid URL, a temporary network condition, or some unknown cause.
 *
 * @csspart base	The component’s base wrapper.
 * @csspart icon	The container that wraps the avatar’s icon.
 * @csspart initials	The container that wraps the avatar’s initials.
 * @csspart image	The avatar image. Only shown when the image attribute is set, or when contactId has an associated avatar image
 */
export class Et2Avatar extends CachedQueueMixin(Et2Widget(SlAvatar)) implements et2_IDetachedDOM
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

	/**
	 * The label of the image
	 * Actually not used as label, but we put it as title
	 */
	@property()
	label = "";

	/**
	 * The shape of the avatar
	 * circle | square | rounded
	 */
	@property({type: String, reflect: true})
	shape : string = "rounded";

	/**
	 * Make avatar widget editable to be able to crop profile picture or upload a new photo
	 */
	@property({type: Boolean}) editable = false;

	@property({type: String, reflect: true})
	image = "";

	@property({type: Boolean})
	crop = false;

	/**
	 * Explicitly specify the avatar size.
	 * Better to set the --size CSS variable in app.css, since it allows inheritance and overriding
	 */
	@property()
	size;

	// Cached Queue Mixin
	protected static searchUrl = "EGroupware\\Api\\Etemplate\\Widget\\Avatar::ajax_image_check";

	constructor()
	{
		super();
		this.image = this.image || this.getAttribute("src");
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
				jQuery(this._imageNode).cropper({aspectRatio: 1/1, autoCropArea: 1});
			}
		}
		if (changedProperties.has("size"))
		{
			if(this.size && this.size.match(/\d/))
			{
				this.style.setProperty('--size', this.size);
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
	@property({type: String, noAccessor: true})
	set contactId(_contactId : string)
	{
		let oldContactId = this._contactId;
		this._contactId = _contactId;

		if(!_contactId || this.image)
		{
			this.requestUpdate("contactId", oldContactId);
			return;
		}

		const params = {no_gen: true};
		let id = 'contact_id';
		let parsedId = "";

		if(!_contactId)
		{
			parsedId = null;
		}
		else if(_contactId.substr(0, 8) === 'account:')
		{
			id = 'account';
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
		if(_contactId)
		{
			if(!parsedId)
			{
				this.image = null;
			}
			// if our image (incl. cache-buster) already includes the correct id, use that one
			else if(!this.image || !this.image.match("(&|\\?)" + id + "=" + encodeURIComponent(parsedId) + "(&|$)"))
			{
				/**
				 * To reduce the number of server requests that result in 404 because there is no avatar,
				 * we cacheQueue the request and only set actual image if server reports there is an avatar image.
				 */
				this.cachedQueue([`${id}:${parsedId}`]).then((hasImage) =>
				{
					if(!hasImage)
					{
						this.image = null;
						return;
					}

					params[id] = parsedId;
					this.image = this.egw().link('/api/avatar.php', params);
				});
			}
			this.requestUpdate("contactId");
		}
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
		this._editBtn.noSubmit = true;
		this._delBtn = <Et2Button>document.createElement('et2-button-icon');
		this._delBtn.setAttribute('image', 'delete');
		this._delBtn.setAttribute('part', 'edit');
		this._delBtn.noSubmit = true;
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
				etemplate_exec_id: this.getInstanceManager().etemplate_exec_id,
				content: _value
			},
			width: "90%",
			height: "450",
			resizable: false,
			position: "top+10",
			template: egw.webserverUrl + '/api/templates/default/avatar_edit.xet'
		});
		document.body.appendChild(dialog);
		dialog.updateComplete.then(() =>
		{
			dialog.querySelector("#_buttons").addEventListener("click", this._handleRotate);
		});
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
				this.requestUpdate('image');
				this.egw().json('addressbook.addressbook_ui.ajax_update_photo',
					[this.getInstanceManager().etemplate_exec_id,  this.image],
					this.__editAjaxUpdatePhotoCallback.bind(this)).sendRequest();
				break;
			default:
				return false;
		}
	}

	private _handleRotate(event)
	{
		let widget = document.getElementById('_cropper_image');
		switch(event.target.id)
		{
			case 'rotate_reset':
				jQuery(widget._imageNode).cropper('reset');
				return false;
			case 'rotate_l':
				jQuery(widget._imageNode).cropper('rotate', -90);
				return false;
			case 'rotate_r':
				jQuery(widget._imageNode).cropper('rotate', 90);
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
	 * Function runs after upload in avatar dialog is finished and it tries to
	 * update image and cropper container.
	 * @param {type} e
	 */
	static uploadAvatar_onFinish(e)
	{
		const file = e.detail.file;
		let reader = new FileReader();
		const fileWidget = e.target;
		fileWidget.loading = true;
		reader.onload = function (e)
		{
			let widget = document.getElementById('_cropper_image');
			widget.image = e.target.result;
			widget.requestUpdate("image");
			// Wait for everything to complete
			widget.getUpdateComplete().then(() =>
			{
				jQuery(widget._imageNode).cropper('replace',e.target.result)
				fileWidget.loading = false;
				fileWidget.requestUpdate("loading", true);
			});
			fileWidget.value = {};
		};
		reader.readAsDataURL(file);
	}

	/**
	 *
	 */
	getDetachedAttributes(_attrs : string[])
	{
		_attrs.push("contactId", "label", "href", "image", "statustext");
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