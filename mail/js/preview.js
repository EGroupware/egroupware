/**
 * EGroupware Mail - handle mailto and other links in preview
 *
 * @link http://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2014 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
*/


document.body.addEventListener('click', function (event)
{
	//event.target might not be a link
	const link = event.target.closest('a[href]');
	if (link && document.body.contains(link))
	{
		// active mailto: links with mail compose
		if (link.href.substr(0, 7) == 'mailto:')
		{
			top.egw.open(null, 'mail', 'add', {send_to: btoa(link.href.substr(7).replace('%40', '@'))});
			event.preventDefault();
			return false;
		}
		// open links with own origin and "index.php?" as popup (not e.g. share.php or *dav.php)
		else if ((link.href[0] === '/' || link.href.match(new RegExp('^' + location.protocol + '//' + location.host + '/'))) &&
			link.href.match(/\/index.php\?/))
		{
			// First check link registry and just use that if we match
			const params = (new URL(link.href)).searchParams;
			if (params.has("menuaction"))
			{
				const menuaction = params.get("menuaction") || "";
				const app = menuaction.split(".")[0] ?? "";
				const registry = egw.link_get_registry(app) ?? {};
				for (const key in registry)
				{
					const value = registry[key];
					let type = "";
					if (app && (typeof value === "string" && value == menuaction || value.menuaction && value.menuaction == menuaction))
					{
						type = key;
						const entry_id = type + '_id';
						if (typeof registry[entry_id] && params.has(registry[entry_id]))
						{
							// Pass only desired parameters
							const extra = {};
							params.entries().forEach(([k, v]) =>
							{
								if (["menuaction", "no_popup", registry[entry_id]].includes(k))
								{
									return;
								}
								extra[k] = v;
							});
							top.egw.open(params.get(registry[entry_id]), app, type, extra, '_self');
							event.preventDefault();
							return false;
						}
					}
				}
			}
			// No link registry match, but still ours - always use a popup
			top.egw.openPopup(link.href.replace(/([?&])no_popup=[^&]*/, '$1'), 800, 600, '_blank');
			event.preventDefault();
			return false;
		} else
		{ // add target=_blank to all other links, gives CSP error and would open in preview
			link.target = '_blank';
		}
	}
});