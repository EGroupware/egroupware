import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * External content of a JMAP-native body is blocked by the body document's CSP, not only by
 * deferExternalImages()'s `<img src>` rewrite: found live 2026-10-08, an Anthropic mail's inline
 * `style="background:url(https://claude.ai/...)"` was fetched under the "Ask for permission"
 * preference, without any banner offering to show it.
 *
 * Setup: a MailJmap with a fake egw whose preferences are set per test, calling the private
 * wrapDocument() / the public showExternalContent() directly.
 * Pass criteria: the CSP's img-src/media-src/font-src/style-src only allow our own origin (plus
 * the allowlisted https domains) unless the preference is "Always" or the content is shown;
 * plain http: is never allowed; the body is marked with the first blocked non-image url;
 * showExternalContent() restores deferred images and widens the CSP without touching the rest.
 */

function createFakeApp(overrides : {allowIMGs? : number, allowedDomains? : string[], imageProxy? : string} = {}) : MailApp
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
		link: (url : string) => "/egroupware"+url,
		webserverUrl: "/egroupware",
	};
	return {egw, getCustomLabels: () => ({}), image_proxy: overrides.imageProxy ?? "https://"} as unknown as MailApp;
}

function wrap(jmap : MailJmap, body : string, forMailvelope? : boolean) : Document
{
	return new DOMParser().parseFromString((jmap as any).wrapDocument(body, forMailvelope), "text/html");
}

function csp(doc : Document) : Record<string, string[]>
{
	const directives = {};
	doc.querySelector('meta[http-equiv="Content-Security-Policy"]').getAttribute("content").split(";")
		.map((directive) => directive.trim().split(/\s+/))
		.forEach(([name, ...sources]) => directives[name] = sources);
	return directives;
}

