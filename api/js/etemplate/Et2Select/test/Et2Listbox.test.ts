/**
 * Test file for Etemplate webComponent Et2Listbox
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Listbox} from "../Et2Listbox";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";
import {sendKeys, sendMouse} from "@web/test-runner-commands";
// The rendered <sl-menu-item>s never upgrade without this (importing SlMenuItem as a type
// elsewhere doesn't register the custom element - same gotcha as sl-switch/sl-select)
import "@shoelace-style/shoelace/dist/components/menu-item/menu-item.js";
// The <sl-menu> the items sit in gives them their role=menu parent, and manages their tab order
import "@shoelace-style/shoelace/dist/components/menu/menu.js";

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

describe("Listbox required styling", () =>
{
	const YELLOW = "rgb(255, 255, 208)";
	const menuBackground = () => getComputedStyle(element.shadowRoot.querySelector("sl-menu")).backgroundColor;

	beforeEach(before);

	it("is yellow when required and nothing is selected", async() =>
	{
		element.multiple = true;
		element.required = true;
		element.value = [];
		await element.updateComplete;

		assert.equal(menuBackground(), YELLOW);
	});

	it("is not yellow once an option is selected", async() =>
	{
		element.multiple = true;
		element.required = true;
		element.value = [];
		await element.updateComplete;
		element.value = ["one"];
		await element.updateComplete;

		assert.notEqual(menuBackground(), YELLOW);
	});

	it("stops being yellow when the user ticks an option", async() =>
	{
		element.multiple = true;
		element.required = true;
		element.value = [];
		await element.updateComplete;

		const item = <any>element.shadowRoot.querySelector("sl-menu-item");
		item.checked = true;
		item.dispatchEvent(new CustomEvent("sl-select", {bubbles: true, composed: true, detail: {item}}));
		await element.updateComplete;
		await element.updateComplete;

		assert.notEqual(menuBackground(), YELLOW);
	});

	it("is not yellow when it is not required", async() =>
	{
		element.multiple = true;
		element.value = [];
		await element.updateComplete;

		assert.notEqual(menuBackground(), YELLOW);
	});
});

describe("Listbox item highlight", () =>
{
	const base = (item : Element) => getComputedStyle(item.shadowRoot.querySelector("[part~=base]")).backgroundColor;

	beforeEach(async() =>
	{
		await before();
		// The Shoelace theme is not loaded in tests
		element.style.setProperty("--sl-color-primary-600", "rgb(10, 20, 30)");
	});

	it("is gray, not primary, under the pointer", async() =>
	{
		const item = element.shadowRoot.querySelectorAll("sl-menu-item")[1];
		const box = item.getBoundingClientRect();
		await sendMouse({type: "move", position: [Math.round(box.x + box.width / 2), Math.round(box.y + box.height / 2)]});
		await new Promise(resolve => setTimeout(resolve, 300));

		assert.equal(base(item), "rgb(244, 244, 245)");
	});

	it("is not primary once the pointer has left the item it focused", async() =>
	{
		element.label = "A listbox";
		await element.updateComplete;
		const item = <HTMLElement>element.shadowRoot.querySelectorAll("sl-menu-item")[1];
		const box = item.getBoundingClientRect();
		await sendMouse({type: "move", position: [Math.round(box.x + box.width / 2), Math.round(box.y + box.height / 2)]});
		item.focus();
		const label = element.shadowRoot.querySelector("label").getBoundingClientRect();
		await sendMouse({type: "move", position: [Math.round(label.x + 2), Math.round(label.y + 2)]});
		await new Promise(resolve => setTimeout(resolve, 300));

		assert.notEqual(base(item), "rgb(10, 20, 30)");
	});

	it("is primary on the item the keyboard is on", async() =>
	{
		const item = <HTMLElement>element.shadowRoot.querySelectorAll("sl-menu-item")[1];
		await sendMouse({type: "move", position: [700, 500]});
		item.focus();
		item.dispatchEvent(new KeyboardEvent("keydown", {key: "ArrowDown", bubbles: true, composed: true}));
		await new Promise(resolve => setTimeout(resolve, 300));

		const focused = element.shadowRoot.activeElement;
		assert.equal(focused?.tagName, "SL-MENU-ITEM", "the keyboard should be on an item");
		assert.equal(base(focused), "rgb(10, 20, 30)");
	});
});

describe("Listbox label and help text", () =>
{
	beforeEach(before);

	it("names and describes its menu from them", async() =>
	{
		element.label = "A listbox";
		element.helpText = "Pick some";
		await element.updateComplete;

		const menu = element.shadowRoot.querySelector("sl-menu");
		const text = (attribute : string) => element.shadowRoot.getElementById(menu.getAttribute(attribute)).textContent.trim();

		assert.include(text("aria-labelledby"), "A listbox");
		assert.include(text("aria-describedby"), "Pick some");
	});

	it("leaves the menu unnamed when there is neither", async() =>
	{
		element.label = "";
		element.helpText = "";
		await element.updateComplete;

		const menu = element.shadowRoot.querySelector("sl-menu");
		assert.isFalse(menu.hasAttribute("aria-labelledby"));
		assert.isFalse(menu.hasAttribute("aria-describedby"));
	});
});

// value comes from which <sl-menu-item>s are actually checked once rendered, not directly from
// the property - select_options above provides a matching option for the round trip to work
// against. No value checked gives undefined (value.pop() on an empty array), not "".
inputBasicTests(before, "one", "sl-menu-item", {
	emptyValue: undefined,
	// conformance: the controls are <sl-menu-item>s (role=menuitemcheckbox), which cannot be required.  The menu
	// itself carries the description (tested above), and there is no native control to look it up on
	skip: ["required-aria", "help-text-aria"],
	checkEmptyDisplay: (element : Et2Listbox) =>
		assert.isFalse(
			Array.from(element.shadowRoot.querySelectorAll("sl-menu-item")).some((i : any) => i.checked),
			"An option is checked when there is no value"
		)
});
