import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {Et2NextmatchActionController} from "../Et2NextmatchActionController";
import * as sinon from "sinon";

/**
 * Contract: the keyboard route into nextmatch actions.  Everything else that runs an
 * action (context menu, double-click, drag) carries a row element in its event and can
 * rebuild what it needs on the way through; a keypress carries nothing, so it depends on
 * a chain of parts that each fail silently - the capture-phase listener being registered,
 * the shortcut lookup finding the action, and the grid's own navigation keys being left
 * alone.  Nothing throws when a link in that chain breaks, the key just stops doing
 * anything, which is why these are pinned here rather than left to manual testing.
 *
 * Setup: a real Et2Nextmatch for the dispatch tests, so the listener wiring in
 * connectedCallback() is exercised rather than assumed; a bare controller for the
 * shortcut-matching rules.
 *
 * Pass: a keydown raised inside the datagrid reaches the action object manager and stops
 * once the widget is detached; the keys the datagrid owns are never turned into actions;
 * Enter opens the row popup.
 */

const egwStub = {
	lang: (label : string) => label,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: (_key? : string) => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	uid: () => "nm-keyboard-test",
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

const DELETE_ACTION = {
	id: "delete",
	shortcut: {keyCode: 46, shift: false, ctrl: false, alt: false}
};

const keyEvent = (key : string, keyCode : number, modifiers : Partial<KeyboardEventInit> = {}) =>
{
	const event = new KeyboardEvent("keydown", {key, bubbles: true, composed: true, cancelable: true, ...modifiers});
	Object.defineProperty(event, "keyCode", {value: keyCode});
	return event;
};

describe("Et2Nextmatch keyboard actions", () =>
{
	/**
	 * Contract under test:
	 * - A keypress anywhere inside the datagrid reaches the action object manager, and
	 *   stops doing so once the nextmatch is detached.
	 *
	 * Setup strategy:
	 * - Render a real Et2Nextmatch so its own connectedCallback() does the listener
	 *   registration, then raise a composed keydown from inside the datagrid's shadow root.
	 *
	 * Pass criteria:
	 * - The shortcut reaches the action object manager and the event is consumed, and
	 *   neither happens again after the widget is removed.
	 */
	it("routes a keypress raised inside the datagrid to the action object manager", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const controller = (el as any)._actionController as any;
		const execute = sinon.stub().returns(true);
		controller.actionManager = {children: [DELETE_ACTION]};
		controller.objectManager = {executeActionImplementation: execute};

		const datagrid = el.shadowRoot!.querySelector("et2-datagrid") as HTMLElement;
		const source = datagrid.shadowRoot?.getElementById("rows") || datagrid;

		const handled = keyEvent("Delete", 46);
		source.dispatchEvent(handled);
		assert.isTrue(execute.calledOnce, "the keypress should reach the action object manager");
		assert.deepInclude(execute.firstCall.args[0].keyEvent, {keyCode: 46}, "the pressed key should be forwarded");
		assert.isTrue(handled.defaultPrevented, "a handled shortcut should be consumed");

		el.remove();
		source.dispatchEvent(keyEvent("Delete", 46));
		assert.isTrue(execute.calledOnce, "a detached nextmatch must not keep handling keys");
	});

	/**
	 * Contract under test:
	 * - The keys the datagrid uses for its own row navigation are never turned into action
	 *   shortcuts, even when an action declares exactly that key.
	 *
	 * Why it matters: handleShortcut() runs in the capture phase, ahead of the datagrid's
	 * own bubble-phase handler.  Consuming one of these keys there would both stop the row
	 * from moving and - through forceActiveRowSelected() - rewrite the very selection the
	 * datagrid handler is about to read, one row behind where the user actually is.
	 *
	 * Setup strategy:
	 * - Register an action on each grid-owned key, then press it.
	 *
	 * Pass criteria:
	 * - Nothing is executed, the selection is left untouched, and the key stays available
	 *   to the datagrid's handler.
	 */
	it("leaves the datagrid's own navigation keys alone, even when an action claims them", () =>
	{
		const gridOwned : Array<[string, number, Partial<KeyboardEventInit>]> = [
			["ArrowUp", 38, {}],
			["ArrowDown", 40, {}],
			["ArrowLeft", 37, {}],
			["ArrowRight", 39, {}],
			["PageUp", 33, {}],
			["PageDown", 34, {}],
			["Home", 36, {}],
			["End", 35, {}],
			[" ", 32, {}],
			["a", 65, {ctrlKey: true}]
		];

		for(const [key, keyCode, modifiers] of gridOwned)
		{
			const controller : any = new Et2NextmatchActionController({} as any);
			const execute = sinon.stub().returns(true);
			// An action claiming this exact key - the worst case, not a missing-action no-op.
			controller.actionManager = {
				children: [{
					id: `claims-${key}`,
					shortcut: {keyCode, shift: false, ctrl: !!modifiers.ctrlKey, alt: false}
				}]
			};
			controller.objectManager = {executeActionImplementation: execute};
			controller.host = {getActiveRowId: () => "row-2", selectSingleRow: sinon.stub()};
			controller.selectedRowIds = ["row-0"];

			const event = keyEvent(key, keyCode, modifiers);
			assert.isFalse(controller.handleShortcut(event), `${key} must stay with the datagrid`);
			assert.isFalse(execute.called, `${key} must not execute an action`);
			assert.isFalse(controller.host.selectSingleRow.called,
				`${key} must not rewrite the selection the datagrid handler still has to read`);
		}
	});

	/**
	 * Contract under test:
	 * - Enter on a row opens that row's action popup, and only Enter does.
	 *
	 * Setup strategy:
	 * - Render a real Et2Nextmatch and stub the popup trigger on its controller.
	 *
	 * Pass criteria:
	 * - Enter reaches the popup trigger and is consumed; another key does not.
	 */
	it("opens the row action popup on Enter", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const controller = (el as any)._actionController as Et2NextmatchActionController;
		const triggerPopup = sinon.stub().returns(true);
		(controller as any).triggerPopupForRow = triggerPopup;
		(controller as any).actionManager = {children: []};
		(controller as any).objectManager = {executeActionImplementation: () => false};

		const datagrid = el.shadowRoot!.querySelector("et2-datagrid") as HTMLElement;
		const source = datagrid.shadowRoot?.getElementById("rows") || datagrid;

		const enter = keyEvent("Enter", 13);
		source.dispatchEvent(enter);
		assert.isTrue(triggerPopup.calledOnce, "Enter should open the row action popup");
		assert.isTrue(enter.defaultPrevented, "a handled Enter should be consumed");

		source.dispatchEvent(keyEvent("Escape", 27));
		assert.isTrue(triggerPopup.calledOnce, "only Enter opens the row popup");

		el.remove();
	});
});
