/**
 * Tests for the legacy search-response normaliser.
 *
 * See doc/ai/projects/et2select-searchmixin-removal.md §4 - the shapes here are what our search
 * endpoints actually send, and they have to keep working indefinitely because custom fields let
 * any installation point searchUrl at its own method.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from '@open-wc/testing';
import * as sinon from 'sinon';
import {_resetLegacyWarnings, normalizeLegacySearchResults} from "../legacySearchResults";

let warnSpy : sinon.SinonStub;

beforeEach(() =>
{
	_resetLegacyWarnings();
	warnSpy = sinon.stub(console, "warn");
});

afterEach(() => { warnSpy.restore(); });

describe("normalizeLegacySearchResults() shapes", () =>
{
	it("passes a modern response through", () =>
	{
		const modern = {results: [{value: "one", label: "One"}], total: 42};

		const out = normalizeLegacySearchResults(modern, "test");

		assert.deepEqual(out.results.map(r => r.value), ["one"]);
		assert.equal(out.total, 42, "Total was not preserved");
	});

	it("takes the total from a modern response even when it exceeds the result count", () =>
	{
		const out = normalizeLegacySearchResults({results: [{value: "one", label: "One"}], total: 500}, "test");

		assert.equal(out.total, 500, "Server's total was replaced by the result count");
	});

	it("converts a bare array", () =>
	{
		const out = normalizeLegacySearchResults([
			{value: "one", label: "One"},
			{value: "two", label: "Two"}
		], "test");

		assert.deepEqual(out.results.map(r => r.value), ["one", "two"]);
		assert.equal(out.total, 2, "Total should be the array length");
	});

	it("converts an object with total spliced in among the results", () =>
	{
		// This is what Select::ajax_search sends when it has a total
		const out = normalizeLegacySearchResults({
			0: {value: "one", label: "One"},
			1: {value: "two", label: "Two"},
			total: 57
		}, "test");

		assert.deepEqual(out.results.map(r => r.value), ["one", "two"]);
		assert.equal(out.total, 57, "Spliced-in total was not used");
		assert.notInclude(out.results.map(r => r.value), "57", "total leaked in as an option");
	});

	it("does not leave the total sitting among the results", () =>
	{
		const out = normalizeLegacySearchResults({
			0: {value: "one", label: "One"},
			total: 3
		}, "test");

		assert.lengthOf(out.results, 1, "total was counted as a result");
		assert.equal(out.total, 3);
	});

	/**
	 * Inherent to the legacy shape, not something the normaliser can fix: results and the count
	 * share one namespace, so an endpoint whose option is genuinely keyed "total" loses it.  No
	 * endpoint we ship does this.  Pinned so the behaviour is a known limitation rather than a
	 * surprise.
	 */
	it("LIMITATION: an option keyed \"total\" is consumed as the count", () =>
	{
		const out = normalizeLegacySearchResults({
			total: {value: "total", label: "Total"},
			other: {value: "other", label: "Other"}
		}, "test");

		assert.deepEqual(out.results.map(r => r.value), ["other"], "Expected the total-keyed option to be eaten");
		// not a number, so the count falls back to what we could actually use
		assert.equal(out.total, 1);
	});

	it("converts a plain object keyed by value", () =>
	{
		const out = normalizeLegacySearchResults({
			one: "One",
			two: "Two"
		}, "test");

		assert.deepEqual(out.results.map(r => r.value), ["one", "two"]);
		assert.deepEqual(out.results.map(r => r.label), ["One", "Two"]);
		assert.equal(out.total, 2);
	});

	it("survives nothing usable", () =>
	{
		for(const junk of [null, undefined, "", "a string", 42])
		{
			const out = normalizeLegacySearchResults(<any>junk, "test");
			assert.deepEqual(out.results, [], `${JSON.stringify(junk)} did not give empty results`);
			assert.equal(out.total, 0, `${JSON.stringify(junk)} did not give a zero total`);
		}
	});

	it("handles an empty array and an empty object", () =>
	{
		assert.deepEqual(normalizeLegacySearchResults([], "test").results, []);
		assert.equal(normalizeLegacySearchResults([], "test").total, 0);
		assert.deepEqual(normalizeLegacySearchResults({}, "test").results, []);
	});
});

describe("normalizeLegacySearchResults() option groups", () =>
{
	it("turns a legacy value-array group into children", () =>
	{
		const out = normalizeLegacySearchResults([
			{
				label: "A group",
				value: [
					{value: "child_one", label: "Child One"},
					{value: "child_two", label: "Child Two"}
				]
			}
		], "test");

		assert.lengthOf(out.results, 1, "Group should stay a single entry");
		const group = out.results[0];
		assert.isArray(group.children, "Group got no children");
		assert.deepEqual(group.children.map(c => c.value), ["child_one", "child_two"]);
		assert.isTrue(group.hasChildren, "hasChildren was not set");
	});

	it("keeps the children in value too, for Et2Select", () =>
	{
		const out = normalizeLegacySearchResults([
			{label: "A group", value: [{value: "kid", label: "Kid"}]}
		], "test");

		assert.isArray(out.results[0].value, "Legacy value-array shape was dropped");
	});

	it("recurses into nested groups", () =>
	{
		const out = normalizeLegacySearchResults([
			{
				label: "Outer",
				value: [
					{label: "Inner", value: [{value: "deep", label: "Deep"}]}
				]
			}
		], "test");

		const inner = out.results[0].children[0];
		assert.isArray(inner.children, "Nested group got no children");
		assert.equal(inner.children[0].value, "deep");
	});

	it("leaves a modern children group alone", () =>
	{
		const out = normalizeLegacySearchResults({
			results: [{value: "group", label: "Group", children: [{value: "kid", label: "Kid"}]}],
			total: 1
		}, "test");

		assert.deepEqual(out.results[0].children.map(c => c.value), ["kid"]);
	});
});

describe("normalizeLegacySearchResults() warning", () =>
{
	it("warns when it had to convert", () =>
	{
		normalizeLegacySearchResults([{value: "one", label: "One"}], "some.legacy.endpoint");

		assert.isTrue(warnSpy.calledOnce, "No warning for a legacy response");
		assert.include(warnSpy.firstCall.args[0], "some.legacy.endpoint", "Warning did not name the endpoint");
	});

	it("stays silent for a modern response", () =>
	{
		normalizeLegacySearchResults({results: [{value: "one", label: "One"}], total: 1}, "modern.endpoint");

		assert.isTrue(warnSpy.notCalled, "Warned about a correctly-shaped response");
	});

	it("warns only once per endpoint, however often it is called", () =>
	{
		for(let i = 0; i < 10; i++)
		{
			normalizeLegacySearchResults([{value: "one", label: "One"}], "same.endpoint");
		}

		assert.isTrue(warnSpy.calledOnce, `Warned ${warnSpy.callCount} times for one endpoint`);
	});

	it("warns separately for each endpoint", () =>
	{
		normalizeLegacySearchResults([{value: "one", label: "One"}], "first.endpoint");
		normalizeLegacySearchResults([{value: "two", label: "Two"}], "second.endpoint");

		assert.equal(warnSpy.callCount, 2, "Second endpoint did not get its own warning");
	});

	it("still warns when there is no searchUrl to name", () =>
	{
		normalizeLegacySearchResults([{value: "one", label: "One"}]);

		assert.isTrue(warnSpy.calledOnce, "No warning without a searchUrl");
	});

	it("does not warn about junk it could not use", () =>
	{
		normalizeLegacySearchResults(null, "test");

		assert.isTrue(warnSpy.notCalled, "Warned about an unusable response");
	});
});
