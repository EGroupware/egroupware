import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectBool extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this.static_options = StaticOptions.bool(this);
	}
}

customElements.define("et2-select-bool", Et2SelectBool);
