/**
 * Tests for the egw_core.js composition engine.
 *
 * This is the part of api/js/jsapi that decides, for every call to
 * `egw(app?, window?)`, which module factories run and how their results
 * get merged into the object the caller receives. It is the single most
 * context-sensitive piece of the whole legacy jsapi - and, before this
 * file, had zero test coverage - so these tests exist to pin down its
 * contract ahead of a TypeScript rewrite, independent of any single
 * feature module (data, json, message, ...).
 *
 * Setup strategy: each test gets a brand-new copy of the engine, loaded
 * into a throwaway iframe by EgwCoreHarness (see there for why). Tests
 * register their own small, synthetic modules via egw.extend() rather than
 * relying on the real egw_*.js files, so failures point at the engine
 * itself rather than at some module's implementation detail.
 *
 * Pass criteria are documented per test. Two tests intentionally pin down
 * behaviour that looks like a caching bug in egw.module() rather than
 * "correct" behaviour - they exist so a rewrite makes a deliberate choice
 * about whether to keep or fix it, instead of silently changing it.
 *
 * Also covers the introspection/utility surface of the engine itself:
 * getAppName()'s fallback chain, dumpModules(), constant() (including its
 * _window filter), the "too many arguments" error, duplicate module-name
 * registration being silently ignored, and that cleaning up one window's
 * instances doesn't disturb a sibling instance for the same app in a
 * different window (two entries in the same instances[] hash bucket).
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwCoreEnv, EgwCoreEnv} from "./EgwCoreHarness";

function findInstanceForWindow(env : EgwCoreEnv, win : Window) : any
{
	const {instances} = env.egw.dumpInstances();
	for (const key in instances)
	{
		const found = instances[key].find((entry : any) => entry.window === win);
		if (found) return found;
	}
	return null;
}

describe('egw_core.js composition engine', () =>
{
	let env : EgwCoreEnv;

	beforeEach(async() =>
	{
		env = await createEgwCoreEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	describe('instance identity and caching', () =>
	{
		it('egw() with no arguments always returns the same, single global instance', () =>
		{
			assert.strictEqual(env.egw(), env.egw());
			// the global instance is literally the egw object itself
			assert.strictEqual(env.egw(), env.egw);
		});

		it('caches app-scoped instances by app name', () =>
		{
			const appA = env.egw('appA');
			const appAagain = env.egw('appA');
			const appB = env.egw('appB');

			assert.strictEqual(appA, appAagain);
			assert.notStrictEqual(appA, appB);
			assert.equal(appA.appName, 'appA');
		});

		it('caches window-scoped instances by window identity', () =>
		{
			const win = env.createWindow();
			const a = env.egw(win);
			const b = env.egw(win);

			assert.strictEqual(a, b);
			assert.notStrictEqual(a, env.egw());
		});

		it('keys combined (app, window) instances on both, independently', () =>
		{
			const w1 = env.createWindow();
			const w2 = env.createWindow();

			const appA_w1 = env.egw('appA', w1);
			const appA_w1_again = env.egw('appA', w1);
			const appA_w2 = env.egw('appA', w2);
			const appB_w1 = env.egw('appB', w1);

			assert.strictEqual(appA_w1, appA_w1_again);
			assert.notStrictEqual(appA_w1, appA_w2, 'same app, different window must differ');
			assert.notStrictEqual(appA_w1, appB_w1, 'same window, different app must differ');
		});

		it('throws for calls with more than 2 arguments', () =>
		{
			assert.throws(() => (env.egw as any)('a', 'b', 'c'), 'Invalid count of parameters');
		});
	});

	describe('getAppName()', () =>
	{
		it('falls back to "api" for the global instance when app_name() is falsy', () =>
		{
			env.egw.extend('app_name-stub', env.egw.MODULE_GLOBAL, () => ({app_name: () => null}));

			assert.equal(env.egw().getAppName(), 'api');
		});

		it('falls back to the instance\'s own appName (the app passed to egw(app)) when app_name() is falsy', () =>
		{
			env.egw.extend('app_name-stub', env.egw.MODULE_GLOBAL, () => ({app_name: () => null}));

			assert.equal(env.egw('myapp').getAppName(), 'myapp');
		});

		it('prefers a truthy app_name() over both appName and the "api" fallback', () =>
		{
			env.egw.extend('app_name-stub', env.egw.MODULE_GLOBAL, () => ({app_name: () => 'overridden'}));

			assert.equal(env.egw('myapp').getAppName(), 'overridden');
		});
	});

	describe('egw.dumpModules()', () =>
	{
		it('reflects every registered module\'s name and flag', () =>
		{
			env.egw.extend('myGlobalModule', env.egw.MODULE_GLOBAL, () => ({}));
			env.egw.extend('myAppModule', env.egw.MODULE_APP_LOCAL, () => ({}));

			const modules = env.egw.dumpModules();

			assert.equal(modules.myGlobalModule.name, 'myGlobalModule');
			assert.equal(modules.myGlobalModule.flags, env.egw.MODULE_GLOBAL);
			assert.equal(modules.myAppModule.flags, env.egw.MODULE_APP_LOCAL);
		});
	});

	describe('egw.constant()', () =>
	{
		it('updates a WND_LOCAL module\'s value for every existing instance, and for the module slot itself', () =>
		{
			env.egw.extend('counter', env.egw.MODULE_WND_LOCAL, () => ({value: 'initial'}));

			const win = env.createWindow();
			const rootInstance = env.egw('appA');
			const otherInstance = env.egw('appB', win);

			env.egw.constant('counter', 'value', 'updated');

			assert.equal(rootInstance.value, 'updated');
			assert.equal(otherInstance.value, 'updated');
			// the module slot itself was updated too, not just existing
			// instances - a brand-new instance for that window sees it too
			assert.equal(env.egw('appC', win).value, 'updated');
		});

		it('with a _window filter, only updates instances/module slots for that window', () =>
		{
			env.egw.extend('counter', env.egw.MODULE_WND_LOCAL, () => ({value: 'initial'}));

			const win = env.createWindow();
			const rootInstance = env.egw('appA');
			const otherInstance = env.egw('appB', win);

			env.egw.constant('counter', 'value', 'updated-for-win', win);

			assert.equal(otherInstance.value, 'updated-for-win');
			assert.equal(rootInstance.value, 'initial', 'the root window must be untouched');
		});
	});

	describe('duplicate module registration', () =>
	{
		it('a second extend() call with an already-used module name is silently ignored, factory never runs', () =>
		{
			const firstFactory = sinon.stub().returns({value: 'first'});
			const secondFactory = sinon.stub().returns({value: 'second'});
			env.egw.extend('dupe', env.egw.MODULE_GLOBAL, firstFactory);
			env.egw.extend('dupe', env.egw.MODULE_GLOBAL, secondFactory);

			assert.equal(env.egw().value, 'first');
			assert.isTrue(firstFactory.called);
			assert.isFalse(secondFactory.called);
		});
	});

	describe('module registration flags', () =>
	{
		it('MODULE_GLOBAL: one closure shared by every app and window', () =>
		{
			let counter = 0;
			env.egw.extend('counter', env.egw.MODULE_GLOBAL, () => ({
				increment: () => ++counter,
				get: () => counter
			}));

			const win = env.createWindow();
			const appA = env.egw('appA');
			const appB_win = env.egw('appB', win);

			appA.increment();
			appB_win.increment();

			assert.equal(appA.get(), 2);
			assert.equal(appB_win.get(), 2);
			assert.equal(env.egw().get(), 2);
		});

		it('MODULE_APP_LOCAL: independent closure per app name', () =>
		{
			env.egw.extend('counter', env.egw.MODULE_APP_LOCAL, () =>
			{
				let count = 0;
				return {increment: () => ++count, get: () => count};
			});

			const appA = env.egw('appA');
			const appB = env.egw('appB');

			appA.increment();
			appA.increment();

			assert.equal(appA.get(), 2);
			assert.equal(appB.get(), 0, 'a different app must not see appA\'s state');
			assert.equal(env.egw('appA').get(), 2, 're-fetching appA must reuse its closure');
		});

		it('MODULE_WND_LOCAL: independent closure per window, shared by every app using that window', () =>
		{
			env.egw.extend('counter', env.egw.MODULE_WND_LOCAL, () =>
			{
				let count = 0;
				return {increment: () => ++count, get: () => count};
			});

			const w1 = env.createWindow();
			const w2 = env.createWindow();

			const w1AppA = env.egw('appA', w1);
			const w1AppB = env.egw('appB', w1);
			const w2AppA = env.egw('appA', w2);

			w1AppA.increment();

			assert.equal(w1AppA.get(), 1);
			assert.equal(w1AppB.get(), 1, 'a different app in the SAME window must share window-local state');
			assert.equal(w2AppA.get(), 0, 'the same app in a DIFFERENT window must not share it');
		});

		it('merges global < app-local < window-local, with window-local winning on name collisions', () =>
		{
			env.egw.extend('g', env.egw.MODULE_GLOBAL, () => ({value: 'global'}));
			env.egw.extend('a', env.egw.MODULE_APP_LOCAL, () => ({value: 'app'}));
			env.egw.extend('w', env.egw.MODULE_WND_LOCAL, () => ({value: 'wnd'}));

			const win = env.createWindow();
			const combined = env.egw('someapp', win);

			assert.equal(combined.value, 'wnd');
		});
	});

	describe('late module registration (egw.extend() called after instances already exist)', () =>
	{
		it('a late MODULE_GLOBAL updates already-issued instances in place', () =>
		{
			const before = env.egw('appA');
			assert.isUndefined(before.lateValue);

			env.egw.extend('late', env.egw.MODULE_GLOBAL, () => ({lateValue: 'hello'}));

			assert.equal(before.lateValue, 'hello', 'the reference obtained before extend() must see the new property');
		});

		it('a late MODULE_APP_LOCAL updates existing app instances in place and gives new apps their own closure', () =>
		{
			const beforeA = env.egw('appA');

			env.egw.extend('late', env.egw.MODULE_APP_LOCAL, () =>
			{
				let count = 0;
				return {bump: () => ++count, get: () => count};
			});

			assert.isFunction(beforeA.bump);
			beforeA.bump();
			assert.equal(beforeA.get(), 1);

			const appB = env.egw('appB');
			assert.equal(appB.get(), 0, 'a newly requested app must get its own, fresh closure');
		});

		it('a late MODULE_WND_LOCAL updates existing window instances in place', () =>
		{
			const win = env.createWindow();
			const before = env.egw('appA', win);

			env.egw.extend('late', env.egw.MODULE_WND_LOCAL, () =>
			{
				let count = 0;
				return {bump: () => ++count, get: () => count};
			});

			assert.isFunction(before.bump);
			before.bump();
			assert.equal(before.get(), 1);
		});
	});

	describe('egw.module() side channel', () =>
	{
		it('returns the single shared MODULE_GLOBAL extension', () =>
		{
			env.egw.extend('g', env.egw.MODULE_GLOBAL, () => ({value: 'global'}));

			const mod = env.egw.module('g');

			assert.equal(mod.value, 'global');
			assert.strictEqual(env.egw.module('g'), mod);
		});

		it('looks up a MODULE_APP_LOCAL extension only for apps already instantiated via egw(app) (documents an asymmetry with the window case below)', () =>
		{
			env.egw.extend('a', env.egw.MODULE_APP_LOCAL, () => ({value: 'x'}));

			// 'appX' was never passed to egw(...) yet, so module() finds no slot for it
			assert.isNull(env.egw.module('a', 'appX'));

			env.egw('appX'); // this is what actually creates appX's app-local closure
			const mod = env.egw.module('a', 'appX');

			assert.equal(mod.value, 'x');
			assert.strictEqual(env.egw.module('a', 'appX'), mod);
		});

		it('KNOWN QUIRK: for a window with no prior egw(app, window) instance, module() rebuilds (and leaks) a fresh instance on every call instead of caching it', () =>
		{
			let created = 0;
			env.egw.extend('w', env.egw.MODULE_WND_LOCAL, () =>
			{
				created++;
				return {n: created};
			});
			// extend() eagerly creates the module for every window slot that
			// already exists - which includes the root window itself - so
			// `created` isn't 0 at this point. Track the delta instead.
			const baseline = created;

			const win = env.createWindow(); // no egw(app, win) call yet

			const first = env.egw.module('w', win);
			const second = env.egw.module('w', win);

			assert.notStrictEqual(first, second, 'each call rebuilds a throwaway instance - this is the bug');
			assert.equal(created - baseline, 2);

			// Once a real instance exists for the window, the slot is persisted
			// and module() correctly reuses it from then on.
			env.egw('someapp', win);
			const third = env.egw.module('w', win);
			const fourth = env.egw.module('w', win);

			assert.strictEqual(third, fourth, 'once a real instance slot exists, caching works correctly');
		});
	});

	describe('window cleanup', () =>
	{
		it('drops instances for a window and calls unregisterAllPlugins() on them when that window fires beforeunload', () =>
		{
			// Every real egw instance has unregisterAllPlugins() (implemented by
			// egw_json.js's MODULE_WND_LOCAL 'json' module). egw_core.js's
			// cleanup path (deleteWhere) calls it unconditionally on every
			// instance it removes, so a synthetic WND_LOCAL module stands in
			// for it here without needing to load egw_json.js.
			const unregisterAllPlugins = sinon.stub();
			env.egw.extend('json-ish', env.egw.MODULE_WND_LOCAL, () => ({unregisterAllPlugins}));

			const win = env.createWindow();
			const instance = env.egw('someapp', win);
			assert.strictEqual(instance.unregisterAllPlugins, unregisterAllPlugins);
			assert.isNotNull(findInstanceForWindow(env, win));

			win.dispatchEvent(new Event('beforeunload'));

			assert.isTrue(unregisterAllPlugins.calledOnce);
			assert.isNull(findInstanceForWindow(env, win), 'the instance must be removed from egw\'s bookkeeping');
		});

		it('cleaning up one window does not affect a sibling instance for the SAME app in a different window', () =>
		{
			// appA/w1 and appA/w2 live in the same `instances['appA']` hash
			// bucket (see egw_core.js's getEgwInstance) - closing one must
			// only remove its own entry, not the whole bucket.
			const unregisterAllPlugins = sinon.stub();
			env.egw.extend('json-ish', env.egw.MODULE_WND_LOCAL, () => ({unregisterAllPlugins}));

			const w1 = env.createWindow();
			const w2 = env.createWindow();
			env.egw('sameapp', w1);
			env.egw('sameapp', w2);

			w1.dispatchEvent(new Event('beforeunload'));

			assert.isNull(findInstanceForWindow(env, w1));
			assert.isNotNull(findInstanceForWindow(env, w2), 'the sibling instance must survive');
			assert.isTrue(unregisterAllPlugins.calledOnce, 'only the closed window\'s instance is cleaned up');
		});
	});
});
