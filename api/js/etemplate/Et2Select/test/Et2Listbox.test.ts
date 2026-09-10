/**
 * Test file for Etemplate webComponent Et2Listbox
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Listbox} from "../Et2Listbox";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";
// The rendered <sl-menu-item>s never upgrade without this (importing SlMenuItem as a type
// elsewhere doesn't register the custom element - same gotcha as sl-switch/sl-select)
import "@shoelace-style/shoelace/dist/components/menu-item/menu-item.js";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2Listbox;

async function before()
{
	element = await fixture<Et2Listbox>(html`
        <et2-listbox></et2-listbox>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	element.noLang = true;
	element.select_options = [{value: "one", label: "One"}, {value: "two", label: "Two"}];
	await elementUpdated(element);

	return element;
}

describe("Listbox widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Listbox);
	});

	it("checks the matching option when a value is set", async() =>
	{
		element.set_value("one");
		await elementUpdated(element);

		const items = element.shadowRoot.querySelectorAll("sl-menu-item");
		assert.isTrue((items[0] as any).checked, "\"one\" should be checked");
		assert.isFalse((items[1] as any).checked, "\"two\" should not be checked");
	});
});

// value comes from which <sl-menu-item>s are actually checked once rendered, not directly from
// the property - select_options above provides a matching option for the round trip to work
// against. No value checked gives undefined (value.pop() on an empty array), not "". No
// label/help-text rendering exists at all.
inputBasicTests(before, "one", "sl-menu-item", {
	emptyValue: undefined,
	skip: ["label", "help-text"],
	checkEmptyDisplay: (element : Et2Listbox) =>
		assert.isFalse(
			Array.from(element.shadowRoot.querySelectorAll("sl-menu-item")).some((i : any) => i.checked),
			"An option is checked when there is no value"
		)
});
