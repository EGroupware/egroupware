/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "@lion/core";
import {LionInput} from "@lion/input";
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
						value: {attribute: true},
						onclick: {type: Function}
				}
		}

		constructor()
		{
				debugger;
				super();

		}

		connectedCallback()
		{
				super.connectedCallback();

		}
}

customElements.define("et2-textbox", Et2Textbox);
