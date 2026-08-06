/**
 * Tests for egw_json.js ("json" module) and egw_jsonq.js ("jsonq" module).
 *
 * Both are MODULE_WND_LOCAL ("json") / MODULE_GLOBAL ("jsonq") pieces of
 * the network layer every other module ultimately calls into. See
 * EgwJsonHarness for how the real, unmodified files are loaded with a
 * fake window.fetch() standing in for the network.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwJsonEnv, EgwJsonEnv} from "./EgwJsonHarness";

function wait(ms : number) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

describe('egw_json.js (json)', () =>
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

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.json);
		assert.isFunction(instance.request);
		assert.isFunction(instance.registerJSONPlugin);
		assert.isFunction(instance.jsonq);
	});

	describe('plugin registry', () =>
	{
		it('KNOWN LANDMINE: the default plugin "type" is the string "global", independent of the _global scope flag', () =>
		{
			// _type defaults to 'global' as a TYPE NAME, matched against
			// response.type - it has nothing to do with the _global (4th
			// arg) SCOPE flag, which chooses between the per-window
			// `plugins` registry and the cross-window `global_plugins`
			// one. Both default to false/'global' independently, so a
			// plugin registered with no _type and no _global still only
			// fires for responses whose type is literally "global", and
			// only within this one window.
			const cb = sinon.stub().returns(true);
			env.egw().registerJSONPlugin(cb, null); // no _type, no _global

			const req = env.egw().json('some.menuaction', [], null, null);
			req.handleResponse({response: [{type: 'global', data: {}}]});

			assert.isTrue(cb.calledOnce);
		});

		it('dispatches handlers for the same type in LIFO (most-recently-registered-first) order', () =>
		{
			const calls : string[] = [];
			env.egw().registerJSONPlugin(() => { calls.push('first'); return true; }, null, 'custom');
			env.egw().registerJSONPlugin(() => { calls.push('second'); return true; }, null, 'custom');

			env.egw().json('m', [], null, null).handleResponse({response: [{type: 'custom', data: {}}]});

			assert.deepEqual(calls, ['second', 'first']);
		});

		it('a _global=true plugin is shared across different windows, unlike a normal (per-window) one', () =>
		{
			const globalCb = sinon.stub().returns(true);
			const localCb = sinon.stub().returns(true);
			env.egw().registerJSONPlugin(globalCb, null, 'custom', true);
			env.egw().registerJSONPlugin(localCb, null, 'custom', false);

			const otherWindow = env.createWindow();
			env.egw(otherWindow).json('m', [], null, null).handleResponse({response: [{type: 'custom', data: {}}]});

			assert.isTrue(globalCb.calledOnce, 'a _global plugin registered in one window must still fire in another');
			assert.isFalse(localCb.called, 'a per-window plugin registered in one window must not fire in another');
		});

		it('unregisterJSONPlugin removes an exact callback+context match only', () =>
		{
			const cb = sinon.stub().returns(true);
			const ctxA = {};
			const ctxB = {};
			env.egw().registerJSONPlugin(cb, ctxA, 'custom');
			env.egw().registerJSONPlugin(cb, ctxB, 'custom');

			env.egw().unregisterJSONPlugin(cb, ctxA, 'custom');
			env.egw().json('m', [], null, null).handleResponse({response: [{type: 'custom', data: {}}]});

			assert.isTrue(cb.calledOnce, 'only the ctxB registration should remain');
			assert.strictEqual(cb.firstCall.thisValue, ctxB);
		});
	});

	describe('handleResponse', () =>
	{
		it('the built-in "data" plugin invokes the request callback with res.data, unwrapped', () =>
		{
			const callback = sinon.stub();
			const sender = {};
			env.egw().json('m', [], callback, null, true, sender).handleResponse({
				response: [{type: 'data', data: {hello: 'world'}}]
			});

			assert.isTrue(callback.calledOnceWith({hello: 'world'}));
			assert.strictEqual(callback.firstCall.thisValue, sender);
		});

		it('a non-"data" response also invokes the request callback directly, with the raw response entry', () =>
		{
			const callback = sinon.stub();
			env.egw().registerJSONPlugin(() => true, null, 'custom');
			env.egw().json('m', [], callback, null).handleResponse({
				response: [{type: 'custom', data: {foo: 'bar'}}]
			});

			assert.isTrue(callback.calledOnce);
			assert.deepEqual(callback.firstCall.args[0], {type: 'custom', data: {foo: 'bar'}});
		});

		it('a data-only response calls back once per entry (via the built-in "data" plugin) with no extra raw call', () =>
		{
			// "only_data" doesn't mean "never call back" - req.callback IS
			// this.callback, so the built-in "data" plugin already invokes
			// it once per entry. only_data instead suppresses a REDUNDANT
			// extra call at the end with just the last raw response entry
			// (see the mixed-response test below, where that extra call
			// does happen).
			const callback = sinon.stub();
			env.egw().json('m', [], callback, null).handleResponse({
				response: [{type: 'data', data: {a: 1}}, {type: 'data', data: {b: 2}}]
			});

			assert.equal(callback.callCount, 2);
			assert.deepEqual(callback.getCall(0).args[0], {a: 1});
			assert.deepEqual(callback.getCall(1).args[0], {b: 2});
		});

		it('a mixed response ALSO makes one extra raw call at the end, with the last response entry', () =>
		{
			const callback = sinon.stub();
			env.egw().registerJSONPlugin(() => true, null, 'custom');
			env.egw().json('m', [], callback, null).handleResponse({
				response: [{type: 'data', data: {a: 1}}, {type: 'custom', data: {foo: 'bar'}}]
			});

			assert.equal(callback.callCount, 2, 'once via the built-in "data" plugin, once via the raw fallback');
			assert.deepEqual(callback.getCall(0).args[0], {a: 1});
			assert.deepEqual(callback.getCall(1).args[0], {type: 'custom', data: {foo: 'bar'}});
		});
	});

	describe('sendRequest (async, via fetch)', () =>
	{
		it('POSTs to ajaxUrl(menuaction) with the parameters JSON-encoded in the body', () =>
		{
			env.egw().json('my.menu.action', [1, 'two'], null, null, true).sendRequest();

			assert.equal(env.fetchCalls.length, 1);
			const {url, init} = env.fetchCalls[0];
			assert.equal(url, 'https://example.test/json.php?menuaction=my.menu.action');
			assert.equal(init.method, 'POST');
			assert.deepEqual(JSON.parse(init.body), {request: {parameters: [1, 'two']}});
		});

		it('uses a GET query string instead of a body when method="GET" is requested', () =>
		{
			env.egw().json('my.menu.action', [1], null, null, true).sendRequest(true, 'GET');

			const {url, init} = env.fetchCalls[0];
			assert.isUndefined(init.body);
			assert.include(url, 'json_data=');
		});

		it('resolves the returned promise with the parsed response and dispatches it through handleResponse', async() =>
		{
			const callback = sinon.stub();
			const promise = env.egw().json('m', [], callback, null, true).sendRequest();

			env.fetchCalls[0].resolve({response: [{type: 'data', data: 'the-data'}]});
			await promise;

			assert.isTrue(callback.calledOnceWith('the-data'));
		});

		it('promise.abort() actually cancels the fetch, via a real AbortSignal reaching fetch()', () =>
		{
			// Previously `{...init, ...signal}` spread an AbortSignal instance
			// (whose "aborted"/"reason" properties are prototype accessors, not
			// own enumerable ones) so nothing reached the fetch init object at
			// all - promise.abort() was a no-op. Fixed to `{...init, signal}`.
			const promise : any = env.egw().json('m', [], null, null, true).sendRequest();

			// Not assert.instanceOf(signal, AbortSignal): the signal was
			// constructed inside the iframe's own realm, and chai's instanceOf
			// hangs trying to inspect a cross-realm EventTarget for its error
			// message. A constructor-name check avoids that entirely.
			assert.isFunction(promise.abort);
			assert.equal(env.fetchCalls[0].init.signal.constructor.name, 'AbortSignal');
			assert.isFalse(env.fetchCalls[0].init.signal.aborted);

			promise.abort();

			assert.isTrue(env.fetchCalls[0].init.signal.aborted);
		});
	});

	describe('request()', () =>
	{
		it('resolves with a single unwrapped data value', async() =>
		{
			const promise = env.egw().request('my.menu.action', ['x']);
			env.fetchCalls[0].resolve({response: [{type: 'data', data: 'single-value'}]});

			assert.equal(await promise, 'single-value');
		});

		it('resolves with an array when the response carries multiple data entries', async() =>
		{
			const promise = env.egw().request('my.menu.action', []);
			env.fetchCalls[0].resolve({
				response: [{type: 'data', data: 'first'}, {type: 'data', data: 'second'}]
			});

			assert.deepEqual(await promise, ['first', 'second']);
		});
	});
});

describe('egw_jsonq.js (jsonq)', () =>
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

	it('batches multiple jsonq() calls into a single api.queue request, then resolves each by its own uid', async() =>
	{
		const p1 = env.egw().jsonq('menu.one', [1]);
		const p2 = env.egw().jsonq('menu.two', [2]);

		await wait(150); // real wall-clock wait for jsonq's internal 100ms batching timer

		assert.equal(env.fetchCalls.length, 1, 'both calls must be sent together');
		// the menuaction ("api.queue") travels in the URL, not the body -
		// egw.request()'s _parameters (the jobs map) is a plain object, and
		// json_request's `[].concat(_parameters)` wraps a non-array as a
		// single-element array rather than spreading it.
		assert.include(env.fetchCalls[0].url, 'menuaction=api.queue');
		const body = JSON.parse(env.fetchCalls[0].init.body);
		const jobs = body.request.parameters[0];
		assert.equal(jobs.u0.menuaction, 'menu.one');
		assert.equal(jobs.u1.menuaction, 'menu.two');

		env.fetchCalls[0].resolve({
			response: [{
				type: 'data',
				data: {
					u0: [{type: 'data', data: 'result-one'}],
					u1: [{type: 'data', data: 'result-two'}]
				}
			}]
		});

		assert.equal(await p1, 'result-one');
		assert.equal(await p2, 'result-two');
	});

	it('calls callbeforesend just before the batch is sent, allowing parameters to be modified', async() =>
	{
		const callbeforesend = sinon.stub().callsFake((params : any[]) => { params.push('extra'); });
		env.egw().jsonq('menu.one', [1], null, null, callbeforesend);

		await wait(150);

		assert.isTrue(callbeforesend.calledOnce);
		const body = JSON.parse(env.fetchCalls[0].init.body);
		assert.deepEqual(body.request.parameters[0].u0.parameters, [1, 'extra']);
	});

	describe('registerPush', () =>
	{
		it('dispatches PushData to every registered callback', () =>
		{
			const cb1 = sinon.stub();
			const cb2 = sinon.stub();
			env.egw().registerPush(cb1);
			env.egw().registerPush(cb2);

			const data = {type: 'update', app: 'infolog', id: 1, account_id: 2};
			env.egw().registerPush(data);

			assert.isTrue(cb1.calledOnceWith(data));
			assert.isTrue(cb2.calledOnceWith(data));
		});

		it('a callback right after a throwing one still runs on that same dispatch', () =>
		{
			// Previously iterated `for (let n in push_callbacks)` forwards while
			// splicing out a throwing callback mid-loop - removing index 0
			// shifted "good" into index 0, but the loop had already moved on to
			// key '1', skipping it entirely for that dispatch. Fixed by
			// iterating backwards, so a splice never shifts anything not yet visited.
			const bad = sinon.stub().throws(new Error('boom'));
			const good = sinon.stub();
			env.egw().registerPush(bad);
			env.egw().registerPush(good);

			const data = {type: 'update', app: 'infolog', id: 1, account_id: 2};
			env.egw().registerPush(data);

			assert.equal(bad.callCount, 1, 'the throwing callback still ran once, and was then removed');
			assert.equal(good.callCount, 1, '"good" must run on the same dispatch, not be skipped');

			// bad is gone now, so a second dispatch also reaches good normally
			env.egw().registerPush(data);
			assert.equal(good.callCount, 2);
		});
	});
});
