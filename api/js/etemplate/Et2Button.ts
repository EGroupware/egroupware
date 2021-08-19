/**
 * EGroupware eTemplate2 - Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html} from "../../../node_modules/@lion/core/index.js";
import {LionButton} from "../../../node_modules/@lion/button/index.js";
import {Et2InputWidget} from "./et2_core_inputWidget";
import {Et2Widget} from "./Et2Widget";

export class Et2Button extends Et2InputWidget(Et2Widget(LionButton))
{
	protected _created_icon_node : HTMLImageElement;
	protected clicked : boolean = false;
	private image : string;

	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
                padding: 1px 8px;
                /* These should probably come from somewhere else */
                border-radius: 3px;
                background-color: #e6e6e6;
            }
            :host([readonly]) {
            	display: none;
            }
            /* Set size for icon */
            ::slotted([slot="icon"]) {
                width: 20px;
                padding-right: 3px;
            }`,
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			image: {type: String}
		}
	}

	constructor()
	{
		super();

		// Property default values
		this.image = '';

		// Create icon Element since BXButton puts it as child, but we put it as attribute
		this._created_icon_node = document.createElement("img");
		this._created_icon_node.slot = "icon";
		// Do not add this._icon here, no children can be added in constructor

		// Define a default click handler
		// If a different one gets set via attribute, it will be used instead
		this.onclick = (typeof this.onclick === "function") ? this.onclick : () =>
		{
			return this.getInstanceManager().submit();
		};
	}

	connectedCallback()
	{
		super.connectedCallback();

		//this.classList.add("et2_button")

		if(this.image)
		{
			this._created_icon_node.src = egw.image(this.image);
			this.appendChild(this._created_icon_node);
		}
	}


	_handleClick(event : MouseEvent) : boolean
	{
		debugger;
		// ignore click on readonly button
		if(this.disabled || this.readonly) return false;

		this.clicked = true;

		// Cancel buttons don't trigger the close confirmation prompt
		if(this.classList.contains("et2_button_cancel"))
		{
			this.getInstanceManager()?.skip_close_prompt();
		}

		if(!super._handleClick(event))
		{
			this.clicked = false;
			return false;
		}

		this.clicked = false;
		this.getInstanceManager()?.skip_close_prompt(false);
		return true;
	}

	render()
	{
		if(this.readonly) return '';

		return html`
            <div class="button-content et2_button" id="${this._buttonId}">
                <slot name="icon"></slot>
                <slot></slot>
            </div> `;
	}

	/**
	 * Implementation of the et2_IInput interface
	 */

	/**
	 * Always return false as a button is never dirty
	 */
	isDirty()
	{
		return false;
	}

	resetDirty()
	{
	}

	getValue()
	{
		if(this.clicked)
		{
			return true;
		}

		// If "null" is returned, the result is not added to the submitted
		// array.
		return null;
	}
}

customElements.define("et2-button", Et2Button);
