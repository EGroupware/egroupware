/**
 * Tests for the generic SearchMixin.
 *
 * It had no coverage at all, and it is about to become load-bearing for every select in the
 * product (see doc/ai/projects/et2select-searchmixin-removal.md).  These test the mixin's own
 * contract through a minimal host, rather than through Et2TreeDropdown / Et2VfsSelectDialog,
 * so a failure points at the mixin and not at a consumer.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture, html, oneEvent} from '@open-wc/testing';
import * as sinon from 'sinon';
import {LitElement} from "lit";
import {Et2InputWidget} from "../../Et2InputWidget/Et2InputWidget";
import {SearchMixin, SearchResult, SearchResultsInterface} from "../SearchMixin";

type Constructor<T = LitElement> = new (...args : any[]) => T;

interface TestResults extends SearchResultsInterface<SearchResult> {}

/**
 * Minimal host: the mixin renders its own search box and result list, all a host has to do is
 * put them somewhere and say what selecting a result means.
 */
class TestSearchWidget
	extends SearchMixin<Constructor<any> & typeof LitElement, SearchResult, TestResults>(Et2InputWidget(LitElement))
{
	// Options the host offers for local searching
	public localOptions : SearchResult[] = [];

	protected localSearch<SearchResult>(search : string, searchOptions : object, localOptions : SearchResult[] = []) : Promise<SearchResult[]>
	{
		return super.localSearch(search, searchOptions, <any>this.localOptions);
	}

	protected searchResultSelected()
	{
		super.searchResultSelected();
		this.value = this.selectedResults.map(el => el.value);
	}

	render()
	{
		return html`
            ${this.searchInputTemplate()}
            ${this.searchResultsTemplate()}`;
	}
}

// Casts needed because this host passes `Constructor<any> & typeof LitElement` as the mixin's T
// (the same shape Et2TreeDropdown / Et2VfsSelectDialog use to satisfy its constraint), and that
// `any` erases the element type.  Not fixable from the return type - see SearchMixin's own note.
customElements.define("test-search-widget", <CustomElementConstructor><unknown>TestSearchWidget);

const egwStub = {
	ajaxUrl: url => url,
	decodePath: url => url,
	// Must match the real egw.lang() (api/js/jsapi/egw_lang.ts:109): null/undefined -> "".
	// searchMatch() calls lang() on fields an option may not have (eg. title), and relies on
	// always getting a string back - a stub returning undefined fakes a crash that cannot happen.
	lang: i => i === null || typeof i === "undefined" ? "" : String(i),
	debug: (_level, ..._args) => {},
	link: (l, _o) => l,
	preference: () => null,
	request: () => Promise.resolve({results: [], total: 0}),
	tooltipUnbind: () => {},
	webserverUrl: "",
	window: window
};

// @ts-ignore
window.egw = egwStub;

async function makeWidget() : Promise<TestSearchWidget>
{
	const element = <TestSearchWidget><unknown>await fixture(`<test-search-widget></test-search-widget>`);
	sinon.stub(element, "egw").returns(<any>window.egw);
	await element.updateComplete;
	return element;
}

/** Type into the mixin's own search box and run the search to completion */
async function search(element : TestSearchWidget, text : string)
{
	(<any>element).shadowRoot.querySelector("#search").value = text;
	await element.startSearch();
	await (<any>element)._searchPromise;
	await element.updateComplete;
}

function resultValues(element : TestSearchWidget) : string[]
{
	return (<any>element)._searchResults.map(r => r.value);
}

describe("SearchMixin", () =>
{
	it("is applied and renders both templates", async() =>
	{
		const element = await makeWidget();

		assert.instanceOf(element, TestSearchWidget);
		assert.exists(element.shadowRoot.querySelector("#search"), "No search input rendered");
		assert.exists(element.shadowRoot.querySelector("#listbox"), "No result list rendered");
	});

	it("defaults search on, searchUrl empty", async() =>
	{
		const element = await makeWidget();

		assert.isTrue(element.search, "search defaulted off");
		assert.equal(element.searchUrl, "", "searchUrl was not empty");
	});
});

