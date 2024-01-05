/**
 * EGroupware eTemplate2 - Select Country WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "../StaticOptions";
import {egw} from "../../../jsapi/egw_global";
import {SelectOption} from "../FindSelectOptions";
import {html} from "lit";

/**
 * Customised Select widget for countries
 * This widget uses CSS from api/templates/default/css/flags.css to set flags
 */
if(egw && egw(window) && typeof egw(window).includeCSS == "function")
{
	egw(window).includeCSS("api/templates/default/css/flags.css")
}

export class Et2SelectCountry extends Et2StaticSelectMixin(Et2Select)
{
	static get properties()
	{
		return {
			...super.properties,
			/* Reflect the value so we can use CSS selectors */
			value: {type: String, reflect: true}
		}
	}

	constructor()
	{
		super();

		this.search = true;

		this.fetchComplete = (<Promise<SelectOption[]>>so.country(this, {}, true)).then(options =>
		{
			this._static_options = options
			this.requestUpdate("select_options");
		});
	}

	connectedCallback()
	{
		super.connectedCallback();
	}

	/**
	 * Get the element for the flag
	 *
	 * @param option
	 * @protected
	 */
	protected _iconTemplate(option)
	{
		return html`
            <span slot="prefix" part="flag country_${option.value}_flag"
                  style="width: var(--icon-width)">
			</span>`;
	}

	/**
	 * Used to render each option into the select
	 * Override to get flags in
	 *
	 * @param {SelectOption} option
	 * @returns {TemplateResult}
	 *
	protected _optionTemplate(option : SelectOption) : TemplateResult
	{
		// Exclude non-matches when searching
		if(typeof option.isMatch == "boolean" && !option.isMatch)
		{
			return html``;
		}

		return html`
            <sl-option
                    part="option"
                    value="${value}"
                    title="${!option.title || this.noLang ? option.title : this.egw().lang(option.title)}"
                    class="${option.class}" .option=${option}
                    .selected=${this.getValueAsArray().some(v => v == value)}
                    ?disabled=${option.disabled}
            >
                ${this._iconTemplate(option)}
                ${this.noLang ? option.label : this.egw().lang(option.label)}
            </sl-option>`;
	}
	 */
}

customElements.define("et2-select-country", Et2SelectCountry);