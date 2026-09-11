/**
 * Tests for egw_preferences.js ("preferences" module) - MODULE_GLOBAL.
 *
 * See EgwLinksPrefsUserHarness for how the real, unmodified file is
 * loaded, and in particular how the fake json()/sendRequest() simulates
 * the server's "apply" response plugin (which replaces prefs[_app] with
 * real data BEFORE sendRequest()'s own promise settles) - understanding
 * that ordering is the key to preference()'s async loading contract.
 *
 * NOT covered (documented residual risk): the true synchronous XHR path
 * (preference() called with no _callback at all blocks for real in
 * production; this harness can't simulate blocking I/O) and
 * show_preferences()'s 'cats' branch (custom per-app url-param shape,
 * narrower and lower value than 'prefs'/'acl').
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwLinksPrefsUserEnv, EgwLinksPrefsUserEnv} from "./EgwLinksPrefsUserHarness";

const AJAX_GET_PREFERENCE = 'EGroupware\\Api\\Framework::ajax_get_preference';

describe('egw_preferences.js (preferences)', () =>
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
		assert.isFunction(instance.preference);
		assert.isFunction(instance.set_preference);
		assert.isFunction(instance.set_preferences);
		assert.isFunction(instance.grants);
		assert.isFunction(instance.reload_preferences);
	});

	describe('sanitizeApp() (private, exercised via preference()/set_preference())', () =>
	{
		it('defaults a missing app to "common" when reading', () =>
		{
			// set_preferences(data) with NO app replaces the whole multi-app
			// prefs structure, not "common"'s slot - use the explicit app form
			// to set up the fixture, and test the read-side default separately.
			env.egw().set_preferences({textsize: 14}, 'common');
			assert.equal(env.egw().preference('textsize'), 14);
		});

		it('truncates app names longer than 16 characters', () =>
		{
			const longApp = 'a'.repeat(20);
			env.egw().set_preferences({y: 1}, longApp);
			assert.deepEqual(env.egw().preference('*', longApp.substring(0, 16)), {y: 1});
		});

		it('aliases any "addressbook-*" app to "infolog"', () =>
		{
			env.egw().set_preferences({tab: 'x'}, 'addressbook-view');
			assert.deepEqual(env.egw().preference('*', 'infolog'), {tab: 'x'});
			// and the reverse direction, to be sure it is the SAME slot, not a coincidence
			env.egw().set_preference('addressbook-list', 'tab', 'y');
			assert.equal(env.egw().preference('tab', 'infolog'), 'y');
		});
	});

	describe('preference() - already loaded', () =>
	{
		it('returns a clone for "*", not the live object', () =>
		{
			env.egw().set_preferences({a: 1}, 'myapp');
			const p1 : any = env.egw().preference('*', 'myapp');
			p1.a = 999;
			assert.equal((env.egw().preference('*', 'myapp') as any).a, 1);
		});

		it('returns a clone for an object-typed single preference', () =>
		{
			env.egw().set_preferences({nested: {x: 1}}, 'myapp');
			const v1 : any = env.egw().preference('nested', 'myapp');
			v1.x = 999;
			assert.equal((env.egw().preference('nested', 'myapp') as any).x, 1);
		});

		it('returns primitive values as-is (no unnecessary cloning)', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'myapp');
			assert.equal(env.egw().preference('dateformat', 'myapp'), 'Y-m-d');
		});
	});

	describe('preference() - loading (not yet cached)', () =>
	{
		it('returns undefined immediately, without sending any request, when _callback === false', () =>
		{
			const result = env.egw().preference('dateformat', 'newapp', false);
			assert.isUndefined(result);
			assert.equal(env.jsonCalls.length, 0);
		});

		it('sends a request for the whole app and returns a Promise when _callback === true', async() =>
		{
			const promise = env.egw().preference('dateformat', 'newapp', true);
			// Not assert.instanceOf(promise, Promise): the promise was
			// constructed inside the iframe's own realm (see
			// EgwLinksPrefsUserHarness), and chai's instanceOf hangs
			// inspecting a cross-realm object for its error message.
			assert.isFunction(promise.then);
			assert.equal(env.jsonCalls.length, 1);
			assert.equal(env.jsonCalls[0].menuaction, AJAX_GET_PREFERENCE);
			assert.deepEqual(env.jsonCalls[0].parameters, ['newapp']);

			env.jsonCalls[0].respond({dateformat: 'Y-m-d', timeformat: 'H:i'});

			assert.equal(await promise, 'Y-m-d');
		});

		it('returns false immediately for a function callback, then calls it back once the (simulated) server responds', () =>
		{
			const cb = sinon.stub();
			const result = env.egw().preference('dateformat', 'newapp', cb, null);

			assert.isFalse(result);
			assert.isFalse(cb.called);

			env.jsonCalls[0].respond({dateformat: 'Y-m-d'});

			assert.isTrue(cb.calledOnceWith({dateformat: 'Y-m-d'}));
		});

		it('does not send a second request while the first is still pending, for any preference name in the same app', () =>
		{
			env.egw().preference('dateformat', 'newapp', true);
			env.egw().preference('timeformat', 'newapp', true);

			assert.equal(env.jsonCalls.length, 1, 'both must share the one in-flight load');
		});

		it('a pending second call with _callback === false returns undefined without disturbing the in-flight request', () =>
		{
			const first = env.egw().preference('dateformat', 'newapp', true);
			const result = env.egw().preference('dateformat', 'newapp', false);

			assert.isUndefined(result);
			assert.equal(env.jsonCalls.length, 1);

			env.jsonCalls[0].respond({dateformat: 'Y-m-d'});
			return first;
		});

		it('a pending second call with a function callback returns false without sending another request', () =>
		{
			env.egw().preference('dateformat', 'newapp', true);
			const cb = sinon.stub();
			const result = env.egw().preference('timeformat', 'newapp', cb, null);

			assert.isFalse(result);
			assert.equal(env.jsonCalls.length, 1, 'the second call must reuse the pending load, not start its own');
		});
	});

	describe('set_preference()', () =>
	{
		it('skips the network call when the value is unchanged', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');
			env.egw().set_preference('common', 'dateformat', 'Y-m-d');
			assert.equal(env.jsonqCalls.length, 0);
		});

		it('sends the change and updates the local cache when the value differs', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');
			env.egw().set_preference('common', 'dateformat', 'd.m.Y');
			assert.equal(env.jsonqCalls.length, 1);
			assert.deepEqual(env.jsonqCalls[0].parameters, ['common', 'dateformat', 'd.m.Y']);
			assert.equal(env.egw().preference('dateformat', 'common'), 'd.m.Y');
		});

		it('compares object values by content (JSON serialization), not by reference', () =>
		{
			env.egw().set_preferences({filters: {a: 1}}, 'app');

			env.egw().set_preference('app', 'filters', {a: 1}); // same content, different object identity
			assert.equal(env.jsonqCalls.length, 0, 'must recognize this as unchanged');

			env.egw().set_preference('app', 'filters', {a: 2});
			assert.equal(env.jsonqCalls.length, 1, 'must recognize this as a real change');
		});

		it('null/undefined/"" deletes the cached key instead of storing it', () =>
		{
			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');

			env.egw().set_preference('common', 'dateformat', null);
			assert.isUndefined(env.egw().preference('dateformat', 'common'));

			env.egw().set_preferences({dateformat: 'Y-m-d'}, 'common');
			env.egw().set_preference('common', 'dateformat', '');
			assert.isUndefined(env.egw().preference('dateformat', 'common'));
		});

		it('does not touch the local cache for an app whose prefs were never loaded (would block loading it)', () =>
		{
			env.egw().set_preference('neverloaded', 'x', 'y');
			// still "not loaded" afterwards - a subsequent read must go through the normal loading path
			assert.equal(env.egw().preference('x', 'neverloaded', false), undefined);
		});
	});

	describe('grants()', () =>
	{
		it('round-trips per-app grants and returns a clone', () =>
		{
			env.egw().set_grants({read: true}, 'infolog');
			const g1 : any = env.egw().grants('infolog');
			g1.read = false;
			assert.isTrue((env.egw().grants('infolog') as any).read);
		});

		it('set_grants() with no app replaces the whole grants map at once', () =>
		{
			env.egw().set_grants({read: true}, 'infolog');
			env.egw().set_grants({infolog: {read: false}, mail: {read: true}});

			assert.deepEqual(env.egw().grants('infolog'), {read: false});
			assert.deepEqual(env.egw().grants('mail'), {read: true});
		});
	});

	describe('reload_preferences()', () =>
	{
		it('does nothing if the app prefs were never loaded', () =>
		{
			env.egw().reload_preferences('neverloaded', 0);
			assert.equal(env.jsonCalls.length, 0);
		});

		it('always reloads for _account_id 0, once loaded', () =>
		{
			env.egw().set_preferences({x: 1}, 'myapp');
			env.egw().reload_preferences('myapp', 0);
			assert.equal(env.jsonCalls.length, 1);
			assert.equal(env.jsonCalls[0].menuaction, AJAX_GET_PREFERENCE);
			assert.deepEqual(env.jsonCalls[0].parameters, ['myapp']);
		});

		it('skips reload for a negative (group) _account_id the user is not a member of', () =>
		{
			env.egw().set_user({memberships: [-5]});
			env.egw().set_preferences({x: 1}, 'myapp');
			env.egw().reload_preferences('myapp', -7);
			assert.equal(env.jsonCalls.length, 0);
		});

		it('reloads for a negative (group) _account_id the user IS a member of', () =>
		{
			env.egw().set_user({memberships: [-7]});
			env.egw().set_preferences({x: 1}, 'myapp');
			env.egw().reload_preferences('myapp', -7);
			assert.equal(env.jsonCalls.length, 1);
		});

		it('coerces a string _account_id to a number', () =>
		{
			env.egw().set_user({memberships: [-7]});
			env.egw().set_preferences({x: 1}, 'myapp');
			env.egw().reload_preferences('myapp', '-7' as any);
			assert.equal(env.jsonCalls.length, 1);
		});
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

		it('excludes multiple comma-separated mimes', () =>
		{
			env.egw().set_preferences({collab_excluded_mimes: 'application/pdf,text/plain'}, 'filemanager');
			const fe : any = env.egw().file_editor_prefered_mimes(undefined as any);
			assert.notProperty(fe.mime, 'application/pdf');
			assert.notProperty(fe.mime, 'text/plain');
		});

		it('also excludes the current mime when document_doubleclick_action is "download"', () =>
		{
			env.egw().set_preferences({document_doubleclick_action: 'download'}, 'filemanager');
			const fe : any = env.egw().file_editor_prefered_mimes('text/plain');
			assert.notProperty(fe.mime, 'text/plain');
			assert.property(fe.mime, 'application/pdf', 'only the current mime is added to the exclusion list');
		});

		it('returns null when the registry has no filemanager-editor mime map', () =>
		{
			env.egw().set_link_registry({'filemanager-editor': {}}, 'filemanager-editor');
			assert.isNull(env.egw().file_editor_prefered_mimes(undefined as any));
		});
	});

	describe('show_preferences()', () =>
	{
		it('warns instead of opening anything when the current app is not in the allowed array (non-prefs/acl type)', () =>
		{
			// 'prefs' and 'acl' specifically bypass this array check (every app
			// links to the common prefs/acl UI even if unlisted - see the next
			// two tests), so a type that isn't either of those is needed to
			// actually exercise the warning branch.
			const messageStub = sinon.stub(env.window as any, 'egw_message');
			env.currentAppName = 'unsupported_app';

			env.egw().show_preferences('cats', ['infolog', 'mail']);

			assert.isTrue(messageStub.calledOnce);
			assert.equal(env.openLinkCalls.length, 0);
			assert.equal(env.linkHandlerCalls.length, 0);
		});

		it('warns for the object-apps form too, when the current app key is missing or falsy', () =>
		{
			const messageStub = sinon.stub(env.window as any, 'egw_message');
			env.currentAppName = 'mail';

			env.egw().show_preferences('acl', {infolog: true, mail: false});

			assert.isTrue(messageStub.calledOnce);
		});

		it('"prefs"/"acl" open even for an app not listed in the array (they fall back to the common UI)', () =>
		{
			const messageStub = sinon.stub(env.window as any, 'egw_message');
			env.currentAppName = 'unsupported_app';

			env.egw().show_preferences('prefs', ['infolog', 'mail']);

			assert.isFalse(messageStub.called);
			assert.equal(env.openLinkCalls.length, 1);
			assert.notInclude(env.openLinkCalls[0][0], 'appname=', 'no appname param when the app isn\'t in the list');
		});

		it('opens the app\'s own "prefs" page when the app array includes the current app', () =>
		{
			env.currentAppName = 'infolog';

			env.egw().show_preferences('prefs', ['infolog', 'mail']);

			assert.equal(env.openLinkCalls.length, 1);
			const [url, target, popup] = env.openLinkCalls[0];
			assert.include(url, 'menuaction=preferences.preferences_settings.index');
			assert.include(url, 'appname=infolog');
			assert.equal(target, '_blank');
			assert.equal(popup, '1200x600');
		});

		it('"acl" is supported for every app when apps is an object, even without an explicit true flag entry present', () =>
		{
			env.currentAppName = 'infolog';

			env.egw().show_preferences('acl', {infolog: true});

			assert.equal(env.openLinkCalls.length, 1);
			assert.include(env.openLinkCalls[0][0], 'menuaction=preferences.preferences_acl.index');
		});
	});
});
