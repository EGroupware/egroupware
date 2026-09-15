/**
 * Shared helpers for the nextmatch header tests.
 *
 * The header widgets all sit on the same two seams - the `et2_INextmatchHeader` /
 * `et2_INextmatchSortable` contract (`setNextmatch()`, `set_sortmode()`) and the composed
 * `et2-nextmatch-sort` / `et2-nextmatch-filter` events - so every header test needs the same
 * three things: a callable egw stub, a nextmatch double recording what the legacy fallback did,
 * and a way to let the queueMicrotask()-deferred fallback run.
 *
 * Keeping them here rather than in each file means a change to the header/nextmatch contract is
 * one edit, not five.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

/**
 * Minimal egw stub.  Tests needing more can spread this and override before installing.
 */
export const egwStub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => null,
	set_preference: () => {},
	app_name: () => "addressbook",
	link: (url : string) => url,
	image: () => "",
	debug: () => {},
	ajaxUrl: (url : string) => url,
	decodePath: (url : string) => url,
	open_link: () => {},
	uid: () => "test",
	webserverUrl: "",
	window: window,

	// Headers built on a searching select (account, entry, custom) reach for these during
	// connectedCallback()/willUpdate(), before a test can stub the instance, so they have to be
	// on the global rather than injected per element.
	request: () => Promise.resolve([]),
	getCache: (_name : string) => ({}),
	// StaticOptions.cached_server_side() reads response.response[0].data off this
	json: (_method : string, _params : any[]) => ({
		sendRequest: () => Promise.resolve({response: [{data: []}]})
	})
};

/**
 * Install a callable egw stub on window.
 *
 * egw is used both as `egw(...)` and as `egw.lang(...)` depending on the call site, so the stub
 * has to be a function *and* carry the members - setting only one of the two leaves half the
 * widget code calling undefined.
 */
export function installEgwStub(overrides : Partial<typeof egwStub> = {})
{
	const stub = {...egwStub, ...overrides};
	const callable = function() { return stub; };
	Object.assign(callable, stub);
	// @ts-ignore - window.egw's real type is the full egw API
	window.egw = callable;
	return stub;
}

/**
 * Both header families emit their event synchronously but defer the legacy
 * nextmatch fallback into a queueMicrotask(), so assertions about `sortBy()` /
 * `applyFilters()` have to yield the microtask queue first.
 */
export async function waitForBubblingHandlers()
{
	await Promise.resolve();
	await Promise.resolve();
	await Promise.resolve();
}

/**
 * Recording double for the legacy et2_nextmatch widget.
 *
 * Only the members the headers actually touch are implemented; anything else the headers start
 * using should fail loudly here rather than silently no-op.
 */
export function fakeNextmatch(activeFilters : any = {})
{
	const calls : {sortBy : any[], resetSort : number, applyFilters : any[]} = {
		sortBy: [],
		resetSort: 0,
		applyFilters: []
	};
	const nextmatch : any = {
		activeFilters: {col_filter: {}, ...activeFilters},
		options: {template: "addressbook.index.rows"},
		_get_appname: () => "addressbook",
		sortBy: (id : string, asc : boolean, update : boolean) => calls.sortBy.push({id, asc, update}),
		resetSort: () => calls.resetSort++,
		applyFilters: (filters : any) => calls.applyFilters.push(filters),
		calls
	};
	return nextmatch;
}

/**
 * Append to a live host element: a header only wires its click/change listeners on
 * connectedCallback, and a detached element never completes its first update.
 */
export async function inHost<T extends HTMLElement>(element : T, setup? : (element : T) => void) : Promise<{ host : HTMLElement, element : T }>
{
	const host = document.createElement("div");
	document.body.append(host);
	setup?.(element);
	host.append(element);
	await (<any>element).updateComplete;
	return {host, element};
}
