import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlTab} from "@shoelace-style/shoelace";

export class Et2Tab extends Et2Widget(SlTab)
{

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