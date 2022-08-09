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
import {Et2Textbox} from "../Et2Textbox/Et2Textbox";
import {colorsDefStyles} from "../Styles/colorsDefStyles";
import {css} from "@lion/core";
import {egw} from "../../jsapi/egw_global";

/**
 * @customElement et2-url
 *
 * @ToDo: implement allowPath attributes
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
			allowPath: {
				type: Boolean,
			},
			/**
			 * Require (or forbid) that the path ends with a /, default not checked
			 */
			trailingSlash: {
				type: Boolean,
			},
		};
	}

	static get styles()
	{
		return [
			...super.styles,
			colorsDefStyles,
			css`
				::slotted([slot="suffix"]) {
					font-size: 133% !important;
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

		this._invokerLabel = '⎆';
		this._invokerTitle = 'Open';
		this._invokerAction = () => {
			Et2Url.action(this.value);
		}
		this.allowPath = false;
		this.trailingSlash = undefined;
	}

	/**
	 * Change handler calling custom handler set via onchange attribute
	 *
	 * Reimplemented to add/remove trailing slash depending on trailingSlash attribute
	 *
	 * @param _ev
	 * @returns
	 */
	_oldChange(_ev: Event): boolean
	{
		const value = this.modelValue;
		if (typeof this.trailingSlash !== 'undefined' && value && this.trailingSlash !== (value.substr(-1)==='/'))
		{
			if (!this.trailingSlash)
			{
				this.modelValue = value.replace(/\/+$/, '');
			}
			else
			{
				this.modelValue += '/';
			}
		}
		return super._oldChange(_ev);
	}

	static action(value)
	{
		if (!value) return;
		// implicit add http:// if no protocol given
		if(value.indexOf("://") === -1) value = "http://"+value;
		// as this is static, we can NOT use this.egw(), but global egw
		egw.open_link(value, '_blank');
	}
}
// @ts-ignore TypeScript is not recognizing that this is a LitElement
customElements.define("et2-url", Et2Url);