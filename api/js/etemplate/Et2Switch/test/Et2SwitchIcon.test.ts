/**
 * Test file for Etemplate webComponent Et2Switch
 */
import {assert, expect, fixture, html} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2SwitchIcon} from "../Et2SwitchIcon";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
// The internal <sl-switch> never upgrades (no shadow root) without this - importing SlSwitch as a
// type elsewhere doesn't register the custom element
import "@shoelace-style/shoelace/dist/components/switch/switch.js";

// Reference to component under test
let element : Et2SwitchIcon;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2SwitchIcon>(html`
        <et2-switch-icon label="I'm a switch"></et2-switch-icon>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns({
		tooltipUnbind: () => {},
		lang: i => i,
		// Image always give check mark.  Use data URL to avoid having to serve an actual image
		image: i => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo="
	});
	return element;
}

describe("Switch icon widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2SwitchIcon);
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

	it("shows 'on' icon", async() =>
	{
		element.onIcon = "plus";
		await element.updateComplete;
		const label = element.shadowRoot.querySelector(".label .on");
		expect(label).to.be.displayed;
	});
	it("shows 'off' icon", async() =>
	{
		element.offIcon = "minus";
		await element.updateComplete;
		const label = element.shadowRoot.querySelector(".label .off");

		expect(label).to.be.displayed;
	});

	/**
	 * Regression test for a real bug found live 2026-09-11 (mail compose's "save as infolog on
	 * send" toggle): clicking the internal <sl-switch> flips ITS OWN checked state, but that never
	 * flowed back to this.checked/value - so get_value() kept returning the pre-click value forever,
	 * even though the switch visually looked toggled. Only affects Et2SwitchIcon (and its
	 * Et2ButtonToggle subclass), which WRAPS an internal <sl-switch> rather than extending it
	 * directly like Et2Switch does.
	 */
	it("a real click on the internal switch updates get_value()", async() =>
	{
		element.value = false;
		await element.updateComplete;
		assert.isFalse(element.get_value());

		const innerSwitch = element.shadowRoot.querySelector("sl-switch");
		innerSwitch.click();
		await element.updateComplete;

		assert.isTrue(element.get_value(), "get_value() should reflect the click, not the stale pre-click value");
	});
});

// Same boolean-value/own-text-label shape as Et2Switch - see its test file for why.
inputBasicTests(before, true, "input", {
	emptyValue: false,
	checkEmptyDisplay: () => {},
	skip: ["label"]
});