import {EgwAction} from "./EgwAction";

/**
 * egwActionManager manages a list of actions - it overwrites the egwAction class
 * and allows child actions to be added to it.
 *
 * @param {EgwAction} _parent
 * @param {string} _id
 * @return {EgwActionManager}
 */
export class EgwActionManager extends EgwAction {
    constructor(_parent = null, _id = "") {
        super(_parent, _id);
        this.type = "actionManager";
        this.canHaveChildren = true;
    }
}