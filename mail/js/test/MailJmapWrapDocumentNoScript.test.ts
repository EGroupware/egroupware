import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * The srcdoc body document runs no script of its own: link activation is attached from the outer
 * page (bodyLinks.ts's activateBodyLinks()), so preview.js is no longer loaded into it - which
 * needed a CSP nonce (Firefox did not resolve 'self' for a srcdoc meta-tag CSP) and a cache-buster.
 */

function createFakeApp() : MailApp
{
	const egw = {
		user: (_key : string) => 1,
		lang: (label : string) => label,
		preference: (_key : string, _app? : string) => null,
		config: (_name : string, _app? : string) => null,
		request: async() => ({}),
		message: (_msg : string, _type? : string) => {},
		link: (url : string) => url,
	};
	return {egw, getCustomLabels: () => ({})} as unknown as MailApp;
}

function wrap(jmap : MailJmap, body : string, forMailvelope? : boolean) : string
{
	return (jmap as any).wrapDocument(body, forMailvelope);
}

describe("MailJmap.wrapDocument() - no script in the srcdoc body", () =>
{
	it("loads no <script> at all (no preview.js)", () =>
	{
		const html = wrap(new MailJmap(createFakeApp()), "<p>hi</p>");

		assert.notInclude(html, "<script");
		assert.notInclude(html, "preview.js");
	});

	it("forbids any script via its CSP", () =>
	{
		const html = wrap(new MailJmap(createFakeApp()), "<p>hi</p>");

		assert.match(html, /Content-Security-Policy" content="[^"]*script-src 'none'/);
	});

	it("keeps script-src 'none' for the forMailvelope branch, which only relaxes frame-src", () =>
	{
		const html = wrap(new MailJmap(createFakeApp()), "<p>hi</p>", true);

		assert.include(html, "script-src 'none'");
		assert.include(html, "frame-src 'self'");
	});

	/**
	 * Ticket #126241 follow-up: an ALLOWED external image (allowlisted domain, or 'Always') can
	 * still render as a broken image - many sites hotlink-protect based on the Referer header,
	 * which a plain <img src> cross-origin fetch sends by default. no-referrer also stops this
	 * document's own url leaking to every external image/link target in a received mail body.
	 */
	it("sends no referrer at all, so a hotlink-protected external image/link doesn't see this page's url", () =>
	{
		const html = wrap(new MailJmap(createFakeApp()), "<p>hi</p>");

		assert.match(html, /<meta name="referrer" content="no-referrer">/);
	});
});
