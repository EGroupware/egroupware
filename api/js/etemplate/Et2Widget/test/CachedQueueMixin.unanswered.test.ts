import {assert, fixture} from "@open-wc/testing";
import {Et2LAvatar} from "../../Et2Avatar/Et2LAvatar";

/**
 * Contract: every widget that asks CachedQueueMixin.cachedQueue() for something is answered, and
 * leaves the queue, once the request it was part of has come back - whether or not the server had
 * an answer for its key, and even if the request failed outright.
 *
 * Why it matters: a queue entry holds its owner widget (and everything that widget references)
 * and is sent again with every later request. The old loop walked the queue from the end and
 * stopped at the first key the server did not answer, so one unanswerable key (the server encodes
 * its answer keys with PHP's json_encode, which escapes "/" and non-ASCII characters where
 * JSON.stringify does not) kept itself AND every entry queued before it - answered or not -
 * for the life of the page.
 *
 * Setup: avatar widgets without a contactId, so nothing queues on its own, and an egw stub whose
 * request() answers according to the test.
 *
 * Pass criteria: each caller's promise settles - with the server's answer when there is one,
 * with undefined when there is not - and the queue is empty afterwards. A caller that queues
 * while a request is in flight is not part of it, and is neither answered nor dropped by it.
 *
 * Environment: no network.
 */

let answer : (params : any[]) => Promise<any>;

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
	request: (_url, args) => answer((args && args[0]) || []),
	window: window
};
// @ts-ignore
window.egw = egwStub;

class UnansweredAvatar extends Et2LAvatar
{
	egw() : any
	{
		return window.egw;
	}
}

customElements.define("test-unanswered-avatar", <CustomElementConstructor><unknown>UnansweredAvatar);

function withTimeout<T>(promise : Promise<T>, ms = 1000) : Promise<T | "TIMED_OUT">
{
	return Promise.race([
		promise,
		new Promise<"TIMED_OUT">(resolve => setTimeout(() => resolve("TIMED_OUT"), ms))
	]);
}

async function makeAvatar() : Promise<UnansweredAvatar>
{
	const el = <UnansweredAvatar><unknown>await fixture(`<test-unanswered-avatar></test-unanswered-avatar>`);
	await el.updateComplete;
	return el;
}

function queueLength() : number
{
	const queues : Map<string, any[]> = (<any>UnansweredAvatar)._queues;
	return queues.get((<any>UnansweredAvatar).widgetCacheKey)?.length ?? 0;
}

describe("CachedQueueMixin unanswered requests", () =>
{
	it("answers the keys the server did answer, and drops the one it did not, whatever order they were queued in", async() =>
	{
		// Answers only the keys that survive the escaping mismatch, like the real server
		answer = params => Promise.resolve(Object.fromEntries(
			params.filter(p => !/[\/\u0080-￿]/.test(p[0])).map(p => [JSON.stringify(p), true])
		));
		const widgets = [await makeAvatar(), await makeAvatar(), await makeAvatar()];

		const plain = (<any>widgets[0]).cachedQueue(["email:plain@example.invalid"]);
		const slash = (<any>widgets[1]).cachedQueue(["email:a/b@example.invalid"]);
		// queued last, so the old end-to-start loop met it first
		const umlaut = (<any>widgets[2]).cachedQueue(["email:ü@example.invalid"]);

		assert.strictEqual(await withTimeout(plain), true, "the answered key must be resolved, not held up by the others");
		assert.strictEqual(await withTimeout(slash), undefined, "an unanswered key must be resolved, not left waiting");
		assert.strictEqual(await withTimeout(umlaut), undefined);
		assert.equal(queueLength(), 0, "nothing may stay queued - each entry holds its widget");
	});

	it("drains the queue when the request itself fails", async() =>
	{
		answer = () => Promise.reject(new Error("network down"));
		const widget = await makeAvatar();

		const result = await withTimeout((<any>widget).cachedQueue(["account_id:7"]));

		assert.strictEqual(result, undefined);
		assert.equal(queueLength(), 0);
	});

	it("drains the queue when the request resolves with nothing (egw.request() does that on failure)", async() =>
	{
		answer = () => Promise.resolve(undefined);
		const widget = await makeAvatar();

		const result = await withTimeout((<any>widget).cachedQueue(["account_id:8"]));

		assert.strictEqual(result, undefined);
		assert.equal(queueLength(), 0);
	});

	it("leaves a widget that queued while the request was in flight for the next request", async() =>
	{
		let release : () => void;
		const held = new Promise<void>(resolve => release = resolve);
		const requests : any[][] = [];
		answer = async params =>
		{
			requests.push(params);
			if(requests.length === 1)
			{
				await held;
			}
			return Object.fromEntries(params.map(p => [JSON.stringify(p), true]));
		};
		const first = await makeAvatar();
		const second = await makeAvatar();

		const firstResult = (<any>first).cachedQueue(["account_id:11"]);
		await new Promise(resolve => setTimeout(resolve, 250));	// past the queue delay: request 1 is in flight
		assert.equal(requests.length, 1);
		const secondResult = (<any>second).cachedQueue(["account_id:12"]);
		release();

		assert.strictEqual(await withTimeout(firstResult), true);
		// not answered "no answer" by a request it was never part of
		assert.strictEqual(await withTimeout(secondResult, 2000), true);
		assert.equal(requests.length, 2, "it was sent in its own request");
		assert.deepEqual(requests[1], [["account_id:12"]]);
		assert.equal(queueLength(), 0);
	});
});
