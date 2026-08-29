/**
 * Tests for egw_css.js ("css" module) - MODULE_WND_LOCAL - and egw_store.js
 * ("store" module) - MODULE_GLOBAL.
 *
 * Both are small, self-contained utility modules with no test coverage
 * before this file. See EgwCssStoreHarness for how they're loaded.
 */
import {assert} from "@open-wc/testing";
import {createEgwCssStoreEnv, EgwCssStoreEnv} from "./EgwCssStoreHarness";

describe('egw_css.js (css) / egw_store.js (store)', () =>
{
	let env : EgwCssStoreEnv;

	beforeEach(async() =>
	{
		env = await createEgwCssStoreEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.css);
		assert.isFunction(instance.getSessionItem);
		assert.isFunction(instance.setSessionItem);
		assert.isFunction(instance.removeSessionItem);
		assert.isFunction(instance.setLocalStorageItem);
		assert.isFunction(instance.getLocalStorageItem);
		assert.isFunction(instance.removeLocalStorageItem);
	});

	describe('css()', () =>
	{
		function getStyleSheet() : any
		{
			const style = env.window.document.querySelector('style');
			return style ? (style as any).sheet : null;
		}

		it('adds a new rule, creating a <style> element in the head', () =>
		{
			assert.isNull(env.window.document.querySelector('style'));

			env.egw('someapp', env.window).css('.foo', 'color: red;');

			const sheet = getStyleSheet();
			assert.isNotNull(sheet);
			assert.equal(sheet.cssRules.length, 1);
			assert.equal(sheet.cssRules[0].selectorText, '.foo');
		});

		it('a second, different selector is appended without removing the first', () =>
		{
			const css = env.egw('someapp', env.window).css.bind(env.egw('someapp', env.window));
			css('.foo', 'color: red;');
			css('.bar', 'color: blue;');

			const sheet = getStyleSheet();
			assert.equal(sheet.cssRules.length, 2);
			const selectors = [sheet.cssRules[0].selectorText, sheet.cssRules[1].selectorText];
			assert.include(selectors, '.foo');
			assert.include(selectors, '.bar');
		});

		it('calling css() again for the same selector with a falsy rule removes it', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.css('.foo', 'color: red;');
			instance.css('.foo');

			const sheet = getStyleSheet();
			assert.equal(sheet.cssRules.length, 0);
		});

		it('calling css() again for the same selector with a new rule replaces the old one', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.css('.foo', 'color: red;');
			instance.css('.foo', 'color: green;');

			const sheet = getStyleSheet();
			assert.equal(sheet.cssRules.length, 1);
			assert.equal(sheet.cssRules[0].selectorText, '.foo');
			assert.include(sheet.cssRules[0].style.cssText.toLowerCase(), 'green');
		});

		it('removing one selector out of several only removes that one', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.css('.foo', 'color: red;');
			instance.css('.bar', 'color: blue;');
			instance.css('.foo');

			const sheet = getStyleSheet();
			assert.equal(sheet.cssRules.length, 1);
			assert.equal(sheet.cssRules[0].selectorText, '.bar');
		});

		it('the rule actually applies to a matching element', () =>
		{
			const instance = env.egw('someapp', env.window);
			instance.css('.egw-test-marker', 'color: rgb(1, 2, 3);');

			const el = env.window.document.createElement('div');
			el.className = 'egw-test-marker';
			env.window.document.body.appendChild(el);

			const computed = (env.window as any).getComputedStyle(el);
			assert.equal(computed.color, 'rgb(1, 2, 3)');
		});

		it('two different windows (MODULE_WND_LOCAL) get independent stylesheets', () =>
		{
			const otherWindow = env.createWindow();
			// createWindow() doesn't get its own copy of egw_core.js, but css()
			// only needs _wnd.document, which every window (even one without the
			// script loaded) has.
			(otherWindow as any).document.head.innerHTML = '';

			env.egw('appA', env.window).css('.foo', 'color: red;');
			env.egw('appB', otherWindow).css('.bar', 'color: blue;');

			const mainSheet = getStyleSheet();
			assert.equal(mainSheet.cssRules.length, 1);
			assert.equal(mainSheet.cssRules[0].selectorText, '.foo');

			const otherStyle = (otherWindow as any).document.querySelector('style');
			assert.isNotNull(otherStyle);
			assert.equal(otherStyle.sheet.cssRules.length, 1);
			assert.equal(otherStyle.sheet.cssRules[0].selectorText, '.bar');
		});
	});

	describe('store', () =>
	{
		describe('session storage', () =>
		{
			it('setSessionItem() stores a value retrievable via getSessionItem()', () =>
			{
				const instance = env.egw();
				instance.setSessionItem('myapp', 'mykey', 'myvalue');
				assert.equal(instance.getSessionItem('myapp', 'mykey'), 'myvalue');
			});

			it('getSessionItem() returns null for a never-set key', () =>
			{
				assert.isNull(env.egw().getSessionItem('myapp', 'nosuchkey'));
			});

			it('removeSessionItem() removes a previously-set item', () =>
			{
				const instance = env.egw();
				instance.setSessionItem('myapp', 'mykey', 'myvalue');
				instance.removeSessionItem('myapp', 'mykey');
				assert.isNull(instance.getSessionItem('myapp', 'mykey'));
			});

			it('keys are namespaced by application - same key, different app, does not collide', () =>
			{
				const instance = env.egw();
				instance.setSessionItem('appA', 'mykey', 'valueA');
				instance.setSessionItem('appB', 'mykey', 'valueB');
				assert.equal(instance.getSessionItem('appA', 'mykey'), 'valueA');
				assert.equal(instance.getSessionItem('appB', 'mykey'), 'valueB');
			});

			it('the underlying sessionStorage key is "application-key" while no user is known', () =>
			{
				env.egw().setSessionItem('myapp', 'mykey', 'myvalue');
				assert.equal(env.window.sessionStorage.getItem('myapp-mykey'), 'myvalue');
			});
		});

		describe('keys are namespaced per user & instance', () =>
		{
			/**
			 * egw_store.js reads the user off the global egw at call time, the user module
			 * is not loaded in this harness - so fake just what mapKey() asks for.
			 */
			function login(account_id, domain = 'default')
			{
				(env.window as any).egw.user = (field) => ({account_id: account_id, domain: domain})[field];
			}

			it('the underlying key gets the user and instance appended', () =>
			{
				login(42);
				env.egw().setSessionItem('myapp', 'mykey', 'myvalue');
				assert.equal(env.window.sessionStorage.getItem('myapp-mykey-42@default'), 'myvalue');
			});

			it('the next user in the same tab does not read the previous one\'s session item', () =>
			{
				login(42);
				env.egw().setSessionItem('api', 'fw_tab_apps', 'user 42 tabs');

				// logout & login as someone else, sessionStorage survives that
				login(43);
				assert.isNull(env.egw().getSessionItem('api', 'fw_tab_apps'));
			});

			it('the same account_id on another instance does not read it either', () =>
			{
				login(42, 'first');
				env.egw().setLocalStorageItem('myapp', 'mykey', 'myvalue');

				login(42, 'second');
				assert.isNull(env.egw().getLocalStorageItem('myapp', 'mykey'));
			});

			it('the user gets his own value back', () =>
			{
				login(42);
				env.egw().setSessionItem('myapp', 'mykey', 'mine');
				login(43);
				env.egw().setSessionItem('myapp', 'mykey', 'theirs');

				login(42);
				assert.equal(env.egw().getSessionItem('myapp', 'mykey'), 'mine');
			});
		});

		describe('local storage', () =>
		{
			it('setLocalStorageItem() stores a value retrievable via getLocalStorageItem()', () =>
			{
				const instance = env.egw();
				instance.setLocalStorageItem('myapp', 'mykey', 'myvalue');
				assert.equal(instance.getLocalStorageItem('myapp', 'mykey'), 'myvalue');
			});

			it('getLocalStorageItem() returns null for a never-set key', () =>
			{
				assert.isNull(env.egw().getLocalStorageItem('myapp', 'nosuchkey'));
			});

			it('removeLocalStorageItem() removes a previously-set item', () =>
			{
				const instance = env.egw();
				instance.setLocalStorageItem('myapp', 'mykey', 'myvalue');
				instance.removeLocalStorageItem('myapp', 'mykey');
				assert.isNull(instance.getLocalStorageItem('myapp', 'mykey'));
			});

			it('the underlying localStorage key is "application-key" while no user is known', () =>
			{
				env.egw().setLocalStorageItem('myapp', 'mykey', 'myvalue');
				assert.equal(env.window.localStorage.getItem('myapp-mykey'), 'myvalue');
			});
		});

		it('is a MODULE_GLOBAL: storage set via one app/window context is visible via another', () =>
		{
			const otherWindow = env.createWindow();
			env.egw('appA', env.window).setSessionItem('shared', 'k', 'v');

			// store.js captures `_wnd` once at module registration (the root
			// window) - MODULE_GLOBAL, so every instance reads/writes the SAME
			// underlying sessionStorage regardless of which window's egw()
			// instance the method is called through.
			assert.equal(env.egw('appB', otherWindow).getSessionItem('shared', 'k'), 'v');
		});
	});
});
