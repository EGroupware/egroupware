/**
 * Test file for Etemplate webComponent Description
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Description} from "../Et2Description";
import * as sinon from "sinon";
import {widgetSlotTests} from "../../Et2Widget/test/WidgetSlotTests";

// Reference to component under test
let element : Et2Description;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Description>(html`
        <et2-description></et2-description>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		tooltipUnbind: () => {},
		lang: (param) => {return param;}
	});
	return element;
}

describe("Textbox widget", () =>
{

	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Description);
	});

	it('has a label', () =>
	{
		element.set_label("Yay label");

		assert.isNotEmpty(element.querySelectorAll('.et2_label'));
		assert.equal(element.querySelector(".et2_label").textContent, "Yay label");
	});

	it("shows its value", async() =>
	{
		let value = "This is my value";

		// Turn off translation
		element.noLang = true;

		element.set_value(value);

		// wait for asynchronous changes to the DOM
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		// Firefox puts the style tag in, so it's not an exact match
		assert.match(element.textContent, new RegExp(value));
	});

	it("translates its value", async() =>
	{
		let value = "This is my original value";

		// Set up translation to give a different value
		// @ts-ignore restore() is created by sinon.stub()
		element.egw.restore();
		sinon.stub(element, "egw").returns({
			tooltipUnbind: () => {},
			lang: (param) => {return "Translated!";}
		});

		element.set_value(value);

		// wait for asynchronous changes to the DOM
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		// Firefox puts the style tag in, so it's not an exact match
		assert.match(element.textContent, new RegExp("Translated!"));
	});

	it("links when given href", async() =>
	{
		let href = "not_real_url";

		element.href = href;
		element.value = "click me";

		// wait for asynchronous changes to the DOM
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		let a = element.querySelector("a");
		assert.isNotNull(a, "Did not find A tag");
		assert.match(a.href, new RegExp(href), "A tag had wrong href");
	});

	it("activates links when asked", async() =>
	{
		let content = "hey, check out www.egroupware.org";
		element.value = content;

		// wait for asynchronous changes to the DOM
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		// Not turned on, make sure there is no links
		assert.isNull(element.querySelector("a"), "Links got activated when activate_links property is false");

		// Turn it on
		element.activateLinks = true;

		// wait for asynchronous changes to the DOM
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		assert.isNotNull(element.querySelector("a"), "Links did not get activated when activate_links property is true");
		assert.equal(element.querySelector("a").href, "http://www.egroupware.org/", "Incorrect href in activated link");
	});
});


/**
 * Opt-in markdown display.
 *
 * Contract: `markdown` is off by default and changes nothing; when on, the value is parsed and
 * rendered into the light DOM inside a .et2_markdown wrapper.  Precedence is
 * href > markdown > activateLinks > plain text.
 *
 * Setup: the shared before() fixture and its egw() stub, plus noLang so egw().lang() can't
 * interfere with the markdown source.
 */
describe("Et2Description markdown", () =>
{
	beforeEach(async() =>
	{
		await before();
		// never translate a markdown source string
		element.noLang = true;
	});

	it("does not render markdown by default", async() =>
	{
		element.value = "**bold** and # not a heading";

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		assert.isNull(element.querySelector(".et2_markdown"), "Markdown wrapper present although markdown is off");
		assert.isNull(element.querySelector("strong"), "Value got parsed although markdown is off");
		assert.match(element.textContent, /\*\*bold\*\*/, "Value is no longer shown literally");
	});

	it("renders markdown when asked", async() =>
	{
		element.markdown = true;
		element.value = "# Heading\n\n- one\n- two";

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		assert.isNotNull(element.querySelector(".et2_markdown"), "No markdown wrapper");
		assert.equal(element.querySelector(".et2_markdown h1")?.textContent, "Heading");
		assert.lengthOf(element.querySelectorAll(".et2_markdown li"), 2, "List was not rendered");
	});

	it("re-renders when markdown is toggled at runtime", async() =>
	{
		element.value = "**bold**";

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);
		assert.isNull(element.querySelector("strong"), "Parsed before markdown was turned on");

		// Also covers "markdown" being in updated()'s changed-property check
		element.markdown = true;

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);
		assert.isNotNull(element.querySelector("strong"), "Toggling markdown did not re-render");
	});

	it("does not double-link when activateLinks is also on", async() =>
	{
		element.markdown = true;
		element.activateLinks = true;
		element.value = "see https://www.egroupware.org";

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		assert.lengthOf(element.querySelectorAll("a"), 1, "Link got processed twice");
		assert.isNotNull(element.querySelector(".et2_markdown a"), "Link is not inside the markdown wrapper");
	});

	it("lets href win over markdown", async() =>
	{
		const href = "not_real_url";
		element.markdown = true;
		element.href = href;
		element.value = "**click me**";

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		assert.isNull(element.querySelector(".et2_markdown"), "Markdown was rendered although href is set");
		const a = element.querySelector("a");
		assert.isNotNull(a, "Did not find A tag");
		assert.match(a.href, new RegExp(href), "A tag had wrong href");
	});

	it("does not execute hostile markup in a markdown value", async() =>
	{
		element.markdown = true;
		element.value = '<img src=x onerror=alert(1)>\n\n[x](javascript:alert(1))';

		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(element);

		assert.isNull(element.querySelector("img"), "An img node survived");
		assert.isNull(element.querySelector('a[href^="javascript:"]'), "A javascript: href survived");
	});
});


