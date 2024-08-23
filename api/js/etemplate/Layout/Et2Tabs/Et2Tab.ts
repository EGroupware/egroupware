import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlTab} from "@shoelace-style/shoelace";
import shoelace from "../../Styles/shoelace";
import {css} from "lit";
import {property} from "lit/decorators/property.js";

export class Et2Tab extends Et2Widget(SlTab)
{
	static get styles()
	{
		return [
			...super.styles,
			...shoelace,
			css`
				.tab {
					font-size: var(--sl-size-x-small);
					gap: var(--sl-spacing-small);
				}
				.tab.tab--active:not(.tab--disabled) {color:var(--sl-color-gray-800)}
				.tab:hover:not(.tab--disabled) {color:var(--sl-color-gray-800)}		
			`
		];
	}

	@property({type: Function})
	ondblclick;

	constructor()
	{
		super();
		this.hidden = false;
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(this.ondblclick)
		{
			this.addEventListener("dblclick", this.ondblclick);
		}
	}

	disconnectedCallback()
	{
		super.disconnectedCallback()

		this.removeEventListener("dblclick", this.ondblclick);
	}
}

customElements.define("et2-tab", Et2Tab);