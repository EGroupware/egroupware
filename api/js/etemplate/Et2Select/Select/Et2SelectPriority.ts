import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectPriority extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this._static_options = StaticOptions.priority(this);
	}
}

customElements.define("et2-select-priority", Et2SelectPriority);