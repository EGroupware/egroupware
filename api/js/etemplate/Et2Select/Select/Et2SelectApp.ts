import {Et2Select} from "../Et2Select";
import {Et2StaticSelectMixin, StaticOptions as so} from "../StaticOptions";
import {cleanSelectOptions} from "../FindSelectOptions";
import {property} from "lit/decorators/property.js";

export class Et2SelectApp extends Et2StaticSelectMixin(Et2Select)
{
	/**
	 * Which apps to show: 'user'=apps of current user, 'enabled', 'installed' (default), 'all' = not installed ones too, 'all+setup'
 	 */
	@property({type: String})
	apps : 'user' | 'enabled' | 'installed' | 'all' | 'all+setup' = 'installed';

	public connectedCallback()
	{
		super.connectedCallback()
		this.fetchComplete = so.app(this, {apps: this.apps}).then((options) =>
		{
			this.set_static_options(cleanSelectOptions(options));
		})
	}
}

customElements.define("et2-select-app", Et2SelectApp);