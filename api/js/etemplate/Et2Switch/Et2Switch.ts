/**
 * EGroupware eTemplate2 - Switch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {css, html, SlotMixin} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlSwitch} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

/**
 * Switch to turn on or off.  Like a checkbox, but different UI.
 *
 * Add "et2SlideSwitch" class to use an alternate UI with images.  Use CSS to set the images:
 *
 */
export class Et2Switch extends Et2InputWidget(SlotMixin(SlSwitch))
{
	static get styles()
	{
		return [
			...shoelace,
			...super.styles,
			css`
			  :host {
				/* Make it line up with the middle of surroundings */
				margin: auto 0px;
				vertical-align: -webkit-baseline-middle;
			  }

			  .switch {
				position: relative;
			  }

			  .toggle__label {
				position: absolute;
				left: 0px;
				border-radius: 50%;
				flex: 0 0 auto;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: var(--width);
				height: var(--height);
				margin: 0px;
			  }

			  .switch__thumb {
				z-index: var(--sl-z-index-tooltip);
			  }

			  ::slotted(span.label) {
				width: var(--width);
				display: inline-flex;
				align-items: center;
				height: var(--height);
			  }

			  /* 
			  Use two images instead of normal switch by adding et2_image_switch class
			  see etemplate.css for the rest (slotted label)
			   */

			  :host(.et2SlideSwitch) .switch {
				min-width: 60px;
				--height: var(--sl-input-height-medium);
				border-color: var(--sl-input-border-color);
				border-width: var(--sl-input-border-width);
				border-radius: var(--sl-border-radius-medium);
				border-style: solid;
			  }

			  :host(.et2SlideSwitch) .switch__control {
				visibility: hidden;
			  }

			  :host(.et2SlideSwitch) .switch__label {
				width: 100%;
				height: 100%;
			  }

			  :host(.et2SlideSwitch) ::slotted(.label) {
				flex: 1 1 auto;
			  }
			`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/* label to show when the toggle switch is on */
			toggleOn: {type: String},
			/* label to show when the toggle switch is off */
			toggleOff: {type: String}
		}
	}

	get slots()
	{
		return {
			...super.slots,
			'': () =>
			{
				return this.labelTemplate();
			}
		}
	}

	constructor()
	{
		super();
		this.isSlComponent = true;
		this.toggleOn = '';
		this.toggleOff = '';
	}

	updated(changedProperties)
	{
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
		if(this.toggleOn || this.toggleOf)
		{
			if(new_value)
			{
				this._labelNode?.classList.add('on');
				this.checked = true;
			}
			else
			{
				this._labelNode?.classList.remove('on');
				this.checked = false;
			}
		}
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
		return html`
            <span class="label" aria-label="${this.label}">
				<span class="on">${this.toggleOn}</span>
				<span class="off">${this.toggleOff}</span>
			</span>
		`;
	}
}

customElements.define("et2-switch", Et2Switch);