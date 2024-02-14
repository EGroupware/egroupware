import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectAccess extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this._static_options = StaticOptions.access(this);
	}
}

customElements.define("et2-select-access", Et2SelectAccess);
