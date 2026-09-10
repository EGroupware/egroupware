/**
 * Test file for Etemplate webComponent Et2Checkbox
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2Checkbox} from "../Et2Checkbox";
import * as sinon from "sinon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

// Reference to component under test
let element : Et2Checkbox;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Checkbox>(html`
        <et2-checkbox></et2-checkbox>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {}
	});

	return element;
}

describe("Checkbox widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Checkbox);
	});

	it("defaults to boolean true/false", async() =>
	{
		element.set_value(true);
		await element.updateComplete;
		assert.strictEqual(element.get_value(), true);

		element.set_value(false);
		await element.updateComplete;
		assert.strictEqual(element.get_value(), false);
	});

	it("supports custom selected/unselected values", async() =>
	{
		element.selectedValue = "yes";
		element.unselectedValue = "no";

		element.set_value("yes");
		await element.updateComplete;
		assert.strictEqual(element.get_value(), "yes");
		assert.isTrue(element.checked);

		element.set_value("no");
		await element.updateComplete;
		assert.strictEqual(element.get_value(), "no");
		assert.isFalse(element.checked);
	});

	it("goes indeterminate with the sentinel value", async() =>
	{
		element.set_value(Et2Checkbox.INDETERMINATE);
		await element.updateComplete;
		assert.isTrue(element.indeterminate);
		assert.isUndefined(element.get_value());
	});
});

// Et2Checkbox's value is a boolean (or custom selected/unselected values) - "no value" is false,
// not "". Its label is its own text content (the default slot, set via a direct-textContent
// override of the normal label mechanism), not a form-control-label part - like Et2Switch.
inputBasicTests(before, true, "input", {
	emptyValue: false,
	checkEmptyDisplay: () => {},
	skip: ["label"]
});
