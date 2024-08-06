/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, nothing, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {Regex} from "../Validators/Regex";
import shoelace from "../Styles/shoelace";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import IMask, {InputMask} from "imask";
import {SlInput} from "@shoelace-style/shoelace";

@customElement("et2-textbox")
export class Et2Textbox extends Et2InputWidget(SlInput)
{

	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
				:host([type="hidden"]) {
					display: none;
				}

				.form_control {
					display: inline-flex;
				}
				.input__control {
					border: none;
					width: 100%;
				}
				.input:hover:not(.input--disabled) .input__control {
					color: var(--input-text-color, inherit);
				}
				`,
		];
	}

	@property()
	value = "";

	/**
	 * Placeholder text to show as a hint when the input is empty.
	 */
	@property()
	placeholder;

	/**
	 * Mask the input to enforce format.  The mask is enforced as the user types, preventing invalid input.
	 */
	@property()
	mask;

	/**
	 * Disables the input.  It is still visible.
	 * @type {boolean}
	 */
	@property({type: Boolean})
	disabled = false;

	@property({type: Function})
	onkeypress;

	private __validator : any;
	private _mask : InputMask;
	protected _value : string = "";

	inputMode = "text";


	static get translate()
	{
		return Object.assign({
			helpText: true
		}, super.translate);
	}

	constructor(...args : any[])
	{
		super(...args);
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("focus", this.handleFocus);
	}

	firstUpdated()
	{
		if(this.maskOptions.mask)
		{
			this.updateMask();
		}
	}

	/** @param  changedProperties */
	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		if(changedProperties.has('validator'))
		{
			// Remove all existing Pattern validators (avoids duplicates)
			this.validators = (this.validators || []).filter((validator) => !(validator instanceof Regex))
			this.validators.push(new Regex(this.validator));
		}
		if(changedProperties.has('mask'))
		{
			this.updateMask();
		}
	}

	@property()
	get validator()
	{
		return this.__validator;
	}

	set validator(value : string | RegExp)
	{
		if(typeof value == 'string')
		{
			let parts = value.split('/');
			let flags = parts.pop();
			if(parts.length < 2 || parts[0] !== '')
			{
				this.egw().debug(this.egw().lang("'%1' has an invalid format !!!", value));
				return;
			}
			parts.shift();
			this.__validator = new RegExp(parts.join('/'), flags);

			this.requestUpdate("validator");
		}
		else if(value instanceof RegExp)
		{
			this.__validator = value;
			this.requestUpdate("validator");
		}
	}

	/**
	 * Get the options for masking.
	 * Can be overridden by subclass for additional options.
	 *
	 * @see https://imask.js.org/guide.html#masked
	 */
	protected get maskOptions()
	{
		return {
			mask: this.mask,
			lazy: this.placeholder ? true : false,
			autofix: true,
			eager: "append",
			overwrite: "shift"
		}
	}

	protected updateMask()
	{
		const input = this.shadowRoot.querySelector("input")
		if(!this._mask)
		{
			this._mask = IMask(input, this.maskOptions);
			this.addEventListener("focus", this.handleFocus)
			window.setTimeout(() =>
			{
				this._mask.updateControl();
			}, 1);
		}
		else
		{
			this._mask.updateOptions(this.maskOptions);
		}

		if(this._mask)
		{
			this.updateMaskValue();
		}
	}

	protected updateMaskValue()
	{
		this._mask.unmaskedValue = "" + this.value;
		this._mask.updateValue();
		this.updateComplete.then(() =>
		{
			this._mask.updateControl();
		});
	}

	protected handleFocus(event)
	{
		if(this._mask)
		{
			//	this._mask.updateValue();
		}
	}

	protected _inputTemplate()
	{
		return html`
            <sl-input
                    part="input"
                    placeholder=${this.placeholder || nothing}
                    inputmode="${this.inputMode}"
                    ?disabled=${this.disabled}
                    ?readonly=${this.readonly}
                    ?required=${this.required}
                    .value=${this.value}
                    @input=${(e) =>
		{
			if(this.__mask)
			{
				this.__mask.updateCursor(this.__mask.cursorPos)
			}
		}}
            >
                <slot name="prefix" slot="prefix"></slot>
                <slot name="suffix" slot="suffix"></slot>
            </sl-input>
		`;
	}

	/*
		render()
		{
			const labelTemplate = this._labelTemplate();
			const helpTemplate = this._helpTextTemplate();

			return html`
				<div
						part="form-control"
						class=${classMap({
							'form-control': true,
							'form-control--medium': true,
							'form-control--has-label': labelTemplate !== nothing,
							'form-control--has-help-text': helpTemplate !== nothing
						})}
				>
					${labelTemplate}
					<div part="form-control-input" class="form-control-input">
						${this._inputTemplate()}
					</div>
					${helpTemplate}
				</div>
			`;
		}

	 */
}
