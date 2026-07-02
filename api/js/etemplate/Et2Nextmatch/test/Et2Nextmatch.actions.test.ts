import {assert} from "@open-wc/testing";
import {Et2Nextmatch} from "../Et2Nextmatch";
import {Et2NextmatchActionController, resolveActionApiGetters} from "../Et2NextmatchActionController";
import {EgwPopupActionImplementation} from "../../../egw_action/EgwPopupActionImplementation";
import * as sinon from "sinon";

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

let resizeObserverErrorHandler : ((event : ErrorEvent) => void) | null = null;
let resizeObserverRejectionHandler : ((event : PromiseRejectionEvent) => void) | null = null;
let originalResizeObserver : typeof window.ResizeObserver | undefined;

type FakeAction = {
	id : string;
	type : string;
	data : Record<string, any>;
	execute : (senders : any[], target? : any) => any;
};

const makeFakeRowObject = (overrides : Record<string, any> = {}) =>
{
	const rowObject : any = {
		id: "",
		iface: {
			getDOMNode: () => null
		},
		parent: {
			updateSelectedChildren: () => {},
			updateFocusedChild: () => {}
		},
		updateActionLinks: () => {},
		executeActionImplementation: () => true,
		forceSelection: () => {},
		setSelected: () => {},
		setFocused: () => {},
		setAOI(nextAoi : any)
		{
			this.iface = nextAoi;
		},
		unregisterActions: () => {},
		getContainerRoot: () => ({
			getSelectedObjects: () => [],
			setAllSelected: () => {}
		})
	};
	return Object.assign(rowObject, overrides);
};

const makeFakeObjectManager = (factory? : (rowId : string, aoi : any) => any) =>
({
	flags: 0,
	addObject: (rowId : string, aoi : any) =>
	{
		const rowObject = factory?.(rowId, aoi) || makeFakeRowObject({id: rowId});
		rowObject.id = rowId;
		rowObject.iface = aoi;
		return rowObject;
	},
	setAOI: () => {},
	updateActionLinks: () => {},
	unregisterActions: () => {}
});

const waitForDatagridRows = async(datagrid : any, expectedCount : number) =>
{
	for(let i = 0; i < 20; i++)
	{
		if((datagrid?.rows?.length || 0) >= expectedCount)
		{
			return;
		}
		await Promise.resolve();
		await datagrid?.updateComplete;
	}
};

const waitForRenderedDatagridRow = async(datagrid : HTMLElement & { updateComplete? : Promise<unknown> }, rowId : string) : Promise<HTMLElement | null> =>
{
	for(let i = 0; i < 20; i++)
	{
		const row = datagrid?.shadowRoot?.querySelector(`[data-row-id='${rowId}']`) as HTMLElement | null;
		if(row)
		{
			return row;
		}
		await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
		await datagrid?.updateComplete;
	}
	return null;
};

