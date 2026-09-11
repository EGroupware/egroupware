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
			// Use the clicked widget, not the template widget that created this handler.
			attrs.onclick = function(event)
			{
				const widget = event?.currentTarget || this;
				Et2UrlPhone.action(widget.value);
			}
		}
		super.transformAttributes(attrs);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-phone_ro", Et2UrlPhoneReadonly);
