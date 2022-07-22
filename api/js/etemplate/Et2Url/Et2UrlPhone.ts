/**
 * EGroupware eTemplate2 - Phone input widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2InvokerMixin} from "./Et2InvokerMixin";
import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {colorsDefStyles} from "../Styles/colorsDefStyles";
import {css} from "@lion/core";

/**
 * @customElement et2-url-phone
 */
export class Et2UrlPhone extends Et2InvokerMixin(Et2Textbox)
{
	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			css`
				::slotted([slot="suffix"]) {
					font-size: 133% !important;
					height: auto;
				}
			`,
		];
	}

	constructor(...args : any[])
	{
		super(...args);

		//this.defaultValidators.push(...);
		this._invokerLabel = '✆';
		this._invokerTitle = 'Call';
		this._invokerAction = () => {
			Et2UrlPhone.action(this.value);
		}
	}

	static action(value)
	{
		// Clean number
		value = value.replace('&#9829;','').replace('(0)','');
		value = value.replace(/[abc]/gi,2).replace(/[def]/gi,3).replace(/[ghi]/gi,4).replace(/[jkl]/gi,5).replace(/[mno]/gi,6);
		value = value.replace(/[pqrs]/gi,7).replace(/[tuv]/gi,8).replace(/[wxyz]/gi,9);
		// remove everything but numbers and plus, as telephon software might not like it
		value = value.replace(/[^0-9+]/g, '');

		if (!value) return;	// don't try to dial an empty number

		// mobile Webkit (iPhone, Android) have precedence over server configuration!
		if (navigator.userAgent.indexOf('AppleWebKit') !== -1 &&
			(navigator.userAgent.indexOf("iPhone") !== -1 || navigator.userAgent.indexOf("Android") !== -1))
		{
			window.open("tel:"+value);
		}
		else if (egw.config("call_link"))
		{
			var link = egw.config("call_link")
				// tel: links use no URL encoding according to rfc3966 section-5.1.4
				.replace("%1", egw.config("call_link").substr(0, 4) == 'tel:' ?
					value : encodeURIComponent(value))
				.replace("%u",egw.user('account_lid'))
				.replace("%t",egw.user('account_phone'));
			var popup = egw.config("call_popup");
			if (popup && popup !== '_self' || !link.match(/^https?:/))	// execute non-http(s) links eg. tel: like before
			{
				egw.open_link(link, '_phonecall', popup);
			}
			else
			{
				// No popup, use AJAX.  We don't care about the response.
				window.fetch(link, {
					headers: { 'Content-Type': 'application/json'},
					method: "GET",
				});
			}
		}
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-phone", Et2UrlPhone);