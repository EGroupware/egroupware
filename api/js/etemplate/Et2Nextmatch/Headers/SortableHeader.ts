import {css, html} from "lit";
import {property} from "lit/decorators/property.js";
import {et2_INextmatchSortable} from "../../et2_extension_nextmatch";
import {Et2NextmatchHeader} from "./Header";
import {customElement} from "lit/decorators/custom-element.js";
import {ET2_NEXTMATCH_SORT_EVENT, Et2NextmatchSortEventDetail} from "./events";

type SortMode = "none" | "asc" | "desc";

/**
 * @summary Sortable nextmatch header.
 *
 * This is the webComponent counterpart of legacy `et2_nextmatch_sortheader`.
 * It emits `et2-nextmatch-sort` on user click and falls back to legacy direct
 * sorting when no modern listener handles the event.
 *
 * @event {CustomEvent<Et2NextmatchSortEventDetail>} et2-nextmatch-sort - Emitted when the user activates the header.
 *
 * @csspart base - Sortable header label element.
 * @csspart label - Sortable header label text.
 * @csspart sort-indicator - Visual sort direction marker.
 */
@customElement("et2-nextmatch-sortheader")
export class Et2NextmatchSortableHeader extends Et2NextmatchHeader implements et2_INextmatchSortable
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					width: 100%;
					cursor: pointer;
					display: inline-flex;
					white-space: nowrap;
				}

				.nextmatch_sortheader {
					padding-right: var(--sl-spacing-large, 20px);
					overflow: hidden;
					text-overflow: ellipsis;
					flex: 1 1 auto;
				}

				.nextmatch_sortheader:hover {
					text-decoration: underline;
				}

				.nextmatch_sortheader--marker {
					text-decoration: none;
					background-repeat: no-repeat;
				}
			`
		];
	}

	/**
	 * Default sort direction used on first click.
	 * Anything other than DESC defaults to ASC.
	 */
	@property({type: String})
	sortmode : string = "";

	/**
	 * Current visual/apply sort mode.
	 */
	private _currentSortmode : SortMode = "none";

	/**
	 * Legacy compatibility wrapper.
	 * Legacy paths may call `set_sortmode()` before using the interface method.
	 */
	set_sortmode(mode : SortMode)
	{
		if(this.nextmatch)
		{
			// Keep legacy semantics: when actively bound to nextmatch, external setter is ignored.
			return;
		}
		this.setSortmode(mode);
	}

	/**
	 * Apply visual sort mode from nextmatch.
	 */
	setSortmode(mode : SortMode)
	{
		this._currentSortmode = mode || "none";
		this.requestUpdate();
	}

	private _sortId() : string
	{
		return this.id || this.getAttribute("id") || "";
	}

	private _nextSortmode() : SortMode
	{
		const defaultAsc = String(this.sortmode || "").toUpperCase() !== "DESC";
		switch(this._currentSortmode)
		{
			case "none":
				return defaultAsc ? "asc" : "desc";
			case "asc":
				return defaultAsc ? "desc" : "none";
			case "desc":
				return defaultAsc ? "none" : "asc";
		}
	}

	/**
	 * Trigger sort on click and persist sort preference like legacy header does.
	 */
	_handleClick(event : MouseEvent) : boolean
	{
		if((this as any).disabled)
		{
			return false;
		}
		event.preventDefault();

		const sortId = this._sortId();
		const nextSortmode = this._nextSortmode();
		const sortEvent = new CustomEvent<Et2NextmatchSortEventDetail>(ET2_NEXTMATCH_SORT_EVENT, {
			bubbles: true,
			composed: true,
			cancelable: true,
			detail: {
				id: sortId,
				asc: nextSortmode === "none" ? undefined : nextSortmode === "asc",
				clear: nextSortmode === "none"
			}
		});
		this.dispatchEvent(sortEvent);
		queueMicrotask(() =>
		{
			// Legacy fallback: if no listener handled the event and we are attached to legacy nextmatch,
			// apply the old direct sort behavior.
			if(sortEvent.defaultPrevented)
			{
				return;
			}
			if(!this.nextmatch)
			{
				return;
			}
			if(sortEvent.detail.clear && typeof this.nextmatch.resetSort === "function")
			{
				this.nextmatch.resetSort();
			}
			else
			{
				this.nextmatch.sortBy(sortId, sortEvent.detail.asc, sortEvent.detail.update);
			}
			try
			{
					this.egw().set_preference(
					(this.nextmatch as any)._get_appname(),
					this.nextmatch.options.template + "_sort",
					this.nextmatch.activeFilters["sort"]
				);
			}
			catch(e)
			{
			}
		});
		return true;
	}

	/**
	 * Render sortable caption with mode classes used by existing nextmatch CSS conventions.
	 */
	render()
	{
		let indicator = "";
		switch(this._currentSortmode)
		{
			case "asc":
				indicator = "bi-caret-up-fill"
				break;
			case "desc":
				indicator = "bi-caret-down-fill"
				break;
		}

		return html`
            <span class="nextmatch_sortheader ${this._currentSortmode} ${this.label ? "" : "et2_label_empty"}"
                  part="base label">
				${this.label || ""}
			</span>
            <span class="nextmatch_sortheader--marker ${indicator}" part="sort-indicator"></span>
		`;
	}
}
