import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Template} from "../Et2Template";

/**
 * Layout is opt-in
 *
 * The layout property reflects, and the layout CSS (kdots/css/src/layouts) keys off the
 * attribute, so a default here would hand that layout to every template in every app.  These
 * tests pin that a template only gets a layout when it actually asks for one.
 */
// Stub global egw
// @ts-ignore
window.egw = {
	debug: () => {},
	debug_level: () => 0,
	lang: i => i + "*",
	link: i => i,
	tooltipUnbind: () => {},
	webserverUrl: ""
};

// A runtime reference, so the import is not erased as type-only - without it <et2-template>
// never registers and the fixture stays an unupgraded HTMLElement.
const keepImport = Et2Template;

async function make(markup)
{
	// @ts-ignore
	const element : Et2Template = await fixture(markup);
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);
	return element;
}

const base = (element : Et2Template) => element.shadowRoot.querySelector('[part="base"]');

describe("Et2Template layout is opt-in", () =>
{
	it("has no layout, attribute or layout class when none was asked for", async() =>
	{
		const element = await make(html`<et2-template></et2-template>`);

		assert.notOk(element.layout, "layout defaults to something instead of staying unset");
		assert.isFalse(element.hasAttribute("layout"),
			"a layout attribute is reflected onto a template that never asked for one");
		assert.notMatch(base(element).className, /layout-/,
			"a layout- class is set on a template that never asked for one");
	});

	it("keeps a layout the template does ask for, and reflects it for the CSS to match", async() =>
	{
		const element = await make(html`<et2-template layout="2-column"></et2-template>`);

		assert.equal(element.layout, "2-column");
		assert.equal(element.getAttribute("layout"), "2-column",
			"layout must stay on the attribute - the layout CSS matches [layout=...], not a class");
		assert.match(base(element).className, /\blayout-2-column\b/);
	});

	it("reflects a layout set after construction, and drops the attribute again when cleared",
		async() =>
	{
		const element = await make(html`<et2-template></et2-template>`);

		element.layout = "edit";
		await elementUpdated(element);
		assert.equal(element.getAttribute("layout"), "edit");
		assert.match(base(element).className, /\blayout-edit\b/);

		element.layout = undefined;
		await elementUpdated(element);
		assert.isFalse(element.hasAttribute("layout"));
		assert.notMatch(base(element).className, /layout-/);
	});
});