describe("MailJmap external content CSP", () =>
{
	it("only allows our own origin and data:/blob: under the 'Ask' (2) preference", () =>
	{
		const policy = csp(wrap(new MailJmap(createFakeApp({allowIMGs: 2})), "<p>hi</p>"));

		for (const directive of ["img-src", "media-src", "font-src", "style-src"])
		{
			assert.include(policy[directive], window.location.origin+"/egroupware/", directive);
			assert.notInclude(policy[directive], window.location.origin, directive);
			assert.notInclude(policy[directive], "https:", directive);
			assert.notInclude(policy[directive], "http:", directive);
		}
		assert.includeMembers(policy["img-src"], ["data:", "blob:"]);
	});

	it("allows exactly the allowlisted domains via https, skipping entries that are no hostname", () =>
	{
		const policy = csp(wrap(new MailJmap(createFakeApp({allowIMGs: 2,
			allowedDomains: ["example.com", "http://localhost/egroupware", "evil.com; script-src *"]})), "<p>hi</p>"));

		assert.include(policy["img-src"], "https://example.com");
		assert.notInclude(policy["img-src"], "http://example.com");
		assert.isFalse(Object.values(policy).flat().some((source) => source.startsWith("https://http") || source.includes("evil")),
			"a non-hostname allowlist entry must not end up in the policy");
		assert.deepEqual(policy["script-src"], ["'none'"]);
	});

	it("allows any https: external content, but never plain http:, under the 'Always' (1) preference", () =>
	{
		const policy = csp(wrap(new MailJmap(createFakeApp({allowIMGs: 1})), "<p>hi</p>"));

		assert.include(policy["img-src"], "https:");
		assert.notInclude(policy["img-src"], "http:");
	});

	it("allows the image proxy's path, so http: images can be shown through it", () =>
	{
		const policy = csp(wrap(new MailJmap(createFakeApp({imageProxy: "https://proxy.example.org/abc/"})), "<p>hi</p>"));

		assert.include(policy["img-src"], "https://proxy.example.org/abc/");
	});

	/**
	 * Found live 2026-10-08 with a tracker at https://<egroupware host>/trk/pixel.php: allowing our
	 * whole origin let every CSS/srcset/poster variant through - only EGroupware's path is ours.
	 */
	it("allows only EGroupware's path of its own origin, so the same host's other paths stay blocked", () =>
	{
		const html = (new MailJmap(createFakeApp({allowIMGs: 2})) as any).wrapDocument(
			`<div style="background:url('${window.location.origin}/trk/pixel.php?v=inline-style')"></div>`);
		const doc = new DOMParser().parseFromString(html, "text/html");

		assert.deepEqual(csp(doc)["img-src"], [window.location.origin+"/egroupware/", "blob:", "data:"]);
		assert.equal(doc.body.getAttribute("data-blocked-external"), `${window.location.origin}/trk/pixel.php?v=inline-style`);
	});

	it("marks the body with a blocked inline-style background url", () =>
	{
		const doc = wrap(new MailJmap(createFakeApp({allowIMGs: 2})),
			`<div style="background:url('https://claude.ai/images/email/hand_wave.gif') center / contain no-repeat"></div>`);

		assert.equal(doc.body.getAttribute("data-blocked-external"), "https://claude.ai/images/email/hand_wave.gif");
	});

	for (const [what, body, url] of [
		["a <style> url()", "<style>p { background: url(https://example.com/bg.png) }</style><p>hi</p>", "https://example.com/bg.png"],
		["a <style> @import", '<style>@import "https://example.com/fonts.css";</style><p>hi</p>', "https://example.com/fonts.css"],
		["a background attribute", '<table><tr><td background="https://example.com/bg.png">hi</td></tr></table>', "https://example.com/bg.png"],
		["a video poster", '<video poster="https://example.com/poster.png"></video>', "https://example.com/poster.png"],
		["a picture source srcset", '<picture><source srcset="https://example.com/a.webp 1x, https://example.com/b.webp 2x"></picture>', "https://example.com/a.webp"],
		["an SVG image href", '<svg><image href="https://example.com/a.png"></image></svg>', "https://example.com/a.png"],
	])
	{
		it(`marks the body with the blocked url of ${what}`, () =>
		{
			const doc = wrap(new MailJmap(createFakeApp({allowIMGs: 2})), body);

			assert.equal(doc.body.getAttribute("data-blocked-external"), url);
		});
	}

	it("does not mark the body for allowlisted, same-origin, data: or fragment urls", () =>
	{
		const doc = wrap(new MailJmap(createFakeApp({allowIMGs: 2, allowedDomains: ["example.com"]})),
			`<div style="background:url(https://example.com/bg.png)"></div>` +
			`<div style="background:url(/egroupware/api/templates/default/images/x.png)"></div>` +
			`<div style="background:url(data:image/png;base64,AAAA)"></div>` +
			`<svg><rect fill="url(#gradient)"></rect></svg>`);

		assert.isNull(doc.body.getAttribute("data-blocked-external"));
	});

	it("does not mark the body for the placeholder of an already deferred image", () =>
	{
		const jmap = new MailJmap(createFakeApp({allowIMGs: 2}));
		const doc = wrap(jmap, (jmap as any).deferExternalImages('<img src="https://example.com/logo.png">'));

		assert.isNull(doc.body.getAttribute("data-blocked-external"));
	});

	it("still marks the body for a plain http: background under the 'Always' (1) preference", () =>
	{
		const doc = wrap(new MailJmap(createFakeApp({allowIMGs: 1})), `<div style="background:url(http://example.com/bg.png)"></div>`);

		assert.equal(doc.body.getAttribute("data-blocked-external"), "http://example.com/bg.png");
	});
});

