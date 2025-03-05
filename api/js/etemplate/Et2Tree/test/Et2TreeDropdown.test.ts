import {Et2TreeDropdown} from "../Et2TreeDropdown";
import {assert, fixture} from "@open-wc/testing";
import {html} from "lit";
import {Et2Tree} from "../Et2Tree";

window.egw = {
	ajaxUrl: (url) => url,
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
};
describe("Et2TreeDropdown", () =>
{
	let element : Et2TreeDropdown;

	beforeEach(async() =>
	{
		element = await fixture<Et2TreeDropdown>(html`
            <et2-tree-dropdown></et2-tree-dropdown>`);
	});

	// Make sure it works
	it("is defined", async() =>
	{
		assert.instanceOf(element, Et2TreeDropdown);

		// Tree is also used, so it needs to work too
		const tree = await fixture<Et2Tree>(html`
            <et2-tree></et2-tree>`);
		assert.instanceOf(tree, Et2Tree);
	});

	it("renders correctly", () =>
	{
		assert(element, "Component should be rendered");
	});

	it("has correct default properties", () =>
	{
		assert.strictEqual(element.open, false, "Default open property should be false");
		assert.strictEqual(element.disabled, false, "Default disabled property should be false");
	});

	it("closes and stays closed when expand icon is clicked", async() =>
	{
		const expandIcon = element.shadowRoot.querySelector(".tree-dropdown__expand-icon");
		assert(expandIcon, "Expand icon should be present");
		const popup = element.shadowRoot.querySelector("sl-popup");
		assert(popup, "Popup should be present");

		// Click to expand
		expandIcon.click();
		await element.updateComplete;
		await new Promise(resolve => setTimeout(resolve, 0));
		assert(element.hasAttribute("open"), "Dropdown should be open after clicking expand");
		assert(popup.hasAttribute("active"), "Popup should be active after clicking expand");
		assert(element.open, "Open property should be true after clicking expand");

		// Click again to collapse
		expandIcon.click();
		await element.updateComplete;
		await new Promise(resolve => setTimeout(resolve, 0));
		assert(!element.hasAttribute("open"), "Dropdown should be closed after clicking expand again");
		assert(!element.hasAttribute("active"), "Popup should not be active after clicking expand again");
		assert(!element.open, "Open property should be false after clicking expand again");

		// Wait a tick to make sure it stays closed
		await new Promise(resolve => setTimeout(resolve, 10));
		await element.updateComplete;
		assert(!element.hasAttribute("open"), "Dropdown should stay closed after closed");
		assert(!element.hasAttribute("active"), "Popup should not be active after closed");

	});


	it("reflects disabled changes correctly", async() =>
	{
		element.setAttribute("disabled", "");
		await element.updateComplete;
		assert(element.hasAttribute("disabled"), "Component should reflect disabled attribute");
	});

	it("closes when clicking outside", async() =>
	{
		element.setAttribute("open", "");
		await element.updateComplete;
		document.body.click();
		await element.updateComplete;
		assert(!element.hasAttribute("open"), "Dropdown should close when clicking outside");
	});
});