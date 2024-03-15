import {assert, elementUpdated, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from 'sinon';
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import {Et2Email} from "../Et2Email";
import {Et2EmailTag} from "../../Et2Select/Tag/Et2EmailTag";
import {waitForEvent} from "../../Et2Widget/event";

/**
 * Test file for Etemplate webComponent Select
 *
 * In here we test just the simple, basic widget stuff.
 */
// Stub global egw for cssImage to find
// @ts-ignore
let uid = 0;
const testSuggestions = [
	{value: "suggestion.1@example.com", label: "Suggestion 1"},
	{value: "suggestion.2@example.com", label: "Suggestion 2"}
];
window.egw = {
	ajaxUrl: () => "",
	app: () => "addressbook",
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	jsonq: () => Promise.resolve({}),
	lang: i => i + "*",
	link: i => i,
	preference: i => "",
	request: () => Promise.resolve(testSuggestions),
	tooltipUnbind: () => {},
	webserverUrl: "",
	uid: () => {return "" + (uid++);}
};

let element : Et2Email;

async function before()
{
	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2Select>(html`
        <et2-email label="I'm an email">
        </et2-email>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Email widget basics", () =>
{
	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Email);
	});

	it('has a label', async() =>
	{
		element.set_label("Label set");
		await elementUpdated(element);

		assert.equal(element.querySelector("[slot='label']").textContent, "Label set");
	});

	it("textbox gets focus when widget is focused", async() =>
	{
		element.focus();
		assert.equal(element.shadowRoot.activeElement, element._search, "Search textbox did not get focus when widget got focus");
	});

	it("closes when losing focus", async() =>
	{
		// WIP
		const blurSpy = sinon.spy();
		element.addEventListener('sl-hide', blurSpy);
		const showPromise = new Promise(resolve =>
		{
			element.addEventListener("sl-after-show", resolve);
		});
		const hidePromise = new Promise(resolve =>
		{
			element.addEventListener("sl-hide", resolve);
		});

		await elementUpdated(element);
		element.show();

		await showPromise;
		await elementUpdated(element);

		element.blur();
		await elementUpdated(element);

		await hidePromise;

		sinon.assert.calledOnce(blurSpy);

		// Check that it actually closed dropdown
		assert.isFalse(element.hasAttribute("open"));
	});

	it("blurring widget accepts current text", async() =>
	{
		const value = "valid@example.com";
		element.focus();
		element._search.value = value;
		element.blur();
		await elementUpdated(element);

		assert.sameMembers(element.value, [value], "Valid email was not accepted on blur");
	});
});
describe("Properties", async() =>
{
	// Setup run before each test
	beforeEach(before);

	it("Allows placeholder", async() =>
	{

		const value = "{{placeholder}}";
		element.allowPlaceholder = false;
		await elementUpdated(element);


		element.addAddress(value);

		await elementUpdated(element);
		assert.sameMembers(element.value, [], "Placeholder was accepted when not allowed");

		element.allowPlaceholder = true;
		await elementUpdated(element);

		element.addAddress(value);

		await elementUpdated(element);
		assert.sameMembers(element.value, [value], "Placeholder was not accepted when allowed");
	});
});

describe("Suggestions", () =>
{	// Setup run before each test
	beforeEach(before);

	it("clicking accepts suggestion", async() =>
	{
		await elementUpdated(element);
		// Start the search
		element.focus();
		element.startSearch();
		await waitForEvent(element, "sl-after-show");

		// Click the first one
		element._listbox.querySelector('sl-option').dispatchEvent(new MouseEvent("mouseup", {bubbles: true}))
		await elementUpdated(element);
		// Check the value
		assert.sameMembers(element.value, [testSuggestions[0].value]);
	});
	
	it("tab accepts top suggestion", async() =>
	{
		element.focus();
		element.startSearch();
		await waitForEvent(element, "sl-after-show");

		// No match between what they typed and the suggestion - no
		element._search.dispatchEvent(new KeyboardEvent("keydown", {key: "Tab"}));
		await elementUpdated(element);
		assert.sameMembers(element.value, []);

		// Partial match with current suggestion, take it
		element.focus();
		element._search.value = "sugg";
		element._search.dispatchEvent(new KeyboardEvent("keydown", {key: "Tab"}));
		await elementUpdated(element);
		assert.sameMembers(element.value, [testSuggestions[0].value]);
	});
});

describe("Tags", () =>
{
	beforeEach(async() =>
	{
		// Create an element to test with, and wait until it's ready
		// @ts-ignore
		element = await fixture<Et2Email>(html`
            <et2-email label="I'm a select" value="one@example.com, two@example.com">
            </et2-email>
		`);
		element.loadFromXML(element);

		// Stub egw()
		sinon.stub(element, "egw").returns(window.egw);

		return element;
	});

	it("Can remove tags", async() =>
	{
		assert.equal(element._tags.length, 2, "Did not find tags");

		// Set up listener
		const listener = oneEvent(element, "change");

		// Click to remove first tag
		let removeButton = element._tags[0].shadowRoot.querySelector("[part='remove-button']");
		assert.exists(removeButton, "Could not find tag remove button");
		removeButton.dispatchEvent(new Event("click"));

		await listener;

		// Wait for widget to update
		await element.updateComplete;
		let tag_updates = []
		element._tags.forEach((t : Et2EmailTag) => tag_updates.push(t.updateComplete));
		await Promise.all(tag_updates);

		// Check
		assert.sameMembers(element.value, ["two@example.com"], "Removing tag did not remove value");
		assert.equal(element._tags.length, 1, "Removed tag is still there");
	});
});

inputBasicTests(async() =>
{
	const element = await before();
	element.noLang = true;
	return element
}, "fake@example.com", "input");