import {EgwAction} from "../../egw_action/EgwAction";
import {EgwActionManager} from "../../egw_action/EgwActionManager";
import {EgwActionObject} from "../../egw_action/EgwActionObject";
import type {EgwActionObjectInterface} from "../../egw_action/EgwActionObjectInterface";
import {EgwActionObjectManager} from "../../egw_action/EgwActionObjectManager";
import {egwSetBit} from "../../egw_action/egw_action_common";
import * as egwActionApi from "../../egw_action/egw_action";
import {
	EGW_AI_DRAG,
	EGW_AI_DRAG_ENTER,
	EGW_AI_DRAG_OUT,
	EGW_AO_EXEC_SELECTED,
	EGW_AO_FLAG_DEFAULT_FOCUS,
	EGW_AO_FLAG_IS_CONTAINER,
	EGW_AO_STATE_VISIBLE
} from "../../egw_action/egw_action_constants";
import {nm_action} from "../et2_extension_nextmatch_actions";
import type {Et2Nextmatch} from "./Et2Nextmatch";

/**
 * Minimal AOI base used by Et2Nextmatch.
 *
 * The legacy lowercase `egwActionObjectInterface` wrapper is deprecated. Nextmatch
 * only needs a small subset of the default behaviour, so the DnD helpers implement
 * the typed interface directly here instead of inheriting the compatibility class.
 */
abstract class Et2NextmatchBaseAOI implements EgwActionObjectInterface
{
	_state = EGW_AO_STATE_VISIBLE;
	handlers = {};
	stateChangeCallback : Function = null;
	stateChangeContext : any = null;
	reconnectActionsCallback : Function = null;
	reconnectActionsContext : any = null;

	setStateChangeCallback(_callback : Function, _context : any) : void
	{
		this.stateChangeCallback = _callback;
		this.stateChangeContext = _context;
	}

	setReconnectActionsCallback(_callback : Function, _context : any) : void
	{
		this.reconnectActionsCallback = _callback;
		this.reconnectActionsContext = _context;
	}

	reconnectActions() : void
	{
		this.reconnectActionsCallback?.call(this.reconnectActionsContext);
	}

	updateState(_stateBit : number, _set : boolean, _shiftState : boolean) : void
	{
		const newState = egwSetBit(this._state, _stateBit, _set);
		this._state = this.stateChangeCallback
		              ? this.stateChangeCallback.call(this.stateChangeContext, newState, _stateBit, _shiftState)
		              : newState;
	}

	getDOMNode() : HTMLElement | null
	{
		return this.doGetDOMNode();
	}

	setState(_state : number) : void
	{
		this._state = _state;
	}

	getState() : number
	{
		return this._state;
	}

	triggerEvent(_event : any, _data : any) : boolean
	{
		return this.doTriggerEvent(_event, _data);
	}

	makeVisible() : void
	{
		this.doMakeVisible();
	}

	protected doMakeVisible() : void
	{
	}

	protected abstract doGetDOMNode() : HTMLElement | null;

	protected abstract doTriggerEvent(_event : any, _data : any) : boolean;
}

/**
 * Container-level AOI that lets the existing action framework resolve drag/drop
 * targets against rendered datagrid rows instead of the rows wrapper element.
 */
class Et2NextmatchDragDropAOI extends Et2NextmatchBaseAOI
{
	private controller : Et2NextmatchActionController;
	private node : HTMLElement | null = null;
	public findActionTargetHandler : Et2NextmatchActionController;

	constructor(controller : Et2NextmatchActionController, node : HTMLElement | null = null)
	{
		super();
		this.controller = controller;
		this.findActionTargetHandler = controller;
		this.bindNode(node);
	}

	bindNode(node : HTMLElement | null)
	{
		if(this.node && (this.node as any).findActionTarget === this.controller.findActionTarget)
		{
			delete (this.node as any).findActionTarget;
		}
		this.node = node;
		if(this.node)
		{
			(this.node as any).findActionTarget = this.controller.findActionTarget;
		}
	}

	doGetDOMNode()
	{
		return this.node;
	}

	getWidget()
	{
		return this.node;
	}

