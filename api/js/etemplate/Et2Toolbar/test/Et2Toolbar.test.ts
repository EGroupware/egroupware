import {assert, fixture, html, nextFrame, oneEvent} from "@open-wc/testing";
import {Et2Toolbar} from "../Et2Toolbar";
import * as sinon from "sinon";
import {waitForEvent} from "../../Et2Widget/event";

// Stub global egw
// @ts-ignore
const egw = {
	app_name: () => "test",
	appName: "test",
	debug: () => {},
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	lang: i => i,
	link: i => i,
	tooltipBind: () => {},
	tooltipUnbind: () => { },
	uniqueId: () => "1",
	webserverUrl: ""
};
window.egw = function() {return egw};
Object.assign(window.egw, egw);
describe("et2-toolbar", () =>
{
	// Make sure it works
	it("renders with no actions", async() =>
	{
		const el = await fixture<any>(html`
            <et2-toolbar></et2-toolbar>`);
		assert.ok(el);
		assert.instanceOf(el, Et2Toolbar);
		assert.equal(el.shadowRoot?.querySelectorAll("button").length, 0);
	});

	it("renders actions as buttons", async() =>
	{
		const el = await fixture<any>(html`
            <et2-toolbar></et2-toolbar>`);
		el.id = "test";
		el.actions = {
			save: {id: "save", caption: "Save"},
			cancel: {id: "cancel", caption: "Cancel"},
		};
		await el.updateComplete;

		const buttons = el.querySelectorAll("et2-button");
		assert.equal(buttons?.length, 2);
		assert.equal(buttons?.[0].getAttribute("label").trim(), "Save");
		assert.equal(buttons?.[1].getAttribute("label").trim(), "Cancel");
	});

	it("updates actions dynamically", async() =>
	{
		const el = await fixture<any>(html`
            <et2-toolbar></et2-toolbar>`);
		el.id = "test";
		el.actions = [{id: "edit", caption: "Edit"}];
		await el.updateComplete;

		el.actions = [{id: "delete", caption: "Delete"}];
		await el.updateComplete;

		const button = el.querySelector("et2-button#delete")!;
		assert.isNotNull(button);
	});
	it("does not affect light DOM children when setting actions", async() =>
	{
		const el : Et2Toolbar = await fixture<any>(html`
            <et2-toolbar>
                <et2-button id="native-btn"></et2-button>
            </et2-toolbar>
		`);
		el.id = "testChildren";

		// Add a light child manually
		const nativeButton = el.querySelector("#native-btn");
		assert.exists(nativeButton, "light DOM <et2-button> exists before setting actions");

		// Now set actions (should not remove light DOM children)
		el.actions = {
			save: {id: "save", caption: "Save"},
			cancel: {id: "cancel", caption: "Cancel"},
		};
		await el.updateComplete;

		// Re-check if light DOM child still exists
		const stillThere = el.querySelector("#native-btn");
		assert.exists(stillThere, "light DOM <et2-button> still exists after setting actions");

		// Check that both native and action-rendered buttons are present
		const renderedButtons = el.querySelectorAll("et2-button");
		assert.equal(renderedButtons.length, 3, "renders action buttons");
	});

	it("reacts to parent resize", async() =>
	{
		const container = document.createElement("div");
		container.style.width = "300px";
		document.body.append(container);

		const el = document.createElement("et2-toolbar") as Et2Toolbar;
		el.id = "resizeTest";
		container.appendChild(el);

		el.actions = {
			save: {id: "save", caption: "Save"},
			cancel: {id: "cancel", caption: "Cancel"},
			refresh: {id: "refresh", caption: "Refresh"}
		};
		await nextFrame(); // Wait for layout

		// Simulate resize observer, since it's hard to test through the observer
		const listener = oneEvent(el, "et2-resize");
		container.style.width = "50px";
		await nextFrame(); // Wait for layout
		el.handleResize([{target: el}], null);
		await listener;

		// Check that at least some button got re-slotted
		assert.isNotEmpty(el.querySelectorAll("[slot='list']"), "Nothing got hidden");
		// Check that dropdown is there
		const listButton = el.shadowRoot.querySelector('sl-dropdown');
		assert.exists(listButton, "Missing dropdown overflow");

		container.remove();
	});

	it("is accessible", async() =>
	{
		const el : Et2Toolbar = await fixture(html`
            <et2-toolbar></et2-toolbar>
		`);
		el.id = "testAccessible";
		el.actions = {help: {id: "help", caption: "Help"}};
		await el.updateComplete;
		await assert.isAccessible(el);
	});

	it("always shows list ⋮ for admins", async() =>
	{
		const el : Et2Toolbar = await fixture(html`
            <et2-toolbar></et2-toolbar>
		`);
		await el.updateComplete;

		// Not currently showing
		let listButton = el.shadowRoot.querySelector('sl-dropdown');
		assert.notExists(listButton, "Unexpected dropdown");

		el._isAdmin = true;
		el.requestUpdate();
		await el.updateComplete;
		listButton = el.shadowRoot.querySelector('sl-dropdown');
		assert.exists(listButton, "Missing dropdown for admin");
	});

	it('hides controls based on preference', async() =>
	{
		// Stub egw().preference
		const egwStub = sinon.stub().callsFake((app : string, key : string) =>
		{
			return {"hide-action": true, "hide-native": true};
		});
		// Replace global egw.preference
		(window as any).egw.preference = egwStub;

		// Set up toolbar with one action and one light DOM child that should be hidden
		const el = await fixture<any>(html`
            <et2-toolbar>
                <et2-button id="hide-native">Native</et2-button>
                <et2-button id="show-native">Visible</et2-button>
            </et2-toolbar>
		`);
		el.id = "visibleTest";

		el.actions = {
			"hide-action": {id: "hide-action", caption: "Hidden Action"},
			"show-action": {id: "show-action", caption: "Visible Action"},
		};
		await el.updateComplete;
		// Resize is deferred to avoid doing it too many times as user drags
		await waitForEvent(el, "et2-resize");

		// All buttons should be present
		const buttons = el.querySelectorAll("et2-button");
		assert.equal(buttons.length, 4);

		// Check visibility of action-rendered buttons
		const hiddenAction = el.querySelector("#hide-action");
		const shownAction = el.querySelector("#show-action");

		assert.exists(hiddenAction);
		assert.equal(hiddenAction!.offsetParent, null, "hidden action button is not visible");

		assert.exists(shownAction);
		assert.notEqual(shownAction!.offsetParent, null, "visible action button is visible");

		// Check visibility of native children
		const nativeHidden = el.querySelector("#hide-native")!;
		const nativeShown = el.querySelector("#show-native")!;

		assert.exists(nativeHidden);
		assert.equal(nativeHidden.offsetParent, null, "native hidden button is not visible");

		assert.exists(nativeShown);
		assert.notEqual(nativeShown.offsetParent, null, "native visible button is visible");
	});

});
