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
	// Keep the raw email separate from _value. _value is the rendered text and may
	// become just a name/domain after async formatting, while clicks and
	// datagrid row updates still need the current actual address.
	protected _emailValue : string = "";

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
		const raw = val || "";
		this._emailValue = raw;
		const split = splitEmail(raw);
		const emailDisplay = !this.emailDisplay ? "email" :
		                     (this.emailDisplay === 'preference' ? null : this.emailDisplay);
		super.statustext = split.email && (split.name || emailDisplay !== "email") ? split.email : "";
		const fallback = emailDisplay === "email" ? split.email : raw;
		super.value = fallback;
		formatEmailAddress(raw, emailDisplay).then(
			(value) =>
			{
				if(this._emailValue === raw)
				{
					super.value = value || fallback;
				}
			},
			() =>
			{
				if(this._emailValue === raw)
				{
					super.value = fallback;
				}
			});
	}

	get value()
	{
		return super.value;
	}

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			// Use the clicked widget, not the template widget that created this handler.
			attrs.onclick = function(event)
			{
				const widget = event?.currentTarget || this;
				let email = widget._emailValue || widget._value;
				if (!IsEmail.EMAIL_PREG.exec(email))
				{
					let name = widget._emailValue || widget._value;
					// do we need to remove the domain in brackets again?
					if ((widget.emailDisplay === 'preference' ? window.egw.preference("emailTag", "mail") : widget.emailDisplay) === 'domain')
					{
						name = (widget._emailValue || widget._value).replace(/ \([^@. ]+\.[^@ )]+\)$/, '');
					}
					email = '"' + name + '" <' + widget.statustext + '>'
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