	doTriggerEvent(egwEvent : number, data : any)
	{
		const domEvent = data?.event ?? data;
		const targetData = this.controller.findActionTarget(domEvent);
		if(!targetData.target)
		{
			if(egwEvent === EGW_AI_DRAG_OUT)
			{
				this.controller.clearDropHover();
			}
			return true;
		}
		if(egwEvent === EGW_AI_DRAG_ENTER)
		{
			this.controller.setDropHover(targetData.target);
		}
		else if(egwEvent === EGW_AI_DRAG_OUT)
		{
			this.controller.clearDropHover(targetData.target);
		}
		else if(egwEvent === EGW_AI_DRAG)
		{
			targetData.action?.setSelected(true);
		}
		return true;
	}
}

/**
 * Row AOI for rendered datagrid rows.
 *
 * Nextmatch rows do not need custom action-event handling at the AOI level, but
 * they do need a concrete DOM binding for the action framework.
 */
class Et2NextmatchRowAOI extends Et2NextmatchBaseAOI
{
	private node : HTMLElement;

	constructor(node : HTMLElement)
	{
		super();
		this.node = node;
	}

	protected doGetDOMNode() : HTMLElement
	{
		return this.node;
	}

	protected doTriggerEvent() : boolean
	{
		return false;
	}
}

/**
 * Bridges Et2Nextmatch row rendering and selection state into the
 * `egw_action` system so popup, drag, drop and placeholder actions can reuse the
 * existing action implementations.
 */
export class Et2NextmatchActionController
{
	private static readonly PLACEHOLDER_ACTION_OBJECT_ID = "__placeholder__";
	private _placeholderActionIds : string[] = ["add"];
	private host : Et2Nextmatch;
	private selectedRowIds : string[] = [];
	private allSelected : boolean = false;
	private actionManager : EgwAction | null = null;
	private objectManager : EgwActionObjectManager | null = null;
	private rowActionObjects : Map<string, EgwActionObject> = new Map();
	private longPressTimer : number | null = null;
	private longPressPointerId : number | null = null;
	private longPressStartX : number = 0;
	private longPressStartY : number = 0;
	private readonly longPressDelayMs : number = 550;
	private readonly longPressMoveThresholdPx : number = 8;
	private dragDropAOI : Et2NextmatchDragDropAOI | null = null;
	private dropHoverRow : HTMLElement | null = null;
	private preparedDragRow : HTMLElement | null = null;

	constructor(host : Et2Nextmatch)
	{
		this.host = host;
	}

	initActions(actions : EgwAction[] | { [id : string] : object })
	{
		const actionEntries = Array.isArray(actions) ? actions : Object.entries(actions || {});
		if(!actionEntries.length && !this.actionManager)
		{
			return;
		}
		if(!this.host.id)
		{
			this.host.egw().debug("warn", "Widget should have an ID if you want actions", this.host);
			return;
		}
		this.ensureActionManagers();
		if(!this.actionManager)
		{
			return;
		}
		this.actionManager.updateActions(actions, this.getAppName());
		const data = this.actionManager.data || {};
		this.actionManager.data = {
			...data,
			nextmatch: this.host,
			context: this.host.getInstanceManager()?.app_obj,
			widget: this.host
		};
		this.actionManager.setDefaultExecute((action, senders, target) =>
		{
			const ids = this.getSelection();
			if(!action.data)
			{
				action.data = {};
			}
			action.data.nextmatch = this.host;
			nm_action(action, senders, target, ids);
		});
		const selectAllAction = this.actionManager.getActionById?.("select_all");
		selectAllAction?.set_onExecute?.(() =>
		{
			this.host.selectAllRows?.();
		});
		this.syncDragDropRegistration();
	}

	getSelection() : { ids : string[]; all : boolean }
	{
		return {ids: [...this.selectedRowIds], all: this.allSelected};
	}

	clearRowActionObjects()
	{
		for(const rowObject of this.rowActionObjects.values())
		{
			try
			{
				rowObject.remove();
			}
			catch(e)
			{
			}
		}
		this.rowActionObjects.clear();
		this.selectedRowIds = [];
		this.allSelected = false;
	}

	handleSelectionChanged(detail : { selectedRowIds? : string[]; activeRowId? : string; allSelected? : boolean })
	{
		this.selectedRowIds = [...(detail?.selectedRowIds || [])];
		this.allSelected = !!detail?.allSelected;
		const selectedSet = new Set(this.selectedRowIds);
		const activeRowId = String(detail?.activeRowId || "");
		this.materializeVisibleSelectedRows(selectedSet, activeRowId);
		for(const [rowId, rowObject] of this.rowActionObjects.entries())
		{
			rowObject.setSelected(this.allSelected || selectedSet.has(rowId));
			rowObject.setFocused(rowId === activeRowId);
		}
	}

