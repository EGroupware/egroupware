import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions} from "../StaticOptions";
import {cleanSelectOptions} from "../FindSelectOptions";

export class Et2SelectLang extends Et2StaticSelectMixin(Et2Select)
{
	connectedCallback()
	{
		super.connectedCallback();

		// Wait for connected instead of constructor because attributes make a difference in
		// which options are offered
		this.fetchComplete = StaticOptions.lang(this, {other: this.other || []}).then(options =>
		{
			this.set_static_options(cleanSelectOptions(options));
		});
	}
}

customElements.define("et2-select-lang", Et2SelectLang);