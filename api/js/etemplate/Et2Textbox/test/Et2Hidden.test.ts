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

	it('returns its value', async() =>
	{
		element.value = "/index.php?menuaction=app.class.method&ajax=true";
		await elementUpdated(element);
		assert.equal(element.getValue(), "/index.php?menuaction=app.class.method&ajax=true");
	});

	// Regression: a template whose readonlys use `__ALL__` (eg. a user without edit
	// rights) marks every widget readonly, including hidden ones. The generic
	// Et2InputWidget answer for a readonly widget is null, which lost the server
	// provided ajax url that app-box tab loaders read from their hidden widget.
	it('still returns its value when readonly', async() =>
	{
		element.value = "/index.php?menuaction=app.class.method&ajax=true";
		element.readonly = true;
		await elementUpdated(element);
		assert.equal(element.getValue(), "/index.php?menuaction=app.class.method&ajax=true");
	});

	it('returns null when disabled', async() =>
	{
		element.value = "/index.php?menuaction=app.class.method&ajax=true";
		element.disabled = true;
		await elementUpdated(element);
		assert.isNull(element.getValue());
	});
});

// A genuine <input type="hidden"> - :host is always display:none by design, so "disabled stays
// visible" (the whole point of which is distinguishing disabled from hidden) doesn't apply here:
// it's never visible regardless of disabled state. Also has no label/help-text chrome at all.
// "readonly" is skipped on purpose: a hidden input carries no user-editable state, so it keeps
// answering its value when readonly (see the regression test above) instead of the generic null.
inputBasicTests(before, "a hidden value", "input", {
	skip: ["readonly", "disabled", "label", "help-text"]
});