// Description is not an input widget (no readonly/required/get_value/isValid), so it can't run
// inputBasicTests()'s value/required/disabled/hidden contract - but its label rendering follows
// the same part="form-control-label" convention as real input widgets, so that part is still
// worth checking. It has no help-text slot at all. skipLabelFixed: the label part is a bare
// <slot part="form-control-label"> (display:contents), which "width" has no effect on at all -
// .et2-label-fixed genuinely cannot apply here, not a bug.
widgetSlotTests(before, ["label"], {skipLabelFixed: true});
/**
 * Link styling.
 *
 * Contract: links inside a description are rendered into its light DOM, out of reach of its own shadow
 * styles, so the description puts its link styles into whichever document or shadow root it is in, once,
 * at a specificity any app rule overrides.
 *
 * Setup: descriptions with href / activateLinks, in the document and inside a shadow root (like a list's
 * row, which page CSS does not reach).
 */
describe("Et2Description link styles", () =>
{
	const linkColor = () =>
	{
		const probe = document.createElement("span");
		probe.style.color = "var(--sl-color-primary-700)";
		document.body.append(probe);
		const color = getComputedStyle(probe).color;
		probe.remove();
		return color;
	};

	it("styles links in the document", async() =>
	{
		const description = await fixture<Et2Description>(html`
            <et2-description noLang href="not_real_url" value="click me"></et2-description>`);
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(description);

		const a = description.querySelector("a");
		assert.equal(getComputedStyle(a).color, linkColor(), "Link has the browser's default colour");
		assert.equal(getComputedStyle(a).textDecorationLine, "none", "Link is underlined");
	});

	it("styles links inside a shadow root, once per root", async() =>
	{
		const host = await fixture<HTMLDivElement>(html`<div></div>`);
		const root = host.attachShadow({mode: "open"});
		root.innerHTML = `<et2-description noLang activateLinks value="see www.egroupware.org"></et2-description>
			<et2-description noLang href="not_real_url" value="click me"></et2-description>`;
		const descriptions = Array.from(root.querySelectorAll("et2-description")) as Et2Description[];
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await Promise.all(descriptions.map(d => elementUpdated(d)));

		assert.lengthOf(root.querySelectorAll("style#" + Et2Description.LINK_STYLES_ID), 1, "Link styles not added exactly once");
		descriptions.forEach(d =>
		{
			const a = d.querySelector("a");
			assert.isNotNull(a, "No link rendered");
			assert.equal(getComputedStyle(a).color, linkColor(), "Link in a shadow root has the browser's default colour");
		});
	});

	it("lets an app rule win", async() =>
	{
		const host = await fixture<HTMLDivElement>(html`<div></div>`);
		const root = host.attachShadow({mode: "open"});
		root.innerHTML = `<style>a { color: rgb(1, 2, 3); }</style>
			<et2-description noLang href="not_real_url" value="click me"></et2-description>`;
		const description = root.querySelector("et2-description") as Et2Description;
		// @ts-ignore TypeScript doesn't recognize widgets as Elements
		await elementUpdated(description);

		assert.equal(getComputedStyle(description.querySelector("a")).color, "rgb(1, 2, 3)", "App rule was overridden");
	});
});
