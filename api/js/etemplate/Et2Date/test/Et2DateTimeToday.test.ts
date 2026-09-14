/**
 * Test file for Etemplate webComponent date-time-today
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2DateTimeToday} from "../Et2DateTimeToday";

describe("DateTimeToday widget", () =>
{
	// Reference to component under test
	let element : Et2DateTimeToday;

	// What preference("date_time_today") should return - set per-test
	let date_time_today_preference : string;

	beforeEach(async() =>
	{
		date_time_today_preference = "";

		// Stub global egw for preference
		// @ts-ignore
		window.egw = {
			preference: (name : string) =>
			{
				if(name === "dateformat") return "Y-m-d";
				if(name === "timeformat") return "24";
				if(name === "date_time_today") return date_time_today_preference;
				return "";
			},
			tooltipBind: () => {},
			tooltipUnbind: () => {},
			lang: i => i
		};

		element = await fixture<Et2DateTimeToday>(html`
            <et2-date-time-today></et2-date-time-today>
		`);

		await element.updateComplete;
	});

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2DateTimeToday);
	});

	describe("default behaviour (preference unset, '0', or anything but 'full')", () =>
	{
		for(const value of ["", "0", "anything_else"])
		{
			it(`shows just the time for today's date when preference is "${value}"`, async() =>
			{
				date_time_today_preference = value;

				const now = new Date();
				element.set_value(now.toISOString());
				await elementUpdated(element);

				assert.include(element.innerText, ":", "Time was not shown for today");
				// Should not contain the full 4-digit year (only time is shown)
				assert.notInclude(element.innerText, now.getFullYear().toString());
			});
		}

		it("shows just the date (2-digit year) for a date that is not today", async() =>
		{
			element.set_value("2021-09-22T12:00:00Z");
			await elementUpdated(element);

			assert.equal(element.innerText, "2021-09-22".replace(/20(\d{2})/, '$1'));
			// Time is not part of the display, only in the tooltip
			assert.notInclude(element.innerText, ":");
		});
	});

	describe('preference "full"', () =>
	{
		beforeEach(() => { date_time_today_preference = "full"; });

		it("shows full date and time for today's date", async() =>
		{
			const now = new Date();
			element.set_value(now.toISOString());
			await elementUpdated(element);

			assert.include(element.innerText, now.getFullYear().toString(),
				"Full date was not shown for today");
			assert.include(element.innerText, ":", "Time was not shown alongside the full date");
		});

		it("shows full date and time for a date that is not today", async() =>
		{
			element.set_value("2021-09-22T12:00:00Z");
			await elementUpdated(element);

			assert.include(element.innerText, "2021-09-22", "Full 4-digit-year date was not shown");
			assert.include(element.innerText, ":", "Time was not shown alongside the full date");
		});
	});
});
