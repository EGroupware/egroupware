/**
 * Tests for the remaining MODULE_GLOBAL modules: preferences, user, config,
 * lang, images, links. Lower priority per the test plan (mostly plain
 * getter/setter state), but each has at least one genuinely interesting
 * piece of logic worth pinning down. See EgwMiscHarness for what's shared
 * and stubbed (a fake jsonq/request/json network layer - the real one has
 * its own thorough coverage in EgwJson.test.ts).
 *
 * NOT covered (documented residual risk, out of scope for this pass):
 * preferences.show_preferences() and the async (server-round-trip) branch
 * of preference()/grants(); user.accounts() and set_account_data() (widget-
 * heavy); config's install_mailto_handler() (real dialog/localStorage
 * side effects, gated on a URL query param); lang's langRequire()/
 * langRequireApp() (dynamic import() side effects); links.link_quick_add()
 * (a full et2-select web component) and link_title()'s server round-trip.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwMiscEnv, EgwMiscEnv} from "./EgwMiscHarness";

describe('egw_preferences.js / egw_user.js / egw_config.js / egw_lang.js / egw_images.js / egw_links.js', () =>
{
	let env : EgwMiscEnv;

	beforeEach(async() =>
	{
		env = await createEgwMiscEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods from every module', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.preference);
		assert.isFunction(instance.user);
		assert.isFunction(instance.config);
		assert.isFunction(instance.lang);
		assert.isFunction(instance.image);
		assert.isFunction(instance.link);
	});

	describe('preferences', () =>
	{
		it('sanitizes app names: truncates to 16 chars and aliases addressbook-* to infolog', () =>
		{
			env.egw().set_preferences({tab: 'x'}, 'addressbook-view');
			assert.deepEqual(env.egw().preference('*', 'infolog'), {tab: 'x'});

			const longApp = 'a'.repeat(20);
			env.egw().set_preferences({y: 1}, longApp);
			assert.deepEqual(env.egw().preference('*', longApp.substring(0, 16)), {y: 1});
		});

		it('preference("*", app) returns a clone, not the live object', () =>
		{
			env.egw().set_preferences({a: 1}, 'myapp');
			const p1 : any = env.egw().preference('*', 'myapp');
			p1.a = 999;
			assert.equal((env.egw().preference('*', 'myapp') as any).a, 1);
		});

		it('preference(name, app) returns a clone for object-typed values', () =>
		{
			env.egw().set_preferences({nested: {x: 1}}, 'myapp');
			const v1 : any = env.egw().preference('nested', 'myapp');
			v1.x = 999;
			assert.equal((env.egw().preference('nested', 'myapp') as any).x, 1);
		});

		it('set_preference skips the network call when the value is unchanged', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');
			env.egw().set_preference('common', 'dateformat', 'Y-m-d');
			assert.equal(env.jsonqCalls.length, 0);
		});

		it('set_preference sends the change and updates the local cache', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');
			env.egw().set_preference('common', 'dateformat', 'd.m.Y');
			assert.equal(env.jsonqCalls.length, 1);
			assert.deepEqual(env.jsonqCalls[0].parameters, ['common', 'dateformat', 'd.m.Y']);
			assert.equal(env.egw().preference('dateformat', 'common'), 'd.m.Y');
		});

		it('set_preference with null/undefined/"" deletes the cached key', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');
			env.egw().set_preference('common', 'dateformat', null);
			assert.isUndefined(env.egw().preference('dateformat', 'common'));
		});

		it('grants() round-trips and returns a clone', () =>
		{
			env.egw().set_grants({read: true}, 'infolog');
			const g1 : any = env.egw().grants('infolog');
			g1.read = false;
			assert.isTrue((env.egw().grants('infolog') as any).read);
		});

		describe('file_editor_prefered_mimes()', () =>
		{
			beforeEach(() =>
			{
				env.egw().set_link_registry({
					'filemanager-editor': {
						mime: {
							'text/plain': {name: 'edit'},
							'application/pdf': {name: 'view'}
						}
					}
				});
				// pre-load "filemanager" prefs so preference() takes its
				// synchronous, already-loaded path rather than the
				// server-round-trip branch (out of scope for this harness's
				// minimal json() stub - see EgwMiscHarness).
				env.egw().set_preferences({}, 'filemanager');
			});

			it('returns the filemanager-editor mime map unfiltered by default', () =>
			{
				const fe : any = env.egw().file_editor_prefered_mimes(undefined as any);
				assert.property(fe.mime, 'text/plain');
				assert.property(fe.mime, 'application/pdf');
			});

			it('excludes mimes listed in the collab_excluded_mimes preference', () =>
			{
				env.egw().set_preferences({collab_excluded_mimes: 'application/pdf'}, 'filemanager');
				const fe : any = env.egw().file_editor_prefered_mimes(undefined as any);
				assert.notProperty(fe.mime, 'application/pdf');
				assert.property(fe.mime, 'text/plain');
			});

			it('also excludes the current mime when document_doubleclick_action is "download"', () =>
			{
				env.egw().set_preferences({document_doubleclick_action: 'download'}, 'filemanager');
				const fe : any = env.egw().file_editor_prefered_mimes('text/plain');
				assert.notProperty(fe.mime, 'text/plain');
			});
		});
	});

	describe('user', () =>
	{
		it('set_user()/user() round-trip, and app()/appByTitle() look up run-rights', () =>
		{
			env.egw().set_user({account_id: 5, apps: {infolog: {title: 'InfoLog'}}});
			assert.equal(env.egw().user('account_id'), 5);
			assert.deepEqual(env.egw().app('infolog'), {title: 'InfoLog'});
			assert.deepEqual(env.egw().appByTitle('InfoLog'), {title: 'InfoLog'});
		});

		it('accountData() resolves the current user directly from userData, without a network call', async() =>
		{
			env.egw().set_user({account_id: 5, account_email: 'me@example.com'});
			const data = await env.egw().accountData(5, 'account_email', false, undefined, undefined);
			assert.deepEqual(data, {5: 'me@example.com'});
			assert.equal(env.requestCalls.length, 0);
		});

		it('set_account_cache pre-fills the cache, and invalidate_account clears it', async() =>
		{
			env.egw().set_account_cache({7: 'seven@example.com'}, 'account_email');
			let data : any = await env.egw().accountData(7, 'account_email', false, undefined, undefined);
			assert.deepEqual(data, {7: 'seven@example.com'});
			assert.equal(env.requestCalls.length, 0);

			env.egw().invalidate_account(7);
			const promise = env.egw().accountData(7, 'account_email', false, undefined, undefined);
			assert.equal(env.requestCalls.length, 1, 'after invalidation, the cache miss must ask the server');
			env.requestCalls[0].resolve({7: 'fresh@example.com'});
			data = await promise;
			assert.deepEqual(data, {7: 'fresh@example.com'});
		});

		it('prompts() filters both top-level prompts and their children by app', () =>
		{
			env.egw().set_prompts([
				{id: 'a', label: 'A'},
				{id: 'b', label: 'B', apps: ['mail']},
				{
					id: 'c', label: 'C', apps: ['infolog'], children: [
						{id: 'c1', label: 'C1', apps: ['infolog']},
						{id: 'c2', label: 'C2', apps: ['mail']}
					]
				}
			]);

			const forMail = env.egw().prompts('mail');
			assert.deepEqual(forMail.map((p : any) => p.id), ['a', 'b']);

			const forInfolog = env.egw().prompts('infolog');
			const c = forInfolog.find((p : any) => p.id === 'c');
			assert.deepEqual(c.children.map((ch : any) => ch.id), ['c1']);
		});
	});

	describe('config', () =>
	{
		it('round-trips config values, keyed by app (default "phpgwapi")', () =>
		{
			env.egw().set_configs({phpgwapi: {max_lang_time: 123}});
			assert.equal(env.egw().config('max_lang_time'), 123);
		});

		it('returns null for an unknown app, and undefined for an unknown key in a known app', () =>
		{
			assert.isNull(env.egw().config('whatever', 'nosuchapp'));
			env.egw().set_configs({someapp: {}});
			assert.isUndefined(env.egw().config('missing_key', 'someapp'));
		});
	});

	describe('lang', () =>
	{
		it('falls back to the original message when no translation is found', () =>
		{
			assert.equal(env.egw().lang('Hello'), 'Hello');
		});

		it('matches a registered translation case-insensitively', () =>
		{
			env.egw().set_lang_arr('common', {'hello': 'Hallo'});
			assert.equal(env.egw().lang('Hello'), 'Hallo');
		});

		it('replaces %1 with the second argument when exactly one extra arg is given', () =>
		{
			env.egw().set_lang_arr('common', {'welcome %1': 'Willkommen %1'});
			assert.equal(env.egw().lang('welcome %1', 'Ralf'), 'Willkommen Ralf');
		});

		it('replaces multiple %N placeholders safely, even if an argument itself contains "%2"', () =>
		{
			env.egw().set_lang_arr('common', {'%1 of %2': '%1 von %2'});
			assert.equal(env.egw().lang('%1 of %2', 'a%2b', 'c'), 'a%2b von c');
		});

		it('returns an empty string for null/undefined messages', () =>
		{
			assert.equal(env.egw().lang(null as any), '');
			assert.equal(env.egw().lang(undefined as any), '');
		});

		it('returns non-string, truthy messages unchanged', () =>
		{
			const obj : any = {foo: 'bar'};
			assert.strictEqual(env.egw().lang(obj), obj);
		});
	});

	describe('images', () =>
	{
		it('returns null before set_images() has ever been called', () =>
		{
			assert.isNull(env.egw().image('anything'));
		});

		it('prefers a vfs-mapped image over everything else', () =>
		{
			env.egw().set_images({vfs: {my_vfs_icon: '/vfs/path/icon.png'}});
			assert.equal(env.egw().image('my_vfs_icon'), 'https://example.test/vfs/path/icon.png');
		});

		it('resolves via the global override map for a non-"navbar" name', () =>
		{
			// webserverUrl and the stored bootstrap path are simply
			// concatenated with no separator - real server-supplied image
			// maps store paths with a leading "/", which the test fixture
			// must match too.
			env.egw().set_images({
				global: {'infolog/some_icon': 'shared/icon'},
				bootstrap: {'shared/icon': '/bootstrap/icon.svg'}
			});
			assert.equal(env.egw().image('some_icon', 'infolog'), 'https://example.test/bootstrap/icon.svg');
		});

		it('KNOWN QUIRK: "navbar" images consult the global override map only when the app is exactly "api"', () =>
		{
			// `images.global !== undefined && (_name !== 'navbar' || _app === 'api')`
			// - the SAME global override entry resolves for app "api" but is
			// silently skipped for every other app.
			env.egw().set_images({
				global: {navbar: 'shared/navbar'},
				bootstrap: {'shared/navbar': '/bootstrap/navbar.svg'}
			});

			assert.equal(env.egw().image('navbar', 'api'), 'https://example.test/bootstrap/navbar.svg');
			assert.isNull(env.egw().image('navbar', 'infolog'), 'the identical override is skipped for any other app');
		});

		it('falls back to a plain app-specific image when nothing else matches', () =>
		{
			env.egw().set_images({infolog: {edit: '/infolog/edit.png'}});
			assert.equal(env.egw().image('edit', 'infolog'), 'https://example.test/infolog/edit.png');
		});

		it('strips a known image extension and retries when the exact name is not found', () =>
		{
			env.egw().set_images({infolog: {edit: '/infolog/edit.png'}});
			assert.equal(env.egw().image('edit.png', 'infolog'), 'https://example.test/infolog/edit.png');
		});

		it('returns null (and does not throw) when nothing at all matches', () =>
		{
			env.egw().set_images({});
			assert.isNull(env.egw().image('totally-unknown-image', 'nosuchapp'));
		});

		it('image_element() creates an <img> with src/alt set', () =>
		{
			const img = env.egw().image_element('http://x/y.png', 'alt text');
			assert.equal(img.tagName, 'IMG');
			assert.include(img.src, 'y.png');
			assert.equal(img.alt, 'alt text');
		});
	});

	describe('links', () =>
	{
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
				const url = env.egw().link('/index.php', {menuaction: 'a.b.c', id: 5});
				assert.equal(url, 'https://example.test/index.php?menuaction=a.b.c&id=5');
			});

			it('encodes array-valued extravars as repeated name[]=value pairs (brackets not percent-encoded)', () =>
			{
				const url = env.egw().link('/index.php', {ids: [1, 2]});
				assert.equal(url, 'https://example.test/index.php?ids[]=1&ids[]=2');
			});

			it('merges an existing query string with string extravars, without double-encoding an already-escaped &', () =>
			{
				const url = env.egw().link('/index.php?existing=1', 'extra=a%26b');
				assert.equal(url, 'https://example.test/index.php?extra=a%26b&existing=1');
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

			it('returns false for an app not in the registry', () =>
			{
				env.egw().set_link_registry({infolog: {view: {menuaction: 'x'}}});
				assert.isFalse(env.egw().link_get_registry('nosuchapp'));
			});

			it('returns a clone of the whole app registry when no name is given', () =>
			{
				env.egw().set_link_registry({infolog: {view: {menuaction: 'x'}}});
				const reg : any = env.egw().link_get_registry('infolog');
				reg.view.menuaction = 'tampered';
				assert.equal((env.egw().link_get_registry('infolog', 'view') as any).menuaction, 'x');
			});

			it('defaults a missing "name" entry to the app-name itself', () =>
			{
				env.egw().set_link_registry({infolog: {}});
				assert.equal(env.egw().link_get_registry('infolog', 'name'), 'infolog');
			});
		});

		describe('get_mime_info()', () =>
		{
			beforeEach(() =>
			{
				env.egw().set_link_registry({
					filemanager: {mime: {'text/plain': {menuaction: 'filemanager.view'}}},
					mail: {mime: {'/^message\\//': {menuaction: 'mail.view'}}}
				});
			});

			it('prefers an exact mime match over a wildcard', () =>
			{
				const info : any = env.egw().get_mime_info('text/plain');
				assert.equal(info.menuaction, 'filemanager.view');
			});

			it('falls back to a wildcard (regex) match', () =>
			{
				const info : any = env.egw().get_mime_info('message/rfc822');
				assert.equal(info.menuaction, 'mail.view');
			});

			it('returns null when nothing matches', () =>
			{
				assert.isNull(env.egw().get_mime_info('application/unknown'));
			});
		});

		describe('mime_open()', () =>
		{
			beforeEach(() =>
			{
				env.egw().set_link_registry({
					filemanager: {mime: {'text/plain': {mime_url: 'path', menuaction: 'filemanager.view'}}}
				});
			});

			it('builds a vfs:// url for a path-based mime handler', () =>
			{
				const data : any = env.egw().mime_open('/some/file.txt', 'text/plain');
				assert.equal(data.path, 'vfs://default/some/file.txt');
				assert.equal(data.menuaction, 'filemanager.view');
			});

			it('falls back to a plain webdav.php url when there is no matching mime handler', () =>
			{
				const data = env.egw().mime_open('/some/file.unknown', 'application/x-unknown');
				assert.equal(data, '/webdav.php/some/file.unknown');
			});
		});

		describe('link_app_list()', () =>
		{
			it('lists only apps the user has run-rights for, sorted by label', () =>
			{
				env.egw().set_user({apps: {infolog: {title: 'InfoLog'}, mail: {title: 'Mail'}}});
				env.egw().set_link_registry({
					infolog: {},
					mail: {},
					filemanager: {} // no run-rights -> excluded
				});

				const list = env.egw().link_app_list();
				assert.deepEqual(Object.keys(list), ['infolog', 'mail']);
			});

			it('filters by required capability via _must_support', () =>
			{
				env.egw().set_user({apps: {infolog: {title: 'InfoLog'}, mail: {title: 'Mail'}}});
				env.egw().set_link_registry({
					infolog: {add: {}},
					mail: {}
				});

				const list = env.egw().link_app_list('add');
				assert.deepEqual(Object.keys(list), ['infolog']);
			});
		});
	});
});
