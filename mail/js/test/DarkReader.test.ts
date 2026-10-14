import {assert} from "@open-wc/testing";
import {adaptMessageForDarkMode, contrast, luminance, restoreMessageFromDarkMode} from "../darkReader";

/**
 * Test the port of Thunderbird's "Dark Reader" (mail/js/darkReader.ts): showing a message in dark mode
 * removes the colors the message sets itself, which are not readable on a dark background,
 * and restoreMessageFromDarkMode() brings the message back exactly as it was, when the user switches dark mode off.
 *
 * Needs a real iframe, as adaptMessageForDarkMode() reads computed styles via the document's window.
 */
async function bodyDocument(html: string): Promise<Document>
{
	const iframe = document.createElement("iframe");
	const loaded = new Promise(resolve => iframe.addEventListener("load", resolve, {once: true}));
	iframe.srcdoc = html;
	document.body.append(iframe);
	await loaded;
	return iframe.contentDocument;
}

describe("darkReader", () =>
{
	afterEach(() => document.querySelectorAll("iframe").forEach(iframe => iframe.remove()));

	it("calculates luminance and contrast of colors, without Thunderbird's Gecko-only InspectorUtils", () =>
	{
		assert.closeTo(luminance("#ffffff"), 255, 0.01);
		assert.equal(luminance("black"), 0);
		assert.equal(luminance("var(--unresolved)"), 0, "not resolvable colors count as 0, like Thunderbird does");
		assert.isAbove(contrast("#ffffff", "#000000"), 1000);
	});

	it("removes dark text and light backgrounds, but keeps light text and readable dark backgrounds", async() =>
	{
		const doc = await bodyDocument(
			'<body bgcolor="#ffffff" style="color: #000000">' +
			'<span id="black" style="color: #000000">black</span>' +
			'<span id="white" style="color: #ffffff">white</span>' +
			'<td id="light" style="background-color: #ffffff; color: #333333">light</td>' +
			'<div id="dark" style="background-color: #000000; color: #ffffff">dark</div>' +
			'<font id="font" color="#000000">font</font>' +
			'</body>');

		adaptMessageForDarkMode(doc);

		assert.isFalse(doc.body.hasAttribute("bgcolor"));
		assert.equal(doc.body.style.color, "");
		assert.equal(doc.getElementById("black").style.color, "");
		assert.equal(doc.getElementById("white").style.color, "rgb(255, 255, 255)");
		assert.equal(doc.getElementById("dark").style.backgroundColor, "rgb(0, 0, 0)");
		assert.isFalse(doc.getElementById("font").hasAttribute("color"));
	});

	it("sanitizes the message's own <style> rules too", async() =>
	{
		const doc = await bodyDocument('<style>p { background-color: #ffffff; color: #222222 }</style><p>text</p>');

		adaptMessageForDarkMode(doc);

		const rule = doc.querySelector("style").sheet.cssRules[0] as CSSStyleRule;
		assert.equal(rule.style.backgroundColor, "");
		assert.equal(rule.style.color, "");
	});

	it("leaves a message alone, that comes with its own dark mode support (inverted)", async() =>
	{
		const doc = await bodyDocument('<html style="filter: invert(1)"><body><span style="color: #000000">x</span></body></html>');

		adaptMessageForDarkMode(doc);

		assert.equal(doc.querySelector("span").getAttribute("style"), "color: #000000");
	});

	it("restores the message exactly as it was, when dark mode is switched off again", async() =>
	{
		const html = '<body bgcolor="#ffffff"><style>p { color: #222222 }</style>' +
			'<p style="color: #000000; font-size: 11pt;">text</p><font color="black">font</font></body>';
		const doc = await bodyDocument(html);
		const original = doc.body.innerHTML;
		const originalRule = (doc.querySelector("style").sheet.cssRules[0] as CSSStyleRule).cssText;

		adaptMessageForDarkMode(doc);
		assert.notEqual(doc.body.innerHTML, original);
		restoreMessageFromDarkMode(doc);

		assert.equal(doc.body.innerHTML, original);
		assert.equal(doc.body.getAttribute("bgcolor"), "#ffffff");
		assert.equal((doc.querySelector("style").sheet.cssRules[0] as CSSStyleRule).cssText, originalRule);
	});
});