describe("SearchMixin.searchMatch()", () =>
{
	let element : TestSearchWidget;

	beforeEach(async() => { element = await makeWidget(); });

	const option = <SearchResult>{value: "abc", label: "Alpha Bravo", title: "Charlie"};

	it("matches on label", () =>
	{
		assert.isTrue(element.searchMatch("Alpha", {}, option));
	});

	it("matches on value", () =>
	{
		assert.isTrue(element.searchMatch("abc", {}, option));
	});

	it("matches on title", () =>
	{
		assert.isTrue(element.searchMatch("Charlie", {}, option));
	});

	it("is case-insensitive", () =>
	{
		assert.isTrue(element.searchMatch("alpha", {}, option));
	});

	it("rejects a non-match", () =>
	{
		assert.isFalse(element.searchMatch("zulu", {}, option));
	});

	it("rejects an option with no value", () =>
	{
		assert.isFalse(element.searchMatch("anything", {}, <SearchResult>{label: "no value"}));
	});

	it("rejects a parent when leafOnly is set", () =>
	{
		const parent = <SearchResult>{value: "folder", label: "Folder", children: [{value: "kid", label: "Kid"}]};

		assert.isTrue(element.searchMatch("Folder", {}, parent), "Parent should match without leafOnly");

		(<any>element).leafOnly = true;
		assert.isFalse(element.searchMatch("Folder", {}, parent), "leafOnly should reject a parent");
		assert.isTrue(element.searchMatch("Kid", {}, <SearchResult>{value: "kid", label: "Kid"}),
			"leafOnly should still accept a leaf");
	});
});

describe("SearchMixin local search", () =>
{
	let element : TestSearchWidget;

	beforeEach(async() =>
	{
		element = await makeWidget();
		element.localOptions = [
			{value: "one", label: "One"},
			{value: "two", label: "Two"},
			{value: "group", label: "Group", children: [{value: "nested", label: "Nested One"}]}
		];
	});

	it("returns only matching options", async() =>
	{
		await search(element, "Two");

		assert.deepEqual(resultValues(element), ["two"]);
	});

	it("searches into children", async() =>
	{
		await search(element, "Nested");

		assert.include(resultValues(element), "nested", "Did not search into children");
	});

	it("returns nothing for a non-match", async() =>
	{
		await search(element, "zulu");

		assert.isEmpty(resultValues(element));
	});

	it("clears previous results when searching again", async() =>
	{
		await search(element, "One");
		assert.isNotEmpty(resultValues(element));

		await search(element, "zulu");
		assert.isEmpty(resultValues(element), "Previous results were not cleared");
	});
});

describe("SearchMixin remote search", () =>
{
	afterEach(() => { window.egw.request = egwStub.request; });

	it("does not call the server without a searchUrl", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve({results: [], total: 0}));
		window.egw.request = request;

		const element = await makeWidget();
		await search(element, "anything");

		assert.isTrue(request.notCalled, "Called the server with no searchUrl");
	});

	it("sends the search text and a default row limit", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve({results: [], total: 0}));
		window.egw.request = request;

		const element = await makeWidget();
		element.searchUrl = "test";
		await search(element, "find me");

		assert.isTrue(request.called, "Server was not called");
		assert.equal(request.firstCall.args[1][0], "find me", "Search text was not sent");
		assert.equal(request.firstCall.args[1][1].num_rows, 100, "Default num_rows was not sent");
	});

	it("lets searchOptions override the class defaults", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve({results: [], total: 0}));
		window.egw.request = request;

		const element = await makeWidget();
		element.searchUrl = "test";
		(<any>element)._classSearchOptions = {num_rows: 25, app: "infolog"};
		element.searchOptions = {num_rows: 5};
		await search(element, "anything");

		const sent = request.firstCall.args[1][1];
		assert.equal(sent.num_rows, 5, "searchOptions did not win over _classSearchOptions");
		assert.equal(sent.app, "infolog", "_classSearchOptions were not sent");
	});

	it("takes a function as searchUrl, instead of a request", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve({results: [], total: 0}));
		window.egw.request = request;

		const element = await makeWidget();
		const supplied = sinon.fake.returns(Promise.resolve({
			results: [{value: "from_fn", label: "From fn"}], total: 1
		}));
		element.searchUrl = <any>supplied;
		await search(element, "anything");

		assert.isTrue(request.notCalled, "Went to the server despite a function searchUrl");
		assert.isTrue(supplied.called, "The function was not called");
		assert.deepEqual(supplied.firstCall.args[0], "anything", "Search text was not passed");
		assert.deepEqual(resultValues(element), ["from_fn"]);
	});

	it("resolves an app.x.y searchUrl through applyFunc()", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve({results: [], total: 0}));
		window.egw.request = request;
		const applyFunc = sinon.fake.returns(Promise.resolve({
			results: [{value: "from_app", label: "From app"}], total: 1
		}));
		(<any>window.egw).applyFunc = applyFunc;

		const element = await makeWidget();
		element.searchUrl = "app.myapp.mySearch";
		await search(element, "anything");

		assert.isTrue(request.notCalled, "Went to the server despite an app. searchUrl");
		assert.equal(applyFunc.firstCall.args[0], "app.myapp.mySearch", "Wrong function name");
		assert.deepEqual(applyFunc.firstCall.args[1], ["anything", {}], "Wrong arguments");
		assert.deepEqual(resultValues(element), ["from_app"]);

		delete (<any>window.egw).applyFunc;
	});

	it("puts remote results in the result list", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve({
			results: [{value: "remote", label: "Remote"}],
			total: 1
		}));

		const element = await makeWidget();
		element.searchUrl = "test";
		await search(element, "remote");

		assert.deepEqual(resultValues(element), ["remote"]);
	});

	it("keeps the server's total, so 'n more' can be shown", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve({
			results: [{value: "remote", label: "Remote"}],
			total: 42
		}));

		const element = await makeWidget();
		element.searchUrl = "test";
		await search(element, "remote");

		assert.equal((<any>element)._totalResults, 42, "Total from the server was not kept");
	});

	it("does not duplicate a remote result that is already a local match", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve({
			results: [{value: "one", label: "One"}],
			total: 1
		}));

		const element = await makeWidget();
		element.localOptions = [{value: "one", label: "One"}];
		element.searchUrl = "test";
		await search(element, "One");

		assert.deepEqual(resultValues(element), ["one"], "Remote result duplicated the local one");
	});
});

