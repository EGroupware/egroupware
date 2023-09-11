import {Et2SelectNumber} from "./Et2SelectNumber";
import {PropertyValues} from "lit";
import {StaticOptions} from "../StaticOptions";

export class Et2SelectYear extends Et2SelectNumber
{
	constructor()
	{
		super();
		this.min = -3;
		this.max = 2;
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has('min') || changedProperties.has('max') || changedProperties.has('interval') || changedProperties.has('suffix'))
		{
			this._static_options = StaticOptions.year(this);
		}
	}
}

customElements.define("et2-select-year", Et2SelectYear);