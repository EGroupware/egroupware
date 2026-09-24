import {assert, fixture} from "@open-wc/testing";
import {Et2LAvatar} from "../Et2LAvatar";

/**
 * Contract: the HTML a datagrid row serializes to must not change while the row's data
 * stays the same.
 *
 * Why it matters: Et2Datagrid renders each row by handing `rowElement.outerHTML` to lit's
 * unsafeHTML() (Et2DatagridRowRenderer), and unsafeHTML replaces the whole row node whenever
 * that string differs from last time - tearing down and reconstructing every widget in the
 * row, each one paying the legacy decorator construction path.  Et2Datagrid.rowRerender.benchmark
 * confirms the reuse side of this: an unchanged string DOES keep the same physical node
 * (node reused=true).  So row-string stability is exactly what decides whether a datagrid
 * settles or keeps rebuilding.
 *
 * Et2Avatar.image is assigned asynchronously, from inside the cachedQueue().then() in the
 * contactId setter, because only the server knows whether a contact has a photo - so it lands
 * only AFTER the avatar's first render has already completed.  While it was reflected, that
 * late answer rewrote the host's attributes and therefore its outerHTML.  The property may
 * still change late (that is inherent); what must not change late is the serialized HTML.
 * The first test therefore asserts BOTH: the HTML is stable, AND the avatar genuinely
 * resolved and rendered its image - so the stability cannot be satisfied by a widget that
 * simply stopped working.
 *
 * Setup: a test-only subclass supplies a stubbed egw (no network).  The queue stub reports
 * "this contact HAS an avatar" for every queried key - keyed by JSON.stringify of the
 * parameters, the way CachedQueueMixin._processQueue() maps responses back - so the async
 * assignment path genuinely runs.  Session-storage caching always reports a miss so the
 * queue is never short-circuited.  Each test logs the captured HTML so a vacuous pass
 * (image never assigned, nothing to differ) is visible rather than silently green.
 *
 * Pass criteria:
 *  1. outerHTML captured at first settled update equals outerHTML after the async assignment
 *     has landed - i.e. the row does not change out from under the datagrid.
 *  2. Two identical avatars serialize identically once settled (determinism).
 *  3. Re-hydrating a settled row's own HTML converges: an element rebuilt from that string
 *     serializes to the same string, so a rebuild cannot feed another rebuild forever.
 *     This is what separates "one extra rebuild per avatar" from an unbounded loop.
 *
 * Environment: no network; timing-based only in waiting a fixed quiet period for microtask
 * work that, if present, is scheduled promptly.
 */

const egwStub = {
	lang: i => i === null || typeof i === "undefined" ? "" : String(i),
	preference: () => null,
	accountData: () => Promise.resolve({}),
	webserverUrl: "",
	debug: () => {},
	decodePath: url => url,
	image: () => "",
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	// Always a cache miss, so the real queue/request path runs every time.
	getSessionItem: () => null,
	setSessionItem: () => {},
	removeSessionItem: () => {},
	// Mimic the real egw.link(): deterministic url + sorted query string, no cache-buster of
	// its own, so any instability observed comes from the widget rather than from this stub.
	link: (url, params) =>
	{
		if(!params || typeof params !== "object")
		{
			return url;
		}
		const query = Object.keys(params).sort()
			.map(k => `${encodeURIComponent(k)}=${encodeURIComponent(String(params[k]))}`)
			.join("&");
		return query ? `${url}?${query}` : url;
	},
	// CachedQueueMixin._processQueue() looks results up as data[item.cacheKey], where cacheKey
	// is the JSON-stringified parameter set.  Echo every queried key back as "has an avatar".
	request: (_url, args) =>
	{
		const out = {};
		for(const param of (args && args[0]) || [])
		{
			out[JSON.stringify(param)] = true;
		}
		return Promise.resolve(out);
	},
	window: window
};
// @ts-ignore
window.egw = egwStub;

class TestAvatar extends Et2LAvatar
{
	egw() : any
	{
		return window.egw;
	}
}

customElements.define("test-stability-avatar", <CustomElementConstructor><unknown>TestAvatar);

function quietPeriod(ms : number) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

async function makeAvatar(attrs : string) : Promise<TestAvatar>
{
	return <TestAvatar><unknown>await fixture(`<test-stability-avatar ${attrs}></test-stability-avatar>`);
}

const ACCOUNT = `fname="Ada" lname="Lovelace" contactId="account:1"`;

describe("Et2Avatar row-string stability", () =>
{
	it("serialized HTML does not change after the async image assignment lands", async() =>
	{
		const el = await makeAvatar(ACCOUNT);
		await el.updateComplete;
		const atFirstRender = el.outerHTML;

		await quietPeriod(300);          // let cachedQueue resolve and image reflect
		const afterSettle = el.outerHTML;

		console.log(`at first render : ${atFirstRender}`);
		console.log(`after settle    : ${afterSettle}`);
		console.log(`resolved image  : ${(<any>el).image}`);

		// Non-vacuity, in two parts.  The point of the fix is that the resolved URL stops
		// appearing in the host's attributes - so "the HTML did not change" must NOT be
		// allowed to pass merely because the widget never resolved anything, nor because it
		// resolved but failed to display it.  Both are checked on the property/shadow side,
		// which is where the answer legitimately lives.
		assert.include(String((<any>el).image), "avatar.php",
			"the widget never resolved an image at all - stability would be vacuous");
		// Shoelace's avatar drops its <img> and falls back to initials when the image fails to
		// load, and nothing serves /api/avatar.php in this harness - so asserting a live <img>
		// would be testing the network, not this contract.  Instead require evidence that the
		// resolved URL reached the render path at all: either an <img> exists, or the
		// component recorded a load error for the URL it tried.
		const img = el.shadowRoot?.querySelector("img");
		assert.isTrue(!!img || (<any>el).hasError === true,
			"the resolved URL never reached the render path - neither an <img> nor a load error");
		if(img)
		{
			assert.include(String(img.getAttribute("src")), "avatar.php",
				"the rendered <img> is not showing the resolved avatar");
		}

		assert.equal(atFirstRender, afterSettle,
			"row HTML changed after first render - every such row gets torn down and rebuilt");
	});

	it("two identical avatars serialize identically once settled", async() =>
	{
		const a = await makeAvatar(ACCOUNT);
		const b = await makeAvatar(ACCOUNT);
		await Promise.all([a.updateComplete, b.updateComplete]);
		await quietPeriod(300);

		console.log(`avatar a: ${a.outerHTML}`);
		console.log(`avatar b: ${b.outerHTML}`);
		assert.equal(a.outerHTML, b.outerHTML, "identical data produced different HTML");
	});

	it("re-hydrating a settled row's own HTML converges (bounded, not a loop)", async() =>
	{
		const first = await makeAvatar(ACCOUNT);
		await first.updateComplete;
		await quietPeriod(300);
		const settled = first.outerHTML;

		// Rebuild from that serialized string, the way unsafeHTML() would.
		const host = <HTMLElement>await fixture(`<div></div>`);
		host.innerHTML = settled;
		const rebuilt = <TestAvatar>host.firstElementChild;
		await (<any>rebuilt).updateComplete;
		await quietPeriod(300);

		console.log(`settled  : ${settled}`);
		console.log(`rebuilt  : ${rebuilt.outerHTML}`);
		assert.equal(rebuilt.outerHTML, settled,
			"a row rebuilt from its own HTML serializes differently - each rebuild feeds another");
	});
});
