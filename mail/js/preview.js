/**
 * EGroupware Mail - handle mailto and other links in preview
 *
 * @link http://www.egroupware.org
 * @author EGroupware GmbH [info@egroupware.org]
 * @copyright (c) 2014 by EGroupware GmbH <info-AT-egroupware.org>
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/**
 * Resolve a bare PDF's base64 payload (Jmap\Imap::structureToHtml()'s "whole message is one PDF,
 * no separate body" branch, ticket #125171) into a real blob: URL, client-side - Chrome's built-in
 * PDF viewer flatly refuses to render a PDF from a data: URI at all (confirmed live: neither
 * <embed> nor <iframe> with a data:application/pdf;base64,... src shows anything but a blank/
 * broken-plugin area, no CSP/iframe-nesting involved), so the server can't just embed a data: URI
 * directly the way it does for an image (a data: URI works fine for <img>, unrelated to this PDF-
 * viewer-specific restriction). preview.js is already loaded unmodified on both this srcdoc iframe
 * and the classic full-page fallback (MailJmap.wrapDocument()'s own docblock), so this needs no
 * separate wiring per context - it just runs once, here, whichever page loaded it.
 *
 * Runs on DOMContentLoaded explicitly rather than trusting this <script defer>'s own top-level
 * execution timing - found live (ralf): the embed's data-bare-pdf-base64 attribute was still
 * unresolved (src never set) even though this script tag's src clearly DID load (fetching it
 * separately returned this exact code) - a srcdoc iframe's `defer` timing is apparently not
 * reliable enough here to assume the DOM is already fully parsed by the time this file's own
 * top-level code would otherwise run.
 */
function egwMailPreviewResolveBarePdfEmbeds()
{
	document.querySelectorAll('embed[data-bare-pdf-base64]').forEach(function (embed)
	{
		const base64 = embed.getAttribute('data-bare-pdf-base64');
		embed.removeAttribute('data-bare-pdf-base64');
		try
		{
			const binary = atob(base64);
			const bytes = new Uint8Array(binary.length);
			for (let i = 0; i < binary.length; i++)
			{
				bytes[i] = binary.charCodeAt(i);
			}
			embed.src = URL.createObjectURL(new Blob([bytes], {type: 'application/pdf'}));
		}
		catch (e)
		{
			console.error('preview.js: failed to resolve a bare PDF embed', e);
		}
	});
}
if (document.readyState === 'loading')
{
	document.addEventListener('DOMContentLoaded', egwMailPreviewResolveBarePdfEmbeds);
}
else
{
	egwMailPreviewResolveBarePdfEmbeds();
}

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
							top.egw.open(params.get(registry[entry_id]), app, type, extra, registry[type + '_popup'] ? '_blank' : '_self');
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