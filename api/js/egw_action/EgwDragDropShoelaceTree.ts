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
import {EGW_AI_DRAG_OUT, EGW_AI_DRAG_OVER, EGW_AO_STATE_FOCUSED, EGW_AO_STATE_SELECTED} from "./egw_action_constants";
import {egwBitIsSet} from "./egw_action_common";


function dhtmlxTree_getNode(_tree: Et2Tree, _itemId: string)
{
    const node = _tree.getDomNode(_itemId);
    if (node != null)
    {
        return node
    }
}

export const EXPAND_FOLDER_ON_DRAG_DROP_TIMEOUT = 1000

export class EgwDragDropShoelaceTree {
    constructor(_tree:Et2Tree, _itemId) {

        const aoi = new egwActionObjectInterface();
        aoi.node = _tree.getDomNode(_itemId);
        aoi.id = _itemId
        aoi.doGetDOMNode = function () {
            return aoi.node;
        }

        aoi.doTriggerEvent = function (_event) {
            if (_event == EGW_AI_DRAG_OVER)
            {
                this.node.classList.add("draggedOver");
                setTimeout(() => {
                    if (this.node.classList.contains("draggedOver"))
                    {
                        this.node.expanded = true
                    }
                }, EXPAND_FOLDER_ON_DRAG_DROP_TIMEOUT)
            }
            if (_event == EGW_AI_DRAG_OUT)
            {
                (this.node).classList.remove("draggedOver");
            }
            return true
        }

        aoi.doSetState = function (_state) {
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

        return aoi;
    }
}