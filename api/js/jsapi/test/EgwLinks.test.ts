/**
 * Tests for egw_links.js ("links" module) - MODULE_GLOBAL.
 *
 * See EgwLinksPrefsUserHarness for how jsonq() is stubbed to support
 * link_title()'s batching contract (flush() simulates the ~100ms timer
 * that gathers multiple pending title requests into one call, separately
 * from respond() simulating the server's reply).
 *
 * NOT covered (documented residual risk): link_quick_add() (a full
 * et2-select web component) and the "cats" branch of preferences'
 * show_preferences() interplay with link_get_registry (covered lightly
 * in EgwPreferences.test.ts already).
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwLinksPrefsUserEnv, EgwLinksPrefsUserEnv} from "./EgwLinksPrefsUserHarness";

/**
 * link_title()'s response always arrives via a promise chain
 * (jsonq().then(response => this.link_title_callback(response))), even
 * when a plain callback was given - respond() only resolves that promise,
 * it doesn't invoke callbacks synchronously. Tests must let that
 * microtask settle before checking a callback fired.
 */
function wait(ms : number = 0) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

describe('egw_links.js (links)', () =>
{
	let env : EgwLinksPrefsUserEnv;

	beforeEach(async() =>
	{
		env = await createEgwLinksPrefsUserEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.link);
		assert.isFunction(instance.link_get_registry);
		assert.isFunction(instance.set_link_registry);
		assert.isFunction(instance.get_mime_info);
		assert.isFunction(instance.mime_open);
		assert.isFunction(instance.link_title);
		assert.isFunction(instance.deepExtend);
	});

	describe('deepExtend()', () =>
	{
		it('deep merges nested objects without mutating any source', () =>
		{
			const target = {a: 1, nested: {x: 1}};
			const source = {nested: {y: 2}, b: 3};
			const result : any = env.egw().deepExtend(target, source);

			assert.deepEqual(result, {a: 1, nested: {x: 1, y: 2}, b: 3});
			assert.deepEqual(source, {nested: {y: 2}, b: 3}, 'source must be untouched');
		});

		it('clones arrays rather than sharing references', () =>
		{
			const source = {list: [1, 2, 3]};
			const result : any = env.egw().deepExtend({}, source);
			result.list.push(4);
			assert.deepEqual(source.list, [1, 2, 3]);
		});

		it('merges multiple sources left-to-right, later ones winning', () =>
		{
			const result = env.egw().deepExtend({}, {a: 1}, {a: 2, b: 3});
			assert.deepEqual(result, {a: 2, b: 3});
		});

		it('returns {} for a falsy target', () =>
		{
			assert.deepEqual(env.egw().deepExtend(null as any, {a: 1}), {});
		});

		it('skips falsy source arguments without throwing', () =>
		{
			assert.deepEqual(env.egw().deepExtend({a: 1}, null as any, undefined as any), {a: 1});
		});
	});

	describe('link_get_registry() / set_link_registry()', () =>
	{
		it('returns false (and alerts) before the registry has ever been set', () =>
		{
			const alertStub = sinon.stub(env.window, 'alert');
			assert.isFalse(env.egw().link_get_registry('infolog'));
			assert.isTrue(alertStub.called);
		});

		it('returns false for an app not in the registry, or a missing named attribute', () =>
		{
			env.egw().set_link_registry({infolog: {view: {menuaction: 'x'}}});
			assert.isFalse(env.egw().link_get_registry('nosuchapp'));
			assert.isFalse(env.egw().link_get_registry('infolog', 'nosuchattr'));
		});

		it('returns a clone of the whole app registry when no name is given', () =>
		{
			env.egw().set_link_registry({infolog: {view: {menuaction: 'x'}}});
			const reg : any = env.egw().link_get_registry('infolog');
			reg.view.menuaction = 'tampered';
			assert.equal((env.egw().link_get_registry('infolog', 'view') as any).menuaction, 'x');
		});

		it('defaults a missing "name" entry to the app-name itself, and caches it', () =>
		{
			env.egw().set_link_registry({infolog: {}});
			assert.equal(env.egw().link_get_registry('infolog', 'name'), 'infolog');
			assert.property(env.egw().link_get_registry('infolog'), 'name');
		});

		describe('icon default', () =>
		{
			it('derives "<icon_app>/<icon>" from the user\'s app-data when available', () =>
			{
				env.egw().set_user({apps: {infolog: {icon: 'special', icon_app: 'customapp'}}});
				env.egw().set_link_registry({infolog: {}});
				assert.equal(env.egw().link_get_registry('infolog', 'icon'), 'customapp/special');
			});

			it('falls back to "<app>/navbar" when the user has no icon data for that app', () =>
			{
				env.egw().set_user({apps: {}});
				env.egw().set_link_registry({infolog: {}});
				assert.equal(env.egw().link_get_registry('infolog', 'icon'), 'infolog/navbar');
			});
		});

		it('the whole-registry form only takes effect once - a second call is silently ignored', () =>
		{
			env.egw().set_link_registry({infolog: {view: {menuaction: 'first'}}});
			env.egw().set_link_registry({infolog: {view: {menuaction: 'second'}}});
			assert.equal((env.egw().link_get_registry('infolog', 'view') as any).menuaction, 'first');
		});

		it('the per-app form (_app given) always overwrites that app\'s entry', () =>
		{
			env.egw().set_link_registry({infolog: {view: {menuaction: 'first'}}});
			env.egw().set_link_registry({view: {menuaction: 'second'}}, 'infolog');
			assert.equal((env.egw().link_get_registry('infolog', 'view') as any).menuaction, 'second');
		});
	});

	describe('get_mime_info()', () =>
	{
		beforeEach(() =>
		{
			env.egw().set_link_registry({
				filemanager: {mime: {'text/plain': {menuaction: 'filemanager.view'}}},
				other: {mime: {'text/plain': {menuaction: 'other.view'}}},
				mail: {mime: {'/^message\\//': {menuaction: 'mail.view'}}}
			});
		});

		it('prefers an exact mime match over a wildcard', () =>
		{
			assert.equal((env.egw().get_mime_info('text/plain') as any).menuaction, 'filemanager.view');
		});

		it('falls back to a wildcard (regex) match', () =>
		{
			assert.equal((env.egw().get_mime_info('message/rfc822') as any).menuaction, 'mail.view');
		});

		it('returns null when nothing matches', () =>
		{
			assert.isNull(env.egw().get_mime_info('application/unknown'));
		});

		it('matches only within the given app when _app_or_num is a string', () =>
		{
			assert.equal((env.egw().get_mime_info('text/plain', 'other') as any).menuaction, 'other.view');
		});

		it('returns the Nth match across apps when _app_or_num is a number > 1', () =>
		{
			assert.equal((env.egw().get_mime_info('text/plain', 2) as any).menuaction, 'other.view');
		});
	});

	describe('mime_open()', () =>
	{
		beforeEach(() =>
		{
			env.egw().set_preferences({}, 'filemanager');
			// set_link_registry()'s whole-registry form only takes effect once
			// (see the set_link_registry() tests above), so every app the test
			// needs - including "filemanager-editor", the SEPARATE registry key
			// isCollaborable()/isEditable() consult - must go in this one call.
			env.egw().set_link_registry({
				filemanager: {
					mime: {
						'text/plain': {mime_url: 'path', menuaction: 'filemanager.view'},
						'application/vnd.oasis.opendocument.text': {name: 'edit', mime_url: 'path'}
					}
				},
				'filemanager-editor': {
					mime: {
						'application/vnd.oasis.opendocument.text': {name: 'edit'}
					}
				}
			});
		});

		it('builds a vfs:// url for a string path starting with "/"', () =>
		{
			const data : any = env.egw().mime_open('/some/file.txt', 'text/plain');
			assert.equal(data.path, 'vfs://default/some/file.txt');
			assert.equal(data.menuaction, 'filemanager.view');
		});

		it('builds the path from app2/id2/id when given an object with no explicit path', () =>
		{
			const data : any = env.egw().mime_open({app2: 'infolog', id2: 5, id: 'file.txt'}, 'text/plain');
			assert.equal(data.path, 'vfs://default/apps/infolog/5/file.txt');
		});

		it('uses the object\'s own .type, ignoring the _type argument', () =>
		{
			const data : any = env.egw().mime_open({path: '/x.txt', type: 'text/plain'}, 'application/other');
			assert.equal(data.path, 'vfs://default/x.txt');
		});

		it('KNOWN QUIRK: a string path not starting with "/" leaves data.path completely unset', () =>
		{
			// mime_open()'s `else if (_path[0] !== '/') {}` branch is empty, so
			// `path` stays undefined - and the 'mime_url' case is itself guarded
			// by `if (path) {...}`, so data.path is never even assigned (not
			// just wrong): only the other mime_info attributes (eg. menuaction)
			// make it into the result.
			const data : any = env.egw().mime_open('relative.txt', 'text/plain');
			assert.isUndefined(data.path);
			assert.equal(data.menuaction, 'filemanager.view');
		});

		it('falls back to a plain webdav.php url when there is no matching mime handler', () =>
		{
			const data = env.egw().mime_open('/some/file.unknown', 'application/x-unknown');
			assert.equal(data, '/webdav.php/some/file.unknown');
		});

		it('falls back to download_url for an object path when there is no matching mime handler', () =>
		{
			const data = env.egw().mime_open({path: '/x.bin', download_url: 'https://dl.example/x.bin'}, 'application/x-unknown');
			assert.equal(data, 'https://dl.example/x.bin');
		});

		it('routes to the Collabora editor when the mime is collaborable and the user has collabora rights', () =>
		{
			env.egw().set_user({apps: {collabora: {}}});
			const data : any = env.egw().mime_open('/doc.odt', 'application/vnd.oasis.opendocument.text');
			assert.equal(data.menuaction, 'collabora.EGroupware\\collabora\\Ui.editor');
			assert.equal(data.path, '/doc.odt');
		});

		it('does NOT route to Collabora when the user lacks collabora rights, even for an editable mime', () =>
		{
			env.egw().set_user({apps: {}});
			const data : any = env.egw().mime_open('/doc.odt', 'application/vnd.oasis.opendocument.text');
			assert.notEqual(data.menuaction, 'collabora.EGroupware\\collabora\\Ui.editor');
		});
	});

	describe('isEditable() / isCollaborable()', () =>
	{
		beforeEach(() =>
		{
			env.egw().set_preferences({}, 'filemanager');
			env.egw().set_link_registry({
				'filemanager-editor': {
					mime: {
						'text/plain': {name: 'edit'},
						'application/pdf': {name: 'view'}
					}
				}
			});
		});

		it('is true only for mimes whose editor action is "edit"', () =>
		{
			assert.isTrue(env.egw().isEditable('text/plain'));
			assert.isFalse(env.egw().isEditable('application/pdf'));
		});

		it('is false for an unknown or empty mime', () =>
		{
			assert.isFalse(env.egw().isEditable(''));
			assert.isFalse(env.egw().isEditable('application/unknown'));
		});

		it('isCollaborable() is false without collabora app rights, even for an editable mime', () =>
		{
			env.egw().set_user({apps: {}});
			assert.isFalse(env.egw().isCollaborable('text/plain'));
		});

		it('isCollaborable() is truthy for an editable mime with collabora app rights', () =>
		{
			env.egw().set_user({apps: {collabora: {}}});
			assert.ok(env.egw().isCollaborable('text/plain'));
		});
	});

	describe('link()', () =>
	{
		it('prefixes a relative url with webserverUrl', () =>
		{
			assert.equal(env.egw().link('/index.php'), 'https://example.test/index.php');
		});

		it('does not double-prefix an already-prefixed url', () =>
		{
			assert.equal(env.egw().link('https://example.test/index.php'), 'https://example.test/index.php');
		});

		it('leaves an absolute, non-EGroupware url alone', () =>
		{
			assert.equal(env.egw().link('https://other.example/x'), 'https://other.example/x');
		});

		it('adds object extravars as a url-encoded query string', () =>
		{
			assert.equal(env.egw().link('/index.php', {menuaction: 'a.b.c', id: 5}),
				'https://example.test/index.php?menuaction=a.b.c&id=5');
		});

		it('encodes array-valued extravars as repeated name[]=value pairs (brackets not percent-encoded)', () =>
		{
			assert.equal(env.egw().link('/index.php', {ids: [1, 2]}),
				'https://example.test/index.php?ids[]=1&ids[]=2');
		});

		it('merges an existing query string with string extravars, without double-encoding an already-escaped &', () =>
		{
			assert.equal(env.egw().link('/index.php?existing=1', 'extra=a%26b'),
				'https://example.test/index.php?extra=a%26b&existing=1');
		});

		it('KNOWN QUIRK: alerts about a relative url not starting with a slash, then builds a malformed url missing the separator', () =>
		{
			// `_url = app+'/'+_url` produces "infolog/foo.php" (no LEADING
			// slash), and the webserverUrl prefix step concatenates directly
			// (`this.webserverUrl + _url`) with no separator of its own either -
			// the two combine into "https://example.testinfolog/foo.php".
			const alertStub = sinon.stub(env.window, 'alert');
			(env.window as any).egw_appName = 'infolog';

			const url = env.egw().link('foo.php');

			assert.isTrue(alertStub.calledOnce);
			assert.equal(url, 'https://example.testinfolog/foo.php');
		});
	});

	describe('link_app_list()', () =>
	{
		it('lists only apps the user has run-rights for, sorted case-insensitively by label', () =>
		{
			env.egw().set_user({apps: {zeta: {title: 'zeta'}, Alpha: {title: 'Alpha'}}});
			env.egw().set_link_registry({zeta: {}, Alpha: {}});

			const list = env.egw().link_app_list();
			assert.deepEqual(Object.keys(list), ['Alpha', 'zeta']);
		});

		it('filters by required capability via _must_support', () =>
		{
			env.egw().set_user({apps: {infolog: {title: 'InfoLog'}, mail: {title: 'Mail'}}});
			env.egw().set_link_registry({infolog: {add: {}}, mail: {}});

			assert.deepEqual(Object.keys(env.egw().link_app_list('add')), ['infolog']);
		});
	});

	describe('link_title() / unset_link_title()', () =>
	{
		it('returns null immediately (deprecated usage), without sending a request, when no callback is given at all', () =>
		{
			assert.isNull(env.egw().link_title('infolog', 1));
			assert.equal(env.jsonqCalls.length, 0);
		});

		it('returns null immediately, without warning, when _callback === false', () =>
		{
			assert.isNull(env.egw().link_title('infolog', 1, false));
			assert.equal(env.jsonqCalls.length, 0);
		});

		it('batches multiple pending link_title() calls into a single jsonq request', () =>
		{
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.egw().link_title('infolog', 2, sinon.stub(), null);

			assert.equal(env.jsonqCalls.length, 1, 'both requests must share one jsonq call');

			env.jsonqCalls[0].flush();
			const params = env.jsonqCalls[0].parameters[0];
			assert.deepEqual([...params.infolog].sort(), ['1', '2']);
		});

		it('delivers the response to every callback waiting on its app+id', async() =>
		{
			const cb1 = sinon.stub();
			const cb2 = sinon.stub();
			env.egw().link_title('infolog', 1, cb1, null);
			env.egw().link_title('infolog', 2, cb2, null);
			env.jsonqCalls[0].flush();

			env.jsonqCalls[0].respond({infolog: {1: 'Entry One', 2: 'Entry Two'}});
			await wait();

			assert.isTrue(cb1.calledOnceWith('Entry One'));
			assert.isTrue(cb2.calledOnceWith('Entry Two'));
		});

		it('queues multiple callbacks for the same app+id and calls all of them', async() =>
		{
			const cb1 = sinon.stub();
			const cb2 = sinon.stub();
			env.egw().link_title('infolog', 1, cb1, null);
			env.egw().link_title('infolog', 1, cb2, null);
			env.jsonqCalls[0].flush();

			env.jsonqCalls[0].respond({infolog: {1: 'Shared Title'}});
			await wait();

			assert.isTrue(cb1.calledOnceWith('Shared Title'));
			assert.isTrue(cb2.calledOnceWith('Shared Title'));
		});

		it('starts a new batch once the pending one has already been sent, even before its response arrives', () =>
		{
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.jsonqCalls[0].flush();

			env.egw().link_title('infolog', 3, sinon.stub(), null);

			assert.equal(env.jsonqCalls.length, 2, 'a call arriving after the first batch was sent starts a new one');
		});

		it('serves a cached title synchronously via the callback, and also returns a Promise for the same value', async() =>
		{
			// Not `assert.equal(result, 'Cached Title')`: with a function
			// callback, link_title() ALWAYS returns Promise.resolve(cached)
			// as well as calling the callback synchronously - comparing the
			// raw (cross-realm) Promise directly would fail, and chai hangs
			// trying to render a cross-realm object in the failure message
			// (see EgwJson.test.ts's AbortSignal note for the same class of issue).
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.jsonqCalls[0].flush();
			env.jsonqCalls[0].respond({infolog: {1: 'Cached Title'}});
			await wait();

			const cb = sinon.stub();
			const result = env.egw().link_title('infolog', 1, cb, null);

			assert.isTrue(cb.calledOnceWith('Cached Title'), 'callback fires synchronously even for a cached title');
			assert.equal(await result, 'Cached Title');
			assert.equal(env.jsonqCalls.length, 1, 'must not issue a second request for a cached title');
		});

		it('returns a Promise for a cached title when _callback === true', async() =>
		{
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.jsonqCalls[0].flush();
			env.jsonqCalls[0].respond({infolog: {1: 'Cached Title'}});
			await wait();

			const result : any = env.egw().link_title('infolog', 1, true);
			assert.isFunction(result.then);
			assert.equal(await result, 'Cached Title');
		});

		it('bypasses the cache and re-requests when _force_reload is true', async() =>
		{
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.jsonqCalls[0].flush();
			env.jsonqCalls[0].respond({infolog: {1: 'Old Title'}});
			await wait();

			env.egw().link_title('infolog', 1, sinon.stub(), null, true);

			assert.equal(env.jsonqCalls.length, 2, '_force_reload must issue a new request even though cached');
		});

		it('unset_link_title removes a single cached id, leaving other ids in the same app intact', async() =>
		{
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.egw().link_title('infolog', 2, sinon.stub(), null);
			env.jsonqCalls[0].flush();
			env.jsonqCalls[0].respond({infolog: {1: 'One', 2: 'Two'}});
			await wait();

			env.egw().unset_link_title('infolog', 1);

			env.egw().link_title('infolog', 1, sinon.stub(), null);
			assert.equal(env.jsonqCalls.length, 2, 'the unset id must be re-requested');

			const cb2 = sinon.stub();
			env.egw().link_title('infolog', 2, cb2, null);
			assert.isTrue(cb2.calledOnceWith('Two'), 'the other id must still be cached');
		});

		it('unset_link_title with no id clears the whole app\'s cache', async() =>
		{
			env.egw().link_title('infolog', 1, sinon.stub(), null);
			env.jsonqCalls[0].flush();
			env.jsonqCalls[0].respond({infolog: {1: 'One'}});
			await wait();

			env.egw().unset_link_title('infolog');

			env.egw().link_title('infolog', 1, sinon.stub(), null);
			assert.equal(env.jsonqCalls.length, 2);
		});
	});
});
