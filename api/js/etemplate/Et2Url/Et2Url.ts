/**
 * EGroupware eTemplate2 - Url input widget
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

/**
 * @customElement et2-url
 *
 * @ToDo: implement allow_path and trailing_slash attributes
 */
export class Et2Url extends Et2InvokerMixin(Et2Textbox)
{
	/** @type {any} */
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Allow a path instead of a URL, path must start with /, default false = not allowed
			 */
			allow_path: {
				type: Boolean,
			},
			/**
			 * Require (or forbid) that the path ends with a /, default not checked
			 */
			trailing_slash: {
				type: Boolean,
			},
		};
	}

	constructor()
	{
		super();
		this.defaultValidators.push(new IsEmail());
		this._invokerLabel = '⎆';
		this._invokerTitle = 'Open';
		this._invokerAction = () => {
			Et2Url.action(this.value);
		}
		this.allow_path = false;
		this.trailing_slash = undefined;
	}

	static action(value)
	{
		if (!value) return;
		// implicit add http:// if no protocol given
		if(value.indexOf("://") == -1) value = "http://"+value;
		egw.open_link(value, '_blank');
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url", Et2Url);