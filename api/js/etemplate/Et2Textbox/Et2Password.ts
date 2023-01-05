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

		if (attrs.viewable)
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
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-password", Et2Password);