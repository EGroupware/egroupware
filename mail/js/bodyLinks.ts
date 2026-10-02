/**
 * Make every real link in a displayed message body open in a new tab, not navigate the
 * preview iframe itself away from the message.
 *
 * The message body iframe is served under a restrictive `frame-src` (a `'self'`/`'none'` CSP,
 * set both via HTTP header for the classic server-rendered path - mail_ui::get_load_email_data()
 * - and via the fast JMAP path's own <meta> tag - MailJmap.assembleBodyHtml(), jmap.ts). CSP's
 * frame-src governs any navigation of that nested browsing context, including one triggered by
 * clicking a plain `<a href>` inside it with no target - the browser silently refuses to
 * navigate the iframe itself to a disallowed (ie. any external) origin, with no visible feedback
 * at all. Forcing a NEW top-level tab (ctrl/cmd-click, middle-click, "open in new tab", or a
 * `target="_blank"` link) is a completely different kind of navigation - a fresh top-level
 * browsing context, not "nested" in anything - so it is never subject to frame-src, which is
 * exactly why those already worked while a plain left-click silently did nothing (ralf,
 * 2026-09-15: "Links aus E-Mails können nur per Rechtsklick oder STRG und nicht direkt via
 * Linksklick geöffnet werden", reported from Firefox but not actually Firefox-specific in cause -
 * every browser enforces frame-src the same way, Firefox just gives zero fallback/console
 * feedback for the blocked navigation, unlike Chrome).
 *
 * MailJmap.textToHtml() already does this correctly for its own auto-linked bare URLs in a
 * plain-text message; this covers the far more common case, real `<a>` tags already present in
 * an HTML message's own markup, for both render paths uniformly - called from each path's own
 * iframe 'load' listener (mail/js/app.ts), operating on the live DOM rather than the HTML string,
 * so it needs no server-side re-sanitization.
 *
 * @param doc body iframe's contentDocument
 */
export function openLinksInNewTab(doc : Document) : void
{
	doc.querySelectorAll('a[href]').forEach((link : HTMLAnchorElement) =>
	{
		if (!link.target)
		{
			link.target = '_blank';
		}
		link.rel = 'noopener noreferrer';
	});
}

/**
 * Activate mailto: and internal EGroupware links in a displayed message body
 *
 * mailto: opens mail compose, a link to our own index.php opens as the entry's popup (via the
 * link registry, if its menuaction matches one), or as a plain popup otherwise.
 *
 * Attached from the outer page, instead of running preview.js inside the body iframe: the fast
 * JMAP path renders into a srcdoc iframe, which has neither an egw object nor a real location of
 * its own ("about:srcdoc"), and a separate script loaded into it would need its own cache-buster.
 * The classic server-rendered body still loads preview.js itself, so this must not be called for it.
 *
 * @param doc body iframe's contentDocument
 * @param egw used to open compose or the popup
 * @param origin our own origin, to recognize internal links
 */
export function activateBodyLinks(doc: Document, egw: {
		open(id: string|null, app: string, type: string, extra?: object, target?: string): any,
		openPopup(url: string, width: number, height: number, windowName?: string): any,
		link_get_registry(app: string): any
	}, origin: string = window.location.origin): void
{
	doc.body?.addEventListener('click', (event: MouseEvent) =>
	{
		const link = (event.target as Element)?.closest?.('a[href]') as HTMLAnchorElement;
		if (!link || !doc.body.contains(link))
		{
			return;
		}
		if (link.href.startsWith('mailto:'))
		{
			egw.open(null, 'mail', 'add', {send_to: btoa(link.href.substring(7).replace('%40', '@'))});
			event.preventDefault();
			return;
		}
		// open links with own origin and "index.php?" as popup (not e.g. share.php or *dav.php)
		let url: URL;
		try
		{
			url = new URL(link.href);
		}
		catch (e)
		{
			return;
		}
		if (url.origin !== origin || !/\/index.php\?/.test(link.href))
		{
			return;
		}
		event.preventDefault();

		// First check link registry and just use that if we match
		const params = url.searchParams;
		const menuaction = params.get('menuaction') || '';
		const app = menuaction.split('.')[0];
		const registry = app ? egw.link_get_registry(app) ?? {} : {};
		for (const type in registry)
		{
			const value = registry[type];
			const idParam = registry[type + '_id'];
			if ((value === menuaction || value?.menuaction === menuaction) && idParam && params.has(idParam))
			{
				// Pass only desired parameters
				const extra = {};
				params.forEach((v, k) =>
				{
					if (!['menuaction', 'no_popup', idParam].includes(k))
					{
						extra[k] = v;
					}
				});
				egw.open(params.get(idParam), app, type, extra, registry[type + '_popup'] ? '_blank' : '_self');
				return;
			}
		}
		// No link registry match, but still ours - always use a popup
		egw.openPopup(link.href.replace(/([?&])no_popup=[^&]*/, '$1'), 800, 600, '_blank');
	});
}
