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

/**
 * @customElement et2-url-email_ro
 */
export class Et2UrlEmailReadonly extends Et2UrlReadonly
{
	constructor()
	{
		super();
	}

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			attrs.onclick = () =>
			{
				if (IsEmail.EMAIL_PREG.exec(this.value))
				{
					Et2UrlEmail.action(this.value);
				}
			}
		}
		super.transformAttributes(attrs);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-email_ro", Et2UrlEmailReadonly);