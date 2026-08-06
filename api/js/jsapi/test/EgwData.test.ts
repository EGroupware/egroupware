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
 * Pass criteria are documented per test. The long-term caching tests are
 * environment-sensitive: they rely on real wall-clock time versus
 * data.js's 29s CACHE_LIFETIME constant, so only the "definitely fresh"
 * (elapsed ~0s) case is covered - the "stale but still cached" branch
 * (which additionally re-issues the request) is not, and is called out as
 * residual risk.
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
