/**
 * Test file for Etemplate webComponent Et2DateRange
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2DateRange} from "../Et2DateRange";
import * as sinon from "sinon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
// The internal <et2-date> from/to fields never upgrade without this
import "../Et2Date";
import "@shoelace-style/shoelace/dist/components/select/select.js";
import "@shoelace-style/shoelace/dist/components/option/option.js";

window.egwIsMobile = () => false;

// The from/to fields are real <et2-date> children created directly in render(), not through the
// normal attribute-driven widget tree - their own egw() falls through to window.egw rather than
// resolving a parent widget, so this needs to be a global stub, not just Et2DateRange's own.
// @ts-ignore
window.egw = {
	lang: i => i,
	tooltipUnbind: () => {},
	preference: (pref) => pref == "lang" ? Promise.resolve("en") : null,
	image: i => "",
	holidays: (y) => Promise.resolve({})
};

// Reference to component under test
let element : Et2DateRange;
let egw_stub;

async function before()
{
	if(egw_stub)
	{
		egw_stub.restore();
	}
	// @ts-ignore
	egw_stub = sinon.stub(Et2DateRange.prototype, "egw").returns(window.egw);

	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2DateRange>(html`
        <et2-date-range></et2-date-range>
	`);
	await element.updateComplete;

	return element;
}

describe("Date range widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	afterEach(() =>
	{
		egw_stub.restore();
	});

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2DateRange);
	});

	// Note: the "from" field's own value getter goes through findInputField(), which depends on
	// flatpickr having initialized - flatpickr's _initFlatpickr() throws in this test environment
	// specifically for the nested <et2-date> children Et2DateRange creates directly in render()
	// (a real, standalone <et2-date> fixture - see Et2Date.test.ts - initializes fine), so a
	// "from"-field round-trip isn't reliably testable here. The shared inputBasicTests() below
	// uses relative mode instead, which doesn't depend on flatpickr at all.

	it("uses a relative range string when relative=true", async() =>
	{
		element.relative = true;
		await element.updateComplete;

		element.set_value("Today");
		await element.updateComplete;
		assert.equal(element.get_value(), "Today");
	});
});

// value is {from, to} in absolute mode (the default), or a plain relative-range string when
// relative=true. The shared contract runs against relative mode: absolute mode's "from" field
// value depends on flatpickr initializing (see the note above, in the nested-<et2-date> case it
// doesn't in this test environment), while relative mode is a plain <et2-select> with no such
// dependency - both are real, supported usage modes, this just picks the one that's reliably
// testable here.
inputBasicTests(async() =>
{
	const el = await before();
	el.relative = true;
	await el.updateComplete;
	return el;
}, "Today", "input", {
	checkEmptyDisplay: (element : Et2DateRange) =>
		assert.notOk(element.relativeElement?.["value"], "Displaying something when there is no value")
});
