import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Styles, resolveEt2StylesSrc} from "../Et2Styles";

describe('Et2Styles Component', () =>
{
	let element : Et2Styles;
	let egw_stub;

	beforeEach(() =>
	{
		// @ts-ignore
		egw_stub = sinon.stub(Et2Styles.prototype, "egw").returns({
			image: () => "",
			preference: () => "",
			tooltipUnbind: () => {},
			webserverUrl: "/egroupware",
			link: (url : string) => url.startsWith("/egroupware/") ? url : "/egroupware" + url,
			window: window
		});
	});

	afterEach(() =>
	{
		egw_stub?.restore();
	});

	// Make sure not to pollute the document head across tests
	afterEach(() =>
	{
		if(element && element.parentNode)
		{
			element.parentNode.removeChild(element);
		}
		// Clean up any leftover injected nodes from this element
		document.head.querySelectorAll("style,link[rel='stylesheet']").forEach(n =>
		{
			if(n.getAttribute("data-et2-styles-test") !== null || n.id?.startsWith("et2-styles"))
			{
				n.remove();
			}
		});
	});

	it('is defined', async() =>
	{
		const el = await fixture<Et2Styles>(html`<et2-styles></et2-styles>`);
		assert.instanceOf(el, Et2Styles);
	});

	it('injects a style node into the document head on connect', async() =>
	{
		element = await fixture<Et2Styles>(html`<et2-styles></et2-styles>`);
		await element.updateComplete;

		// A <style> node should be injected into <head> by this component
		assert.isNotNull(document.head.querySelector("style"), "A <style> node should be injected into <head>");
	});

	it('writes inline CSS (value) into the injected style node', async() =>
	{
		element = await fixture<Et2Styles>(html`<et2-styles></et2-styles>`);
		element.value = ".my-class { color: red; }";
		await element.updateComplete;

		const styleNode = document.head.querySelector("style");
		assert.isNotNull(styleNode, "style node should exist");
		assert.include(styleNode.textContent, ".my-class { color: red; }");
	});

	it('accepts CSS as light-DOM text content (legacy <styles> usage)', async() =>
	{
		element = await fixture<Et2Styles>(html`<et2-styles>.legacy-rule { display: block; }</et2-styles>`);
		await element.updateComplete;
		// firstUpdated() picks up text content
		await element.updateComplete;

		const styleNode = document.head.querySelector("style");
		assert.isNotNull(styleNode, "style node should exist");
		assert.include(styleNode.textContent, ".legacy-rule { display: block; }");
	});

	it('removes the injected style node from the head when disconnected', async() =>
	{
		element = await fixture<Et2Styles>(html`<et2-styles></et2-styles>`);
		element.value = ".temp { color: blue; }";
		await element.updateComplete;

		assert.isNotNull(document.head.querySelector("style"), "style node should exist before disconnect");

		element.remove();

		// The <style> must be gone from the head after disconnect
		assert.isNull(document.head.querySelector("style"), "style node should be removed from head on disconnect");
	});

	it('removes the injected link node from the head when disconnected', async() =>
	{
		const css = "data:text/css,.x{color:red}";
		element = await fixture<Et2Styles>(html`<et2-styles src="${css}"></et2-styles>`);
		await element.updateComplete;
		assert.isNotNull(document.head.querySelector(`link[rel='stylesheet'][href='${css}']`), "link should exist before disconnect");

		element.remove();

		assert.isNull(document.head.querySelector(`link[rel='stylesheet'][href='${css}']`), "link should be removed from head on disconnect");
	});

	it('does not leave orphaned nodes when reconnected', async() =>
	{
		element = await fixture<Et2Styles>(html`<et2-styles></et2-styles>`);
		element.value = ".temp { color: green; }";
		await element.updateComplete;
		assert.lengthOf(document.head.querySelectorAll("style"), 1, "exactly one style node while connected");

		// Simulate normal DOM removal and re-addition
		element.remove();
		assert.isNull(document.head.querySelector("style"), "style node removed on disconnect");
		document.body.appendChild(element);
		await element.updateComplete;

		// Reconnecting must not create a second orphaned node
		assert.lengthOf(document.head.querySelectorAll("style"), 1, "still exactly one style node after reconnect");
	});

	it('loads an external stylesheet via src (data: URL, no network request)', async() =>
	{
		const css = "data:text/css,.my-class{color:red}";
		element = await fixture<Et2Styles>(html`<et2-styles src="${css}"></et2-styles>`);
		await element.updateComplete;

		const linkNode = document.head.querySelector(`link[rel='stylesheet'][href='${css}']`);
		assert.isNotNull(linkNode, "A <link rel='stylesheet'> node should be injected for src");
	});

	it('resolves a relative src against the EGroupware webserver root', async() =>
	{
		element = await fixture<Et2Styles>(html`<et2-styles></et2-styles>`);
		await element.updateComplete;

		// Test the resolution logic directly so we don't trigger a real network
		// request (which would otherwise 404 and fail the suite).
		const resolved = (element as any)._resolveSrc("api/etemplate/extra.css");
		assert.strictEqual(resolved, "/egroupware/api/etemplate/extra.css");

		// A webserver-relative path WITHOUT a leading slash (exactly what the
		// PHP converter emits for a bare-filename src like "row.css" in a
		// template under /addressbook/templates/default/) is resolved through
		// egw.link(), which avoids double-prefixing the webserverUrl.
		assert.strictEqual((element as any)._resolveSrc("egroupware/addressbook/templates/default/row.css"),
			"/egroupware/addressbook/templates/default/row.css");

		// http(s) / data: URLs pass through unchanged; root-relative URLs go through egw.link().
		assert.strictEqual((element as any)._resolveSrc("https://example.com/theme.css"), "https://example.com/theme.css");
		assert.strictEqual((element as any)._resolveSrc("/absolute/path.css"), "/egroupware/absolute/path.css");
		assert.strictEqual((element as any)._resolveSrc("data:text/css,.x{}"), "data:text/css,.x{}");
	});

	it('resolves a bare src relative to the containing template URL', async() =>
	{
		const resolved = resolveEt2StylesSrc(
			"row.css",
			{link: (url : string) => "/egroupware" + url},
			"/egroupware/infolog/templates/default/index.xet?download=123"
		);
		assert.strictEqual(resolved, "/egroupware/infolog/templates/default/row.css");
	});

	it('removes the external stylesheet link when src is cleared', async() =>
	{
		const css = "data:text/css,.my-class{color:blue}";
		element = await fixture<Et2Styles>(html`<et2-styles src="${css}"></et2-styles>`);
		await element.updateComplete;
		assert.isNotNull(document.head.querySelector(`link[rel='stylesheet'][href='${css}']`), "link should exist");

		element.src = "";
		await element.updateComplete;
		assert.isNull(document.head.querySelector(`link[rel='stylesheet'][href='${css}']`), "link should be removed when src is cleared");
	});

});
