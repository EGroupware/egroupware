/**
 * EGroupware egw_dragdrop_dhtmlxmenu - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */


import {egwBitIsSet, egwSetBit} from "./egw_action_common";
import {
	EGW_AI_DRAG_OUT,
	EGW_AI_DRAG_OVER,
	EGW_AO_STATE_FOCUSED,
	EGW_AO_STATE_NORMAL,
	EGW_AO_STATE_SELECTED, EGW_AO_STATE_VISIBLE, EGW_AO_STATES
} from "./egw_action_constants";
import {EgwActionObjectInterface} from "./egw_action";
import {egwActionObjectInterface} from "./egw_action";

/**
 * This file contains an egw_actionObjectInterface which allows a dhtmlx tree
 * row to be a drag target and contains a function which transforms a complete
 * dhtmlx tree into egw_actionObjects
 */
declare class dhtmlXTreeObject
{

	_globalIdStorageFind(_itemId: string): any
}

export function dhtmlxTree_getNode(_tree: dhtmlXTreeObject, _itemId: string)
{
	const node = _tree._globalIdStorageFind(_itemId);
	if (node != null)
	{
		// Get the outer html table node of the tree node - return the first
		// "tr" child of the element
		return node.htmlNode.querySelector("tr:first-child")
	}
}

// An action object interface for an dhtmlxTree entry - it only contains the
// code needed for drag/drop handling
export class dhtmlxtreeItemAOI implements EgwActionObjectInterface
{
	private node;
	private id;
	private tree;

	constructor(_tree, _itemId)
	{
		// Retrieve the actual node from the tree
		this.node = dhtmlxTree_getNode(_tree, _itemId);
		this.id = _itemId;
		this.tree = _tree

	}

	_state: number = EGW_AO_STATE_NORMAL || EGW_AO_STATE_VISIBLE;
	stateChangeCallback: Function;
	stateChangeContext: any;
	reconnectActionsCallback: Function;
	reconnectActionsContext: any;

	setStateChangeCallback(_callback: Function, _context: any): void
	{
		this.stateChangeCallback = _callback;
		this.stateChangeContext = _context;
	}

	setReconnectActionsCallback(_callback: Function, _context: any): void
	{
		this.reconnectActionsCallback = _callback;
		this.reconnectActionsContext = _context;
	}

	reconnectActions(): void
	{
		if (this.reconnectActionsCallback)
		{
			this.reconnectActionsCallback.call(this.reconnectActionsContext);
		}
	}

	updateState(_stateBit: number, _set: boolean, _shiftState: boolean): void
	{
		// Calculate the new state
		//this does not guarantee a valid state at runtime
		const newState: EGW_AO_STATES = <EGW_AO_STATES>egwSetBit(this._state, _stateBit, _set);

		// Call the stateChangeCallback if the state really changed
		if (this.stateChangeCallback)
		{
			this._state = this.stateChangeCallback.call(this.stateChangeContext, newState, _stateBit, _shiftState);
		} else
		{
			this._state = newState;
		}

	}

	getDOMNode(): Element
	{
		return this.node;
	}

	setState(_state: any): void
	{
		if (!this.tree || !this.tree.focusItem) return;

		// Update the "focused" flag
		if (egwBitIsSet(_state, EGW_AO_STATE_FOCUSED))
		{
			this.tree.focusItem(this.id);
		}
		if (egwBitIsSet(_state, EGW_AO_STATE_SELECTED))
		{
			this.tree.selectItem(this.id, false);	// false = do not trigger onSelect
		}
	}

	getState(): number
	{
		return this._state;
	}

	triggerEvent(_event: any, _data: any): boolean
	{
		let result = false
		if (_event == EGW_AI_DRAG_OVER)
		{
			this.node.classList.add("draggedOver")
			result = true
		}
		if (_event == EGW_AI_DRAG_OUT)
		{
			this.node.classList.remove("draggedOver")
			result = true
		}
		return result
	}

	makeVisible(): void
	{
		console.log("Method not implemented.");
	}
}

