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
		rowObject.forceSelection();
		const rowAlreadySelected = this.allSelected || this.selectedRowIds.includes(row.rowId);
		if(!rowAlreadySelected)
		{
			this.host.selectSingleRow?.(row.rowId);
			this.allSelected = false;
			this.selectedRowIds = [row.rowId];
		}
		const rect = row.rowElement.getBoundingClientRect();
		return  rowObject.executeActionImplementation({
			// Pass menu along to prevent recreation
			menu: this.actionManager?.data?.menu ?? null,
			event: contextEvent,
			posx: rect.left + (rect.width / 2),
			posy: rect.top + (rect.height / 2),
			innerText: row.rowElement.textContent || ""
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
