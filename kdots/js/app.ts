/**
 * app.ts is auto-built
 */

import {EgwFramework} from "./EgwFramework";
import {EgwFrameworkApp} from "./EgwFrameworkApp";
import {EgwDarkmodeToggle} from "./EgwDarkmodeToggle";


document.addEventListener('DOMContentLoaded', () =>
{
	// Not sure what's up here, but it makes sure everything is loaded
	if(!window.customElements.get("egw-framework"))
	{
		window.customElements.define("egw-framework", EgwFramework);
	}
	if(!window.customElements.get("egw-app"))
	{
		window.customElements.define("egw-app", EgwFrameworkApp);
	}
	if(!window.customElements.get("egw-darkmode-toggle"))
	{
		window.customElements.define("egw-darkmode-toggle", EgwDarkmodeToggle);
	}
	/* Set up listener on avatar menu */
	const avatarMenu = document.querySelector("#topmenu_info_user_avatar");
	if(avatarMenu)
	{
		avatarMenu.addEventListener("sl-select", (e : CustomEvent) =>
		{
			window.egw.open_link(e.detail.item.value);
		});
	}
});