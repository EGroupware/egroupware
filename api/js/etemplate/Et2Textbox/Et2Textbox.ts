/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "../../../../node_modules/@lion/core/index.js"
import {LionInput} from "../../../../node_modules/@lion/input/index.js"
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

export class Et2Textbox extends Et2InputWidget(LionInput)
{

	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Custom CSS */
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			onkeypress: Function,
		}
	}

	constructor(...args : any[])
	{
		super(...args);
	}

	connectedCallback()
	{
		super.connectedCallback();
	}
}

customElements.define("et2-textbox", Et2Textbox);
