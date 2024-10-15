import {Et2SwitchIcon} from "../Et2Switch/Et2SwitchIcon";
import {customElement} from "lit/decorators/custom-element.js";
import {css, PropertyValues} from "lit";
import {property} from "lit/decorators/property.js";

/**
 * @summary A button to allow turning something on or off, displayed with two images instead of the normal button UI
 *
 * @slot - Add an image directly instead of setting the `icon` property
 * @slot help-text - Text that describes how to use the button. Alternatively, you can use the `help-text` attribute.
 *
 * @cssproperty --indicator-color - The color of the selected image
 */
@customElement("et2-button-toggle")
export class Et2ButtonToggle extends Et2SwitchIcon
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				slot[name] {
					display: none;
				}

				sl-switch {
					font-size: inherit;
					--width: 1em;
					--height: 1em;
				}

				sl-switch:not([checked]) slot[name="off"] {
					color: var(--sl-color-neutral-400);
				}

				sl-switch[checked] slot[name="on"], sl-switch:not([checked]) slot[name="off"] {
					display: inline-block;
				}

				.label {
					border: var(--sl-input-border-width) solid var(--sl-input-border-color);
					border-radius: var(--sl-input-border-radius-medium);
				}


				/* Success */

				:host([variant=success]) .label {
					background-color: var(--sl-color-success-600);
					border-color: var(--sl-color-success-600);
					--indicator-color: var(--sl-color-neutral-0);
				}

				:host([variant=success]) .label:hover {
					background-color: var(--sl-color-success-500);
					border-color: var(--sl-color-success-500);
					--indicator-color: var(--sl-color-neutral-0);
				}

				/* Neutral */

				:host([variant=neutral]) .label {
					background-color: var(--sl-color-neutral-600);
					border-color: var(--sl-color-neutral-600);
					--indicator-color: var(--sl-color-neutral-0);
				}

				:host([variant=neutral]) .label:hover {
					background-color: var(--sl-color-neutral-500);
					border-color: var(--sl-color-neutral-500);
					--indicator-color: var(--sl-color-neutral-0);
				}

				/* Warning */

				:host([variant=warning]) .label {
					background-color: var(--sl-color-warning-600);
					border-color: var(--sl-color-warning-600);
					--indicator-color: var(--sl-color-neutral-0);
				}

				:host([variant=warning]) .label:hover {
					background-color: var(--sl-color-warning-500);
					border-color: var(--sl-color-warning-500);
					--indicator-color: var(--sl-color-neutral-0);
				}

				/* Danger */

				:host([variant=danger]) .label {
					background-color: var(--sl-color-danger-600);
					border-color: var(--sl-color-danger-600);
					--indicator-color: var(--sl-color-neutral-0);
				}

				:host([variant=danger]) .label:hover {
					background-color: var(--sl-color-danger-500);
					border-color: var(--sl-color-danger-500);
					--indicator-color: var(--sl-color-neutral-0);
				}

			`
		]
	}

	/**
	 * Name of the icon used.
	 * Alternatively, you can add an `et2-image` as a child
	 * @type {string}
	 */
	@property() icon = "check";

	/**
	 * Specify the icon used when the toggle is off.  Defaults to `icon` but dimmed.
	 * @type {string}
	 */
	@property() offIcon = ""

	/**
	 *
	 * @type {string}
	 */
	@property() variant = "neutral"

	private mutationObserver : MutationObserver;

	constructor()
	{
		super();

		this.handleIconChanged = this.handleIconChanged.bind(this);
	}

	async connectedCallback()
	{
		super.connectedCallback();

		// If a child was added as part of loading, set up 1 child into both on/off slots
		if(this.children && this.childElementCount == 1 && !this.children[0].hasAttribute("slot"))
		{
			this.adoptIcon(<HTMLElement>this.children[0]);
		}

		await this.updateComplete;

		this.mutationObserver = new MutationObserver(this.handleIconChanged);
		this.mutationObserver.observe(this, {subtree: true, childList: true});
	}

	willUpdate(changedProperties : PropertyValues<this>)
	{
		if(changedProperties.has("icon") || this.icon && (!this.onIcon || this.onIcon == "check"))
		{
			this.onIcon = this.icon;
			this.offIcon = this.icon;
		}
	}

	// Take a single element and give it the needed slots so it works
	protected adoptIcon(icon : HTMLElement)
	{
		const off = <HTMLElement>icon.cloneNode();
		icon.setAttribute("slot", "on");
		off.setAttribute("slot", "off");
		this.append(off);
	}

	// Listen for added icon and adopt it (needs to not have a slot)
	protected handleIconChanged(mutations : MutationRecord[])
	{
		for(const mutation of mutations)
		{
			mutation.addedNodes.forEach((n : HTMLElement) =>
			{
				if(typeof n.hasAttribute == "function" && !n.hasAttribute("slot"))
				{
					this.adoptIcon(n);
				}
			});
		}
	}
}