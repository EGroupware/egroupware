import {Et2InputWidgetInterface} from "../Et2InputWidget";
import {assert, elementUpdated} from "@open-wc/testing";


/**
 * Some basic, common tests that any decent input widget should pass
 *
 * Have your widget creation in a separate function, and pass it in along with a "good" value.
 * Checking "bad" values and error conditions are widget-specific, so you have to handle those in your own tests.
 * This is just a starting point, and to make sure that if a widget doesn't pass these, there's something
 * wrong.
 *
 * @example
 * async function before()
 * {
 * 	// Create an element to test with, and wait until it's ready
 * 	// @ts-ignore
 * 	element = await fixture<Et2Date>(html`
 *         <et2-date label="I'm a date"></et2-date>
 * 	`);
 * 	return element;
 * 	}
 * inputBasicTests(before, "2008-09-22T00:00:00.000Z", "input");
 *
 * @param {Function} before function to create / setup the widget.  It is run before each test and must return the widget to test
 * @param {string} test_value A "good" value
 * @param {string} value_selector Passed to document.querySelector() to check that the value is displayed
 */

// Widget used in each test
let element : Et2InputWidgetInterface;

export function inputBasicTests(before : Function, test_value : string, value_selector : string)
{
	describe("Readonly", () =>
	{
		beforeEach(async() =>
		{
			element = await before();
		});

		it("does not return a value (via attribute)", async() =>
		{
			element.readonly = true;

			element.set_value(test_value);

			// wait for asychronous changes to the DOM
			await elementUpdated(<Element><unknown>element);
			// Read-only widget returns null
			assert.equal(element.getValue(), null);
		});

		it("does not return a value (via method)", async() =>
		{
			(<Et2InputWidgetInterface>element).set_readonly(true);

			element.set_value(test_value);

			// wait for asychronous changes to the DOM
			await elementUpdated(<Element><unknown>element);
			// Read-only widget returns null
			assert.equal(element.getValue(), null);
		});

		it("does not return a value if it goes readonly after having a value", async() =>
		{
			element.set_value(test_value);

			element.set_readonly(true);

			// wait for asychronous changes to the DOM
			await elementUpdated(<Element><unknown>element);
			// Read-only widget returns null
			assert.equal(element.getValue(), null);
		});
	});

	describe("In/Out value tests", () =>
	{
		beforeEach(async() =>
		{
			element = await before();
		});
		it("no value gives empty string", () =>
		{
			// Shows as empty / no value
			let value = (<Element><unknown>element).querySelector(value_selector) || (<Element><unknown>element).shadowRoot.querySelector(value_selector);
			assert.equal(value.textContent.trim(), "", "Displaying something when there is no value");
			// Gives no value
			assert.equal(element.get_value(), "", "Value mismatch");
		});

		it("value out matches value in", async() =>
		{
			element.set_value(test_value);

			// wait for asychronous changes to the DOM
			await elementUpdated(<Element><unknown>element);

			// widget returns what we gave it
			assert.equal(element.get_value(), test_value);
		});
	});

	describe("Required", () =>
	{
		beforeEach(async() =>
		{
			element = await before();
		});

		// This is just visually comparing for a difference, no deep inspection
		it("looks different when required")

		/*
		Not yet working attempt to have playwright compare visually
		I haven't figured out how to get it to actually work

		const pre = await page.locator(element.localName).screenshot();

		element.required = true;

		// wait for asychronous changes to the DOM
		await elementUpdated(<Element><unknown>element);


		const post = await page.locator(element.localName).screenshot();

		expect(post).toMatchSnapshot(pre);

		 */

	});
}