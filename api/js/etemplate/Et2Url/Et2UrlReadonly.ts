/**
 * EGroupware eTemplate2 - Url r/o widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {IsEmail} from "../Validators/IsEmail";
import {Et2Description} from "../Et2Description/Et2Description";
import {Et2UrlEmail} from "./Et2UrlEmail";
import {css} from "@lion/core";
import {Et2Url} from "./Et2Url";

/**
 * @customElement et2-url_ro
 */
export class Et2UrlReadonly extends Et2Description
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			:host {
				cursor: pointer;
				color: #26537c;
			}`
		];
	}

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			attrs.onclick = () =>
			{
				if (this.value)
				{
					Et2Url.action(this.value);
				}
			}
		}
		super.transformAttributes(attrs);
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url_ro", Et2UrlReadonly);