describe("SearchMixin bad response shape", () =>
{
	let warnSpy : sinon.SinonSpy;

	beforeEach(() =>
	{
		// et2_warnOnce() routes through egw().debug("warn", ...)
		warnSpy = sinon.fake();
		(<any>window.egw).debug = (level, ...args) => { if(level === "warn") warnSpy(...args); };
	});

	afterEach(() =>
	{
		(<any>window.egw).debug = egwStub.debug;
		window.egw.request = egwStub.request;
	});

	it("warns but still uses a bare array", async() =>
	{
		// what an older endpoint, or the obvious app-method implementation, returns
		window.egw.request = sinon.fake.returns(Promise.resolve([
			{value: "one", label: "One"}, {value: "two", label: "Two"}
		]));

		const element = await makeWidget();
		element.searchUrl = "legacy.endpoint.a";
		await search(element, "anything");

		assert.deepEqual(resultValues(element), ["one", "two"], "A bare array should still be used");
		assert.isTrue(warnSpy.called, "No warning for a bare array");
		assert.include(warnSpy.firstCall.args[0], "legacy.endpoint.a", "Warning did not name the source");
		assert.include(warnSpy.firstCall.args[0], "bare array", "Warning did not say what was wrong");
	});

	it("has no real total for a bare array, so cannot claim there are more", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve([{value: "one", label: "One"}]));

		const element = await makeWidget();
		element.searchUrl = "legacy.endpoint.total";
		await search(element, "anything");

		// total can only be what arrived, so total - taken == 0 and "n more..." stays away
		assert.equal((<any>element)._totalResults, 1, "Total should be what arrived");
		assert.equal((<any>element)._totalResults - (<any>element)._searchResults.length, 0);
	});

	it("ignores a response it cannot interpret at all", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve("just a string"));

		const element = await makeWidget();
		element.searchUrl = "legacy.endpoint.junk";
		await search(element, "anything");

		assert.isEmpty(resultValues(element), "Unusable response should give no results");
		assert.isTrue(warnSpy.called, "No warning for an unusable response");
		assert.include(warnSpy.firstCall.args[0], "ignoring", "Warning did not say it was ignored");
	});

	it("warns once per source, not once per search", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve([{value: "one", label: "One"}]));

		const element = await makeWidget();
		element.searchUrl = "legacy.endpoint.b";
		for(let i = 0; i < 5; i++)
		{
			await search(element, "anything" + i);
		}

		assert.equal(warnSpy.callCount, 1, `Warned ${warnSpy.callCount} times for one source`);
	});

	it("names a callback searchUrl in the warning", async() =>
	{
		const element = await makeWidget();
		element.searchUrl = <any>function mySupplierC() { return Promise.resolve([{value: "x", label: "X"}]); };
		await search(element, "anything");

		assert.isTrue(warnSpy.called, "No warning for a callback returning the wrong shape");
		assert.include(warnSpy.firstCall.args[0], "mySupplierC", "Warning did not name the callback");
	});

	it("stays silent for the documented shape", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve({
			results: [{value: "one", label: "One"}], total: 1
		}));

		const element = await makeWidget();
		element.searchUrl = "modern.endpoint";
		await search(element, "anything");

		assert.isTrue(warnSpy.notCalled, "Warned about a correctly-shaped response");
		assert.deepEqual(resultValues(element), ["one"]);
	});
});

