import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";

export class Et2SelectLang extends Et2StaticSelectMixin(Et2Select)
{
	constructor()
	{
		super();

		this._static_options = StaticOptions.lang(this, {other: this.other || []});
	}
}

customElements.define("et2-select-lang", Et2SelectLang);