import {assert, fixture, html, nextFrame, oneEvent} from "@open-wc/testing";
import {Et2Toolbar} from "../Et2Toolbar";
// Toolbar creates these from actions, they need to be registered for the dirty state tests
import "../../Et2Button/Et2ButtonToggle";
import "../../Et2DropdownButton/Et2DropdownButton";
import "../../Et2Select/Et2Select";
import {et2_IInput} from "../../et2_core_interfaces";
import * as sinon from "sinon";
import {waitForEvent} from "../../Et2Widget/event";
import {assertNoElement} from "../../test/assertDom";

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
		assertNoElement(listButton, "Unexpected dropdown");

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

	/**
	 * A toolbar generates its widgets from actions, which happens after the template was loaded -
	 * and so after etemplate2.load() reset everyone's dirty state.  Any value they get then (a
	 * dropdown-button's defaultPreference, a checkbox action's checked state) looks exactly like a
	 * user edit to isDirty(), and because etemplate2.isDirty() walks the whole widget tree it finds
	 * them regardless of the toolbar's own "never dirty" answer.  Symptom when this breaks: closing
	 * a popup that has a toolbar (mail's display window) warns about unsaved changes.
	 */
	describe("dirty state", () =>
	{
		async function toolbarWithActions()
		{
			const el = await fixture<any>(html`
                <et2-toolbar></et2-toolbar>`);
			el.id = "dirtyTest";
			el.actions = {
				save: {id: "save", caption: "Save"},
				toggle: {id: "toggle", caption: "Toggle", checkbox: true, checked: true},
				flag: {
					id: "flag", caption: "Flag", groupChildren: true, children: [
						{id: "flagged", caption: "Flagged"},
						{id: "unflagged", caption: "Unflagged"}
					]
				}
			};
			await el.updateComplete;
			return el;
		}

		/**
		 * Everything the toolbar made out of an action - not all of it is marked in the DOM
		 * (a dropdown-button gets no data-action-id), so ask the toolbar
		 */
		function generatedWidgets(el)
		{
			const generated = el._actionWidgets;
			assert.isNotEmpty(generated, "test setup: toolbar should have generated widgets");
			assert.include(generated.map(w => w.id), "flag", "test setup: dropdown-button should be tracked too");
			return generated;
		}

		/** Give every generated widget a value, as an action would */
		async function changeValues(generated)
		{
			for(const widget of generated)
			{
				widget.value = widget.tagName.toLowerCase() == "et2-dropdown-button" ? "unflagged" : true;
				await widget.updateComplete;
			}
		}

		it("starts generated widgets off clean", async() =>
		{
			const el = await toolbarWithActions();

			generatedWidgets(el).forEach(widget =>
				assert.isFalse(widget.isDirty(), widget.id + " should not be dirty before it is touched")
			);
		});

		/**
		 * handleAction() resets the toolbar after running an action, so picking another entry from
		 * a toolbar dropdown does not count as unsaved content.  That reset has to reach the
		 * widgets we generated - the toolbar's own isDirty() is hardcoded false, so resetting only
		 * itself does nothing.
		 */
		it("resets generated widgets along with itself", async() =>
		{
			const el = await toolbarWithActions();

			const generated = generatedWidgets(el);
			await changeValues(generated);
			assert.isTrue(Array.from(generated).some((w : any) => w.isDirty()), "test setup: changing a value makes a widget dirty");

			el.resetDirty();

			generated.forEach(widget =>
				assert.isFalse(widget.isDirty(), widget.id + " should be clean again after resetDirty()")
			);
		});

		/**
		 * A toolbar is also a plain layout container: widgets the template author put inside it are
		 * normal form input that happens to sit in a toolbar, not something we generated, so they
		 * keep tracking their own dirty state - including across the toolbar's own resetDirty().
		 */
		it("leaves widgets it did not generate alone", async() =>
		{
			const el = await fixture<any>(html`
                <et2-toolbar>
                    <et2-select id="native-select"></et2-select>
                </et2-toolbar>`);
			el.id = "dirtyNativeTest";
			el.actions = {save: {id: "save", caption: "Save"}};
			await el.updateComplete;

			const select = el.querySelector("#native-select");
			select.select_options = [{value: "a", label: "A"}, {value: "b", label: "B"}];
			select.resetDirty();
			assert.isFalse(select.isDirty(), "test setup: untouched select is not dirty");

			select.value = "b";
			await select.updateComplete;
			assert.isTrue(select.isDirty(), "a select in a toolbar still tracks its own dirty state");

			el.resetDirty();

			assert.isTrue(select.isDirty(), "...and the toolbar's reset does not clear it");
		});

		/**
		 * Clean must not mean not submitted: etemplate2.getValues() collects every et2_IInput's
		 * getValue() and never looks at isDirty(), so a generated widget still contributes its
		 * value either way.
		 */
		it("still returns a value for generated widgets", async() =>
		{
			const el = await toolbarWithActions();

			const dropdown = el.querySelector("et2-dropdown-button#flag");
			dropdown.value = "unflagged";
			await dropdown.updateComplete;
			el.resetDirty();

			assert.equal(dropdown.getValue(), "unflagged", "generated widget still has its value");
			assert.isTrue(dropdown.instanceOf(et2_IInput), "...and still counts as input for getValues()");
		});
	});

	/**
	 * A child pinned to the menu by preference lives inside the dropdown panel, so its
	 * offsetLeft describes a position in *that*, not in the button row.  Measuring it against
	 * the row's width says "overflowed" by construction, and because that verdict is sticky it
	 * used to push every button ordered after it into the menu too - a calendar toolbar with
	 * one early-sorted pinned entry lost Today, previous/next and every integration toggle,
	 * with ~950px of empty row sitting there.
	 */
	describe("overflow calculation", () =>
	{
		async function toolbarWithButtonRow()
		{
			const el = await fixture<any>(html`
                <et2-toolbar></et2-toolbar>`);
			el.id = "overflowTest";
			await el.updateComplete;
			const buttonDiv = el.shadowRoot.querySelector(".toolbar-buttons");
			assert.exists(buttonDiv, "test setup: toolbar should have rendered its button row");
			return {el, buttonDiv};
		}

		/** A child whose geometry puts it well past the end of the button row */
		function childAt(id : string, offsetLeft : number)
		{
			const child = document.createElement("et2-button");
			child.id = id;
			Object.defineProperty(child, "offsetWidth", {value: 181});
			Object.defineProperty(child, "offsetLeft", {value: offsetLeft});
			return child;
		}

		it("does not let a menu-pinned child report the row as overflowed", async() =>
		{
			const {el, buttonDiv} = await toolbarWithButtonRow();
			el._preference = {pinned: true};

			const pinned = childAt("pinned", buttonDiv.offsetWidth + 500);
			const overflowed = el._organiseChild(pinned, false);

			assert.isFalse(overflowed, "a pinned child must not report the row as overflowed");
			assert.isFalse(el._isOverflowed, "...nor flag the toolbar itself as overflowed");
			assert.equal(pinned.slot, "list", "a pinned child still belongs in the menu");
		});

		it("still overflows on a child that genuinely does not fit", async() =>
		{
			const {el, buttonDiv} = await toolbarWithButtonRow();
			el._preference = {tooWide: false};

			const tooWide = childAt("tooWide", buttonDiv.offsetWidth + 500);
			const overflowed = el._organiseChild(tooWide, false);

			assert.isTrue(overflowed, "a child past the end of the row is overflowed");
			assert.isTrue(el._isOverflowed, "and the toolbar knows it has overflow");
			assert.equal(tooWide.slot, "list", "an overflowed child goes into the menu");
		});
	});

});
