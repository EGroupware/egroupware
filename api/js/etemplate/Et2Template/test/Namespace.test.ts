import {Et2Template} from "../Et2Template";
import {Et2Description} from "../../Et2Description/Et2Description";
import {assert, elementUpdated, fixture, html, oneEvent} from "@open-wc/testing";
import * as sinon from "sinon";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";

/**
 * Test file for Template webComponent
 *
 * In here we test just basics and simple loading to avoid as few additional dependencies as possible.
 */
// Stub global egw
// @ts-ignore
window.egw = {
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	lang: i => i + "",
	link: i => i,
	tooltipUnbind: () => { },
	webserverUrl: ""
};
let element : Et2Template;
let keepImport : Et2Description = new Et2Description();

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

function fakedTemplate(template_text)
{
	const parser = new window.DOMParser();
	return parser.parseFromString(template_text, "text/xml").children[0];
}

const SIMPLE = `<overlay><template id="simple">
<et2-description id="static" value="Static value"></et2-description>
<et2-description id="test"></et2-description>
</template></overlay>`;

// Pre-fill cache
Et2Template.templateCache["simple"] = <Element>fakedTemplate(SIMPLE).childNodes.item(0);

describe("Namespaces", () =>
{
	// Setup run before each test
	beforeEach(before);
	it("Does not create a namespace with no 'content'", async() =>
	{
		const listener = oneEvent(element, "load");
		element.setArrayMgr("content", new et2_arrayMgr({
			test: "Test"
		}));

		// Set the template to start load
		element.template = "simple";

		// Wait for load & load event
		await element.updateComplete;
		const loadEvent = await listener;

		const staticElement = element.querySelector(":scope > *:first-of-type");
		assert.isNotNull(staticElement, "Did not find test element");
		assert.equal(staticElement.getAttribute("id"), "static", "Static child ID was wrong");
		assert.equal(staticElement.innerText, "Static value");

		const dynamicElement = element.querySelector(":scope > *:last-of-type");
		assert.isNotNull(dynamicElement, "Did not find test element");
		assert.equal(dynamicElement.getAttribute("id"), "test", "Dynamic child ID was wrong");
		assert.equal(dynamicElement.innerText, "Test");
	});

	/**
	 * Test creating a namespace on the template.
	 * This means we expect all child elements to include the template ID as part of their ID,
	 * and they should be given the correct part of the content array.
	 */
	it("Creates a namespace when content is set", async() =>
	{
		const listener = oneEvent(element, "load");
		element.setArrayMgr("content", new et2_arrayMgr({
			test: "Top level",
			sub: {
				test: "Namespaced"
			}
		}));

		element.content = "sub";
		// Set the template to start load
		element.template = "simple";

		// Wait for load & load event
		await element.updateComplete;
		const loadEvent = await listener;

		const staticElement = element.querySelector(":scope > *:first-of-type");
		assert.isNotNull(staticElement, "Did not find test element");
		assert.equal(staticElement.getAttribute("id"), "sub_static", "Child ID was not namespaced");
		assert.equal(staticElement.innerText, "Static value");

		const dynamicElement = element.querySelector(":scope > *:last-of-type");
		assert.isNotNull(dynamicElement, "Did not find test element");
		assert.notEqual(dynamicElement.getAttribute("id"), "Top level");
		assert.equal(dynamicElement.innerText, "Namespaced");
	});

	it("Can replace data when loading", async() =>
	{
		const listener = oneEvent(element, "load");
		element.setArrayMgr("content", new et2_arrayMgr({
			test: "Test"
		}));

		// Set the template to start load
		element.template = "simple";

		// Wait for load & load event
		await element.updateComplete;
		const loadEvent = await listener;

		const staticElement = element.querySelector(":scope > *:first-of-type");
		assert.isNotNull(staticElement, "Did not find test element");
		assert.equal(staticElement.innerText, "Static value");

		let dynamicElement = element.querySelector(":scope > *:last-of-type");
		assert.isNotNull(dynamicElement, "Did not find test element");
		assert.equal(dynamicElement.innerText, "Test");

		// Now load new data
		await element.load({test: "Success"});

		// Old element was destroyed, get the new one
		dynamicElement = element.querySelector(":scope > *:last-of-type");
		assert.equal(dynamicElement.innerText, "Success", "Element did not get new value when template was loaded with new data");
	});
});