/**
 * EGroupware egw_action framework - egw action framework
 *
 * @link https://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */

import {EgwActionImplementation} from "./EgwActionImplementation";
import {EGW_AI_DRAG_ENTER, EGW_AI_DRAG_OUT, EGW_AI_DRAG_OVER, EGW_AO_EXEC_THIS} from "./egw_action_constants";
import {egw_getObjectManager} from "./egw_action";
import {getPopupImplementation} from "./EgwPopupActionImplementation";

export class EgwDropActionImplementation implements EgwActionImplementation {
    type: string = "drop";
    //keeps track of current drop element where dragged item's entered.
    // it's necessary for dragenter/dragleave issue correction.
    private currentDropEl = null


    registerAction: (_actionObjectInterface: any, _triggerCallback: Function, _context: object) => boolean =  (_aoi, _callback, _context)=> {
        const node = _aoi.getDOMNode() && _aoi.getDOMNode()[0] ? _aoi.getDOMNode()[0] : _aoi.getDOMNode();
        const self:EgwDropActionImplementation = this;
        if (node) {
            node.classList.add('et2dropzone');
            const dragover = (event)=> {
                if (event.preventDefault) {
                    event.preventDefault();
                }
                if (!this.getTheDraggedDOM()) return;

                const data = {
                    event: event,
                    ui: this.getTheDraggedData()
                };
                _aoi.triggerEvent(EGW_AI_DRAG_OVER, data);

                return true;

            };

            const dragenter = function (event) {
                event.stopImmediatePropagation();
                // don't trigger dragenter if we are entering the drag element
                // don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
                if (!self.getTheDraggedDOM() || self.isTheDraggedDOM(this) || this == self.currentDropEl) return;

                self.currentDropEl = event.currentTarget;
                event.dataTransfer.dropEffect = 'link';

                const data = {
                    event: event,
                    ui: self.getTheDraggedData()
                };

                _aoi.triggerEvent(EGW_AI_DRAG_ENTER, data);

                // cleanup drop hover class from all other DOMs if there's still anything left
                Array.from(document.getElementsByClassName('et2dropzone drop-hover')).forEach(_i => {
                    _i.classList.remove('drop-hover')
                })

                this.classList.add('drop-hover');

                // stop the event from being fired for its children
                event.preventDefault();
                return false;
            };

            const drop = function (event) {
                event.preventDefault();
                // don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
                if (!self.getTheDraggedDOM()) return;

                // remove the hover class
                this.classList.remove('drop-hover');

                const helper = self.getHelperDOM();
                let ui = self.getTheDraggedData();
                ui.position = {top: event.clientY, left: event.clientX};
                ui.offset = {top: event.offsetY, left: event.offsetX};


                let data = JSON.parse(event.dataTransfer.getData('application/json'));

                if (!self.isAccepted(data, _context, _callback,undefined) || self.isTheDraggedDOM(this)) {
                    // clean up the helper dom
                    if (helper) helper.remove();
                    return;
                }

                let selected = data.selected.map((item) => {
                    return egw_getObjectManager(item.id, false)
                });

                //links is an Object of DropActions bound to their names
                const links = _callback.call(_context, "links", self, EGW_AO_EXEC_THIS);

                // Disable all links which only accept types which are not
                // inside ddTypes
                for (const k in links) {
                    const accepted = links[k].actionObj.acceptedTypes;

                    let enabled = false;
                    for (let i = 0; i < data.ddTypes.length; i++) {
                        if (accepted.indexOf(data.ddTypes[i]) != -1) {
                            enabled = true;
                            break;
                        }
                    }
                    // Check for allowing multiple selected
                    if (!links[k].actionObj.allowOnMultiple && selected.length > 1) {
                        enabled = false;
                    }
                    if (!enabled) {
                        links[k].enabled = false;
                        links[k].visible = !links[k].actionObj.hideOnDisabled;
                    }
                }

                // Check whether there is only one link
                let cnt = 0;
                let lnk = null;
                for (const k in links) {
                    if (links[k].enabled && links[k].visible) {
                        lnk = links[k];
                        cnt += 1 + links[k].actionObj.children.length;

                        // Add ui, so you know what happened where
                        lnk.actionObj.ui = ui;

                    }
                }

                if (cnt == 1) {
                    window.setTimeout(function () {
                        lnk.actionObj.execute(selected, _context);
                    }, 0);
                }

                if (cnt > 1) {
                    // More than one drop action link is associated
                    // to the drop event - show those as a popup menu
                    // and let the user decide which one to use.
                    // This is possible as the popup and the popup action
                    // object and the drop action object share same
                    // set of properties.
                    const popup = getPopupImplementation();
                    const pos = popup._getPageXY(event);

                    // Don't add paste actions, this is a drop
                    popup.auto_paste = false;

                    window.setTimeout(function () {
                        popup.executeImplementation(pos, selected, links,
                            _context);
                        // Reset, popup is reused
                        popup.auto_paste = true;
                    }, 0); // Timeout is needed to have it working in IE
                }
                // Set cursor back to auto. Seems FF can't handle cursor reversion
                jQuery('body').css({cursor: 'auto'});

                _aoi.triggerEvent(EGW_AI_DRAG_OUT, {event: event, ui: self.getTheDraggedData()});

                // clean up the helper dom
                if (helper) helper.remove();
                self.getTheDraggedDOM().classList.remove('drag--moving');
            };

            const dragleave = function (event) {
                event.stopImmediatePropagation();

                // don't trigger dragleave if we are leaving the drag element
                // don't go further if the dragged element is no there (happens when a none et2 dragged element is being dragged)
                if (!self.getTheDraggedDOM() || self.isTheDraggedDOM(this)) return;

                const data = {
                    event: event,
                    ui: self.getTheDraggedData()
                };

                _aoi.triggerEvent(EGW_AI_DRAG_OUT, data);

                this.classList.remove('drop-hover');

                event.preventDefault();
                return false;
            };

            // DND Event listeners
            node.addEventListener('dragover', dragover, false);

            node.addEventListener('dragenter', dragenter, false);

            node.addEventListener('drop', drop, false);

            node.addEventListener('dragleave', dragleave, false);

            return true;
        }
        return false;
    };

