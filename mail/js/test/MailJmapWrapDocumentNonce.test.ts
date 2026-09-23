import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket-driven regression (2026-09-23, real customer, Firefox, "every mail opened in preview"):
 * script-src 'self' in the srcdoc body iframe's own <meta http-equiv="Content-Security-Policy">
 * failed to resolve against the iframe's inherited parent origin in Firefox, outright blocking
 * preview.js (mailto:/internal-EGroupware-link activation) for every single message. A nonce is
 * origin-resolution-independent - see wrapDocument()'s own docblock.
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

describe("MailJmap.wrapDocument() - CSP script-src nonce for preview.js", () =>
{
	it("the <script> tag's nonce attribute matches the CSP meta tag's nonce-<value>", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const html = wrap(jmap, "<p>hi</p>");

		const cspMatch = html.match(/Content-Security-Policy" content="[^"]*script-src[^;"]*'nonce-([^']+)'/);
		const scriptMatch = html.match(/<script defer nonce="([^"]+)"/);

		assert.isNotNull(cspMatch, "CSP meta tag must carry a nonce-<value> in script-src");
		assert.isNotNull(scriptMatch, "the <script> tag must carry a matching nonce attribute");
		assert.equal(scriptMatch[1], cspMatch[1]);
	});

	it("still allows 'self' alongside the nonce - browsers that DO resolve it correctly keep working unchanged", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const html = wrap(jmap, "<p>hi</p>");

		assert.match(html, /script-src 'self' 'nonce-[^']+'/);
	});

	it("generates a fresh, different nonce on every call - not a fixed/predictable value", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const first = wrap(jmap, "<p>hi</p>").match(/<script defer nonce="([^"]+)"/)[1];
		const second = wrap(jmap, "<p>hi</p>").match(/<script defer nonce="([^"]+)"/)[1];

		assert.notEqual(first, second);
	});

	it("the nonce mechanism is present regardless of the forMailvelope branch", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const html = wrap(jmap, "<p>hi</p>", true);

		const cspMatch = html.match(/'nonce-([^']+)'/);
		const scriptMatch = html.match(/<script defer nonce="([^"]+)"/);
		assert.isNotNull(cspMatch);
		assert.equal(scriptMatch[1], cspMatch[1]);
		assert.include(html, "frame-src 'self'", "forMailvelope must still leave frame-src 'self', unrelated to the nonce fix");
	});
});
