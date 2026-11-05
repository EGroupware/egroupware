/**
 * EGroupware eTemplate2 - A widget's own filter drawer, opened from its datagrid's column action
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

import {css, html, nothing, ReactiveController, ReactiveControllerHost, TemplateResult} from "lit";

export type Et2FilterDrawerHost = ReactiveControllerHost & HTMLElement & {
	egw() : { lang(label : string) : string }
};

export interface Et2FilterDrawerOptions
{
	/** Filter keys that are always set and so do not count as filtering, eg. sort or the record id */
	ignore? : string[];
	/** Called each time the drawer is opened, eg. to create the filterbox on first use */
	onOpen? : () => void;
	/** csspart name for the drawer; its panel is exported as `<part>__panel` */
	part? : string;
	/**
	 * Empty every filter, for the "Clear filters" action in the drawer header.  Without it there
	 * is no such action.
	 */
	clear? : () => unknown;
}

/**
 * Is anything set in these filters?
 *
 * "", null, undefined and false are not set, and neither is an array or object holding only
 * those - an empty `col_filter: {}` is not a filter.  Keys in `ignore` are skipped.
 */
export function hasActiveFilters(filters : Record<string, any> | null | undefined, ignore : string[] = []) : boolean
{
	const isSet = (value : any) : boolean =>
	{
		if(value === "" || value === null || typeof value === "undefined" || value === false)
		{
			return false;
		}
		if(Array.isArray(value))
		{
			return value.some(isSet);
		}
		if(typeof value === "object")
		{
			return Object.values(value).some(isSet);
		}
		return true;
	};
	return Object.entries(filters || {}).some(([key, value]) => !ignore.includes(key) && isSet(value));
}

/**
 * A filter drawer for a widget that has nowhere else to put its filters - a nextmatch in a popup, a
 * history log in an edit dialog.  There is no `egw-app` filter drawer there, and no header to put a
 * filter button in, so the drawer slides over the widget itself and is opened from the "Filters"
 * entry of the datagrid's column action.
 *
 * The host renders `render()` somewhere in its shadow root, includes `filterDrawerStyles`, gives
 * itself `position: relative` (the drawer is `contained`) and binds its datagrid:
 *
 * ```ts
 * <et2-datagrid .filterAction=${true}
 *               .filterActive=${this._filterDrawer.isActive(this._filters)}
 *               @et2-datagrid-filters=${this._filterDrawer.toggle}>
 * ```
 *
 * Like egw-app's drawer, the header offers "Clear filters" while anything is filtered, if the host
 * passes a `clear` option and tells `render()` whether something is filtered.  The filterbox
 * inside then needs no Clear button of its own.
 *
 * The drawer closes on Escape from anywhere inside it.  A contained sl-drawer does not, and
 * Et2Filterbox only closes a drawer on Escape when the key event's target carries one as
 * `filtersDrawer`, which a field inside the filterbox never does.  Hosts still expose `drawer` as
 * `filtersDrawer`, which is the name everything else looks for.
 *
 * Nothing is kept across a disconnect: the drawer starts closed again.
 */
export class Et2FilterDrawerController implements ReactiveController
{
	private host : Et2FilterDrawerHost;
	private options : Et2FilterDrawerOptions;
	private _open = false;

	constructor(host : Et2FilterDrawerHost, options : Et2FilterDrawerOptions = {})
	{
		this.host = host;
		this.options = options;
		host.addController(this);
	}

	hostDisconnected()
	{
		// Through the setter, so the rendered drawer is closed too when the host comes back
		this.open = false;
	}

	get open() : boolean
	{
		return this._open;
	}

	set open(open : boolean)
	{
		if(open === this._open)
		{
			return;
		}
		this._open = open;
		if(open)
		{
			this.options.onOpen?.();
		}
		this.host.requestUpdate();
	}

	/** The rendered sl-drawer, once the host has rendered it */
	get drawer() : any
	{
		return this.host.shadowRoot?.querySelector(".et2-filter-drawer") ?? null;
	}

