/**
 * EGroupware eTemplate2 - Email url/compose widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {IsEmail} from "../Validators/IsEmail";
import {Et2UrlEmail} from "./Et2UrlEmail";
import {Et2UrlReadonly} from "./Et2UrlReadonly";
import {property} from "lit/decorators/property.js";
import {formatEmailAddress, splitEmail} from "../Et2Email/utils";

/**
 * @customElement et2-url-email_ro
 */
export class Et2UrlEmailReadonly extends Et2UrlReadonly
{
	/**
	 * What to display for the selected email addresses
	 *
	 *  - email: "test@example.com" (default)
	 *	- full: "Mr Test User <test@example.com>
	 *	- name: "Mr Test User"
	 *	- domain: "Mr Test User (example.com)"
	 *  - preference: use the users preference, like et2-email does
	 *
	 * If name is unknown, we'll use the email instead.
	 */
	@property({type: String})
	emailDisplay : "email" | "full" | "name" | "domain" | "preference";

	set value(val : string)
	{
		this._value = val;
		const split = splitEmail(this._value);
		super.statustext = split.name ? split.email : "";
		formatEmailAddress(val, !this.emailDisplay ? "email" :
			(this.emailDisplay === 'preference' ? null : this.emailDisplay)).then(
				(value) => super.value = value);
	}

	get value()
	{
		return super.value;
	}

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			attrs.onclick = () =>
			{
				let email=this._value;
				if (!IsEmail.EMAIL_PREG.exec(email))
				{
					let name = this._value;
					// do we need to remove the domain in brackets again?
					if ((this.emailDisplay === 'preference' ? window.egw.preference("emailTag", "mail") : this.emailDisplay) === 'domain')
					{
						name = this._value.replace(/ \([^@. ]+\.[^@ )]+\)$/, '');
					}
					email = '"' + name + '" <' + this.statustext + '>'
				}
				if (IsEmail.EMAIL_PREG.exec(email))
				{
					Et2UrlEmail.action(email);
				}
			}
		}
		super.transformAttributes(attrs);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-email_ro", Et2UrlEmailReadonly);