/**
 * Test file for Etemplate date parsing
 */
import {assert} from '@open-wc/testing';
import {parseDate, parseTime} from "../Et2Date";

describe("Date parsing", () =>
{
	// Function under test
	let parser = parseDate;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Stub global egw for preference
		// @ts-ignore
		window.egw = {
			preference: () => 'Y-m-d'
		};
	});

	it("Handles server format", () =>
	{
		let test_string = '2021-09-22T19:22:00Z';
		let test_date = new Date(test_string);

		let parsed = parser(test_string);

		// Can't compare results - different objects
		//assert.equal(parsed, test_date);
		assert.equal(parsed.toJSON(), test_date.toJSON());
	});

	it("Handles Y-m-d", () =>
	{
		let test_string = '2021-09-22';
		let test_date = new Date("2021-09-22T00:00:00Z");

		let parsed = parser(test_string);

		assert.equal(parsed.toJSON(), test_date.toJSON());
	});

	it("Handles Y.d.m", () =>
	{
		let test_string = '2021.22.09';
		let test_date = new Date("2021-09-22T00:00:00Z");

		//@ts-ignore
		window.egw = {
			preference: () => 'Y.d.m'
		};
		let parsed = parser(test_string);

		assert.equal(parsed.toJSON(), test_date.toJSON());
	});


	it("Handles '0'", () =>
	{
		let test_string = '0';
		let test_date = undefined;

		//@ts-ignore
		window.egw = {
			preference: () => 'Y.d.m'
		};
		let parsed = parser(test_string);

		assert.equal(parsed, test_date);
	});
});


describe("Time parsing", () =>
{
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
			// As expected
			"9:15 am": new Date('1970-01-01T09:15:00Z'),
			"12:00 am": new Date('1970-01-01T00:00:00Z'),
			"12:00 pm": new Date('1970-01-01T12:00:00Z'),
			"5:00 pm": new Date('1970-01-01T17:00:00Z'),
			"11:59 pm": new Date('1970-01-01T23:59:00Z'),

			// Not valid, should be undefined
			"invalid": undefined,
			"23:45 pm": undefined,
			"0": undefined,
			"": undefined
		};
		for(let test_string of Object.keys(test_data))
		{
			let test_date = test_data[test_string];
			let parsed = parseTime(test_string);

			if(typeof test_date == "undefined")
			{
				assert.isUndefined(parsed);
			}
			else
			{
				assert.equal(parsed.toJSON(), test_date.toJSON());
			}
		}
	});

	it("Handles 24h", () =>
	{
		const test_data = {
			"09:15": new Date('1970-01-01T09:15:00Z'),
			"00:00": new Date('1970-01-01T00:00:00Z'),
			"12:00": new Date('1970-01-01T12:00:00Z'),
			"17:00": new Date('1970-01-01T17:00:00Z'),
			"23:59": new Date('1970-01-01T23:59:00Z'),

			// Not valid, should be undefined
			"invalid": undefined,
			"23:45 pm": undefined,
			"0": undefined,
			"": undefined
		};
		for(let test_string of Object.keys(test_data))
		{
			let test_date = test_data[test_string];
			let parsed = parseTime(test_string);

			if(typeof test_date == "undefined")
			{
				assert.isUndefined(parsed);
			}
			else
			{
				assert.equal(parsed.toJSON(), test_date.toJSON());
			}
		}
	});
});