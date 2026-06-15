import {Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * Customfields filter variant (filter-only contexts).
 */
@customElement("et2-customfields-filters")
export class Et2CustomfieldsFilters extends Et2CustomfieldsBase
{
	constructor()
	{
		super();
		this.mode = "customfields-filters";
	}
}
