/**
 * EGroupware eTemplate2 - Fax url/send widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2UrlFax} from "./Et2UrlFax";
import {Et2UrlReadonly} from "./Et2UrlReadonly";

/**
 * @customElement et2-url-fax_ro
 */
export class Et2UrlFaxReadonly extends Et2UrlReadonly
{
	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			// Use the clicked widget, not the template widget that created this handler.
			attrs.onclick = function(event)
			{
				const widget = event?.currentTarget || this;
				Et2UrlFax.action(widget.value);
			}
		}
		super.transformAttributes(attrs);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-fax_ro", Et2UrlFaxReadonly);
