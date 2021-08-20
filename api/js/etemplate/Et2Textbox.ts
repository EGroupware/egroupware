/**
 * EGroupware eTemplate2 - Textbox widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "../../../node_modules/@lion/core/index.js"
import {LionInput} from "../../../node_modules/@lion/input/index.js"
import {Et2InputWidget} from "./et2_core_inputWidget";
import {Et2Widget} from "./Et2Widget";

export class Et2Textbox extends Et2InputWidget(Et2Widget(LionInput))
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

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
	}
}

customElements.define("et2-textbox", Et2Textbox);