	/**
	 * Keep the action system's selected-object list aligned with rendered datagrid rows.
	 */
	private materializeVisibleSelectedRows(selectedSet : Set<string>, activeRowId : string)
	{
		const rowsBody = this.getRowsBody();
		if(!rowsBody)
		{
			return;
		}
		const rowIds = this.allSelected
		               ? Array.from(rowsBody.querySelectorAll("[data-row-id]"))
						   .map((row) => this.getActionRowId(row as HTMLElement))
						   .filter(Boolean) as string[]
		               : Array.from(selectedSet);
		for(const rowId of rowIds)
		{
			const rowElement = rowsBody.querySelector(`[data-row-id="${CSS.escape(rowId)}"]`) as HTMLElement | null;
			if(!rowElement)
			{
				continue;
			}
			const rowObject = this.ensureRowActionObject(rowId, rowElement);
			rowObject?.setSelected(this.allSelected || selectedSet.has(rowId));
			rowObject?.setFocused(rowId === activeRowId);
		}
	}

	triggerPopupForRow(contextEvent : Event) : boolean
	{
		const row = this.findEventRow(contextEvent);
		if(!row)
		{
			return false;
		}
		const rowObject = this.ensureRowActionObject(row.rowId, row.rowElement);
		if(!rowObject)
		{
			return false;
		}
		this._selectActionRow(row.rowId, rowObject);
		const rect = row.rowElement.getBoundingClientRect();
		const mouseEvent = contextEvent as MouseEvent;
		return  rowObject.executeActionImplementation({
			event: contextEvent,
			posx: typeof mouseEvent.clientX === "number" ? mouseEvent.clientX : rect.left + (rect.width / 2),
			posy: typeof mouseEvent.clientY === "number" ? mouseEvent.clientY : rect.top + (rect.height / 2),
			innerText: row.rowElement.textContent || ""
		}, "popup", EGW_AO_EXEC_SELECTED);
	}

	triggerDefaultActionForRow(contextEvent : Event) : boolean
	{
		const row = this.findEventRow(contextEvent);
		if(!row)
		{
			return false;
		}
		const rowObject = this.ensureRowActionObject(row.rowId, row.rowElement);
		if(!rowObject)
		{
			return false;
		}
		this._selectActionRow(row.rowId, rowObject);
		return rowObject.executeActionImplementation("default", "popup", EGW_AO_EXEC_SELECTED);
	}

	/**
	 * Open the Nextmatch action popup for empty-state placeholder interactions.
	 * Placeholder actions are filtered to existing action ids (including first-level children)
	 * and executed through the regular EGroupware action system.
	 */
	triggerPlaceholderPopup(contextEvent : Event, actionIds : string[] = [], anchorElement? : HTMLElement | null) : boolean
	{
		const target = anchorElement || (contextEvent.target as HTMLElement | null);
		if(!target)
		{
			return false;
		}
		this.ensureActionManagers();
		if(!this.objectManager)
		{
			return false;
		}
		const allowedLinks = this._resolvePlaceholderActionLinks(actionIds);
		if(!allowedLinks.length)
		{
			return false;
		}
		const placeholderObject = this.ensurePlaceholderActionObject(target);
		if(!placeholderObject)
		{
			return false;
		}
		placeholderObject.updateActionLinks(this._getPlaceholderContextLinks(allowedLinks));
		placeholderObject.forceSelection();
		const anchorRect = target.getBoundingClientRect();
		const mouseEvent = contextEvent as MouseEvent;
		const posx = typeof mouseEvent.clientX === "number" ? mouseEvent.clientX : anchorRect.left + (anchorRect.width / 2);
		const posy = typeof mouseEvent.clientY === "number" ? mouseEvent.clientY : anchorRect.top + (anchorRect.height / 2);
		return placeholderObject.executeActionImplementation({
			event: contextEvent,
			posx,
			posy,
			innerText: target.textContent || ""
		}, "popup", EGW_AO_EXEC_SELECTED);
	}

