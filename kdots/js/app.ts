/**
 * app.ts is auto-built
 */

import "./EgwFramework";
import "./EgwApp";


document.addEventListener('DOMContentLoaded', () =>
{
	/* Set up listener on avatar menu */
	const avatarMenu = document.querySelector("#topmenu_info_user_avatar");
	avatarMenu.addEventListener("sl-select", (e : CustomEvent) =>
	{
		window.egw.open_link(e.detail.item.value);
	});

	/* Listener on placeholder checkbox */
	// TODO: Remove this & the switch
	document.querySelector("#placeholders").addEventListener("sl-change", (e) =>
	{
		document.querySelector("egw-framework").classList.toggle("placeholder", e.target.checked);
		document.querySelector("egw-app").classList.toggle("placeholder", e.target.checked);
	});
});