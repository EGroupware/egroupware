import {assert} from "@open-wc/testing";
import {adaptMessageForDarkMode, contrast, hasOwnDarkMode, lightenColor, luminance, restoreMessageFromDarkMode} from "../darkReader";

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

/**
 * Rendered color of an element as RGB (0-255), lightenColor() uses CSS relative color syntax computed by the browser
 */
function renderedColor(element: Element, property: "color" | "backgroundColor" = "color"): number[]
{
	const color = element.ownerDocument.defaultView.getComputedStyle(element)[property];
	const srgb = color.match(/^color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)/);
	return srgb ? srgb.slice(1, 4).map(value => Math.round(255 * Number(value))) : color.match(/[\d.]+/g).slice(0, 3).map(Number);
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

	it("lightens a too dark color, keeping its hue, but leaves light colors as they are", () =>
	{
		const span = document.createElement("span");
		document.body.append(span);
		span.style.color = lightenColor("#800000");
		const [r, g, b] = renderedColor(span);
		assert.equal(r, 255, "dark red becomes light red");
		assert.equal(g, b);
		assert.isAbove(g, 0x80 / 2);
		span.style.color = lightenColor("#ffffff");
		assert.deepEqual(renderedColor(span), [255, 255, 255]);
		span.remove();
		assert.isNull(lightenColor("not a color"));
	});

	it("lightens dark text, removes light backgrounds, but keeps light text and readable dark backgrounds", async() =>
	{
		const doc = await bodyDocument(
			'<body bgcolor="#ffffff" style="color: #000000">' +
			'<span id="black" style="color: #000000">black</span>' +
			'<span id="white" style="color: #ffffff">white</span>' +
			'<div id="light" style="background-color: #ffffff; color: #333333">light</div>' +
			'<div id="dark" style="background-color: #000000; color: #ffffff">dark</div>' +
			'<font id="font" color="#000000">font</font>' +
			'<table><tr><td id="cell" bgcolor="#ffffff">cell</td></tr></table>' +
			'</body>');

		adaptMessageForDarkMode(doc);

		const isLightGray = ([r, g, b]: number[]) => r === g && g === b && r > 255 * 0.6;
		assert.isFalse(doc.body.hasAttribute("bgcolor"), "the body's own background is removed, to use our dark one");
		assert.equal(doc.body.style.color, "", "the body's own text color is removed, to use our default one");
		assert.isTrue(isLightGray(renderedColor(doc.getElementById("black"))), "black becomes light gray");
		assert.equal(doc.getElementById("white").style.color, "rgb(255, 255, 255)");
		assert.equal(doc.getElementById("light").style.backgroundColor, "", "white background is removed");
		assert.isTrue(isLightGray(renderedColor(doc.getElementById("light"))), "with light text on it");
		assert.equal(doc.getElementById("dark").style.backgroundColor, "rgb(0, 0, 0)");
		assert.equal(doc.getElementById("font").getAttribute("color"), "#000000", "attribute stays, overridden by an inline style");
		assert.isTrue(isLightGray(renderedColor(doc.getElementById("font"))));
		assert.isFalse(doc.getElementById("cell").hasAttribute("bgcolor"));
	});

	it("sanitizes the message's own <style> rules too", async() =>
	{
		const doc = await bodyDocument('<style>p { background-color: #ffffff; color: #222222 }</style><p>text</p>');

		adaptMessageForDarkMode(doc);

		const rule = doc.querySelector("style").sheet.cssRules[0] as CSSStyleRule;
		assert.equal(rule.style.backgroundColor, "");
		assert.isAbove(renderedColor(doc.querySelector("p"))[0], 255 * 0.6, "#222222 becomes light gray");
	});

	it("leaves a message alone, that comes with its own dark mode support (inverted)", async() =>
	{
		const doc = await bodyDocument('<html style="filter: invert(1)"><body><span style="color: #000000">x</span></body></html>');

		adaptMessageForDarkMode(doc);

		assert.equal(doc.querySelector("span").getAttribute("style"), "color: #000000");
	});

	it("leaves a message alone, that comes with its own dark mode CSS (prefers-color-scheme: dark)", async() =>
	{
		const html = '<style>@media (prefers-color-scheme: dark) { body { background: #000000; color: #ffffff } }</style>' +
			'<div bgcolor="#ffffff" style="background-color: #ffffff; color: #000000">light by default</div>';
		const doc = await bodyDocument(html);
		const original = doc.body.innerHTML;

		assert.isTrue(hasOwnDarkMode(doc));
		adaptMessageForDarkMode(doc);

		assert.equal(doc.body.innerHTML, original);
	});

	it("detects dark mode rules nested in other rules, but not a light-only media rule", async() =>
	{
		assert.isTrue(hasOwnDarkMode(await bodyDocument(
			'<style>@supports (color: red) { @media screen and (prefers-color-scheme:dark) { p { color: white } } }</style>')));
		assert.isFalse(hasOwnDarkMode(await bodyDocument(
			'<style>@media (prefers-color-scheme: light) { p { color: black } }</style><p style="color: #000000">x</p>')));
	});

	it("restores the message exactly as it was, when dark mode is switched off again", async() =>
	{
		const html = '<body bgcolor="#ffffff"><style>p { color: #222222 }</style>' +
			'<p style="color: #000000; font-size: 11pt;">text</p><font color="black">font</font>' +
			'<table><tr><td bgcolor="#ffffcc">cell</td><td style="background: #ffffff">cell</td></tr></table></body>';
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
