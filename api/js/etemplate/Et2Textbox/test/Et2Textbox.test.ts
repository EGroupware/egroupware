/**
 * Test file for Etemplate webComponent Textbox
 */
import {assert, fixture} from '@open-wc/testing';
import {Et2Textbox} from "../Et2Textbox";
import {html} from "lit-element";

describe("Textbox widget", () =>
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

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Textbox);
	});

	it('has a label', () =>
	{
		element.set_label("Yay label");
		assert.isEmpty(element.shadowRoot.querySelectorAll('.et2_label'));
	})
});