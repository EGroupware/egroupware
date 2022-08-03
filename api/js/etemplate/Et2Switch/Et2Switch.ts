/**
 * EGroupware eTemplate2 - Switch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {css, html, PropertyValues, SlotMixin} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import '../Et2Image/Et2Image';
import {SlSwitch} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

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
			::slotted(span.label)
			 {
				width: var(--width);
				display: inline-flex;
				height: var(--height);
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

	firstUpdated()
	{
		if (!this.toggleOn && !this.toggleOff)
		{
			this._labelNode.children().remove();
		}
		else
		{
			this._labelNode.querySelector('.on').textContent = this.toggleOn;
			this._labelNode.querySelector('.off').textContent = this.toggleOff;
			this.shadowRoot.querySelector('.switch__label').classList.add('toggle__label');
		}
	}

	connectedCallback()
	{
		super.connectedCallback();

	}

	get label()
	{
		return this._labelNode?.textContent || "";
	}

	set label(label_text)
	{
		if(this._labelNode)
		{
			this._labelNode.textContent = label_text;
		}
		else
		{
			this.updateComplete.then(() =>
			{
				this.label = label_text;
			})
		}
	}


	set value(new_value : string | boolean)
	{
		this.requestUpdate("checked");
		if (this.toggleOn || this.toggleOf)
		{
			if (new_value)
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
			<span class="label">
				<span class="on"></span>
				<span class="off"></span>
			</span>
		`;
	}
}

customElements.define("et2-switch", Et2Switch);