/**
 * Tests for the Et2Select subclasses whose options come from the server.
 *
 * Each of these hands a *type string* to `Select::ajax_get_options` on the PHP side and trusts
 * whatever comes back.  That string is a real cross-language contract with no compiler and, until
 * now, no test: rename one end and the select silently comes up empty, which in an edit dialog
 * looks like "this record has no category" rather than like a bug.
 *
 * Behaviour under test:
 * - every server-backed subclass requests its documented option type on connect
 * - the returned options land in `select_options` and render
 * - Et2SelectState re-fetches when its country code changes, and defaults to DE
 * - the shared per-type cache means a second identical widget does not re-request
 *
 * Setup strategy:
 * `egw().json()` is replaced with a recorder that captures `(method, params)` and resolves with
 * canned options in the envelope shape `cached_server_side()` unwraps
 * (`{response: [{data: [...]}]}`).  The cache object `egw().getCache('Et2Select')` returns is
 * stable per test (the shared helper stub hands back a fresh one each call, which would hide the
 * caching behaviour), and is reset between tests so request counts are meaningful.
 * These widgets fetch from `connectedCallback()`, so simply rendering the fixture is enough to
 * trigger the request; `fetchComplete` is awaited before asserting on options.
 *
 * Pass criteria:
 * Explicit assertions on the recorded request type, on the applied options, and on the request
 * count for the caching case.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";
import {egwStub} from "./helpers";

const OPTIONS = [{value: "a", label: "Alpha"}, {value: "b", label: "Beta"}];

let requests : { method : string, type : string, options : string }[] = [];
let cache : { [key : string] : any } = {};

const stub = {
	...egwStub,
	getCache: (_name : string) => cache,
	json: (method : string, params : any[]) =>
	{
		requests.push({method, type: params[0], options: params[1]});
		return {sendRequest: () => Promise.resolve({response: [{data: OPTIONS.map(o => ({...o}))}]})};
	},
	app_name: () => "addressbook"
};
const callableEgw = function() { return stub; };
Object.assign(callableEgw, stub);
// @ts-ignore
window.egw = callableEgw;

import "../Select/Et2SelectApp";
// Et2SelectCategory is a tree, not a listbox - its shadow root needs et2-tree registered
// or firstUpdated() dies on this._tree.requestUpdate.
import "../../Et2Tree/Et2Tree";
import "../Select/Et2SelectCategory";
import "../Select/Et2SelectCountry";
import "../Select/Et2SelectDayOfWeek";
import "../Select/Et2SelectLang";
import "../Select/Et2SelectState";
import "../Select/Et2SelectTimezone";
import {Et2SelectState} from "../Select/Et2SelectState";

/**
 * tag -> the option type it must ask the server for.
 * These strings are matched by Api\Etemplate\Widget\Select::ajax_get_options() on the PHP side.
 */
const SERVER_BACKED : [string, string][] = [
	["et2-select-app", "select-app"],
	["et2-select-cat", "select-cat"],
	["et2-select-country", "select-country"],
	["et2-select-dow", "select-dow"],
	["et2-select-lang", "select-lang"],
	["et2-select-state", "select-state"],
	["et2-select-timezone", "select-timezone"]
];

async function connected(tag : string, attributes = "")
{
	const element = <any>await fixture(`<${tag} ${attributes}></${tag}>`);
	await element.updateComplete;
	await element.fetchComplete;
	await element.updateComplete;
	return element;
}

describe("Server-backed Et2Select subclasses", () =>
{
	beforeEach(() =>
	{
		requests = [];
		cache = {};
	});

	SERVER_BACKED.forEach(([tag, type]) =>
	{
		it(`${tag} asks the server for "${type}"`, async() =>
		{
			await connected(tag);

			assert.isNotEmpty(requests, `${tag} should fetch its options on connect`);
			assert.equal(
				requests[0].method,
				"EGroupware\\Api\\Etemplate\\Widget\\Select::ajax_get_options",
				"the options endpoint changed"
			);
			assert.equal(
				requests[0].type,
				type,
				`${tag} must request "${type}" - this string is matched server-side`
			);
		});

		it(`${tag} applies the options it gets back`, async() =>
		{
			const element = await connected(tag);

			assert.includeMembers(
				(element.select_options || []).map(o => "" + o.value),
				["a", "b"],
				`${tag} should offer the options the server returned`
			);
		});
	});

	describe("Et2SelectState", () =>
	{
		it("defaults to DE", async() =>
		{
			await connected("et2-select-state");

			assert.equal(requests[0].options, "DE", "state list is country specific and defaults to DE");
		});

		it("re-fetches when the country changes", async() =>
		{
			const element = <Et2SelectState>await connected("et2-select-state");
			const before = requests.length;

			element.countryCode = "AT";
			await (<any>element).fetchComplete;

			assert.isAbove(requests.length, before, "changing country must fetch that country's states");
			assert.equal(requests[requests.length - 1].options, "AT", "the new country code should be sent");
		});

		it("still accepts the legacy set_country_code()", async() =>
		{
			const element = <Et2SelectState>await connected("et2-select-state");
			element.set_country_code("FR");
			await (<any>element).fetchComplete;

			assert.equal(requests[requests.length - 1].options, "FR", "the deprecated setter must keep working");
		});
	});

	describe("option cache", () =>
	{
		it("does not re-request for an identical second widget", async() =>
		{
			await connected("et2-select-country");
			const afterFirst = requests.length;
			assert.equal(afterFirst, 1, "the first widget should fetch");

			await connected("et2-select-country");

			assert.equal(
				requests.length,
				afterFirst,
				"a second identical select must come out of the cache - otherwise every nextmatch row refetches"
			);
		});

		it("does request again for a different option set", async() =>
		{
			await connected("et2-select-state");
			const afterFirst = requests.length;

			await connected("et2-select-state", 'countryCode="AT"');

			assert.isAbove(requests.length, afterFirst, "a different country is a different cache entry");
		});
	});
});
