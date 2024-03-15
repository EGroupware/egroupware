/**
 * Test file for Etemplate webComponent Textbox
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Textbox} from "../Et2Textbox";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// Stub global egw for cssImage to find
// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2Textbox;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Textbox>(html`
        <et2-textbox></et2-textbox>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Textbox widget", () =>
{

	// Setup run before each test
	beforeEach(before);

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
inputBasicTests(before, "I'm a good test value", "input");