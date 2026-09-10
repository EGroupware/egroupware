/**
 * Test file for Etemplate webComponent Et2UrlPhone
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2UrlPhone} from "../Et2UrlPhone";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2UrlPhone;

async function before()
{
	element = await fixture<Et2UrlPhone>(html`
        <et2-url-phone></et2-url-phone>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Url phone widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2UrlPhone);
	});
});

inputBasicTests(before, "+1 555 123 4567", "input");