	handlePointerDown(event : PointerEvent)
	{
		if(event.pointerType === "mouse")
		{
			this.prepareDragRow(event);
		}
		if(event.pointerType !== "touch" && event.pointerType !== "pen")
		{
			return;
		}
		this.cancelLongPress();
		this.longPressPointerId = event.pointerId;
		this.longPressStartX = event.clientX;
		this.longPressStartY = event.clientY;
		this.longPressTimer = window.setTimeout(() =>
		{
			this.longPressTimer = null;
			if(this.triggerPopupForRow(event))
			{
				event.preventDefault();
			}
		}, this.longPressDelayMs);
	}

	handlePointerMove(event : PointerEvent)
	{
		if(this.longPressPointerId === null || event.pointerId !== this.longPressPointerId)
		{
			return;
		}
		if(
			Math.abs(event.clientX - this.longPressStartX) > this.longPressMoveThresholdPx ||
			Math.abs(event.clientY - this.longPressStartY) > this.longPressMoveThresholdPx
		)
		{
			this.cancelLongPress();
		}
	}

	cancelLongPress = () =>
	{
		if(this.longPressTimer !== null)
		{
			window.clearTimeout(this.longPressTimer);
			this.longPressTimer = null;
		}
		this.longPressPointerId = null;
	};

	/**
	 * Native drag is armed on pointerdown and must be cleared again once the
	 * gesture completes or is canceled.
	 */
	clearPreparedDragRow = () =>
	{
		if(this.preparedDragRow)
		{
			this.preparedDragRow.draggable = false;
			this.preparedDragRow = null;
		}
	};

	/**
	 * Set placeholder action ids from legacy CSV string or array value.
	 * Empty input falls back to the default `["add"]`.
	 */
	setPlaceholderActions(actionIds : string[] | string | null | undefined)
	{
		let normalized : string[] = [];
		if(Array.isArray(actionIds))
		{
			normalized = actionIds.map((id) => String(id || "").trim()).filter(Boolean);
		}
		else if(typeof actionIds === "string")
		{
			normalized = actionIds.split(",").map((id) => id.trim()).filter(Boolean);
		}
		this._placeholderActionIds = normalized.length ? normalized : ["add"];
	}

	/**
	 * Get currently configured placeholder action ids, including controller defaults.
	 */
	getPlaceholderActionIds() : string[]
	{
		return [...this._placeholderActionIds];
	}

	/**
	 * Resolve inline placeholder actions from configured ids.
	 * If `add` has children, return the children entries instead of the add parent.
	 */
	getInlinePlaceholderActions() : EgwAction[]
	{
		this.ensureActionManagers();
		if(!this.actionManager)
		{
			return [];
		}
		const requested = new Set(this._placeholderActionIds.map((id) => String(id || "").trim()).filter(Boolean));
		if(!requested.size)
		{
			return [];
		}
		const resolved : EgwAction[] = [];
		for(const actionId of requested)
		{
			const action = this.actionManager.getActionById?.(actionId);
			if(!action)
			{
				continue;
			}
			if(actionId === "add" && Array.isArray(action.children) && action.children.length > 0)
			{
				for(const child of action.children)
				{
					if(!child?.id)
					{
						continue;
					}
					resolved.push(child);
				}
				continue;
			}
			resolved.push(action);
		}
		const seen = new Set<string>();
		return resolved.filter((action) =>
		{
			const actionId = String(action?.id || "");
			if(!actionId || seen.has(actionId))
			{
				return false;
			}
			seen.add(actionId);
			return true;
		});
	}

	/**
	 * Execute one placeholder action immediately using Nextmatch default action handling.
	 */
	executePlaceholderAction(actionId : string, anchorElement : HTMLElement | null = null) : boolean
	{
		this.ensureActionManagers();
		if(!this.actionManager || !actionId)
		{
			return false;
		}
		const action = this.actionManager.getActionById?.(actionId);
		if(!action)
		{
			return false;
		}
		const placeholderObject = this.ensurePlaceholderActionObject(anchorElement || this.host);
		if(!placeholderObject)
		{
			return false;
		}
		try
		{
			nm_action(action, [placeholderObject], placeholderObject, {ids: [], all: false});
			return true;
		}
		catch(e)
		{
			return false;
		}
	}

	destroy()
	{
		this.clearPreparedDragRow();
		this.cancelLongPress();
		this.clearDropHover();
		this.dragDropAOI?.bindNode(null);
		this.clearRowActionObjects();
	}

