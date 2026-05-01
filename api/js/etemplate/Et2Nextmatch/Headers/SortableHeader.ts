import {css, html} from "lit";
import {property} from "lit/decorators/property.js";
import {et2_INextmatchSortable} from "../../et2_extension_nextmatch";
import {Et2NextmatchHeader} from "./Header";
import {customElement} from "lit/decorators.js";

type SortMode = "none" | "asc" | "desc";

/**
 * Sortable nextmatch header.
 *
 * This is the webComponent counterpart of legacy `et2_nextmatch_sortheader`.
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
					cursor: pointer;
					display: inline-block;
					white-space: nowrap;
				}

				.nextmatch_sortheader {
					padding-right: var(--sl-spacing-large, 20px);
					overflow: hidden;
					text-overflow: ellipsis;
				}

				.nextmatch_sortheader:hover {
					text-decoration: underline;
				}

				.nextmatch_sortheader--marker {
					text-decoration: none;
					margin-left: var(--sl-spacing-small);
					font-size: var(--sl-font-size-large);
					background-repeat: no-repeat;
				}

				.asc {
					background-image: url(../../../node_modules/bootstrap-icons/icons/caret-up-fill.svg);
				}

				.desc {
					background-image: url(../../node_modules/bootstrap-icons/icons/caret-down-fill.svg);
				}
			`
		];
	}

	/**
	 * Default sort direction used on first click.
	 * Matches legacy behavior: anything other than DESC defaults to ASC.
	 */
	@property({type: String})
	sortmode : string = "";

	/**
	 * Current visual/apply sort mode.
	 */
	private _currentSortmode : SortMode = "none";

	/**
	 * Bind click handling once.
	 */
	constructor(...args : any[])
	{
		super(...args);
		this._handleClick = this._handleClick.bind(this);
	}

	/**
	 * Attach click listener for triggering nextmatch sorting.
	 */
	connectedCallback()
	{
		super.connectedCallback();
		this.addEventListener("click", this._handleClick);
	}

	/**
	 * Remove click listener on detach.
	 */
	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.removeEventListener("click", this._handleClick);
	}

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

	/**
	 * Trigger sort on click and persist sort preference like legacy header does.
	 */
	private _handleClick(event : MouseEvent)
	{
		if(this.disabled || !this.nextmatch)
		{
			return false;
		}
		event.preventDefault();
		event.stopPropagation();

		const defaultAsc = String(this.sortmode || "").toUpperCase() !== "DESC";
		this.nextmatch.sortBy(this.id, this._currentSortmode === "none" ? defaultAsc : undefined);

		try
		{
			this.egw().set_preference(
				this.nextmatch._get_appname(),
				this.nextmatch.options.template + "_sort",
				this.nextmatch.activeFilters["sort"]
			);
		}
		catch(e)
		{
		}
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
            <span class="nextmatch_sortheader ${this._currentSortmode} ${this.label ? "" : "et2_label_empty"}">
				${this.label || ""}
			</span>
            <span class="nextmatch_sortheader--marker ${indicator}"></span>
		`;
	}
}
