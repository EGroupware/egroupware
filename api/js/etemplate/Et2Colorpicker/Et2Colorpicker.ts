/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */



import {html} from "@lion/core";
import {LionInput} from "@lion/input";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";

export class Et2Colorpicker extends Et2InputWidget(Et2Widget(LionInput))
{

	constructor()
	{
		super();
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	render()
	{
		return html`
			<div class="et2_colorpicker" id="${this.id}">
            	<input class="et2_colorpicker" type="color" />
			</div>
		`;
	}
}
customElements.define('et2-colorpicker', Et2Colorpicker);