	syncDragDropRegistration()
	{
		this.ensureActionManagers();
		this.initLinkDragDropActions();
		const rowsBody = this.getRowsBody();
		if(!this.objectManager)
		{
			this.dragDropAOI?.bindNode(null);
			return;
		}
		if(!rowsBody)
		{
			this.dragDropAOI?.bindNode(null);
			return;
		}
		if(!this.dragDropAOI)
		{
			this.dragDropAOI = new Et2NextmatchDragDropAOI(this, rowsBody);
		}
		else
		{
			this.dragDropAOI.bindNode(rowsBody);
		}
		this.objectManager.unregisterActions?.();
		this.objectManager.setAOI(this.dragDropAOI);
		this.objectManager.updateActionLinks(this.getActionLinks());
		for(const rowObject of this.rowActionObjects.values())
		{
			if(rowObject.id === Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID)
			{
				continue;
			}
			rowObject.updateActionLinks(this.getActionLinks());
		}
		this.cleanupDetachedRowActionObjects();
	}

	customizeRowElement(rowElement : HTMLElement)
	{
		if(!rowElement)
		{
			return;
		}
		const rowId = this.getActionRowId(rowElement);
		if(!rowId || !this.rowActionObjects.has(rowId))
		{
			return;
		}
		this.ensureRowActionObject(rowId, rowElement);
	}

	findActionTarget = (event : Event) : { target : HTMLElement | null; action : EgwActionObject | null } =>
	{
		const row = this.findEventRow(event);
		if(!row)
		{
			return {target: null, action: null};
		}
		const rowObject = this.ensureRowActionObject(row.rowId, row.rowElement);
		return {target: row.rowElement, action: rowObject};
	};

	setDropHover(rowElement : HTMLElement | null)
	{
		if(this.dropHoverRow && this.dropHoverRow !== rowElement)
		{
			this.dropHoverRow.classList.remove("draggedOver", "drop-hover");
		}
		this.dropHoverRow = rowElement;
		if(rowElement)
		{
			rowElement.classList.add("draggedOver", "drop-hover");
		}
	}

	clearDropHover(rowElement? : HTMLElement | null)
	{
		if(rowElement)
		{
			rowElement.classList.remove("draggedOver", "drop-hover");
			if(this.dropHoverRow === rowElement)
			{
				this.dropHoverRow = null;
			}
			return;
		}
		if(this.dropHoverRow)
		{
			this.dropHoverRow.classList.remove("draggedOver", "drop-hover");
			this.dropHoverRow = null;
		}
	}

	private ensureActionManagers()
	{
		const appName = this.getAppName() || this.host.egw().appName;
		const uid = this.host.id || this.host.getInstanceManager()?.uniqueId || this.host.egw().uid?.();
		if(!uid)
		{
			return;
		}
		const actionApi = window as any;
		const {getActionManager, getObjectManager} = resolveActionApiGetters(egwActionApi, actionApi);
		if(!this.actionManager)
		{
			let appActionManager = null;
			if(typeof getActionManager === "function")
			{
				try
				{
					appActionManager = getActionManager(appName, true, 1) || getActionManager(undefined, true, 1);
				}
				catch(e)
				{
				}
			}
			if(!appActionManager)
			{
				const localGlobalActionManager = new EgwActionManager();
				try
				{
					appActionManager = localGlobalActionManager.addAction("actionManager", uid);
				}
				catch(e)
				{
					appActionManager = localGlobalActionManager;
				}
			}
			try
			{
				this.actionManager = appActionManager?.getActionById?.(uid, 1) || appActionManager?.addAction?.("actionManager", uid) || appActionManager;
			}
			catch(e)
			{
				this.actionManager = appActionManager;
			}
		}
		if(!this.objectManager)
		{
			let appObjectManager = null;
			if(typeof getObjectManager === "function")
			{
				try
				{
					appObjectManager = getObjectManager(appName, true, 1) || getObjectManager(undefined, true, 1);
				}
				catch(e)
				{
				}
			}
			this.objectManager = appObjectManager?.addObject
				? appObjectManager.addObject(new EgwActionObjectManager(uid, this.actionManager))
				: new EgwActionObjectManager(uid, this.actionManager);
			if(!this.objectManager)
			{
				this.objectManager = new EgwActionObjectManager(uid, this.actionManager);
			}
			this.objectManager.flags |= EGW_AO_FLAG_DEFAULT_FOCUS | EGW_AO_FLAG_IS_CONTAINER;
		}
	}

