/**
 * Use a custom tag for when multiple=true
 *
 * @returns {string}
 */
import {literal, StaticValue} from "lit/static-html.js";
import {property} from "lit/decorators/property.js";
import {PropertyValues} from "lit";
import {Et2TreeDropdown} from "./Et2TreeDropdown";
import {Et2CategoryTag} from "../Et2Select/Tag/Et2CategoryTag";

export class Et2TreeDropdownCategory extends Et2TreeDropdown
{

	/**
	 * Application to get categories from
	 */
	@property({type: String}) application = '';

	/**
	 * Include global categories
	 */
	@property({type: Boolean}) globalCategories = true;

	private keep_import : Et2CategoryTag

	connectedCallback()
	{
		super.connectedCallback();

		// Default the application if not set
		if(!this.application && this.getInstanceManager())
		{
			this.application = this.getInstanceManager().app;
		}

		// Set the search options from our properties
		this.searchOptions.application = this.application;
		this.searchOptions.globalCategories = this.globalCategories;
	}

	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has('application'))
		{
			this.searchOptions.application = this.application;
		}
		if(changedProperties.has('globalCategories'))
		{
			this.searchOptions.globalCategories = this.globalCategories;
		}
	}

	public get tagTag() : StaticValue
	{
		return literal`et2-category-tag`;
	}
}

// @ts-ignore Type problems because of Et2WidgetWithSelectMixin in parent
customElements.define("et2-tree-cat", Et2TreeDropdownCategory);