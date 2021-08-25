/**
 * Test file for Etemplate webComponent base widget Et2Box
 */
import {assert, fixture} from '@open-wc/testing';
import {Et2Button} from "../Et2Button";
import type {Et2Widget} from "../../Et2Widget";
import {html} from "lit-element";
import * as sinon from 'sinon';

describe("Button widget", () =>
{
	// Reference to component under test
	let element : Et2Button;


	// Setup run before each test
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		element = await fixture<Et2Button>(html`
            <et2-button label="I'm a button"></et2-button>
		`);

		// Stub egw()
		sinon.stub(element, "egw").returns({
			tooltipUnbind: () => {}
		});
	});

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Button);
	});

	it('has a label', () =>
	{
		element.set_label("Label set");

		assert.equal(element.textContent, "Label set");
	})
});