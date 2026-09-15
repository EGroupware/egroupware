/**
 * Test file for Et2DateRange's relative range table
 *
 * relativeToAbsolute() and the relative_dates entries it drives are pure date arithmetic with no
 * DOM involved, so they are tested directly against a fixed reference day rather than through a
 * widget fixture.  Month lengths and year boundaries are where this kind of code goes wrong, so
 * they get their own cases.
 */
import {assert} from '@open-wc/testing';
import {Et2DateRange} from "../Et2DateRange";

// Et2DateRange reads week_start off the global egw, which egw_global.js snapshots from
// window.egw - so this is the same object it holds.  week_start() itself comes from
// egw_calendar, which isn't loaded here, and depends on a user preference we have no session
// for.  Weeks start Sunday, matching egw's own default.
const original_week_start = window.egw.week_start;
before(() =>
{
	window.egw.week_start = (date) =>
	{
		const d = new Date(date);
		d.setUTCDate(d.getUTCDate() - d.getUTCDay());
		return d;
	};
});
after(() =>
{
	window.egw.week_start = original_week_start;
});

/**
 * UTC midnight on the given day, which is what relativeToAbsolute() works in
 */
function day(year : number, month : number, date : number) : Date
{
	return new Date(Date.UTC(year, month - 1, date));
}

/**
 * Just the date part, so a failure reads as "2026-08-31" rather than a full ISO timestamp
 */
function ymd(date : Date | string) : string
{
	return date instanceof Date ? date.toISOString().substring(0, 10) : <string>date;
}

function range(name : string, reference : Date) : { from : string, to : string }
{
	const absolute = Et2DateRange.relativeToAbsolute(name, reference);
	return {from: ymd(absolute.from), to: ymd(absolute.to)};
}

