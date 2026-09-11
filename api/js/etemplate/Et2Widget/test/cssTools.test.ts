import {assert} from "@open-wc/testing";
import {clearStylesheetCache, loadStylesheet} from "../cssTools";

/**
 * Contract: loadStylesheet() fetches each URL at most once per page load and hands
 * every caller the same CSSStyleSheet instance.  Several et2-nextmatch widgets on one
 * page load the same app.css, and each uncached call was a forced revalidation round
 * trip plus another parsed copy of the same CSS retained for the lifetime of the page.
 * A network failure must not be cached, so it can be retried.
 *
 * Setup: window.fetch is replaced with a counting stub for the duration of each test
 * and the module cache is cleared around it, so call counts belong to that test alone.
 *
 * Pass criteria: repeat and concurrent loads of one URL produce exactly one fetch and
 * one shared sheet; a 404 resolves to null and is also only fetched once; a rejected
 * fetch is not cached, so the next call retries and can succeed.
 */

const originalFetch = window.fetch;

function stubFetch(handler : (url : string) => Response)
{
	let calls = 0;
	window.fetch = ((input : any) =>
	{
		calls++;
		return Promise.resolve(handler(String(input)));
	}) as any;
	return () => calls;
}

function cssResponse(css : string) : Response
{
	return new Response(css, {status: 200, headers: {"Content-Type": "text/css"}});
}

describe("loadStylesheet caching", () =>
{
	beforeEach(() => clearStylesheetCache());
	afterEach(() =>
	{
		window.fetch = originalFetch;
		clearStylesheetCache();
	});

	it("fetches a URL once and shares the sheet between callers", async() =>
	{
		const calls = stubFetch(() => cssResponse(".a{color:red}"));

		const first = await loadStylesheet("/app/templates/default/app.css");
		const second = await loadStylesheet("/app/templates/default/app.css");

		assert.equal(calls(), 1, "second load should be served from the cache");
		assert.isNotNull(first, "stylesheet should have loaded");
		assert.strictEqual(second, first, "both callers should get the same shared sheet");
	});

	it("collapses concurrent loads of the same URL into one request", async() =>
	{
		const calls = stubFetch(() => cssResponse(".b{color:blue}"));

		const [first, second] = await Promise.all([
			loadStylesheet("/app/templates/default/app.css"),
			loadStylesheet("/app/templates/default/app.css")
		]);

		assert.equal(calls(), 1, "concurrent callers should share one in-flight request");
		assert.strictEqual(second, first, "concurrent callers should get the same shared sheet");
	});

	it("caches a 404 as a real answer", async() =>
	{
		const calls = stubFetch(() => new Response("", {status: 404}));

		assert.isNull(await loadStylesheet("/app/templates/mobile/app.css"), "404 should resolve to null");
		assert.isNull(await loadStylesheet("/app/templates/mobile/app.css"), "repeat 404 should still be null");
		assert.equal(calls(), 1, "a missing stylesheet should not be re-probed");
	});

	it("does not cache a network failure", async() =>
	{
		let attempt = 0;
		window.fetch = (() =>
		{
			attempt++;
			return attempt === 1 ? Promise.reject(new TypeError("network down")) : Promise.resolve(cssResponse(".c{color:green}"));
		}) as any;

		let rejected = false;
		try
		{
			await loadStylesheet("/app/templates/default/app.css");
		}
		catch(e)
		{
			rejected = true;
		}
		assert.isTrue(rejected, "a network failure should propagate to the caller");
		assert.isNotNull(await loadStylesheet("/app/templates/default/app.css"), "a retry should not be served the failed promise");
		assert.equal(attempt, 2, "the failed URL should be re-fetched, not cached");
	});
});
