/**
 * Shared helpers for Et2Select tests.
 *
 * These exist to keep the tests from asserting against Et2Select's *internals*.  Where a test
 * previously reached for `element._searchInputNode` or `element.select.querySelectorAll("sl-option")`
 * it should go through here instead, so that when the search implementation changes we edit these
 * few functions rather than every assertion in every file.
 *
 * The rule this enforces: a test that has to change when the implementation changes was testing the
 * implementation, not the behaviour.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {elementUpdated} from '@open-wc/testing';
import {Et2Select} from "../Et2Select";

/**
 * Et2Select's search & free-entry members live in SelectSearchMixin, whose returned constructor
 * type omits most of them (SearchMixinInterface declares only a handful), so they do not survive
 * into Et2Select's public type and TS does not believe they exist.  Tests need them.
 *
 * Beyond silencing the errors, this list is useful on its own: it is the surface the replacement
 * mixin has to expose in Step 4 of the removal plan.  If something here becomes unnecessary, that
 * is a member nothing actually depended on.
 *
 * @see doc/ai/projects/et2select-searchmixin-removal.md
 */
export type SearchableSelect = Et2Select & {
	search : boolean;
	searchUrl : string;
	searchOptions : any;
	allowFreeEntries : boolean;
	editModeEnabled : boolean;
	readonly searchEnabled : boolean;
	startSearch() : Promise<void>;
	clearSearch() : void;
	createFreeEntry(text : string) : boolean;
	validateFreeEntry(text : string) : boolean;
	getValueAsArray() : string[];
	select_options : any[];
	value : any;
	multiple : boolean;
	emptyLabel : string;
};

/**
 * Helpers take anything select-shaped: the Et2Select subclasses are not assignable to Et2Select
 * either, for the same mixin-erasure reason.
 */
type AnySelect = any;

/**
 * Minimal egw stub.  Tests that need more can spread this and override.
 */
export const egwStub = {
	ajaxUrl: url => url,
	decodePath: url => url,
	lang: i => i + "*",
	link: l => l,
	preference: (_p, _app) => null,
	request: () => Promise.resolve([]),
	tooltipUnbind: () => {},
	webserverUrl: "",
	window: window,

	// Subclasses that pull static options from the server (eg. Et2SelectApp) touch these during
	// connectedCallback(), before a test can stub the instance, so they have to be on the global.
	getCache: (_name : string) => ({}),
	// StaticOptions.cached_server_side() reads response.response[0].data off this
	json: (_method : string, _params : any[]) => ({
		sendRequest: () => Promise.resolve({response: [{data: []}]})
	}),
	app_name: () => "api",
	uid: () => "test",
	debug: () => {},
	image: () => "",
	set_preference: () => {},
	open_link: () => {},
	tooltipBind: () => {}
};

/**
 * Et2Select defers building the full <sl-option> list until the user interacts with it
 * (_optionsActivated).  Tests that assert on the rendered option set need it forced on.
 *
 * This is an internal, and deliberately so - it is the one place tests are allowed to know about it.
 */
export async function activateOptions(select : AnySelect) : Promise<void>
{
	(<any>select)._optionsActivated = true;
	select.requestUpdate("_optionsActivated");
	await elementUpdated(select);
	await select.updateComplete;
}

/**
 * The search input, however it is currently implemented.
 */
function searchNode(select : AnySelect) : any
{
	return (<any>select)._searchInputNode;
}

/**
 * Sinon's fake DOM does not always give et2-textbox a select(), which focus handling calls.
 */
export function ensureSearchInputHasSelect(select : AnySelect) : void
{
	const input = searchNode(select);
	if(input && typeof input.select !== "function")
	{
		input.select = () => {};
	}
}

/**
 * Wait until the search UI is actually present & ready.
 * Call in beforeEach() for anything that searches.
 */
export async function searchReady(select : AnySelect) : Promise<void>
{
	await select.updateComplete;
	await activateOptions(select);
	await searchNode(select)?.updateComplete;
	ensureSearchInputHasSelect(select);
	await elementUpdated(select);
}

/**
 * Type into the search box and run the search to completion.
 */
export async function searchFor(select : AnySelect, text : string) : Promise<void>
{
	searchNode(select).value = text;

	await (<any>select).startSearch();

	await elementUpdated(select);
	await select.updateComplete;
}

