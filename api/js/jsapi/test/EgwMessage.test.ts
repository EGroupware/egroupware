/**
 * Tests for egw_message.js ("message" module).
 *
 * MODULE_WND_LOCAL: displaying messages/app-header, the app-refresh
 * dispatch chain, and push-notification fan-out to registered EgwApp
 * instances. See EgwMessageHarness for how the real, unmodified file is
 * loaded and what minimal framework stand-ins it needs.
 *
 * refresh()'s final fallback (reload the window via `location.href =`) is
 * deliberately never reached by these tests - assigning to a real
 * window's location.href triggers actual navigation, which would hang or
 * flake the test runner. Every test provides an earlier hook (app_refresh,
 * etemplate2.app_refresh, or framework.refresh) so execution returns
 * before that point. That final fallback path is residual, untested risk.
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwMessageEnv, EgwMessageEnv} from "./EgwMessageHarness";

describe('egw_message.js (message)', () =>
{
	let env : EgwMessageEnv;

	beforeEach(async() =>
	{
		env = await createEgwMessageEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.message);
		assert.isFunction(instance.is_popup);
		assert.isFunction(instance.app_name);
		assert.isFunction(instance.app_header);
		assert.isFunction(instance.loading_prompt);
		assert.isFunction(instance.refresh);
		assert.isFunction(instance.push);
	});

	describe('is_popup()', () =>
	{
		it('is false with no opener', () =>
		{
			(env.window as any).opener = null;
			assert.isFalse(env.egw().is_popup());
		});

		it('is false when opener is the window itself', () =>
		{
			(env.window as any).opener = env.window;
			assert.isFalse(env.egw().is_popup());
		});

		it('is true when a different opener\'s top has a real egw() function', () =>
		{
			(env.window as any).opener = {top: {egw: () => {}}};
			assert.isTrue(env.egw().is_popup());
		});

		it('is false, not thrown, if accessing opener.top throws (cross-origin)', () =>
		{
			(env.window as any).opener = {get top() { throw new Error('SecurityError'); }};
			assert.isFalse(env.egw().is_popup());
		});
	});

	describe('app_name()', () =>
	{
		it('falls back to window.egw_appName with no framework', () =>
		{
			(env.window as any).egw_appName = 'myapp';
			assert.equal(env.egw().app_name(), 'myapp');
		});

		it('uses framework.activeApp.appName when present and not a popup', () =>
		{
			(env.window as any).egw_appName = 'fallback';
			(env.window as any).framework = {activeApp: {appName: 'active-one'}};
			assert.equal(env.egw().app_name(), 'active-one');
		});

		it('ignores framework.activeApp while running as a popup', () =>
		{
			(env.window as any).egw_appName = 'fallback';
			(env.window as any).framework = {activeApp: {appName: 'active-one'}};
			(env.window as any).opener = {top: {egw: () => {}}};
			assert.equal(env.egw().app_name(), 'fallback');
		});
	});

	describe('app_header()', () =>
	{
		it('updates the header div and document title when there is no framed template', () =>
		{
			const div = env.window.document.createElement('div');
			div.id = 'divAppboxHeader';
			env.window.document.body.appendChild(div);
			// Real titles are always server-rendered as "<site_title> [<app_header>]"
			// (see api/src/Framework.php's $site_title construction) - app_header()
			// updates that existing bracketed suffix in place.
			env.window.document.title = 'Something [Old Header]';

			env.egw().app_header('New Header');

			assert.equal(div.textContent, 'New Header');
			assert.equal(env.window.document.title, 'Something [New Header]');
		});

		it('does nothing when a framed template already manages the website title', () =>
		{
			const div = env.window.document.createElement('div');
			div.id = 'divAppboxHeader';
			div.textContent = 'unchanged';
			env.window.document.body.appendChild(div);
			(env.window as any).framework = {setWebsiteTitle: () => {}};

			env.egw().app_header('New Header');

			assert.equal(div.textContent, 'unchanged');
		});

		it('replaces an existing bracketed title suffix, matching how the server always renders it', () =>
		{
			// Previously /[.*]$/ (a character class matching a single literal
			// '.' or '*') left any already-bracketed title untouched, since
			// real EGroupware titles never end in a bare '.' or '*' - the
			// server always renders "<site_title> [<app_header>]"
			// (api/src/Framework.php). Fixed to /\[.*\]$/.
			env.window.document.title = 'Mail [old header]';

			env.egw().app_header('New Header');

			assert.equal(env.window.document.title, 'Mail [New Header]');
		});
	});

	describe('loading_prompt()', () =>
	{
		// Note the id really is "egw-loadin-prompt_" (missing the "g") in
		// the source - matching it here on purpose, not a typo in the test.
		it('shows a spinner container with the given message text', () =>
		{
			env.egw().loading_prompt('test-id', true, 'Loading...');

			const node = env.window.document.getElementById('egw-loadin-prompt_test-id');
			assert.exists(node);
			assert.isTrue(node!.classList.contains('egw-loading-prompt-container'));
			assert.include(node!.textContent, 'Loading...');
		});

		it('removes the container when hiding', () =>
		{
			env.egw().loading_prompt('test-id', true, 'Loading...');
			env.egw().loading_prompt('test-id', false);

			assert.isNull(env.window.document.getElementById('egw-loadin-prompt_test-id'));
		});
	});

	describe('message()', () =>
	{
		it('delegates to the top window\'s instance when this window is not the top and not a popup', () =>
		{
			const otherWindow = env.createWindow();
			const topInstance = env.egw(env.window);
			const topMessageSpy = sinon.stub(topInstance, 'message');

			env.egw('someapp', otherWindow).message('hello', 'info');

			assert.isTrue(topMessageSpy.calledOnceWith('hello', 'info'));
		});

		it('removes any existing fallback element and returns undefined for an empty message with no framework', () =>
		{
			const el = env.window.document.createElement('egw-message');
			env.window.document.body.appendChild(el);

			const result = env.egw().message('');

			assert.isUndefined(result);
			assert.isNull(env.window.document.querySelector('egw-message'));
		});

		it('returns framework.message()\'s promise as-is when the framework is attached to the document', async() =>
		{
			const frameworkNode : any = env.window.document.createElement('div');
			env.window.document.body.appendChild(frameworkNode);
			const fakeMessage = {toast: sinon.stub()};
			frameworkNode.message = sinon.stub().returns(Promise.resolve(fakeMessage));
			(env.window as any).framework = frameworkNode;

			const result = await env.egw().message('hello', 'info');

			assert.isTrue(frameworkNode.message.calledOnce);
			assert.strictEqual(result, fakeMessage);
			assert.isFalse(fakeMessage.toast.called, 'no extra popup-toast handling when framework is attached to the document');
		});

		it('adds popup-toast handling when the framework exists but is NOT attached to this window\'s document', async() =>
		{
			const frameworkNode : any = env.window.document.createElement('div'); // deliberately not appended
			const fakeMessage = {toast: sinon.stub()};
			frameworkNode.message = sinon.stub().returns(Promise.resolve(fakeMessage));
			(env.window as any).framework = frameworkNode;

			await env.egw().message('hello', 'info');

			assert.isTrue(fakeMessage.toast.calledOnce);
		});
	});

	describe('refresh()', () =>
	{
		it('always calls message() first, and returns early for "msg-only-push-refresh" without touching app_refresh', () =>
		{
			const instance = env.egw();
			const messageStub = sinon.stub(instance, 'message');
			(env.window as any).app_refresh = sinon.stub();

			instance.refresh('done', 'msg-only-push-refresh', 1, 'update');

			assert.isTrue(messageStub.calledOnce);
			assert.isFalse((env.window as any).app_refresh.called);
		});

		it('always notifies every EgwApp observer, but only suppresses the regular refresh if the matching app\'s observer returns false', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'message');
			const matching = sinon.stub().returns(false);
			const other = sinon.stub().returns(false); // returns false too, but appname does not match
			(env.window as any).EgwApp = [
				{appname: 'other', observer: other},
				{appname: 'infolog', observer: matching}
			];
			(env.window as any).app_refresh = sinon.stub();

			instance.refresh('done', 'infolog', 1, 'update');

			assert.isTrue(matching.calledOnceWith('done', 'infolog', 1, 'update', undefined, []));
			assert.isTrue(other.calledOnce, 'every observer runs regardless of app match');
			assert.isFalse((env.window as any).app_refresh.called, 'the matching app\'s observer returning false suppresses the regular refresh');
		});

		it('delegates to a plain, unregistered window.app_refresh function', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'message');
			(env.window as any).app_refresh = sinon.stub();

			instance.refresh('done', 'infolog', 1, 'update');

			assert.isTrue((env.window as any).app_refresh.calledOnceWith('done', 'infolog', 1, 'update'));
		});

		it('lets window.framework.refresh fully handle it when it returns a falsy replacement window', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'message');
			(env.window as any).app_refresh = sinon.stub(); // proves we returned before reaching this
			(env.window as any).framework = {refresh: sinon.stub().returns(undefined)};

			instance.refresh('done', 'infolog', 1, 'update');

			assert.isFalse((env.window as any).app_refresh.called);
		});

		it('delegates to window.etemplate2.app_refresh, and refreshes the target app too when it differs', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'message');
			(env.window as any).egw_appName = 'infolog';
			const appRefresh = sinon.stub().returns(true);
			(env.window as any).etemplate2 = {app_refresh: appRefresh};

			instance.refresh('done', 'infolog', 1, 'update', 'mail');

			assert.isTrue(appRefresh.calledWith('done', 'infolog', 1, 'update'));
			assert.isTrue(appRefresh.calledWith('done', 'mail'));
		});
	});

	describe('push()', () =>
	{
		it('logs a warning and does nothing for undefined pushData', () =>
		{
			const instance = env.egw();
			instance.registerPush = sinon.stub();

			instance.push(undefined);

			assert.isFalse((<sinon.SinonStub>instance.registerPush).called);
		});

		it('recursively pushes each item of an array', () =>
		{
			const instance = env.egw();
			instance.registerPush = sinon.stub();
			const pushSpy = sinon.spy(instance, 'push');

			const data = [
				{type: 'update', app: 'a', id: 1, account_id: 1},
				{type: 'update', app: 'b', id: 2, account_id: 1}
			];
			instance.push(data);

			assert.equal(pushSpy.callCount, 3, '1 outer call + 2 recursive calls');
			assert.isTrue((<sinon.SinonStub>instance.registerPush).calledTwice);
		});

		it('notifies EgwApp observers with a .push() method and forwards to registerPush', () =>
		{
			const instance = env.egw();
			instance.registerPush = sinon.stub();
			const appObj = {push: sinon.stub()};
			(env.window as any).EgwApp = [appObj];

			const data = {type: 'update', app: 'infolog', id: 1, account_id: 2};
			instance.push(data);

			assert.isTrue(appObj.push.calledOnceWith(data));
			assert.isTrue((<sinon.SinonStub>instance.registerPush).calledOnceWith(data));
		});
	});
});
