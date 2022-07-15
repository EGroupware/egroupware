/**
 * EGroupware eTemplate2 - Select Country WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */


import {Et2Select} from "./Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "./StaticOptions";
import {egw} from "../../jsapi/egw_global";
import {SelectOption} from "./FindSelectOptions";

/**
 * Customised Select widget for countries
 * This widget uses CSS from api/templates/default/css/flags.css to set flags
 */
egw(window).includeCSS("api/templates/default/css/flags.css")

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

		(<Promise<SelectOption[]>>so.country(this, {}, true)).then(options =>
		{
			this.static_options = options
			this.requestUpdate("select_options");
		});
	}
}

/**
 * Use a single StaticOptions, since it should have no state
 * @type {StaticOptions}
 */
const so = new StaticOptions();

customElements.define("et2-select-country", Et2SelectCountry);