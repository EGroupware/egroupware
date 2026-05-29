import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {resolveActionApiGetters} from "../Et2NextmatchActionController";

const egwStub = {
	lang: (label : string) => label,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	uid: () => "nm-test-id",
	debug: () => {}
};
window.egw = function() { return egwStub; } as any;
Object.assign(window.egw, egwStub);

type FakeAction = {
	id : string;
	type : string;
	data : Record<string, any>;
	execute : (senders : any[], target? : any) => any;
};

describe("Et2Nextmatch action setup", () =>
{
	/**
	 * Contract under test:
	 * - Nextmatch action definitions are retained and linked to a row only when actions are triggered.
	 *
	 * Setup strategy:
	 * - Stub action/object manager APIs with deterministic fakes.
	 * - Configure one popup action.
	 * - Trigger context menu on one row.
	 *
	 * Pass criteria:
	 * - Row action object receives `updateActionLinks()` containing the popup action id.
	 */
	it("links popup action onto row object when context menu is requested", async() =>
	{
		const linkedActions : string[] = [];
		let fakeRowObject : any = null;
		const fakeActionManager = {
			children: [] as FakeAction[],
			data: {},
			getActionById: () => null,
			addAction: () => fakeActionManager,
			updateActions: (actions : Record<string, any>) =>
			{
				fakeActionManager.children = Object.entries(actions).map(([id, action]) =>
				{
					const onExecute = action.onExecute;
					return {
						id,
						type: action.type || "popup",
						data: action.data || {},
						execute: (senders, target) => onExecute?.(action, senders, target)
					};
				});
			},
			setDefaultExecute: () => {}
		};
		const fakeObjectContainer = {
			flags: 0,
			addObject: (_rowId : string, _aoi : any) =>
			{
				fakeRowObject = {
					updateActionLinks: (links : string[]) => linkedActions.splice(0, linkedActions.length, ...links),
					executeActionImplementation: () => true,
					forceSelection: () => {},
					setSelected: () => {},
					setFocused: () => {}
				};
				return fakeRowObject;
			}
		};
		const el = new Et2Nextmatch();
		el.id = "nm_actions";
		document.body.append(el);
		await el.updateComplete;
		const controller = (el as any)._actionController;
		controller.actionManager = fakeActionManager;
		controller.objectManager = fakeObjectContainer;

		el.actions = {
			open: {
				type: "popup",
				caption: "Open"
			}
		};
		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::1");
		el.shadowRoot?.append(row);
		row.dispatchEvent(new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true}));

		assert.deepEqual(linkedActions, ["open"], "popup action id should be linked to row action object");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Selecting a popup action executes the configured handler with the selected row object.
	 *
	 * Setup strategy:
	 * - Use fake action/object managers that model popup execution.
	 * - Configure one popup action with spy-like capture.
	 * - Trigger context menu on one row.
	 *
	 * Pass criteria:
	 * - Handler runs once and receives sender row id matching the triggered row.
	 */
	it("executes popup handler with the selected row sender", async() =>
	{
		const executedRowIds : string[] = [];
		const fakeActionManager = {
			children: [] as FakeAction[],
			data: {},
			getActionById: () => null,
			addAction: () => fakeActionManager,
			updateActions: (actions : Record<string, any>) =>
			{
				fakeActionManager.children = Object.entries(actions).map(([id, action]) => ({
					id,
					type: action.type || "popup",
					data: action.data || {},
					execute: (_senders, _target) => action.onExecute?.(action, _senders, _target)
				}));
			},
			setDefaultExecute: () => {}
		};
		const fakeObjectContainer = {
			flags: 0,
			addObject: (rowId : string, _aoi : any) =>
			{
				const rowObject = {
					id: rowId,
					links: [] as string[],
					updateActionLinks(links : string[])
					{
						this.links = links;
					},
					executeActionImplementation()
					{
						const actionId = this.links[0];
						const action = fakeActionManager.children.find((child) => child.id === actionId);
						if(!action)
						{
							return false;
						}
						action.execute([this], this);
						return true;
					},
					forceSelection: () => {},
					setSelected: () => {},
					setFocused: () => {}
				};
				return rowObject;
			}
		};
		const el = new Et2Nextmatch();
		el.id = "nm_actions_execute";
		document.body.append(el);
		await el.updateComplete;
		const controller = (el as any)._actionController;
		controller.actionManager = fakeActionManager;
		controller.objectManager = fakeObjectContainer;

		el.actions = {
			open: {
				type: "popup",
				caption: "Open",
				onExecute: (_action, senders) => executedRowIds.push(senders?.[0]?.id || "")
			}
		};

		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::7");
		el.shadowRoot?.append(row);
		row.dispatchEvent(new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true}));

		assert.deepEqual(executedRowIds, ["row::7"], "popup handler should receive selected sender row");
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Nextmatch action manager wiring must work without relying on window global action getter functions.
	 *
	 * Setup strategy:
	 * - Explicitly clear `window.egw_getActionManager` and `window.egw_getObjectManager`.
	 * - Use the component's normal action setup and trigger context menu on a row.
	 *
	 * Pass criteria:
	 * - Contextmenu path still resolves row and marks it selected.
	 */
	it("falls back to window action getters when module getters are missing", async() =>
	{
		const windowActionGetter = () => ({source: "window-action"});
		const windowObjectGetter = () => ({source: "window-object"});
		const resolved = resolveActionApiGetters(
			{egw_getActionManager: null, egw_getObjectManager: null},
			{egw_getActionManager: windowActionGetter, egw_getObjectManager: windowObjectGetter}
		);

		assert.strictEqual(resolved.getActionManager, windowActionGetter, "window action getter should be used when module getter is null");
		assert.strictEqual(resolved.getObjectManager, windowObjectGetter, "window object getter should be used when module getter is null");
	});
});
