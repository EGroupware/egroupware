/**
 * EGroupware eTemplate2 - Common button code
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, LitElement, PropertyValues} from "@lion/core";
import '../Et2Image/Et2Image';
import shoelace from "../Styles/shoelace";
import {Et2Button} from "./Et2Button";

type Constructor<T = LitElement> = new (...args : any[]) => T;
export const ButtonMixin = <T extends Constructor>(superclass : T) => class extends superclass
{
	protected clicked : boolean = false;

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
			...shoelace,
			...(super.styles || []),
			css`
            :host {
                padding: 0;
                /* These should probably come from somewhere else */
               	max-width: 125px;
               	min-width: fit-content;
            }
            :host([hideonreadonly][disabled]) {
            	display:none;
            }
            /* Set size for icon */
            ::slotted(img.imageOnly) {
    			padding-right: 0px !important;
    			width: 16px !important;
			}
            ::slotted(et2-image) {
                max-width: 20px;
                display: flex;
            }
            ::slotted([slot="icon"][src='']) {
				display: none;
			}
			.imageOnly {
				width:18px;
				height: 18px;
			}
			/* Make hover border match other widgets (select) */
			.button--standard.button--default:hover:not(.button--disabled) {
				background-color: var(--sl-input-background-color-hover);
				border-color: var(--sl-input-border-color-hover);
				color: var(--sl-input-color-hover);
			}
			.button {
				justify-content: left;
			}
			.button--has-label.button--medium .button__label {
				padding: 0 var(--sl-spacing-medium);
			}
			.button__label {
			}
			.button__prefix {
				padding-left: 1px;
			}
			
			/* Only image, no label */
			.button--has-prefix:not(.button--has-label) {
				justify-content: center;
				width: var(--sl-input-height-medium);
				padding-inline-start: 0;			
			}
			
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			label: {type: String},
			image: {type: String},

			/**
			 * If button is set to readonly, do we want to hide it completely (old behaviour) or show it as disabled
			 * (default)
			 * Something's not quite right here, as the attribute shows up as "hideonreadonly" instead of "hide" but
			 * it does not show up without the "attribute", and attribute:"hideonreadonly" does not show as an attribute
			 */
			hideOnReadonly: {type: Boolean, reflect: true, attribute: "hide"},

			/**
			 * Button should submit the etemplate
			 * Return false from the click handler to cancel the submit, or set noSubmit to true to skip submitting.
			 */
			noSubmit: {type: Boolean, reflect: false},

			/**
			 * When submitting, skip the validation step.  Allows to submit etemplates directly to the server.
			 */
			noValidation: {type: Boolean}
		}
	}

	constructor(...args : any[])
	{
		super(...args);

		// Property default values
		this.__image = '';
		this.noSubmit = false;
		this.hideOnReadonly = false;
		this.noValidation = false;

		// Do not add icon here, no children can be added in constructor

	}

	set image(new_image : string)
	{
		let oldValue = this.__image;
		if(new_image.indexOf("http") >= 0 || new_image.indexOf(this.egw().webserverUrl) >= 0)
		{
			this.__image = new_image
		}
		else
		{
			this.__image = this.egw().image(new_image);
		}
		this.requestUpdate("image", oldValue);
	}

	get image()
	{
		return this.__image;
	}

	_handleClick(event : MouseEvent) : boolean
	{
		// ignore click on readonly button
		if(this.disabled || this.readonly)
		{
			event.preventDefault();
			event.stopImmediatePropagation();
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
		if(!this.noSubmit)
		{
			return this.getInstanceManager().submit(this, undefined, this.noValidation);
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

		// "disabled" is the attribute from the spec
		if(name == 'readonly')
		{
			if(this.readonly)
			{
				this.setAttribute('disabled', "");
			}
			else
			{
				this.removeAttribute("disabled");
			}
		}

		// Default image & class are determined based on ID
		if(name == "id" && this._widget_id)
		{
			// Check against current value to avoid triggering another update
			if(!this.image)
			{
				let image = this._get_default_image(this._widget_id);
				if(image && image != this.__image)
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

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has("image"))
		{
			if(this.image && !this._iconNode)
			{
				const image = document.createElement("et2-image");
				image.slot = "prefix";
				this.prepend(image);
				image.src = this.__image;
			}
			else if(this._iconNode)
			{
				this._iconNode.src = this.__image;
			}
		}
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

		if(!this.image)
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
			el => (<HTMLElement>el).slot === "prefix",
		);
	}

	get _labelNode() : HTMLElement
	{
		return <HTMLImageElement>(Array.from(this.childNodes)).find(
			el => (<HTMLElement>el).nodeType === 3,
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
