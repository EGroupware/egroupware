import {assert, fixture} from "@open-wc/testing";
import {Et2LAvatar} from "../Et2LAvatar";

/**
 * Contract: an <et2-lavatar> must stop updating once it has rendered.  A widget that keeps
 * scheduling further Lit update cycles after it has settled pays a fresh update pass per
 * instance forever.  In a virtualized list this multiplies by the number of visible rows -
 * Et2Datagrid rebuilds a row's entire widget set whenever the row string handed to
 * unsafeHTML() changes (see Et2DatagridRowRenderer.ts) - and shows up as sustained
 * main-thread load plus cycle-collector churn.
 *
 * Setup: a test-only subclass counts update cycles by overriding updated(), registered under
 * its own tag so nothing patches a live instance.  The widget is rendered and allowed to
 * reach a settled state, the counter is then zeroed, and the element is left strictly alone
 * for a quiet period spanning many microtask and task turns.  Et2LAvatar's re-entry path is
 * a microtask chain (an await inside willUpdate()/updated()), so it needs no timer or rAF to
 * keep going - a quiet period is enough to observe it.
 *
 * Three fixtures cover the distinct branches of Et2LAvatar.willUpdate():
 *   - plain fname/lname     - no contactId branch
 *   - contactId="account:N" - the branch that awaits egw.accountData()
 *   - contactId="email:..." - the branch that REASSIGNS the reactive properties it keys on
 *                             (this.lname / this.fname / this.contactId)
 *
 * Pass criteria: zero further update cycles during the quiet period after settling.  A
 * non-zero count is the defect.  The last test distinguishes a fixed number of extra passes
 * from an unbounded loop by comparing a short and a long quiet period: only an unbounded
 * loop keeps climbing with time.
 *
 * Environment: no network and no real egw - every egw entry point the widget touches is
 * stubbed below.  Timing-sensitive only in that it waits a fixed period for work that, if
 * present, is scheduled promptly as microtasks.
 */

const egwStub = {
	lang: i => i === null || typeof i === "undefined" ? "" : String(i),
	preference: () => null,          // avatar_display / account_display both unset
	accountData: () => Promise.resolve({}),
	webserverUrl: "",
	debug: () => {},
	ajaxUrl: url => url,
	decodePath: url => url,
	link: l => l,
	image: () => "",
	request: () => Promise.resolve({}),
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	// CachedQueueMixin caches avatar-existence answers in session storage.  Always report a
	// miss so the real queue/request path runs rather than being short-circuited by a cache
	// hit - a stub that "hits" would silently skip the very code under test.
	getSessionItem: () => null,
	setSessionItem: () => {},
	removeSessionItem: () => {},
	window: window
};
// @ts-ignore - the widget reads the global egw as well as its own this.egw()
window.egw = egwStub;

/**
 * Counts its own Lit update cycles.  A subclass rather than an instance patch: updated() is
 * inherited through the Et2Widget/Shoelace mixin chain, and overriding it here goes through
 * the normal prototype path instead of fighting it.
 */
class CountingLAvatar extends Et2LAvatar
{
	public updateCount = 0;

	egw() : any
	{
		return window.egw;
	}

	updated(changedProperties)
	{
		this.updateCount++;
		return super.updated(changedProperties);
	}
}

customElements.define("test-lavatar", <CustomElementConstructor><unknown>CountingLAvatar);

/** Let queued microtasks and several macrotask turns drain. */
function quietPeriod(ms : number) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Render, let it settle, then report how many MORE update cycles happen while it is left
 * alone.  The initial render's own cycles are deliberately excluded - the question is only
 * whether anything keeps scheduling work afterwards.
 */
async function updatesAfterSettle(attrs : string, quiet = 250)
	: Promise<{initial : number, extra : number}>
{
	const el = <CountingLAvatar><unknown>await fixture(`<test-lavatar ${attrs}></test-lavatar>`);
	await el.updateComplete;
	await quietPeriod(50);          // drain a legitimate one-off follow-up pass
	const initial = el.updateCount;

	el.updateCount = 0;
	await quietPeriod(quiet);
	return {initial, extra: el.updateCount};
}

describe("Et2LAvatar update settling", () =>
{
	it("stops updating after render (plain name)", async() =>
	{
		const {initial, extra} = await updatesAfterSettle(`fname="Ada" lname="Lovelace"`);
		console.log(`plain name: ${initial} cycles to render, ${extra} extra after settling`);
		assert.equal(extra, 0, `kept updating after settling (${extra} extra cycles)`);
	});

	it("stops updating after render (account contactId)", async() =>
	{
		const {initial, extra} = await updatesAfterSettle(`fname="Ada" lname="Lovelace" contactId="account:1"`);
		console.log(`account id: ${initial} cycles to render, ${extra} extra after settling`);
		assert.equal(extra, 0, `kept updating after settling (${extra} extra cycles)`);
	});

	it("stops updating after render (email contactId - reassigns its own reactive props)", async() =>
	{
		const {initial, extra} = await updatesAfterSettle(`contactId="email:Test User &lt;test@example.invalid&gt;"`);
		console.log(`email id: ${initial} cycles to render, ${extra} extra after settling`);
		assert.equal(extra, 0, `kept updating after settling (${extra} extra cycles)`);
	});

	it("does not update more the longer it is left alone (unbounded-loop check)", async() =>
	{
		const short = await updatesAfterSettle(`fname="Ada" lname="Lovelace" contactId="account:1"`, 150);
		const long = await updatesAfterSettle(`fname="Ada" lname="Lovelace" contactId="account:1"`, 600);
		console.log(`unbounded check: ${short.extra} extra in 150ms vs ${long.extra} extra in 600ms`);
		assert.isAtMost(long.extra, short.extra + 1,
			`update count grows with time (${short.extra} in 150ms vs ${long.extra} in 600ms) - unbounded loop`);
	});
});