    unregisterAction: (_actionObjectInterface: any) => boolean = function (_aoi) {
        const node = _aoi.getDOMNode();

        if (node) {
            node.classList.remove('et2dropzone');
        }
        return true;
    };

    /**
     * Builds the context menu and shows it at the given position/DOM-Node.
     *
     * @param {string} _context
     * @param {array} _selected
     * @param {object} _links
     */
    executeImplementation: (_context: any, _selected: any, _links: any) => any = function (_context, _selected, _links) {
        if (_context == "links") {
            return _links;
        }
    };


    isTheDraggedDOM = function (_dom) {
        return _dom.classList.contains('drag--moving');
    }

    getTheDraggedDOM = function () {
        return document.querySelector('.drag--moving');
    }

    getHelperDOM = function () {
        return document.querySelector('.et2_egw_action_ddHelper');
    }

    getTheDraggedData =  ()=> {
        // @ts-ignore // in our case dataset will be present
        let data = this.getTheDraggedDOM().dataset.egwactionobjid;
        let selected = [];
        if (data) {
            data = JSON.parse(data);
            selected = data.map((item) => {
                return egw_getObjectManager(item.id, false)
            });
        }
        return {
            draggable: this.getTheDraggedDOM(),
            helper: this.getHelperDOM(),
            selected: selected,
            position: undefined,
            offset: undefined

        }
    }

    // check if given draggable is accepted for drop
    isAccepted =  (_data, _context, _callback, _node)=> {
        if (_node && !_node.classList.contains('et2dropzone')) return false;
        if (typeof _data.ddTypes != "undefined") {
            const accepted = this._fetchAccepted(
                _callback.call(_context, "links", this, EGW_AO_EXEC_THIS));

            // Check whether all drag types of the selected objects
            // are accepted
            const ddTypes = _data.ddTypes;

            for (let i = 0; i < ddTypes.length; i++) {
                if (accepted.indexOf(ddTypes[i]) != -1) {
                    return true;
                }
            }
        }
        return false;
    };


    private _fetchAccepted =  (_links) =>{
        // Accumulate the accepted types
        const accepted = [];
        for (let k in _links) {
            for (let i = 0; i < _links[k].actionObj.acceptedTypes.length; i++) {
                const type = _links[k].actionObj.acceptedTypes[i];

                if (accepted.indexOf(type) == -1) {
                    accepted.push(type);
                }
            }
        }

        return accepted;
    };

}

export class egwDropActionImplementation extends EgwDropActionImplementation {
}