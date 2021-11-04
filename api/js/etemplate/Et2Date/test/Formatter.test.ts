/**
 * Test file for Etemplate date formatting
 *
 * For now, the best way to check if different timezones work is to change the TZ of your
 * computer, then run the tests,
 */
import {assert} from '@open-wc/testing';
import {formatDate, formatTime} from "../Et2Date";

describe("Date formatting", () =>
{
	// Function under test
	let formatter = formatDate;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Stub global egw for preference
		// @ts-ignore
		window.egw = {
			preference: () => 'Y-m-d'
		};
	});

	it("Handles Y-m-d", () =>
	{
		let test_string = '2021-09-22';
		let test_date = new Date("2021-09-22T12:34:56Z");

		let formatted = formatter(test_date);

		assert.equal(formatted, test_string);
	});

	it("Handles Y.d.m", () =>
	{
		let test_string = '2021.22.09';
		let test_date = new Date("2021-09-22T12:34:56Z");

		//@ts-ignore
		window.egw = {
			preference: () => 'Y.d.m'
		};
		let formatted = formatter(test_date);

		assert.equal(formatted, test_string);
	});
});

describe("Time formatting", () =>
{
	// Function under test
	let formatter = formatTime;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Stub global egw for preference
		// @ts-ignore
		window.egw = {
			preference: () => 'Y-m-d'
		};
	});

	it("Handles 12h", () =>
	{
		const test_data = {
			"9:15 am": new Date('2021-09-22T09:15:00Z'),
			"12:00 am": new Date('2021-09-22T00:00:00Z'),
			"12:00 pm": new Date('2021-09-22T12:00:00Z'),
			"5:00 pm": new Date('2021-09-22T17:00:00Z'),
		};
		for(let test_string of Object.keys(test_data))
		{
			let test_date = test_data[test_string];
			let formatted = formatter(test_date, {timeFormat: "12"});

			assert.equal(formatted, test_string);

		}
	});

	it("Handles 24h", () =>
	{
		const test_data = {
			"09:15": new Date('2021-09-22T09:15:00Z'),
			"00:00": new Date('2021-09-22T00:00:00Z'),
			"12:00": new Date('2021-09-22T12:00:00Z'),
			"17:00": new Date('2021-09-22T17:00:00Z'),
		};
		for(let test_string of Object.keys(test_data))
		{
			let test_date = test_data[test_string];
			let formatted = formatter(test_date, {timeFormat: "24"});

			assert.equal(formatted, test_string);

		}
	});
});