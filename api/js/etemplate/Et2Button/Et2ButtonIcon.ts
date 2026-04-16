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
import {css} from "lit";


export class Et2ButtonIcon extends ButtonMixin(Et2InputWidget(SlIconButton))
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
            :host {
				color: inherit;
                flex: 0 0 auto !important;			
			}
            `,
		];
	}

	private __image;

	set image(new_image : string)
	{
		let oldValue = this.__image;
		if(new_image.indexOf("http") >= 0 || new_image.indexOf(this.egw().webserverUrl) >= 0)
		{
			this.src = new_image
		}
		else
		{
			//just set the name of the SlIcon. We registered an icon library that resolves the name to egw.image(name)
			//this gets rid of timing issues we have, e.g. in the toolbar
			this.name = new_image;
		}
		this.__image = new_image;
		this.requestUpdate('image', oldValue);

		// For some reason setting it directly does not show the image
		/*
		this.updateComplete.then(() =>
		{
			const icon = this.shadowRoot.querySelector('sl-icon');
			icon.id = "";
			if(new_image && !this.src)
			{
				icon.src = "";
				icon.name = new_image;
			}
			else
			{
				icon.name = "";
			}
		});

		 */
	}

	get image()
	{
		return this.__image || this.name;
	}

	protected get _iconNode() : HTMLElement & { src?: string }
	{
		// SlIconButton already renders an internal sl-icon; reuse it so ButtonMixin
		// does not inject a second et2-image into the prefix slot.
		return this.shadowRoot?.querySelector("sl-icon");
	}
}

customElements.define("et2-button-icon", Et2ButtonIcon);
