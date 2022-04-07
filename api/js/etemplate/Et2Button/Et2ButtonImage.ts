/**
 * EGroupware eTemplate2 - Image only button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Button} from "./Et2Button";
import {css} from "@lion/core";

export class Et2ButtonImage extends Et2Button
{

	public static styles = [
		...Et2Button.styles,
		css`
		:host {
			/* Important needed to override boxes trying to stretch children */
			flex: 0 0 !important;
		}
		`
	];

	/**
	 * Handle changes that have to happen based on changes to properties
	 *
	 */
	requestUpdate(name : PropertyKey, oldValue)
	{
		super.requestUpdate(name, oldValue);

		// image only don't have labels, but we'll set hover instead
		if(name == 'label')
		{
			if(!this.statustext)
			{
				this.statustext = this.label;
			}
			this.__label = "";
		}
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Button is a LitElement
customElements.define("et2-button-image", Et2ButtonImage);