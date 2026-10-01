import {assert} from "@open-wc/testing";
import {activateBodyLinks, openLinksInNewTab} from "../bodyLinks";

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

/**
 * Test activateBodyLinks() - internal EGroupware links in a mail body opened a plain new tab
 * instead of the entry's popup, since the JMAP path renders the body into a srcdoc iframe, where
 * preview.js's own-origin check against location ("about:srcdoc", empty host) never matched.
 * Now attached from the outer page, comparing against the outer page's origin.
 */
describe("activateBodyLinks()", () =>
{
	const origin = "https://egw.example.org";

	function fakeEgw(registry : object = {})
	{
		const calls = {open: [] as any[][], openPopup: [] as any[][]};
		const egw = {
			open: (...args : any[]) => { calls.open.push(args); },
			openPopup: (...args : any[]) => { calls.openPopup.push(args); },
			link_get_registry: (_app : string) => registry,
		};
		return {egw: egw as any, calls};
	}

	function click(doc : Document, selector : string = "a") : MouseEvent
	{
		const event = new MouseEvent("click", {bubbles: true, cancelable: true});
		doc.querySelector(selector).dispatchEvent(event);
		return event;
	}

	const trackerRegistry = {
		view: {menuaction: "tracker.tracker_ui.edit"},
		view_id: "tr_id",
		view_popup: "780x720",
	};

	it("opens an internal link matching the link registry as the entry's popup", () =>
	{
		const doc = bodyDocument(`<a href="${origin}/egroupware/index.php?menuaction=tracker.tracker_ui.edit&tr_id=4">#4</a>`);
		const {egw, calls} = fakeEgw(trackerRegistry);
		activateBodyLinks(doc, egw, origin);

		const event = click(doc);

		assert.isTrue(event.defaultPrevented);
		assert.deepEqual(calls.open, [["4", "tracker", "view", {}, "_blank"]]);
		assert.isEmpty(calls.openPopup);
	});

	it("passes extra query parameters on, but not menuaction, no_popup or the id", () =>
	{
		const doc = bodyDocument(`<a href="${origin}/egroupware/index.php?menuaction=tracker.tracker_ui.edit&tr_id=4&no_popup=1&tab=history">#4</a>`);
		const {egw, calls} = fakeEgw(trackerRegistry);
		activateBodyLinks(doc, egw, origin);

		click(doc);

		assert.deepEqual(calls.open, [["4", "tracker", "view", {tab: "history"}, "_blank"]]);
	});

	it("opens an internal index.php link without a registry match as a plain popup, without no_popup", () =>
	{
		const doc = bodyDocument(`<a href="${origin}/egroupware/index.php?menuaction=foo.bar.baz&no_popup=1&x=1">x</a>`);
		const {egw, calls} = fakeEgw({});
		activateBodyLinks(doc, egw, origin);

		const event = click(doc);

		assert.isTrue(event.defaultPrevented);
		assert.isEmpty(calls.open);
		assert.deepEqual(calls.openPopup, [[`${origin}/egroupware/index.php?menuaction=foo.bar.baz&&x=1`, 800, 600, "_blank"]]);
	});

	it("opens mail compose for a mailto: link", () =>
	{
		const doc = bodyDocument('<a href="mailto:someone%40example.org">mail</a>');
		const {egw, calls} = fakeEgw();
		activateBodyLinks(doc, egw, origin);

		const event = click(doc);

		assert.isTrue(event.defaultPrevented);
		assert.deepEqual(calls.open, [[null, "mail", "add", {send_to: btoa("someone@example.org")}]]);
	});

	it("leaves external links, and our own non-index.php ones (eg. share.php), to the browser", () =>
	{
		const doc = bodyDocument(
			'<a id="ext" href="https://example.com/index.php?x=1">ext</a>' +
			`<a id="share" href="${origin}/egroupware/share.php/abc">share</a>`
		);
		const {egw, calls} = fakeEgw(trackerRegistry);
		activateBodyLinks(doc, egw, origin);

		assert.isFalse(click(doc, "#ext").defaultPrevented);
		assert.isFalse(click(doc, "#share").defaultPrevented);
		assert.isEmpty(calls.open);
		assert.isEmpty(calls.openPopup);
	});

	it("handles a click on an element inside the link", () =>
	{
		const doc = bodyDocument(`<a href="${origin}/egroupware/index.php?menuaction=tracker.tracker_ui.edit&tr_id=4"><b>#4</b></a>`);
		const {egw, calls} = fakeEgw(trackerRegistry);
		activateBodyLinks(doc, egw, origin);

		click(doc, "b");

		assert.equal(calls.open.length, 1);
	});
});
