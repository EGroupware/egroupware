import {assert} from "@open-wc/testing";
import {openLinksInNewTab} from "../bodyLinks";

/**
 * Test openLinksInNewTab() - tracker report (ralf, 2026-09-15): "Links aus E-Mails können nur
 * per Rechtsklick oder STRG und nicht direkt via Linksklick geöffnet werden" (links in emails
 * only open via right-click or ctrl, not a plain left-click). Root cause: the message body
 * iframe is served under a restrictive `frame-src` CSP, which governs any navigation of that
 * nested browsing context - including a plain link click with no target, which the browser
 * silently refuses to let navigate the iframe itself to an external origin. Forcing a NEW tab
 * (ctrl-click, or a real `target="_blank"` link) is a top-level navigation, never subject to
 * frame-src - hence why those already worked. Fixed by tagging every real link with
 * target="_blank" once the body has loaded, so a plain left-click behaves the same way.
 *
 * Pure DOM manipulation against a real Document - no iframe needed for the test itself.
 */
function bodyDocument(bodyHtml : string) : Document
{
	const doc = document.implementation.createHTMLDocument("");
	doc.body.innerHTML = bodyHtml;
	return doc;
}

describe("openLinksInNewTab()", () =>
{
	it("gives a plain link (no target at all) target=_blank, so a left-click opens a new tab instead of silently failing to navigate the iframe", () =>
	{
		const doc = bodyDocument('<a href="https://example.com">click me</a>');

		openLinksInNewTab(doc);

		const link = doc.querySelector("a");
		assert.equal(link.target, "_blank");
	});

	it("adds rel=noopener noreferrer alongside the new target, so the new tab can't reach back into this window", () =>
	{
		const doc = bodyDocument('<a href="https://example.com">click me</a>');

		openLinksInNewTab(doc);

		const link = doc.querySelector("a");
		assert.equal(link.rel, "noopener noreferrer");
	});

	it("leaves an already-targeted link's target untouched (eg. a sender's own target=_self)", () =>
	{
		const doc = bodyDocument('<a href="https://example.com" target="_self">click me</a>');

		openLinksInNewTab(doc);

		const link = doc.querySelector("a");
		assert.equal(link.target, "_self");
	});

	it("handles multiple links, and a link with no href at all, without throwing", () =>
	{
		const doc = bodyDocument(
			'<a href="https://example.com/a">a</a>' +
			'<a name="anchor-only">no href</a>' +
			'<a href="https://example.com/b">b</a>'
		);

		assert.doesNotThrow(() => openLinksInNewTab(doc));

		const links = Array.from(doc.querySelectorAll("a[href]")) as HTMLAnchorElement[];
		assert.equal(links.length, 2);
		links.forEach(link => assert.equal(link.target, "_blank"));
	});

	it("does nothing when the body has no links at all", () =>
	{
		const doc = bodyDocument('<p>no links here</p>');

		assert.doesNotThrow(() => openLinksInNewTab(doc));
	});
});
