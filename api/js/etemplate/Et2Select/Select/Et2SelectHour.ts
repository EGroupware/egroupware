import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectHour extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this.static_options = StaticOptions.hour(this, {other: this.other || []});
	}
}

customElements.define("et2-select-hour", Et2SelectHour);