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
import {egw} from "../jsapi/egw_global";
import {EgwActionObjectInterface} from "./EgwActionObjectInterface";

export class EgwDragActionImplementation implements EgwActionImplementation {
    type = "drag";
    helper: HTMLDivElement = null;
    ddTypes: any[] = [];
    selected: any[] = [];
    defaultDDHelper: (_selected) => HTMLDivElement = (_selected) => {
        // Table containing clone of rows
        const table: HTMLTableElement = (document.createElement("table"));
        table.classList.add('egwGridView_grid', 'et2_egw_action_ddHelper_row');
        // tr element to use as last row to show 'more ...' label
        const moreRow: HTMLTableRowElement = (document.createElement('tr'))
        moreRow.classList.add('et2_egw_action_ddHelper_moreRow');
        // Main div helper container
        const div: HTMLDivElement = (document.createElement("div"));
        div.append(table);

        let rows = [];
        // Maximum number of rows to show
        let maxRows = 3;
        // item label
        const itemLabel = egw.lang(
            (
                egw.link_get_registry(egw.app_name(), _selected.length > 1 ? 'entries' : 'entry') || egw.app_name()
            ) as string
        );
        let index = 0;

        // Take select all into account when counting number of rows, because they may not be
        // in _selected object
        const pseudoNumRows = (_selected[0]?._context?._selectionMgr?._selectAll) ?
            _selected[0]._context?._selectionMgr?._total : _selected.length;

		// Clone nodes but use copy webComponent properties
		const carefulClone = (node) =>
		{
			// Don't clone text nodes, it causes duplication in et2-description
			if(node.nodeType == node.TEXT_NODE)
			{
				return;
			}

			let clone = node.cloneNode();

			let widget_class = window.customElements.get(clone.localName);
			let properties = widget_class ? widget_class.properties : [];
			for(let key in properties)
			{
				clone[key] = node[key];
			}
			// Children
			node.childNodes.forEach(c =>
			{
				const child = carefulClone(c)
				if(child)
				{
					clone.appendChild(child);
				}
			})
			if(widget_class)
			{
				clone.requestUpdate();
			}
			return clone;
		}

		for(const egwActionObject of _selected)
		{
			const row : Node = carefulClone(egwActionObject.iface.getDOMNode());
			if(row)
			{
				rows.push(row);
				table.append(row);
			}
            index++;
            if (index == maxRows) {
                // Label to show number of items
                const spanCnt = (document.createElement('span'))
                spanCnt.classList.add('et2_egw_action_ddHelper_itemsCnt')
                div.append(spanCnt);

                spanCnt.textContent = (pseudoNumRows + ' ' + itemLabel);
                // Number of not shown rows
                const restRows = pseudoNumRows - maxRows;
                if (restRows > 0) {
                    moreRow.textContent = egw.lang(`${pseudoNumRows - maxRows} more ${itemLabel} selected ...`);
                }
                table.append(moreRow);
                break;
            }
        }

        const text = (document.createElement('div'))
        text.classList.add('et2_egw_action_ddHelper_tip');
        div.append(text);

        // Add notice of Ctrl key, if supported
        if ('draggable' in document.createElement('span') &&
            navigator && navigator.userAgent.indexOf('Chrome') >= 0 && egw.app_name() == 'filemanager') // currently only filemanager supports drag out
        {
            if (rows.length == 1)
            {
                text.textContent=(egw.lang('You may drag file out to your desktop', itemLabel));
            }
            else
            {
                text.textContent=(egw.lang('Note: If you drag out these selected rows to desktop only the first selected row will be downloaded.', itemLabel));
            }
        }
// Final html DOM return as helper structure
        return div;
    };

