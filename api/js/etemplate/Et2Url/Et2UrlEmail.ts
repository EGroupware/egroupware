/**
 * EGroupware eTemplate2 - Email input widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Ralf Becker
 */

/* eslint-disable import/no-extraneous-dependencies */
import {Et2InvokerMixin} from "./Et2InvokerMixin";
import {IsEmail} from "../Validators/IsEmail";
import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {colorsDefStyles} from "../Styles/colorsDefStyles";
import {css} from "@lion/core";

/**
 * @customElement et2-url-email
 */
export class Et2UrlEmail extends Et2InvokerMixin(Et2Textbox)
{
	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			css`
				::slotted([slot="suffix"]) {
					font-size: 90% !important;
					height: auto;
					width: auto;
				}
			`,
		];
	}

	constructor(...args : any[])
	{
		super(...args);

		this.defaultValidators.push(new IsEmail());
		this._invokerLabel = '@';
		this._invokerTitle = 'Compose mail to';
		this._invokerAction = () =>
		{
			if(this.value.length > 0 && !this.hasFeedbackFor.length)
			{
				Et2UrlEmail.action(this.value);
			}
		}
	}

	static action(value)
	{
		if (value && egw.user('apps').mail && egw.preference('force_mailto','addressbook') != '1' )
		{
			egw.open_link('mailto:'+value);
		}
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url-email", Et2UrlEmail);