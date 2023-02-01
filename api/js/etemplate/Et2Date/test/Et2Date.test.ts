/**
 * Test file for Etemplate webComponent Date
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Date} from "../Et2Date";
import * as sinon from 'sinon';
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

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
		preference: () => null,
		// Image always give check mark.  Use data URL to avoid having to serve an actual image
		image: i => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo="

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
		assert.equal(element.querySelector("et2-textbox").value, "");
		assert.equal(element.get_value(), '');
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