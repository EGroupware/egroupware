/**
 * Test file for Etemplate webComponent Description
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Description} from "../Et2Description";
import * as sinon from "sinon";

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


// Description is not an input widget, do not run inputBasicTests
// inputBasicTests(before, "I'm a good test value", "input");