/**
 * Type into the search box *without* triggering the search, so a test can assert on
 * the debounce / minimum-character behaviour itself.
 */
export function typeSearch(select : AnySelect, text : string) : void
{
	searchNode(select).value = text;
}

/**
 * Type into the search box the way a user does.
 *
 * Ordinary typing reaches the widget as `sl-input`, not keydown - keydown only sees the value
 * from *before* the character was inserted, so the widget listens to both for different things
 * (see SearchMixin._handleSearchInput()).  Use this for "the user typed", and searchKey() for
 * Enter / Escape / arrows / tag breaks.
 */
export function typeInSearch(select : AnySelect, text : string) : void
{
	const input = searchNode(select);
	input.value = text;
	input.dispatchEvent(new CustomEvent("sl-input"));
}

/**
 * Send a key to the search input.
 */
export function searchKey(select : AnySelect, key : string) : void
{
	searchNode(select).dispatchEvent(new KeyboardEvent("keydown", {key, bubbles: true}));
}

/**
 * Current contents of the search box.
 */
export function searchValue(select : AnySelect) : string
{
	return searchNode(select)?.value ?? "";
}

/**
 * Is the search UI present at all?  (readonly & non-searching selects should have none)
 */
export function hasSearchUI(select : AnySelect) : boolean
{
	return !!searchNode(select);
}

/**
 * The option values currently offered to the user, in order.
 *
 * This is the main assertion target: "what can the user pick right now".  It deliberately says
 * nothing about *where* those options live.
 */
export function visibleOptionValues(select : AnySelect) : string[]
{
	const options = Array.from((<any>select).select?.querySelectorAll("sl-option") ?? []);
	return options
		// an option the widget has rendered but hidden (eg. a search non-match) is not "offered"
		.filter((option : any) => !option.hasAttribute("hidden") && option.style?.display !== "none")
		// sl-option cannot hold a space in its value attribute, so Et2Select encodes them as "___"
		// (Et2Select.ts:1122).  Tests should not have to know that.
		.map((option : any) => option.value.replace(/___/g, " "));
}

/**
 * The labels of the options currently offered, in order.
 */
export function visibleOptionLabels(select : AnySelect) : string[]
{
	const options = Array.from((<any>select).select?.querySelectorAll("sl-option") ?? []);
	return options.map((option : any) => option.textContent.trim());
}

/**
 * The option elements themselves, for the rare test that needs to inspect one directly
 * (eg. computed style).
 */
export function optionNodes(select : AnySelect) : any[]
{
	return Array.from((<any>select).select?.querySelectorAll("sl-option") ?? []);
}

/**
 * Find one offered option by value, or null.
 */
export function findOption(select : AnySelect, value : string) : any
{
	// see visibleOptionValues() for why the value is encoded
	const encoded = value.replace(/ /g, "___");
	return (<any>select).select?.querySelector("[value='" + encoded + "']") ?? null;
}

/**
 * Pick an option the way a user would.
 */
export async function pickOption(select : AnySelect, value : string) : Promise<void>
{
	const option = findOption(select, value);
	if(!option)
	{
		throw new Error("No option '" + value + "' to pick.  Offered: " + visibleOptionValues(select).join(", "));
	}
	option.dispatchEvent(new Event("mouseup", {bubbles: true}));

	await elementUpdated(select);
	await select.updateComplete;
}

/**
 * The values shown as tags (multiple=true), in order.
 */
export function tagValues(select : AnySelect) : string[]
{
	const tags = (<any>select).select?.combobox?.querySelectorAll("et2-tag") ?? [];
	return Array.from(tags).map((tag : any) => tag.value);
}

/**
 * The tag elements themselves, for tests that have to interact with one (eg. its remove button).
 */
export function tagNodes(select : AnySelect) : any[]
{
	return Array.from((<any>select).select?.combobox?.querySelectorAll("et2-tag") ?? []);
}

/**
 * Wait for all currently-rendered tags to finish updating.
 */
export async function tagsReady(select : AnySelect) : Promise<void>
{
	const tags = (<any>select).select?.combobox?.querySelectorAll("et2-tag") ?? [];
	await Promise.all(Array.from(tags).map((tag : any) => tag.updateComplete));
}
