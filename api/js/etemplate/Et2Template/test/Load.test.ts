import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Template} from "../Et2Template";

/**
 * Test file for Template webComponent
 *
 * In here we test just the simple, basic widget stuff.
 */
// Stub global egw
// @ts-ignore
window.egw = {
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	lang: i => i + "*",
	tooltipUnbind: () => { },
	webserverUrl: ""
};
let element;

async function before()
{

	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture(html`
        <et2-template>
        </et2-template>
	`);
	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);
	return element;
}

describe("Template widget basics", () =>
{
	// Setup run before each test
	beforeEach(before);
	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Template);
	});
	it("starts empty", () =>
	{
		assert.notExists(element.querySelectorAll("*"), "Not-loaded template has content.  It should be empty.");
	});
	it("shows loader", () =>
	{
		assert.exists(element.shadowRoot.querySelector(".template--loading"), "Could not find load indicator");
	});
});
describe("Loading", () =>
{
	it("loads from file", async() =>
	{
		// Stub the url to point to the fixture
		sinon.stub(element, "getUrl").returns("./fixtures/simple.xml");
		element.template = "simple.empty";
		await element.updateComplete;
		assert.isTrue(element.__isLoading);
	})
	it("loads from cache", () =>
	{
	});
});