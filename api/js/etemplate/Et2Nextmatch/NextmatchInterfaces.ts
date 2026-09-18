/**
 * EGroupware eTemplate2 - contracts between a nextmatch and the widgets around it
 *
 * There are two nextmatch implementations - the legacy `et2_nextmatch` widget
 * (`et2_extension_nextmatch.ts`, tag `<nextmatch>`) and the `Et2Nextmatch` web component
 * (tag `<et2-nextmatch>`) - and the same header, filter and favourite widgets are used with
 * both.  Those widgets need to name the thing they are attached to and to declare which
 * callbacks a nextmatch may make on them, but they must not depend on either implementation:
 * importing the legacy widget for a type annotation drags its entire dependency tree (dataview,
 * row provider, controller, jQuery UI, ...) into every module that only wanted a method
 * signature, and makes the legacy file undeletable.  This module holds those contracts instead,
 * and imports nothing at all, so it is safe to import from anywhere.
 *
 * The legacy widget's own header contracts (`et2_INextmatchHeader`, `et2_INextmatchSortable`)
 * are NOT here - they are legacy-only and live in LegacyNextmatchInterfaces.ts, to be deleted
 * with the widget they serve.  What stays here is what describes a nextmatch either way.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 */

/**
 * The filter state a nextmatch sends to the server with every row request.
 *
 * Declared as a type alias rather than an interface on purpose: only aliases get TypeScript's
 * implicit index signature, which is what lets both nextmatch implementations - one typed with
 * named filter keys, one with a plain `Record<string, any>` - satisfy it without a cast.
 *
 * Keys beyond the named ones are the app's own `col_filter` siblings and extra settings, so the
 * index signature is part of the contract, not a shortcut.
 */
export type NextmatchActiveFilters = {
	search? : string,
	filter? : any,
	filter2? : any,
	col_filter? : { [key : string] : any },
	selectcols? : string[],
	searchletter? : string,
	selected? : string[],
	[key : string] : any
};

/**
 * What a header, filter or favourite widget may rely on from the nextmatch it is attached to.
 *
 * Both `et2_nextmatch` and `Et2Nextmatch` declare `implements NextmatchInterface`, so anything
 * added here has to exist on both - which is the point: it is the only part of a nextmatch the
 * surrounding widgets are allowed to reach for, and the compiler now says so instead of a
 * comment.  Members only one of the two has - the legacy widget's `header` and
 * `template_promise` - deliberately stay out, in LegacyNextmatchInterfaces.ts.
 */
export interface NextmatchInterface
{
	/**
	 * Current filter state.  Read-only here - change it through applyFilters() so the nextmatch
	 * can reload and notify its other widgets.
	 */
	readonly activeFilters : NextmatchActiveFilters;

	/**
	 * Merge the given filters into the active ones and reload.  Called with no argument, it
	 * reloads with the filters unchanged.
	 */
	applyFilters(set? : NextmatchActiveFilters, options? : { [key : string] : any }) : any;

	/**
	 * Sort by the given column id.  `update` false changes the sort indicator without reloading,
	 * for a caller that is about to reload anyway.
	 */
	sortBy(id : string, asc? : boolean, update? : boolean) : any;

	/**
	 * Drop the current sort order and go back to whatever the template asked for.
	 */
	resetSort() : any;

	/**
	 * Find a widget by id inside the nextmatch, eg. one of its header templates.
	 */
	getWidgetById(id : string) : any;

	/**
	 * The nextmatch's own DOM node, which is what filter-related CSS classes and the "et2-filter"
	 * event listener go on.
	 */
	getDOMNode(sender? : any) : HTMLElement;

	/**
	 * Child widgets, eg. to wait for each of them to finish updating before reading their values.
	 */
	getChildren() : any[];

	/**
	 * The widget's attributes as a plain bag, including the `settings` an app passed in.
	 *
	 * @deprecated read the individual properties instead - on the webComponent this is rebuilt
	 *	on every access and logs a deprecation trace.  Typed `any` because the two
	 *	implementations disagree about its shape; the callers left here index it by name.
	 */
	readonly options : any;
}

/**
 * What `Et2NextmatchDataProvider` needs from the widget that owns it.
 *
 * The provider was written against `Et2Nextmatch` because that was the only owner, but it only
 * ever reaches for the members below - and it is the piece worth reusing verbatim for any other
 * datagrid owner that fetches rows through `egw.dataFetch()`, because it carries the row-cache
 * keep-alive bookkeeping that stops egw's 5-minute sweep from evicting rows the grid can still
 * scroll back to.  `Et2Historylog` is the second such owner.
 *
 * Typed as an interface rather than the concrete class so a second owner does not have to
 * inherit a nextmatch's whole surface (actions, favourites, column preferences, print, ...) just
 * to reuse its data plumbing.  Members the provider only touches on a real nextmatch are
 * optional, and it guards each of them.
 */
export interface NextmatchDataProviderHost
{
	/** Widget id, sent to the server as the form_name that resolves the row source. */
	readonly id : string;

	/** Current filter state sent with every row request. */
	readonly activeFilters : NextmatchActiveFilters;

	egw() : any;

	getInstanceManager() : any;

	getArrayMgr(which : string) : any;

	getWidgetById(id : string) : any;

	/** Lit's own controller registration; absent on the plain mocks the provider's tests use. */
	addController?(controller : any) : void;

	/**
	 * Sort by a column, called when a response echoes an `order` the host does not already have.
	 * A host with a fixed sort order (eg. the history log) implements this as a no-op.
	 */
	sortBy(id : string, asc? : boolean, update? : boolean) : any;

	/**
	 * Re-evaluate column visibility after the response wrote new flags into the content manager.
	 * A host without expression-driven columns implements this as a no-op.
	 */
	refreshColumnVisibility?() : void;

	/** App settings bag; read for `dataStorePrefix` and `row_id`. */
	readonly settings? : Record<string, any>;

	/** DOM/widget-tree access the provider uses to push select-options at sibling widgets. */
	closest(selector : string) : Element | null;

	getAttribute(name : string) : string | null;

	getParent?() : any;
}
