import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlTabPanel} from "@shoelace-style/shoelace";
import shoelace from "../../Styles/shoelace";
import {property} from "lit/decorators/property.js";

import styles from "./Et2TabPanel.styles";
export class Et2TabPanel extends Et2Widget(SlTabPanel)
{
	static get styles()
	{
		return [
			// @ts-ignore
			...super.styles,
			...shoelace,
			styles
		];
	}


	@property({type: Boolean, reflect: true})
	hidden : boolean = false;
}

customElements.define("et2-tab-panel", Et2TabPanel);