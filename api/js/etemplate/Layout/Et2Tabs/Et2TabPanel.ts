import {Et2Widget} from "../../Et2Widget/Et2Widget";
import {SlTabPanel} from "@shoelace-style/shoelace";
import shoelace from "../../Styles/shoelace";
import {css} from "lit";
import {property} from "lit/decorators/property.js";

export class Et2TabPanel extends Et2Widget(SlTabPanel)
{
	static get styles()
	{
		return [
			// @ts-ignore
			...super.styles,
			...shoelace,
			css`
			:host {
			
				height: 100%;
				/*
				width: 100%;
				
				min-height: fit-content;
				min-width: fit-content;
				*/
			}
			.tab-panel {
				height: 100%;
			}
			::slotted(*) {
				height: 100%;
			}
			`
		];
	}


	@property({type: Boolean, reflect: true})
	hidden : boolean = false;
}

customElements.define("et2-tab-panel", Et2TabPanel);