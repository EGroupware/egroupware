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

	/**
	 * Base AOI visibility hook; concrete rows are already visible when rendered.
	 */
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
	findActionTargetHandler : Et2NextmatchActionController;

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

	/**
	 * Return the DOM row owned by this action object interface.
	 */
	protected doGetDOMNode() : HTMLElement
	{
		return this.node;
	}

	/**
	 * Row AOIs delegate action execution to their owning action object.
	 */
	protected doTriggerEvent() : boolean
	{
		return false;
	}
}

/**
 * Bridges Et2Nextmatch row rendering and selection state into the
 * `egw_action` system so popup, drag, drop and placeholder actions can reuse the
 * existing action implementations.
 *
 * The controller owns action-manager registration, row action-object materialization,
 * selection mirroring, context-menu execution, touch long-press handling and native
 * drag/drop registration. Et2Nextmatch remains responsible for data loading and
 * rendering; this class only adapts rendered rows to the legacy action framework.
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
	private pendingActionSubmitValue : Record<string, any> | null = null;

	constructor(host : Et2Nextmatch)
	{
		this.host = host;
	}

	/**
	 * Register or update actions supplied by the server/template.
	 *
	 * Actions are attached to this Nextmatch's action manager and annotated with
	 * `data.nextmatch` so existing app handlers can find the owning widget even
	 * when invoked through popup or drag/drop action implementations.
	 */
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
		this.annotateActionsWithNextmatch(this.actionManager.children || []);
		const data = this.actionManager.data || {};
		this.actionManager.data = {
			...data,
			nextmatch: this.host,
			context: this.host.getInstanceManager()?.app_obj,
			widget: this.host
		};
		this.actionManager.setDefaultExecute((action, senders, target) =>
		{
			this.executeNextmatchAction(action, senders, target);
		});
		const selectAllAction = this.actionManager.getActionById?.("select_all");
		selectAllAction?.set_onExecute?.(() =>
		{
			this.host.selectAllRows?.();
		});
		this.syncDragDropRegistration();
	}

	/**
	 * Return a snapshot of the rows currently selected in the datagrid.
	 *
	 * Row ids are datagrid row ids. Callers that submit to the server should use
	 * the normalized provider ids produced by executeNextmatchAction().
	 */
	getSelection() : { ids : string[]; all : boolean }
	{
		return {ids: [...this.selectedRowIds], all: this.allSelected};
	}

	/**
	 * Return the pending submit payload produced by the last submit action.
	 *
	 * Et2Nextmatch includes this value in its normal eTemplate submit value so
	 * server-side nextmatch actions receive the same fields as legacy Nextmatch.
	 */
	getActionSubmitValue() : Record<string, any> | null
	{
		return this.pendingActionSubmitValue ? {...this.pendingActionSubmitValue} : null;
	}

	/**
	 * Execute a registered action programmatically.
	 *
	 * Used by app code that already knows the action id and selection and does
	 * not need a context-menu action object. `options.nmAction` can temporarily
	 * force the legacy nextmatch action mode (`submit`, `popup`, etc.) for this
	 * execution without permanently changing the action definition.
	 */
	executeAction(
		actionId : string,
		selection : { ids? : string[]; all? : boolean } = this.getSelection(),
		options : { nmAction? : string } = {}
	) : boolean
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
		if(!action.data)
		{
			action.data = {};
		}
		const hadNmAction = Object.prototype.hasOwnProperty.call(action.data, "nm_action");
		const previousNmAction = action.data.nm_action;
		if(options.nmAction)
		{
			action.data.nm_action = options.nmAction;
		}
		try
		{
			this.executeNextmatchAction(action, [], null, {ids: selection.ids || [], all: selection.all === true});
			return true;
		}
		finally
		{
			if(options.nmAction && !hadNmAction)
			{
				delete action.data.nm_action;
			}
			else if(options.nmAction)
			{
				action.data.nm_action = previousNmAction;
			}
		}
	}

	/**
	 * Remove all row action objects and reset mirrored selection state.
	 */
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

	/**
	 * Mirror datagrid selection changes into the action framework.
	 *
	 * Only visible selected rows are materialized as action objects. For
	 * select-all, off-screen rows are represented later when actions normalize
	 * the selection and fetch all ids as needed.
	 */
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
		const rowsBodies = this.getRowsBodies();
		if(!rowsBodies.length)
		{
			return;
		}
		const rowIds = this.allSelected
		               ? rowsBodies.flatMap((rowsBody) => Array.from(rowsBody.querySelectorAll("[data-row-id]")))
						   .map((row) => this.getActionRowId(row as HTMLElement))
						   .filter(Boolean) as string[]
		               : Array.from(selectedSet);
		for(const rowId of rowIds)
		{
			const rowElement = this.findRenderedRowByActionId(rowId, rowsBodies);
			if(!rowElement)
			{
				continue;
			}
			const rowObject = this.ensureRowActionObject(rowId, rowElement);
			rowObject?.setSelected(this.allSelected || selectedSet.has(rowId));
			rowObject?.setFocused(rowId === activeRowId);
		}
	}

	/**
	 * Show the regular row action popup for a context-menu, keyboard or touch event.
	 */
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
		const target = this.findContextTarget(contextEvent, row.rowElement);
		return  rowObject.executeActionImplementation({
			event: contextEvent,
			posx: typeof mouseEvent.clientX === "number" ? mouseEvent.clientX : rect.left + (rect.width / 2),
			posy: typeof mouseEvent.clientY === "number" ? mouseEvent.clientY : rect.top + (rect.height / 2),
			target,
			innerText: target?.textContent || row.rowElement.textContent || ""
		}, "popup", EGW_AO_EXEC_SELECTED);
	}

	/**
	 * Execute the default row action, normally from double-click.
	 */
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

	/**
	 * Start touch/pen long-press detection and arm native mouse drag sources.
	 */
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

	/**
	 * Cancel a pending long-press once the pointer moves beyond the threshold.
	 */
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

	/**
	 * Cancel any pending touch/pen long-press popup.
	 */
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
		const uniqueActions = resolved.filter((action) =>
		{
			const actionId = String(action?.id || "");
			if(!actionId || seen.has(actionId))
			{
				return false;
			}
			seen.add(actionId);
			return true;
		});
		const enabledActionIds = this._enabledPlaceholderActionIds(uniqueActions.map((action) => action.id));
		return uniqueActions.filter((action) => enabledActionIds.has(action.id));
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
		if(!this._enabledPlaceholderActionIds([action.id], anchorElement || this.host).has(action.id))
		{
			return false;
		}
		if(!action.data)
		{
			action.data = {};
		}
		action.data.nextmatch = this.host;
		const placeholderObject = this.ensurePlaceholderActionObject(anchorElement || this.host);
		if(!placeholderObject)
		{
			return false;
		}
		try
		{
			this.executeNextmatchAction(action, [placeholderObject], placeholderObject, {ids: [], all: false});
			return true;
		}
		catch(e)
		{
			return false;
		}
	}

	/**
	 * Release action objects, transient drag state and action-framework bindings.
	 */
	destroy()
	{
		this.clearPreparedDragRow();
		this.cancelLongPress();
		this.clearDropHover();
		this.dragDropAOI?.bindNode(null);
		this.clearRowActionObjects();
	}

	/**
	 * Register the rendered datagrid rows container with the action framework.
	 *
	 * This is called after render and when action links change so drag/drop
	 * handlers can resolve real row elements instead of the virtualized wrapper.
	 */
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

	/**
	 * Rebind an existing action object when a virtualized row element is recycled.
	 */
	customizeRowElement(rowElement : HTMLElement)
	{
		this.cleanupDetachedRowActionObjects();
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

	/**
	 * Resolve the action target row for drag/drop events.
	 */
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

	/**
	 * Mark a row as the current drag/drop hover target.
	 */
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

	/**
	 * Clear drag/drop hover classes from one row or the current hover row.
	 */
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

	/**
	 * Lazily create the action and object managers used by legacy actions.
	 */
	private ensureActionManagers()
	{
		const appName = this.getAppName() || this.host.egw().app_name?.();
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

	/**
	 * Materialize or update the action object bound to one rendered row.
	 */
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

	/**
	 * Mirror a context-action row into both datagrid and action object selection.
	 */
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

	/**
	 * Return top-level action ids currently available to row objects.
	 */
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

	/**
	 * Execute Nextmatch action targets locally for the web component.
	 *
	 * This is the central bridge from `egw_action` to Nextmatch behavior. It
	 * normalizes selected row ids, expands legacy URL placeholders, dispatches
	 * location/popup/long-task/open-popup modes and prepares submit payloads for
	 * the eTemplate instance manager.
	 */
	private executeNextmatchAction(
		action : EgwAction,
		senders : EgwActionObject[] = [],
		target : any = null,
		selection : { ids : string[]; all : boolean } = this.getSelection()
	) : boolean | void
	{
		if((action as any).checkbox && (!action.data || typeof action.data.nm_action === "undefined"))
		{
			return;
		}
		if(!action.data)
		{
			action.data = {};
		}
		action.data.nextmatch = this.host;
		if(typeof action.data.nm_action === "undefined" && (action as any).type === "popup")
		{
			action.data.nm_action = "submit";
		}

		const ids = this.normalizeSelection(selection, senders);
		const url = this.buildActionUrl(action, ids);
		const actionTarget = typeof action.data.target !== "undefined" ? action.data.target : target;

		switch(action.data.nm_action)
		{
			case "alert":
				window.alert(`${(action as any).caption} ('${action.id}') executed on rows: ${ids.idsCsv}`);
				break;

			case "location":
				this.executeLocationAction(action, url, actionTarget);
				break;

			case "popup":
				this.executePopupAction(action, url, actionTarget);
				break;

			case "long_task":
				if(this.executeLongTaskAction(action, ids))
				{
					break;
				}
			// Fall through to egw_open for single-row long_task actions with egw_open.
			case "egw_open":
				this.executeEgwOpenAction(action, ids.providerIds, actionTarget);
				break;

			case "open_popup":
				if(this.openActionPopup(action, ids.rawIds))
				{
					break;
				}
			// If no popup is available, submit the action payload.
			case "submit":
				this.executeSubmitAction(action, ids, senders);
				break;
		}
	}

	/**
	 * Resolve selected row ids into datastore and provider ids for action execution.
	 */
	private normalizeSelection(selection : { ids? : string[]; all? : boolean } = {}, senders : EgwActionObject[] = [])
	{
		const rawIds = (selection.ids && selection.ids.length ? selection.ids : senders.map((sender) => sender?.id)).filter(Boolean).map(String);
		const providerIds = rawIds.map((id) => id.split("::").pop()).filter(Boolean);
		return {
			rawIds,
			providerIds,
			all: selection.all === true,
			rowIdsCsv: this.toCsv(rawIds),
			idsCsv: this.toCsv(providerIds)
		};
	}

	/**
	 * Convert selected provider ids to the legacy comma-separated action format.
	 */
	private toCsv(ids : string[]) : string
	{
		return ids.map((id) =>
		{
			const value = String(id);
			return value.indexOf(",") >= 0 ? `"${value.replace(/"/g, '""')}"` : value;
		}).join(",");
	}

	/**
	 * Substitute legacy row-id placeholders into an action URL template.
	 */
	private buildActionUrl(action : EgwAction, ids : ReturnType<Et2NextmatchActionController["normalizeSelection"]>) : string
	{
		const data = action.data || {};
		if(typeof data.url === "undefined")
		{
			return "#";
		}
		let url = String(data.url);
		if(ids.all === true && url.includes("active_filters") && this.host.activeFilters)
		{
			url = url.replace(/(\$|%24)active_filters/, encodeURIComponent(JSON.stringify(this.host.activeFilters)));
		}
		return url
			.replace(/(\$|%24)id/, encodeURIComponent(ids.idsCsv))
			.replace(/(\$|%24)select_all/, String(ids.all))
			.replace(/(\$|%24)row_id/, encodeURIComponent(ids.rowIdsCsv));
	}

	/**
	 * Execute a location-style action by navigating the requested target.
	 */
	private executeLocationAction(action : EgwAction, url : string, target : any)
	{
		if(typeof action.data?.targetapp !== "undefined")
		{
			(this.host.egw() as any).top?.egw_appWindowOpen(action.data.targetapp, url);
		}
		else if(target)
		{
			(this.host.egw() as any).open_link(url, target, action.data?.width ? `${action.data.width}x${action.data.height}` : false);
		}
		else
		{
			window.location.href = url;
		}
	}

	/**
	 * Execute a popup-style action through the legacy popup helper.
	 */
	private executePopupAction(action : EgwAction, url : string, target : any)
	{
		let popupUrl = url;
		let postForm : HTMLFormElement | null = null;
		if(url.length > 4000)
		{
			const params = url.split("&");
			popupUrl = params.shift() || url;
			postForm = document.createElement("form");
			postForm.method = "post";
			for(const param of params)
			{
				const values = param.split("=");
				if(["cd", "tz", "menuaction", "hasupdate"].includes(values[0]))
				{
					popupUrl += `&${values.join("=")}`;
				}
				const input = document.createElement("input");
				input.name = values[0];
				input.type = "text";
				input.value = values.slice(1).join("=");
				postForm.append(input);
			}
		}
		const popup = this.host.egw().open_link(popupUrl, target, `${action.data?.width}x${action.data?.height}`);
		if(postForm && popup)
		{
			popup.name = popup.name || "postRequest";
			postForm.target = popup.name;
			postForm.action = popupUrl;
			document.body.append(postForm);
			postForm.submit();
			postForm.remove();
		}
	}

	/**
	 * Execute a long-task action using the legacy egw.json transport.
	 */
	private executeLongTaskAction(action : EgwAction, ids : ReturnType<Et2NextmatchActionController["normalizeSelection"]>) : boolean
	{
		if(!ids.all && ids.providerIds.length <= 1 && typeof action.data?.egw_open !== "undefined")
		{
			return false;
		}
		const dialog = (window as any).Et2Dialog;
		if(ids.all)
		{
			const datagrid = (this.host as any)._datagrid;
			const total = Number(datagrid?.total || 0);
			const fetchIds = total && ids.providerIds.length >= total
			                 ? Promise.resolve(ids.providerIds)
			                 : this.host.fetchAllIds();
			fetchIds.then((allIds) =>
			{
				dialog?.long_task?.(null, action.data?.message || (action as any).caption, action.data?.title, action.data?.menuaction, allIds);
			}).catch(() => {});
			return true;
		}
		dialog?.long_task?.(null, action.data?.message || (action as any).caption, action.data?.title, action.data?.menuaction, ids.providerIds);
		return true;
	}

	/**
	 * Execute an egw.open action with the selected provider row ids.
	 */
	private executeEgwOpenAction(action : EgwAction, providerIds : string[], target : any)
	{
		const spec = String(action.data?.egw_open || "");
		const params = spec.split("-");
		let egwOpenId = providerIds[0] || "";
		const type = params.shift();
		const app = params.shift();
		if(!type || !app)
		{
			return;
		}
		if(typeof params[2] !== "undefined")
		{
			if(egwOpenId.indexOf(":") >= 0)
			{
				egwOpenId = egwOpenId.split(":")[Number(params.shift())];
			}
			else if(params.length > 1 && params[0] === "" && params[1].indexOf("from=merge") !== -1)
			{
				params.shift();
			}
			else
			{
				params.shift();
			}
		}
		if(params.length > 1 && params[0] === "" && params[1].indexOf("from=merge") !== -1)
		{
			params.shift();
		}
		(window as any).egw(app, window).open(egwOpenId, app, type, params.join("-"), target);
	}

	/**
	 * Store submit action payload so Et2Nextmatch can include it in form submit data.
	 */
	private executeSubmitAction(
		action : EgwAction,
		ids : ReturnType<Et2NextmatchActionController["normalizeSelection"]>,
		senders : EgwActionObject[] = []
	)
	{
		const checkboxValues = {};
		const checkboxes = this.actionManager?.getActionsByAttr?.("checkbox", true) || action.getManager?.()?.getActionsByAttr?.("checkbox", true) || [];
		for(const checkbox of checkboxes)
		{
			checkboxValues[checkbox.id] = checkbox.checked;
		}

		const nextmatch = this.host;
		this.pendingActionSubmitValue = Object.assign(
			{},
			action.data || {},
			{
				selected: ids.providerIds,
				select_all: ids.all,
				checkboxes: checkboxValues
			}
		);
		delete this.pendingActionSubmitValue.id;
		// Avoid deep structures getting passed around
		delete this.pendingActionSubmitValue.children;
		this.pendingActionSubmitValue[nextmatch.settings?.action_var || "action"] = action.id;
		delete this.pendingActionSubmitValue.nextmatch;

		if(action.data?.postSubmit)
		{
			nextmatch.getInstanceManager()?.postSubmit?.();
		}
		else
		{
			nextmatch.getInstanceManager()?.submit?.();
		}
	}

	/**
	 * Open a configured action popup/dialog for the selected provider row ids.
	 */
	private openActionPopup(action : EgwAction, selectedIds : string[]) : boolean
	{
		const instance = this.host.getInstanceManager?.();
		const uid = instance?.uniqueId || "";
		const root = instance?.DOMContainer || document.body;
		const popup = root.querySelector(`et2-dialog[id*='${action.id}_popup']`) ||
			document.body.querySelector(`#${uid}_${action.id}_popup`) ||
			document.body.querySelector(`[id*='${action.id}_popup']`);
		if(!popup)
		{
			return false;
		}
		action.data.nextmatch = this.host;
		(popup as any).selectedIds = selectedIds;
		if(typeof (popup as any).show === "function")
		{
			(popup as any).show();
			return true;
		}
		if(typeof (popup as any).open === "boolean")
		{
			(popup as any).open = true;
			return true;
		}
		if(typeof (popup as any).showModal === "function")
		{
			(popup as any).showModal();
			return true;
		}
		return false;
	}

	/**
	 * Preserve legacy action handlers by giving every registered action direct
	 * access to the owning nextmatch. Some handlers are invoked outside the
	 * default execute callback, so manager-level data is not always enough.
	 */
	private annotateActionsWithNextmatch(actions : EgwAction[] | { [id : string] : EgwAction })
	{
		const actionList = Array.isArray(actions) ? actions : Object.values(actions || {});
		for(const action of actionList)
		{
			if(!action)
			{
				continue;
			}
			if(!action.data)
			{
				action.data = {};
			}
			action.data.nextmatch = this.host;
			this.annotateActionsWithNextmatch(action.children || []);
		}
	}

	/**
	 * Check whether any registered action participates in drag handling.
	 */
	private hasDragActions() : boolean
	{
		return (this.actionManager?.children || []).some((child) => child?.type === "drag");
	}

	/**
	 * Return the root datagrid rows body.
	 */
	private getRowsBody() : HTMLElement | null
	{
		return this.host.shadowRoot?.querySelector("et2-datagrid")?.shadowRoot?.getElementById("rows") as HTMLElement | null;
	}

	/**
	 * Return rows bodies for the root grid and any currently rendered child grids.
	 *
	 * The action framework operates on rendered DOM rows, so nested grids need to
	 * participate in row lookup for selection, context menus and drag/drop.
	 */
	private getRowsBodies() : HTMLElement[]
	{
		const primaryRowsBody = this.getRowsBody();
		const rootGrid = this.host.shadowRoot?.querySelector("et2-datagrid") as HTMLElement | null;
		if(!rootGrid?.shadowRoot)
		{
			return primaryRowsBody ? [primaryRowsBody] : [];
		}
		const grids = [
			rootGrid,
			...Array.from(rootGrid.shadowRoot.querySelectorAll("et2-datagrid"))
		] as HTMLElement[];
		const rowsBodies = grids
			.map((grid) => grid.shadowRoot?.getElementById("rows") as HTMLElement | null)
			.filter(Boolean) as HTMLElement[];
		if(primaryRowsBody && !rowsBodies.includes(primaryRowsBody))
		{
			rowsBodies.unshift(primaryRowsBody);
		}
		return rowsBodies;
	}

	/**
	 * Find a rendered parent or child row by action/datastore row id.
	 */
	private findRenderedRowByActionId(rowId : string, rowsBodies : HTMLElement[] = this.getRowsBodies()) : HTMLElement | null
	{
		for(const rowsBody of rowsBodies)
		{
			const rowElement = rowsBody.querySelector(`[data-row-id="${CSS.escape(rowId)}"]`) as HTMLElement | null;
			if(rowElement)
			{
				return rowElement;
			}
		}
		return null;
	}

	/**
	 * Remove action objects whose rendered rows have left the DOM.
	 */
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

	/**
	 * Create the action object interface wrapper for one rendered row.
	 */
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

	/**
	 * Register legacy link-based drag/drop action implementations once.
	 */
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
		const dataStorePrefix = dataProvider?.getDataStorePrefix?.() || this.host.getInstanceManager()?.app || this.host.egw().app_name?.();
		if(!this.host.egw().link_get_registry?.(dataStorePrefix, "query") ||
			this.host.egw().link_get_registry?.(dataStorePrefix, "title"))
		{
			return;
		}
		if(!dropCancel)
		{
			dropCancel = mgr.addAction("drop", "egw_cancel_drop", this.host.egw().lang("Cancel"), this.host.egw().image("cancel"), function() {}, true);
			(dropCancel as any)?.set_group?.("99");
			if(Array.isArray((dropCancel as any)?.acceptedTypes))
			{
				(dropCancel as any).acceptedTypes = (dropCancel as any).acceptedTypes.concat(Object.keys(this.host.egw().user?.("apps") || {}).concat(["link", "file"]));
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
		if(Array.isArray((dropAction as any)?.acceptedTypes) && !(dropAction as any).acceptedTypes.includes("link"))
		{
			(dropAction as any).acceptedTypes.push("link");
		}
		if(!dragAction)
		{
			dragAction = mgr.addAction("drag", "egw_link_drag", this.host.egw().lang("link"), "link", () => null, true);
		}
		(dragAction as any)?.set_dragType?.("link");
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
		return this.getActionLinks().map((actionId) =>
		{
			const allowedInPlaceholderContext = this._isPlaceholderContextActionAllowed(actionId, allowed);
			return {
				actionId,
				enabled: allowedInPlaceholderContext,
				visible: allowedInPlaceholderContext
			};
		});
	}

	/**
	 * Return placeholder action ids that are enabled and visible for the placeholder context.
	 *
	 * This evaluates through the same action-link path as the placeholder popup once
	 * per action type, so dynamic `enabled` callbacks and context visibility flags
	 * are owned by the action system.
	 */
	private _enabledPlaceholderActionIds(actionIds : string[], anchorElement : HTMLElement = this.host) : Set<string>
	{
		const requested = Array.from(new Set((actionIds || []).map((id) => String(id || "").trim()).filter(Boolean)));
		const enabled = new Set<string>();
		this.ensureActionManagers();
		if(!requested.length || !this.objectManager)
		{
			return enabled;
		}
		const placeholderObject = this.ensurePlaceholderActionObject(anchorElement);
		if(!placeholderObject)
		{
			return enabled;
		}
		placeholderObject.updateActionLinks(this._getPlaceholderContextLinks(requested));

		const actionTypes = new Set<string>();
		for(const actionId of requested)
		{
			const action = this.actionManager?.getActionById?.(actionId);
			actionTypes.add(String((action as any)?.type || "popup"));
		}
		for(const actionType of actionTypes)
		{
			const links = placeholderObject.getSelectedLinks?.(actionType, true)?.links || {};
			for(const actionId of requested)
			{
				const link = links[actionId];
				if(link?.enabled && link?.visible)
				{
					enabled.add(actionId);
				}
			}
		}
		return enabled;
	}

	/**
	 * Top-level action links also need to be enabled when one of their children is
	 * allowed, because the egw_action link resolver reaches children through the
	 * parent's context link.
	 */
	private _isPlaceholderContextActionAllowed(actionId : string, allowed : Set<string>) : boolean
	{
		if(allowed.has(actionId))
		{
			return true;
		}
		const action = this.actionManager?.getActionById?.(actionId);
		return !!action?.children?.some((child) => child?.id && allowed.has(child.id));
	}

	/**
	 * Materialize the placeholder action object used by empty-state actions.
	 */
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
	 * Resolve the most specific rendered element for row popup context.
	 *
	 * Row actions still execute on the row action object, but generic popup
	 * helpers such as "Copy to OS clipboard" need the clicked widget/cell text
	 * instead of the whole row text.
	 */
	private findContextTarget(event : Event, rowElement : HTMLElement) : HTMLElement
	{
		const path = event.composedPath?.() || [];
		for(const target of path)
		{
			if(!(target instanceof HTMLElement) || target === rowElement || !rowElement.contains(target))
			{
				continue;
			}
			const widget = this.closestContextWidget(target, rowElement);
			if(widget)
			{
				return widget;
			}
			if((target.textContent || "").trim())
			{
				return target;
			}
		}
		const pointTarget = this.findContextTargetFromPoint(event as MouseEvent, rowElement);
		return pointTarget || rowElement;
	}

	/**
	 * Find the nearest row widget target suitable for context action metadata.
	 */
	private closestContextWidget(target : HTMLElement, rowElement : HTMLElement) : HTMLElement | null
	{
		let node : HTMLElement | null = target;
		while(node && node !== rowElement)
		{
			const tagName = node.tagName.toLowerCase();
			if(tagName.includes("-") || node.hasAttribute("data-et2-id") || node.hasAttribute("data-id"))
			{
				return node;
			}
			node = node.parentElement;
		}
		return null;
	}

	/**
	 * Resolve the deepest context target for popup actions at the pointer location.
	 */
	private findContextTargetFromPoint(event : MouseEvent, rowElement : HTMLElement) : HTMLElement | null
	{
		if(typeof event.clientX !== "number" || typeof event.clientY !== "number")
		{
			return null;
		}
		const deepTarget = this.getDeepElementFromPoint(rowElement.getRootNode() as Document | ShadowRoot, event.clientX, event.clientY);
		if(!deepTarget || deepTarget === rowElement || !rowElement.contains(deepTarget))
		{
			return null;
		}
		return this.closestContextWidget(deepTarget, rowElement) || deepTarget;
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
		const rowsBodies = this.getRowsBodies();
		if(!rowsBodies.length)
		{
			return null;
		}
		for(const rowsBody of rowsBodies)
		{
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
