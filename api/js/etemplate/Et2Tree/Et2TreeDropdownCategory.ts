/**
 * Use a custom tag for when multiple=true
 *
 * @returns {string}
 */
import {html, literal, StaticValue} from "lit/static-html.js";
import {property} from "lit/decorators/property.js";
import {css, PropertyValues, unsafeCSS} from "lit";
import {Et2TreeDropdown} from "./Et2TreeDropdown";
import {Et2CategoryTag} from "../Et2Select/Tag/Et2CategoryTag";

export class Et2TreeDropdownCategory extends Et2TreeDropdown
{

	static get styles()
	{
		return [
			super.styles,
			css`
				:host {
					--category-color: transparent;
				}

				::part(item-item) {
					border-inline-start: 4px solid transparent;
					border-inline-start-color: var(--category-color, transparent);
				}
			`
		];
	}
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

	/**
	 * Set CSS category colors
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected styleTemplate()
	{
		let css = "";
		const catColor = (option) =>
		{
			css += ".cat_" + option.value + " {--category-color: " + (option.data?.color || "transparent") + ";}\n";

			option.children?.forEach((option) => catColor(option))
		}
		this.select_options.forEach((option => catColor(option)));
		// @formatter:off
		return html`
            <style>${unsafeCSS(css)}</style>
		`;
		// @formatter:on
	}
}

// @ts-ignore Type problems because of Et2WidgetWithSelectMixin in parent
customElements.define("et2-tree-cat", Et2TreeDropdownCategory);