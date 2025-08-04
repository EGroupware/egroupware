/**
 * app.ts is auto-built
 */

import {EgwFramework} from "./EgwFramework";
import {EgwFrameworkApp} from "./EgwFrameworkApp";
import {EgwDarkmodeToggle} from "./EgwDarkmodeToggle";

(<EgwFramework>document.querySelector("egw-framework")).updateComplete.then(() =>
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
	const framework = <EgwFramework>document.querySelector("egw-framework");
	// Quick add
	framework.egw.link_quick_add(<HTMLElement>framework.querySelector('#egw_fw_topmenu_info_items'));

	// Ask about timer before logout
	const logout = framework.querySelector('#topmenu_logout');
	logout.addEventListener('click', async(e) =>
	{
		e.preventDefault();
		e.stopImmediatePropagation();
		await framework.egw.onLogout_timer();
		framework.egw.open_link(e.target.value);
	});

	/* Set up listener on avatar menu */
	const avatarMenu = document.querySelector("#topmenu_info_user_avatar");
	if(avatarMenu)
	{
		avatarMenu.addEventListener("sl-select", (e : CustomEvent) =>
		{
			// allowing javascript urls in topmenu and sidebox only under CSP by binding click handlers to them
			const href_regexp = /^javascript:([^\(]+)\((.*)?\);?$/;
			let matches = e.detail.item.value.replaceAll(/%27/g, "'").replaceAll(/%22/g, '"').match(href_regexp);
			let args = [];
			if(matches.length > 1)
			{
				matches[2] = typeof matches[2] == "undefined" ? [] : matches[2];
				try
				{
					args = JSON.parse('[' + matches[2] + ']');
				}

				catch(e)
				{
					// deal with '-enclosed strings (JSON allows only ")
					args = JSON.parse('[' + matches[2].replace(/','/g, '","').replace(/((^|,)'|'(,|$))/g, '$2"$3') + ']');
				}
				args.unshift(matches[1]);
				if(matches[1] !== 'void')
				{
					return et2_call.apply(this, args);
				}
				return false;
			}
			window.egw.open_link(e.detail.item.value);
		});
	}
});