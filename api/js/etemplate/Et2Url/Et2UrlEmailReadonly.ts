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
import type {PropertyValues} from "lit";
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

	/**
	 * Suppress the default click-to-compose action entirely (ticket #126151: a mail-list row's
	 * own sender/address chip must only ever select/open the row for reading, never intercept a
	 * plain click/tap to mailto:-open a compose - "you cannot teach users to tap only in specific
	 * places"). Checked INSIDE the default onclick handler below, at actual click time, not used
	 * to decide (in transformAttributes()) whether to install that handler in the first place -
	 * for a widget living inside a nextmatch row template, transformAttributes() can run before
	 * this property's own XET-attribute value has been applied yet (confirmed live: a row's widget
	 * already had disableClickAction=true by the time it was inspected, but still carried the
	 * default onclick - transformAttributes() had already made its install-or-not decision using a
	 * still-default `false` moments earlier). A click, by contrast, can only ever happen once the
	 * widget's full attribute/property application has long since finished.
	 */
	@property({type: Boolean})
	disableClickAction : boolean = false;

	set value(val : string)
	{
		const raw = val || "";
		this._emailValue = raw;
		const split = splitEmail(raw);
		const emailDisplay = !this.emailDisplay ? "email" :
		                     (this.emailDisplay === 'preference' ? null : this.emailDisplay);
		super.statustext = split.email && (split.name || emailDisplay !== "email") ? split.email : (this.statustext??"");
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

	/**
	 * Ticket #126151, part 2: having ANY onclick installed (even one that no-ops via
	 * disableClickAction above) makes Et2Widget's own willUpdate() add the "et2_clickable" CSS
	 * class (api/js/etemplate/Et2Widget/Et2Widget.ts) - and a nextmatch row click landing on a
	 * ".et2_clickable" descendant is explicitly treated as "an interactive widget's own click,
	 * not a row click" by Et2Datagrid._isInteractiveRowEventTarget(), which then skips the row's
	 * own select/open-for-reading handling entirely (found live: the compose-popup regression
	 * was gone, but so was row selection and the double-click message-view popup). Clearing
	 * `onclick` itself - not just no-opping its body - removes that class too, via the same
	 * reactive property Et2Widget's own class-toggle already watches. Done here, before calling
	 * super.willUpdate(), so the base class's own `changedProperties.has("onclick")` check sees
	 * it within the SAME update pass (Lit's documented pattern for deriving one reactive property
	 * from another in willUpdate(), https://lit.dev/msg/change-in-update) - this property's own
	 * XET-attribute value is reliably applied by the time any update pass runs, same timing
	 * guarantee as the click-time check in the handler body above, which stays as a defense-in-
	 * depth fallback.
	 */
	willUpdate(changedProperties : PropertyValues)
	{
		if(this.disableClickAction && this.onclick)
		{
			this.onclick = null;
		}
		super.willUpdate(changedProperties);
	}

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			// Use the clicked widget, not the template widget that created this handler.
			attrs.onclick = function(event)
			{
				const widget = event?.currentTarget || this;
				if (widget.disableClickAction) return;
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
