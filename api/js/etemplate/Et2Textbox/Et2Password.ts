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
import {html} from "lit";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {ifDefined} from "lit/directives/if-defined.js";
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

	/**
	 * Password is plaintext
	 */
	@property({type: Boolean})
	plaintext = true;

	/**
	 * Suggest password length (0 for off)
	 */
	@property({type: Number})
	suggest = 0;

	constructor(...args : any[])
	{
		super(...args);

		this._invokerLabel = '';
		this._invokerTitle = this.egw().lang("Suggest password");
		this._invokerAction = () =>
		{
			this.suggestPassword();
		};
	}
	
	/**
	 * Add a button that switches the field to plain text, for a password the user is allowed to
	 * read back.
	 *
	 * This is the name to use.  The server decides whether to send the password to the client at
	 * all from the template's own attributes, and it looks for this one (see
	 * \EGroupware\Api\Etemplate\Widget\Password::beforeSendToClient()) - a field the client
	 * shows a reveal button for, but the server masked, reveals nothing but asterisks.  It is also
	 * what every password customfield sets, defaulting to true.
	 *
	 * Implemented on top of SlInput's passwordToggle, which render() reads.
	 */
	@property({type: Boolean, attribute: "viewable"})
	get viewable() : boolean
	{
		return this.passwordToggle;
	}

	set viewable(viewable : boolean)
	{
		this.passwordToggle = viewable;
	}

	/**
	 * @deprecated use viewable, which means exactly this.  Kept because templates outside this
	 * repository use it, and the server accepts it as well.
	 */
	get togglePassword() : boolean
	{
		return this.viewable;
	}

	set togglePassword(toggle : boolean)
	{
		this.viewable = toggle;
	}

	transformAttributes(attrs)
	{
		if(typeof attrs.suggest !== "undefined")
		{
			attrs.suggest = parseInt(attrs.suggest);
		}
		// This is the only place the type gets set, so a hand-written <et2-password> (one not built
		// from a template, eg. in documentation) needs its own type="password" or it renders as a
		// plain text input showing the value.  Every password field in the product comes from a
		// template, so this is a documentation concern, not a product one.
		attrs.type = 'password';

		if(typeof attrs.togglePassword !== "undefined")
		{
			attrs.viewable = attrs.togglePassword;
			delete attrs.togglePassword;
		}
		// Shoelace's own name for this.  It works in plain HTML, where Lit maps the attribute
		// itself, but a template must not use it: the server does not know the name, so it would
		// mask the password while we rendered a reveal button for it.
		delete attrs.passwordToggle;

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
			invokerNode.disabled = this.disabled || this.readonly;
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
	 * Anything the user types is theirs, not the ciphertext the server handed us.
	 *
	 * Without this the field would still consider itself encrypted, so revealing a password just
	 * typed (or one we suggested and the user then edited) would demand the login password and
	 * then ask the server to decrypt something it never issued - which it refuses.
	 */
	handleInput()
	{
		this.encrypted = false;

		super.handleInput();
	}

	/**
	 * If the password is viewable, toggle the visibility.
	 * If the password is still encrypted, we'll ask for the user's password then have the server decrypt it.
	 */
	handlePasswordToggle()
	{
		// Hiding, or a value we may show anyway: nothing to authenticate for
		if(this.visible || !this.encrypted || !this.value || this.plaintext)
		{
			this._setVisible(!this.visible);
			return;
		}

		// Need username & password to decrypt.  The field stays masked until the server answers:
		// unmasking first would put the value on screen behind the still-open dialog, and for a
		// password the user typed or we suggested in this session that value IS the password.
		const prompt = Et2Dialog.show_prompt(
			(button, user_password) =>
			{
				if(button == Et2Dialog.CANCEL_BUTTON)
				{
					return;
				}
				this.egw().request(
					"EGroupware\\Api\\Etemplate\\Widget\\Password::ajax_decrypt",
					[user_password, this.value, this.getInstanceManager().etemplate_exec_id]).then(decrypted =>
				{
					if (decrypted)
					{
						this.encrypted = false;
						this.value = decrypted;
						this._setVisible(true);
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

		// prompt.xet asks for a plain textbox, which every other caller wants, so the login
		// password the user is about to type would be on screen in clear.  Nothing about the
		// prompt is configurable, so we change the input once the dialog's template is there.
		prompt.getUpdateComplete().then(() =>
		{
			const value = prompt.eTemplate?.widgetContainer?.getWidgetById("value");
			if(value)
			{
				value.type = "password";
			}
		});
	}

	/**
	 * Mask or unmask the field.
	 *
	 * SlInput keeps the visibility its render() reads in a state of its own and only ever flips it,
	 * so this has to be called exactly once per real change - and never before we know the user is
	 * allowed to see the value.
	 *
	 * @param visible
	 */
	private _setVisible(visible : boolean)
	{
		if(visible == this.visible)
		{
			return;
		}
		super.handlePasswordToggle();
		this.visible = visible;
		this.type = visible ? 'text' : 'password';
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
			this.shadowRoot.querySelector("input").removeAttribute("readonly");
		}
		super.handleFocus(e);
	}

}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-password", Et2Password);