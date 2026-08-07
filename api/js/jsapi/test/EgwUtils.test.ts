/**
 * Tests for egw_utils.js ("utils" module) - MODULE_GLOBAL.
 *
 * No test coverage existed before this file. See EgwUtilsHarness for how
 * it's loaded (jQuery + egw_store.ts + a debug stub, on top of the core
 * env).
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwUtilsEnv, EgwUtilsEnv} from "./EgwUtilsHarness";

describe('egw_utils.js (utils)', () =>
{
	let env : EgwUtilsEnv;

	beforeEach(async() =>
	{
		env = await createEgwUtilsEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.ajaxUrl);
		assert.isFunction(instance.elemWindow);
		assert.isFunction(instance.uid);
		assert.isFunction(instance.decodePath);
		assert.isFunction(instance.encodePath);
		assert.isFunction(instance.encodePathComponent);
		assert.isFunction(instance.hashString);
		assert.isFunction(instance.htmlspecialchars);
		assert.isFunction(instance.getHiddenDimensions);
		assert.isFunction(instance.storeWindow);
		assert.isFunction(instance.getOpenWindows);
		assert.isFunction(instance.windowClosed);
		assert.isFunction(instance.copyTextToClipboard);
		assert.isFunction(instance.getCache);
		assert.isFunction(instance.invalidateCache);
	});

	describe('uid()', () =>
	{
		it('returns a hex string, incrementing on each call', () =>
		{
			const instance = env.egw();
			const first = instance.uid();
			const second = instance.uid();
			assert.equal(parseInt(second, 16), parseInt(first, 16) + 1);
		});
	});

	describe('ajaxUrl()', () =>
	{
		it('prefixes a bare menuaction with webserverUrl + json.php', () =>
		{
			assert.equal(env.egw().ajaxUrl('app.class.method'),
				'https://example.test/json.php?menuaction=app.class.method');
		});

		it('returns the input unchanged if it already contains menuaction=', () =>
		{
			const url = '/some/other.php?menuaction=app.class.method&foo=bar';
			assert.equal(env.egw().ajaxUrl(url), url);
		});
	});

	describe('elemWindow()', () =>
	{
		it('returns the window owning a given element', () =>
		{
			const div = env.window.document.createElement('div');
			env.window.document.body.appendChild(div);
			assert.equal(env.egw().elemWindow(div), env.window);
		});
	});

	describe('decodePath() / encodePath() / encodePathComponent()', () =>
	{
		it('decodePath() decodes a URI-encoded path', () =>
		{
			assert.equal(env.egw().decodePath('foo%20bar%2Fbaz'), 'foo bar/baz');
		});

		it('decodePath() returns the input unchanged and logs an error on malformed input', () =>
		{
			const instance = env.egw();
			const malformed = '%';
			assert.equal(instance.decodePath(malformed), malformed);
			assert.equal(env.debugCalls.length, 1);
			assert.equal(env.debugCalls[0][0], 'error');
		});

		it('encodePathComponent() encodes %, # and ? but strips /', () =>
		{
			assert.equal(env.egw().encodePathComponent('100%/#?'), '100%25%23%3F');
		});

		it('encodePath() encodes each path component but keeps / as separator', () =>
		{
			assert.equal(env.egw().encodePath('foo/100%/bar'), 'foo/100%25/bar');
		});
	});

	describe('hashString()', () =>
	{
		it('returns the SHA-256 hex digest of the input', async() =>
		{
			// Known SHA-256("abc")
			const expected = 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad';
			assert.equal(expected.length, 64);
			assert.equal(await env.egw().hashString('abc'), expected);
		});
	});

	describe('htmlspecialchars()', () =>
	{
		it('escapes &, ", < and > but leaves single quotes alone', () =>
		{
			assert.equal(env.egw().htmlspecialchars(`<a href="x">'&'</a>`),
				`&lt;a href=&quot;x&quot;&gt;'&amp;'&lt;/a&gt;`);
		});
	});

	describe('getCache() / invalidateCache()', () =>
	{
		it('getCache() returns the same object on repeated calls', () =>
		{
			const instance = env.egw();
			const cache = instance.getCache('mycache');
			cache.foo = 'bar';
			assert.equal(instance.getCache('mycache').foo, 'bar');
		});

		it('invalidateCache() with no attribute removes the whole named cache', () =>
		{
			const instance = env.egw();
			instance.getCache('mycache').foo = 'bar';
			instance.invalidateCache('mycache');
			assert.notEqual(instance.getCache('mycache').foo, 'bar');
		});

		it('invalidateCache() with a plain attribute name removes just that key', () =>
		{
			const instance = env.egw();
			const cache = instance.getCache('mycache');
			cache.foo = 'bar';
			cache.baz = 'qux';
			instance.invalidateCache('mycache', 'foo');
			assert.isUndefined(cache.foo);
			assert.equal(cache.baz, 'qux');
		});

		it('invalidateCache() with a "/regex/flags" string deletes matching keys', () =>
		{
			const instance = env.egw();
			const cache = instance.getCache('mycache');
			cache.foo_one = 'a';
			cache.foo_two = 'b';
			cache.bar = 'c';
			instance.invalidateCache('mycache', '/^foo_/');
			assert.isUndefined(cache.foo_one);
			assert.isUndefined(cache.foo_two);
			assert.equal(cache.bar, 'c');
		});

		it('KNOWN BUG: the "starts with /" check is a no-op comma expression, so any ' +
			'attribute string containing a later "/" is misparsed as /regex/flags, ' +
			'throwing on the resulting invalid flags', () =>
		{
			const instance = env.egw();
			instance.getCache('mycache').foo = 'bar';
			// Intended as a literal key "foo/bar", not a regex - but
			// `(_attr[0] === '/', _attr.indexOf('/', 1) !== -1)` evaluates (via the
			// comma operator) to just the second half, so the leading-slash check
			// never actually runs.
			assert.throws(() => instance.invalidateCache('mycache', 'foo/bar'));
		});
	});

	describe('storeWindow() / getOpenWindows() / windowClosed()', () =>
	{
		it('does nothing for an unnamed popup', () =>
		{
			const instance = env.egw();
			const popup = {name: ''} as unknown as Window;
			instance.storeWindow('myapp', popup);
			assert.deepEqual(instance.getOpenWindows('myapp'), {});
		});

		it('does nothing for a popup named "_blank"', () =>
		{
			const instance = env.egw();
			const popup = {name: '_blank'} as unknown as Window;
			instance.storeWindow('myapp', popup);
			assert.deepEqual(instance.getOpenWindows('myapp'), {});
		});

		it('stores a named popup, retrievable via getOpenWindows()', () =>
		{
			const instance = env.egw();
			const popup = {name: 'mypopup'} as unknown as Window;
			instance.storeWindow('myapp', popup);
			assert.property(instance.getOpenWindows('myapp'), 'mypopup');
		});

		it('getOpenWindows() with a regex filters names and returns a list, not the raw map', () =>
		{
			const instance = env.egw();
			instance.storeWindow('myapp', {name: 'foo_1'} as unknown as Window);
			instance.storeWindow('myapp', {name: 'bar_1'} as unknown as Window);
			const list = instance.getOpenWindows('myapp', '^foo_');
			assert.deepEqual(list, ['foo_1']);
		});

		it('getOpenWindows() with a regex expires entries older than 5 seconds', () =>
		{
			const instance = env.egw();
			instance.setSessionItem('myapp', 'windows', JSON.stringify({stale: Date.now() - 6000}));
			const list = instance.getOpenWindows('myapp', '.*');
			assert.notInclude(list, 'stale');
		});

		it('windowClosed() removes a window known by name after its 100ms delay', async() =>
		{
			const instance = env.egw();
			instance.storeWindow('myapp', {name: 'mypopup'} as unknown as Window);
			instance.windowClosed('myapp', 'mypopup');
			await new Promise(resolve => env.window.setTimeout(resolve, 150));
			assert.notProperty(instance.getOpenWindows('myapp'), 'mypopup');
		});

		it('windowClosed() given a Window-like object that reports itself as still open does NOT remove it', async() =>
		{
			const instance = env.egw();
			instance.storeWindow('myapp', {name: 'mypopup'} as unknown as Window);
			instance.windowClosed('myapp', {name: 'mypopup', closed: false} as unknown as Window);
			await new Promise(resolve => env.window.setTimeout(resolve, 150));
			assert.property(instance.getOpenWindows('myapp'), 'mypopup');
		});
	});

	describe('getHiddenDimensions()', () =>
	{
		it('measures a display:none element by temporarily showing it', () =>
		{
			const el = env.window.document.createElement('div');
			el.style.width = '150px';
			el.style.height = '75px';
			el.style.display = 'none';
			env.window.document.body.appendChild(el);

			const dim = env.egw().getHiddenDimensions(el);
			assert.equal(dim.w, 150);
			assert.equal(dim.h, 75);
		});

		it('KNOWN BUG: only restores display:none on browsers with computedStyleMap() ' +
			'(Chromium) - a `this.styles` typo (should be `this.style`) means the ' +
			'correct capture branch never runs, silently falling through to the ' +
			'computedStyleMap()-based branch; on Firefox (no computedStyleMap) neither ' +
			'branch captures anything, so the element is left visible afterwards', () =>
		{
			const el = env.window.document.createElement('div');
			el.style.display = 'none';
			env.window.document.body.appendChild(el);

			env.egw().getHiddenDimensions(el);

			const usesComputedStyleMap = typeof (env.window as any).Element.prototype.computedStyleMap === 'function';
			assert.equal(el.style.display, usesComputedStyleMap ? 'none' : '');
		});
	});

	describe('copyTextToClipboard()', () =>
	{
		it('uses the Clipboard API when available', async() =>
		{
			const clipboard = (env.window as any).navigator.clipboard;
			if (!clipboard)
			{
				return;
			}
			const writeText = sinon.stub(clipboard, 'writeText').resolves();
			const target = env.window.document.createElement('div');
			env.window.document.body.appendChild(target);
			await env.egw().copyTextToClipboard('hello', target);
			assert.isTrue(writeText.calledWith('hello'));
			writeText.restore();
		});

		it('KNOWN BUG: with the Clipboard API available but no target_element, throws ' +
			'instead of falling back to window - the `??` right-hand side ' +
			'unconditionally dereferences target_element.ownerDocument', () =>
		{
			const clipboard = (env.window as any).navigator.clipboard;
			if (!clipboard)
			{
				return;
			}
			assert.throws(() => env.egw().copyTextToClipboard('hello'));
		});
	});
});
