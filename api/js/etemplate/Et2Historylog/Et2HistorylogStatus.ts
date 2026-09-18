/**
 * EGroupware eTemplate2 - history log: which field changed
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {css, html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import type {Et2Historylog} from "./Et2Historylog";

/**
 * The "Changed" cell of the history log: the label of the field this row records a change to.
 *
 * A plain `<et2-select readonly>` looks like the obvious way to do this, and it is what the legacy
 * widget used - but it does not work in a datagrid row.  The row renderer only calls
 * transformAttributes() on a row widget that has row-bound attributes recorded for it
 * (Et2DatagridRowRenderer.applyRowElementAttributes() skips any element whose attribute set is
 * empty), and resolving a select's options from `sel_options` happens inside transformAttributes().
 * A status select therefore silently renders blank unless something unrelated happens to give it a
 * row attribute.
 *
 * Reading the label straight off the owner's registry avoids that entirely, and keeps one source
 * for the label the column shows and the one its filter offers.
 *
 * Bound with `id="$row"`, like the other history-log cells.
 */
@customElement("et2-historylog-status")
export class Et2HistorylogStatus extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			...(super.styles || []),
			css`
				:host {
					display: block;
					min-width: 0;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
				}
			`
		];
	}

	private _row : any = {};

	set value(row : any)
	{
		this._row = row && typeof row === "object" ? row : {};
		this.requestUpdate("value");
	}

	get value() : any
	{
		return this._row;
	}

	isDirty() : boolean
	{
		return false;
	}

	getValue() : null
	{
		return null;
	}

	/**
	 * Walk up shadow boundaries to the owning history log - row widgets have no widget-tree
	 * parent, and rows live in the datagrid's shadow DOM.
	 */
	private _owner() : Et2Historylog | null
	{
		let element : Element | null = this;
		while(element)
		{
			const found = element.closest("et2-historylog");
			if(found)
			{
				return <Et2Historylog><unknown>found;
			}
			const root = element.getRootNode();
			element = root instanceof ShadowRoot ? root.host : null;
		}
		return null;
	}

	render()
	{
		const status = this._row?.status;
		if(!status)
		{
			return html``;
		}
		const label = this._owner()?.widgetRegistry?.labels().find(l => l.value == status)?.label;
		// Fall back to the raw field code rather than an empty cell: an app that forgot a label
		// still leaves the user able to tell one row's field from another's.
		return html`<span part="label" title=${label || status}>${label || status}</span>`;
	}
}
