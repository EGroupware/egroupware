import shoelace from "../Styles/shoelace";
import styles from "./Et2FileItem.styles";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {html, LitElement, nothing} from "lit";
import {HasSlotController} from "../Et2Widget/slot";
import {property} from "lit/decorators/property.js";
import {classMap} from "lit/directives/class-map.js";
import {waitForEvent} from "../Et2Widget/event";
import {ifDefined} from "lit/directives/if-defined.js";

/**
 * @summary Displays a single (uploaded) file with file information, upload status, etc.
 *
 *
 * @dependency sl-format-bytes
 * @dependency sl-progress-bar
 * @dependency sl-icon
 *
 * @slot - File name
 * @slot image - The file's image (mimetype icon, status icon, etc)
 * @slot close-button - Close button
 * @event load - Emitted when file is loaded
 *
 * @csspart base - Component internal wrapper
 */
@customElement("et2-file-item")
export class Et2FileItem extends Et2Widget(LitElement)
{

	static get styles()
	{
		return [
			shoelace,
			super.styles,
			styles
		];
	}

	/** Draws the item in a loading state. */
	@property({type: Boolean, reflect: true}) loading = false;

	/** The current progress, 0 to 100. Only used if loading property is true. */
	@property({type: Number, reflect: true}) progress : number;

	/** The item's theme variant. */
	@property({reflect: true}) variant : "default" | "primary" | "success" | "neutral" | "warning" | "danger" =
		"default";

	/** Different ways of displaying the item.  Large for a few files, small is like a tag, list is for several files */
	@property({reflect: true}) display : "large" | "small" | "list" = "large";

	/** Makes the item closable (removable). */
	@property({type: Boolean, reflect: true}) closable = false;

	/** A unique value to store in the item. This can be used as a way to identify items. */
	@property() value = "";

	/** The size of the file in bytes as a read-only 64-bit integer. */
	@property({type: Number, reflect: true}) size : number;

	/** The file's thumbnail image */
	@property({type: String}) image : string = "";

	/** Indicates whether the file item is hidden. */
	@property({type: Boolean, reflect: true}) hidden = false;

	private readonly hasSlotController = new HasSlotController(this, "image", "suffix");

	private get base() : HTMLElement {return this.shadowRoot?.querySelector('[part~="base"]');}

	updated(changedProperties : Map<string, any>)
	{
		if(changedProperties.has('hidden'))
		{
			this.handleHiddenChange();
		}
	}

	/* Hides the file item */
	async hide()
	{
		if(this.hidden)
		{
			return undefined;
		}

		this.hidden = true;
		this.requestUpdate("hidden");
		return waitForEvent(this, 'sl-after-hide');
	}

	async error(message?)
	{
		this.variant = "danger";
		this.loading = false;
		if(message)
		{
			this.innerHTML += "<br />" + message;
		}
		this.requestUpdate("variant");
	}

	handleCloseClick(e)
	{
		e.stopPropagation();
		this.hide();
	}

	async handleHiddenChange()
	{
		if(!this.hidden)
		{
			// Show
			this.dispatchEvent(new Event('sl-show', {bubbles: true}));
			// TODO: Animation?
			this.base.hidden = false;

			this.dispatchEvent(new Event('sl-after-show', {bubbles: true}));
		}
		else
		{
			// Hide
			this.dispatchEvent(new Event('sl-hide', {bubbles: true}));
			// TODO: Animation?
			this.base.hidden = true;

			this.dispatchEvent(new Event('sl-after-hide', {bubbles: true}));
		}
	}

	handleTriggerKeyUp(event : KeyboardEvent)
	{
		// Prevent space from triggering a click event in Firefox
		if(event.key === "\xA0 ")
		{
			event.preventDefault();
		}
	}

	render()
	{
		const progressBar = html`${this.loading ? html`
            <sl-progress-bar
                    class="file-item__progress-bar"
                    ?indeterminate=${this.progress === undefined}
                    value=${ifDefined(this.progress)}
            ></sl-progress-bar>` : nothing}`;

		return html`
            <div
                    part="base"
                    class=${classMap({
                        "file-item": true,
                        'file-item--default': this.variant === 'default',
                        'file-item--primary': this.variant === 'primary',
                        'file-item--success': this.variant === 'success',
                        'file-item--neutral': this.variant === 'neutral',
                        'file-item--warning': this.variant === 'warning',
                        'file-item--danger': this.variant === 'danger',
                        'file-item--large': this.display === "large" || !this.display,
                        'file-item--small': this.display === "small",
                        'file-item--list': this.display === "list",
                        //@ts-ignore disabled comes from Et2Widget
                        "file-item--disabled": this.disabled,
                        "file-item--hidden": this.hidden,
                        "file-item--closable": this.closable,
                        "file-item--has-size": this.size,
                        "file-item--is-loading": this.loading,
                        "file-item--has-image": (this.hasSlotController.test("image") || this.image != ""),
                    })}
            >
        <span class="file-item__content">
          <span part="image" class="file-item__image">
            <slot name="image">${this.image ? html`
                <et2-image src="${this.image}"></et2-image>` : nothing}</slot>
          </span>
          <span part="label" class="file-item__label">
            <slot></slot>
			  ${progressBar}
            ${this.size
              ? html`
                        <sl-format-bytes
                                value="${this.size}"
                                class="file-item__label__size"
                                lang=${this.egw().preference("lang", "common") ?? "en"}>
                        </sl-format-bytes>`
              : ""}
		  </span>
         	</span>
                ${this.closable
                  ? html`
                            <span
                                    class="file-item__close-button"
                                    @click=${this.handleCloseClick}
                                    @keyup=${this.handleTriggerKeyUp}
                            >
						<slot name="close-button">
						  <sl-icon-button part="close-button"
                                          name="x"
                                          exportparts="base:close-button__base"
                          ></sl-icon-button>
						</slot>
					  </span>`
                  : ""}
            </div>
		`;
	}
}