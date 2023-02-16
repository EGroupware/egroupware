/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlButton} from "@shoelace-style/shoelace";
import {ButtonMixin} from "./ButtonMixin";
import {PropertyValues} from "@lion/core";


export class Et2Button extends ButtonMixin(Et2InputWidget(SlButton))
{
	static get properties()
	{
		return {
			...super.properties,
			label: {type: String}
		}
	}

	protected firstUpdated(_changedProperties : PropertyValues)
	{
		super.firstUpdated(_changedProperties);

		if(!this.label && this.__image)
		{
			/*
			 Label / no label should get special classes set, but they're missing without this extra requestUpdate()
			 This is a work-around for button--has-prefix & button--has-label not being set, something to do
			 with how we're setting them.
			 */
			this.updateComplete.then(() =>
			{
				this.requestUpdate();
			});
		}
	}

	set label(new_label : string)
	{
		this.updateComplete.then(() =>
		{
			if(!this._labelNode)
			{
				const textNode = document.createTextNode(new_label);
				this.appendChild(textNode);
				// for some reason button doesn't get resized properly without a forced rendereing therefore the
				// requestUpdate is used to trigger a refresh.
				this.requestUpdate();
			}
			else
			{
				this._labelNode.textContent = new_label;
				// for some reason button doesn't get resized properly without a forced rendereingtherefore the
				// requestUpdate is used to trigger a refresh.
				this.requestUpdate();
			}
		});
	}

	get label()
	{
		return this._labelNode?.textContent?.trim();
	}
}

customElements.define("et2-button", Et2Button);