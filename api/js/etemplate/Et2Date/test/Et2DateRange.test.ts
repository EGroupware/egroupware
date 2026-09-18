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

	/**
	 * The two halves are independent <et2-date> widgets, so without wiring them the picker happily
	 * accepts a "to" earlier than its "from" - which is not a range, and downstream builds a query
	 * that can never match anything.
	 *
	 * Tested against stubbed halves rather than the real ones: the constraint reads their value and
	 * writes minDate/maxDate, and flatpickr does not initialize for the nested <et2-date> children
	 * in this environment (see the note above), so their real value getter is not dependable here.
	 */
	describe("keeps the two halves a range", () =>
	{
		let from, to;

		const stubHalves = (fromValue = "", toValue = "") =>
		{
			from = {value: fromValue, minDate: "", maxDate: ""};
			to = {value: toValue, minDate: "", maxDate: ""};
			Object.defineProperty(element, "fromElement", {get: () => from, configurable: true});
			Object.defineProperty(element, "toElement", {get: () => to, configurable: true});
		};

		it("stops To being set before From", () =>
		{
			stubHalves("2026-09-16", "2026-09-18");

			element["_constrainToRange"]();

			assert.equal(to.minDate, "2026-09-16", "To must not be allowed before From");
			assert.equal(from.maxDate, "2026-09-18", "From must not be allowed after To");
		});

		it("leaves the other half unconstrained while one is empty", () =>
		{
			stubHalves("", "2026-09-18");
			element["_constrainToRange"]();
			assert.equal(to.minDate, "", "no From yet, so To has no lower bound");
			assert.equal(from.maxDate, "2026-09-18");

			stubHalves("2026-09-16", "");
			element["_constrainToRange"]();
			assert.equal(to.minDate, "2026-09-16");
			assert.equal(from.maxDate, "", "no To yet, so From has no upper bound");
		});

		it("clears a constraint when its half is emptied again", () =>
		{
			stubHalves("2026-09-16", "2026-09-18");
			element["_constrainToRange"]();
			assert.equal(to.minDate, "2026-09-16");

			from.value = "";
			element["_constrainToRange"]();
			assert.equal(to.minDate, "", "clearing From must release To's lower bound, not keep it");
		});

		it("does not constrain a relative range", async() =>
		{
			element.relative = true;
			await element.updateComplete;
			stubHalves("2026-09-16", "2026-09-18");

			element["_constrainToRange"]();

			assert.equal(to.minDate, "", "a relative range is one select, there is nothing to pair");
			assert.equal(from.maxDate, "");
		});

		/**
		 * A value set programmatically - restoring a favourite, or a filter seeded by the server -
		 * never goes through the pickers' change event, so the constraint has to be applied there
		 * too or it only ever holds for ranges the user typed by hand.
		 */
		it("applies the constraint to a programmatically set value", () =>
		{
			stubHalves();
			element.value = {from: "2026-09-16", to: "2026-09-18"};

			assert.equal(to.minDate, "2026-09-16",
				"setting the value must constrain the halves, not just a user edit");
			assert.equal(from.maxDate, "2026-09-18");
		});
	});

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
