import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectTimezone extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this._static_options = StaticOptions.timezone(this, {other: this.other || []});
	}
}

customElements.define("et2-select-timezone", Et2SelectTimezone);