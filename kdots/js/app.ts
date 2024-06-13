/**
 * app.ts is auto-built
 */

import {EgwFramework} from "./EgwFramework";
import {EgwFrameworkApp} from "./EgwFrameworkApp";


document.addEventListener('DOMContentLoaded', () =>
{
	// Not sure what's up here
	if(!window.customElements.get("egw-framework"))
	{
		window.customElements.define("egw-framework", EgwFramework);
	}
	if(!window.customElements.get("egw-app"))
	{
		window.customElements.define("egw-app", EgwFrameworkApp);
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