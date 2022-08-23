/**
 * EGroupware eTemplate2 - Button that's just an image
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlIconButton} from "@shoelace-style/shoelace";
import {ButtonMixin} from "./ButtonMixin";
import shoelace from "../Styles/shoelace";
import {css} from "@lion/core";


export class Et2ButtonIcon extends ButtonMixin(Et2InputWidget(SlIconButton))
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
            :host {
                flex: 0 0 auto !important;			
			}
            `,
		];
	}

	set image(new_image : string)
	{
		let oldValue = this.__src;
		if(new_image.indexOf("http") >= 0 || new_image.indexOf(this.egw().webserverUrl) >= 0)
		{
			this.src = new_image
		}
		else
		{
			this.src = this.egw().image(new_image);
		}
		if(new_image && !this.src)
		{
			this.name = new_image;
		}
	}

	get image()
	{
		return this.src || this.name;
	}
}

customElements.define("et2-button-icon", Et2ButtonIcon);