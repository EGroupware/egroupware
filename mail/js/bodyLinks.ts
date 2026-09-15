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
