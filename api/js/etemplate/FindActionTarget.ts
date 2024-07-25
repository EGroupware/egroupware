import {EgwActionObject} from "../egw_action/EgwActionObject";

export interface FindActionTarget
{
    /**
     * returns the closest Item to the click position, and the corresponding EgwActionObject
     * @param _event the click event
     * @returns { target:HTMLElement, action:EgwActionObject }
     */
    findActionTarget(_event): { target: HTMLElement, action: EgwActionObject };
}