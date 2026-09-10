/**
 * Test file for Etemplate webComponent base widget Et2Colorpicker
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2Colorpicker} from "../Et2Colorpicker";
import * as sinon from 'sinon';
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

// Reference to component under test
let element : Et2Colorpicker;
let egw_stub;

async function before()
{
	// Stub egw - restore any previous stub first, since inputBasicTests() calls before()
	// repeatedly with no afterEach of its own between calls
	if(egw_stub)
	{
		egw_stub.restore();
	}
	// @ts-ignore
	egw_stub = sinon.stub(Et2Colorpicker.prototype, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {}
	});

	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Colorpicker>(html`
        <et2-colorpicker></et2-colorpicker>
	`);
	return element;
}

describe("Colorpicker widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	afterEach(() =>
	{
		egw_stub.restore();
	});

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Colorpicker);
	});

	it('clearing value', () =>
	{
		// set a value
		element.set_value("11111");
		// trigger the clear button
		element.shadowRoot.querySelector(".input__clear").dispatchEvent(new MouseEvent('click'));

		assert.equal(element.getValue(), "");
	});

});

// SlColorPicker has no form-control-label/form-control-help-text parts at all (its label is
// screen-reader only, wrapped in <sl-visually-hidden>) and no plain <input> to check for an empty
// display - use the trigger button's own "empty" class instead, and it normalizes hex to
// uppercase.
inputBasicTests(before, "#ff0000", "input", {
	expectedValue: "#FF0000",
	skip: ["label", "help-text"],
	checkEmptyDisplay: (element : Et2Colorpicker) =>
		assert.isTrue(
			element.shadowRoot.querySelector(".color-dropdown__trigger")?.classList.contains("color-dropdown__trigger--empty"),
			"Trigger does not show as empty when there is no value"
		)
});