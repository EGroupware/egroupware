import {assert, fixture, html, oneEvent} from '@open-wc/testing';
import {Et2Select} from "../Et2Select";
import {Et2Tag} from "../Tag/Et2Tag";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";

// Stub global egw for cssImage & widget.egw() to find
// @ts-ignore
window.egw = {
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	lang: i => i + "*",
	tooltipUnbind: () => {},
	webserverUrl: ""
};

let element : Et2Select;
let keepMe : Et2Textbox = new Et2Textbox();
const tag_name = "et2-tag";

async function before(editable = true)
{
	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2Select>(html`
        <et2-select label="I'm a select" multiple="true" editModeEnabled=${editable}>
            <option value="one">One</option>
            <option value="two">Two</option>
        </et2-select>
	`);
	// Need to call loadFromXML() explicitly to read the options
	element.loadFromXML(element);
	element.value = "one";

	return element;
}

describe("Editable tag", () =>
{
	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Select);
		assert.notInstanceOf(element, Et2Tag);
	});

	it("Tag editable matches editModeEnabled", async() =>
	{
		let tag = element.select.combobox.querySelectorAll(tag_name);
		assert.isAbove(tag.length, 0, "No tags found");
		assert.isTrue(tag[0].editable, "Select is editable but tag is not");

		// Change it to false & force immediate update
		element.editModeEnabled = false;
		element.requestUpdate("editModeEnabled", true);
		await element.updateComplete;

		tag = element.select.combobox.querySelectorAll(tag_name);
		tag.forEach(async t => await t.updateComplete);
		assert.isAbove(tag.length, 0, "No tags found");
		assert.isFalse(tag[0].editable, "Tag was editable but should not be");
	});

	it("Has edit button when editable ", async() =>
	{
		let tag = element.select.combobox.querySelectorAll(tag_name);
		await tag.updateComplete;
		assert.isAbove(tag.length, 0, "No tags found");
		assert.exists(tag[0].shadowRoot.querySelector("et2-button-icon[label='edit*']"), "No edit button");
	});
	it("Shows input when edit button is clicked", async() =>
	{
		let tag = element.select.combobox.querySelectorAll(tag_name)[0];
		await tag.updateComplete;

		let edit_button = tag.shadowRoot.querySelector("et2-button-icon");
		edit_button.click();

		await tag.updateComplete;
		assert.exists(tag.shadowRoot.querySelector("et2-textbox"), "No input to edit");
	});
	it("Changes value when edited", async() =>
	{
		let tag = <Et2Tag>element.select.combobox.querySelectorAll(tag_name)[0];
		tag.isEditing = true;
		tag.requestUpdate();
		await tag.updateComplete;

		const listener = oneEvent(tag, "change");
		let textbox = tag.shadowRoot.querySelector('et2-textbox');
		textbox.value = "changed";
		tag.stopEdit();

		await listener;

		// Value changes
		assert.equal(tag.value, "changed");

		// Haven't turned on allow free entries, so no change here
		assert.equal(element.value, "one", "Tag change caused a value change in parent select, but allowFreeEntries was off");

		// Shown as invalid
		assert.equal(tag.variant, "danger");

		// Turn it on, check again
		element.allowFreeEntries = true;

		// Re-set to original value
		tag.value = "one"

		// Change again, this time select should change value too
		tag.isEditing = true;
		tag.requestUpdate();
		await tag.updateComplete;
		const listener2 = oneEvent(tag, "change");
		textbox = tag.shadowRoot.querySelector('et2-textbox');
		textbox.value = "change select too";
		tag.stopEdit();
		await listener2;
		assert.equal(tag.value, "change select too");

		// Have turned on allow free entries, so it should change here
		assert.equal(element.value, "change select too", "Tag change did not cause value change in parent select (allowFreeEntries was on)");

	});

	it("Does not have edit button when readonly", async() =>
	{
		element.readonly = true;
		await element.updateComplete;

		let tag = element.select.combobox.querySelectorAll(tag_name);
		assert.isAbove(tag.length, 0, "No tags found");

		let wait = [];
		tag.forEach((t : Et2Tag) => wait.push(t.updateComplete))
		await Promise.all(wait);

		assert.isNull(tag[0].shadowRoot.querySelector("et2-button-icon[label='edit*']"), "Unexpected edit button");
	});
});
describe("Select is not editable", () =>
{

	beforeEach(() => before(false));

	it("Does not have edit button when not editable", async() =>
	{
		let tag = element.select.combobox.querySelectorAll(tag_name);
		assert.isAbove(tag.length, 0, "No tags found");

		assert.isNull(tag[0].shadowRoot.querySelector("et2-button-icon[label='edit*']"), "Unexpected edit button");
	});

});