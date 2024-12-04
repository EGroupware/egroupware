import {assert, elementUpdated, expect, fixture, html, oneEvent} from '@open-wc/testing';
import {sendKeys} from "@web/test-runner-commands";
import * as sinon from 'sinon';
import {Et2Dialog} from "../Et2Dialog";

/**
 * Test file for Etemplate webComponent Et2Dialog
 *
 * In here we test just the simple, basic widget stuff.
 */
// Stub global egw for egw_action to find
const egw = {
	ajaxUrl: () => "",
	app: () => "addressbook",
	app_name: () => "addressbook",
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	jsonq: () => Promise.resolve({}),
	lang: i => i + "*",
	link: i => i,
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
}
window.egw = function() {return egw};
Object.assign(window.egw, egw);

let element : Et2Dialog;

async function before()
{
	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2Dialog>(html`
        <et2-dialog title="I'm a dialog">
        </et2-dialog>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Dialog widget basics", () =>
{
	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Dialog);
	});

	it('has a title', async() =>
	{
		element.title = "Title set";
		await elementUpdated(element);

		assert.equal(element.shadowRoot.querySelector("#title").textContent.trim(), "Title set");
	});
});
describe("Properties", async() =>
{
	// Setup run before each test
	beforeEach(before);

	it("destroyOnClose = true", async() =>
	{
		element.destroyOnClose = true;
		await element.show();
		assert.isNotNull(document.querySelector("et2-dialog"));
		await element.hide();

		assert.isNull(document.querySelector("et2-dialog"));
	});
	it("destroyOnClose = false", async() =>
	{
		element.destroyOnClose = false;
		await element.show();
		assert.isNotNull(document.querySelector("et2-dialog"));

		await element.hide();
		assert.isNotNull(document.querySelector("et2-dialog"));
	});
	it("noCloseButton", async() =>
	{
		await element.show();
		const closeButton = element.shadowRoot.querySelector("[part=close-button]");
		assert.isNotNull(closeButton);
		assert.isTrue(closeButton.checkVisibility());

		element.noCloseButton = true;
		await element.show();

		assert.isFalse(closeButton.checkVisibility());
	});
	it("hideOnEscape = true", async() =>
	{
		element.hideOnEscape = true;

		await element.show();
		const listener = oneEvent(element, "close");

		await sendKeys({down: "Escape"});
		const event = await listener;
		expect(event).to.exist;
	});
	it("hideOnEscape = false", (done) =>
	{
		element.hideOnEscape = false;

		element.show().then(async() =>
		{
			// Listen for events
			const requestCloseListener = oneEvent(element, "sl-request-close");
			const closeListener = oneEvent(element, "close");

			let event = null;

			// Press Escape
			let keysSender = await sendKeys({down: "Escape"});

			// Request close gets sent, but Et2Dialog cancels it if hideOnEscape=false
			await requestCloseListener;

			// Can't really test that an event didn't happen
			setTimeout(() =>
			{
				assert.isNull(event, "Close happened");
				done();
			}, 500)

			event = await closeListener;
			return requestCloseListener;
		});
	});
});