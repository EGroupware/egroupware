import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectBool extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this._static_options = StaticOptions.bool(this);
	}

	get value()
	{
		return super.value;
	}

	/**
	 * Boolean option values are "0" and "1", so change boolean to those
	 * @param {string | string[]} new_value
	 */
	set value(new_value)
	{
		super.value = new_value ? "1" : "0";
	}
}

customElements.define("et2-select-bool", Et2SelectBool);
