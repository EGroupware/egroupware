/**
 * Test file for Etemplate webComponent Et2Hidden
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Hidden} from "../Et2Hidden";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2Hidden;

async function before()
{
	element = await fixture<Et2Hidden>(html`
        <et2-hidden></et2-hidden>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Hidden widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Hidden);
	});

	it("is always invisible", () =>
	{
		assert.isFalse(element.checkVisibility());
	});
});

// A genuine <input type="hidden"> - :host is always display:none by design, so "disabled stays
// visible" (the whole point of which is distinguishing disabled from hidden) doesn't apply here:
// it's never visible regardless of disabled state. Also has no label/help-text chrome at all.
inputBasicTests(before, "a hidden value", "input", {
	skip: ["disabled", "label", "help-text"]
});
