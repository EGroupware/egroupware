import {EgwActionObject} from "../egw_action/EgwActionObject";

/**
 * if a class that registers actions implements this interface the Popup actions only bind one event handler on the parent item
 * so not every child item will get its own event handler
 * Currently only implemented by Et2Tree
 * there the actions only bind one contextmenu event listener on the "et2-tree" htmlElement instead of one for each sl-tree-item
 */
export interface FindActionTarget
{
    /**
     * returns the closest Item to the click position, and the corresponding EgwActionObject
     * @param _event the click event
     * @returns { target:HTMLElement, action:EgwActionObject }
     */
    findActionTarget(_event): { target: HTMLElement, action: EgwActionObject };
}