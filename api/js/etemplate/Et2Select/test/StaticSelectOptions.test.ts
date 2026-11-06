/**
 * Tests for the Et2Select subclasses that build their option list entirely client-side.
 *
 * These are `Et2Select/Select/*.ts` - ~35 registered tags with roughly 1400 uses across shipped
 * templates and, until now, no coverage at all.  They are small, but they are small in the way
 * that hides breakage: each is little more than a constructor assigning `_static_options`, so a
 * change to `Et2StaticSelectMixin`, to `StaticOptions`, or to the property-decorator plumbing can
 * silently empty one of them without anything failing anywhere.
 *
 * Behaviour under test:
 * - every static subclass upgrades and offers its documented option set
 * - the numeric family (number/percent/day/hour/year) respects min/max/interval/suffix and
 *   recomputes when those change after construction
 * - Et2SelectBool coerces anything truthy to "1" and "0"/"false"/empty to "0"
 * - Et2SelectDayOfWeek expands a packed bitmask value into the individual day values
 * - server-fed options merge with the static ones rather than replacing them
 *
 * Setup strategy:
 * The shared `egwStub` from ./helpers is installed globally before any element is created -
 * several of these read `egw().preference()` or `egw().lang()` from their *constructor*, which is
 * too early to stub per instance.  Option sets are asserted on `select_options` rather than on
 * rendered `<sl-option>` nodes, because Et2Select defers building those until interaction; where
 * the rendered list matters, `activateOptions()` forces it.
 *
 * Pass criteria:
 * Explicit assertions on option values/labels and on the coerced value.  A failure means one of
 * these selects is offering the user the wrong list, which in a filter or an edit dialog is
 * silent data loss rather than a visible error.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";
import {activateOptions, egwStub, visibleOptionValues} from "./helpers";

// Must be in place before the first element is constructed - several subclasses read
// egw().preference()/lang() from their constructor.
const callableEgw = function() { return egwStub; };
Object.assign(callableEgw, egwStub);
// @ts-ignore
window.egw = callableEgw;

import "../Select/Et2SelectBool";
import "../Select/Et2SelectDay";
import "../Select/Et2SelectDayOfWeek";
import "../Select/Et2SelectHour";
import "../Select/Et2SelectMonth";
import "../Select/Et2SelectNumber";
import "../Select/Et2SelectPercent";
import "../Select/Et2SelectPriority";
import "../Select/Et2SelectYear";
import "../Select/Et2SelectAccess";
import "../Select/Et2SelectBitwise";
import {Et2SelectBool} from "../Select/Et2SelectBool";
import {Et2SelectDayOfWeek} from "../Select/Et2SelectDayOfWeek";
import {Et2SelectNumber} from "../Select/Et2SelectNumber";

const make = async(tag : string, attributes = "") =>
{
	const element = <any>await fixture(`<${tag} ${attributes}></${tag}>`);
	await element.updateComplete;
	return element;
};

const values = (element : any) => (element.select_options || []).map(o => "" + o.value);
const labels = (element : any) => (element.select_options || []).map(o => "" + o.label);

describe("Static Et2Select subclasses", () =>
{
	describe("fixed option lists", () =>
	{
		it("et2-select-priority offers low/normal/high plus undefined", async() =>
		{
			const element = await make("et2-select-priority");
			assert.deepEqual(values(element), ["1", "2", "3", "0"], "priority options changed");
			assert.deepEqual(labels(element), ["low", "normal", "high", "undefined"], "priority labels changed");
		});

		it("et2-select-bool offers no/yes", async() =>
		{
			const element = await make("et2-select-bool");
			assert.deepEqual(values(element), ["0", "1"], "bool options changed");
			assert.deepEqual(labels(element), ["no", "yes"], "bool labels changed");
		});

		it("et2-select-month offers all twelve months", async() =>
		{
			const element = await make("et2-select-month");
			assert.lengthOf(element.select_options, 12, "a year has twelve months");
			assert.equal(element.select_options[0].value, "1", "January must be 1, not 0 - it is a date part");
			assert.equal(element.select_options[11].label, "December", "last month changed");
		});

		it("et2-select-day offers 1..31", async() =>
		{
			const element = await make("et2-select-day");
			assert.lengthOf(element.select_options, 31, "day of month runs 1..31");
			assert.deepEqual(
				[values(element)[0], values(element)[30]],
				["1", "31"],
				"day options should span the whole month"
			);
		});

		it("et2-select-hour offers 0..23", async() =>
		{
			const element = await make("et2-select-hour");
			assert.lengthOf(element.select_options, 24, "a day has 24 hours");
			assert.deepEqual([values(element)[0], values(element)[23]], ["0", "23"], "hour range changed");
		});

		it("et2-select-percent counts to 100 in tens", async() =>
		{
			const element = await make("et2-select-percent");
			assert.deepEqual(
				values(element),
				["0", "10", "20", "30", "40", "50", "60", "70", "80", "90", "100"],
				"percent should step 0..100 by 10"
			);
		});

		it("et2-select-access and et2-select-bitwise upgrade without options of their own", async() =>
		{
			// Both get their real options from the server; the point here is that they construct
			// and render rather than throwing on an empty static list.
			const access = await make("et2-select-access");
			const bitwise = await make("et2-select-bitwise");
			assert.isArray(access.select_options, "et2-select-access should still expose an option array");
			assert.isArray(bitwise.select_options, "et2-select-bitwise should still expose an option array");
		});
	});

	/**
	 * et2-select-hour's labels are a port of PHP's `case 'select-hour'`
	 * (api/src/Etemplate/Widget/Select.php), and both transcription errors that were in it hid
	 * each other: the preference was read with name and app swapped, so the 12-hour branch never
	 * ran, so the bug inside it - midnight and noon labelled "0" because `h % 12` is 0 for both -
	 * never showed.  These pin the preference lookup and the two hours that `h % 12` alone gets
	 * wrong.
	 */
	describe("et2-select-hour labels", () =>
	{
		let originalPreference;

		/**
		 * The option list is built in the constructor, so this has to be set before make().
		 *
		 * It has to go on `window.egw` and not just on `egwStub`: Et2Widget.egw() falls back to
		 * `window['egw']` itself (the callable, with the stub's properties copied onto it) rather
		 * than to its return value, so overriding only the stub object leaves the widget reading
		 * the copy.
		 */
		const withTimeformat = (timeformat : string) =>
		{
			(<any>window).egw.preference = (name, app) =>
				name === "timeformat" && app === "common" ? timeformat : null;
		};

		beforeEach(() => {originalPreference = (<any>window).egw.preference;});
		afterEach(() => {(<any>window).egw.preference = originalPreference;});

		it("uses zero-padded 24-hour labels when the preference is not 12", async() =>
		{
			withTimeformat("24");
			const element = await make("et2-select-hour");
			assert.deepEqual(
				[labels(element)[0], labels(element)[9], labels(element)[12], labels(element)[23]],
				["00", "09", "12", "23"],
				"24-hour labels should be the zero-padded hour"
			);
		});

		it("uses am/pm labels when the preference is 12", async() =>
		{
			withTimeformat("12");
			const element = await make("et2-select-hour");
			assert.deepEqual(
				[labels(element)[9], labels(element)[13]],
				["9 " + egwStub.lang("am"), "1 " + egwStub.lang("pm")],
				"12-hour labels should count 1..12 and carry am/pm"
			);
		});

		it("labels midnight and noon 12, not 0", async() =>
		{
			withTimeformat("12");
			const element = await make("et2-select-hour");
			assert.deepEqual(
				[labels(element)[0], labels(element)[12]],
				["12 " + egwStub.lang("am"), "12 " + egwStub.lang("pm")],
				"h % 12 is 0 for both midnight and noon - they must fall back to 12"
			);
		});
	});

	describe("et2-select-number", () =>
	{
		it("defaults to 1..10", async() =>
		{
			const element = await make("et2-select-number");
			assert.deepEqual(values(element), ["1", "2", "3", "4", "5", "6", "7", "8", "9", "10"], "default range changed");
		});

		it("honours min, max and interval", async() =>
		{
			const element = await make("et2-select-number", 'min="0" max="10" interval="5"');
			assert.deepEqual(values(element), ["0", "5", "10"], "min/max/interval should shape the list");
		});

		it("recomputes when the range changes after construction", async() =>
		{
			const element = <Et2SelectNumber>await make("et2-select-number");
			element.max = 3;
			await element.updateComplete;

			assert.deepEqual(values(element), ["1", "2", "3"], "changing max must rebuild the options");
		});

		/**
		 * NOTE: this pins the *actual* behaviour, which does not match what `leading_zero`'s
		 * docblock promises ("Set to how many zeros you want (000)").  StaticOptions.number()
		 * derives the padding width from the INTERVAL's digit count, not from the length of
		 * leading_zero - so leading_zero only ever acts as an on/off switch, and with the default
		 * interval of 1 it pads to width 1, i.e. does nothing visible.  Changing that is a product
		 * decision, not a test fix; these two cases exist so the current behaviour is at least
		 * known rather than assumed.
		 */
		it("treats leading_zero as a switch, taking its width from the interval", async() =>
		{
			const element = await make("et2-select-number", 'min="1" max="3" leading_zero="00"');
			assert.deepEqual(labels(element), ["1", "2", "3"], "interval 1 gives width 1, so nothing is padded");
			assert.deepEqual(values(element), ["1", "2", "3"], "values must stay unpadded regardless");
		});

		it("pads to the interval's width when the interval has more digits", async() =>
		{
			const element = await make("et2-select-number", 'min="0" max="30" interval="10" leading_zero="0"');
			assert.deepEqual(labels(element), ["00", "10", "20", "30"], "a two-digit interval pads labels to width 2");
			assert.deepEqual(values(element), ["0", "10", "20", "30"], "values must stay unpadded");
		});

		it("does not loop forever when the interval points the wrong way", async() =>
		{
			// StaticOptions.number() flips a negative interval rather than hanging; this pins that
			// guard, because getting it wrong locks up the browser rather than failing a test.
			const element = await make("et2-select-number", 'min="1" max="5" interval="-1"');
			assert.deepEqual(values(element), ["1", "2", "3", "4", "5"], "a backwards interval should be corrected");
		});
	});

	describe("et2-select-year", () =>
	{
		it("spans the current year minus 3 to plus 2 by default", async() =>
		{
			const element = await make("et2-select-year");
			const thisYear = new Date().getFullYear();
			const expected = [-3, -2, -1, 0, 1, 2].map(offset => "" + (thisYear + offset));

			assert.deepEqual(values(element), expected, "year offsets are relative to the current year");
		});
	});

	describe("et2-select-bool value coercion", () =>
	{
		const cases : [any, string][] = [
			[true, "1"],
			["1", "1"],
			["yes", "1"],
			[1, "1"],
			[false, "0"],
			["0", "0"],
			["false", "0"],
			["", "0"],
			[null, "0"],
			[undefined, "0"]
		];

		cases.forEach(([input, expected]) =>
		{
			it(`coerces ${JSON.stringify(input)} to "${expected}"`, async() =>
			{
				const element = <Et2SelectBool>await make("et2-select-bool");
				element.value = input;
				await element.updateComplete;

				assert.strictEqual(
					element.value,
					expected,
					"bool options are the strings '0' and '1', so the value has to be one of them"
				);
			});
		});
	});

	describe("et2-select-dow bitmask", () =>
	{
		it("expands a packed bitmask into the individual days", async() =>
		{
			// `multiple` matters: Et2Select's value getter only returns an array when multiple is
			// set, and a day-of-week picker is always multiple in practice.
			const element = <Et2SelectDayOfWeek>await make("et2-select-dow", "multiple");
			// Options come from the server; supply them directly so the test does not depend on
			// the endpoint.  1|2|4 = Monday|Tuesday|Wednesday in the packed representation.
			(<any>element).fetchComplete = Promise.resolve();
			element.select_options = [
				{value: "1", label: "Monday"},
				{value: "2", label: "Tuesday"},
				{value: "4", label: "Wednesday"},
				{value: "8", label: "Thursday"}
			];
			await element.updateComplete;

			element.value = "7";
			await element.updateComplete;
			await (<any>element).fetchComplete;
			await element.updateComplete;

			assert.deepEqual(
				element.value,
				["1", "2", "4"],
				"a packed dow value must expand to every day whose bit is set"
			);
		});

		it("passes an already-expanded array through", async() =>
		{
			const element = <Et2SelectDayOfWeek>await make("et2-select-dow", "multiple");
			element.value = ["1", "4"];
			await element.updateComplete;

			assert.deepEqual(element.value, ["1", "4"], "an array value is already expanded");
		});
	});

	it("merges server-supplied options with the static ones", async() =>
	{
		// The whole reason Et2StaticSelectMixin keeps _static_options separate: sel_options from
		// the server must add to the built-in list, not replace it.
		const element = <any>await make("et2-select-priority");
		element.select_options = [{value: "9", label: "Critical"}];
		await element.updateComplete;
		await activateOptions(element);

		assert.include(values(element), "9", "server option should be present");
		assert.includeMembers(values(element), ["1", "2", "3", "0"], "static options must survive");
		assert.includeMembers(visibleOptionValues(element), ["9", "1"], "merged options should render");
	});
});
