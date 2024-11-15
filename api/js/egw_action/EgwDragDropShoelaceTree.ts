/**
 * EGroupware egw_dragdrop_shoelaceTree - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */
import {egwActionObjectInterface} from "./egw_action";
import {Et2Tree} from "../etemplate/Et2Tree/Et2Tree";
import {
	EGW_AI_DRAG,
	EGW_AI_DRAG_ENTER,
	EGW_AI_DRAG_OUT,
	EGW_AO_STATE_FOCUSED,
	EGW_AO_STATE_SELECTED
} from "./egw_action_constants";
import {egwBitIsSet} from "./egw_action_common";
import {SlTreeItem} from "@shoelace-style/shoelace";
import {FindActionTarget} from "../etemplate/FindActionTarget";


export const EXPAND_FOLDER_ON_DRAG_DROP_TIMEOUT = 1000

export class EgwDragDropShoelaceTree extends egwActionObjectInterface{

	id: string;
    tree: Et2Tree;

	// Reference to the widget that's handling actions for us
	public findActionTargetHandler : FindActionTarget;

	// List of timeouts indexed by ID because drag out doesn't always happen before drag in
	private timeouts : { [key : string] : ReturnType<typeof setTimeout> };

	constructor(_tree : Et2Tree)
	{

		super();
		this.tree = _tree
		this.findActionTargetHandler = _tree;
		this.timeouts = {};
	}

	public doTriggerEvent(egw_event : number, data : any)
	{
		let dom_event = data.event ?? data;
		const target = this.findActionTargetHandler.findActionTarget(dom_event);
		if(egw_event == EGW_AI_DRAG_ENTER && !target.target.classList.contains("draggedOver"))
		{
			// Remove drag classes from all items
			this.tree.shadowRoot.querySelectorAll("sl-tree-item.draggedOver").forEach(n =>
			{
				n.classList.remove("draggedOver", "drop-hover");
			});
			target.target.classList.add("draggedOver", "drop-hover");

			// Open nodes with children after a wait
			if(target.target.hasAttribute("lazy") || target.target.querySelector(target.target.nodeName))
			{
				this.timeouts[target.target.id] = setTimeout(() =>
				{
					if(target.target.classList.contains("draggedOver"))
					{
						(<SlTreeItem>target.target).expanded = true
					}
				}, EXPAND_FOLDER_ON_DRAG_DROP_TIMEOUT)
			}
		}
		else if(egw_event == EGW_AI_DRAG_OUT)
		{
			target.target.classList.remove("draggedOver", "drop-hover");
			clearTimeout(this.timeouts[target.target.id])
		}
		else if(egw_event == EGW_AI_DRAG)
		{
			target.action.setSelected(true);
		}
		return true
	}

	public doSetState(_state)
	{
		if(!this.tree || !this.tree.focusItem)
		{
			return;
		}
		if(this.stateChangeContext)
		{
			const target = this.tree.shadowRoot.querySelector("[id='" + this.stateChangeContext.id + "']");

			if(target && egwBitIsSet(_state, EGW_AO_STATE_FOCUSED))
			{
				target.focus();
			}
		}

		// Update the "focused" flag
		if(egwBitIsSet(_state, EGW_AO_STATE_FOCUSED))
		{
			this.tree.focus();
		}
		if(egwBitIsSet(_state, EGW_AO_STATE_SELECTED))
		{
			// _tree.selectItem(this.id, false);	// false = do not trigger onSelect
		}
	}

	getWidget()
	{
		return this.tree;
	}

	doGetDOMNode()
	{
		return this.tree;
	}
}