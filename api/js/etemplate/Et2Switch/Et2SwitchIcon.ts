import {css, html, LitElement, nothing} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {live} from "lit/directives/live.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {SlSwitch} from "@shoelace-style/shoelace";
import {et2_evalBool} from "../et2_core_common";

/**
 * @summary Switch to allow choosing between two options, displayed with two images
 *
 * @slot on - Content shown when the switch is on
 * @slot off - Content shown when the switch is off
 * @slot help-text - Text that describes how to use the switch. Alternatively, you can use the `help-text` attribute.
 *
 * @cssproperty --height - The height of the switch.
 * @cssproperty --width - The width of the switch.
 * @cssproperty --indicator-color - The color of the selected image
 *
 * @csspart form-control-label The label's wrapper
 * @csspart control The control's wrapper
 * @csspart switch-label The internal control's wrapper (sometimes needed for positioning)
 */
@customElement("et2-switch-icon")
export class Et2SwitchIcon extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					--indicator-color: var(--sl-color-primary-600);
					display: flex;
				}

				sl-switch {
					font-size: 1em;
					--height: 1em;
				}

				::part(control) {
					display: none;
				}

				::part(label) {
					width: 100%;
					height: 100%;
				}

				.label {
					display: inline-flex;
					flex: 1 1 auto;
					font-size: var(--height);
					user-select: none;
				}

				et2-image, ::slotted(:scope > *) {
					flex: 1 1 50%;
					font-size: var(--width);
				}

				slot {
					color: var(--sl-input-placeholder-color);
				}

				sl-switch {
					display: flex;
					align-items: center;
				}

				sl-switch[checked] slot[name="on"], sl-switch:not([checked]) slot[name="off"] {
					color: var(--indicator-color, inherit);
				}

				sl-switch::part(label), sl-switch::part(form-control) {
					display: flex;
					align-items: center;
					margin-inline-start: 0px;
				}

				.label:hover {
					background-color: var(--sl-input-background-color-hover);
					border-color: var(--sl-input-border-color-hover);
				}
			`
		]
	}

	/**
	 * Name of the icon displayed when the switch is on
	 * @type {string}
	 */
	@property() onIcon = "check";

	/**
	 * Name of the icon displayed when the switch is off
	 * @type {string}
	 */
	@property() offIcon = "x"

	protected get switch() : SlSwitch { return <SlSwitch>this.shadowRoot?.querySelector("sl-switch")};

	private get input() { return this.switch.shadowRoot.querySelector("input");}

	async getUpdateComplete()
	{
		const result = await super.getUpdateComplete();
		await this.switch?.updateComplete;
		return result;
	}

	set value(new_value : string | boolean)
	{
		if(typeof new_value !== "boolean")
		{
			new_value = et2_evalBool(new_value);
		}
		if(this.switch)
		{
			this.switch.checked = !!new_value;
		}
		else
		{
			this.updateComplete.then(() => this.value = new_value);
		}
	}

	get value()
	{
		return this.switch?.checked;
	}

	/** Overridden from parent because something in there clears / resets the check value */
	async validate(skipManual = false)
	{
		return;
	}

	labelTemplate()
	{
		return html`
            ${this.label ? html`<span
                    part="form-control-label"
                    class="form-control__label">${this.label}
			</span>` : nothing}
            <span
                    part="control"
                    class=${classMap({
                        "label": true,
                        "on": this.checked,
                    })}
                    aria-label="${this.label}"
            >
				<slot name="on">
					<et2-image class="image on" src=${this.onIcon} title="${this.toggleOn}"></et2-image>
				</slot>
				<slot name="off">
					<et2-image class="image off" src=${this.offIcon} title="${this.toggleOff}"></et2-image>
				</slot>
			</span>
		`;
	}

	render()
	{
		return html`
            <sl-switch
                    part="switch"
                    exportparts="base:switch-label control"
                    .label=${this.label}
                    .value=${live(this.value)}
                    .checked=${live(this.checked)}
                    .disabled=${live(this.disabled)}
                    .required=${this.required}
                    .helpText=${this.helpText}
                    @sl-change=${async(e) =>
                    {
                        e.stopPropagation();
                        await this.updateComplete;
                        this.dispatchEvent(new Event("change", {bubbles: true}));
                    }}
            >
                ${this.labelTemplate()}
            </sl-switch>
		`;
	}
}