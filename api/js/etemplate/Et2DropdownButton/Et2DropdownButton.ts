/**
 * EGroupware eTemplate2 - Dropdown Button widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Button} from "../Et2Button/Et2Button";
import {SlButtonGroup, SlDropdown} from "@shoelace-style/shoelace";
import {css, html, TemplateResult} from "@lion/core";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {SelectOption} from "../Et2Select/FindSelectOptions";

/**
 * A split button - a button with a dropdown list
 *
 * There are several parts to the button UI:
 * - Container: This is what is percieved as the dropdown button, the whole package together
 *   - Button: The part on the left that can be clicked
 *   - Arrow: The button to display the choices
 *   - Menu: The list of choices
 *
 * Menu options are passed via the select_options.  They are normally the same
 * as for a select box, but the title can also be full HTML if needed.
 *
 */
export class Et2DropdownButton extends Et2widgetWithSelectMixin(Et2Button)
{

	static get styles()
	{
		return [
			...super.styles,
			css`
            :host {
            	/* Avoid unwanted style overlap from button */
            	border: none;
            	background-color: none;
            }
            :host, sl-menu {
                /**
                Adapt shoelace color variables to what we want 
                Maybe some logical variables from etemplate2.css here? 
                */
				--sl-color-primary-50: rgb(244, 246, 247);
				--sl-color-primary-100: var(--gray-10);
				--sl-color-primary-300: var(--input-border-color);
				--sl-color-primary-400: var(--input-border-color);
				--sl-color-primary-600: var(--primary-background-color);
				--sl-color-primary-700: #505050;
            }
            :host(:active), :host([active]) {
            	background-color: initial;
            }
            sl-button-group {
            	display: initial;
            }
            #main {
            	flex: 1 1 auto;
            }
            `,
		];
	}

	static get properties()
	{
		return {
			...super.properties
		};
	}

	// Make sure imports stay
	private _group : SlButtonGroup;
	private _dropdow : SlDropdown;

	constructor()
	{
		super();

		// Bind handlers - parent already got click
		this._handleSelect = this._handleSelect.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Rebind click to just the main button, not the whole thing
		this.removeEventListener("click", this._handleClick);

		// Need to wait until update is done and these exist
		this.updateComplete.then(() =>
		{
			if(this.buttonNode)
			{
				this.buttonNode.addEventListener("click", this._handleClick);
			}
			if(this.dropdownNode)
			{
				this.dropdownNode.addEventListener('sl-select', this._handleSelect);
			}
		});
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		if(this.buttonNode)
		{
			this.buttonNode.removeEventListener("click", this._handleClick);
		}
		if(this.dropdownNode)
		{
			this.dropdownNode.removeEventListener('sl-select', this._handleSelect);
		}
	}

	render()
	{
		if(this.readonly)
		{
			return '';
		}
		return html`
            <sl-button-group>
                <sl-button size="${egwIsMobile() ? "large" : "medium"}" id="main" ?disabled=${this.disabled}>
                    ${this.label}
                </sl-button>
                <sl-dropdown placement="bottom-end" hoist>
                    <sl-button size="${egwIsMobile() ? "large" : "medium"}" slot="trigger" caret
                               ?disabled=${this.disabled}></sl-button>
                    <sl-menu>
                    </sl-menu>
                </sl-dropdown>
            </sl-button-group>
		`;
	}

	_optionTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" src=${option.icon} icon></et2-image>` : '';

		return html`
            <sl-menu-item value="${option.value}">
                ${icon}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-menu-item>`;
	}

	protected _handleSelect(ev)
	{
		this._value = ev.detail.item.value;

		// Trigger a change event
		this.dispatchEvent(new Event("change"));

		// Let it bubble, if anyone else is interested
	}

	get value() : string
	{
		return this._value;
	}

	set value(new_value)
	{
		let oldValue = this.value;
		this._value = new_value;
		this.requestUpdate("value", oldValue);
	}

	get _optionTargetNode() : HTMLElement
	{
		return this.shadowRoot.querySelector("sl-menu");
	}

	get buttonNode()
	{
		return this.shadowRoot.querySelector("#main");
	}

	get triggerButtonNode()
	{
		return this.shadowRoot.querySelector("[slot='trigger']");
	}

	get dropdownNode()
	{
		return this.shadowRoot.querySelector("sl-dropdown");
	}

	blur()
	{
		this.shadowRoot.querySelector("sl-button-group")?.dispatchEvent(new Event('blur'));
	}

	focus()
	{
		this.shadowRoot.querySelector("sl-button-group")?.dispatchEvent(new Event('focus'));
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Button is a LitElement
customElements.define("et2-dropdown-button", Et2DropdownButton);