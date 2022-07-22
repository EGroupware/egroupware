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
	/** @type {any} */
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Show full email address if true otherwise show only name and put full address as statustext/tooltip
			 */
			fullEmail: {
				type: Boolean,
			},
			/**
			 * Show icon to add email as contact to addressbook
			 * @ToDo
			 */
			contactPlus: {
				type: Boolean,
			},
		};
	}

	set value(val : string)
	{
		this._value = val;
		// check if we have a "name <email>" value and only show name
		if (!this.fullEmail && val && val.indexOf('<') !== -1)
		{
			const parts = val.split('<');
			if (parts[0])
			{
				super.statustext = parts[1].substring(0, parts[1].length-1);
				val = parts[0].trim();
				// remove quotes
				if ((val[0] === '"' || val[0] === "'" ) && val[0] === val.substr(-1))
				{
					val = val.substring(1, val.length-1);
				}
			}
			else	// <email> --> email
			{
				super.statustext = '';
				val = parts[1].substring(0, val.length-1);
			}
		}
		else
		{
			super.statustext = '';
		}
		super.value = val;
	}

	get value()
	{
		return super.value;
	}

	transformAttributes(attrs)
	{
		if (typeof attrs.onclick === 'undefined')
		{
			attrs.onclick = () =>
			{
				let email;
				if (IsEmail.EMAIL_PREG.exec(email=this.value) ||
					IsEmail.EMAIL_PREG.exec(email=this.value+' <'+this.statustext+'>'))
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