describe("MailJmap.showExternalContent()", () =>
{
	function show(jmap : MailJmap, body : string, forMailvelope? : boolean) : Document
	{
		const html = jmap.showExternalContent((jmap as any).wrapDocument((jmap as any).deferExternalImages(body), forMailvelope));
		return new DOMParser().parseFromString(html, "text/html");
	}

	it("restores a deferred image's src, alt and srcset", () =>
	{
		const doc = show(new MailJmap(createFakeApp({allowIMGs: 2})),
			'<img alt="Logo" src="https://example.com/logo.png" srcset="https://example.com/logo@2x.png 2x">');
		const img = doc.querySelector("img");

		assert.equal(img.getAttribute("src"), "https://example.com/logo.png");
		assert.equal(img.getAttribute("alt"), "Logo");
		assert.isNull(img.getAttribute("title"), "the title deferExternalImages() added must go");
		assert.equal(img.getAttribute("srcset"), "https://example.com/logo@2x.png 2x");
		assert.isNull(img.getAttribute("data-blocked-srcset"));
	});

	it("routes a deferred http: image through the image proxy", () =>
	{
		const doc = show(new MailJmap(createFakeApp({allowIMGs: 2, imageProxy: "https://proxy.example.org/abc/"})),
			'<img src="http://insecure.example.org/photo.png">');

		assert.equal(doc.querySelector("img").getAttribute("src"), "https://proxy.example.org/abc/insecure.example.org/photo.png");
	});

	it("allows https: external content, removes the blocked marker and keeps the other directives", () =>
	{
		const doc = show(new MailJmap(createFakeApp({allowIMGs: 2})),
			`<div style="background:url(https://claude.ai/images/email/hand_wave.gif)"></div>`, true);
		const policy = csp(doc);

		for (const directive of ["img-src", "media-src", "font-src", "style-src"])
		{
			assert.include(policy[directive], "https:", directive);
			assert.notInclude(policy[directive], "http:", directive);
		}
		assert.deepEqual(policy["script-src"], ["'none'"]);
		assert.deepEqual(policy["frame-src"], ["'self'"], "the Mailvelope frame-src must survive");
		assert.isNull(doc.body.getAttribute("data-blocked-external"));
		assert.isNotNull(doc.querySelector("div.mailDisplayBody td.td_display div[style]"), "the body itself is unchanged");
	});
});

/**
 * Found live 2026-10-09 (ralf): a blocked external url (eg. a tracking pixel) must not survive
 * into a reply/forward/edit-as-new's quoted body at all - unlike the message-VIEW iframe, the
 * compose editor (TinyMCE) has no CSP of its own, so deferExternalImages()'s soft, restorable
 * placeholder is not enough: the url would be fetched the instant it's rendered into the editor,
 * AND re-sent live as part of the new message, reaching the tracker on behalf of a recipient who
 * never got the original mail. sanitizeExternalContentForQuoting() hard-removes every blocked
 * reference instead (no alt-stashed url, no "Show"/"Allow" to restore it - there is no such UI
 * inside compose) while leaving an ALLOWED url (an allowlisted domain, or the 'Always'
 * preference) completely unchanged, for every vector findBlockedExternalUrl() already covers.
 */
