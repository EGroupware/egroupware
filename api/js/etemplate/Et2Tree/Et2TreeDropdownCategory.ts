/**
 * Use a custom tag for when multiple=true
 *
 * @returns {string}
 */
import {literal, StaticValue} from "lit/static-html.js";
import {Et2TreeDropdown} from "./Et2TreeDropdown";
import {Et2CategoryTag} from "../Et2Select/Tag/Et2CategoryTag";

export class Et2TreeDropdownCategory extends Et2TreeDropdown
{
	private keep_import : Et2CategoryTag

	public get tagTag() : StaticValue
	{
		return literal`et2-category-tag`;
	}
}

customElements.define("et2-tree-cat", Et2TreeDropdownCategory);