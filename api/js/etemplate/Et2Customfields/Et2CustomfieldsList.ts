import {Et2CustomfieldsBase} from "./Et2CustomfieldsBase";
import {customElement} from "lit/decorators/custom-element.js";

/**
 * Customfields list variant used for row/list contexts.
 */
@customElement("et2-customfields-list")
export class Et2CustomfieldsList extends Et2CustomfieldsBase
{
	constructor(...args : any[])
	{
		super(...args);
		this.mode = "customfields-list";
	}
}
