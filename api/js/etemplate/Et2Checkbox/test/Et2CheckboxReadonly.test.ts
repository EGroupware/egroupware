/**
 * Test file for Etemplate webComponent Et2CheckboxReadonly
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2CheckboxReadonly} from "../Et2CheckboxReadonly";
import * as sinon from "sinon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

// Reference to component under test
let element : Et2CheckboxReadonly;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2CheckboxReadonly>(html`
        <et2-checkbox_ro></et2-checkbox_ro>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {}
	});

	return element;
}

describe("Checkbox readonly widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2CheckboxReadonly);
	});

	it("shows a check icon for a truthy value with no selectedValue", async() =>
	{
		element.set_value("1");
		await element.updateComplete;
		assert.exists(element.shadowRoot.querySelector("sl-icon"));
	});

	it("shows nothing for an empty value with no selectedValue", async() =>
	{
		element.set_value("");
		await element.updateComplete;
		assert.notExists(element.shadowRoot.querySelector("sl-icon"));
	});

	it("only counts a matching selectedValue as checked", async() =>
	{
		element.selectedValue = "yes";

		element.set_value("no");
		await element.updateComplete;
		assert.notExists(element.shadowRoot.querySelector("sl-icon"), "Non-matching value should not show as checked");

		element.set_value("yes");
		await element.updateComplete;
		assert.exists(element.shadowRoot.querySelector("sl-icon"), "Matching selectedValue should show as checked");
	});

	it("uses roTrue/roFalse text instead of the check icon when given", async() =>
	{
		element.roTrue = "Yes";
		element.roFalse = "No";

		element.set_value("1");
		await element.updateComplete;
		assert.notExists(element.shadowRoot.querySelector("sl-icon"));
		assert.include(element.shadowRoot.querySelector(".checkbox__control").textContent, "Yes");

		element.set_value("");
		await element.updateComplete;
		assert.include(element.shadowRoot.querySelector(".checkbox__control").textContent, "No");
	});
});

// This is a permanently-readonly *display* widget (the framework swaps in this whole separate
// class instead of toggling a "readonly" flag on Et2Checkbox), but it still extends
// Et2InputWidget with the base get_value()/isValid() unmodified, so the standard contract applies.
// Its label/check icon are plain content (part="label"/"control", not the form-control-label/
// form-control-help-text convention), like Et2Checkbox itself.
inputBasicTests(before, "1", "input", {
	skip: ["label", "help-text"],
	checkEmptyDisplay: (element : Et2CheckboxReadonly) =>
		assert.equal(element.shadowRoot.querySelector(".checkbox__control").textContent.trim(), "",
			"Displaying something when there is no value")
});
