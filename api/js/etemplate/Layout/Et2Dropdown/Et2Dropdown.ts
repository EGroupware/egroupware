import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlDropdown} from "@shoelace-style/shoelace";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
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

	/**
	 * Open the panel on mouse hover, instead of requiring a click on the trigger
	 */
	@property({type: Boolean})
	toggleOnHover = false;

	constructor()
	{
		super();
		this._mouseOutEvent = this._mouseOutEvent.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		this.updateComplete.then(() =>
		{
			if(this.toggleOnHover)
			{
				this.addEventListener("mouseover", this.show);
				this.addEventListener('mouseout', this._mouseOutEvent);
			}
		});
	}

	/**
	 * Close the dropdown once the mouse leaves it
	 * @param event
	 */
	_mouseOutEvent(event)
	{
		if(!this.getDOMNode().contains(event.relatedTarget)) this.hide();
	}
}