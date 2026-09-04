import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";
import {PropertyValues} from 'lit';
import {property} from "lit/decorators/property.js";

export class Et2SelectNumber extends Et2StaticSelectMixin(Et2Select)
{
	/**
	 * Step between numbers
	 */
	@property({type: Number})
	interval : number = 1;

	@property({type: Number})
	min : number = 1;

	@property({type: Number})
	max : number = 10;

	/**
	 * Add one or more leading zeros
	 * Set to how many zeros you want (000)
	 */
	@property({type: String})
	leading_zero : string = "";

	/**
	 * Appended after every number
	 */
	@property({type: String})
	suffix : string = "";

	willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') ||
			changedProperties.has('leading_zero') || changedProperties.has('suffix'))
		{
			this._static_options = StaticOptions.number(this);
		}
	}
}

customElements.define("et2-select-number", Et2SelectNumber);