describe("MailJmap.sanitizeExternalContentForQuoting()", () =>
{
	function sanitize(jmap : MailJmap, html : string) : string
	{
		return (jmap as any).sanitizeExternalContentForQuoting(html);
	}

	it("removes a blocked <img> entirely, not just its src", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp()),
			'<p>hi</p><img alt="tracker" src="https://tracker.example.com/pixel.gif">');

		assert.notInclude(html, "tracker.example.com");
		assert.isNull(new DOMParser().parseFromString(html, "text/html").querySelector("img"));
		assert.include(html, "<p>hi</p>");
	});

	it("leaves an allowlisted <img> completely unchanged", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp({allowedDomains: ["example.com"]})),
			'<img alt="logo" src="https://example.com/logo.png">');

		const img = new DOMParser().parseFromString(html, "text/html").querySelector("img");
		assert.equal(img.getAttribute("src"), "https://example.com/logo.png");
		assert.equal(img.getAttribute("alt"), "logo");
	});

	it("leaves any <img> unchanged under the 'Always' (1) preference", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp({allowIMGs: 1})),
			'<img src="https://tracker.example.com/pixel.gif">');

		assert.include(html, "tracker.example.com");
	});

	it("drops a blocked url() from an inline style attribute, keeping the rest of the declaration", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp()),
			'<div style="background:url(https://tracker.example.com/bg.gif) center / contain no-repeat; color: red"></div>');

		const div = new DOMParser().parseFromString(html, "text/html").querySelector("div");
		assert.notInclude(div.getAttribute("style"), "tracker.example.com");
		assert.include(div.getAttribute("style"), "color: red");
	});

	it("leaves an allowlisted url() in an inline style attribute unchanged", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp({allowedDomains: ["example.com"]})),
			'<div style="background:url(https://example.com/bg.png)"></div>');

		assert.include(new DOMParser().parseFromString(html, "text/html").querySelector("div").getAttribute("style"),
			"example.com/bg.png");
	});

	it("drops a blocked url() and @import from a <style> block, keeping an allowed rule", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp({allowedDomains: ["example.com"]})),
			'<style>' +
			'p { background: url(https://tracker.example.com/bg.gif) }' +
			'@import "https://tracker.example.com/fonts.css";' +
			'div { background: url(https://example.com/bg.png) }' +
			'</style>');

		const css = new DOMParser().parseFromString(html, "text/html").querySelector("style").textContent;
		assert.notInclude(css, "tracker.example.com");
		assert.include(css, "example.com/bg.png");
	});

	it("drops a blocked background/poster attribute, keeping the element", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp()),
			'<table><tr><td background="https://tracker.example.com/bg.gif">hi</td></tr></table>' +
			'<video poster="https://tracker.example.com/poster.png"></video>');

		const doc = new DOMParser().parseFromString(html, "text/html");
		assert.isFalse(doc.querySelector("td").hasAttribute("background"));
		assert.equal(doc.querySelector("td").textContent, "hi");
		assert.isFalse(doc.querySelector("video").hasAttribute("poster"));
	});

	it("filters a blocked candidate out of srcset, keeping an allowed one", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp({allowedDomains: ["example.com"]})),
			'<picture><source srcset="https://tracker.example.com/a.webp 1x, https://example.com/b.webp 2x"></picture>');

		assert.equal(new DOMParser().parseFromString(html, "text/html").querySelector("source").getAttribute("srcset"),
			"https://example.com/b.webp 2x");
	});

	it("removes the srcset attribute entirely once every candidate is blocked", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp({allowedDomains: ["example.com"]})),
			'<img src="https://example.com/a.png" srcset="https://tracker.example.com/a.webp 1x">');

		const img = new DOMParser().parseFromString(html, "text/html").querySelector("img");
		assert.equal(img.getAttribute("src"), "https://example.com/a.png", "the allowlisted src itself must survive");
		assert.isFalse(img.hasAttribute("srcset"));
	});

	it("removes a blocked SVG <image>/<use> href, keeping an svg <a> href untouched", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp()),
			'<svg xmlns="http://www.w3.org/2000/svg">' +
			'<image href="https://tracker.example.com/a.png"></image>' +
			'<a href="https://tracker.example.com/click"><text>link</text></a>' +
			'</svg>');

		const doc = new DOMParser().parseFromString(html, "text/html");
		assert.isFalse(doc.querySelector("image").hasAttribute("href"));
		assert.equal(doc.querySelector("a").getAttribute("href"), "https://tracker.example.com/click",
			"an SVG <a> is a real hyperlink, not a resource fetch - externalUrlFilter() rules apply to resources, not navigation");
	});

	it("never touches cid:/data: urls", () =>
	{
		const html = sanitize(new MailJmap(createFakeApp()),
			'<img src="cid:logo@example.com"><div style="background:url(data:image/png;base64,AAAA)"></div>');

		const doc = new DOMParser().parseFromString(html, "text/html");
		assert.equal(doc.querySelector("img").getAttribute("src"), "cid:logo@example.com");
		assert.include(doc.querySelector("div").getAttribute("style"), "data:image/png;base64,AAAA");
	});
});
