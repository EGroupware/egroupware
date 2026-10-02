import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";

const egwStub = {
	lang: (label : string) => label,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: (_key? : string) => null as any,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

describe("Et2Nextmatch row stylesheet synchronization", () =>
{
	/**
	 * Contract: datagrid rows get the framework row styles plus the row template's own styles, nothing else.
	 * Setup: synchronize with a template stylesheet present.
	 * Pass: exactly those two sheets, framework first.
	 */
	it("adopts only the framework and row template styles", async() =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const templateSheet = new CSSStyleSheet();
		await templateSheet.replace(".from-template { color: green; }");

		nextmatch._templateData = {rowStylesheets: [templateSheet]};
		nextmatch._syncDatagridRowStylesheets();

		assert.lengthOf(nextmatch._rowStylesheets, 2);
		assert.strictEqual(nextmatch._rowStylesheets[1], templateSheet, "template row stylesheet should be adopted");
	});

	/**
	 * Contract: app.css is page CSS and never reaches the row shadow root, even for a row template
	 * without <et2-styles>.
	 * Setup: a nextmatch whose row template has no styles goes through template application, with fetch
	 * recording every request.
	 * Pass: no app.css request, and only the framework row styles are adopted.
	 */
	it("never loads app.css into the rows", async() =>
	{
		const originalFetch = window.fetch;
		const fetchedUrls : string[] = [];
		window.fetch = (async(input : RequestInfo | URL) =>
		{
			fetchedUrls.push(String(input));
			return new Response("", {status: 200});
		}) as typeof window.fetch;
		try
		{
			const nextmatch = new Et2Nextmatch() as any;
			nextmatch._applyTemplateData({columns: [], rowStylesheets: []});
			await new Promise(resolve => setTimeout(resolve, 0));

			assert.deepEqual(fetchedUrls.filter(url => url.includes("app.css")), [], "app.css should not be requested");
			assert.lengthOf(nextmatch._rowStylesheets, 1, "only the framework row styles");
		}
		finally
		{
			window.fetch = originalFetch;
		}
	});

	/**
	 * Contract: runtime row styles added through the public API survive later internal stylesheet synchronization.
	 * Setup: add a constructed stylesheet, then synchronize the template styles again and add the same sheet twice.
	 * Pass: the runtime sheet remains last, so it can override static rules, and is included only once.
	 */
	it("retains additional row stylesheets across synchronization", async() =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const templateSheet = new CSSStyleSheet();
		await templateSheet.replace(".from-template { color: green; }");
		const additionalSheet = new CSSStyleSheet();
		await additionalSheet.replace(".from-runtime { color: purple; }");

		nextmatch._templateData = {rowStylesheets: [templateSheet]};
		nextmatch.addRowStylesheet(additionalSheet);
		nextmatch._syncDatagridRowStylesheets();
		nextmatch.addRowStylesheet(additionalSheet);

		assert.strictEqual(
			nextmatch._rowStylesheets[nextmatch._rowStylesheets.length - 1],
			additionalSheet,
			"runtime stylesheet should remain after the template styles"
		);
		assert.equal(
			nextmatch._rowStylesheets.filter((style : CSSStyleSheet) => style === additionalSheet).length,
			1,
			"the same runtime stylesheet should only be adopted once"
		);
	});
});
