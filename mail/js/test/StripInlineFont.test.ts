import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";

/**
 * Ticket #124821 (2026-09-22, a real customer via Ingo): "die Schriftgröße wird nicht immer wie
 * eingestellt verwendet, in der Einstellung ist 10, verwendet wird 12" - a signature edited
 * through admin's identity editor gets an inline font-size/font-family baked onto its own elements
 * at SAVE time (Et2HtmlArea's own _applyDefaultFontToContent()), which then always wins over the
 * compose editor's current, correctly-configured font preference by CSS specificity. See
 * MailJmap.stripInlineFont()'s own docblock for the full mechanism.
 */
describe("MailJmap.stripInlineFont()", () =>
{
	it("removes an inline font-size from a signature paragraph", () =>
	{
		const result = MailJmap.stripInlineFont('<p style="font-size: 12pt;">John Doe</p>');
		assert.notInclude(result, 'font-size');
		assert.include(result, 'John Doe');
	});

	it("removes an inline font-family too, keeping other inline styles untouched", () =>
	{
		const result = MailJmap.stripInlineFont('<p style="font-family: Arial; font-size: 12pt; color: red;">John Doe</p>');
		assert.notInclude(result, 'font-family');
		assert.notInclude(result, 'font-size');
		assert.include(result, 'color: red');
	});

	it("strips font-size/family from EVERY descendant, not just the root element", () =>
	{
		const result = MailJmap.stripInlineFont(
			'<div style="font-size: 12pt;"><p style="font-family: Arial;">John Doe</p><span style="font-size: 10pt;">Title</span></div>');
		assert.notInclude(result, 'font-size');
		assert.notInclude(result, 'font-family');
		assert.include(result, 'John Doe');
		assert.include(result, 'Title');
	});

	it("removes legacy <font face/size> attributes", () =>
	{
		const result = MailJmap.stripInlineFont('<font face="Arial" size="4">John Doe</font>');
		assert.notInclude(result, 'face=');
		assert.notInclude(result, 'size=');
		assert.include(result, 'John Doe');
	});

	it("preserves formatting other than font-family/font-size (bold, links, colour)", () =>
	{
		const result = MailJmap.stripInlineFont(
			'<p style="font-size: 12pt; color: blue;"><b>John Doe</b> - <a href="https://example.com">example.com</a></p>');
		assert.include(result, '<b>John Doe</b>');
		assert.include(result, 'href="https://example.com"');
		assert.include(result, 'color: blue');
	});

	it("is a no-op on HTML with no font styling at all", () =>
	{
		const html = '<p>John Doe</p><p><b>Title</b></p>';
		assert.equal(MailJmap.stripInlineFont(html), html);
	});

	it("returns an empty/falsy input unchanged, without throwing", () =>
	{
		assert.equal(MailJmap.stripInlineFont(''), '');
	});
});
