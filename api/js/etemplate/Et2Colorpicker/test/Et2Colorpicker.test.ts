/**
 * Test file for Etemplate webComponent base widget Et2Colorpicker
 */
import {assert, fixture} from '@open-wc/testing';
import {Et2Colorpicker} from "../Et2Colorpicker";
import {html} from "lit-element";


describe("Colorpicker widget", () =>
{
	// Reference to component under test
	let element : Et2Colorpicker;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		element = await fixture<Et2Colorpicker>(html`
            <et2-colorpicker></et2-colorpicker>
		`);
	});

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Colorpicker);
	});

	it('clearing value', () =>
	{
		// set a value
		element.set_value("11111");
		// trigger the clear button
		element.__getClearButtonNode().dispatchEvent(new MouseEvent('click'));

		assert.equal(element.getValue(), "");
	});

});