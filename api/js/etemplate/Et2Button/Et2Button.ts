/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "@lion/core";
import {LionButton} from "@lion/button";
import {SlotMixin} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {buttonStyles} from "./ButtonStyles";

export class Et2Button extends Et2InputWidget(SlotMixin(LionButton))
{
	protected _created_icon_node : HTMLImageElement;
	protected clicked : boolean = false;
	private _image : string;

	/**
	 * images to be used as background-image, if none is explicitly applied and id matches given regular expression
	 */
	static readonly default_background_images : object = {
		save: /save(&|\]|$)/,
		apply: /apply(&|\]|$)/,
		cancel: /cancel(&|\]|$)/,
		delete: /delete(&|\]|$)/,
		discard: /discard(&|\]|$)/,
		edit: /edit(&|\[\]|$)/,
		next: /(next|continue)(&|\]|$)/,
		finish: /finish(&|\]|$)/,
		back: /(back|previous)(&|\]|$)/,
		copy: /copy(&|\]|$)/,
		more: /more(&|\]|$)/,
		check: /(yes|check)(&|\]|$)/,
		cancelled: /no(&|\]|$)/,
		ok: /ok(&|\]|$)/,
		close: /close(&|\]|$)/,
		add: /(add(&|\]|$)|create)/	// customfields use create*
	};

	/**
	 * Classnames added automatically to buttons to set certain hover background colors
	 */
	static readonly default_classes : object = {
		et2_button_cancel: /cancel(&|\]|$)/,		// yellow
		et2_button_question: /(yes|no)(&|\]|$)/,	// yellow
		et2_button_delete: /delete(&|\]|$)/			// red
	};

	static get styles()
	{
		return [
			...super.styles,
			buttonStyles,
			css`
            :host {
                padding: 0;
                /* These should probably come from somewhere else */
               	max-width: 125px;
               	min-width: fit-content;
            }
            :host([readonly]) {
            	display: none;
            }
            /* Set size for icon */
            ::slotted(img.imageOnly) {
    			padding-right: 0px !important;
    			width: 16px !important;
			}
            ::slotted([slot="icon"][src]) {
                width: 20px;
                padding-right: 4px;
            }
            ::slotted([slot="icon"][src='']) {
				display: none;
			}
			.imageOnly {
				width:18px;
				height: 18px;
			}
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			image: {type: String}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			icon: () =>
			{
				return document.createElement("img");
			}
		}
	}

	constructor()
	{
		super();

		// Property default values
		this._image = '';

		// Do not add icon here, no children can be added in constructor

	}

	connectedCallback()
	{
		super.connectedCallback();

		//this.classList.add("et2_button")
	}

	set image(new_image : string)
	{
		let oldValue = this._image;
		if(new_image.indexOf("http") >= 0)
		{
			this._image = new_image
		}
		else
		{
			this._image = this.egw().image(new_image);
		}
		this.requestUpdate("image", oldValue);
	}

	_handleClick(event : MouseEvent) : boolean
	{
		// ignore click on readonly button
		if(this.disabled || this.readonly)
		{
			return false;
		}

		this.clicked = true;

		// Cancel buttons don't trigger the close confirmation prompt
		if(this.classList.contains("et2_button_cancel"))
		{
			this.getInstanceManager()?.skip_close_prompt();
		}

		if(!super._handleClick(event))
		{
			this.clicked = false;
			return false;
		}

		// Submit the form
		if(this.getType() !== "buttononly")
		{
			return this.getInstanceManager().submit();
		}
		this.clicked = false;
		this.getInstanceManager()?.skip_close_prompt(false);
		return true;
	}

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	requestUpdate(name : PropertyKey, oldValue)
	{
		super.requestUpdate(name, oldValue);

		// Default image & class are determined based on ID
		if(name == "id" && this._widget_id)
		{
			// Check against current value to avoid triggering another update
			if(!this.image)
			{
				let image = this._get_default_image(this._widget_id);
				if(image != this._image)
				{
					this.image = image;
				}
			}
			let default_class = this._get_default_class(this._widget_id);
			if(default_class && !this.classList.contains(default_class))
			{
				this.classList.add(default_class);
			}
		}
	}

	render()
	{
		if(this.readonly)
		{
			return '';
		}

		this._iconNode.src = this._image;
		if (!this._label) this._iconNode.classList.add('imageOnly');
		return html`
            <div class="button-content et2_button ${this._label?'':'imageOnly'}" id="${this._buttonId}">
           		<slot name="icon" class="${this._label?'':'imageOnly'}"></slot>
                <slot name="label">${this._label}</slot>
            </div> `;
	}

	/**
	 * Get a default image for the button based on ID
	 *
	 * @param {string} check_id
	 */
	_get_default_image(check_id : string) : string
	{
		if(!check_id)
		{
			return "";
		}

		if(typeof this.image == 'undefined')
		{
			for(const image in Et2Button.default_background_images)
			{
				if(check_id.match(Et2Button.default_background_images[image]))
				{
					return image;
				}
			}
		}
		return "";
	}

	/**
	 * Get a default class for the button based on ID
	 *
	 * @param check_id
	 * @returns {string}
	 */
	_get_default_class(check_id)
	{
		if(!check_id)
		{
			return "";
		}
		for(var name in Et2Button.default_classes)
		{
			if(check_id.match(Et2Button.default_classes[name]))
			{
				return name;
			}
		}
		return "";
	}

	get _iconNode() : HTMLImageElement
	{
		return <HTMLImageElement>(Array.from(this.children)).find(
			el => (<HTMLElement>el).slot === "icon",
		);
	}

	/**
	 * Implementation of the et2_IInput interface
	 */

	/**
	 * Always return false as a button is never dirty
	 */
	isDirty()
	{
		return false;
	}

	resetDirty()
	{
	}

	getValue()
	{
		if(this.clicked)
		{
			return true;
		}

		// If "null" is returned, the result is not added to the submitted
		// array.
		return null;
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Button is a LitElement
customElements.define("et2-button", Et2Button);
