/**
 * Test file for Etemplate webComponent Et2DropdownButton
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2DropdownButton} from "../Et2DropdownButton";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";
// The rendered <sl-menu-item>/<sl-button>/<sl-dropdown>/<sl-button-group> never upgrade without
// these (same gotcha as sl-switch/sl-select/sl-menu-item elsewhere in this rollout)
import "@shoelace-style/shoelace/dist/components/menu-item/menu-item.js";
import "@shoelace-style/shoelace/dist/components/menu/menu.js";
import "@shoelace-style/shoelace/dist/components/button/button.js";
import "@shoelace-style/shoelace/dist/components/button-group/button-group.js";
import "@shoelace-style/shoelace/dist/components/dropdown/dropdown.js";

window.egwIsMobile = () => false;
// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {},
	preference: () => "",
	getAppName: () => "test"
};
// Reference to component under test
let element : Et2DropdownButton;

async function before()
{
	element = await fixture<Et2DropdownButton>(html`
        <et2-dropdown-button></et2-dropdown-button>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	element.noLang = true;
	element.select_options = [{value: "one", label: "One"}, {value: "two", label: "Two"}];
	await elementUpdated(element);

	return element;
}

describe("Dropdown button widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2DropdownButton);
	});

	it("renders nothing when readonly", async() =>
	{
		element.readonly = true;
		await elementUpdated(element);

		assert.notExists(element.shadowRoot.querySelector("sl-button-group"));
	});
});

// value is a plain string (this._value), not derived from rendered DOM state - a direct property,
// simple round trip. No label/help-text rendering exists at all (it's a button).
inputBasicTests(before, "one", "sl-menu-item", {
	skip: ["label", "help-text"],
	checkEmptyDisplay: () => {}
});
