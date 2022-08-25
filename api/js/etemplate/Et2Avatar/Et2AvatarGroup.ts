import {Et2Widget} from "../Et2Widget/Et2Widget";
import {css, html, LitElement, repeat} from "@lion/core";
import shoelace from "../Styles/shoelace";

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
			css`
			:host {
				display: flex;
				flex-direction: row;
				justify-content: flex-end;
			}
			et2-avatar {
				--size: 1.5rem;
				flex: 0 0 auto;
				min-width: 20px;
				transition-duration:0.1s;
			}
			et2-avatar:not(:first-of-type) {
				margin-left: -0.5rem;
			}
			et2-avatar::part(base) {
				border: solid 2px var(--sl-color-neutral-0);
			}
			et2-avatar:hover {
				--size: 2.5rem;
				overflow: visible;
				z-index: 11;
				transition-delay: 1s;
				transition-suration:0.5s
			}
			`
		];
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * List of contact IDs
			 */
			value: {
				type: Array
			},
		}
	}

	constructor()
	{
		super();
		this.value = []
	}

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