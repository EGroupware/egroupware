/**
 * Tests for the push-availability state machine added to egw_json.ts's "json" module -
 * egw.pushAvailable()/egw.onPushAvailabilityChange() - see doc/ai/projects/
 * push-fallback-longpoll.md Phase 2. Loads the real, unmodified egw_json.ts (see
 * EgwJsonHarness), with a FakeWebSocket standing in for the network and sinon fake timers
 * (targeting the iframe's own window - see EgwJsonHarness for why realms matter here) standing in
 * for the connect-timeout/grace-period/reconnect-backoff delays, so none of this actually waits
 * real seconds.
 *
 * Covered: the initial-connect bound (CONNECT_TIMEOUT_MS), the debounce/grace period that keeps a
 * single dropped ping from flapping pushAvailable() (PUSH_UNAVAILABLE_GRACE_MS), immediate
 * unavailability on a clean close, onPushAvailabilityChange() firing only on actual transitions
 * (not on every reconnect attempt), and unsubscribe.
 *
 * NOT covered: the reconnect backoff math itself (MIN/MAX_RECONNECT_TIME doubling) - pre-existing
 * behaviour, unchanged by this project.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwJsonEnv, EgwJsonEnv} from "./EgwJsonHarness";

function openWebSocket(env : EgwJsonEnv)
{
	env.egw().json('websocket', {}, undefined, null).openWebSocket('wss://test.invalid/push', ['tok'], 5);
}

describe('egw_json.ts push availability', () =>
{
	let env : EgwJsonEnv;
	let clock : sinon.SinonFakeTimers;

	beforeEach(async() =>
	{
		env = await createEgwJsonEnv();
		// `global` targets the iframe's own realm, not @types/sinon's declared config shape (it's
		// a real @sinonjs/fake-timers install() option this repo's sinon version supports, just
		// missing from the bundled .d.ts) - egw_json.ts's #setTimeoutOn() calls _wnd.setTimeout()
		// (env.window's, not this test file's realm's), so faking the default global here would
		// silently do nothing.
		clock = sinon.useFakeTimers(<any>{global: env.window, toFake: ['setTimeout', 'clearTimeout']});
	});

	afterEach(() =>
	{
		clock.restore();
		env.destroy();
	});

	it('is false before any connection attempt, and no app.ts ever needs to wait for a signal to find that out', () =>
	{
		assert.isFalse(env.egw().pushAvailable());
	});

	it('becomes true once the websocket opens', () =>
	{
		openWebSocket(env);
		env.webSockets[0].onopen({});

		assert.isTrue(env.egw().pushAvailable());
	});

	it('fires onPushAvailabilityChange(true) exactly once when it opens, not again on every message', () =>
	{
		const calls : boolean[] = [];
		env.egw().onPushAvailabilityChange((available) => calls.push(available));

		openWebSocket(env);
		env.webSockets[0].onopen({});
		env.webSockets[0].onmessage({data: 'pong'});
		env.webSockets[0].onmessage({data: 'pong'});

		assert.deepEqual(calls, [true]);
	});

	it('force-closes a connection attempt that never completes within CONNECT_TIMEOUT_MS', () =>
	{
		openWebSocket(env);
		const ws = env.webSockets[0];

		assert.isFalse(ws.closeCalled);
		clock.tick(8000);

		assert.isTrue(ws.closeCalled);
	});

	it('does NOT flip to unavailable on a single dropped connection that reconnects within the grace period', () =>
	{
		const calls : boolean[] = [];
		openWebSocket(env);
		env.webSockets[0].onopen({});		// available
		env.egw().onPushAvailabilityChange((available) => calls.push(available));

		env.webSockets[0].onclose({wasClean: false, code: 1006, reason: ''});	// schedules a reconnect
		clock.tick(2000);	// reconnectTime after one failure (MIN_RECONNECT_TIME doubled) - openWebSocket()
		// retries, creating a 2nd FakeWebSocket
		env.webSockets[1].onopen({});		// reconnected well within PUSH_UNAVAILABLE_GRACE_MS

		assert.deepEqual(calls, [], 'a blip that reconnects quickly must not be reported as a transition at all');
		assert.isTrue(env.egw().pushAvailable());
	});

	it('flips to unavailable once a lost connection stays down through the whole grace period', () =>
	{
		const calls : boolean[] = [];
		openWebSocket(env);
		env.webSockets[0].onopen({});
		env.egw().onPushAvailabilityChange((available) => calls.push(available));

		env.webSockets[0].onclose({wasClean: false, code: 1006, reason: ''});
		clock.tick(10000);	// PUSH_UNAVAILABLE_GRACE_MS, with no reconnect having opened in the meantime

		assert.deepEqual(calls, [false]);
		assert.isFalse(env.egw().pushAvailable());
	});

	it('flips to unavailable immediately (no grace period) on a clean close - nothing will reconnect it', () =>
	{
		const calls : boolean[] = [];
		openWebSocket(env);
		env.webSockets[0].onopen({});
		env.egw().onPushAvailabilityChange((available) => calls.push(available));

		env.webSockets[0].onclose({wasClean: true, code: 1000, reason: ''});

		assert.deepEqual(calls, [false]);
		assert.isFalse(env.egw().pushAvailable());
	});

	it('stops calling a listener once unsubscribed', () =>
	{
		const calls : boolean[] = [];
		const unsubscribe = env.egw().onPushAvailabilityChange((available) => calls.push(available));
		unsubscribe();

		openWebSocket(env);
		env.webSockets[0].onopen({});

		assert.deepEqual(calls, []);
	});
});