describe("Date range relative dates", () =>
{
	describe("ranges from a plain mid-month day (Tuesday 2026-09-15)", () =>
	{
		const reference = day(2026, 9, 15);
		const expected = {
			'Today': ['2026-09-15', '2026-09-15'],
			'Yesterday': ['2026-09-14', '2026-09-14'],
			'This week': ['2026-09-13', '2026-09-19'],
			'Last week': ['2026-09-06', '2026-09-12'],
			'This month': ['2026-09-01', '2026-09-30'],
			'Last month': ['2026-08-01', '2026-08-31'],
			'Last 3 months': ['2026-07-01', '2026-09-30'],
			'This year': ['2026-01-01', '2026-12-31'],
			'Last year': ['2025-01-01', '2025-12-31']
		};

		Object.keys(expected).forEach(name =>
		{
			it(name, () =>
			{
				assert.deepEqual(range(name, reference), {from: expected[name][0], to: expected[name][1]});
			});
		});

		it("covers every option offered by the widget", () =>
		{
			assert.sameMembers(Et2DateRange.relative_dates.map(o => o.value), Object.keys(expected));
		});
	});

	describe("month ends", () =>
	{
		it("Last month from the 31st of a month does not skip February", () =>
		{
			assert.deepEqual(range("Last month", day(2026, 3, 31)), {from: '2026-02-01', to: '2026-02-28'});
		});

		it("Last month from the 31st of a month does not skip a 30 day month", () =>
		{
			assert.deepEqual(range("Last month", day(2026, 7, 31)), {from: '2026-06-01', to: '2026-06-30'});
		});

		it("Last 3 months from the 31st of a month does not skip a 30 day month", () =>
		{
			assert.deepEqual(range("Last 3 months", day(2026, 8, 31)), {from: '2026-06-01', to: '2026-08-31'});
		});

		it("This month ends on the last day of a 31 day month", () =>
		{
			assert.deepEqual(range("This month", day(2026, 8, 1)), {from: '2026-08-01', to: '2026-08-31'});
		});

		it("This month ends on the last day of February", () =>
		{
			assert.deepEqual(range("This month", day(2027, 2, 15)), {from: '2027-02-01', to: '2027-02-28'});
		});

		it("This month ends on the last day of a leap February", () =>
		{
			assert.deepEqual(range("This month", day(2028, 2, 29)), {from: '2028-02-01', to: '2028-02-29'});
		});

		it("Last month ends on the last day of a leap February", () =>
		{
			assert.deepEqual(range("Last month", day(2028, 3, 15)), {from: '2028-02-01', to: '2028-02-29'});
		});
	});

	describe("year boundaries", () =>
	{
		it("Yesterday on 1 January is in the previous year", () =>
		{
			assert.deepEqual(range("Yesterday", day(2026, 1, 1)), {from: '2025-12-31', to: '2025-12-31'});
		});

		it("Last month in January is December of the previous year", () =>
		{
			assert.deepEqual(range("Last month", day(2027, 1, 15)), {from: '2026-12-01', to: '2026-12-31'});
		});

		it("Last month on 31 December stays in December", () =>
		{
			assert.deepEqual(range("Last month", day(2026, 12, 31)), {from: '2026-11-01', to: '2026-11-30'});
		});

		it("Last 3 months in January reaches back into the previous year", () =>
		{
			assert.deepEqual(range("Last 3 months", day(2027, 1, 15)), {from: '2026-11-01', to: '2027-01-31'});
		});

		it("This week can start in the previous year", () =>
		{
			assert.deepEqual(range("This week", day(2027, 1, 1)), {from: '2026-12-27', to: '2027-01-02'});
		});

		it("Last year from a leap day is the whole previous year", () =>
		{
			assert.deepEqual(range("Last year", day(2028, 2, 29)), {from: '2027-01-01', to: '2027-12-31'});
		});

		it("Last year from 31 December is the whole previous year", () =>
		{
			assert.deepEqual(range("Last year", day(2026, 12, 31)), {from: '2025-01-01', to: '2025-12-31'});
		});
	});

	describe("every option", () =>
	{
		// A range that ends before it starts, or that runs past the day it was calculated from,
		// is wrong no matter which option produced it
		[day(2026, 9, 15), day(2026, 1, 1), day(2026, 3, 31), day(2028, 2, 29), day(2026, 12, 31)]
			.forEach(reference =>
			{
				Et2DateRange.relative_dates.forEach(option =>
				{
					it(`${option.value} from ${ymd(reference)} is a valid range`, () =>
					{
						const absolute = Et2DateRange.relativeToAbsolute(option.value, reference);

						assert.instanceOf(absolute.from, Date, "from is not a date");
						assert.instanceOf(absolute.to, Date, "to is not a date");
						assert.isFalse(isNaN(<any>absolute.from), "from is an invalid date");
						assert.isFalse(isNaN(<any>absolute.to), "to is an invalid date");
						assert.isAtMost((<Date>absolute.from).valueOf(), (<Date>absolute.to).valueOf(),
							`Range ends before it starts: ${ymd(absolute.from)} - ${ymd(absolute.to)}`);
						assert.notStrictEqual(absolute.from, absolute.to,
							"from and to are the same object, so one can be changed by mutating the other");
					});
				});
			});

		it("does not change the reference date it was given", () =>
		{
			const reference = day(2026, 9, 15);
			Et2DateRange.relative_dates.forEach(option =>
			{
				Et2DateRange.relativeToAbsolute(option.value, reference);
				assert.equal(ymd(reference), '2026-09-15', `${option.value} changed the date it was given`);
			});
		});
	});

	describe("unknown ranges", () =>
	{
		it("returns an empty range for a name that is not in the list", () =>
		{
			assert.deepEqual(Et2DateRange.relativeToAbsolute("Next century", day(2026, 9, 15)), {from: '', to: ''});
		});

		it("returns an empty range for no name at all", () =>
		{
			assert.deepEqual(Et2DateRange.relativeToAbsolute("", day(2026, 9, 15)), {from: '', to: ''});
		});
	});
});
