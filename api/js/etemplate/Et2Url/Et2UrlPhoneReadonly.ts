/**
 * EGroupware eTemplate2 - Phone url/call widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2UrlPhone} from "./Et2UrlPhone";
import {Et2UrlReadonly} from "./Et2UrlReadonly";

/**
 * @customElement et2-url-phone_ro
 */
export class Et2UrlPhoneReadonly extends Et2UrlReadonly
{
	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			attrs.onclick = () =>
			{
				Et2UrlPhone.action(this.value);
			}
		}
		super.transformAttributes(attrs);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-phone_ro", Et2UrlPhoneReadonly);