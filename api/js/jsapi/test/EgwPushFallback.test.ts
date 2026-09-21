/**
 * Tests for egw_push_fallback.ts - the generic fallback message-delivery driver added in
 * doc/ai/projects/push-fallback-longpoll.md Phases 3, 4 and 6. Loads the real, unmodified
 * egw_push_fallback.ts (which self-instantiates on import, see that file's bottom) on top of the
 * same real egw_json.ts environment EgwJsonPushAvailability.test.ts uses, with a fake
 * window.fetch() standing in for Api\Json\Push::ajax_poll() itself.
 *
 * Every attempt (SSE or plain) starts as one fetch() call to the same menuaction - the plain
 * long-poll tests below rely on EgwJsonHarness's default resolve()/resolveNotOk()/reject()
 * always answering as a non-stream response (fixed Content-Type, known body up front), which
 * makes #trySse() itself immediately fall back to plain long-polling on the very first call -
 * exactly the "server chose not to stream" path Phase 6 adds, exercised here as a side effect
 * rather than needing its own setup. Tests that need a REAL stream use resolveStream() instead.
 *
 * Covered: does nothing at all on the login page (a live regression: no session there, so every
 * attempt hit json.php's login_redirect() path and reloaded the page, forever - found live
 * shortly after this shipped); starts on load when push isn't available (falling straight through
 * SSE's "not a stream" branch into plain long-polling, per the note above); does NOT poll at all
 * when push is already available; stops when push becomes available mid-poll and resumes when it
 * drops again; re-issues immediately on a successful response; backs off (not hammering) on a
 * failed one; backs off to DEGRADED_RETRY_MS on a {degraded: true} response; does NOT
 * double-schedule when a single response carries both a delivered message AND the degraded flag
 * (a real bug found while wiring up SSE's reuse of this same response-handling path - see
 * egw_push_fallback.ts's #once() docs); commits to SSE and marks egw.pushAvailable() true once
 * the ack chunk arrives; falls back to plain long-polling if nothing arrives within
 * SSE_ACK_TIMEOUT_MS; dispatches a real message delivered over an open stream; and reconnects
 * via SSE directly (no re-probe) once a working stream ends.
 *
 * NOT covered: Api\Json\Push::ajax_poll() itself (server-side, PHPUnit-tested separately in
 * api/tests/Json/PushPollTest.php) or the response-dispatch machinery a real reply from it would
 * go through (already covered generically by egw_json.ts's own response-handling tests) - this
 * file is only about when the driver decides to call/not call/re-call that endpoint, and via
 * which transport.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwJsonEnv, EgwJsonEnv, loadScript} from "./EgwJsonHarness";

const MENUACTION = 'EGroupware\\Api\\Json\\Push::ajax_poll';

async function loadPushFallback(env : EgwJsonEnv)
{
	// egw_push_fallback.ts's constructor runs `window.egw_ready.then(...)` immediately on
	// import - the real egw.js (which normally sets this up) is stubbed out empty in this
	// harness (see EgwJsonHarness's import map), so seed it ourselves first.
	(<any>env.window).egw_ready = Promise.resolve();
	await loadScript(env.window.document, '/api/js/jsapi/egw_push_fallback.ts', 'module');
	// let the egw_ready.then() microtask (and, transitively, the first poll's sendRequest()) run
	await new Promise((resolve) => env.window.setTimeout(resolve, 0));
}

function openRealPush(env : EgwJsonEnv)
{
	env.egw().json('websocket', {}, undefined, null).openWebSocket('wss://test.invalid/push', ['tok'], 5);
	env.webSockets[env.webSockets.length - 1].onopen({});
}

describe('egw_push_fallback.ts', () =>
{
	let env : EgwJsonEnv;

	beforeEach(async() =>
	{
		env = await createEgwJsonEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('does nothing at all on the login page - regression: it used to poll there, get redirected back to /login.php by json.php\'s login_redirect() (no session), and reload forever', async() =>
	{
		(<any>env.window).egw_appName = 'login';

		await loadPushFallback(env);

		assert.equal(env.fetchCalls.length, 0);
	});

	it('starts long-polling Push::ajax_poll() immediately when push is not available at load time', async() =>
	{
		await loadPushFallback(env);

		assert.equal(env.fetchCalls.length, 1);
		assert.include(env.fetchCalls[0].url, MENUACTION);
	});

	it('does not poll at all once push is already available by the time it loads', async() =>
	{
		openRealPush(env);
		await loadPushFallback(env);

		assert.equal(env.fetchCalls.length, 0);
	});

	it('re-issues immediately on a successful response - the actual long-poll loop', async() =>
	{
		await loadPushFallback(env);
		assert.equal(env.fetchCalls.length, 1);

		env.fetchCalls[0].resolve({response: []});
		await new Promise((resolve) => env.window.setTimeout(resolve, 0));

		assert.equal(env.fetchCalls.length, 2, 'a successful return must immediately trigger the next long-poll call');
	});

	it('backs off (does not hammer) instead of retrying instantly after a failed request', async() =>
	{
		await loadPushFallback(env);
		assert.equal(env.fetchCalls.length, 1);

		// fake timers installed only now, AFTER the (real-timer-based) load/first-poll above -
		// installing them earlier would make loadPushFallback()'s own internal real-timer wait
		// hang forever, since nothing would ever call clock.tick() to advance it
		const clock = sinon.useFakeTimers(<any>{global: env.window, toFake: ['setTimeout', 'clearTimeout']});
		try
		{
			env.fetchCalls[0].reject(new TypeError('network error'));
			await clock.tickAsync(0);	// flush the reject's microtask chain down to sendRequest()'s onError

			assert.equal(env.fetchCalls.length, 1, 'must not have retried yet');
			await clock.tickAsync(2000);	// MIN_RETRY_MS
			assert.equal(env.fetchCalls.length, 2, 'must retry once the backoff elapses');
		}
		finally { clock.restore(); }
	});

	it('backs off to the slower degraded cadence, not an immediate re-issue, when the server reports it degraded the call', async() =>
	{
		await loadPushFallback(env);
		assert.equal(env.fetchCalls.length, 1);

		const clock = sinon.useFakeTimers(<any>{global: env.window, toFake: ['setTimeout', 'clearTimeout']});
		try
		{
			// matches Api\Json\Response::data(['degraded' => true])'s real wire shape - a
			// {type: 'data', data: ...} entry inside the response array (see egw_json.ts's
			// registerJSONPlugin(..., 'data'), not a top-level `data` key on the payload itself
			env.fetchCalls[0].resolve({response: [{type: 'data', data: {degraded: true}}]});
			await clock.tickAsync(0);

			assert.equal(env.fetchCalls.length, 1, 'must not have re-issued immediately');
			await clock.tickAsync(14999);
			assert.equal(env.fetchCalls.length, 1, 'must not retry before DEGRADED_RETRY_MS elapses');
			await clock.tickAsync(1);	// DEGRADED_RETRY_MS = 15000
			assert.equal(env.fetchCalls.length, 2, 'must retry once DEGRADED_RETRY_MS elapses');
		}
		finally { clock.restore(); }
	});

	it('stops polling once push becomes available mid-poll, and resumes once it drops again', async() =>
	{
		await loadPushFallback(env);
		assert.equal(env.fetchCalls.length, 1, 'poll started while push was unavailable');

		openRealPush(env);
		env.fetchCalls[0].resolve({response: []});
		await new Promise((resolve) => env.window.setTimeout(resolve, 0));

		assert.equal(env.fetchCalls.length, 1, 'must not re-issue once push became available');

		env.webSockets[0].onclose({wasClean: true, code: 1000, reason: ''});	// push stops being available again
		await new Promise((resolve) => env.window.setTimeout(resolve, 0));

		assert.equal(env.fetchCalls.length, 2, 'must resume polling the moment push becomes unavailable again');
	});

	it('does not double-schedule when one response carries both a delivered message and the degraded flag', async() =>
	{
		await loadPushFallback(env);

		const clock = sinon.useFakeTimers(<any>{global: env.window, toFake: ['setTimeout', 'clearTimeout']});
		try
		{
			// Push::ajax_poll()'s degraded path still calls notifications_push::get() before
			// adding the degraded flag - a message that was already queued shows up as an
			// earlier entry in the SAME response. handleResponse() dispatches the 'apply' entry
			// via the generic "last entry" fallback (since a 'data' entry is also present, that
			// fallback path fires too) THEN the 'data' plugin fires for the degraded entry - the
			// callback must only actually be acted on once, using degraded's outcome.
			env.fetchCalls[0].resolve({response: [
				{type: 'apply', data: {func: 'egw.push', parms: [{app: 'test', id: 1, type: 'update'}]}},
				{type: 'data', data: {degraded: true}}
			]});
			await clock.tickAsync(0);

			assert.equal(env.fetchCalls.length, 1, 'must not have re-issued immediately (that would mean the fallback callback won)');
			await clock.tickAsync(15000);	// DEGRADED_RETRY_MS
			assert.equal(env.fetchCalls.length, 2, 'must retry exactly once, at the degraded cadence');
		}
		finally { clock.restore(); }
	});

	/**
	 * Waits for a macrotask boundary (not a fixed number of microtask ticks - the fetch/reader/
	 * decode chain's exact hop count is an implementation detail not worth pinning tests to),
	 * same as loadPushFallback()'s own wait below.
	 */
	function settle(env : EgwJsonEnv) : Promise<void>
	{
		return new Promise((resolve) => env.window.setTimeout(resolve, 0));
	}

	it('commits to SSE and marks egw.pushAvailable() true once the ack chunk arrives', async() =>
	{
		await loadPushFallback(env);
		const stream = env.fetchCalls[0].resolveStream();

		assert.isFalse(env.egw().pushAvailable());
		stream.push('event: ack\ndata: {}\n\n');
		await settle(env);

		assert.isTrue(env.egw().pushAvailable());
		assert.equal(env.fetchCalls.length, 1, 'still just the one held connection - no plain long-poll was started');
	});

	it('falls back to plain long-polling if nothing arrives within SSE_ACK_TIMEOUT_MS', async() =>
	{
		// Fake timers installed BEFORE the load this time (unlike the backoff test above) -
		// #trySse()'s ack-timeout timer is scheduled synchronously as part of the very first
		// #trySse() call, which itself happens during egw_ready's microtask chain triggered by
		// loading the script - there's no later point to install fake timers from that would
		// still be in time to control that specific timer.
		const clock = sinon.useFakeTimers(<any>{global: env.window, toFake: ['setTimeout', 'clearTimeout']});
		try
		{
			(<any>env.window).egw_ready = Promise.resolve();
			await loadScript(env.window.document, '/api/js/jsapi/egw_push_fallback.ts', 'module');
			await clock.tickAsync(0);	// egw_ready.then() -> #trySse() -> the initial fetch() call

			assert.equal(env.fetchCalls.length, 1);
			env.fetchCalls[0].resolveStream();	// a stream that never actually sends anything
			await clock.tickAsync(0);	// let #trySse() reach (and start waiting on) reader.read()

			await clock.tickAsync(2999);
			assert.equal(env.fetchCalls.length, 1, 'must not have given up before SSE_ACK_TIMEOUT_MS');
			await clock.tickAsync(1);	// SSE_ACK_TIMEOUT_MS = 3000
			assert.equal(env.fetchCalls.length, 2, 'must fall back to a plain long-poll call once the ack timeout elapses');
			assert.isFalse(env.egw().pushAvailable());
		}
		finally { clock.restore(); }
	});

	it('dispatches a real message delivered over an open SSE stream', async() =>
	{
		await loadPushFallback(env);
		const stream = env.fetchCalls[0].resolveStream();
		stream.push('event: ack\ndata: {}\n\n');
		await settle(env);

		// same "apply" dispatch EgwJson.test.ts's own '"apply" calls a global function by name'
		// test already proves works - a flat (not dotted) global name, to keep this test about
		// SSE chunk parsing/dispatch, not applyFunc()'s separate dotted-path resolution
		const funcSpy = sinon.spy();
		(<any>env.window).myPushTestFunc = funcSpy;
		stream.push('data: {"response":[{"type":"apply","data":{"func":"myPushTestFunc","parms":[{"app":"test","id":1}]}}]}\n\n');
		await settle(env);

		assert.isTrue(funcSpy.calledOnceWith({app: 'test', id: 1}));
		assert.equal(env.fetchCalls.length, 1, 'still on the same held connection, no long-poll or reconnect triggered');
	});

	it('reconnects via SSE directly (no ack re-probe) once a working stream ends', async() =>
	{
		await loadPushFallback(env);
		const stream = env.fetchCalls[0].resolveStream();
		stream.push('event: ack\ndata: {}\n\n');
		await settle(env);
		assert.isTrue(env.egw().pushAvailable());

		stream.end();	// the server's own bounded AJAX_POLL_SSE_MAX_WAIT_SECONDS, or a drop
		await settle(env);

		assert.equal(env.fetchCalls.length, 2, 'must reconnect via a new SSE attempt, not fall back to plain long-polling');
		assert.isFalse(env.egw().pushAvailable(), 'unavailable again the instant the stream ends');

		// the reconnect requests sse=true again (parameters: [true]), same as the initial
		// attempt - #poll()'s own requests always carry parameters: [] instead
		assert.include(env.fetchCalls[1].init.body, '"parameters":[true]');
	});
});