	/** Bound, so it can be handed to an event binding as it is */
	toggle = () =>
	{
		this.open = !this.open;
	};

	close = () =>
	{
		this.open = false;
	};

	/** Are any of these filters set, apart from the `ignore` ones? */
	isActive(filters : Record<string, any> | null | undefined) : boolean
	{
		return hasActiveFilters(filters, this.options.ignore);
	}

	/**
	 * The filters to apply to empty these ones: every filter named and set to "", every column
	 * filter too, apart from the `ignore` ones.
	 *
	 * For a `clear` that hands this to its host's applyFilters(), rather than emptying the
	 * filterbox's widgets and applying what the filterbox then reports: a widget emptied that way
	 * can drop out of the filterbox's value altogether instead of reporting "" (a date range does),
	 * and applyFilters() only changes the filters it is given, so that filter would stay applied.
	 * The filterbox's widgets follow the applied filters, so they end up empty too.
	 */
	blank(filters : Record<string, any> | null | undefined) : Record<string, any>
	{
		const ignore = this.options.ignore || [];
		const blank : Record<string, any> = {col_filter: {}};
		for(const [key, value] of Object.entries(filters || {}))
		{
			if(ignore.includes(key))
			{
				continue;
			}
			if(key === "col_filter")
			{
				Object.keys(value || {}).forEach(column => blank.col_filter[column] = "");
				continue;
			}
			blank[key] = "";
		}
		return blank;
	}

	private _handleKeydown = (event : KeyboardEvent) =>
	{
		if(event.key === "Escape" && this._open)
		{
			event.stopPropagation();
			this.close();
		}
	};

	private _handleAfterHide = (event : Event) =>
	{
		// Only the drawer's own hide, not one bubbling up from inside a filter
		if(event.target === event.currentTarget)
		{
			this.close();
		}
	};

	/**
	 * The drawer, holding `content`.  `filtered` (see isActive()) shows the "Clear filters" action.
	 */
	render(content : unknown, filtered = false) : TemplateResult
	{
		const part = this.options.part || "filters";
		const clearLabel = this.host.egw().lang("Clear filters");
		return html`
            <sl-drawer part=${part}
                       exportparts="panel:${part}__panel"
                       class="et2-filter-drawer"
                       label=${this.host.egw().lang("Filters")}
                       contained
                       ?open=${this._open}
                       @keydown=${this._handleKeydown}
                       @sl-after-hide=${this._handleAfterHide}
            >
                ${filtered && this.options.clear ? html`
                    <et2-button-icon nosubmit
                                     slot="header-actions"
                                     name="x-circle-fill"
                                     label=${clearLabel}
                                     statustext=${clearLabel}
                                     @click=${() => this.options.clear!()}
                    ></et2-button-icon>` : nothing}
                ${content}
            </sl-drawer>
		`;
	}
}

/** Styles for the host's shadow root */
export const filterDrawerStyles = css`
	/* Contained, so it slides over the widget rather than the window */
	.et2-filter-drawer::part(panel) {
		--size: min(28em, 90%);
	}

	.et2-filter-drawer::part(body) {
		padding: var(--sl-spacing-medium);
	}

	/*
	 * One fixed label column for every filter, so they line up.  The filterbox's own default is a
	 * percentage of its width, which turns into "label above the field" on a narrow window - but
	 * this drawer is narrow on any window, and not every widget stacks: an account select keeps its
	 * full-width label beside the field and pushes the field out of the drawer.  ::slotted for a
	 * host that slots its filterbox into the drawer, the plain selector for one that puts it inside.
	 */
	.et2-filter-drawer ::slotted(et2-filterbox),
	.et2-filter-drawer et2-filterbox {
		--label-width: 7em;
		--gap-width: var(--sl-spacing-small);
	}

	/* Same fix egw-app needs: without it the header-action buttons sit off-centre */
	.et2-filter-drawer [slot="header-actions"] {
		display: flex;
	}
`;