	private ensureRowActionObject(rowId : string, rowElement : HTMLElement) : EgwActionObject | null
	{
		if(!rowId || !rowElement)
		{
			return null;
		}
		this.ensureActionManagers();
		if(!this.objectManager)
		{
			return null;
		}
		let rowObject = this.rowActionObjects.get(rowId) || null;
		if(!rowObject)
		{
			const aoi = this.createRowActionObjectInterface(rowElement);
			rowObject = this.objectManager.addObject(rowId, aoi);
			if(!rowObject)
			{
				return null;
			}
			rowObject._context = rowElement;
			rowObject.findActionTargetHandler = this.objectManager;
			rowObject.setSelected = (selected : boolean) =>
			{
				(rowObject as any)._actionSelected = !!selected;
				rowObject.parent?.updateSelectedChildren(rowObject, !!selected);
			};
			rowObject.getSelected = () => !!(rowObject as any)._actionSelected;
			rowObject.setFocused = (focused : boolean) =>
			{
				(rowObject as any)._actionFocused = !!focused;
				rowObject.parent?.updateFocusedChild?.(rowObject, !!focused);
			};
			rowObject.getFocused = () => !!(rowObject as any)._actionFocused;
			rowObject.updateActionLinks(this.getActionLinks());
			this.rowActionObjects.set(rowId, rowObject);
		}
		else
		{
			const currentNode = rowObject.iface?.getDOMNode?.();
			if(currentNode !== rowElement)
			{
				rowObject.unregisterActions?.();
				rowObject.setAOI(this.createRowActionObjectInterface(rowElement));
			}
			rowObject._context = rowElement;
			rowObject.findActionTargetHandler = this.objectManager;
			rowObject.updateActionLinks(this.getActionLinks());
		}
		return rowObject;
	}

	private _selectActionRow(rowId : string, rowObject : EgwActionObject)
	{
		rowObject.forceSelection();
		const rowAlreadySelected = this.allSelected || this.selectedRowIds.includes(rowId);
		if(rowAlreadySelected)
		{
			return;
		}
		this.host.selectSingleRow?.(rowId);
		this.allSelected = false;
		this.selectedRowIds = [rowId];
	}

	private getActionLinks() : string[]
	{
		const links : string[] = [];
		const children = this.actionManager?.children || [];
		for(const child of children)
		{
			if(child?.id)
			{
				links.push(child.id);
			}
		}
		return links;
	}

	private hasDragActions() : boolean
	{
		return (this.actionManager?.children || []).some((child) => child?.type === "drag");
	}

	private getRowsBody() : HTMLElement | null
	{
		return this.host.shadowRoot?.querySelector("et2-datagrid")?.shadowRoot?.getElementById("rows") as HTMLElement | null;
	}

	private cleanupDetachedRowActionObjects()
	{
		for(const [rowId, rowObject] of this.rowActionObjects.entries())
		{
			if(rowId === Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID)
			{
				continue;
			}
			const rowElement = rowObject._context as HTMLElement | null;
			if(rowElement?.isConnected)
			{
				continue;
			}
			rowObject.remove?.();
			this.rowActionObjects.delete(rowId);
		}
	}

	private createRowActionObjectInterface(rowElement : HTMLElement)
	{
		return new Et2NextmatchRowAOI(rowElement);
	}

	/**
	 * Resolve the app name used by action-manager registration and legacy callbacks.
	 */
	private getAppName() : string
	{
		return typeof (this.host as any)._getAppName === "function"
		       ? String((this.host as any)._getAppName() || "")
		       : String(this.host.getInstanceManager?.()?.app || this.host.egw()?.app_name?.() || "");
	}

	/**
	 * Arm the currently pointed row as the native drag source without disturbing
	 * selection, keyboard navigation or context menu gestures.
	 */
	private prepareDragRow(event : PointerEvent)
	{
		this.clearPreparedDragRow();
		if(event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)
		{
			return;
		}
		if(!this.hasDragActions())
		{
			return;
		}
		const target = event.target as HTMLElement | null;
		if(target?.closest?.("a,button,input,select,textarea,label,[contenteditable=''],[contenteditable='true']"))
		{
			return;
		}
		const row = this.findEventRow(event);
		if(!row?.rowElement)
		{
			return;
		}
		row.rowElement.draggable = true;
		this.preparedDragRow = row.rowElement;
	}

