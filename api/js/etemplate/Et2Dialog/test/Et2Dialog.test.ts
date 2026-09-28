import {assert, elementUpdated, expect, fixture, html, oneEvent} from '@open-wc/testing';
import {sendKeys} from "@web/test-runner-commands";
import * as sinon from 'sinon';
import {Et2Dialog} from "../Et2Dialog";
import {assertNoElement} from "../../test/assertDom";
import "../../Et2Textarea/Et2Textarea";
import "../../Et2Textbox/Et2Textbox";

/**
 * Test file for Etemplate webComponent Et2Dialog
 *
 * In here we test just the simple, basic widget stuff.
 */
// Stub global egw for egw_action to find
const egw = {
	ajaxUrl: () => "",
	app: () => "addressbook",
	app_name: () => "addressbook",
	decodePath: (_path : string) => _path,
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	jsonq: () => Promise.resolve({}),
	lang: i => i + "*",
	link: i => i,
	preference: i => "",
	tooltipUnbind: () => {},
	webserverUrl: ""
}
window.egw = function() {return egw};
Object.assign(window.egw, egw);

let element : Et2Dialog;

async function before()
{
	// Create an element to test with, and wait until it's ready
	// @ts-ignore
	element = await fixture<Et2Dialog>(html`
        <et2-dialog title="I'm a dialog">
        </et2-dialog>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Dialog widget basics", () =>
{
	// Setup run before each test
	beforeEach(before);

	// Make sure it works
	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Dialog);
	});

	it('has a title', async() =>
	{
		element.title = "Title set";
		await elementUpdated(element);

		assert.equal(element.shadowRoot.querySelector("#title").textContent.trim(), "Title set");
	});

	it("preserves loaded template content when buttons change", async() =>
	{
		const contentNode = element.querySelector(".dialog_content");
		const loadedContent = document.createElement("div");
		loadedContent.id = "loaded-template-content";
		contentNode.append(loadedContent);

		element.buttons = Et2Dialog.BUTTONS_OK_CANCEL;
		await elementUpdated(element);

		assert.strictEqual(element.querySelector(".dialog_content"), contentNode,
			"Button changes must keep the template container");
		assert.strictEqual(element.querySelector("#loaded-template-content"), loadedContent,
			"Button changes must not destroy loaded template content");
	});

	it("resolves getComplete() with a custom string button_id as-is, not corrupted by parseInt()", async() =>
	{
		// Regression test: _onClick() used to unconditionally parseInt() the button_id
		// attribute. That's correct for the built-in numeric *_BUTTON constants, but silently
		// turned any custom string id (eg. "add"/"remove", or "dont_ask_again" in egw_timer.ts)
		// into NaN - making two different custom buttons indistinguishable from each other to a
		// callback comparing `button === "add"` vs `button === "remove"`.
		//
		// Buttons must be set as a property on the fixture's initial markup, not assigned
		// afterwards: Et2Dialog only ever renders its (light-DOM) button elements once, in
		// firstUpdated() - a later `element.buttons = ...` requests a re-render but does not
		// recreate the <et2-button> elements, so the buttons used by every other test in this
		// file (assigned post-creation) are never actually queryable in the DOM.
		// @ts-ignore
		const dialog = await fixture<Et2Dialog>(html`
			<et2-dialog title="Custom buttons" .buttons=${[
				{button_id: "add", label: "Add", id: "dialog[add]"},
				{button_id: "remove", label: "Remove", id: "dialog[remove]"}
			]}>
			</et2-dialog>
		`);
		sinon.stub(dialog, "egw").returns(window.egw);
		await elementUpdated(dialog);
		await dialog.show();

		const addButton = dialog.querySelector('et2-button[button_id="add"]');
		const removeButton = dialog.querySelector('et2-button[button_id="remove"]');
		assert.isNotNull(addButton, "Add button must be rendered");
		assert.isNotNull(removeButton, "Remove button must be rendered");

		const completePromise = dialog.getComplete();
		(<HTMLElement>addButton).click();
		const [buttonId] = await completePromise;

		assert.strictEqual(buttonId, "add");
		assert.notStrictEqual(<any>buttonId, <any>NaN);

		await dialog.hide();
	});

	it("falls back to the button's id when it has no button_id", async() =>
	{
		// A caller-defined button may omit button_id entirely and only carry an id (eg. the
		// "delete" button of Et2Portlet's edit dialog, which its callback identifies by
		// `button_id == "delete"`). _onClick() then has to fall back to the id attribute, so
		// keep that fallback - and make sure the (unset) button_id attribute stays absent
		// rather than being rendered as an empty or literal "undefined" string, either of
		// which would shadow the id.
		// @ts-ignore
		const dialog = await fixture<Et2Dialog>(html`
			<et2-dialog title="Custom buttons" .buttons=${[
				{label: "Custom", id: "custom_action", image: "check"}
			]}>
			</et2-dialog>
		`);
		sinon.stub(dialog, "egw").returns(window.egw);
		await elementUpdated(dialog);
		await dialog.show();

		const button = dialog.querySelector('et2-button[id="custom_action"]');
		assert.isNotNull(button, "Button must be rendered");
		assert.isFalse(button.hasAttribute("button_id"),
			"An unset button_id must not be rendered as an attribute");

		const completePromise = dialog.getComplete();
		(<HTMLElement>button).click();
		const [buttonId] = await completePromise;

		assert.strictEqual(buttonId, "custom_action");

		await dialog.hide();
	});
});
describe("Properties", async() =>
{
	// Setup run before each test
	beforeEach(before);

	it("destroyOnClose = true", async() =>
	{
		element.destroyOnClose = true;
		await element.show();
		assert.isNotNull(document.querySelector("et2-dialog"));
		await element.hide();

		assertNoElement(document.querySelector("et2-dialog"));
	});
	it("destroyOnClose = false", async() =>
	{
		element.destroyOnClose = false;
		await element.show();
		assert.isNotNull(document.querySelector("et2-dialog"));

		await element.hide();
		assert.isNotNull(document.querySelector("et2-dialog"));
	});
	it("noCloseButton", async() =>
	{
		await element.show();
		const closeButton = element.shadowRoot.querySelector("[part=close-button]");
		assert.isNotNull(closeButton);
		assert.isTrue(closeButton.checkVisibility());

		element.noCloseButton = true;
		await element.show();

		assert.isFalse(closeButton.checkVisibility());
	});
	it("hideOnEscape = true", async() =>
	{
		element.hideOnEscape = true;

		await element.show();
		const listener = oneEvent(element, "close");

		await sendKeys({down: "Escape"});
		const event = await listener;
		expect(event).to.exist;
	});
	it("hideOnEscape = false", (done) =>
	{
		element.hideOnEscape = false;

		element.show().then(async() =>
		{
			// Listen for events
			const requestCloseListener = oneEvent(element, "sl-request-close");
			const closeListener = oneEvent(element, "close");

			let event = null;

			// Press Escape
			let keysSender = await sendKeys({down: "Escape"});

			// Request close gets sent, but Et2Dialog cancels it if hideOnEscape=false
			await requestCloseListener;

			// Can't really test that an event didn't happen
			setTimeout(() =>
			{
				assert.isNull(event, "Close happened");
				done();
			}, 500)

			event = await closeListener;
			return requestCloseListener;
		});
	});
});
describe("Enter key", () =>
{
	// Enter on its own in a single line field presses the dialog's default button, but Enter from a
	// multi-line field is a line break, and so is Shift+Enter from anywhere.  The dialog only sees the
	// key events after they are retargeted to the shadow host (et2-textarea, et2-textbox), so these use
	// real keyboard input into the fields' inner elements rather than synthetic events on the host.
	let dialog : Et2Dialog;
	let clicked : sinon.SinonSpy;

	beforeEach(async() =>
	{
		// Buttons have to be there from the start, see "resolves getComplete() with a custom string button_id"
		// @ts-ignore
		dialog = await fixture<Et2Dialog>(html`
			<et2-dialog title="Enter key" .buttons=${Et2Dialog.BUTTONS_OK_CANCEL} .destroyOnClose=${false}>
			</et2-dialog>
		`);
		sinon.stub(dialog, "egw").returns(window.egw);
		await elementUpdated(dialog);

		const content = dialog.querySelector(".dialog_content");
		// Where a loaded template would put its fields
		content.insertAdjacentHTML("beforeend", "<et2-textarea></et2-textarea><et2-textbox></et2-textbox>");
		// Shoelace focuses the dialog panel one animation frame after opening; wait for that, or it takes the
		// focus away from the field again before the keys are sent
		const initialFocus = oneEvent(dialog, "sl-initial-focus");
		await dialog.show();
		await initialFocus;
		await elementUpdated(dialog.querySelector("et2-textarea"));
		await elementUpdated(dialog.querySelector("et2-textbox"));

		const button = dialog.querySelector("et2-button[slot='footer']");
		assert.isNotNull(button, "Dialog must have a footer button");
		clicked = sinon.spy();
		button.addEventListener("click", clicked);
	});

	async function press(selector : string, key : string)
	{
		const field = <HTMLElement>dialog.querySelector(selector);
		field.focus();
		// The keys have to come from inside the field, not from wherever the focus happened to be
		const keyup = oneEvent(field, "keyup");
		await sendKeys({press: key});
		await keyup;
		// Let any click the keyup triggered run
		await new Promise(resolve => setTimeout(resolve, 50));
	}

	it("Shift+Enter in a textarea does not press the default button", async() =>
	{
		await press("et2-textarea", "Shift+Enter");
		assert.isFalse(clicked.called, "Default button was pressed");
		assert.isTrue(dialog.open, "Dialog closed");
	});

	it("Shift+Enter in a textbox does not press the default button", async() =>
	{
		await press("et2-textbox", "Shift+Enter");
		assert.isFalse(clicked.called, "Default button was pressed");
		assert.isTrue(dialog.open, "Dialog closed");
	});

	it("Enter in a textarea does not press the default button", async() =>
	{
		await press("et2-textarea", "Enter");
		assert.isFalse(clicked.called, "Default button was pressed");
		assert.isTrue(dialog.open, "Dialog closed");
	});

	it("Enter in a textbox presses the default button", async() =>
	{
		await press("et2-textbox", "Enter");
		assert.isTrue(clicked.calledOnce, "Default button was not pressed");
	});
});
