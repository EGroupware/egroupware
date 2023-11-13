import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";
import {PropertyValues} from 'lit';

export class Et2SelectNumber extends Et2StaticSelectMixin(Et2Select)
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Step between numbers
			 */
			interval: {type: Number},
			min: {type: Number},
			max: {type: Number},

			/**
			 * Add one or more leading zeros
			 * Set to how many zeros you want (000)
			 */
			leading_zero: {type: String},
			/**
			 * Appended after every number
			 */
			suffix: {type: String}
		}
	}

	constructor()
	{
		super();
		this.min = 1;
		this.max = 10;
		this.interval = 1;
		this.leading_zero = "";
		this.suffix = "";
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') || changedProperties.has('suffix'))
		{
			this._static_options = StaticOptions.number(this);
			this.requestUpdate("select_options");
		}
	}
}

customElements.define("et2-select-number", Et2SelectNumber);