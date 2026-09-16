/**
 * Test file for Etemplate webComponent Date
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Date} from "../Et2Date";
import * as sinon from 'sinon';
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";
import flatpickr from "flatpickr";

let element : Et2Date;
let egw_stub;

// Stub global function
window.egwIsMobile = () => false;

async function before()
{
	// Stub egw
	if(egw_stub)
	{
		egw_stub.restore();
	}
	// @ts-ignore
	egw_stub = sinon.stub(Et2Date.prototype, "egw").returns({
		lang: i => i,
		tooltipUnbind: () => {},
		preference: (pref) => pref == "lang" ? Promise.resolve("en") : null,
		// Image always give check mark.  Use data URL to avoid having to serve an actual image
		image: i => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
		holidays: (y) => Promise.resolve({})
	});

	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2Date>(html`
        <et2-date label="I'm a date"></et2-date>
	`);

	await element.updateComplete;
	
	return element;
};

describe("Date widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	afterEach(() =>
	{
		egw_stub.restore();
	});

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Date);
		assert.instanceOf(element._inputNode, Et2Textbox);
	});

	it('has a label', () =>
	{
		element.set_label("Label set");

		assert.equal(element.querySelector("[slot='label']").textContent, "Label set");
	})


	it("'0' shows nothing", async() =>
	{
		element.set_value("0");
		// wait for asychronous changes to the DOM
		await elementUpdated(element);
		assert.equal(element._inputNode.value, "");
		assert.equal(element.get_value(), '');
	});

	/**
	 * Regression test: flatpickr's own clear() defaults triggerChangeEvent to true, unlike
	 * setDate() (used for a real value) which this same setter calls with no triggerChange arg
	 * (defaults false). Programmatically resetting value to empty must not fire "change" -
	 * a real caller (Et2Filterbox re-applying a nextmatch's saved filter state) listens for
	 * "change" and calls back into Et2Nextmatch.applyFilters(), which can re-set this same
	 * widget's value to empty again - an infinite loop reproduced live against a real mailbox
	 * whose date filter legitimately resolves to empty.
	 */
	it("setting value to empty does not re-fire a change event", async() =>
	{
		element.set_value("2008-09-22T00:00:00.000Z");
		await elementUpdated(element);

		const changeSpy = sinon.spy();
		element.addEventListener("change", changeSpy);

		element.set_value("");
		await elementUpdated(element);

		assert.isFalse(changeSpy.called);
	});

	describe("Value format", () =>
	{
		// A date has two string forms: what the user sees (their dateformat preference) and what
		// we store and submit.  Whichever way the user entered it, value has to be the second one
		// or the same day gives two different values depending on how it was entered.
		it("gives the stored format for a value that was set", async() =>
		{
			element.set_value("2026-11-05T00:00:00Z");
			await elementUpdated(element);

			assert.equal(element.get_value(), "2026-11-05T00:00:00Z");
		});

		it("gives the stored format for a date the user typed", async() =>
		{
			await element.init();
			await elementUpdated(element);

			// The visible field is flatpickr's altInput, which shows the dateformat preference
			const input = element.findInputField();
			input.value = "2026-11-05";
			input.dispatchEvent(new Event("input", {bubbles: true}));
			await elementUpdated(element);

			assert.equal(input.value, "2026-11-05", "Stopped showing what the user typed");
			assert.equal(element.get_value(), "2026-11-05T00:00:00Z");
		});

		// flatpickr parses every prefix of a date, so a half-typed date must not be committed:
		// the "change" it fired re-queried a nextmatch filter after the first keystroke, and the
		// re-render took the focus out of the field.
		it("does not commit a date the user is still typing", async() =>
		{
			element.set_value("2026-11-05T00:00:00Z");
			await element.init();
			await elementUpdated(element);
			const changeSpy = sinon.spy();
			element.addEventListener("change", changeSpy);

			const input = element.findInputField();
			for(const typed of ["2", "202", "2027-0", "2027-01-"])
			{
				input.value = typed;
				input.dispatchEvent(new Event("input", {bubbles: true}));
				await elementUpdated(element);
				assert.isFalse(changeSpy.called, `"${typed}" was committed`);
				assert.equal(element.get_value(), "2026-11-05T00:00:00Z", `"${typed}" changed the value`);
			}

			input.value = "2027-01-15";
			input.dispatchEvent(new Event("input", {bubbles: true}));
			await elementUpdated(element);
			assert.isTrue(changeSpy.called, "the complete date was not committed");
			assert.equal(element.get_value(), "2027-01-15T00:00:00Z");
		});

		// Not committing a half-typed date means anything that does not round-trip through the
		// display format is left alone while the user types - including a complete date written
		// in another form ("2027-1-5" for "Y-m-d").  Those are picked up by flatpickr's own blur
		// handler, so this is the safety net the test above leans on.
		it("commits an unpadded date the user typed when the field is left", async() =>
		{
			element.set_value("2026-11-05T00:00:00Z");
			await element.init();
			await elementUpdated(element);
			const changeSpy = sinon.spy();
			element.addEventListener("change", changeSpy);

			const input = element.findInputField();
			input.focus();
			input.value = "2027-1-5";
			input.dispatchEvent(new Event("input", {bubbles: true}));
			await elementUpdated(element);

			// Does not match "2027-01-05", so nothing is committed yet
			assert.isFalse(changeSpy.called, "committed while still typing");
			assert.equal(element.get_value(), "2026-11-05T00:00:00Z", "changed the value while typing");

			input.blur();
			await elementUpdated(element);
			await element.updateComplete;

			assert.equal(element.get_value(), "2027-01-05T00:00:00Z", "the typed date was lost on blur");
			assert.isTrue(changeSpy.called, "no change event when the field was left");
		});
	});

	describe("Minimum and maximum date", () =>
	{
		// Flatpickr isn't built until the widget is first shown, which is long after a template
		// has applied its attributes - so the limits have to survive until then.
		it("remembers a limit set before the calendar exists", async() =>
		{
			element.minDate = "2026-03-01T00:00:00Z";
			element.maxDate = "2026-03-31T00:00:00Z";

			assert.instanceOf(element.minDate, Date, "minDate was not kept");
			assert.instanceOf(element.maxDate, Date, "maxDate was not kept");

			await element.init();
			await elementUpdated(element);

			assert.equal(flatpickr.formatDate(element._instance.config.minDate, "Y-m-d"), "2026-03-01");
			assert.equal(flatpickr.formatDate(element._instance.config.maxDate, "Y-m-d"), "2026-03-31");
		});

		// The attributes have to be on the element from the start, the way a template applies
		// them - setting them on an element that has already rendered once is the easy case
		it("takes a limit from an attribute", async() =>
		{
			const limited = await fixture<Et2Date>(html`
                <et2-date mindate="2026-03-01T00:00:00Z" maxdate="2026-03-31T00:00:00Z"></et2-date>`);
			await elementUpdated(limited);
			await limited.init();
			await elementUpdated(limited);

			assert.equal(flatpickr.formatDate(limited._instance.config.minDate, "Y-m-d"), "2026-03-01");
			assert.equal(flatpickr.formatDate(limited._instance.config.maxDate, "Y-m-d"), "2026-03-31");
		});

		// min / max is what a template says, minDate / maxDate is what flatpickr calls it
		it("takes a limit from the min and max attributes a template uses", async() =>
		{
			const limited = await fixture<Et2Date>(html`
                <et2-date min="2026-03-01T00:00:00Z" max="2026-03-31T00:00:00Z"></et2-date>`);
			await elementUpdated(limited);
			await limited.init();
			await elementUpdated(limited);

			assert.equal(flatpickr.formatDate(limited._instance.config.minDate, "Y-m-d"), "2026-03-01");
			assert.equal(flatpickr.formatDate(limited._instance.config.maxDate, "Y-m-d"), "2026-03-31");
		});

		it("passes a limit on to a calendar that already exists", async() =>
		{
			await element.init();
			await elementUpdated(element);

			element.set_min("2026-03-01T00:00:00Z");
			element.set_max("2026-03-31T00:00:00Z");

			assert.equal(flatpickr.formatDate(element._instance.config.minDate, "Y-m-d"), "2026-03-01");
			assert.equal(flatpickr.formatDate(element._instance.config.maxDate, "Y-m-d"), "2026-03-31");
		});

		it("clears a limit", async() =>
		{
			element.minDate = "2026-03-01T00:00:00Z";
			await element.init();
			await elementUpdated(element);

			element.set_min("");

			assert.equal(element.minDate, "");
			assert.notOk(element._instance.config.minDate);
		});

		it("ignores something that is not a date", async() =>
		{
			element.minDate = "Not a date";

			assert.equal(element.minDate, "", "Kept an invalid date");

			await element.init();
			await elementUpdated(element);

			assert.notOk(element._instance.config.minDate);
		});
	});

	const tz_list = [
		{name: "America/Edmonton", offset: -600},
		{name: "UTC", offset: 0},
		{name: "Australia/Adelaide", offset: 630}
	];
	for(let tz of tz_list)
	{
		describe("Timezone: " + tz.name, () =>
		{
			// TODO: Figure out how to mock timezone...
			// Stub timezone offset to return a different value
			let tz_offset_stub = sinon.stub(Date.prototype, "getTimezoneOffset").returns(
				tz.offset
			);
			let test_time_string = '2008-09-22T12:00:00.000Z';
			let test_time = new Date(test_time_string);
			it('Can accept a value', async() =>
			{
				element.set_value(test_time_string);

				// wait for asychronous changes to the DOM
				await elementUpdated(element);
				// Widget gives time as a string so we can send to server, but zeros the time
				//assert.equal(element.getValue().substr(0, 11), test_time_string.substr(0, 11));
			});

			/* Doesn't work yet
			it("Can be modified", () =>
			{
				element.getInputNode().value = "2008-09-22";
				let event = new Event("change");
				element.getInputNode().dispatchEvent(event);

				// Use a Promise to wait for asychronous changes to the DOM
				return Promise.resolve().then(() =>
				{
					assert.equal(element.getValue(), "2008-09-22T00:00:00.000Z");
				});
			});

			 */

			// Put timezone offset back
			tz_offset_stub.restore();
		});
	}
});
inputBasicTests(before, "2008-09-22T00:00:00Z", "et2-textbox");