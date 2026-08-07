/**
 * Tests for egw_data.js ("data" / "data_storage" modules).
 *
 * "data" is the only MODULE_APP_LOCAL module in the whole jsapi - it gets
 * a fresh, independent closure per application name (dataFetch,
 * dataCacheRegister/Unregister, dataRegisterFetch/UnregisterFetch).
 * "data_storage" is MODULE_GLOBAL - one shared row-data cache
 * (dataStoreUID, dataRegisterUID, ...) used by every app and window.
 * Getting that split right is exactly the kind of thing a TS rewrite could
 * easily flatten by accident, so these tests pin it down against the real,
 * unmodified egw_data.js (see EgwDataHarness for how it's loaded).
 *
 * Pass criteria are documented per test. The stale-cache test fakes the
 * iframe's own Date.now() rather than waiting on real wall-clock time
 * against data.js's 29s CACHE_LIFETIME constant.
 *
 * Not covered (documented residual risk): _clearCache()'s
 * QuotaExceededError recovery path (would need to actually exhaust
 * localStorage or fake that specific DOMException, for a purely
 * best-effort cleanup path) and set_account_data()-style widget
 * integration in dataSearchUIDs/dataRefreshUIDs beyond the minimal fake
 * widget shape already covered.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwDataEnv, EgwDataEnv} from "./EgwDataHarness";

const AJAX_GET_ROWS = 'EGroupware\\Api\\Etemplate\\Widget\\Nextmatch::ajax_get_rows';

/** Let any pending promise chains (eg. inside dataFetch's fetchCallback handling) settle */
function flushMicrotasks() : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, 0));
}

