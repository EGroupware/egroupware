/**
 * Test file for Etemplate webComponent base widget Et2Box
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Box} from "../Et2Box";

describe("Box widget", () =>
{
	// Reference to component under test
	let element : Et2Box;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		element = await fixture<Et2Box>(html`
            <et2-box></et2-box>
		`);
	});

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Box);
	});

	it('has no label', () =>
	{
		element.set_label("Nope");
		assert.isEmpty(element.shadowRoot.querySelectorAll('.et2_label'));
	});

	it('disabled', async() =>
	{
		element.disabled = true;

		// wait for asychronous changes to the DOM
		// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
		await elementUpdated(element);

		let style = window.getComputedStyle(<Element><unknown>element);
		assert.include(element.getAttributeNames(), "disabled", "Missing disabled attribute");
		assert.equal(style.display, "none", "Did not hide when disabled");

		element.disabled = false;
		// wait for asychronous changes to the DOM
		// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
		await elementUpdated(element);

		// Check attribute goes
		assert.notInclude(element.getAttributeNames(), "disabled", "Still had disabled attribute after setting it to false");
		style = window.getComputedStyle(<Element><unknown>element);
		assert.notEqual(style.display, "none", "Did not show when not disabled");

		/** Check via set method instead of property **/
		element.set_disabled(true);

		// wait for asychronous changes to the DOM
		// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
		await elementUpdated(element);
		style = window.getComputedStyle(<Element><unknown>element);
		assert.include(element.getAttributeNames(), "disabled", "Missing disabled attribute");
		assert.equal(style.display, "none", "Did not hide when disabled via set_disabled()");

	});

});