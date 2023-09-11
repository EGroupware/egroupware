import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "../StaticOptions";

export class Et2SelectDay extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this._static_options = so.day(this, {});
	}
}

customElements.define("et2-select-day", Et2SelectDay);