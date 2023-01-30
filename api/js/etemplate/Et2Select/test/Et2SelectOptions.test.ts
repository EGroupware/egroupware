import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Box} from "../../Layout/Et2Box/Et2Box";
import {Et2Select, SelectOption} from "../Et2Select";
import * as sinon from "sinon";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

let parser = new window.DOMParser();

// Use this to load the select as a child
let container : Et2Box;

// Element under test
let element : Et2Select;

// Stub global egw
// @ts-ignore
window.egw = {
	tooltipUnbind: () => {},
	lang: i => i + "*",
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	webserverUrl: ""
};

let options = [
	<SelectOption>{value: "1", label: "Option 1"},
	<SelectOption>{value: "2", label: "Option 2"}
];
describe("Select widget", () =>
{
	beforeEach(async() =>
	{
		// This stuff because otherwise Et2Select isn't actually loaded when testing
		// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
		element = await fixture<Et2Select>(html`
            <et2-select></et2-select>
		`);
		// Stub egw()
		sinon.stub(element, "egw").returns(window.egw);
		assert.instanceOf(element, Et2Select);
		element.remove();

		// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
		container = await fixture<Et2Box>(html`
            <et2-box/>
		`);

		assert.instanceOf(container, Et2Box);
		// Stub egw()
		sinon.stub(container, "egw").returns(window.egw);
	});

	describe("Finds options", () =>
	{
		it("static", async() =>
		{
			/** SETUP **/
				// Create an element to test with, and wait until it's ready
			let node = '<et2-select id="select" label="I am a select"><option value="option">option label</option></et2-select>';

			container.loadFromXML(parser.parseFromString(node, "text/xml"));

			// wait for asychronous changes to the DOM
			// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
			await elementUpdated(container);
			element = <Et2Select>container.getWidgetById('select');
			await element.updateComplete;

			/** TESTING **/
			assert.isNotNull(element.querySelector("[value='option']"), "Missing static option");
		});

		it("directly in sel_options", async() =>
		{
			/** SETUP **/
				// Create an element to test with, and wait until it's ready
			container.setArrayMgr("sel_options", new et2_arrayMgr({
				select: options
			}));
			let node = '<et2-select id="select"></et2-select>';
			container.loadFromXML(parser.parseFromString(node, "text/xml"));

			// wait for asychronous changes to the DOM
			// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
			await elementUpdated(container);
			element = <Et2Select>container.getWidgetById('select');
			await element.updateComplete;

			/** TESTING **/
			assert.equal(element.querySelectorAll("sl-menu-item").length, 2);
		});

		it("merges static options with sel_options", async() =>
		{
			/** SETUP **/

				// Create an element to test with, and wait until it's ready
			let node = '<et2-select id="select" label="I am a select"><option value="option">option label</option></et2-select>';
			container.setArrayMgr("sel_options", new et2_arrayMgr({
				select: options
			}));
			container.loadFromXML(parser.parseFromString(node, "text/xml"));

			// wait for asychronous changes to the DOM
			// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
			await elementUpdated(container);
			element = <Et2Select>container.getWidgetById('select');
			await element.updateComplete;

			/** TESTING **/

				// @ts-ignore o.value isn't known by TypeScript, but it's there
			let option_keys = Object.values(element.querySelectorAll("sl-menu-item")).map(o => o.value);
			assert.include(option_keys, "option", "Static option missing");
			assert.includeMembers(option_keys, ["1", "2", "option"], "Option mis-match");
			assert.equal(option_keys.length, 3);
		});
	});

	describe("Value tests", () =>
	{
		it("set_value()", async() =>
		{
			/** SETUP **/
				// Create an element to test with, and wait until it's ready
			let node = '<et2-select id="select"></et2-select>';
			let test_value = "2";
			container.setArrayMgr("sel_options", new et2_arrayMgr({
				select: options
			}));
			container.loadFromXML(parser.parseFromString(node, "text/xml"));

			// wait for asychronous changes to the DOM
			// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
			await elementUpdated(container);
			element = <Et2Select>container.getWidgetById('select');

			/** TESTING **/
			element.set_value(test_value);
			// wait for asychronous changes to the DOM
			// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
			await elementUpdated(element);

			// Now check - value is preferred over get_value()
			assert.equal(element.get_value(), test_value, "Wrong value from widget");
			assert.equal(element.value, test_value, "Wrong value from widget");
		});
	});
});