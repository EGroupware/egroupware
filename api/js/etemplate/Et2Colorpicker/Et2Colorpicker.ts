/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {css, html, PropertyValues, render} from "@lion/core";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlColorPicker} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

export class Et2Colorpicker extends Et2InputWidget(SlColorPicker)
{
	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			css`
			:host {
				display: flex;
			}
			.input-group__suffix{
				width: 12px;
				height: 12px;
			}
			.input-group__container {
				align-items: center
			}

			.color-dropdown__trigger--empty .input__clear {
				display: none;
			}
			.input__clear {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				font-size: inherit;
				color: var(--sl-input-icon-color);
				border: none;
				background: none;
				padding: 0px;
				transition: var(--sl-transition-fast) color;
				cursor: pointer;
				
				/* Positioning of clear button */
				position: absolute;
				left: var(--sl-input-height-medium);
				top: 0px;
				margin: auto 0px;
				bottom: 0px;

			}
			`,
		];
	}

	constructor()
	{
		super();

		this.hoist = true;
		this.noFormatToggle = true;
		this.uppercase = true;

		// Bind the handlers
		this._handleClickClear = this._handleClickClear.bind(this);
	}

	protected firstUpdated(_changedProperties : PropertyValues)
	{
		super.firstUpdated(_changedProperties);

		// Add in clear button - parent has no accessible slots
		render(this._clearButtonTemplate(), this._buttonNode);
	}

	private get _buttonNode() : HTMLElement
	{
		return this.shadowRoot.querySelector("button[slot='trigger']");
	}

	_clearButtonTemplate()
	{
		return html`
            <button part="clear-button" class="input__clear" type="button" tabindex="-1"
                    aria-label="${this.egw().lang("Clear entry")}"
                    @click=${this._handleClickClear}>
                <slot name="clear-icon">
                    <sl-icon name="x-circle-fill" library="system"></sl-icon>
                </slot>
            </button>
		`;
	}

	_handleClickClear(e)
	{
		e.stopImmediatePropagation();
		this.value = "";

	}
}
customElements.define('et2-colorpicker', Et2Colorpicker);