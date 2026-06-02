import {Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * Generic customfields widget container.
 *
 * This component currently focuses on customfield visibility/filter state and is
 * designed to be extended with concrete field rendering.
 */
@customElement("et2-customfields")
export class Et2Customfields extends Et2CustomfieldsBase
{
	constructor()
	{
		super();
		this.mode = "customfields";
	}
}
