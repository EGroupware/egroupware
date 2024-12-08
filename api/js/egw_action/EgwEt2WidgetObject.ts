import {EgwActionObjectInterface} from "./EgwActionObjectInterface";
import {Element} from "parse5";
import {Function} from "estree";
import {EGW_AO_STATE_NORMAL, EGW_AO_STATE_VISIBLE} from "./egw_action_constants";
import {Et2Widget} from "../etemplate/Et2Widget/Et2Widget";

/**
 * Generic interface object so any webComponent can participate in action system.
 * This interface can be extended if special handling is needed, but it should work
 * for any widget.
 */
export class EgwEt2WidgetObject implements EgwActionObjectInterface
{
	node = null;

	constructor(node)
	{
		this.node = node;
	}

	_state : number = EGW_AO_STATE_NORMAL || EGW_AO_STATE_VISIBLE;
	handlers : { [p : string] : any };

	reconnectActionsCallback(p0)
	{
	}

	reconnectActionsContext : any;

	stateChangeCallback(p0)
	{
	}

	stateChangeContext : any;

	// @ts-ignore
	getDOMNode() : Element
	{
		return this.node;
	}

	getWidget() : typeof Et2Widget
	{
		return this.node
	}

	getState() : number
	{
		return this._state;
	}

	makeVisible() : void
	{
	}

	reconnectActions() : void
	{
	}

	setReconnectActionsCallback(_callback : Function, _context : any) : void
	{
	}

	setState(_state : any) : void
	{
		this._state = _state
	}

	setStateChangeCallback(_callback : Function, _context : any) : void
	{
	}

	triggerEvent(_event : any, _data : any) : boolean
	{
		return false;
	}

	updateState(_stateBit : number, _set : boolean, _shiftState : boolean) : void
	{
	}
}