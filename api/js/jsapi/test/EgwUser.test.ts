/**
 * Tests for egw_user.js ("user" module) - MODULE_GLOBAL.
 *
 * See EgwLinksPrefsUserHarness for how egw.request() (used by accounts()/
 * accountData()) is stubbed with a controllable Promise.
 *
 * NOT covered (documented residual risk): set_account_data()'s "{template}"
 * placeholder-resolution recursive branch (only the plain-field branch is
 * tested) and prompts()'s interaction with real aiassistant UI code.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwLinksPrefsUserEnv, EgwLinksPrefsUserEnv} from "./EgwLinksPrefsUserHarness";

const AJAX_USER_LIST = 'EGroupware\\Api\\Framework::ajax_user_list';
const AJAX_ACCOUNT_DATA = 'EGroupware\\Api\\Framework::ajax_account_data';

function wait(ms : number = 0) : Promise<void>
{
	return new Promise(resolve => setTimeout(resolve, ms));
}

describe('egw_user.js (user)', () =>
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
		assert.isFunction(instance.set_user);
		assert.isFunction(instance.user);
		assert.isFunction(instance.app);
		assert.isFunction(instance.accounts);
		assert.isFunction(instance.accountData);
	});

	describe('set_user() / user() / app() / appByTitle()', () =>
	{
		it('round-trips arbitrary user fields', () =>
		{
			env.egw().set_user({account_id: 5, account_lid: 'ralf'});
			assert.equal(env.egw().user('account_id'), 5);
			assert.equal(env.egw().user('account_lid'), 'ralf');
		});

		it('app() returns the whole app-data object, or a single named attribute', () =>
		{
			env.egw().set_user({apps: {infolog: {title: 'InfoLog', enabled: 1}}});
			assert.deepEqual(env.egw().app('infolog'), {title: 'InfoLog', enabled: 1});
			assert.equal(env.egw().app('infolog', 'title'), 'InfoLog');
		});

		it('app() for an app the user has no rights to returns undefined', () =>
		{
			env.egw().set_user({apps: {}});
			assert.isUndefined(env.egw().app('nosuchapp'));
		});

		it('appByTitle() finds an app by its translated title, or returns undefined', () =>
		{
			env.egw().set_user({apps: {infolog: {title: 'InfoLog'}}});
			assert.deepEqual(env.egw().appByTitle('InfoLog'), {title: 'InfoLog'});
			assert.equal(env.egw().appByTitle('InfoLog', 'title'), 'InfoLog');
			assert.isUndefined(env.egw().appByTitle('No Such Title'));
		});
	});

	describe('accounts()', () =>
	{
		function respondUserList(data : any)
		{
			env.requestCalls[env.requestCalls.length - 1].resolve(data);
		}

		it('requests the user list once and resolves with "accounts" by default', async() =>
		{
			const promise = env.egw().accounts();
			assert.equal(env.requestCalls.length, 1);
			assert.equal(env.requestCalls[0].menuaction, AJAX_USER_LIST);

			respondUserList({
				accounts: {1: {value: 1, label: 'Admin'}},
				groups: {7: {value: -7, label: 'Group'}},
				owngroups: {}
			});

			assert.deepEqual(await promise, [{value: '1', label: 'Admin'}]);
		});

		it('merges accounts and groups for type "both"', async() =>
		{
			const promise = env.egw().accounts('both');
			respondUserList({
				accounts: {1: {value: 1, label: 'Admin'}},
				groups: {7: {value: -7, label: 'Group'}},
				owngroups: {}
			});

			assert.deepEqual(await promise, [{value: '1', label: 'Admin'}, {value: '-7', label: 'Group'}]);
		});

		it('concurrent calls while the initial load is pending all share the one request', async() =>
		{
			const p1 = env.egw().accounts('accounts');
			const p2 = env.egw().accounts('groups');
			assert.equal(env.requestCalls.length, 1, 'only one ajax_user_list request must be sent');

			respondUserList({
				accounts: {1: {value: 1, label: 'Admin'}},
				groups: {7: {value: -7, label: 'Group'}},
				owngroups: {}
			});

			assert.deepEqual(await p1, [{value: '1', label: 'Admin'}]);
			assert.deepEqual(await p2, [{value: '-7', label: 'Group'}]);
		});

		it('a later call, once cached, resolves without another request', async() =>
		{
			const first = env.egw().accounts();
			respondUserList({accounts: {1: {value: 1, label: 'Admin'}}, groups: {}, owngroups: {}});
			await first;

			await env.egw().accounts();
			assert.equal(env.requestCalls.length, 1, 'no second request once cached');
		});
	});

	describe('accountData()', () =>
	{
		it('resolves the current user directly from userData, without a network call', async() =>
		{
			env.egw().set_user({account_id: 5, account_email: 'me@example.com'});
			const data = await env.egw().accountData(5, 'account_email', false, undefined, undefined);
			assert.deepEqual(data, {5: 'me@example.com'});
			assert.equal(env.requestCalls.length, 0);
		});

		it('resolves cached ids locally, requesting only the ones missing from the cache', async() =>
		{
			env.egw().set_account_cache({5: 'five@example.com'}, 'account_email');

			const promise = env.egw().accountData([5, 9], 'account_email', false, undefined, undefined);

			assert.equal(env.requestCalls.length, 1);
			assert.deepEqual(env.requestCalls[0].parameters[0], [9], 'only the uncached id must be requested');

			env.requestCalls[0].resolve({9: 'nine@example.com'});

			assert.deepEqual(await promise, {5: 'five@example.com', 9: 'nine@example.com'});
		});

		it('a second call for the same pending id/field reuses the in-flight promise instead of re-requesting', async() =>
		{
			const p1 = env.egw().accountData(7, 'account_email', false, undefined, undefined);
			assert.equal(env.requestCalls.length, 1);

			const p2 = env.egw().accountData(7, 'account_email', false, undefined, undefined);
			assert.equal(env.requestCalls.length, 1, 'must not send a second request for the same pending id/field');

			env.requestCalls[0].resolve({7: 'seven@example.com'});

			assert.deepEqual(await p1, {7: 'seven@example.com'});
			assert.deepEqual(await p2, {7: 'seven@example.com'});
		});

		it('caches the full response for a single resolved group, for later local re-resolution', async() =>
		{
			const promise = env.egw().accountData(-7, 'account_email', true, undefined, undefined);
			env.requestCalls[0].resolve({10: 'ten@example.com', 11: 'eleven@example.com'});
			await promise;

			const promise2 = env.egw().accountData(-7, 'account_email', true, undefined, undefined);
			assert.equal(env.requestCalls.length, 1, 'must reuse the cached group resolution, not re-request');
			assert.deepEqual(await promise2, {10: 'ten@example.com', 11: 'eleven@example.com'});
		});

		it('supports the deprecated _callback/_context parameters', async() =>
		{
			const cb = sinon.stub();
			const ctx = {};
			const promise = env.egw().accountData(7, 'account_email', false, cb, ctx);
			env.requestCalls[0].resolve({7: 'seven@example.com'});
			await promise;

			assert.isTrue(cb.calledOnceWith({7: 'seven@example.com'}));
			assert.strictEqual(cb.firstCall.thisValue, ctx);
		});

		it('defaults _field to "account_email" when omitted', () =>
		{
			env.egw().accountData(7, undefined as any, false, undefined, undefined);
			assert.equal(env.requestCalls[0].parameters[1], 'account_email');
		});
	});

	describe('set_account_cache() / invalidate_account()', () =>
	{
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

		async function seedAccountStore()
		{
			const promise = env.egw().accounts();
			env.requestCalls[env.requestCalls.length - 1].resolve({
				accounts: {1: {value: 1, label: 'Admin'}},
				groups: {7: {value: -7, label: 'Old Group Name'}},
				owngroups: {7: {value: -7, label: 'Old Group Name'}}
			});
			await promise;
		}

		it('"delete" removes the matching entry from the cached account list', async() =>
		{
			await seedAccountStore();

			env.egw().invalidate_account(1, 'delete');

			const list : any = await env.egw().accounts('accounts');
			assert.isUndefined(list.find((a : any) => a.value === '1'));
		});

		it('"edit"/"update" ask link_title for a fresh label, with forced reload', async() =>
		{
			await seedAccountStore();
			const instance = env.egw();
			const linkTitleSpy = sinon.spy(instance, 'link_title');

			instance.invalidate_account(1, 'edit');

			assert.isTrue(linkTitleSpy.calledOnce);
			const [app, id, , , forceReload] = linkTitleSpy.firstCall.args;
			assert.equal(app, 'api-accounts');
			assert.equal(id, 1);
			assert.isTrue(forceReload);
		});

		it('with no id, clears the whole accountData/resolveGroup cache', async() =>
		{
			env.egw().set_account_cache({7: 'seven@example.com', 8: 'eight@example.com'}, 'account_email');

			env.egw().invalidate_account();

			env.egw().accountData([7, 8], 'account_email', false, undefined, undefined);
			assert.equal(env.requestCalls.length, 1);
			assert.deepEqual(env.requestCalls[0].parameters[0], [7, 8]);
		});
	});

	describe('set_account_data()', () =>
	{
		it('sets a plain (non-templated) field directly from accountData()', async() =>
		{
			env.egw().set_account_cache({7: 'seven@example.com'}, 'account_email');
			const targetWidget = {set_value: sinon.stub()};
			const root = {getWidgetById: sinon.stub().returns(targetWidget)};
			const srcWidget : any = {get_value: () => 7, getRoot: () => root};

			env.egw().set_account_data(srcWidget, 'target', 'account_email');
			await wait();

			assert.isTrue(targetWidget.set_value.calledWith('seven@example.com'));
		});

		it('does nothing if the source widget has no value or the target widget is not found', () =>
		{
			const root = {getWidgetById: sinon.stub().returns(null)};
			const srcWidget : any = {get_value: () => 7, getRoot: () => root};

			// must not throw even though the target can't be found
			env.egw().set_account_data(srcWidget, 'missing_target', 'account_email');
		});
	});

	describe('prompts()', () =>
	{
		it('filters both top-level prompts and their children by app', () =>
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

		it('returns an empty list before set_prompts() has been called', () =>
		{
			assert.deepEqual(env.egw().prompts('anyapp'), []);
		});
	});
});
