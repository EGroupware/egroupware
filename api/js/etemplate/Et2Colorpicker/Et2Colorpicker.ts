/**
 * EGroupware eTemplate2 - Colorpicker widget (WebComponent)
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Hadi Nategh
 */


import {html, nothing, PropertyValues, render} from "lit";
import {classMap} from "lit/directives/class-map.js";
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

		// Add in clear button - parent has no accessible slots, so it goes into the trigger button.
		// An inline picker has no trigger button, and nowhere equivalent to put one: it renders the
		// colour grid itself, with no chrome of its own to hang a button off.  So an inline picker
		// deliberately gets no clear button - if "no colour" is a valid answer there, the template
		// has to offer it some other way.
		if(this._buttonNode)
		{
			render(this._clearButtonTemplate(), this._buttonNode);
		}
	}

	/**
	 * SlColorPicker has no visible label or help text of its own, so they are rendered around it, like the other widgets
	 */
	render()
	{
		const label = this._labelTemplate();
		const help = this._helpTextTemplate();

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        "form-control": true,
                        "form-control--medium": true,
                        "form-control--has-label": label !== nothing,
                        "form-control--has-help-text": help !== nothing
                    })}
            >
                ${label}
                <div part="form-control-input" class="form-control-input">
                    ${super.render()}
                </div>
                ${help}
            </div>
		`;
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has("disabled") && this._buttonNode)
		{
			// SlColorPicker marks a disabled trigger with a CSS class only, which assistive tech never sees
			this._buttonNode.setAttribute("aria-disabled", this.disabled ? "true" : "false");
			// The clear button is rendered by hand, outside SlColorPicker's own render, so it has to follow disabled itself
			if(changedProperties.get("disabled") !== undefined)
			{
				render(this._clearButtonTemplate(), this._buttonNode);
			}
		}
	}

	/**
	 * SlColorPicker's trigger button, which we render our clear button into.
	 *
	 * Null when inline, where SlColorPicker renders no trigger.
	 */
	private get _buttonNode() : HTMLElement | null
	{
		return this.shadowRoot.querySelector("button[slot='trigger']");
	}

	/**
	 * The trigger button is what the user operates, so it gets the aria-label / -description
	 */
	getInputNode() : HTMLInputElement
	{
		return this._buttonNode as unknown as HTMLInputElement;
	}

	_clearButtonTemplate()
	{
		return html`
            <button part="clear-button" class="input__clear" type="button" tabindex="-1"
                    aria-label="${this.egw().lang("Clear entry")}"
                    ?disabled=${this.disabled}
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