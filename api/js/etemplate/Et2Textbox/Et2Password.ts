/**
 * EGroupware eTemplate2 - Password input widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2InvokerMixin} from "../Et2Url/Et2InvokerMixin";
import {Et2Textbox} from "./Et2Textbox";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {classMap, html, ifDefined} from "@lion/core";
import {egw} from "../../jsapi/egw_global";

const isChromium = navigator.userAgentData?.brands.some(b => b.brand.includes('Chromium'));
const isFirefox = isChromium ? false : navigator.userAgent.includes('Firefox');

/**
 * @customElement et2-password
 */
export class Et2Password extends Et2InvokerMixin(Et2Textbox)
{
	// The password is stored encrypted server side, and passed encrypted.
	// This flag is for if we've decrypted the password to show it already
	private encrypted = true;
	private visible = false;

	/** @type {any} */
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Password is plaintext
			 */
			plaintext: Boolean,
			/**
			 * Suggest password length (0 for off)
			 */
			suggest: Number,
		};
	}

	constructor(...args : any[])
	{
		super(...args);

		this.plaintext = true;
		this.suggest = 0;

		this._invokerLabel = '';
		this._invokerTitle = this.egw().lang("Suggest password");
		this._invokerAction = () =>
		{
			this.suggestPassword();
		};
	}
	
	transformAttributes(attrs)
	{
		if(typeof attrs.suggest !== "undefined")
		{
			attrs.suggest = parseInt(attrs.suggest);
		}
		attrs.type = 'password';

		if(attrs.viewable)
		{
			attrs['toggle-password'] = true;
		}

		super.transformAttributes(attrs);
	}

	/**
	 * Method to check if invoker can be activated: not disabled, empty or invalid
	 *
	 * @protected
	 * */
	_toggleInvokerDisabled()
	{
		if (this._invokerNode)
		{
			const invokerNode = /** @type {HTMLElement & {disabled: boolean}} */ (this._invokerNode);
			invokerNode.disabled = this.disabled;
		}
	}

	/**
	 * @param {PropertyKey} name
	 * @param {?} oldValue
	 */
	requestUpdate(name, oldValue)
	{
		super.requestUpdate(name, oldValue);

		if (name === 'suggest' && this.suggest != oldValue)
		{
			this._invokerLabel = this.suggest ? 'generate_password' : '';
			this._toggleInvokerDisabled();
		}
	}

	/**
	 * @param _len
	 * @deprecated use this.suggest instead
	 */
	set_suggest(_len)
	{
		this.suggest = _len;
	}

	/**
	 * Ask the server for a password suggestion
	 */
	suggestPassword()
	{
		// They need to see the suggestion
		this.encrypted = false;
		this.type = 'text';
		//this.toggle_visibility(true);

		let suggestion = "Suggestion";
		let request = egw.request("EGroupware\\Api\\Etemplate\\Widget\\Password::ajax_suggest", [this.suggest])
			.then(suggestion =>
			{
				this.encrypted = false;
				this.value = suggestion;

				// Check for second password, update it too
				let two = this.getParent().getWidgetById(this.id+'_2');
				if(two && two.getType() == this.getType())
				{
					two.type = 'text';
					two.value = suggestion;
				}
			});
	}

	/**
	 * If the password is viewable, toggle the visibility.
	 * If the password is still encrypted, we'll ask for the user's password then have the server decrypt it.
	 */
	handlePasswordToggle()
	{
		super.handlePasswordToggle();

		this.visible = !this.visible;	// can't access private isPasswordVisible

		if (!this.visible || !this.encrypted)
		{
			return;
		}

		// Need username & password to decrypt
		Et2Dialog.show_prompt(
			(button, user_password) =>
			{
				if(button == Et2Dialog.CANCEL_BUTTON)
				{
					return this.handlePasswordToggle();
				}
				this.egw().request(
					"EGroupware\\Api\\Etemplate\\Widget\\Password::ajax_decrypt",
					[user_password, this.value]).then(decrypted =>
				{
					if (decrypted)
					{
						this.encrypted = false;
						this.value = decrypted;
						this.type = 'text';
					}
					else
					{
						this.set_validation_error(this.egw().lang("invalid password"));
						window.setTimeout(() =>
						{
							this.set_validation_error(false);
						}, 2000);
					}
				});
			},
			this.egw().lang("Enter your password"),
			this.egw().lang("Authenticate")
		);
	}

	render()
	{
		const hasLabelSlot = this.hasSlotController.test('label');
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasLabel = this.label ? true : !!hasLabelSlot;
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		const hasClearIcon =
			this.clearable && !this.disabled && !this.readonly && (typeof this.value === 'number' || this.value.length > 0);

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--small': this.size === 'small',
                        'form-control--medium': this.size === 'medium',
                        'form-control--large': this.size === 'large',
                        'form-control--has-label': hasLabel,
                        'form-control--has-help-text': hasHelpText
                    })}
            >
                <label
                        part="form-control-label"
                        class="form-control__label"
                        for="input"
                        aria-hidden=${hasLabel ? 'false' : 'true'}
                >
                    <slot name="label">${this.label}</slot>
                </label>
                <div part="form-control-input" class="form-control-input">
                    <div
                            part="base"
                            class=${classMap({
                                input: true,
                                // Sizes
                                'input--small': this.size === 'small',
                                'input--medium': this.size === 'medium',
                                'input--large': this.size === 'large',
                                // States
                                'input--pill': this.pill,
                                'input--standard': !this.filled,
                                'input--filled': this.filled,
                                'input--disabled': this.disabled,
                                'input--focused': this.hasFocus,
                                'input--empty': !this.value,
                                'input--no-spin-buttons': this.noSpinButtons,
                                'input--is-firefox': isFirefox
                            })}
                    >
                        <slot name="prefix" part="prefix" class="input__prefix"></slot>
                        <input
                                part="input"
                                id="input"
                                class="input__control"
                                type=${this.type === 'password' && this.passwordVisible ? 'text' : this.type}
                                title=${this.title /* An empty title prevents browser validation tooltips from appearing on hover */}
                                name=${ifDefined(this.name)}
                                ?disabled=${this.disabled}
                                ?readonly=${this.readonly || this.autocomplete == "new-password"}
                                ?required=${this.required}
                                placeholder=${ifDefined(this.placeholder)}
                                minlength=${ifDefined(this.minlength)}
                                maxlength=${ifDefined(this.maxlength)}
                                min=${ifDefined(this.min)}
                                max=${ifDefined(this.max)}
                                step=${ifDefined(this.step as number)}
                                .value=${this.value}
                                autocapitalize=${ifDefined(this.type === 'password' ? 'off' : this.autocapitalize)}
                                autocomplete=${ifDefined(this.autocomplete)}
                                autocorrect="off"
                                ?autofocus=${this.autofocus}
                                spellcheck=${this.spellcheck}
                                pattern=${ifDefined(this.pattern)}
                                enterkeyhint=${ifDefined(this.enterkeyhint)}
                                inputmode=${ifDefined(this.inputmode)}
                                aria-describedby="help-text"
                                @change=${this.handleChange}
                                @input=${this.handleInput}
                                @invalid=${this.handleInvalid}
                                @keydown=${this.handleKeyDown}
                                @focus=${this.handleFocus}
                                @blur=${this.handleBlur}
                        />
                        ${
                                hasClearIcon
                                ? html`
                                    <button
                                            part="clear-button"
                                            class="input__clear"
                                            type="button"
                                            aria-label=${this.localize.term('clearEntry')}
                                            @click=${this.handleClearClick}
                                            tabindex="-1"
                                    >
                                        <slot name="clear-icon">
                                            <sl-icon name="x-circle-fill" library="system"></sl-icon>
                                        </slot>
                                    </button>
                                `
                                : ''
                        }
                        ${
                                this.passwordToggle && !this.disabled
                                ? html`
                                    <button
                                            part="password-toggle-button"
                                            class="input__password-toggle"
                                            type="button"
                                            aria-label=${this.localize.term(this.passwordVisible ? 'hidePassword' : 'showPassword')}
                                            @click=${this.handlePasswordToggle}
                                            tabindex="-1"
                                    >
                                        ${this.passwordVisible
                                          ? html`
                                                    <slot name="show-password-icon">
                                                        <sl-icon name="eye-slash" library="system"></sl-icon>
                                                    </slot>
                                                `
                                          : html`
                                                    <slot name="hide-password-icon">
                                                        <sl-icon name="eye" library="system"></sl-icon>
                                                    </slot>
                                                `}
                                    </button>
                                `
                                : ''
                        }
                        <slot name="suffix" part="suffix" class="input__suffix"></slot>
                    </div>
                </div>
                <slot
                        name="help-text"
                        part="form-control-help-text"
                        id="help-text"
                        class="form-control__help-text"
                        aria-hidden=${hasHelpText ? 'false' : 'true'}
                >
                    ${this.helpText}
                </slot>
            </div>
            </div>
		`;
	}

	handleFocus(e : FocusEvent)
	{
		if(!this.readonly)
		{
			this.shadowRoot.querySelector("input[type='password']").removeAttribute("readonly");
		}
		super.handleFocus(e);
	}

}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-password", Et2Password);