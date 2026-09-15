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

/**
 * What the widget has decided to show, which is protected
 */
function displayed(widget : Et2DateDuration) : { value : any, unit : string }
{
	return (<any>widget)._display;
}

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

	describe("values the display has to round", () =>
	{
		// The field shows at most 2 decimals, so a duration that isn't a round number of the
		// displayed unit can only be shown approximately.  Reading it back has to give what was
		// put in, not what the rounded display converts to.
		it("keeps a value that does not divide evenly into days", async() =>
		{
			element.hoursPerDay = 6;
			element.set_value("960");
			await element.updateComplete;

			assert.equal(displayed(element).value, 2.67, "Not displaying rounded days");
			assert.equal(displayed(element).unit, "d", "Not displaying days");
			assert.equal(element.get_value(), "960");
		});

		it("keeps a value that does not divide evenly into hours", async() =>
		{
			element.set_value("100");
			await element.updateComplete;

			assert.equal(displayed(element).value, 1.67, "Not displaying rounded hours");
			assert.equal(displayed(element).unit, "h", "Not displaying hours");
			assert.equal(element.get_value(), "100");
		});

		it("uses what the user typed once they change it", async() =>
		{
			element.hoursPerDay = 6;
			element.set_value("960");
			await element.updateComplete;

			element._durationNode[0].value = "3";
			assert.equal(element.get_value(), "1080", "Did not pick up the user's change");
		});

		it("uses what the user selected once they change the unit", async() =>
		{
			element.hoursPerDay = 6;
			element.set_value("960");
			await element.updateComplete;

			element._formatNode.value = "h";
			assert.equal(element.get_value(), "160", "Did not pick up the changed unit");
		});
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
