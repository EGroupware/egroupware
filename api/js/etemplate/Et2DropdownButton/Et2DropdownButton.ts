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
import {css, html, repeat, TemplateResult} from "@lion/core";
import {Et2widgetWithSelectMixin} from "../Et2Select/Et2WidgetWithSelectMixin";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {buttonStyles} from "../Et2Button/ButtonStyles";
import shoelace from "../Styles/shoelace";

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
			shoelace,
			buttonStyles,
			css`
            :host {
                display: contents;
            }
            .menu-item  {
				width: --sl-input-height-medium;
				max-height: var(--sl-input-height-medium)
			}
			
           
            `,
		];
	}

	// Make sure imports stay
	private _group : SlButtonGroup;
	private _dropdow : SlDropdown;


	connectedCallback()
	{
		super.connectedCallback();

		// Rebind click to just the button
		this.removeEventListener("click", this._handleClick);
		this.buttonNode.addEventListener("click", this._handleClick);
	}

	render()
	{
		return html`
            <sl-button-group>
                <sl-button size="medium">${this.label}</sl-button>
                <sl-dropdown placement="bottom-end">
                    <sl-button size="medium" slot="trigger" caret></sl-button>
                    <sl-menu>
                        ${repeat(this.select_options, (option : SelectOption) => option.value, option =>
                                this._itemTemplate(option)
                        )}
                    </sl-menu>
                </sl-dropdown>
            </sl-button-group>
		`;
	}

	protected _itemTemplate(option : SelectOption) : TemplateResult
	{
		let icon = option.icon ? html`
            <et2-image slot="prefix" src=${option.icon} icon></et2-image>` : '';

		return html`
            <sl-menu-item value="${option.value}">
                ${icon}
                ${option.label}
            </sl-menu-item>`;
	}

	get buttonNode()
	{
		return this.shadowRoot.querySelector("et2-button");
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Button is a LitElement
customElements.define("et2-dropdown-button", Et2DropdownButton);