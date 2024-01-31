/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

/**
 * The egwActionObjectInterface has to be implemented for each actual object in
 * the browser. E.g. for the object "DataGridRow", there has to be an
 * egwActionObjectInterface which is responsible for returning the outer DOMNode
 * of the object to which JS-Events may be attached by the EgwActionImplementation
 * object, and to do object specific stuff like highlighting the object in the
 * correct way and to route state changes (like: "object has been selected")
 * to the egwActionObject object the interface is associated to.
 *
 * @return {egwActionObjectInterface}
 */
export interface EgwActionObjectInterface {
    //properties
    id?:string
    _state: number;
    stateChangeCallback: Function;
    stateChangeContext: any;
    reconnectActionsCallback: Function;
    reconnectActionsContext: any;

    //functions
    /**
     * Sets the callback function which will be called when a user interaction changes
     * state of the object.
     *
     * @param {function} _callback
     * @param {object} _context
     */
    setStateChangeCallback(_callback: Function, _context: any): void;

    /**
     * Sets the reconnectActions callback, which will be called by the AOI if its
     * DOM-Node has been replaced and the actions have to be re-registered.
     *
     * @param {function} _callback
     * @param {object} _context
     */
    setReconnectActionsCallback(_callback: Function, _context: any): void;

    /**
     * Will be called by the aoi if the actions have to be re-registered due to a
     * DOM-Node exchange.
     */
    reconnectActions(): void;

    /**
     * Internal function which should be used whenever the select status of the object
     * has been changed by the user. This will automatically calculate the new state of
     * the object and call the stateChangeCallback (if it has been set)
     *
     * @param {number} _stateBit is the bit in the state bit which should be changed
     * @param {boolean} _set specifies whether the state bit should be set or not
     * @param {boolean} _shiftState
     */
    updateState(_stateBit: number, _set: boolean, _shiftState: boolean): void;


    /**
     * Returns the DOM-Node the ActionObject is actually a representation of.
     * Calls the internal "doGetDOMNode" function, which has to be overwritten
     * by implementations of this class.
     */
    getDOMNode(): Element;

    setState(_state: any): void;

    getState(): number;

    triggerEvent(_event: any, _data: any): boolean;

    /**
     * Scrolls the element into a visible area if it is currently hidden
     */
    makeVisible(): void;
}