	private initLinkDragDropActions()
	{
		const mgr = this.actionManager;
		if(!mgr?.addAction)
		{
			return;
		}
		let dropAction = mgr.getActionById?.("egw_link_drop");
		let dragAction = mgr.getActionById?.("egw_link_drag");
		let dropCancel = mgr.getActionById?.("egw_cancel_drop");
		const dataProvider = (this.host as any)._dataProvider;
		const dataStorePrefix = dataProvider?.getDataStorePrefix?.() || this.host.getInstanceManager()?.app || this.host.egw().appName;
		if(!this.host.egw().link_get_registry?.(dataStorePrefix, "query") ||
			this.host.egw().link_get_registry?.(dataStorePrefix, "title"))
		{
			return;
		}
		if(!dropCancel)
		{
			dropCancel = mgr.addAction("drop", "egw_cancel_drop", this.host.egw().lang("Cancel"), this.host.egw().image("cancel"), function() {}, true);
			dropCancel?.set_group?.("99");
			if(Array.isArray(dropCancel?.acceptedTypes))
			{
				dropCancel.acceptedTypes = dropCancel.acceptedTypes.concat(Object.keys(this.host.egw().user?.("apps") || {}).concat(["link", "file"]));
			}
		}
		if(!dropAction)
		{
			dropAction = mgr.addAction("drop", "egw_link_drop", this.host.egw().lang("Create link"), this.host.egw().image("link"), (action, source, dropped) =>
			{
				const links = [];
				for(let i = 0; i < source.length; i++)
				{
					if(!source[i]?.id)
					{
						continue;
					}
					const id = source[i].id.split("::");
					links.push({app: id[0] === "filemanager" ? "link" : id[0], id: id[1]});
				}
				if(!links.length || !dropped?.id)
				{
					return;
				}
				this.host.egw().json(
					"EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link",
					dropped.id.split("::").concat([links]),
					(result) =>
					{
						if(!result)
						{
							return;
						}
						for(const child of (this.objectManager as any)?.selectedChildren || [])
						{
							this.host.refresh(child.id, "update");
						}
						this.host.egw().message?.("Linked");
						this.host.refresh(dropped.id, "update");
					},
					this,
					true,
					this
				).sendRequest();
			}, true);
		}
		if(Array.isArray(dropAction?.acceptedTypes) && !dropAction.acceptedTypes.includes("link"))
		{
			dropAction.acceptedTypes.push("link");
		}
		if(!dragAction)
		{
			dragAction = mgr.addAction("drag", "egw_link_drag", this.host.egw().lang("link"), "link", () => null, true);
		}
		dragAction?.set_dragType?.("link");
	}

	/**
	 * Keep only placeholder action ids that are available in the current action tree.
	 */
	private _resolvePlaceholderActionLinks(actionIds : string[]) : string[]
	{
		const requested = new Set((actionIds || []).map((id) => String(id || "").trim()).filter(Boolean));
		if(!requested.size)
		{
			return [];
		}
		const available : string[] = [];
		for(const action of this.actionManager?.children || [])
		{
			if(action?.id && requested.has(action.id))
			{
				available.push(action.id);
			}
			for(const childId of Object.keys(action?.children || {}))
			{
				if(requested.has(childId))
				{
					available.push(childId);
				}
			}
		}
		return Array.from(new Set(available));
	}

	/**
	 * Build a full popup-link map for placeholder context:
	 * - all known popup actions stay in the menu tree
	 * - only allowed placeholder actions are visible/enabled
	 */
	private _getPlaceholderContextLinks(allowedLinks : string[]) : {
		actionId : string;
		enabled : boolean;
		visible : boolean
	}[]
	{
		const allowed = new Set((allowedLinks || []).map((id) => String(id || "").trim()).filter(Boolean));
		return this.getActionLinks().map((actionId) => ({
			actionId,
			enabled: allowed.has(actionId),
			visible: allowed.has(actionId)
		}));
	}

	private ensurePlaceholderActionObject(anchorElement : HTMLElement) : EgwActionObject | null
	{
		if(!this.objectManager)
		{
			return null;
		}
		let placeholderObject = this.rowActionObjects.get(Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID) || null;
		if(!placeholderObject)
		{
			placeholderObject = this.objectManager.addObject(
				Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID,
				this.createRowActionObjectInterface(anchorElement)
			);
			if(!placeholderObject)
			{
				return null;
			}
			this.rowActionObjects.set(Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID, placeholderObject);
		}
		else if(placeholderObject.iface?.getDOMNode?.() !== anchorElement)
		{
			placeholderObject.setAOI(this.createRowActionObjectInterface(anchorElement));
		}
		return placeholderObject;
	}

