/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 */
import {
	EgwAction,
	EgwActionImplementation,
	EgwActionObjectInterface,
	EgwActionObject, EgwActionLink
} from "./egw_action";
import {getPopupImplementation} from "../egw_action_popup.js"; //TODO replace with .ts
import {EGW_AI_DRAG_OUT, EGW_AI_DRAG_OVER, EGW_AO_EXEC_THIS, EGW_AI_DRAG_ENTER} from "./egw_action_constants";
import {egw} from "../../jsapi/egw_global";
import {egwActionLink} from "../egw_action";


/**
 * The egwDragAction class overwrites the egwAction class and adds the new
 * "dragType" propery. The "onExecute" event of the drag action will be called
 * whenever dragging starts. The onExecute JS handler should return the
 * drag-drop helper object - otherwise an default helper will be generated.
 */
export class EgwDragAction extends EgwAction
{
	private dragType = "default"

	public set set_dragType(_value)
	{
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
	constructor(parent: EgwAction, _id, _caption, _iconUrl, _onExecute, _allowOnMultiple)
	{
		super(parent, _id, _caption, _iconUrl, _onExecute, _allowOnMultiple);
		this.type = "drag";
		this.hideOnDisabled = true;
	}
}

(() => {
	window._egwActionClasses.drag = {
		"actionConstructor": EgwDragAction.constructor, "implementation": getDragImplementation
	};
})()
let _dragActionImpl = null

export function getDragImplementation()
{
	if (!_dragActionImpl)
	{
		_dragActionImpl = new EgwDragActionImplementation();
	}
	return _dragActionImpl;
}

export class EgwDragActionImplementation implements EgwActionImplementation
{
	type: string = "drag";
	helper = null;
	ddTypes = [];
	selected = [];

