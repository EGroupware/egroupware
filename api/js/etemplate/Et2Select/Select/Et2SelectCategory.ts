/**
 * EGroupware eTemplate2 - Select Category WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {css, html, nothing, PropertyValues, TemplateResult, unsafeCSS} from "lit";
import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "../StaticOptions";
import {cleanSelectOptions} from "../FindSelectOptions";
import {StaticValue} from "lit/development/static-html";
import {literal} from "lit/static-html.js";
import {repeat} from "lit/directives/repeat.js";

/**
 * Customised Select widget for categories
 * This widget gives us category colors and icons in the options and selected value.
 */
export class Et2SelectCategory extends Et2StaticSelectMixin(Et2Select)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			/* Category color on options */

			  sl-option {
				border-left: 6px solid var(--category-color, transparent);
			}
			/* Border on the (single) selected value */

			  :host(:not([multiple]))::part(combobox) {
				border-left: 6px solid var(--category-color, var(--sl-input-border-color));
			}			
			`
		]
	}

	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Include global categories
			 */
			globalCategories: {type: Boolean},
			/**
			 * Show categories from this application.  If not set, will be the current application
			 */
			application: {type: String},
			/**
			 * Show categories below this parent category
			 */
			parentCat: {type: Number}
		}
	}

	constructor()
	{
		super();
		// we should not translate categories name
		this.noLang = true;
	}

	async connectedCallback()
	{
		super.connectedCallback();

		if(typeof this.application == 'undefined')
		{
			this.application =
				// When the widget is first created, it doesn't have a parent and can't find it's instanceManager
				(this.getInstanceManager() && this.getInstanceManager().app) ||
				this.egw().app_name();
		}
	}


	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("global_categories") || changedProperties.has("application") || changedProperties.has("parentCat"))
		{
			this.fetchComplete = so.cat(this).then(options =>
			{
				this._static_options = cleanSelectOptions(options);
				this.requestUpdate("select_options");

				// Shoelace select has rejected our value due to missing option by now, so re-set it
				this.updateComplete.then(() =>
				{
					this.value = this.value;
				});
			});
		}
	}


	protected handleValueChange(e)
	{
		super.handleValueChange(e);

		// Just re-draw to get the colors & icon
		this.requestUpdate();
	}

	/**
	 * Custom, dynamic styling
	 *
	 * CSS variables are not making it through to options, re-declaring them here works
	 *
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _styleTemplate() : TemplateResult
	{
		return html`
            <style>
                ${repeat(this.select_options, (option) =>
                {
                    if(typeof option.color == "undefined" || !option.color)
                    {
                        return nothing;
                    }
                    return unsafeCSS(
                            (this.getValueAsArray().includes(option.value) ? "::part(combobox) { --category-color: " + option.color + ";}" : "") +
                            ".cat_" + option.value + " {--category-color: " + option.color + ";}"
                    );
                })}
            </style>
		`;
	}
	
	/**
	 * Use a custom tag for when multiple=true
	 *
	 * @returns {string}
	 */
	public get tagTag() : StaticValue
	{
		return literal`et2-category-tag`;
	}
}

customElements.define("et2-select-cat", Et2SelectCategory);