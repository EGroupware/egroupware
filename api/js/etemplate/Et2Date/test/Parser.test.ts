/**
 * Test file for Etemplate date parsing
 */
import {assert, fixture} from '@open-wc/testing';
import {Et2Date, parseDate} from "../Et2Date";
import {html} from "lit-element";
import * as sinon from 'sinon';

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
		let test_date = new Date(2021, 8, 22, 0, 0, 0);

		let parsed = parser(test_string);

		assert.equal(parsed.toJSON(), test_date.toJSON());
	});

	it("Handles Y.d.m", () =>
	{
		let test_string = '2021.22.09';
		let test_date = new Date(2021, 8, 22, 0, 0, 0);

		//@ts-ignore
		window.egw = {
			preference: () => 'Y.d.m'
		};
		let parsed = parser(test_string);

		assert.equal(parsed.toJSON(), test_date.toJSON());
	});
});