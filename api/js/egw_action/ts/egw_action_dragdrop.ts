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
	egw_getObjectManager,
	EgwActionObjectInterface,
	EgwActionObject
} from "./egw_action";
import {getPopupImplementation} from "../egw_action_popup.js"; //TODO replace with .ts
import {EGW_AI_DRAG_OUT, EGW_AI_DRAG_OVER, EGW_AO_EXEC_THIS, EGW_AI_DRAG_ENTER} from "./egw_action_constants";
import {egw} from "../../jsapi/egw_global";


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

	defaultDDHelper(_selected:EgwActionObject[]): HTMLDivElement
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
				// Lable to show number of items
				const spanCnt = (document.createElement('span'))
					spanCnt.classList.add('et2_egw_action_ddHelper_itemsCnt')
					div.append(spanCnt);

				spanCnt.textContent = (pseudoNumRows + ' ' + itemLabel);
				// Number of not shown rows
				const restRows = pseudoNumRows - maxRows;
				if (restRows>0)
				{
					moreRow.textContent = egw.lang(`${pseudoNumRows - maxRows} more ${itemLabel} selected ...`);
				}
				table.append(moreRow);
				break;
			}
		}

		var text = jQuery(document.createElement('div')).addClass('et2_egw_action_ddHelper_tip');
		div.append(text);

		// Add notice of Ctrl key, if supported
		if ('draggable' in document.createElement('span') &&
			navigator && navigator.userAgent.indexOf('Chrome') >= 0 && egw.app_name() == 'filemanager') // currently only filemanager supports drag out
		{
			var key = ["Mac68K", "MacPPC", "MacIntel"].indexOf(window.navigator.platform) < 0 ?
				egw.lang('Alt') : egw.lang('Command ⌘');
			text.text(egw.lang('Hold [%1] and [%2] key to drag %3 to your desktop', key, egw.lang('Shift ⇧'), itemLabel));
		}
		// Final html DOM return as helper structor
		return div;
	}

	executeImplementation(_context: any, _selected: any, _links: any): any
	{
	}

	registerAction(_actionObjectInterface: EgwActionObjectInterface, _triggerCallback: Function, _context: object): boolean
	{
		return false;
	}

	unregisterAction(_actionObjectInterface: EgwActionObjectInterface): boolean
	{
		return false;
	}

}