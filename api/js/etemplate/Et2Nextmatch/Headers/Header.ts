import {css, html, LitElement} from "lit";
import {property} from "lit/decorators/property.js";
import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {et2_INextmatchHeader, et2_nextmatch} from "../../et2_extension_nextmatch";
import {customElement} from "lit/decorators.js";

/**
 * Plain nextmatch header caption.
 *
 * This is the webComponent counterpart of legacy `et2_nextmatch_header`.
 */
@customElement("et2-nextmatch-header")
export class Et2NextmatchHeader extends Et2Widget(LitElement) implements et2_INextmatchHeader
{
	static get styles()
	{
		return [
			super.styles,
			css`
				:host {
					display: inline-block;
				}

				.label {
					display: inline-block;
				}

				.label.et2_label_empty {
					min-width: 0.5em;
				}
			`
		];
	}

	/**
	 * Header caption text.
	 */
	@property({type: String})
	label : string = "";

	/**
	 * The owning nextmatch instance.
	 * Set by the parent header bar via the `et2_INextmatchHeader` contract.
	 */
	protected nextmatch : et2_nextmatch | null = null;

	/**
	 * Store owning nextmatch for later interactions (sorting/filtering hooks).
	 */
	setNextmatch(nextmatch : et2_nextmatch)
	{
		this.nextmatch = nextmatch;
	}

	/**
	 * Legacy compatibility wrapper.
	 * Legacy code paths can call `set_label()` directly.
	 */
	set_label(value : string)
	{
		this.label = value || "";
	}

	/**
	 * Render plain caption text and keep the legacy empty-label class behavior.
	 */
	render()
	{
		return html`
			<span class="label ${this.label ? "" : "et2_label_empty"}">${this.label || ""}</span>
		`;
	}
}

if(!window.customElements.get("et2-nextmatch-header"))
{
	window.customElements.define("et2-nextmatch-header", Et2NextmatchHeader);
}