describe("SearchMixin selection", () =>
{
	let element : TestSearchWidget;

	beforeEach(async() =>
	{
		element = await makeWidget();
		element.localOptions = [
			{value: "one", label: "One"},
			{value: "two", label: "Two"}
		];
		await search(element, "o");
	});

	it("offers the matches as results", async() =>
	{
		assert.includeMembers(resultValues(element), ["one", "two"]);
	});

	it("selecting a result puts it in selectedResults", async() =>
	{
		const option = element.shadowRoot.querySelector("#listbox sl-option");
		assert.exists(option, "No result rendered to select");

		option.dispatchEvent(new Event("mouseup", {bubbles: true}));
		await element.updateComplete;

		assert.lengthOf(element.selectedResults, 1, "Selection was not recorded");
	});

	it("selecting a result fires et2-select", async() =>
	{
		const option = element.shadowRoot.querySelector("#listbox sl-option");
		const listener = oneEvent(<EventTarget><unknown>element, "et2-select");

		option.dispatchEvent(new Event("mouseup", {bubbles: true}));
		await listener;

		assert.isNotEmpty(element.selectedResults, "Nothing was selected");
	});

	it("clears the search box after a selection", async() =>
	{
		const option = element.shadowRoot.querySelector("#listbox sl-option");
		option.dispatchEvent(new Event("mouseup", {bubbles: true}));
		await element.updateComplete;

		assert.equal((<any>element).shadowRoot.querySelector("#search").value, "",
			"Search box still held the text");
	});
});

describe("SearchMixin.getValueAsArray()", () =>
{
	it("wraps a single value", async() =>
	{
		const element = await makeWidget();
		element.value = "one";

		assert.deepEqual(element.getValueAsArray(), ["one"]);
	});

	it("passes an array through", async() =>
	{
		const element = await makeWidget();
		element.value = ["one", "two"];

		assert.deepEqual(element.getValueAsArray(), ["one", "two"]);
	});

	it("returns empty for empty-ish values", async() =>
	{
		const element = await makeWidget();

		for(const empty of ["", null, undefined, "null"])
		{
			element.value = <any>empty;
			assert.deepEqual(element.getValueAsArray(), [], `${JSON.stringify(empty)} was not treated as empty`);
		}
	});

	/**
	 * The mixin used to define getValueAsArray() unconditionally, shadowing the host's.  That is
	 * silent breakage for any host with its own idea of it - notably Et2WidgetWithSelectMixin,
	 * which keeps "" when there is an emptyLabel so the empty option still renders.
	 */
	it("defers to the host's own getValueAsArray() when there is one", async() =>
	{
		const element = await makeWidget();
		const hostAnswer = ["whatever", "the", "host", "says"];

		// Walk down to the prototype *below* the mixin's - that is the "host" whose implementation
		// super.getValueAsArray() has to reach.  Injecting onto the mixin's own prototype instead
		// just replaces the mixin's method and passes whether it delegates or not.
		const mixinProto = Object.getPrototypeOf(Object.getPrototypeOf(element));
		const hostProto = Object.getPrototypeOf(mixinProto);
		assert.isFalse(Object.prototype.hasOwnProperty.call(hostProto, "getValueAsArray"),
			"Test setup: host prototype already has getValueAsArray, wrong level");
		assert.isTrue(Object.prototype.hasOwnProperty.call(mixinProto, "getValueAsArray"),
			"Test setup: mixin prototype has no getValueAsArray, wrong level");

		hostProto.getValueAsArray = () => hostAnswer;

		try
		{
			element.value = "ignored";
			assert.deepEqual(element.getValueAsArray(), hostAnswer,
				"Mixin shadowed the host's getValueAsArray()");
		}
		finally
		{
			delete hostProto.getValueAsArray;
		}
	});
});
