import {EgwAction} from "../../egw_action/EgwAction";
import {EgwActionManager} from "../../egw_action/EgwActionManager";
import {EgwActionObject} from "../../egw_action/EgwActionObject";
import {EgwActionObjectManager} from "../../egw_action/EgwActionObjectManager";
import * as egwActionApi from "../../egw_action/egw_action";
import {
	EGW_AO_EXEC_SELECTED,
	EGW_AO_FLAG_DEFAULT_FOCUS,
	EGW_AO_FLAG_IS_CONTAINER
} from "../../egw_action/egw_action_constants";
import {nm_action} from "../et2_extension_nextmatch_actions";
import type {Et2Nextmatch} from "./Et2Nextmatch";

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
		this.actionManager.updateActions(actions, this.host.egw().appName);
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
		for(const [rowId, rowObject] of this.rowActionObjects.entries())
		{
			rowObject.setSelected(this.allSelected || selectedSet.has(rowId));
			rowObject.setFocused(rowId === activeRowId);
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
		this.cancelLongPress();
		this.clearRowActionObjects();
	}

	private ensureActionManagers()
	{
		const appName = this.host.getInstanceManager()?.app || this.host.egw().appName;
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
			const aoi = new egwActionApi.egwActionObjectInterface();
			aoi.doGetDOMNode = () => rowElement;
			rowObject = this.objectManager.addObject(rowId, aoi);
			if(!rowObject)
			{
				return null;
			}
			rowObject.updateActionLinks(this.getActionLinks());
			this.rowActionObjects.set(rowId, rowObject);
		}
		else
		{
			rowObject.iface.doGetDOMNode = () => rowElement;
			rowObject.setAOI(rowObject.iface);
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
			const aoi = new egwActionApi.egwActionObjectInterface();
			aoi.doGetDOMNode = () => anchorElement;
			placeholderObject = this.objectManager.addObject(Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID, aoi);
			if(!placeholderObject)
			{
				return null;
			}
			this.rowActionObjects.set(Et2NextmatchActionController.PLACEHOLDER_ACTION_OBJECT_ID, placeholderObject);
		}
		else
		{
			placeholderObject.iface.doGetDOMNode = () => anchorElement;
			placeholderObject.setAOI(placeholderObject.iface);
		}
		return placeholderObject;
	}

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
			const rowId = String(row.getAttribute("data-row-id") || "");
			if(rowId)
			{
				return {rowId, rowElement: row};
			}
		}
		return null;
	}
}

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
