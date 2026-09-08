/**
 * EGroupware eTemplate2 - normalise legacy search responses
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {cleanSelectOptions, SelectOption} from "./FindSelectOptions";
import {SearchResultsInterface} from "../Et2Widget/SearchMixin";

/**
 * SearchMixin expects a search endpoint to answer with {results: [...], total: n}.
 *
 * Most of ours do not, and predate that contract.  Select::ajax_search echoes a bare JSON array,
 * with "total" spliced in among the numeric keys when it has one; other endpoints return a plain
 * object keyed by value.  We cannot simply fix them all and move on either: custom fields let any
 * installation point searchUrl at their own method (see et2_extension_customfields.ts and
 * Et2CustomfieldWidgetMapper.ts, which both take field.values["@"]), and EPL/third-party apps do
 * the same, so the old shapes have to keep working indefinitely.
 *
 * What we can do is stop shipping new ones, and say so when we meet an old one - hence the
 * warning.  It is not only hygiene: turning it on and clicking through the app produces the
 * inventory of endpoints still to be updated.
 */

/**
 * Endpoints we have already complained about, so a search-per-keystroke does not flood the console.
 * Mirrors Et2Nextmatch's _warnDeprecatedOnce().
 */
const warned = new Set<string>();

/**
 * Reset the warned-endpoint list.  Tests only.
 *
 * @internal
 */
export function _resetLegacyWarnings() : void
{
	warned.clear();
}

/**
 * Does this look like the modern {results: [...], total: n} response?
 */
function isModern(raw : any) : boolean
{
	return raw !== null && typeof raw == "object" && !Array.isArray(raw) && Array.isArray(raw.results);
}

/**
 * Legacy option groups arrive as an entry whose *value* is the array of children.  The modern
 * shape puts them in `children`, which is what SearchMixin.localSearch() recurses into.
 */
function fixOptionGroups(options : SelectOption[]) : SelectOption[]
{
	return options.map(option =>
	{
		if(!Array.isArray(option.value))
		{
			return option;
		}
		const children = fixOptionGroups(cleanSelectOptions(<any>option.value));
		return <SelectOption>{
			...option,
			// keep the legacy shape too - Et2Select still reads groups out of value
			value: <any>children,
			children: children,
			hasChildren: true
		};
	});
}

/**
 * Bring a search response into the shape SearchMixin expects, whatever the server sent.
 *
 * Handles, in order:
 * - {results: [...], total: n}   already modern, returned untouched and *silently*
 * - [...]                        a bare array, total is its length
 * - {total: n, 0: {...}, ...}    results on their own keys with total mixed in among them
 * - {key: {...}, ...}            a plain object keyed by value
 *
 * Anything that needed converting warns once per searchUrl.  A correct response must stay silent,
 * or the warning becomes noise and gets ignored - which defeats the point of having it.
 *
 * @param raw whatever egw().request() resolved to
 * @param searchUrl the endpoint it came from, used to identify it in the warning
 */
export function normalizeLegacySearchResults(raw : any, searchUrl : string = "") : SearchResultsInterface<SelectOption>
{
	// Already correct - say nothing
	if(isModern(raw))
	{
		return <SearchResultsInterface<SelectOption>>{
			results: fixOptionGroups(raw.results),
			total: typeof raw.total == "number" ? raw.total : raw.results.length,
			...(typeof raw.message != "undefined" ? {message: raw.message} : {})
		};
	}

	let results : SelectOption[];
	let total : number;

	if(Array.isArray(raw))
	{
		results = cleanSelectOptions(raw);
		total = raw.length;
	}
	else if(raw !== null && typeof raw == "object")
	{
		// "total" is not an option, it is the count spliced in beside them
		const {total: rawTotal, ...rest} = raw;
		results = cleanSelectOptions(rest);
		total = typeof rawTotal == "undefined" ? results.length : parseInt(rawTotal);
	}
	else
	{
		// null, undefined, a string - nothing usable
		return <SearchResultsInterface<SelectOption>>{results: [], total: 0};
	}

	warnLegacyResponse(searchUrl);

	return <SearchResultsInterface<SelectOption>>{
		results: fixOptionGroups(results),
		total: isNaN(total) ? results.length : total
	};
}

/**
 * Complain about one endpoint, once.
 */
function warnLegacyResponse(searchUrl : string) : void
{
	const key = searchUrl || "(no searchUrl)";
	if(warned.has(key))
	{
		return;
	}
	warned.add(key);

	console.warn(
		`searchUrl "${key}" returned a legacy search response; {results: [...], total: n} expected. ` +
		`Update the endpoint - client-side normalisation stays for custom fields, but nothing we ship should need it.`
	);
}
