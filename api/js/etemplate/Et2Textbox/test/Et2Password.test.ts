/**
 * Test file for Etemplate webComponent Et2Password
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Password} from "../Et2Password";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// Stub global egw for cssImage to find
// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2Password;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Password>(html`
        <et2-password></et2-password>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Password widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Password);
	});
});

inputBasicTests(before, "a good password", "input");
