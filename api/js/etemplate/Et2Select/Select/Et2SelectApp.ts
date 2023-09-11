import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "../StaticOptions";
import {cleanSelectOptions} from "../FindSelectOptions";

export class Et2SelectApp extends Et2StaticSelectMixin(Et2Select)
{
	public connectedCallback()
	{
		super.connectedCallback()
		this.fetchComplete = so.app(this, {}).then((options) =>
		{
			this.set_static_options(cleanSelectOptions(options));
		})
	}
}

customElements.define("et2-select-app", Et2SelectApp);