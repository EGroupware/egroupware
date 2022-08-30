import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlTab} from "@shoelace-style/shoelace";
import shoelace from "../../Styles/shoelace";
import {css} from "@lion/core";

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
				}
				.tab.tab--active:not(.tab--disabled) {color:var(--sl-color-gray-800)}
				.tab:hover:not(.tab--disabled) {color:var(--sl-color-gray-800)}		
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,

			hidden: {type: Boolean, reflect: true}
		}
	}

	constructor()
	{
		super();
		this.hidden = false;
	}
}

customElements.define("et2-tab", Et2Tab);