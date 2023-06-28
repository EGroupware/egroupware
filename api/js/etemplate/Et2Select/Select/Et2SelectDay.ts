import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectDay extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this.static_options = StaticOptions.day(this, {other: this.other || []});
	}
}

customElements.define("et2-select-day", Et2SelectDay);