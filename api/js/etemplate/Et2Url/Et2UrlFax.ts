/**
 * EGroupware eTemplate2 - Fax input widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2UrlPhone} from "./Et2UrlPhone";
import {Et2UrlEmail} from "./Et2UrlEmail";
import {colorsDefStyles} from "../Styles/colorsDefStyles";
import {css} from "@lion/core";

/**
 * @customElement et2-url-phone
 */
export class Et2UrlFax extends Et2UrlPhone
{
	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			css`
				::slotted([slot="suffix"]) {
					font-size: 90% !important;
					position: relative;
					height: auto;
					width: auto;
				}
			`,
		];
	}

	constructor(...args : any[])
	{
		super(...args);

		//this.defaultValidators.push(...);
		this._invokerLabel = '📠';
		this._invokerTitle = 'Send';
		this._invokerAction = () => {
			Et2UrlFax.action(this.value);
		}
	}

	static action(value)
	{
		// convert fax numbers to email, if configured
		if (egw.config('fax_email') && (value = value.replace('&#9829;','').replace('(0)','').replace(/[^0-9+]/g, '')))
		{
			value = value.replace(new RegExp(egw.config('fax_email_regexp')||'(.*)'),
				egw.config('fax_email'));
			Et2UrlEmail.action(value);
		}
		else
		{
			Et2UrlPhone.action(value);
		}
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-fax", Et2UrlFax);