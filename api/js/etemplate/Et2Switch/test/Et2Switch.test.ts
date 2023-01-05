/**
 * Test file for Etemplate webComponent Et2Switch
 */
import {assert, expect, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Switch} from "../Et2Switch";

describe("Switch widget", () =>
{
	// Reference to component under test
	let element : Et2Switch;


	// Setup run before each test
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		element = await fixture<Et2Switch>(html`
            <et2-switch label="I'm a switch"></et2-switch>
		`);

		// Stub egw()
		sinon.stub(element, "egw").returns({
			tooltipUnbind: () => {},
			// Image always give check mark.  Use data URL to avoid having to serve an actual image
			image: i => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo="
		});
	});

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Switch);
	});

	it('has a label', () =>
	{
		element.set_label("Label set");

		assert.equal(element.textContent.trim(), "Label set");
	})

	it("click happens", () =>
	{
		// Setup
		let clickSpy = sinon.spy();
		element.onclick = clickSpy;

		// Click
		element.click();

		// Check for once & only once
		assert(clickSpy.calledOnce, "Click only once");
	});

	it("change callback gets called on click", async() =>
	{
		// Note that we generally don't fire an actual change event

		// Setup
		const listener = oneEvent(element, "sl-change");
		let clickSpy = sinon.spy();
		element.onclick = clickSpy;

		// Click
		element.click();

		const event = await listener;

		assert(clickSpy.calledOnce, "onchange callback should be called only once");
	});

	it("shows 'on' label", async() =>
	{
		element.toggleOn = "On label";
		await element.updateComplete;
		const label = element.querySelector(".label .on");

		expect(label).to.exist;
		expect(label.textContent).to.equal("On label");
	});
	it("shows 'off' label", async() =>
	{
		element.toggleOff = "Off label";
		await element.updateComplete;
		const label = element.querySelector(".label .off");

		expect(label).to.exist;
		expect(label.textContent).to.equal("Off label");
	});
});