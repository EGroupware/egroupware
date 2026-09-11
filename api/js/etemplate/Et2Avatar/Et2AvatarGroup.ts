import {Et2Widget} from "../Et2Widget/Et2Widget";
import {html, LitElement} from "lit";
import {property} from "lit/decorators/property.js";
import {repeat} from "lit/directives/repeat.js";
import shoelace from "../Styles/shoelace";

import styles from "./Et2AvatarGroup.styles";
/**
 * Show multiple avatars
 */
export class Et2AvatarGroup extends Et2Widget(LitElement)
{

	static get styles()
	{
		return [
			...super.styles,
			shoelace,
			// TODO: More work on sizing needed to better adapt to available space
			styles
		];
	}

	/**
	 * List of contact IDs
	 */
	@property({type: Array})
	value : any[] = [];

	set_value(new_value)
	{
		if(typeof new_value !== "object")
		{
			new_value = new_value.split(",");
		}
		this.value = new_value;
	}

	avatarTemplate(contact : { id : string, label? : string })
	{
		if(typeof contact == "string")
		{
			contact = {id: contact};
		}
		return html`
            <et2-avatar
                    .contactId="${contact.id}"
                    .label="${contact.label}"
                    .title="${contact.label}"
                    shape="circle"
                    size=""
            ></et2-avatar>`;
	}

	render()
	{
		return html`
            ${repeat(this.value, (contact) => contact.id, (contact) => this.avatarTemplate(contact))}`;
	}
}

customElements.define("et2-avatar-group", Et2AvatarGroup);