	/**
	 * Resolve the rendered row for native drag/drop and popup events.
	 */
	private findEventRow(event : Event) : { rowId : string; rowElement : HTMLElement } | null
	{
		const path = event.composedPath?.() || [];
		for(const target of path)
		{
			if(!(target instanceof HTMLElement))
			{
				continue;
			}
			const row = target.closest?.("[data-row-id]") as HTMLElement | null;
			if(!row)
			{
				continue;
			}
			const rowId = this.getActionRowId(row);
			if(rowId)
			{
				return {rowId, rowElement: row};
			}
		}
		const rowFromPoint = this.findRowElementFromPoint(event as DragEvent);
		const rowId = rowFromPoint ? this.getActionRowId(rowFromPoint) : "";
		if(rowFromPoint && rowId)
		{
			return {rowId, rowElement: rowFromPoint};
		}
		return null;
	}

	/**
	 * Drag events crossing the shadow boundary may only surface the rows container.
	 * Fall back to point-based lookup inside the datagrid shadow root in that case.
	 */
	private findRowElementFromPoint(event : DragEvent) : HTMLElement | null
	{
		if(typeof event.clientX !== "number" || typeof event.clientY !== "number")
		{
			return null;
		}
		const rowsBody = this.getRowsBody();
		if(!rowsBody)
		{
			return null;
		}
		const deepTarget = this.getDeepElementFromPoint(rowsBody.getRootNode() as Document | ShadowRoot, event.clientX, event.clientY);
		const deepRow = deepTarget?.closest?.("[data-row-id]") as HTMLElement | null;
		if(deepRow)
		{
			return deepRow;
		}
		const rows = Array.from(rowsBody.querySelectorAll("[data-row-id]")) as HTMLElement[];
		for(const row of rows)
		{
			const rect = row.getBoundingClientRect();
			if(
				event.clientX >= rect.left && event.clientX <= rect.right &&
				event.clientY >= rect.top && event.clientY <= rect.bottom
			)
			{
				return row;
			}
		}
		return null;
	}

	/**
	 * Recursively resolve the deepest element reachable via elementFromPoint(),
	 * including nested shadow roots.
	 */
	private getDeepElementFromPoint(root : Document | ShadowRoot, x : number, y : number) : HTMLElement | null
	{
		const elementFromPoint = typeof (root as any).elementFromPoint === "function"
		                         ? (root as any).elementFromPoint.bind(root)
		                         : document.elementFromPoint.bind(document);
		let element = elementFromPoint(x, y) as HTMLElement | null;
		while(element?.shadowRoot)
		{
			const nested = element.shadowRoot.elementFromPoint?.(x, y) as HTMLElement | null;
			if(!nested || nested === element)
			{
				break;
			}
			element = nested;
		}
		return element;
	}

	/**
	 * Normalize the datagrid row id back to the provider/action-system id.
	 */
	private getActionRowId(rowElement : HTMLElement) : string
	{
		const rawRowId = String(rowElement.getAttribute("data-row-id") || "");
		if(!rawRowId)
		{
			return "";
		}
		if(rawRowId.includes("::"))
		{
			return rawRowId;
		}
		return (this.host as any)._dataProvider?.normalizeRowId?.(rawRowId, true) || rawRowId;
	}
}

/**
 * Resolve action/object-manager getters from either the module API or the legacy
 * window globals.
 *
 * The module exports are preferred, while the global functions remain as a
 * compatibility fallback for mixed legacy/modern loading contexts.
 */
export function resolveActionApiGetters(moduleApi : any, windowApi : any) : {
	getActionManager : Function | null;
	getObjectManager : Function | null;
}
{
	return {
		getActionManager: typeof moduleApi?.egw_getActionManager === "function"
			? moduleApi.egw_getActionManager
			: (typeof windowApi?.egw_getActionManager === "function" ? windowApi.egw_getActionManager : null),
		getObjectManager: typeof moduleApi?.egw_getObjectManager === "function"
			? moduleApi.egw_getObjectManager
			: (typeof windowApi?.egw_getObjectManager === "function" ? windowApi.egw_getObjectManager : null)
	};
}
