import {html, LitElement} from "lit";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {FileInfo} from "./Et2VfsSelectDialog";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import shoelace from "../Styles/shoelace";
import styles from "./Et2VfsSelectRow.styles";

/**
 * @summary Shows one file in the Et2VfsSelectDialog list
 *
 * @slot prefix - Used to prepend an icon or similar element between the checked icon and the mime icon
 * @slot suffix - Used to append an icon or similar element after the file name
 *
 * @csspart base - The component’s base wrapper.
 * @csspart checked-icon - The checked icon, an <sl-icon> element.
 */
export class Et2VfsSelectRow extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			styles
		];
	}

	@property({type: Object}) value : FileInfo;

	/** Draws the file in a disabled state, preventing selection. */
	@property({type: Boolean, reflect: true}) disabled = false;

	@state() current = false; // the user has keyed into the file, but hasn't selected it yet (shows a highlight)
	@state() selected = false; // the file is selected and has aria-selected="true"
	@state() hasHover = false; // we need this because Safari doesn't honor :hover styles while dragging

	connectedCallback()
	{
		super.connectedCallback();
		this.setAttribute('role', 'option');
		this.setAttribute('aria-selected', 'false');
	}

	private handleMouseEnter()
	{
		this.hasHover = true;
		this.requestUpdate("hasHover", false);
	}

	private handleMouseLeave()
	{
		this.hasHover = false;
		this.requestUpdate("hasHover", true);
	}

	render()
	{
		return html`
            <div
                    part="base"
                    class=${classMap({
                        file: true,
                        'file--current': this.current,
                        'file--disabled': this.disabled,
                        'file--selected': this.selected,
                        'file--hover': this.hasHover
                    })}
                    @mouseenter=${this.handleMouseEnter}
                    @mouseleave=${this.handleMouseLeave}
            >
                <sl-icon part="checked-icon" class="file__check" name="check-lg" aria-hidden="true"></sl-icon>
                <slot part="prefix" name="prefix" class="file__prefix"></slot>
                <et2-vfs-mime .value=${this.value}></et2-vfs-mime>
                ${this.value.name}
                <slot part="suffix" name="suffix" class="file__suffix"></slot>
            </div>
		`;
	}
}

customElements.define("et2-vfs-select-row", Et2VfsSelectRow);