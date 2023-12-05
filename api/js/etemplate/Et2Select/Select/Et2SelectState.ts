import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "../StaticOptions";
import {cleanSelectOptions, SelectOption} from "../FindSelectOptions";

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
		this.fetchComplete = so.state(this, {country_code: code}).then((options : SelectOption[]) =>
		{
			this._static_options = cleanSelectOptions(options);
			this.requestUpdate();
		});
	}

	set_country_code(code)
	{
		this.countryCode = code;
	}
}

customElements.define("et2-select-state", Et2SelectState);