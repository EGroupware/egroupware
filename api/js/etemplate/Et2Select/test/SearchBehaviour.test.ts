/**
 * Behaviour-level tests for the parts of Et2Select's search that had no coverage:
 * missing-option resolution, readonly gating, clearSearch(), the minimum-character gate,
 * the maxmatchs preference, and the legacy server response shapes.
 *
 * These all move or change in the SearchMixin conversion, so they need to be pinned first.
 * See doc/ai/projects/et2select-searchmixin-removal.md §8b.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture, html} from '@open-wc/testing';
import * as sinon from 'sinon';
import {Et2Select} from "../Et2Select";
import {Et2Textbox} from "../../Et2Textbox/Et2Textbox";
import {
	SearchableSelect,
	egwStub,
	hasSearchUI,
	searchFor,
	searchKey,
	searchReady,
	searchValue,
	typeInSearch,
	visibleOptionValues
} from "./helpers";

let keep_import : Et2Textbox = null;

// @ts-ignore
window.egw = {...egwStub};

before(async() =>
{
	const warmup = await fixture<Et2Select>(html`
        <et2-select></et2-select>`);
	assert.instanceOf(warmup, Et2Select);
	warmup.remove();
});

async function makeSelect(attributes : string, options = `
    <option value="one">One</option>
    <option value="two">Two</option>`) : Promise<SearchableSelect>
{
	// searchUrl is {attribute: false} - it can be a function, so it is only ever a JS property.
	// etemplate assigns .xet attributes as properties too (Et2Widget.transformAttributes), so
	// pull it out of the attribute string and set it the same way.
	const searchUrl = attributes.match(/searchUrl="([^"]*)"/)?.[1];
	attributes = attributes.replace(/\s*searchUrl="[^"]*"/, "");

	const element = <SearchableSelect>await fixture(`<et2-select ${attributes}>${options}</et2-select>`);
	element.loadFromXML(element);
	sinon.stub(element, "egw").returns(window.egw);
	if(typeof searchUrl !== "undefined")
	{
		element.searchUrl = searchUrl;
	}
	await element.updateComplete;
	return element;
}

describe("Search is gated", () =>
{
	it("has no search UI without search or searchUrl", async() =>
	{
		const element = await makeSelect(`label="Plain"`);
		assert.isFalse(element.searchEnabled, "searchEnabled with nothing set");
		assert.isFalse(hasSearchUI(element), "Plain select got a search box");
	});

	it("is enabled by search alone", async() =>
	{
		const element = await makeSelect(`label="Search" search="true"`);
		assert.isTrue(element.searchEnabled, "search=true did not enable searching");
	});

	it("is enabled by searchUrl alone", async() =>
	{
		const element = await makeSelect(`label="Url" searchUrl="test"`);
		assert.isTrue(element.searchEnabled, "searchUrl did not enable searching");
	});

	it("is disabled by readonly, even with search set", async() =>
	{
		const element = await makeSelect(`label="RO" search="true" readonly="true"`);
		assert.isFalse(element.searchEnabled, "readonly select was still searchable");
		assert.isFalse(hasSearchUI(element), "readonly select got a search box");
	});

	it("is disabled by readonly, even with allowFreeEntries set", async() =>
	{
		const element = await makeSelect(`label="RO" allowFreeEntries="true" readonly="true"`);
		assert.isFalse(hasSearchUI(element), "readonly select got a search box");
	});
});

describe("Minimum characters", () =>
{
	let element : SearchableSelect;
	let clock;
	let searchSpy;

	beforeEach(async() =>
	{
		clock = sinon.useFakeTimers();
		element = await makeSelect(`label="Search" search="true"`);
		await searchReady(element);
		searchSpy = sinon.spy(element, "startSearch");
	});

	afterEach(() => clock.restore());

	it("does not search on one character", async() =>
	{
		typeInSearch(element, "o");
		clock.tick(1000);

		assert.isTrue(searchSpy.notCalled, "Searched with only one character typed");
	});

	it("searches on two characters", async() =>
	{
		typeInSearch(element, "on");
		clock.tick(1000);

		assert.isTrue(searchSpy.calledOnce, "Did not search after two characters");
	});

	it("waits for the user to stop typing", async() =>
	{
		typeInSearch(element, "on");
		clock.tick(100);
		assert.isTrue(searchSpy.notCalled, "Searched before the debounce elapsed");

		typeInSearch(element, "one");
		clock.tick(100);
		assert.isTrue(searchSpy.notCalled, "Debounce was not restarted by more typing");

		clock.tick(1000);
		assert.isTrue(searchSpy.calledOnce, "Only one search should run for one burst of typing");
	});

	it("searches immediately on Enter, without waiting", async() =>
	{
		typeInSearch(element, "on");
		searchKey(element, "Enter");

		assert.isTrue(searchSpy.calledOnce, "Enter did not search immediately");
	});
});

describe("clearSearch()", () =>
{
	let element : SearchableSelect;

	beforeEach(async() =>
	{
		element = await makeSelect(`label="Search" search="true"`);
		await searchReady(element);
	});

	it("empties the search box", async() =>
	{
		await searchFor(element, "one");
		assert.equal(searchValue(element), "one", "Search text was not set up");

		element.clearSearch();
		await element.updateComplete;

		assert.equal(searchValue(element), "", "clearSearch() left text in the search box");
	});

	it("puts the non-matching options back", async() =>
	{
		await searchFor(element, "one");
		assert.notInclude(visibleOptionValues(element), "two", "Search did not filter anything out");

		element.clearSearch();
		await element.updateComplete;

		assert.includeMembers(visibleOptionValues(element), ["one", "two"], "clearSearch() did not restore the options");
	});

	it("keeps the value", async() =>
	{
		element.value = "one";
		await element.updateComplete;

		await searchFor(element, "two");
		element.clearSearch();
		await element.updateComplete;

		assert.equal(element.value, "one", "clearSearch() lost the value");
	});
});

describe("Legacy server response shapes", () =>
{
	let element : SearchableSelect;

	beforeEach(async() =>
	{
		element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		await searchReady(element);
	});

	afterEach(() => { window.egw.request = egwStub.request; });

	it("accepts a bare array", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve([
			{value: "remote_one", label: "Remote One"}
		]));

		await searchFor(element, "remote");

		assert.include(visibleOptionValues(element), "remote_one", "Bare-array response was not used");
	});

	it("accepts an object with a total spliced in", async() =>
	{
		// This is what Select::ajax_search does when it has a total - the results stay on
		// numeric keys and "total" is added alongside them.
		window.egw.request = sinon.fake.returns(Promise.resolve({
			0: {value: "remote_one", label: "Remote One"},
			1: {value: "remote_two", label: "Remote Two"},
			total: 57
		}));

		await searchFor(element, "remote");

		assert.includeMembers(visibleOptionValues(element), ["remote_one", "remote_two"],
			"Response with a total was not used");
	});

	it("reports more results than it shows when the server says so", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve({
			0: {value: "remote_one", label: "Remote One"},
			total: 57
		}));

		await searchFor(element, "remote");

		// NB reaching for a private field - the only way to see the count without the "n more"
		// element, which needs a rendered dropdown.  Renamed to _totalResults when Et2Select moved
		// onto the generic SearchMixin.
		assert.equal((<any>element)._totalResults, 57, "Total from the server was not kept");
	});

	it("does not count a result we already had as one more result", async() =>
	{
		// Server says 57 exist and sends two, but one is already a local option.  It renders once,
		// so it must also only be counted once - otherwise "n more..." over-reports.
		window.egw.request = sinon.fake.returns(Promise.resolve({
			0: {value: "remote_one", label: "Remote One"},
			1: {value: "one", label: "One"},
			total: 57
		}));

		await searchFor(element, "one");

		assert.equal((<any>element)._totalResults, 56, "Duplicate was still counted in the total");
	});

	it("counts 'n more' against what the server has, not what is on screen", async() =>
	{
		// "one" is a local option and matches the search, so it renders as a match - but it is not
		// one of the server's 57, so it must not be subtracted from them.  Two remote results were
		// taken, so 55 remain.
		window.egw.request = sinon.fake.returns(Promise.resolve({
			0: {value: "remote_one", label: "One Remote"},
			1: {value: "remote_two", label: "One More Remote"},
			total: 57
		}));

		await searchFor(element, "one");

		const shown = (<any>element)._searchResults.length;
		assert.equal(shown, 2, "Expected both remote results to be taken");
		assert.equal((<any>element)._totalResults - shown, 55, "Wrong 'n more' count");
	});

	it("accepts option groups as an array value", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve([
			{
				value: [
					{value: "child_one", label: "Child One"},
					{value: "child_two", label: "Child Two"}
				],
				label: "A group"
			}
		]));

		await searchFor(element, "child");

		assert.includeMembers(visibleOptionValues(element), ["child_one", "child_two"],
			"Grouped results were not flattened into selectable options");
	});

	it("does not duplicate a remote result that is already a local option", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve([
			{value: "one", label: "One"}
		]));

		await searchFor(element, "one");

		assert.lengthOf(visibleOptionValues(element).filter(v => v == "one"), 1,
			"Remote result duplicated an existing local option");
	});
});

describe("Search request options", () =>
{
	afterEach(() =>
	{
		window.egw.request = egwStub.request;
		window.egw.preference = egwStub.preference;
	});

	it("limits results using the maxmatchs preference", async() =>
	{
		window.egw.preference = (name, app) => name == "maxmatchs" && app == "common" ? "25" : null;
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		await searchReady(element);
		await searchFor(element, "anything");

		assert.isTrue(request.called, "No request was sent");
		const sentOptions = request.firstCall.args[1][1];
		assert.equal(sentOptions.num_rows, 25, "maxmatchs preference was not used as num_rows");
	});

	it("passes searchOptions through to the server", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		element.searchOptions = {app: "infolog", extra: "thing"};
		await searchReady(element);
		await searchFor(element, "anything");

		const sentOptions = request.firstCall.args[1][1];
		assert.equal(sentOptions.app, "infolog", "searchOptions.app was not sent");
		assert.equal(sentOptions.extra, "thing", "Extra searchOptions were not sent");
	});

	it("sends the search text", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		await searchReady(element);
		await searchFor(element, "look for me");

		assert.equal(request.firstCall.args[1][0], "look for me", "Search text was not sent");
	});

	it("takes a function as searchUrl instead of going to the server", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Callback" search="true"`);
		element.searchUrl = <any>((search, options) => Promise.resolve([{value: "from_fn", label: "From fn"}]));
		await searchReady(element);
		await searchFor(element, "anything");

		assert.isTrue(request.notCalled, "Went to the server despite a function searchUrl");
		assert.include(visibleOptionValues(element), "from_fn", "Function results were not used");
	});

	it("resolves an app.x.y searchUrl through egw().applyFunc()", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;
		const applyFunc = sinon.fake.returns(Promise.resolve([{value: "from_app", label: "From app"}]));
		window.egw.applyFunc = applyFunc;

		const element = await makeSelect(`label="App" search="true"`);
		element.searchUrl = "app.myapp.mySearch";
		await searchReady(element);
		await searchFor(element, "anything");

		assert.isTrue(request.notCalled, "Went to the server despite an app. searchUrl");
		assert.isTrue(applyFunc.called, "applyFunc was not used");
		assert.equal(applyFunc.firstCall.args[0], "app.myapp.mySearch");
		assert.include(visibleOptionValues(element), "from_app", "applyFunc results were not used");

		delete window.egw.applyFunc;
	});

	it("does not go to the server without a searchUrl", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Local only" search="true"`);
		await searchReady(element);
		await searchFor(element, "one");

		assert.isTrue(request.notCalled, "Searched the server with no searchUrl set");
		assert.include(visibleOptionValues(element), "one", "Local search did not work");
	});
});

describe("Missing options", () =>
{
	afterEach(() => { window.egw.request = egwStub.request; });

	it("asks the server for a value that is not in the options", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([
			{value: "unknown", label: "Resolved Label"}
		]));
		window.egw.request = request;

		const element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		element.value = "unknown";
		await element.updateComplete;
		await new Promise(resolve => setTimeout(resolve, 0));
		await element.updateComplete;

		assert.isTrue(request.called, "Did not ask the server about the missing value");
		assert.equal(element.value, "unknown", "Value was lost while resolving it");
	});

	it("does not ask about a value it already has an option for", async() =>
	{
		const request = sinon.fake.returns(Promise.resolve([]));
		window.egw.request = request;

		const element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		element.value = "one";
		await element.updateComplete;
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isTrue(request.notCalled, "Asked the server about a value it already had");
	});

	it("drops a value the server does not know either", async() =>
	{
		window.egw.request = sinon.fake.returns(Promise.resolve([]));

		const element = await makeSelect(`label="Search" search="true" searchUrl="test"`);
		element.value = "nonexistent";
		await element.updateComplete;
		await new Promise(resolve => setTimeout(resolve, 0));
		await element.updateComplete;

		// Note: comes back null rather than "", pinning current behaviour
		assert.isNotOk(element.value, "Unresolvable value was kept");
	});
});
