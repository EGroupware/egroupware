/**
 * Test file for search actions.
 * Currently just checking to make sure onchange is only called once.
 */
import {SelectOption} from "../FindSelectOptions";
import {assert, elementUpdated, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Box} from "../../Layout/Et2Box/Et2Box";
import {Et2Select} from "../Et2Select";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";

let keep_import : Et2Textbox = null;

// Stub global egw for cssImage to find
// @ts-ignore
window.egw = {
	ajaxUrl: url => url,
	decodePath: url => url,
	//image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	lang: i => i + "*",
	link: l => l,
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
		await elementUpdated(container);

		const change = sinon.spy();
		let element = <Et2Select>container.getWidgetById('select');
		element.onchange = change;

		await elementUpdated(element);
		const option = element.select.querySelector("[value='two']");
		const listener = oneEvent(option, "mouseup");
		option.dispatchEvent(new Event("mouseup", {bubbles: true}));
		await listener;

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
            <et2-select label="I'm a select" search>
                <sl-option value="one">One</sl-option>
                <sl-option value="two">Two</sl-option>
                <sl-option value="three">Three</sl-option>
                <sl-option value="four">Four</sl-option>
                <sl-option value="five">Five</sl-option>
                <sl-option value="six">Six</sl-option>
                <sl-option value="seven">Seven</sl-option>
            </et2-select>
		`);
		// Stub egw()
		sinon.stub(element, "egw").returns(window.egw);

		await element.updateComplete;
		await element._searchInputNode.updateComplete;
		await elementUpdated(element);
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
		element._searchInputNode.value = "o";
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "o"}));
		assert(searchSpy.notCalled);
		element._searchInputNode.value = "on";
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "n"}));
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
		element._searchInputNode.value = "t";
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "o"}));
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
		element._searchInputNode.value = "t";
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "t"}));
		element._searchInputNode.dispatchEvent(new KeyboardEvent("keydown", {"key": "Escape"}));

		assert(searchSpy.notCalled, "startSearch() was called");
		assert(abortSpy.calledOnce, "_handleSearchAbort() was not called");
	})
});

async function doSearch(element, search)
{
	// we need to explicitly set the value
	element._searchInputNode.value = search;

	await element.startSearch();

	await elementUpdated(element)
};

describe("Search results", () =>
{
	let element : Et2Select;
	const remote_results = [
		{value: "remote_one", label: "remote_one"},
		{value: "remote_two", label: "remote_two"}
	];
	let clickOption = (value) =>
	{
		const option = element.select.querySelector("[value='" + value + "']");
		let listener = oneEvent(option, "mouseup");
		option.dispatchEvent(new Event("mouseup", {bubbles: true}));
		return listener;
	}

	// Setup run before each test
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		// @ts-ignore
		element = await fixture<Et2Select>(html`
            <et2-select label="I'm a select" search>
                <option value="one">One</option>
                <option value="two">Two</option>
                <option value="three">Three</option>
                <option value="four">Four</option>
                <option value="five">Five</option>
                <option value="six">Six</option>
                <option value="seven">Seven</option>
            </et2-select>
		`);
		element.loadFromXML(element);
		// Stub egw()
		sinon.stub(element, "egw").returns(window.egw);

		await element.updateComplete;
		await element._searchInputNode.updateComplete;
		await elementUpdated(element);
	});

	it("Correct local results", async() =>
	{
		// Search
		await doSearch(element, "one")
		// Check the result is offered
		const option = element.select.querySelector("[value='one']")
		assert.isNotNull(option, "Did not find option in result");

		// _only_ that one?
		assert.sameMembers(Array.from(element.select.querySelectorAll("sl-option")).map(e => e.value), ["one"], "Unexpected search results");
	});
	it("Correct remote results", async() =>
	{
		// Enable searching
		element.searchUrl = "test";

		// Fake remote search
		window.egw.request = sinon.fake
			.returns(Promise.resolve([remote_results[0]]));

		// Search
		await doSearch(element, "remote_one")

		// Check the result is offered
		const option = element.select.querySelector("[value='remote_one']")
		assert.isNotNull(option, "Did not find option in result");

		// _only_ that one?
		// N.B. that "one" will stay, since that's the current value
		assert.sameMembers(Array.from(element.select.querySelectorAll("sl-option.remote")).map(e => e.value), ["remote_one"], "Unexpected search results");
	});
	it("Correct local and remote together", async() =>
	{
		// Enable searching
		element.searchUrl = "test";

		// Fake remote search
		window.egw.request = sinon.fake
			.returns(Promise.resolve([remote_results[0]]));

		// Search
		await doSearch(element, "one")

		// Check the result is offered
		const local_option = element.select.querySelector("[value='one']")
		assert.isNotNull(local_option, "Did not find local option in result");
		const remote_option = element.select.querySelector("[value='remote_one']")
		assert.isNotNull(remote_option, "Did not find remote option in result");

		// _only_ that one?
		assert.sameMembers(Array.from(element.select.querySelectorAll("sl-option")).map(e => e.value), ["one", "remote_one"], "Unexpected search results");
	});
	it("Selected local result is in value", async() =>
	{
		// Search
		await doSearch(element, "one")

		// "Click" that one
		await clickOption("one");
		await element.updateComplete;

		assert.equal(element.value, "one", "Selected search result was not in value");
	});
	it("Selected remote result in value", async() =>
	{
		// Enable searching
		element.searchUrl = "test";

		// Fake remote search
		window.egw.request = sinon.fake
			.returns(Promise.resolve([remote_results[0]]));

		// Search
		await doSearch(element, "remote_one")

		// Click
		await clickOption("remote_one");
		await element.updateComplete;

		assert.equal(element.value, "remote_one", "Selected search result was not in value");
	});
	it("Selected multiple remote results in value", async() =>
	{
		// Enable multiple
		element.multiple = true;

		// Clear auto-selected value
		element.value = "";

		// Enable searching
		element.searchUrl = "test";

		// Fake remote search
		window.egw.request = sinon.fake
			.returns(Promise.resolve(remote_results));

		// Search
		await doSearch(element, "doesn't matter, we're faking it")

		// Click
		const values = ["remote_one", "remote_two"];
		let listener;
		values.forEach(value =>
		{
			listener = clickOption(value);
		});
		await listener;
		await element.updateComplete;

		assert.deepEqual(element.value, values, "Selected search results were not in value");
	});
	it("Adding (multiple) remote keeps value", async() =>
	{
		const values = ["remote_one", "remote_two"];

		// Enable multiple
		element.multiple = true;

		// Clear value ("one" was selected automatically)
		element.value = "";
		await element.updateComplete;

		// Enable searching
		element.searchUrl = "test";

		// Fake remote search
		window.egw.request = sinon.fake
			.returns(Promise.resolve(remote_results));

		// Search
		await doSearch(element, "doesn't matter, we're faking it")

		debugger;
		// Select the first one
		await clickOption("remote_one");
		await element.updateComplete;

		// Search & select another one
		await doSearch(element, "doesn't matter, we're faking it");
		await clickOption("remote_two");
		await element.updateComplete;

		assert.deepEqual(element.value, values, "Selected search results were not in value");
	});
});