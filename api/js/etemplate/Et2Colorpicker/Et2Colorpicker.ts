/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {html, PropertyValues, render} from "lit";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlColorPicker} from "@shoelace-style/shoelace";
import shoelace from "../Styles/shoelace";

import styles from "./Et2Colorpicker.styles";
export class Et2Colorpicker extends Et2InputWidget(SlColorPicker)
{
	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			styles,
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
		// our clear button is rendered into SlColorPicker's own trigger button (see firstUpdated),
		// so the trigger's click handler sits on the very same element - stopPropagation() would
		// not keep it from opening the dropdown, only stopImmediatePropagation() does
		e.stopImmediatePropagation();
		this.value = "";

		// Shoelace emits sl-change only for its own user-interaction, never for a programmatic value
		// change - so emit it ourselves, otherwise nothing observes the clear:
		// onchange handlers and Et2InputWidget's sl-change -> change bridge both stay silent.
		// Same pattern as Et2TreeDropdown.handleClearClick(), minus the sl-clear that SlColorPicker
		// does not document as one of its events.
		this.updateComplete.then(() =>
		{
			this.emit('sl-input');
			this.emit('sl-change');
		});
	}
}
customElements.define('et2-colorpicker', Et2Colorpicker);