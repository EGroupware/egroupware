import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlDropdown} from "@shoelace-style/shoelace";
import {customElement} from "lit/decorators/custom-element.js";
import {css} from "lit";

@customElement("et2-dropdown")
export class Et2Dropdown extends Et2Widget(SlDropdown)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					max-width: fit-content;
				}

				.dropdown--open .dropdown__panel {
					background-color: var(--sl-panel-background-color);
					padding: var(--sl-spacing-medium);
				}
			`
		];
	}
}