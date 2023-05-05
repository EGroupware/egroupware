import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectState extends Et2StaticSelectMixin(Et2Select)
{
	/**
	 * Two-letter ISO country code
	 */
	protected __countryCode;

	static get properties()
	{
		return {
			...super.properties,
			countryCode: String,
		}
	}

	constructor()
	{
		super();

		this.countryCode = 'DE';
	}

	get countryCode()
	{
		return this.__countryCode;
	}

	set countryCode(code : string)
	{
		this.__countryCode = code;
		this.static_options = StaticOptions.state(this, {country_code: code});
		this.requestUpdate("select_options");
	}

	set_country_code(code)
	{
		this.countryCode = code;
	}
}

customElements.define("et2-select-state", Et2SelectState);