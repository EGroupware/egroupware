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
	 * Contract: template-local row styles replace app.css for datagrid rows.
	 * Setup: compose row styles with both an app stylesheet and a template
	 * stylesheet present.
	 * Pass: the template stylesheet is included and the app stylesheet is not.
	 */
	it("uses template row styles instead of app.css in datagrid row stylesheets", async() =>
	{
		const nextmatch = new Et2Nextmatch() as any;
		const appSheet = new CSSStyleSheet();
		await appSheet.replace(".from-app-css { color: red; }");
		const templateSheet = new CSSStyleSheet();
		await templateSheet.replace(".from-template { color: green; }");

		nextmatch._appRowStylesheet = appSheet;
		nextmatch._templateData = {rowStylesheets: [templateSheet]};
		nextmatch._syncDatagridRowStylesheets();

		assert.include(nextmatch._rowStylesheets, templateSheet, "template row stylesheet should be adopted");
		assert.notInclude(nextmatch._rowStylesheets, appSheet, "app.css should not be adopted when template row styles exist");
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