describe('egw_data.js (data / data_storage)', () =>
{
	let env : EgwDataEnv;

	beforeEach(async() =>
	{
		env = await createEgwDataEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers both modules with the expected methods', () =>
	{
		const appInstance = env.egw('appA');
		assert.isFunction(appInstance.dataFetch);
		assert.isFunction(appInstance.dataCacheRegister);
		assert.isFunction(appInstance.dataRegisterFetch);

		// data_storage (MODULE_GLOBAL) must be reachable from the bare global instance too
		assert.isFunction(env.egw().dataStoreUID);
		assert.isFunction(env.egw().dataGetUIDdata);
	});

	describe('data_storage: shared row-data cache (MODULE_GLOBAL)', () =>
	{
		it('dataStoreUID/dataGetUIDdata round-trip, visible from a completely different app instance', () =>
		{
			env.egw('appA').dataStoreUID('appA::123', {name: 'Alice'});

			assert.isTrue(env.egw('appB').dataHasUID('appA::123'));
			assert.deepEqual(env.egw('appB').dataGetUIDdata('appA::123').data, {name: 'Alice'});
		});

		it('dataRegisterUID calls back immediately if data is already known', () =>
		{
			env.egw().dataStoreUID('x::1', {v: 1});

			const cb = sinon.stub();
			env.egw().dataRegisterUID('x::1', cb, null, null, null);

			assert.isTrue(cb.calledOnceWith({v: 1}, 'x::1'));
		});

		it('dataDeleteUID removes the data and unregisters callbacks', () =>
		{
			env.egw().dataStoreUID('x::1', {v: 1});
			const cb = sinon.stub();
			env.egw().dataRegisterUID('x::1', cb, null, null, null);
			cb.resetHistory();

			env.egw().dataDeleteUID('x::1');

			assert.isFalse(env.egw().dataHasUID('x::1'));
			// a fresh store must not call the (now unregistered) old callback
			env.egw().dataStoreUID('x::1', {v: 2});
			assert.isFalse(cb.called);
		});

		it('dataStoreUID removes a callback that throws, without blocking others, and never calls it again', () =>
		{
			const bad = sinon.stub().throws(new Error('boom'));
			const good = sinon.stub();
			env.egw().dataRegisterUID('x::1', bad, null, null, null);
			env.egw().dataRegisterUID('x::1', good, null, null, null);

			env.egw().dataStoreUID('x::1', {v: 1});
			assert.equal(good.callCount, 1);
			assert.equal(bad.callCount, 1, 'it was still attempted once, then removed for throwing');

			env.egw().dataStoreUID('x::1', {v: 2});
			assert.equal(good.callCount, 2, 'good must keep receiving updates');
			assert.equal(bad.callCount, 1, 'bad must not be called again');
		});

		it('dataStoreUID(_skipCallback=true) updates storage without notifying any registered callback', () =>
		{
			const cb = sinon.stub();
			env.egw().dataRegisterUID('x::1', cb, null, null, null); // not yet known, no immediate call
			cb.resetHistory();

			env.egw().dataStoreUID('x::1', {v: 1}, true);

			assert.isFalse(cb.called);
			assert.deepEqual(env.egw().dataGetUIDdata('x::1').data, {v: 1});
		});

		it('logs (but does not throw) when a uid is unknown and no execId/widgetId is given to fetch it', () =>
		{
			const debugSpy = sinon.spy(env.egw(), 'debug');

			env.egw().dataRegisterUID('appA::unknown', sinon.stub(), null, null, null);

			assert.isTrue(debugSpy.calledWith('log'));
		});

		describe('batched refresh queue (execId + widgetId, uid not yet known)', () =>
		{
			function wait(ms : number) : Promise<void>
			{
				return new Promise(resolve => setTimeout(resolve, ms));
			}

			// Unique uids (not "appA::1"/"appA::2" used elsewhere in this file):
			// dataRegisterUID's long-term-storage path can leave entries in the
			// REAL window.localStorage, which an about:blank test iframe shares
			// with the top page's origin - a stray leftover for a reused uid
			// would make a test see the "already known" branch instead of the
			// queue branch under test.

			it('KNOWN BUG: the queued refresh crashes (silently, inside a setTimeout) when _context is null', async() =>
			{
				// The timer callback forwards whatever _context the ORIGINAL
				// dataRegisterUID call was given straight into dataFetch(),
				// which unconditionally dereferences `_context.lastModification`
				// with no null check (unlike parseServerResponse elsewhere in
				// this same file, which does check `_context != null` first).
				// `null` is a completely ordinary, common _context value - see
				// the "batches multiple..." test below, which passes `{}`
				// specifically to avoid tripping this.
				const instance = env.egw('appA');
				instance.dataRegisterUID('appA::qbug', sinon.stub(), null, 'exec1', 'widget1');

				await wait(150);

				assert.equal(env.jsonCalls.length, 0, 'dataFetch() threw before ever reaching egw.json()');
			});

			it('batches multiple dataRegisterUID calls sharing an execId into one dataFetch "refresh" request after ~100ms', async() =>
			{
				const cb1 = sinon.stub();
				const cb2 = sinon.stub();
				const instance = env.egw('appA');

				// {} rather than null - see the KNOWN BUG test above
				instance.dataRegisterUID('appA::q1', cb1, {}, 'exec1', 'widget1');
				instance.dataRegisterUID('appA::q2', cb2, {}, 'exec1', 'widget1');

				await wait(150);

				assert.equal(env.jsonCalls.length, 1, 'both must batch into a single request');
				const params = env.jsonCalls[0].parameters;
				assert.equal(params[0], 'exec1');
				assert.deepEqual(params[1].refresh, ['q1', 'q2'], 'uids in the queue, with the app prefix stripped');
				assert.equal(params[3], 'widget1');

				env.jsonCalls[0].respond({
					order: ['q1', 'q2'], data: {'q1': {v: 'one'}, 'q2': {v: 'two'}}, total: 2, lastModification: 1
				});

				assert.isTrue(cb1.calledOnceWith({v: 'one'}, 'appA::q1'));
				assert.isTrue(cb2.calledOnceWith({v: 'two'}, 'appA::q2'));
			});

			it('does not add the same uid to the queue twice', async() =>
			{
				const instance = env.egw('appA');
				instance.dataRegisterUID('appA::q3', sinon.stub(), {}, 'exec1', 'widget1');
				instance.dataRegisterUID('appA::q3', sinon.stub(), {}, 'exec1', 'widget1');

				await wait(150);

				assert.deepEqual(env.jsonCalls[0].parameters[1].refresh, ['q3']);
			});
		});
	});

	describe('data: per-app dataFetch (MODULE_APP_LOCAL)', () =>
	{
		it('requests rows via ajax_get_rows and stores the prefixed result in the global data cache', () =>
		{
			const received : any[] = [];
			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 2}, {}, 'widget1',
				(result : any) => received.push(result), {prefix: 'appA'});

			assert.equal(env.jsonCalls.length, 1);
			assert.equal(env.jsonCalls[0].menuaction, AJAX_GET_ROWS);

			env.respondToLastJsonCall({
				order: ['1', '2'],
				data: {'1': {name: 'one'}, '2': {name: 'two'}},
				total: 2,
				lastModification: 1000,
				readonlys: {}
			});

			assert.equal(received.length, 1);
			assert.deepEqual(received[0].order, ['appA::1', 'appA::2']);
			assert.equal(received[0].total, 2);

			assert.deepEqual(env.egw().dataGetUIDdata('appA::1').data, {name: 'one'});
			assert.deepEqual(env.egw().dataGetUIDdata('appA::2').data, {name: 'two'});
		});

		it('isolates dataRegisterFetch callbacks per app - a different app with the same prefix must not see them', async() =>
		{
			const interceptA = sinon.stub().returns({order: ['1'], data: {'1': {v: 'A'}}, total: 1});
			env.egw('appA').dataRegisterFetch('shared-prefix', interceptA, null);

			env.egw('appB').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'shared-prefix'});
			await flushMicrotasks();

			assert.isFalse(interceptA.called, 'appB must not trigger appA\'s registered fetch callback');
			assert.equal(env.jsonCalls.length, 1, 'appB must fall through to the normal ajax_get_rows request');
		});

		it('dataRegisterFetch intercepts dataFetch and skips ajax_get_rows when it returns a result', async() =>
		{
			const intercept = sinon.stub().returns({order: ['1'], data: {'1': {name: 'intercepted'}}, total: 1});
			env.egw('appA').dataRegisterFetch('appA', intercept, null);

			const received : any[] = [];
			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {filterX: 1}, 'w1',
				r => received.push(r), {prefix: 'appA'});
			await flushMicrotasks();

			assert.isTrue(intercept.calledOnce);
			assert.equal(env.jsonCalls.length, 0, 'must not fall back to ajax_get_rows');
			assert.equal(received.length, 1);
			assert.deepEqual(received[0].order, ['appA::1']);
			assert.deepEqual(env.egw().dataGetUIDdata('appA::1').data, {name: 'intercepted'});
		});

		it('falls back to ajax_get_rows when the registered fetch callback returns a falsy value', async() =>
		{
			const intercept = sinon.stub().returns(false);
			env.egw('appA').dataRegisterFetch('appA', intercept, null);

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
			await flushMicrotasks();

			assert.isTrue(intercept.calledOnce);
			assert.equal(env.jsonCalls.length, 1);
		});

		it('falls back to ajax_get_rows when the registered fetch callback resolves to a falsy value', async() =>
		{
			const intercept = sinon.stub().returns(Promise.resolve(false));
			env.egw('appA').dataRegisterFetch('appA', intercept, null);

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
			await flushMicrotasks();

			assert.equal(env.jsonCalls.length, 1);
		});

		it('falls back to ajax_get_rows when the registered fetch callback rejects (misbehaving callback)', async() =>
		{
			const intercept = sinon.stub().returns(Promise.reject(new Error('boom')));
			env.egw('appA').dataRegisterFetch('appA', intercept, null);

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
			await flushMicrotasks();

			assert.equal(env.jsonCalls.length, 1);
		});

		it('dataUnregisterFetch removes only the specified callback', async() =>
		{
			const cb1 = sinon.stub().returns({order: [], data: {}, total: 0});
			const cb2 = sinon.stub().returns({order: [], data: {}, total: 0});
			env.egw('appA').dataRegisterFetch('appA', cb1, null);
			env.egw('appA').dataRegisterFetch('appA', cb2, null);

			env.egw('appA').dataUnregisterFetch('appA', cb1);

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
			await flushMicrotasks();

			assert.isFalse(cb1.called);
			assert.isTrue(cb2.called);
		});

		it('dataUnregisterFetch with no callback removes ALL registered fetch callbacks for that prefix', async() =>
		{
			const cb1 = sinon.stub().returns({order: [], data: {}, total: 0});
			const cb2 = sinon.stub().returns({order: [], data: {}, total: 0});
			env.egw('appA').dataRegisterFetch('appA', cb1, null);
			env.egw('appA').dataRegisterFetch('appA', cb2, null);

			env.egw('appA').dataUnregisterFetch('appA');

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
			await flushMicrotasks();

			assert.isFalse(cb1.called);
			assert.isFalse(cb2.called);
			assert.equal(env.jsonCalls.length, 1, 'falls through to the real request once no interceptor remains');
		});
	});

	describe('dataFetch(): query-range flags', () =>
	{
		it('no_data forces lastModification to the max value', () =>
		{
			env.egw('appA').dataFetch('exec1', {no_data: true}, {}, 'w1', () => {}, {prefix: 'appA'});
			assert.equal(env.jsonCalls[0].parameters[5], 0xFFFFFFFFFFFF);
		});

		it('only_data forces lastModification to 0 (always considered stale)', () =>
		{
			env.egw('appA').dataFetch('exec1', {only_data: true}, {}, 'w1', () => {}, {prefix: 'appA'});
			assert.equal(env.jsonCalls[0].parameters[5], 0);
		});

		it('a string "refresh" value is normalized to a single-element array in _context', () =>
		{
			// _context.refresh is set as a side effect, inspectable via the
			// second dataFetch() below reusing the SAME context object
			const context : any = {prefix: 'appA'};
			env.egw('appA').dataFetch('exec1', {refresh: '1'}, {}, 'w1', () => {}, context);
			assert.deepEqual(context.refresh, ['1']);
		});

		it('a targeted "refresh" fetch nulls out and removes any requested uid the server does not return', () =>
		{
			env.egw('appA').dataStoreUID('appA::1', {v: 'old'});
			env.egw('appA').dataStoreUID('appA::2', {v: 'old2'});
			const cb = sinon.stub();
			env.egw().dataRegisterUID('appA::2', cb, null, null, null);
			cb.resetHistory(); // registering itself calls back immediately with the current value

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 0, only_data: true, refresh: ['1', '2']},
				{}, 'w1', () => {}, {prefix: 'appA'});

			// server only returns uid '1' - uid '2' was deleted server-side
			env.jsonCalls[0].respond({order: ['1'], data: {'1': {v: 'new'}}, total: 1, lastModification: 999});

			assert.deepEqual(env.egw('appA').dataGetUIDdata('appA::1').data, {v: 'new'});
			assert.isFalse(env.egw('appA').dataHasUID('appA::2'), 'uid 2 must have been deleted (requested but not returned)');
			assert.isTrue(cb.calledOnceWith(null, 'appA::2'), 'listeners are notified with null before removal');
		});
	});

	describe('dataFetch(): knownUids', () =>
	{
		it('KNOWN QUIRK: the 200-item known-uid limit never actually truncates anything', () =>
		{
			// `if (knownUids > KNOWN_UID_LIMIT)` compares an ARRAY to a number,
			// which is always false for any realistic uid list (array-to-number
			// coercion of a multi-element array is NaN) - and even if it were
			// true, `knownUids.slice(...)`'s result is never assigned back
			// anywhere. So a huge known-uid list is always sent in full.
			const hugeList = Array.from({length: 300}, (_, i) => String(i));

			env.egw('appA').dataFetch('exec1', {}, {}, 'w1', () => {}, {prefix: 'appA'}, hugeList);

			assert.equal(env.jsonCalls[0].parameters[4].length, 300, 'all 300 are sent - the 200-item cap is dead code');
		});

		it('falls back to egw.dataKnownUIDs(prefix) when _knownUids is not given', () =>
		{
			env.egw('appA').dataStoreUID('appA::1', {v: 1});

			env.egw('appA').dataFetch('exec1', {}, {}, 'w1', () => {}, {prefix: 'appA'});

			assert.deepEqual(env.jsonCalls[0].parameters[4], ['1']);
		});
	});

	describe('data: long-term client-side query caching (dataCacheRegister)', () =>
	{
		// dataCacheRegister/dataFetch write to real window.localStorage (and,
		// via dataRegisterUID, to per-uid long-term storage too) - clean up
		// anything a test added so runs don't leak state into each other.
		let localStorageKeysBefore : string[];

		beforeEach(() =>
		{
			localStorageKeysBefore = Object.keys(env.window.localStorage);
		});

		afterEach(() =>
		{
			Object.keys(env.window.localStorage)
				.filter(key => !localStorageKeysBefore.includes(key))
				.forEach(key => env.window.localStorage.removeItem(key));
		});

		it('caches a fresh query result and answers a repeat query from cache without hitting the server again', () =>
		{
			const queryKey = 'test-query-' + Date.now();
			const cacheKey = 'cached_fetch_appA::' + queryKey;

			env.egw('appA').dataCacheRegister('appA', () => queryKey, null, null);

			env.egw('appA').dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
			assert.equal(env.jsonCalls.length, 1, 'first fetch must go to the server, cache is empty');

			env.respondToLastJsonCall({
				order: ['1'], data: {'1': {v: 'fresh'}}, total: 1,
				lastModification: Math.floor(Date.now() / 1000)
			});
			assert.isNotNull(env.window.localStorage.getItem(cacheKey), 'result must have been cached');

			const received2 : any[] = [];
			env.egw('appA').dataFetch('exec2', {start: 0, num_rows: 1}, {}, 'w1',
				r => received2.push(r), {prefix: 'appA'});

			assert.equal(env.jsonCalls.length, 1, 'a fresh cache hit must not issue a second server request');
			assert.equal(received2.length, 1);
			assert.deepEqual(received2[0].order, ['appA::1']);
		});

		it('answers immediately from a STALE cache, but also re-fetches from the server', () =>
		{
			// Fakes the iframe's own Date.now() (not the top page's) so the
			// cache-age check (`(Date.now()/1000) - cached.lastModification
			// < CACHE_LIFETIME`, 29s) can be deterministically pushed past
			// stale - no real wall-clock wait needed.
			const dateNowStub = sinon.stub((env.window as any).Date, 'now');
			try
			{
				const queryKey = 'stale-test';
				const instance = env.egw('appA');
				instance.dataCacheRegister('appA', () => queryKey, null, null);

				dateNowStub.returns(1_000_000_000);
				instance.dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});
				assert.equal(env.jsonCalls.length, 1);
				env.jsonCalls[0].respond({order: ['1'], data: {'1': {v: 'fresh'}}, total: 1, lastModification: 1_000_000});

				dateNowStub.returns(1_000_000_000 + 30_000); // +30s real time -> past the 29s CACHE_LIFETIME

				const received : any[] = [];
				instance.dataFetch('exec2', {start: 0, num_rows: 1}, {}, 'w1', r => received.push(r), {prefix: 'appA'});

				assert.equal(received.length, 1, 'answers immediately from the stale cache');
				assert.deepEqual(received[0].order, ['appA::1']);
				assert.equal(env.jsonCalls.length, 2, 'but ALSO re-fetches, since the cache is stale');
			}
			finally
			{
				dateNowStub.restore();
			}
		});

		it('dataCacheUnregister removes a specific callback, leaving others for the same prefix intact', () =>
		{
			const cbA = sinon.stub().returns('keyA');
			const cbB = sinon.stub().returns('keyB');
			const instance = env.egw('appA');
			instance.dataCacheRegister('appA', cbA, null, null);
			instance.dataCacheRegister('appA', cbB, null, null);

			instance.dataCacheUnregister('appA', cbA);
			instance.dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});

			assert.isFalse(cbA.called);
			assert.isTrue(cbB.called);
		});

		it('dataCacheUnregister with no callback removes ALL registered cache callbacks for that prefix', () =>
		{
			const cbA = sinon.stub().returns('keyA');
			const instance = env.egw('appA');
			instance.dataCacheRegister('appA', cbA, null, null);

			instance.dataCacheUnregister('appA');
			instance.dataFetch('exec1', {start: 0, num_rows: 1}, {}, 'w1', () => {}, {prefix: 'appA'});

			assert.isFalse(cbA.called);
		});
	});

	describe('data_storage: nextmatch widget bookkeeping (dataSearchUIDs / dataRefreshUIDs / dataRefreshUID)', () =>
	{
		it('dataSearchUIDs finds widgets registered against a uid via their callback context', () =>
		{
			env.egw().dataStoreUID('appA::1', {v: 1});
			const fakeWidget = {controller: {dataStorePrefix: 'appA'}};
			env.egw().dataRegisterUID('appA::1', () => {}, {self: {_widget: fakeWidget}}, null, null);

			const matches = env.egw().dataSearchUIDs('appA::1');

			assert.deepEqual(matches['appA::1'], [fakeWidget]);
		});

		it('dataRefreshUIDs calls refresh() on matched widgets with the app-unprefixed uid', () =>
		{
			env.egw().dataStoreUID('appA::1', {v: 1});
			const fakeWidget = {controller: {dataStorePrefix: 'appA'}, refresh: sinon.stub()};
			env.egw().dataRegisterUID('appA::1', () => {}, {self: {_widget: fakeWidget}}, null, null);

			const widgets = env.egw().dataRefreshUIDs('appA::1', 'update');

			assert.deepEqual(widgets, [fakeWidget]);
			assert.isTrue(fakeWidget.refresh.calledOnceWith(['1'], 'update'));
		});

		it('dataRefreshUID re-fetches a known, registered uid', () =>
		{
			env.egw().dataStoreUID('appA::1', {v: 1});
			env.egw().dataRegisterUID('appA::1', () => {}, {self: {}}, 'exec1', 'widget1');

			const ok = env.egw().dataRefreshUID('appA::1');

			assert.isTrue(ok);
			assert.equal(env.jsonCalls.length, 1);
			assert.equal(env.jsonCalls[0].parameters[0], 'exec1');
		});

		it('dataRefreshUID returns false for an unknown uid without making a request', () =>
		{
			const ok = env.egw().dataRefreshUID('nope::999');

			assert.isFalse(ok);
			assert.equal(env.jsonCalls.length, 0);
		});
	});
});
