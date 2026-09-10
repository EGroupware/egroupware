/**
 * Test file for Etemplate webComponent Et2UrlFax
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2UrlFax} from "../Et2UrlFax";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2UrlFax;

async function before()
{
	element = await fixture<Et2UrlFax>(html`
        <et2-url-fax></et2-url-fax>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Url fax widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2UrlFax);
	});
});

inputBasicTests(before, "+1 555 123 4567", "input");
