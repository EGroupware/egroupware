import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";
import {cleanSelectOptions} from "../FindSelectOptions";

export class Et2SelectTimezone extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();
		this.fetchComplete = StaticOptions.timezone(this, {other: this.other ?? []}).then((options) =>
		{
			this.set_static_options(cleanSelectOptions(options));
		})
	}
}

customElements.define("et2-select-timezone", Et2SelectTimezone);