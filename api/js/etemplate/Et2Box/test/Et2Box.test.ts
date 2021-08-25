/**
 * Test file for Etemplate webComponent base widget Et2Box
 */
import {assert, fixture} from '@open-wc/testing';
import {Et2Box} from "../Et2Box";
import {html} from "lit-element";

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
	})
});