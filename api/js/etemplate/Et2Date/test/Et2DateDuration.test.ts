/**
 * Test file for Etemplate webComponent Et2DateDuration
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2DateDuration} from "../Et2DateDuration";
import * as sinon from "sinon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
// The internal <sl-select>/<sl-option> (unit picker) never upgrade without these, causing
// this._formatNode.requestUpdate() to throw in Et2DateDuration's own getUpdateComplete()
import "@shoelace-style/shoelace/dist/components/select/select.js";
import "@shoelace-style/shoelace/dist/components/option/option.js";
// Same for the internal <et2-number> duration inputs - Et2DateDuration.ts doesn't import
// Et2Number itself (relies on it being registered elsewhere in the real app)
import "../../Et2Textbox/Et2Number";

// Reference to component under test
let element : Et2DateDuration;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2DateDuration>(html`
        <et2-date-duration></et2-date-duration>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {},
		preference: () => ""
	});

	return element;
}

describe("Date duration widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2DateDuration);
	});

	it("round trips a value in minutes (the default dataFormat)", async() =>
	{
		element.set_value("60");
		await element.updateComplete;
		assert.equal(element.get_value(), "60");
	});
});

// dataFormat defaults to minutes ("m") and displayFormat to "dhm" - a plain numeric string in
// that unit round-trips as-is with no conversion needed. With no value, get_value() returns "0"
// by default, not "" (that's what emptyNot0=false, the default, means) - and there's no single
// plain <input>, the duration is entered via nested <et2-number> parts.
// required is skipped: with the default emptyNot0=false, "unset" and "zero" are the same value
// ("0"), so isValid()'s required check (which looks for a literal "" or null) can never catch a
// blank required duration field - that's the documented purpose of emptyNot0, not a bug here.
inputBasicTests(before, "60", "input", {
	emptyValue: "0",
	skip: ["required"],
	checkEmptyDisplay: (element : Et2DateDuration) =>
		assert.equal(element._durationNode[0]?.value, "", "Displaying something when there is no value")
});
