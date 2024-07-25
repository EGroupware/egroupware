/**
 * EGroupware egw_dragdrop_shoelaceTree - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */
import {EgwActionObjectInterface} from "./EgwActionObjectInterface";
import {egwActionObjectInterface} from "./egw_action";
import {Et2Tree} from "../etemplate/Et2Tree/Et2Tree";
import {
    EGW_AI_DRAG_ENTER,
    EGW_AI_DRAG_OUT,
    EGW_AI_DRAG_OVER,
    EGW_AO_STATE_FOCUSED,
    EGW_AO_STATE_SELECTED
} from "./egw_action_constants";
import {egwBitIsSet} from "./egw_action_common";
import {SlTreeItem} from "@shoelace-style/shoelace";



export const EXPAND_FOLDER_ON_DRAG_DROP_TIMEOUT = 1000

export class EgwDragDropShoelaceTree extends egwActionObjectInterface{
    node: SlTreeItem;
    id: string;
    tree: Et2Tree;
    constructor(_tree:Et2Tree, _itemId: string) {

        super();
        this.node = _tree.getDomNode(_itemId);
        this.id = _itemId
        this.tree = _tree
        this.doGetDOMNode = function () {
            return this.node;
        }
        let timeout: NodeJS.Timeout;

        this.doTriggerEvent = function (_event) {
            if (_event == EGW_AI_DRAG_ENTER)
            {

                this.node.classList.add("draggedOver");
                timeout = setTimeout(() => {
                    if (this.node.classList.contains("draggedOver"))
                    {
                        this.node.expanded = true
                    }
                }, EXPAND_FOLDER_ON_DRAG_DROP_TIMEOUT)
            }
            if (_event == EGW_AI_DRAG_OUT)
            {
                (this.node).classList.remove("draggedOver");
                clearTimeout(timeout)
            }
            return true
        }

        this.doSetState = function (_state) {
            if (!_tree || !_tree.focusItem) return;

            // Update the "focused" flag
            if (egwBitIsSet(_state, EGW_AO_STATE_FOCUSED))
            {
                _tree.focusItem(this.id);
            }
            if (egwBitIsSet(_state, EGW_AO_STATE_SELECTED))
            {
               // _tree.selectItem(this.id, false);	// false = do not trigger onSelect
            }
        }
    }
}