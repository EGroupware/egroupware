import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * A real customer's forum report (2026-09-23, "EGw Logo wird im Newsletter nicht geladen
 * Firefox"): a newsletter's own http:// logo silently failed to load, with no "show images"
 * prompt or image_proxy fallback ever offered. Root cause: when the mail app's HTML body
 * rendering moved from server-rendered (Api\Html\HtmLawed::purify(), which blocks external
 * images for privacy and always routes a plain http: image through the configured image_proxy -
 * see IMAGE_PROXY_CONFIG in mail/src/Ui.php) to this client-side DOMPurify path
 * (MailJmap.assembleBodyHtml()), no equivalent step existed at all - every `<img src>` passed
 * straight through untouched, so MailApp.resolveExternalImages() (which expects the
 * `alt="... [blocked external image:<url>]"` + placeholder-src convention HtmLawed used to
 * produce) never found anything to act on.
 *
 * deferExternalImages() restores that convention client-side, mirroring HtmLawed's own
 * blocking condition exactly: `($allowIMGs != 1 && !in_array($domain, $domains)) || $isHttp`.
 */

function createFakeApp(overrides : {allowIMGs? : number, allowedDomains? : string[], webserverUrl? : string} = {}) : MailApp
{
	const egw = {
		user: (_key : string) => 1,
		lang: (label : string) => label,
		preference: (key : string, _app? : string) =>
		{
			if (key === "allowExternalIMGs") return overrides.allowIMGs ?? 2;
			if (key === "allowExternalDomains") return overrides.allowedDomains ?? [];
			return null;
		},
		config: (_name : string, _app? : string) => null,
		request: async() => ({}),
		message: (_msg : string, _type? : string) => {},
		image: (_name : string, _app? : string) => "/egroupware/api/templates/default/images/no-image-shown.png",
		webserverUrl: overrides.webserverUrl ?? "/egroupware",
	};
	return {egw, getCustomLabels: () => ({})} as unknown as MailApp;
}

function defer(jmap : MailJmap, html : string) : Document
{
	const out = (jmap as any).deferExternalImages(html);
	return new DOMParser().parseFromString(out, "text/html");
}

describe("MailJmap.deferExternalImages()", () =>
{
	it("defers a plain https image from a non-allowlisted domain when the preference is 'Ask' (2)", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2}));
		const doc = defer(jmap, '<img src="https://example.com/logo.png">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "/egroupware/api/templates/default/images/no-image-shown.png");
		assert.include(img.getAttribute("alt"), "[blocked external image:https://example.com/logo.png]");
		assert.equal(img.getAttribute("title"), img.getAttribute("alt"));
	});

	it("leaves a plain https image untouched when the preference is 'Always' (1)", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 1}));
		const doc = defer(jmap, '<img src="https://example.com/logo.png">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "https://example.com/logo.png");
		assert.isNull(img.getAttribute("alt"));
	});

	it("still defers a plain http image even when the preference is 'Always' (1) - forced through image_proxy regardless", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 1}));
		const doc = defer(jmap, '<img src="http://example.com/logo.png">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "/egroupware/api/templates/default/images/no-image-shown.png");
		assert.include(img.getAttribute("alt"), "[blocked external image:http://example.com/logo.png]");
	});

	it("leaves an https image from an explicitly allowlisted domain untouched, even with 'Ask' (2)", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2, allowedDomains: ["example.com"]}));
		const doc = defer(jmap, '<img src="https://example.com/logo.png">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "https://example.com/logo.png");
		assert.isNull(img.getAttribute("alt"));
	});

	it("still defers a plain http image from an allowlisted domain - the allowlist never overrides the http: safety net", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2, allowedDomains: ["example.com"]}));
		const doc = defer(jmap, '<img src="http://example.com/logo.png">');
		const img = doc.querySelector("img");

		assert.include(img.getAttribute("alt"), "[blocked external image:http://example.com/logo.png]");
	});

	it("leaves a cid: image completely untouched - handled separately by deferCidImages()", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2}));
		const doc = defer(jmap, '<img src="cid:logo@egroupware">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "cid:logo@egroupware");
		assert.isNull(img.getAttribute("alt"));
	});

	it("leaves a data: URI image untouched", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2}));
		const doc = defer(jmap, '<img src="data:image/png;base64,AAAA">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "data:image/png;base64,AAAA");
		assert.isNull(img.getAttribute("alt"));
	});

	it("leaves a same-origin (own webserverUrl) image untouched", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2, webserverUrl: "https://my.egroupware.example"}));
		const doc = defer(jmap, '<img src="https://my.egroupware.example/api/templates/default/images/foo.png">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "https://my.egroupware.example/api/templates/default/images/foo.png");
		assert.isNull(img.getAttribute("alt"));
	});

	it("preserves an existing alt text, appending the blocked marker rather than replacing it", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2}));
		const doc = defer(jmap, '<img alt="Company logo" src="https://example.com/logo.png">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("alt"), "Company logo [blocked external image:https://example.com/logo.png]");
	});
});
