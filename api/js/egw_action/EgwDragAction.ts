/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {EgwAction} from "./EgwAction";

/**
 * The egwDragAction class overwrites the egwAction class and adds the new
 * "dragType" property. The "onExecute" event of the drag action will be called
 * whenever dragging starts. The onExecute JS handler should return the
 * drag-drop helper object - otherwise a default helper will be generated.
 */
export class EgwDragAction extends EgwAction {
    private dragType = "default"

    public set_dragType(_value) {
        this.dragType = _value
    }

    /**
     * @param {EgwAction} parent
     * @param {string} _id
     * @param {string} _caption
     * @param {string} _iconUrl
     * @param {(string|function)} _onExecute
     * @param {bool} _allowOnMultiple
     */
    constructor(parent: EgwAction, _id, _caption, _iconUrl, _onExecute, _allowOnMultiple) {
        super(parent, _id, _caption, _iconUrl, _onExecute, _allowOnMultiple);
        this.type = "drag";
        this.hideOnDisabled = true;
    }
}