/**
 * Tests for egw_debug.js ("debug" module) - MODULE_GLOBAL.
 *
 * No prior test coverage (it only appeared as a stub replacement in other
 * modules' harnesses). See EgwDebugHarness for how it's loaded.
 *
 * DEBUGLEVEL (console output threshold) and LOCAL_LOG_LEVEL (localStorage
 * persistence + global error handler) are hardcoded module constants (3
 * and 0 respectively), with no public setter - so a lot of this module's
 * code is provably dead in normal operation:
 * - LOCAL_LOG_LEVEL=0 means log_on_client() always returns immediately
 *   without writing to localStorage, and the module-level `if
 *   (LOCAL_LOG_LEVEL) {...}` block that would bind a global 'error'
 *   listener never runs at all.
 * - raise_error() is only ever called from debug()'s
 *   `LOCAL_LOG_LEVEL && _level == "error"` branch or from that same dead
 *   error handler - so it's entirely unreachable too.
 * - DEBUGLEVEL=3 means "log"-level console output never fires (needs
 *   >=4), and "navigation" never matches any of debug()'s four
 *   level-specific `if`s at all.
 * These are documented as KNOWN QUIRKs below rather than treated as bugs
 * to fix - only debug_level(), debug(), and show_log() are public API.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwDebugEnv, EgwDebugEnv} from "./EgwDebugHarness";

describe('egw_debug.js (debug)', () =>
{
	let env : EgwDebugEnv;

	beforeEach(async() =>
	{
		env = await createEgwDebugEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.debug_level);
		assert.isFunction(instance.debug);
		assert.isFunction(instance.show_log);
	});

	it('debug_level() returns the hardcoded level (3)', () =>
	{
		assert.equal(env.egw().debug_level(), 3);
	});

	describe('debug()', () =>
	{
		it('"error" logs via console.error (DEBUGLEVEL 3 >= 1)', () =>
		{
			const spy = sinon.stub(env.window.console, 'error');
			env.egw().debug('error', 'oops', 42);
			assert.isTrue(spy.calledOnceWith('oops', 42));
		});

		it('"warn" logs via console.warn (DEBUGLEVEL 3 >= 2)', () =>
		{
			const spy = sinon.stub(env.window.console, 'warn');
			env.egw().debug('warn', 'careful');
			assert.isTrue(spy.calledOnceWith('careful'));
		});

		it('"info" logs via console.info (DEBUGLEVEL 3 >= 3)', () =>
		{
			const spy = sinon.stub(env.window.console, 'info');
			env.egw().debug('info', 'fyi');
			assert.isTrue(spy.calledOnceWith('fyi'));
		});

		it('KNOWN QUIRK: "log" does NOT reach console.log - DEBUGLEVEL (3) is below the required threshold (4)', () =>
		{
			const spy = sinon.stub(env.window.console, 'log');
			env.egw().debug('log', 'just fyi');
			assert.isFalse(spy.called);
		});

		it('KNOWN QUIRK: "navigation" produces no console output at all - none of debug()\'s level checks match it', () =>
		{
			const logSpy = sinon.stub(env.window.console, 'log');
			const infoSpy = sinon.stub(env.window.console, 'info');
			const warnSpy = sinon.stub(env.window.console, 'warn');
			const errorSpy = sinon.stub(env.window.console, 'error');

			env.egw().debug('navigation', 'went somewhere');

			assert.isFalse(logSpy.called);
			assert.isFalse(infoSpy.called);
			assert.isFalse(warnSpy.called);
			assert.isFalse(errorSpy.called);
		});

		it('passes multiple arguments through to the console method', () =>
		{
			const spy = sinon.stub(env.window.console, 'error');
			env.egw().debug('error', 'a', 'b', {c: 1});
			assert.isTrue(spy.calledOnceWith('a', 'b', {c: 1}));
		});

		it('does not throw when the window has no console', () =>
		{
			const win = env.createWindow();
			delete (win as any).console;
			// debug() is MODULE_GLOBAL - always operates via the root window's
			// _wnd captured at registration, so calling it via a DIFFERENT
			// window's egw() instance is unaffected either way; this just
			// confirms the `typeof _wnd.console != "undefined"` guard works
			// if it were ever instantiated against a console-less window.
			assert.doesNotThrow(() => env.egw('someapp', win).debug('error', 'msg'));
		});

		it('KNOWN QUIRK: never writes to localStorage, regardless of level - LOCAL_LOG_LEVEL is hardcoded to 0 ("off")', () =>
		{
			env.window.localStorage.clear();
			env.egw().debug('error', 'should not be persisted');
			env.egw().debug('warn', 'nor this');
			env.egw().debug('info', 'nor this either');

			assert.equal(env.window.localStorage.length, 0);
		});
	});

	describe('show_log()', () =>
	{
		it('KNOWN BUG: throws when jQuery UI is not loaded, instead of falling back gracefully', () =>
		{
			// The guard is `window.jQuery && window.jQuery.ui.dialog` - missing
			// a `window.jQuery.ui &&` check, so accessing `.dialog` on
			// `undefined` throws. In a modern EGroupware install (no jQuery
			// UI bundled), show_log() is completely broken as a result -
			// it never reaches the console.log(get_client_log()) fallback
			// at the end of the function either, since the throw happens first.
			assert.throws(() => env.egw().show_log(), /jQuery\.ui is undefined|Cannot read propert/);
		});
	});
});
