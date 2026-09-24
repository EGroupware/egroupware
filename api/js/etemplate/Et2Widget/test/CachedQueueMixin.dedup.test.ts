import {assert, fixture} from "@open-wc/testing";
import {Et2LAvatar} from "../../Et2Avatar/Et2LAvatar";

/**
 * Contract: when two widgets ask CachedQueueMixin.cachedQueue() for the SAME key before the
 * first request has come back, both must resolve with the server's answer for that key.
 * Deduplicating the request is the point of the queue; dropping or corrupting either
 * caller's answer is not.
 *
 * Why it matters: Et2Avatar sets its (reflected, therefore serialized) image property from
 * inside cachedQueue().then().  An avatar whose promise never settles keeps no image, and
 * one that resolves with the wrong value shows an image the server never confirmed - which
 * defeats the 404-avoidance the queue exists for.  Duplicate in-flight keys are routine in a
 * virtualized datagrid: the same contact can occupy several rows, and a recycled row builds
 * a fresh avatar for a key whose earlier request may still be in flight.
 *
 * Setup: two avatar widgets (created without contactId, so nothing queues on their own),
 * then cachedQueue() is called on both with identical parameters in the same synchronous
 * turn, so the second call necessarily takes the "pending request" branch.  The egw stub
 * answers the way CachedQueueMixin._processQueue() reads responses - data[JSON.stringify(params)] -
 * returning the boolean true for the queried key.
 *
 * Pass criteria: both promises settle within the timeout, and both yield exactly the
 * server's value (true).  A promise that never settles fails on timeout; one that yields a
 * function instead of the server's boolean fails the value assertion.
 *
 * Environment: no network.  The timeout only has to outlast a microtask chain, so it is not
 * sensitive to machine speed.
 */

const egwStub = {
	lang: i => i === null || typeof i === "undefined" ? "" : String(i),
	preference: () => null,
	accountData: () => Promise.resolve({}),
	webserverUrl: "",
	debug: () => {},
	decodePath: url => url,
	image: () => "",
	link: url => url,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	getSessionItem: () => null,       // always a miss, so the queue path really runs
	setSessionItem: () => {},
	removeSessionItem: () => {},
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

class QueueAvatar extends Et2LAvatar
{
	egw() : any
	{
		return window.egw;
	}
}

customElements.define("test-queue-avatar", <CustomElementConstructor><unknown>QueueAvatar);

/** Resolve to a sentinel if the promise has not settled in time, so a hang is a clean failure. */
function withTimeout<T>(promise : Promise<T>, ms = 1000) : Promise<T | "TIMED_OUT">
{
	return Promise.race([
		promise,
		new Promise<"TIMED_OUT">(resolve => setTimeout(() => resolve("TIMED_OUT"), ms))
	]);
}

async function makeAvatar() : Promise<QueueAvatar>
{
	const el = <QueueAvatar><unknown>await fixture(`<test-queue-avatar></test-queue-avatar>`);
	await el.updateComplete;
	return el;
}

describe("CachedQueueMixin duplicate in-flight requests", () =>
{
	it("resolves both callers with the server's answer when the same key is requested twice", async() =>
	{
		const first = await makeAvatar();
		const second = await makeAvatar();

		// Same synchronous turn, identical parameters: the second call takes the pending branch.
		const params = ["account_id:4242"];
		const firstPromise = (<any>first).cachedQueue(params);
		const secondPromise = (<any>second).cachedQueue(params);

		const firstResult = await withTimeout(firstPromise);
		const secondResult = await withTimeout(secondPromise);

		console.log(`first  caller resolved with: ${typeof firstResult} ${String(firstResult)}`);
		console.log(`second caller resolved with: ${typeof secondResult} ${String(secondResult)}`);

		assert.notEqual(secondResult, "TIMED_OUT",
			"second caller's promise never settled - its widget waits forever");
		assert.notEqual(firstResult, "TIMED_OUT",
			"first caller's promise never settled");
		assert.strictEqual(firstResult, true,
			"first caller did not get the server's answer");
		assert.strictEqual(secondResult, true,
			"second caller did not get the server's answer");
	});
});
