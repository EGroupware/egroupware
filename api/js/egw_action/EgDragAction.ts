import {EgwAction} from "./EgwAction";

/**
 * The egwDragAction class overwrites the egwAction class and adds the new
 * "dragType" propery. The "onExecute" event of the drag action will be called
 * whenever dragging starts. The onExecute JS handler should return the
 * drag-drop helper object - otherwise an default helper will be generated.
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