/**
 * Testing Et2Textbox input / values
 */

import {fixture, html} from "@open-wc/testing";
import {Et2Textbox} from "../Et2Textbox";

describe("Textbox input / values", () =>
{
	// Reference to component under test
	let element : Et2Textbox;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		element = await fixture<Et2Textbox>(html`
            <et2-textbox></et2-textbox>
		`);
	});

	it("Takes a value", () =>
	{
		/*
		Complains about set_value() being missing?
		let test_value = "test value";
		debugger;
		element.set_value(test_value);
		assert(document.querySelector('input').should.have.text(test_value))

		 */
	})
});