    registerAction: (_actionObjectInterface: EgwActionObjectInterface, _triggerCallback: Function, _context: any) => boolean = (_aoi, _callback, _context) => {
        const node = _aoi.getDOMNode() && _aoi.getDOMNode()[0] ? _aoi.getDOMNode()[0] : _aoi.getDOMNode();

        if (node) {
            // Prevent selection
            node.onselectstart = function () {
                return false;
            };
            if (!(window.FileReader && 'draggable' in document.createElement('span'))) {
                // No DnD support
                return;
            }

            // It shouldn't be so hard to get the action...
            let action = null;
            const groups = _context.getActionImplementationGroups();
            if (!groups.drag) {
                return;
            }

            // Bind mouse handlers
            //et2_dataview_view_aoi binds mousedown event in et2_dataview_rowAOI to "egwPreventSelect" function from egw_action_common via jQuery.mousedown
            //jQuery(node).off("mousedown",egwPreventSelect)
            //et2_dataview_view_aoi binds mousedown event in et2_dataview_rowAOI to "egwPreventSelect" function from egw_action_common via addEventListener
            //node.removeEventListener("mousedown",egwPreventSelect)
            node.addEventListener("mousedown", (event) => {
                if (_context.isSelection(event)) {
                    node.setAttribute("draggable", false);
                } else if (event.which != 3) {
                    document.getSelection().removeAllRanges();
                }
            })
            node.addEventListener("mouseup", (event) => {
				node.setAttribute("draggable", true);

                // Set cursor back to auto. Seems FF can't handle cursor reversion
                document.body.style.cursor = 'auto'
            })


            node.setAttribute('draggable', true);
            const ai = this
            const dragstart = function (event) {

                // The helper function is called before the start function
                // is evoked. Call the given callback function. The callback
                // function will gather the selected elements and action links
                // and call the doExecuteImplementation function. This
                // will call the onExecute function of the first action
                // in order to obtain the helper object (stored in ai.helper)
                // and the multiple dragDropTypes (ai.ddTypes)
                _callback.call(_context, false, ai);

				// Stop parent elements from also starting to drag if we're nested
				if(ai.selected.length)
				{
					event.stopPropagation();
				}

				if(action && egw.app_name() == 'filemanager')
				{
                    if (_context.isSelection(event)) return;

                    // Get all selected
                    const selected = ai.selected;

                    // Set file data
                    for (let i = 0; i < 1; i++) {
                        let d = selected[i].data || egw.dataGetUIDdata(selected[i].id).data || {};
                        if (d && d.mime && d.download_url) {
                            let url = d.download_url;

                            // NEED an absolute URL
                            if (url[0] == '/') url = egw.link(url);
                            // egw.link adds the webserver, but that might not be an absolute URL - try again
                            if (url[0] == '/') url = window.location.origin + url;
                            event.dataTransfer.setData("DownloadURL", d.mime + ':' + d.name + ':' + url);
                        }
                    }
                    event.dataTransfer.effectAllowed = 'copy';

                    if (event.dataTransfer.types.length == 0) {
                        // No file data? Abort: drag does nothing
                        event.preventDefault();
                        return;
                    }
                } else {
                    event.dataTransfer.effectAllowed = 'linkMove';
                }


                const data = {
                    ddTypes: ai.ddTypes,
                    selected: ai.selected.map((item) => {
                        return {id: item.id}
                    })
                };

                if (!ai.helper) {
                    ai.helper = ai.defaultDDHelper(ai.selected);
                }
                // Add a basic class to the helper in order to standardize the background layout
                ai.helper.classList.add('et2_egw_action_ddHelper', 'ui-draggable-dragging');
                document.body.append(ai.helper);
                this.classList.add('drag--moving');

                event.dataTransfer.setData('application/json', JSON.stringify(data))

				// Wait for any webComponents to finish
				let wait = [];
				const webComponents = [];
				const check = (element) =>
				{
					if(typeof element.updateComplete !== "undefined")
					{
						webComponents.push(element)
						element.requestUpdate();
						wait.push(element.updateComplete);
					}
					element.childNodes.forEach(child => check(child));
				}
				check(ai.helper);
				// Clumsily force widget update, since we can't do it async
				Promise.all(wait).then(() =>
				{
					wait = [];
					webComponents.forEach(e => wait.push(e.updateComplete));
					Promise.all(wait).then(() =>
					{
						event.dataTransfer.setDragImage(ai.helper, 12, 12);
					});
				});

				this.setAttribute('data-egwActionObjID', JSON.stringify(data.selected));
            };

            const dragend = (_) => {
                const helper = document.querySelector('.et2_egw_action_ddHelper');
                if (helper) helper.remove();
                const draggable = document.querySelector('.drag--moving');
                if (draggable) draggable.classList.remove('drag--moving');
                // cleanup drop hover class from all other DOMs if there's still anything left
                Array.from(document.getElementsByClassName('et2dropzone drop-hover')).forEach(_i=>{_i.classList.remove('drop-hover')})
				// Clean up selected
				ai.selected = [];
			};

            // Drag Event listeners
            node.addEventListener('dragstart', dragstart, false);
            node.addEventListener('dragend', dragend, false);


            return true;
        }
        return false;
    };

    unregisterAction: (_actionObjectInterface: EgwActionObjectInterface) => boolean =(_aoi) => {
        const node = _aoi.getDOMNode();

        if (node) {
            node.setAttribute('draggable', "false");
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
    executeImplementation: (_context: any, _selected: any, _links: any) => any = (_context, _selected, _links) => {
        // Reset the helper object of the action implementation
        this.helper = null;
        let hasLink = false;

        // Store the drag-drop types
        this.ddTypes = [];
        this.selected = _selected;

        // Call the onExecute event of the first actionObject
        for (const k in _links) {
            if (_links[k].visible) {
                hasLink = true;

                // Only execute the following code if a JS function is registered
                // for the action and this is the first action link
                if (!this.helper && _links[k].actionObj.onExecute.hasHandler()) {
                    this.helper = _links[k].actionObj.execute(_selected);
                }

                // Push the dragType of the associated action object onto the
                // drag type list - this allows an element to support multiple
                // drag/drop types.
                const type: string[] = Array.isArray(_links[k].actionObj.dragType)
                    ? _links[k].actionObj.dragType
                    : [_links[k].actionObj.dragType];
                for (const i of type) {
                    if (this.ddTypes.indexOf(i) === -1) {
                        this.ddTypes.push(i);
                    }
                }
            }
        }
        // If no helper has been defined, create a default one
        if (!this.helper && hasLink) {
            this.helper = this.defaultDDHelper(_selected);
        }

        return true;
    };
}

/**
 * @deprecated use upper case class
 */
export class egwDragActionImplementation extends EgwDragActionImplementation {
}

let _dragActionImpl = null

export function getDragImplementation():EgwDragActionImplementation {
    if (!_dragActionImpl) {
        _dragActionImpl = new EgwDragActionImplementation();
    }
    return _dragActionImpl
}