	defaultDDHelper(_selected: EgwActionObject[]): HTMLDivElement
	{
		const table: HTMLTableElement = (document.createElement("table"));
		table.classList.add('egwGridView_grid et2_egw_action_ddHelper_row');
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
		//todo Type of _context
		const pseudoNumRows = (_selected[0]?._context?._selectionMgr?._selectAll) ?
			_selected[0]._context?._selectionMgr?._total : _selected.length;

		for (const egwActionObject of _selected)
		{
			const row: Node = (egwActionObject.interface.getDOMNode()).cloneNode(true);
			if (row)
			{
				rows.push(row);
				table.append(row);
			}
			index++;
			if (index == maxRows)
			{
				// Label to show number of items
				const spanCnt = (document.createElement('span'))
				spanCnt.classList.add('et2_egw_action_ddHelper_itemsCnt')
				div.append(spanCnt);

				spanCnt.textContent = (pseudoNumRows + ' ' + itemLabel);
				// Number of not shown rows
				const restRows = pseudoNumRows - maxRows;
				if (restRows > 0)
				{
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
			const key = ["Mac68K", "MacPPC", "MacIntel"].indexOf(window.navigator.platform) < 0 ?
				egw.lang('Alt') : egw.lang('Command ⌘');
			text.textContent = (egw.lang(`Hold [ ${key} ] and [${egw.lang('Shift ⇧')}] key to drag ${itemLabel} to your desktop`));
		}
		// Final html DOM return as helper structure
		return div;
	}


	registerAction(_actionObjectInterface: EgwActionObjectInterface, _triggerCallback: Function, _context: EgwActionObject): boolean
	{
		let node: any;
		if (_actionObjectInterface.getDOMNode() && _actionObjectInterface.getDOMNode()[0])
		{
			node = _actionObjectInterface.getDOMNode()[0];
		} else
		{
			node = _actionObjectInterface.getDOMNode();
		}

		if (node)
		{
			// Prevent selection
			node.onselectstart = function () {
				return false;
			};
			if (!(window.FileReader && 'draggable' in document.createElement('span')))
			{
				// No DnD support
				return;
			}

			// It shouldn't be so hard to get the action...
			var action = null;
			var groups = _context.getActionImplementationGroups();
			if (!groups.drag)
			{
				return;
			}
			// Disable file drag and drop, it conflicts with normal drag and drop
			for (var i = 0; false && i < groups.drag.length; i++)
			{
				// dragType 'file' says it can be dragged as a file
				if (groups.drag[i].link.actionObj.dragType == 'file' || groups.drag[i].link.actionObj.dragType.indexOf('file') > -1)
				{
					action = groups.drag[i].link.actionObj;
					break;
				}
			}

			if (!action)
			{
				// Use Ctrl key in order to select content
				jQuery(node).off("mousedown")
					.on({
						mousedown: function (event) {
							if (_context.isSelection(event))
							{
								node.setAttribute("draggable", false);
							} else if (event.which != 3)
							{
								document.getSelection().removeAllRanges();
							}
						},
						mouseup: function (event) {
							if (_context.isSelection(event))
							{
								// TODO: save and retrive selected range
								node.setAttribute("draggable", true);
							} else
							{
								node.setAttribute("draggable", true);
							}

							// Set cursor back to auto. Seems FF can't handle cursor reversion
							jQuery('body').css({cursor: 'auto'});
						}
					});
			}

			node.setAttribute('draggable', true);
			const ai = this
			const dragstart = function (event) {
				if (action)
				{
					if (_context.isSelection(event)) return;

					// Get all selected
					// Multiples aren't supported by event.dataTransfer, yet, so
					// select only the row they clicked on.
					var selected = [_context];
					_context.parent.setAllSelected(false);
					_context.setSelected(true);

					// Set file data
					for (let i = 0; i < selected.length; i++)
					{
						let d = selected[i].data || egw.dataGetUIDdata(selected[i].id).data || {};
						if (d && d.mime && d.download_url)
						{
							var url = d.download_url;

							// NEED an absolute URL
							if (url[0] == '/') url = egw.link(url);
							// egw.link adds the webserver, but that might not be an absolute URL - try again
							if (url[0] == '/') url = window.location.origin + url;

							// Unfortunately, dragging files is currently only supported by Chrome
							if (navigator && navigator.userAgent.indexOf('Chrome'))
							{
								event.dataTransfer.setData("DownloadURL", d.mime + ':' + d.name + ':' + url);
							} else
							{
								// Include URL as a fallback
								event.dataTransfer.setData("text/uri-list", url);
							}
						}
					}
					event.dataTransfer.effectAllowed = 'copy';

					if (event.dataTransfer.types.length == 0)
					{
						// No file data? Abort: drag does nothing
						event.preventDefault();
						return;
					}
				} else
				{
					event.dataTransfer.effectAllowed = 'linkMove';
				}
				// The helper function is called before the start function
				// is evoked. Call the given callback function. The callback
				// function will gather the selected elements and action links
				// and call the doExecuteImplementation function. This
				// will call the onExecute function of the first action
				// in order to obtain the helper object (stored in ai.helper)
				// and the multiple dragDropTypes (ai.ddTypes)
				_triggerCallback.call(_context, false, _actionObjectInterface);

				const data = {
					ddTypes: ai.ddTypes,
					selected: ai.selected.map((item) => {
						return {id: item.id}
					})
				};

				if (!ai.helper)
				{
					ai.helper = ai.defaultDDHelper(ai.selected);
				}
				// Add a basic class to the helper in order to standardize the background layout
				ai.helper[0].classList.add('et2_egw_action_ddHelper', 'ui-draggable-dragging');
				document.body.append(ai.helper[0]);
				this.classList.add('drag--moving');

				event.dataTransfer.setData('application/json', JSON.stringify(data))

				event.dataTransfer.setDragImage(ai.helper[0], 12, 12);

				this.setAttribute('data-egwActionObjID', JSON.stringify(data.selected));
			};

			const dragend = function (event) {
				const helper = document.querySelector('.et2_egw_action_ddHelper');
				if (helper) helper.remove();
				const draggable = document.querySelector('.drag--moving');
				if (draggable) draggable.classList.remove('drag--moving');
			};

			// Drag Event listeners
			node.addEventListener('dragstart', dragstart, false);
			node.addEventListener('dragend', dragend, false);


			return true;
		}
		return false;
	}

	unregisterAction(_actionObjectInterface: EgwActionObjectInterface): boolean
	{
		const node = _actionObjectInterface.getDOMNode();

		if (node)
		{
			node.setAttribute('draggable', "false");
			return true
		}
		return false
	}

	executeImplementation(_context: any, _selected: any, _links: any): any
	{
		// Reset the helper object of the action implementation
		this.helper = null;
		let hasLink = false;

		// Store the drag-drop types
		this.ddTypes = [];
		this.selected = _selected;

		// Call the onExecute event of the first actionObject
		for (const k in _links)
		{
			if (_links[k].visible)
			{
				hasLink = true;

				// Only execute the following code if a JS function is registered
				// for the action and this is the first action link
				if (!this.helper && _links[k].actionObj.onExecute.hasHandler())
				{
					this.helper = _links[k].actionObj.execute(_selected);
				}

				// Push the dragType of the associated action object onto the
				// drag type list - this allows an element to support multiple
				// drag/drop types.
				const type:string[] = Array.isArray(_links[k].actionObj.dragType)
					? _links[k].actionObj.dragType
					: [_links[k].actionObj.dragType];
				for (const i of type)
				{
					if (this.ddTypes.indexOf(i) === -1)
					{
						this.ddTypes.push(i);
					}
				}
			}
		}
		// If no helper has been defined, create a default one
		if (!this.helper && hasLink)
		{
			this.helper = this.defaultDDHelper(_selected);
		}
		return true
	};
}

/**
 * The egwDropAction class overwrites the egwAction class and adds the "acceptedTypes"
 * property. This array should contain all "dragTypes" the drop action is allowed to
 *
 * @param {egwAction} _id
 * @param {string} _handler
 * @param {string} _caption
 * @param {string} _icon
 * @param {(string|function)} _onExecute
 * @param {bool} _allowOnMultiple
 * @returns {egwDropAction}
 */
export class EgwDropAction extends EgwAction
{
	acceptedTypes = ["default"];
	canHaveChildren = ["drag","popup"];
	order = 0;
	group = 0;

	constructor(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
	{
		super(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple);
		this.type = "drop";
	}
}
