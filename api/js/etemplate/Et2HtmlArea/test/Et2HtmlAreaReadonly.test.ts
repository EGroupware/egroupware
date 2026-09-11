/**
 * Test file for Etemplate webComponent Et2HtmlAreaReadonly
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2HtmlAreaReadonly} from "../Et2HtmlAreaReadonly";
import * as sinon from "sinon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

// Reference to component under test
let element : Et2HtmlAreaReadonly;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2HtmlAreaReadonly>(html`
        <et2-htmlarea_ro></et2-htmlarea_ro>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {}
	});

	return element;
}

describe("HtmlArea readonly widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2HtmlAreaReadonly);
	});

	it("is always readonly", () =>
	{
		assert.isTrue(element.readonly);
	});

	it("renders rich HTML by default", async() =>
	{
		element.value = "<strong>bold</strong> text";
		await element.updateComplete;

		assert.exists(element.shadowRoot.querySelector("[part='readonly-content'] strong"));
	});

	it("renders literal text in ascii mode", async() =>
	{
		element.mode = "ascii";
		element.value = "<strong>not bold</strong>";
		await element.updateComplete;

		assert.notExists(element.shadowRoot.querySelector("[part='readonly-content'] strong"));
		assert.include(element.shadowRoot.querySelector("[part='readonly-content']").textContent, "<strong>not bold</strong>");
	});
});

// Always readonly (set in the constructor, never a toggle) - Et2InputWidget's base getValue()
// therefore always returns null regardless of .value, so the round-trip/required contract has
// nothing meaningful to check (a readonly widget never returns a value in the first place, and
// "required" on a display-only widget isn't a real scenario). label/help-text use the normal
// _labelTemplate()/_helpTextTemplate() convention and are still worth checking.
inputBasicTests(before, "some html value", "input", {
	expectedValue: null,
	emptyValue: null,
	skip: ["required"],
	checkEmptyDisplay: (element : Et2HtmlAreaReadonly) =>
		assert.equal(element.shadowRoot.querySelector("[part='readonly-content']").textContent.trim(), "",
			"Displaying something when there is no value")
});
