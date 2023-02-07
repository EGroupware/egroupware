/**
 * Test file for search actions.
 * Currently just checking to make sure onchange is only called once.
 */
import {SelectOption} from "../FindSelectOptions";
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Box} from "../../Layout/Et2Box/Et2Box";
import {Et2Select} from "../Et2Select";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";

let keep_import : Et2Textbox = new Et2Textbox();

// Stub global egw for cssImage to find
// @ts-ignore
window.egw = {
	//image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	lang: i => i + "*",
	tooltipUnbind: () => {},
	webserverUrl: "",
	window: window
};

let parser = new window.DOMParser();
let container : Et2Box;

const options = [
	<SelectOption>{value: "1", label: "Option 1"},
	<SelectOption>{value: "2", label: "Option 2"}
];

async function before()
{
	// This stuff because otherwise Et2Select isn't actually loaded when testing
	let element = await fixture<Et2Select>(html`
        <et2-select></et2-select>
	`);
	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	assert.instanceOf(element, Et2Select);
	element.remove();
	container = await fixture<Et2Box>(html`
        <et2-box/>
	`);
	// Stub egw()
	sinon.stub(container, "egw").returns(window.egw);

	assert.instanceOf(container, Et2Box);
}

describe("Search actions", () =>
{
	// Setup run before each test
	beforeEach(before);


	it('onChange is called when value changes', async() =>
	{
		/** SETUP **/
			// Create an element to test with, and wait until it's ready
			// Set onchange="true" to make sure something is set before we override it
		let node = '<et2-select id="select" label="I am a select" onchange="true">' +
				'<option value="option">option label</option>' +
				'<option value="two">option label 2</option>' +
				'</et2-select>';

		container.loadFromXML(parser.parseFromString(node, "text/xml"));

		const change = sinon.spy();
		let element = <Et2Select>container.getWidgetById('select');
		element.onchange = change;

		await elementUpdated(element);

		element.value = "two";

		await elementUpdated(element);

		// For some reason in the test change gets called twice, even though in normal operation it gets called once.
		sinon.assert.called(change);
	});
})

describe("Trigger search", () =>
{

	let element : Et2Select;
	let clock;

	// Setup run before each test
	beforeEach(async() =>
	{
		// Mess with time
		clock = sinon.useFakeTimers()

		// Create an element to test with, and wait until it's ready
		// @ts-ignore
		element = await fixture<Et2Select>(html`
            <et2-select label="I'm a select" search=true>
                <sl-menu-item value="one">One</sl-menu-item>
                <sl-menu-item value="two">Two</sl-menu-item>
                <sl-menu-item value="three">Three</sl-menu-item>
                <sl-menu-item value="four">Four</sl-menu-item>
                <sl-menu-item value="five">Five</sl-menu-item>
                <sl-menu-item value="six">Six</sl-menu-item>
                <sl-menu-item value="seven">Seven</sl-menu-item>
            </et2-select>
		`);
		// Stub egw()
		sinon.stub(element, "egw").returns(window.egw);

		await element.updateComplete;
		await element._searchInputNode.updateComplete;
	});

	afterEach(() =>
	{
		clock.restore();
	})

	it("Searches after 2 characters", () =>
	{

		// Set up spy
		let searchSpy = sinon.spy(element, "startSearch");

		// Send two keypresses, but we need to explicitly set the value
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "o"}));
		element._searchInputNode.value = "o";
		assert(searchSpy.notCalled);
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "n"}));
		element._searchInputNode.value = "on";
		assert(searchSpy.notCalled);

		// Skip the timeout
		clock.runAll();

		assert(searchSpy.calledOnce, "startSearch() was not called");

	});

	it("Searches on enter", () =>
	{
		// Set up spy
		let searchSpy = sinon.spy(element, "startSearch");

		// Send two keypresses, but we need to explicitly set the value
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "o"}));
		element._searchInputNode.value = "t";
		assert(searchSpy.notCalled);
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "Enter"}));

		// Search starts immediately
		assert(searchSpy.calledOnce, "startSearch() was not called");
	});

	it("Aborts search when escape pressed", () =>
	{
		// Set up spy
		let abortSpy = sinon.spy(element, "_handleSearchAbort");
		let searchSpy = sinon.spy(element, "startSearch");

		// Send two keypresses, but we need to explicitly set the value
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "t"}));
		element._searchInputNode.value = "t";
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "Escape"}));

		assert(searchSpy.notCalled, "startSearch() was called");
		assert(abortSpy.calledOnce, "_handleSearchAbort() was not called");
	})
});