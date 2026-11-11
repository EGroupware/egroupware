/**
 * Test file for Etemplate webComponent Html
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import "../Et2Html";
import type {Et2Html} from "../Et2Html";

describe("Et2Html", () =>
{
	it("renders raw html value", async() =>
	{
		const element = await fixture<Et2Html>(html`<et2-html></et2-html>`);
		element.set_value("<b>Hello</b> world");
		await elementUpdated(element);

		assert.equal(element.querySelector('b')?.textContent, "Hello");
		assert.include(element.textContent, "world");
	});

	it("renders an optional label", async() =>
	{
		const element = await fixture<Et2Html>(html`<et2-html label="My Label"></et2-html>`);
		element.set_value("content");
		await elementUpdated(element);

		assert.equal(element.querySelector('.et2_label')?.textContent, "My Label");
	});

	it("executes embedded script tags, matching legacy behaviour", async() =>
	{
		const element = await fixture<Et2Html>(html`<et2-html></et2-html>`);
		(<any>window).__et2HtmlTestFlag = false;
		element.set_value("<span>before</span><script>window.__et2HtmlTestFlag = true;</script>");
		await elementUpdated(element);
		// Let the recreated <script> element actually execute
		await new Promise(r => setTimeout(r, 0));

		assert.isTrue((<any>window).__et2HtmlTestFlag, "script should have executed");
		delete (<any>window).__et2HtmlTestFlag;
	});

	it("updates value via set_value() (legacy content-array binding)", async() =>
	{
		const element = await fixture<Et2Html>(html`<et2-html></et2-html>`);
		element.set_value("first");
		await elementUpdated(element);
		assert.include(element.textContent, "first");

		element.set_value("second");
		await elementUpdated(element);
		assert.include(element.textContent, "second");
		assert.notInclude(element.textContent, "first");
	});

	// Defense-in-depth: an event-handler attribute must never survive into the rendered DOM,
	// regardless of what reached this widget - the server-side purifier is the primary gate.
	it("strips an event-handler attribute from the value (defense-in-depth)", async() =>
	{
		const element = await fixture<Et2Html>(html`<et2-html></et2-html>`);
		element.set_value('<img src="y" onerror="window.__et2HtmlTestFlag = true">');
		await elementUpdated(element);

		assert.isUndefined((<any>window).__et2HtmlTestFlag, "onerror must never fire");
		assert.isNull(element.querySelector('img')?.getAttribute('onerror'), "onerror attribute must be stripped");
	});

	it("preserves a <style> block's CSS untouched alongside the sanitizer (defense-in-depth)", async() =>
	{
		const element = await fixture<Et2Html>(html`<et2-html></et2-html>`);
		element.set_value('<style>div > p { color: red; }</style><p>text</p>');
		await elementUpdated(element);

		assert.include(element.querySelector('style')?.textContent, "div > p");
	});
});
