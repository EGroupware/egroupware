/**
 * Test file for Etemplate webComponent Textbox
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2Number} from "../Et2Number";
import * as sinon from "sinon";

window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {},
	preference: () => ""
};
// Reference to component under test
let element : Et2Number;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Number>(html`
        <et2-number></et2-number>
	`);

	sinon.stub(element, "egw").returns(window.egw);

	return element;
}

describe("Number widget", () =>
{

	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Number);
	});

	it('has a label', () =>
	{
		element.set_label("Yay label");
		assert.isEmpty(element.shadowRoot.querySelectorAll('.et2_label'));
	});

	it("handles precision", () =>
	{
		element.decimalSeparator = ".";
		element.precision = 2;
		element.value = "1.234";
		assert.equal(element.value, "1.23", "Wrong number of decimals (. separator");
		element.precision = 0;
		element.value = "1.234";
		assert.equal(element.value, "1", "Wrong number of decimals (. separator");


		// Now do it with comma decimal separator
		element.decimalSeparator = ","
		element.precision = 2;
		element.value = "1.234";
		assert.equal(element.value, "1,23", "Wrong number of decimals ( . -> , separator)");
		element.value = "1,234";
		assert.equal(element.value, "1,23", "Wrong number of decimals (, separator)");
		element.precision = 0;
		element.value = "1,234";
		assert.equal(element.value, "1", "Wrong number of decimals (, separator)");
	});

	it("handles blank ('')", () =>
	{
		element.value = "";
		assert.equal(element.value, "");
	});

	it("Min limit", () =>
	{
		element.value = 0;
		element.min = 2;
		element.value = "1.234";
		assert.equal(element.value, "2", "Value allowed below minimum");
	});

	describe("Check number preferences", () =>
	{

		const checkValue = (set, expected?) =>
		{
			if(typeof expected == "undefined")
			{
				expected = set;
			}
			element.value = set;
			assert.equal(element.getValue(), expected);

		};

		it("Handles . as decimal", () =>
		{
			window.egw.preference = () => ".";
			element.decimalSeparator = ".";

			checkValue("1");
			assert.equal(element.valueAsNumber, 1, "Numeric value does not match");
			checkValue("1.1");
			assert.equal(element.valueAsNumber, 1.1, "Numeric value does not match");

			element.value = "Fail";
			assert.isNaN(element.value);
		});
		it("Handles , as decimal", () =>
		{
			window.egw.preference = () => ",";
			element.decimalSeparator = ",";

			checkValue("1");
			assert.equal(element.valueAsNumber, 1, "Numeric value does not match");
			checkValue("1,1", "1.1");
			assert.equal(element.getValue(), "1.1");
			assert.equal(element.valueAsNumber, 1.1, "Numeric value does not match");

			element.value = "Fail";
			assert.isNaN(element.value);
		});
	});
});
//
// inputBasicTests(before, "I'm a good test value", "input");