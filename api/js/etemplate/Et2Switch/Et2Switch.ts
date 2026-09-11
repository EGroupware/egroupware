/**
 * EGroupware eTemplate2 - Switch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */

import {html, render} from "lit";
import {property} from "lit/decorators/property.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlSwitch} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

import styles from "./Et2Switch.styles";
/**
 * Switch to turn on or off.  Like a checkbox, but different UI.
 *
 * Add "et2SlideSwitch" class to use an alternate UI with images.  Use CSS to set the images:
 *
 */
export class Et2Switch extends Et2InputWidget(SlSwitch)
{
	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			styles,
		];
	}

	/* label to show when the toggle switch is on */
	@property({type: String})
	toggleOn = '';

	/* label to show when the toggle switch is off */
	@property({type: String})
	toggleOff = '';

	constructor()
	{
		super();
		this.isSlComponent = true;
	}

	updated(changedProperties)
	{
		render(this.labelTemplate(), this);
		if(changedProperties.has("toggleOn") || changedProperties.has("toggleOff") || changedProperties.has("label"))
		{
			if(!this.toggleOn && !this.toggleOff && this._labelNode)
			{
				this._labelNode.childNodes.forEach(c => c.remove());
			}
			else
			{
				if(this._labelNode)
				{
					this._labelNode.querySelector('.on').textContent = this.toggleOn;
					this._labelNode.querySelector('.off').textContent = this.toggleOff;
				}
				this.shadowRoot.querySelector('.switch__label').classList.add('toggle__label');
			}
		}
	}

	set value(new_value : string | boolean)
	{
		this.requestUpdate("checked");
		if(this.toggleOn || this.toggleOff)
		{
			if(new_value)
			{
				this._labelNode?.classList.add('on');
			}
			else
			{
				this._labelNode?.classList.remove('on');
			}
		}
		this.checked = !!new_value;
		return;
	}

	get value ()
	{
		return this.checked;
	}

	private get _labelNode()
	{
		return this.querySelector(".label");
	}

	labelTemplate()
	{
		const labelClass = this.checked ? "label on" : "label";
		return html`
            <span class=${labelClass} aria-label="${this.label}">
				<span class="on">${this.toggleOn}</span>
				<span class="off">${this.toggleOff}</span>
			</span>
		`;
	}
}

customElements.define("et2-switch", Et2Switch);