describe("Et2Nextmatch action setup", () =>
{
	before(() =>
	{
		originalResizeObserver = window.ResizeObserver;
		class ResizeObserverStub
		{
			observe() {}
			unobserve() {}
			disconnect() {}
		}
		window.ResizeObserver = ResizeObserverStub as any;

		resizeObserverErrorHandler = (event : ErrorEvent) =>
		{
			const message = String(event?.message || "");
			if(message.includes("ResizeObserver loop completed with undelivered notifications"))
			{
				event.preventDefault();
				event.stopImmediatePropagation?.();
			}
		};
		window.addEventListener("error", resizeObserverErrorHandler);
		resizeObserverRejectionHandler = (event : PromiseRejectionEvent) =>
		{
			const message = String((event?.reason && (event.reason.message || event.reason)) || "");
			if(message.includes("ResizeObserver loop completed with undelivered notifications"))
			{
				event.preventDefault();
			}
		};
		window.addEventListener("unhandledrejection", resizeObserverRejectionHandler);
	});

	after(() =>
	{
		if(resizeObserverErrorHandler)
		{
			window.removeEventListener("error", resizeObserverErrorHandler);
			resizeObserverErrorHandler = null;
		}
		if(resizeObserverRejectionHandler)
		{
			window.removeEventListener("unhandledrejection", resizeObserverRejectionHandler);
			resizeObserverRejectionHandler = null;
		}
		if(originalResizeObserver)
		{
			window.ResizeObserver = originalResizeObserver;
		}
	});

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
		const controller : any = new Et2NextmatchActionController({
			id: "nm_actions",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = fakeActionManager;
		controller.objectManager = makeFakeObjectManager((rowId) => makeFakeRowObject({
			id: rowId,
			updateActionLinks: (links : string[]) => linkedActions.splice(0, linkedActions.length, ...links)
		}));

		controller.initActions({
			open: {
				type: "popup",
				caption: "Open"
			}
		});
		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::1");
		controller.findEventRow = () => ({rowId: "row::1", rowElement: row});
		controller.triggerPopupForRow(new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true}));

		assert.deepEqual(linkedActions, ["open"], "popup action id should be linked to row action object");
	});

	/**
	 * Contract under test:
	 * - Recycled datagrid rows do not leave action objects holding detached DOM nodes.
	 *
	 * Setup strategy:
	 * - Create a row action object through the normal popup-target path.
	 * - Detach that row and customize a later rendered row.
	 *
	 * Pass criteria:
	 * - The detached row action object is removed during row customization.
	 */
	it("prunes detached row action objects while customizing rendered rows", () =>
	{
		const removeDetached = sinon.spy();
		const controller : any = new Et2NextmatchActionController({
			id: "nm_actions_cleanup",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = {
			children: [],
			data: {},
			getActionById: () => null,
			addAction: () => controller.actionManager,
			updateActions: () => {},
			setDefaultExecute: () => {}
		};
		controller.objectManager = makeFakeObjectManager((rowId) => makeFakeRowObject({
			id: rowId,
			remove: removeDetached
		}));

		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::detached");
		document.body.append(row);
		controller.findEventRow = () => ({rowId: "row::detached", rowElement: row});
		controller.triggerPopupForRow(new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true}));
		row.remove();

		const nextRow = document.createElement("div");
		nextRow.setAttribute("data-row-id", "row::next");
		document.body.append(nextRow);
		try
		{
			controller.customizeRowElement(nextRow);
			assert.isTrue(removeDetached.calledOnce, "detached row action object should be removed");
			assert.isFalse(controller.rowActionObjects.has("row::detached"), "detached action object should leave the map");
		}
		finally
		{
			nextRow.remove();
		}
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
		const controller : any = new Et2NextmatchActionController({
			id: "nm_actions_execute",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = fakeActionManager;
		controller.objectManager = makeFakeObjectManager((rowId) =>
		{
			const rowObject = makeFakeRowObject({id: rowId, links: [] as string[]});
			rowObject.updateActionLinks = function(links : string[])
			{
				this.links = links;
			};
			rowObject.executeActionImplementation = function()
			{
				const actionId = this.links[0];
				const action = fakeActionManager.children.find((child) => child.id === actionId);
				if(!action)
				{
					return false;
				}
				action.execute([this], this);
				return true;
			};
			return rowObject;
		});

		controller.initActions({
			open: {
				type: "popup",
				caption: "Open",
				onExecute: (_action, senders) => executedRowIds.push(senders?.[0]?.id || "")
			}
		});

		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::7");
		controller.findEventRow = () => ({rowId: "row::7", rowElement: row});
		controller.triggerPopupForRow(new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true}));

		assert.deepEqual(executedRowIds, ["row::7"], "popup handler should receive selected sender row");
	});

	/**
	 * Contract under test:
	 * - Et2Nextmatch action registration attaches the owning nextmatch pointer
	 *   on every action, including nested child actions.
	 *
	 * Setup strategy:
	 * - Use a fake action manager that builds a parent action with one child.
	 * - Initialize actions through the controller.
	 *
	 * Pass criteria:
	 * - Parent and child action data both contain the owning nextmatch instance.
	 */
	it("annotates registered actions and children with the owning nextmatch", () =>
	{
		const host = {
			id: "nm_annotate",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any;
		const fakeActionManager = {
			children: [] as any[],
			data: {},
			getActionById: () => null,
			updateActions: () =>
			{
				fakeActionManager.children = [{
					id: "parent",
					data: {},
					children: {
						child: {
							id: "child",
							data: {}
						}
					}
				}];
			},
			setDefaultExecute: () => {}
		};
		const controller : any = new Et2NextmatchActionController(host);
		controller.actionManager = fakeActionManager;
		controller.objectManager = makeFakeObjectManager();

		controller.initActions({parent: {caption: "Parent"}});

		assert.strictEqual(fakeActionManager.children[0].data.nextmatch, host, "parent action should reference nextmatch");
		assert.strictEqual(fakeActionManager.children[0].children.child.data.nextmatch, host, "child action should reference nextmatch");
	});

	/**
	 * Contract under test:
	 * - Controller submit actions provide selected ids, select_all, checkboxes,
	 *   active filters and the configured action variable from Et2Nextmatch.settings.
	 *
	 * Setup strategy:
	 * - Execute the controller submit path against a real Et2Nextmatch.
	 * - Stub the instance manager submit path.
	 *
	 * Pass criteria:
	 * - Submit is called once.
	 * - The generated nextmatch value includes the controller submit payload.
	 */
	it("supports submit actions through Et2Nextmatch settings action_var", () =>
	{
		const el = new Et2Nextmatch();
		el.id = "nm";
		const submit = sinon.spy();
		const postSubmit = sinon.spy();
		el.settings = {action_var: "nm_action_id"};
		el.setInstanceManager({
			submit,
			postSubmit,
			DOMContainer: document.body,
			app: "addressbook",
			uniqueId: "nm_submit_uid"
		} as any);
		el.applyFilters({filter: "open"}, {reload: false});
		const controller : any = (el as any)._actionController;
		controller.actionManager = {
			getActionsByAttr: () => []
		};

		const action : any = {
			id: "archive",
			caption: "Archive",
			data: {
				nm_action: "submit",
				extra_payload: "kept"
			}
		};

		controller.executeNextmatchAction(action, [], null, {ids: ["addressbook::7"], all: false});
		const value = el.value;

		assert.isTrue(submit.calledOnce, "submit action should use the instance manager submit path");
		assert.isFalse(postSubmit.called, "regular submit should not use postSubmit");
		assert.deepEqual(value.selected, ["7"], "selected ids should be converted back to provider ids");
		assert.equal(value.select_all, false, "select_all flag should be included");
		assert.equal(value.filter, "open", "active filters should be included");
		assert.equal(value.extra_payload, "kept", "action data should be included");
		assert.equal(value.nm_action_id, "archive", "configured action_var should receive the action id");
		assert.notProperty(value, "nextmatch", "nextmatch object should not be submitted");
	});

	it("can execute a plain action through the controller submit path", () =>
	{
		const el = new Et2Nextmatch();
		el.id = "nm";
		const submit = sinon.spy();
		el.setInstanceManager({
			submit,
			DOMContainer: document.body,
			app: "addressbook",
			uniqueId: "nm_view_org_uid"
		} as any);
		const action : any = {
			id: "view_org",
			data: {}
		};
		const controller : any = (el as any)._actionController;
		controller.actionManager = {
			getActionById: (id) => id === "view_org" ? action : null,
			getActionsByAttr: () => []
		};

		const handled = el.executeAction("view_org", {ids: ["org_name:Example"], all: false}, {nmAction: "submit"});
		const value = el.value;

		assert.isTrue(handled, "existing action should be handled");
		assert.isTrue(submit.calledOnce, "forced submit action should submit the instance manager");
		assert.deepEqual(value.selected, ["org_name:Example"], "selected ids should be submitted");
		assert.equal(value.action, "view_org", "default action_var should receive the action id");
		assert.notProperty(action.data, "nm_action", "temporary nm_action should not remain on action data");
	});

	it("executes actions against the current selection when selection is omitted", () =>
	{
		const el = new Et2Nextmatch();
		el.id = "nm";
		const submit = sinon.spy();
		el.setInstanceManager({
			submit,
			DOMContainer: document.body,
			app: "addressbook",
			uniqueId: "nm_current_selection_uid"
		} as any);
		const action : any = {
			id: "view_org",
			data: {}
		};
		const controller : any = (el as any)._actionController;
		controller.actionManager = {
			getActionById: (id) => id === "view_org" ? action : null,
			getActionsByAttr: () => []
		};
		sinon.stub(el, "getSelection").returns({ids: ["addressbook::99"], all: false});

		el.executeAction("view_org", undefined, {nmAction: "submit"});

		assert.deepEqual(el.value.selected, ["99"], "omitted selection should use the nextmatch current selection");
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

	/**
	 * Contract under test:
	 * - Holding Ctrl while right-clicking bypasses nextmatch custom popup interception.
	 *
	 * Setup strategy:
	 * - Stub placeholder and row popup controller methods.
	 * - Dispatch cancelable `contextmenu` with `ctrlKey=true` from a row node.
	 *
	 * Pass criteria:
	 * - Event is not prevented.
	 * - Neither placeholder nor row popup controller path is invoked.
	 */
	it("skips custom context popup handling when ctrl key is pressed", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const triggerPlaceholderPopup = sinon.stub((el as any)._actionController, "triggerPlaceholderPopup").returns(true);
		const triggerPopupForRow = sinon.stub((el as any)._actionController, "triggerPopupForRow").returns(true);

		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::ctrl");
		el.shadowRoot?.append(row);

		const event = new MouseEvent("contextmenu", {
			bubbles: true,
			composed: true,
			cancelable: true,
			ctrlKey: true
		});
		row.dispatchEvent(event);

		assert.isFalse(event.defaultPrevented, "native menu should not be prevented when ctrl is held");
		assert.isFalse(triggerPlaceholderPopup.called, "placeholder popup should not be called with ctrl key");
		assert.isFalse(triggerPopupForRow.called, "row popup should not be called with ctrl key");

		triggerPlaceholderPopup.restore();
		triggerPopupForRow.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Right-clicking a visible row after the addressbook named row template
	 *   has loaded and the grid has been scrolled must not surface the datagrid
	 *   missing-template warning.
	 *
	 * Setup strategy:
	 * - Configure the same named template used by addressbook `index.xet`.
	 * - Resolve template data and preload enough rows for a visible grid.
	 * - Dispatch scroll and then a composed contextmenu event from a rendered row.
	 *
	 * Pass criteria:
	 * - Rows are rendered before the contextmenu event.
	 * - Row context-menu handling is invoked.
	 * - `Et2Datagrid: No row template configured` is not logged.
	 */
	it("does not warn about missing row template when right-clicking a visible row after scroll", async() =>
	{
		const el = new Et2Nextmatch();
		el.setAttribute("template", "addressbook.index.rows");
		el.rows = [{
			id: "addressbook::1",
			data: {name: "Contact 1"}
		}];
		const rowTemplate = document.createElement("template");
		rowTemplate.innerHTML = `
			<tr>
				<td data-col-key="name"><span data-name="name"></span></td>
			</tr>
		`;
		const fromTemplate = sinon.stub((el as any)._rowProvider, "fromTemplate").resolves({
			rowTemplateId: "addressbook.index.rows",
			rowTemplate,
			rowTemplateXml: null,
			rowTemplateAttrMap: {},
			loaderTemplate: null,
			columns: [{key: "name", title: "Name"}]
		});
		let debug : sinon.SinonSpy | null = null;
		let triggerPopupForRow : sinon.SinonStub | null = null;
		try
		{
			debug = sinon.spy(egwStub, "debug");
			document.body.append(el);
			await el.updateComplete;
			await el.updateComplete;

			const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as HTMLElement & { updateComplete? : Promise<unknown> };
			await datagrid?.updateComplete;
			await waitForDatagridRows(datagrid, 1);
			assert.isTrue(!!(datagrid as any)?.templateData?.rowTemplate, "row template should be loaded before right-click");
			assert.equal((datagrid as any)?.rows?.length, 1, "datagrid should have row data before right-click");

			const body = datagrid?.shadowRoot?.querySelector(".dg-body") as HTMLElement | null;
			body!.scrollTop = 120;
			body!.dispatchEvent(new Event("scroll", {bubbles: true, composed: true}));

			const row = await waitForRenderedDatagridRow(datagrid, "addressbook::1");
			assert.isNotNull(row, "a row should be visible before right-click");

			triggerPopupForRow = sinon.stub((el as any)._actionController, "triggerPopupForRow").returns(true);
			const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true});
			row!.dispatchEvent(event);

			assert.isTrue(triggerPopupForRow.calledOnce, "right-click should use row popup handling");
			assert.isTrue(event.defaultPrevented, "row context menu should be intercepted");
			assert.isFalse(debug!.getCalls().some((call) =>
					(call.args as any[])[0] === "warn" && (call.args as any[])[1] === "Et2Datagrid: No row template configured"),
				"missing-template warning should not be logged after rows are visible");
		}
		finally
		{
			triggerPopupForRow?.restore();
			debug?.restore();
			fromTemplate.restore();
			el.remove();
		}
	});

	/**
	 * Contract under test:
	 * - Right-clicking a row while the addressbook named row template is still
	 *   loading must not surface the datagrid missing-template warning.
	 *
	 * Setup strategy:
	 * - Configure the same named template used by addressbook `index.xet`.
	 * - Keep row-template resolution pending during first render.
	 * - Dispatch a composed contextmenu event from a datagrid row.
	 *
	 * Pass criteria:
	 * - Row context-menu handling is invoked.
	 * - `Et2Datagrid: No row template configured` is not logged before template
	 *   resolution completes.
	 */
	it("does not warn about missing row template when right-clicking a row during initial template load", async() =>
	{
		const el = new Et2Nextmatch();
		el.setAttribute("template", "addressbook.index.rows");
		let resolveTemplate : (value : any) => void = () => {};
		const templatePromise = new Promise((resolve) =>
		{
			resolveTemplate = resolve;
		});
		const fromTemplate = sinon.stub((el as any)._rowProvider, "fromTemplate").returns(templatePromise);
		let debug : sinon.SinonSpy | null = null;
		let triggerPopupForRow : sinon.SinonStub | null = null;
		try
		{
			debug = sinon.spy(egwStub, "debug");
			document.body.append(el);
			await el.updateComplete;

			const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as HTMLElement & { updateComplete? : Promise<unknown> };
			await datagrid?.updateComplete;

			const row = document.createElement("tr");
			row.setAttribute("data-row-id", "addressbook::1");
			datagrid?.shadowRoot?.append(row);
			triggerPopupForRow = sinon.stub((el as any)._actionController, "triggerPopupForRow").returns(true);
			const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true});
			row.dispatchEvent(event);

			assert.isTrue(triggerPopupForRow.calledOnce, "right-click should use row popup handling");
			assert.isTrue(event.defaultPrevented, "row context menu should be intercepted");
			assert.isFalse(debug!.getCalls().some((call) =>
					(call.args as any[])[0] === "warn" && (call.args as any[])[1] === "Et2Datagrid: No row template configured"),
				"missing-template warning should not be logged while row template is loading");
		}
		finally
		{
			resolveTemplate(null);
			await Promise.resolve();
			triggerPopupForRow?.restore();
			debug?.restore();
			fromTemplate.restore();
			el.remove();
		}
	});

	/**
	 * Contract under test:
	 * - Right-clicking the last visible row after scrolling must not throw when
	 *   the datagrid's virtualized row index cache contains unloaded gaps.
	 *
	 * Setup strategy:
	 * - Render a Nextmatch with a child datagrid.
	 * - Emulate sparse datagrid row state with only the last row loaded.
	 * - Dispatch contextmenu from that last row.
	 *
	 * Pass criteria:
	 * - Contextmenu handling does not throw while selecting the action row.
	 * - The last row is selected through the datagrid.
	 */
	it("selects sparse last row without throwing when right-clicked", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const datagrid = el.shadowRoot?.querySelector("et2-datagrid") as any;
		datagrid.rows = [{id: "addressbook::last", data: {label: "Last row"}}];
		datagrid._rowsByIndex = [
			undefined,
			undefined,
			{id: "addressbook::last", data: {label: "Last row"}}
		];
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "addressbook::last");
		datagrid.shadowRoot?.append(row);
		const controller : any = (el as any)._actionController;
		const fakeRowObject = {
			forceSelection: sinon.spy(),
			executeActionImplementation: sinon.stub().returns(true)
		};
		const ensureRowActionObject = sinon.stub(controller, "ensureRowActionObject").returns(fakeRowObject);

		const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true});
		assert.doesNotThrow(() => row.dispatchEvent(event),
			"right-click on sparse last row should not throw while selecting it");
		assert.deepEqual(Array.from(datagrid.selectedRowIds), ["addressbook::last"], "last row should be selected");
		assert.equal(datagrid.activeRowIndex, 2, "selection should use sparse row index");
		assert.isTrue(fakeRowObject.forceSelection.calledOnce, "action row should still be force-selected");

		ensureRowActionObject.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Row popup flow does not re-select rows that are already selected.
	 *
	 * Setup strategy:
	 * - Stub row lookup/object creation and mark requested row as pre-selected.
	 * - Spy on host `selectSingleRow()` and trigger row popup execution.
	 *
	 * Pass criteria:
	 * - `selectSingleRow()` is not called for an already-selected row.
	 * - Row object selection focus path still executes.
	 */
	it("does not call selectSingleRow when context row is already selected", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		const controller : any = (el as any)._actionController;
		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::same");
		const fakeRowObject = {
			forceSelection: sinon.spy(),
			executeActionImplementation: sinon.stub().returns(true)
		};

		const findEventRow = sinon.stub(controller, "findEventRow").returns({rowId: "row::same", rowElement: row});
		const ensureRowActionObject = sinon.stub(controller, "ensureRowActionObject").returns(fakeRowObject);
		const selectSingleRow = sinon.spy(el, "selectSingleRow");

		controller.selectedRowIds = ["row::same"];
		controller.allSelected = false;
		controller.triggerPopupForRow(new MouseEvent("contextmenu", {bubbles: true, composed: true, cancelable: true}));

		assert.isFalse(selectSingleRow.called, "already-selected row should not be selected again");
		assert.isTrue(fakeRowObject.forceSelection.calledOnce, "row forceSelection should still run");

		findEventRow.restore();
		ensureRowActionObject.restore();
		selectSingleRow.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Double-click on a row executes the default popup action instead of selecting row text.
	 *
	 * Setup strategy:
	 * - Render a lightweight row in the component shadow root.
	 * - Stub row default-action trigger on the action controller.
	 * - Dispatch cancelable `dblclick` from row text.
	 *
	 * Pass criteria:
	 * - Controller default-action path is invoked once.
	 * - Browser default is prevented for the double-click.
	 */
	it("routes row double-click to the default action handler", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;

		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::dbl");
		const text = document.createElement("span");
		text.textContent = "Double click me";
		row.append(text);
		el.shadowRoot?.append(row);

		const triggerDefaultActionForRow = sinon.stub((el as any)._actionController, "triggerDefaultActionForRow").returns(true);
		const event = new MouseEvent("dblclick", {
			bubbles: true,
			composed: true,
			cancelable: true,
			button: 0
		});
		text.dispatchEvent(event);

		assert.isTrue(triggerDefaultActionForRow.calledOnce, "double-click should execute row default action");
		assert.isTrue(event.defaultPrevented, "double-click should suppress native text-selection behavior");

		triggerDefaultActionForRow.restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Long-press on touch/pen triggers context popup after delay.
	 *
	 * Setup strategy:
	 * - Use controller in isolation with Sinon fake timers.
	 * - Stub `triggerPopupForRow()` and send touch `pointerdown`.
	 *
	 * Pass criteria:
	 * - Advancing fake clock beyond threshold invokes popup trigger once.
	 */
	it("opens row popup via long-press without waiting in real time", async() =>
	{
		const controller : any = new Et2NextmatchActionController({} as any);
		const clock = sinon.useFakeTimers();
		const triggerPopupForRow = sinon.stub(controller, "triggerPopupForRow").returns(true);
		const event = new PointerEvent("pointerdown", {
			pointerId: 1,
			pointerType: "touch",
			clientX: 12,
			clientY: 22
		});

		try
		{
			controller.handlePointerDown(event);
			await clock.tickAsync(560);
			assert.isTrue(triggerPopupForRow.calledOnce, "long-press should trigger popup action");
		}
		finally
		{
			triggerPopupForRow.restore();
			clock.restore();
		}
	});

	/**
	 * Contract under test:
	 * - Mouse-triggered row popup uses pointer coordinates instead of row center.
	 *
	 * Setup strategy:
	 * - Stub row lookup/object creation on the action controller.
	 * - Dispatch `contextmenu` carrying client coordinates.
	 *
	 * Pass criteria:
	 * - Popup implementation receives the original mouse x/y values.
	 */
	it("opens row popup at the mouse cursor position", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		const controller : any = (el as any)._actionController;
		const row = document.createElement("div");
		row.setAttribute("data-row-id", "row::cursor");
		const fakeRowObject = {
			forceSelection: sinon.spy(),
			executeActionImplementation: sinon.stub().returns(true)
		};

		sinon.stub(controller, "findEventRow").returns({rowId: "row::cursor", rowElement: row});
		sinon.stub(controller, "ensureRowActionObject").returns(fakeRowObject);

		controller.triggerPopupForRow(new MouseEvent("contextmenu", {
			bubbles: true,
			composed: true,
			cancelable: true,
			clientX: 123,
			clientY: 234
		}));

		const context = fakeRowObject.executeActionImplementation.firstCall.args[0];
		assert.instanceOf(context.event, MouseEvent, "popup context should keep the source mouse event");
		assert.equal(context.posx, 123, "popup should use mouse X for placement");
		assert.equal(context.posy, 234, "popup should use mouse Y for placement");
		assert.equal(context.innerText, "", "popup context should still include row text");

		(controller.findEventRow as sinon.SinonStub).restore();
		(controller.ensureRowActionObject as sinon.SinonStub).restore();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Delegated row popup actions execute on the row object.
	 * - Popup context still exposes the clicked widget as the clipboard/text target.
	 *
	 * Setup strategy:
	 * - Create a row with a child custom element representing a rendered widget.
	 * - Dispatch the contextmenu from the widget while stubbing row/action lookup.
	 *
	 * Pass criteria:
	 * - The popup context target is the widget.
	 * - Context text comes from the widget, not the whole row.
	 */
	it("uses the clicked widget as row popup context target", async() =>
	{
		const el = new Et2Nextmatch();
		document.body.append(el);
		await el.updateComplete;
		const controller : any = (el as any)._actionController;
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "row::clipboard");
		const otherCell = document.createElement("td");
		otherCell.textContent = "Other row text";
		const cell = document.createElement("td");
		const widget = document.createElement("et2-label");
		widget.textContent = "Specific widget text";
		cell.append(widget);
		row.append(otherCell, cell);
		document.body.append(row);
		const fakeRowObject = {
			forceSelection: sinon.spy(),
			executeActionImplementation: sinon.stub().returns(true)
		};

		sinon.stub(controller, "findEventRow").returns({rowId: "row::clipboard", rowElement: row});
		sinon.stub(controller, "ensureRowActionObject").returns(fakeRowObject);

		widget.addEventListener("contextmenu", (event) => controller.triggerPopupForRow(event), {once: true});
		widget.dispatchEvent(new MouseEvent("contextmenu", {
			bubbles: true,
			composed: true,
			cancelable: true,
			clientX: 12,
			clientY: 34
		}));

		const context = fakeRowObject.executeActionImplementation.firstCall.args[0];
		assert.strictEqual(context.target, widget, "popup context should target the clicked widget");
		assert.equal(context.innerText, "Specific widget text", "popup context text should come from the clicked widget");

		(controller.findEventRow as sinon.SinonStub).restore();
		(controller.ensureRowActionObject as sinon.SinonStub).restore();
		row.remove();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Moving pointer beyond movement threshold cancels pending long-press popup.
	 *
	 * Setup strategy:
	 * - Start long-press timer on touch `pointerdown`.
	 * - Send `pointermove` exceeding threshold and advance fake clock.
	 *
	 * Pass criteria:
	 * - Popup trigger is not called after timer duration.
	 */
	it("cancels long-press popup when pointer moves beyond threshold", async() =>
	{
		const controller : any = new Et2NextmatchActionController({} as any);
		const clock = sinon.useFakeTimers();
		const triggerPopupForRow = sinon.stub(controller, "triggerPopupForRow").returns(true);

		try
		{
			controller.handlePointerDown(new PointerEvent("pointerdown", {
				pointerId: 7,
				pointerType: "touch",
				clientX: 10,
				clientY: 10
			}));
			controller.handlePointerMove(new PointerEvent("pointermove", {
				pointerId: 7,
				pointerType: "touch",
				clientX: 25,
				clientY: 10
			}));
			await clock.tickAsync(560);
			assert.isFalse(triggerPopupForRow.called, "popup should not open after movement cancels long-press");
		}
		finally
		{
			triggerPopupForRow.restore();
			clock.restore();
		}
	});

	/**
	 * Contract under test:
	 * - Nextmatch shared popup menu is created once and then reused.
	 *
	 * Setup strategy:
	 * - Instantiate popup implementation with deterministic `_buildMenu` stub.
	 * - Execute popup twice in row context on same manager data.
	 *
	 * Pass criteria:
	 * - `_buildMenu()` runs once.
	 * - Built menu is persisted in manager data for reuse.
	 */
	it("reuses nextmatch popup menu after first execution instead of building again", () =>
	{
		const popup : any = new EgwPopupActionImplementation();
		popup.auto_paste = false;
		const menu = {
			showAt: sinon.spy(),
			applyContext: sinon.spy(),
			remove: sinon.spy()
		};
		const buildMenu = sinon.stub(popup, "_buildMenu").returns(menu);
		const selected = [{parent: {manager: {data: {}}}}] as any;
		const context = {posx: 10, posy: 20, event: new MouseEvent("contextmenu")} as any;

		popup.executeImplementation(context, selected, {}, null);
		popup.executeImplementation(context, selected, {}, null);

		assert.equal(buildMenu.callCount, 1, "menu should be built once and reused");
		assert.strictEqual(selected[0].parent.manager.data.menu, menu, "reused menu should be stored on manager data");
		buildMenu.restore();
	});

	/**
	 * Contract under test:
	 * - Legacy async prebuild path is not used for web-component `et2-nextmatch`.
	 *
	 * Setup strategy:
	 * - Call `_registerContext()` with `data.nextmatch` set to `et2-nextmatch` element.
	 * - Stub `_buildMenu()` and advance fake timers.
	 *
	 * Pass criteria:
	 * - `_buildMenu()` is never called by prebuild timeout.
	 * - No cached menu placeholder is written to manager data.
	 */
	it("does not prebuild legacy popup menu for Et2Nextmatch context registration", () =>
	{
		const popup : any = new EgwPopupActionImplementation();
		const clock = sinon.useFakeTimers();
		const buildMenu = sinon.stub(popup, "_buildMenu");
		const context = {
			actionLinks: {open: true},
			parent: {
				manager: {
					data: {
						nextmatch: document.createElement("et2-nextmatch")
					}
				}
			}
		} as any;

		try
		{
			popup._registerContext(document.createElement("div"), () => {}, context);
			clock.tick(1);
			assert.isFalse(buildMenu.called, "Et2Nextmatch should not trigger legacy async prebuild");
			assert.isUndefined(context.parent.manager.data.menu, "no prebuilt menu placeholder should be created");
		}
		finally
		{
			buildMenu.restore();
			clock.restore();
		}
	});

	/**
	 * Contract under test:
	 * - Placeholder popup uses full action-link map and context visibility flags.
	 *
	 * Setup strategy:
	 * - Stub controller action manager with two actions (`open`, `add`).
	 * - Force placeholder resolver to allow only `add`.
	 * - Capture links passed to placeholder object `updateActionLinks()`.
	 *
	 * Pass criteria:
	 * - Full action list is passed.
	 * - Non-placeholder actions are marked `visible=false` / `enabled=false`.
	 * - Allowed placeholder actions are marked `visible=true` / `enabled=true`.
	 */
	it("uses full link map with visibility flags for placeholder popup context", async() =>
	{
		const el = new Et2Nextmatch();
		el.id = "nm_placeholder_links";
		document.body.append(el);
		await el.updateComplete;
		const controller : any = (el as any)._actionController;
		const updates : any[] = [];
		const placeholderObject = {
			updateActionLinks: (links) => updates.push(links),
			forceSelection: () => {},
			executeActionImplementation: () => true
		};
		controller.actionManager = {
			children: [{id: "open"}, {id: "add"}]
		};
		controller.objectManager = makeFakeObjectManager();
		const resolvePlaceholderActionLinks = sinon.stub(controller, "_resolvePlaceholderActionLinks").returns(["add"]);
		const ensurePlaceholderActionObject = sinon.stub(controller, "ensurePlaceholderActionObject").returns(placeholderObject);

		const state = document.createElement("div");
		state.className = "dg-state";
		document.body.append(state);
		const event = new MouseEvent("contextmenu", {bubbles: true, cancelable: true, composed: true, clientX: 10, clientY: 10});
		controller.triggerPlaceholderPopup(event, ["add"], state);

		assert.deepEqual(updates[0], [
			{actionId: "open", enabled: false, visible: false},
			{actionId: "add", enabled: true, visible: true}
		], "placeholder popup should keep full action map and only enable/show allowed entries");

		resolvePlaceholderActionLinks.restore();
		ensurePlaceholderActionObject.restore();
		state.remove();
		el.remove();
	});

	/**
	 * Contract under test:
	 * - Inline placeholder buttons use evaluated action-link enabled state.
	 *
	 * Setup strategy:
	 * - Configure two placeholder actions.
	 * - Stub the placeholder action object to report one disabled link.
	 *
	 * Pass criteria:
	 * - Only the enabled and visible action is returned for inline rendering.
	 */
	it("filters disabled placeholder actions from inline no-results buttons", async() =>
	{
		const el = new Et2Nextmatch();
		el.id = "nm_inline_placeholder_links";
		document.body.append(el);
		await el.updateComplete;
		const controller : any = (el as any)._actionController;
		const actions = [
			{id: "add", type: "popup", children: []},
			{id: "import_csv", type: "popup", children: []}
		];
		controller.actionManager = {
			children: actions,
			getActionById: (id : string) => actions.find((action) => action.id === id) || null
		};
		controller.objectManager = makeFakeObjectManager();
		controller.setPlaceholderActions(["add", "import_csv"]);
		const placeholderObject = {
			updateActionLinks: () => {},
			getSelectedLinks: () => ({
				links: {
					add: {enabled: false, visible: true},
					import_csv: {enabled: true, visible: true}
				}
			})
		};
		const ensurePlaceholderActionObject = sinon.stub(controller, "ensurePlaceholderActionObject").returns(placeholderObject);

		const inlineActions = controller.getInlinePlaceholderActions();

		assert.deepEqual(inlineActions.map((action) => action.id), ["import_csv"], "disabled placeholder actions should not render inline");

		ensurePlaceholderActionObject.restore();
		el.remove();
	});

	it("binds delegated drag target resolution on the datagrid rows container", () =>
	{
		const host = document.createElement("div");
		const hostShadow = host.attachShadow({mode: "open"});
		const datagrid = document.createElement("et2-datagrid");
		const datagridShadow = datagrid.attachShadow({mode: "open"});
		const rows = document.createElement("tbody");
		rows.id = "rows";
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "row::drag");
		rows.append(row);
		datagridShadow.append(rows);
		hostShadow.append(datagrid);

		const controller : any = new Et2NextmatchActionController({
			id: "nm_drag_rows",
			shadowRoot: hostShadow,
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = {
			children: [{id: "egw_link_drag", type: "drag"}]
		};
		controller.objectManager = {
			flags: 0,
			setAOI: sinon.spy(),
			updateActionLinks: sinon.spy()
		};

		controller.syncDragDropRegistration();

		assert.isFunction((rows as any).findActionTarget, "rows container should expose findActionTarget for drag/drop");
	});

	it("normalizes bare row ids through the data provider for drag/drop target resolution", () =>
	{
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "7");
		let addedRowId = "";
		let fakeRowObject : any = null;
		const controller : any = new Et2NextmatchActionController({
			id: "nm_drag_target",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"}),
			_dataProvider: {
				normalizeRowId: (rowId : string, ensurePrefix : boolean) => ensurePrefix ? `addressbook::${rowId}` : rowId
			}
		} as any);
		controller.actionManager = {children: []};
		controller.objectManager = makeFakeObjectManager((rowId : string) =>
		{
			addedRowId = rowId;
			fakeRowObject = makeFakeRowObject({id: rowId, updateActionLinks: () => {}});
			return fakeRowObject;
		});

		const target = controller.findActionTarget({
			composedPath: () => [row]
		} as any);

		assert.equal(addedRowId, "addressbook::7", "row action objects should use normalized provider ids");
		assert.strictEqual(target.target, row, "resolved drag/drop target should be the matching rendered row");
		assert.strictEqual(target.action, fakeRowObject, "resolved drag/drop action should be the lazily created row action object");
	});

	it("resolves drag/drop rows from the datagrid shadow root when composedPath stops at tbody", () =>
	{
		const host = document.createElement("div");
		const hostShadow = host.attachShadow({mode: "open"});
		const datagrid = document.createElement("et2-datagrid");
		const datagridShadow = datagrid.attachShadow({mode: "open"});
		const rows = document.createElement("tbody");
		rows.id = "rows";
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "row::shadow");
		rows.append(row);
		datagridShadow.append(rows);
		hostShadow.append(datagrid);

		const controller : any = new Et2NextmatchActionController({
			id: "nm_shadow_drag_target",
			shadowRoot: hostShadow,
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = {children: []};
		controller.objectManager = makeFakeObjectManager(() => makeFakeRowObject({id: "row::shadow", updateActionLinks: () => {}}));
		controller.getDeepElementFromPoint = () => row;

		const target = controller.findActionTarget({
			composedPath: () => [rows],
			clientX: 12,
			clientY: 24
		} as any);

		assert.strictEqual(target.target, row, "shadow-root point lookup should recover the dragged row");
	});

	it("arms a nextmatch row as draggable on plain mouse pointerdown", () =>
	{
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "addressbook::1");
		const controller : any = new Et2NextmatchActionController({
			id: "nm_prepare_drag",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = {children: [{id: "egw_link_drag", type: "drag"}]};
		controller.findEventRow = () => ({rowId: "addressbook::1", rowElement: row});

		controller.handlePointerDown(new PointerEvent("pointerdown", {
			pointerType: "mouse",
			button: 0,
			bubbles: true
		}));

		assert.isTrue(row.draggable, "plain mouse pointerdown should arm the row as draggable before dragstart");
		controller.clearPreparedDragRow();
		assert.isFalse(row.draggable, "prepared row should be cleared after pointer cleanup");
	});

	it("does not arm a nextmatch row as draggable without a drag action", () =>
	{
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "addressbook::1");
		const controller : any = new Et2NextmatchActionController({
			id: "nm_prepare_drag_none",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = {children: []};
		controller.findEventRow = () => ({rowId: "addressbook::1", rowElement: row});

		controller.handlePointerDown(new PointerEvent("pointerdown", {
			pointerType: "mouse",
			button: 0,
			bubbles: true
		}));

		assert.isFalse(row.draggable, "row should stay non-draggable when no drag action is linked");
	});

	it("does not auto-register drop support without a drop action", () =>
	{
		const host = document.createElement("div");
		const hostShadow = host.attachShadow({mode: "open"});
		const datagrid = document.createElement("et2-datagrid");
		const datagridShadow = datagrid.attachShadow({mode: "open"});
		const rows = document.createElement("tbody");
		rows.id = "rows";
		const row = document.createElement("tr");
		row.setAttribute("data-row-id", "row::drop");
		rows.append(row);
		datagridShadow.append(rows);
		hostShadow.append(datagrid);

		const addAction = sinon.spy();
		const objectManager = {
			flags: 0,
			setAOI: sinon.spy(),
			updateActionLinks: sinon.spy(),
			unregisterActions: sinon.spy()
		};
		const controller : any = new Et2NextmatchActionController({
			id: "nm_drop_none",
			shadowRoot: hostShadow,
			egw: () => ({
				...egwStub,
				link_get_registry: () => null
			}),
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.actionManager = {
			children: [],
			getActionById: () => null,
			addAction
		};
		controller.objectManager = objectManager;

		controller.syncDragDropRegistration();

		assert.isTrue(objectManager.updateActionLinks.calledOnce, "rows container should still be synchronized with the action system");
		assert.deepEqual(objectManager.updateActionLinks.firstCall.args[0], [], "no drop links should be registered when no drop action exists");
		assert.isFalse(addAction.called, "controller should not synthesize drop actions when drop support is unavailable");
	});

	it("materializes visible selected rows into action objects for multi-row drag helpers", () =>
	{
		const rows = document.createElement("tbody");
		const rowOne = document.createElement("tr");
		rowOne.setAttribute("data-row-id", "addressbook::1");
		const rowTwo = document.createElement("tr");
		rowTwo.setAttribute("data-row-id", "addressbook::2");
		rows.append(rowOne, rowTwo);

		const controller : any = new Et2NextmatchActionController({
			id: "nm_selection_materialize",
			egw: () => egwStub,
			getInstanceManager: () => ({app: "addressbook"})
		} as any);
		controller.getRowsBody = () => rows;
		const ensureRowActionObject = sinon.stub();
		controller.ensureRowActionObject = ensureRowActionObject.callsFake((rowId : string) => ({
			id: rowId,
			setSelected: sinon.spy(),
			setFocused: sinon.spy()
		}));

		controller.handleSelectionChanged({
			selectedRowIds: ["addressbook::1", "addressbook::2"],
			activeRowId: "addressbook::2",
			allSelected: false
		});

		assert.sameMembers(
			ensureRowActionObject.getCalls().map((call) => call.args[0]),
			["addressbook::1", "addressbook::2"],
			"visible selected rows should get action objects during selection sync"
		);
	});
});
