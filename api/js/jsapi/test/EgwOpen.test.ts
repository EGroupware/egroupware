/**
 * Tests for egw_open.js ("open" module) - MODULE_WND_LOCAL.
 *
 * Covers open(), open_link()'s URL/target dispatch tree, openTab(),
 * openPopup(), availHeight(), link_handler(), close(), _check_popupBlocker(),
 * the private mailto() parser (reached via open_link), openDialog(), and
 * openWithinWindow() including its multi-popup Et2Dialog chooser (see
 * EgwOpenHarness for how a minimal, non-rendering Et2Dialog stand-in makes
 * the chooser's callback directly invocable, as if a button had been
 * clicked). See EgwOpenHarness for what else is stubbed and why.
 *
 * NOT covered (documented residual risk, see EgwOpenHarness docblock):
 * link_handler()'s no-framework fallback (real navigation), and
 * openWithinWindow()'s long-content form-POST path (real form submission).
 */
import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {createEgwOpenEnv, EgwOpenEnv} from "./EgwOpenHarness";

describe('egw_open.js (open)', () =>
{
	let env : EgwOpenEnv;

	beforeEach(async() =>
	{
		env = await createEgwOpenEnv();
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('loads and registers the expected methods', () =>
	{
		const instance = env.egw();
		assert.isFunction(instance.open);
		assert.isFunction(instance.open_link);
		assert.isFunction(instance.openTab);
		assert.isFunction(instance.openPopup);
		assert.isFunction(instance.openDialog);
		assert.isFunction(instance.availHeight);
		assert.isFunction(instance.link_handler);
		assert.isFunction(instance.close);
		assert.isFunction(instance._check_popupBlocker);
		assert.isFunction(instance.openWithinWindow);
	});

	describe('open_link()', () =>
	{
		it('executes a "javascript:" link directly, without opening anything', () =>
		{
			(env.window as any).__probe = 0;
			env.egw().open_link('javascript:window.__probe = 42;');

			assert.equal((env.window as any).__probe, 42);
			assert.isFalse((env.window.open as sinon.SinonStub).called);
		});

		it('rewrites a bare "app.method.sub" menuaction into /index.php?menuaction=...', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'link_handler');

			instance.open_link('infolog.infolog_ui.index');

			assert.isTrue((<sinon.SinonStub>instance.link_handler).calledOnce);
			const url = (<sinon.SinonStub>instance.link_handler).firstCall.args[0];
			assert.equal(url, 'https://example.test/index.php?menuaction=infolog.infolog_ui.index');
		});

		it('leaves an already-relative /index.php url alone (besides webserverUrl prefixing)', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'link_handler');

			instance.open_link('/index.php?menuaction=infolog.infolog_ui.index');

			const url = (<sinon.SinonStub>instance.link_handler).firstCall.args[0];
			assert.equal(url, 'https://example.test/index.php?menuaction=infolog.infolog_ui.index');
		});

		it('does not double-prefix a url that already includes the webserverUrl', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'link_handler');

			instance.open_link('https://example.test/index.php?menuaction=x');

			const url = (<sinon.SinonStub>instance.link_handler).firstCall.args[0];
			assert.equal(url, 'https://example.test/index.php?menuaction=x');
		});

		it('routes to openPopup() and remembers the window when _popup is a "WxH" size', () =>
		{
			const instance = env.egw();
			const fakePopup = {name: 'popup1'};
			sinon.stub(instance, 'openPopup').returns(fakePopup as any);
			const storeWindowStub = sinon.stub(env.egw(), 'storeWindow');

			instance.open_link('https://example.test/index.php?x=1', '_blank', '600x400', 'mail');

			assert.isTrue((<sinon.SinonStub>instance.openPopup).calledOnce);
			const args = (<sinon.SinonStub>instance.openPopup).firstCall.args;
			assert.equal(args[1], '600');
			assert.equal(args[2], '400');
			assert.isTrue(storeWindowStub.calledOnceWith('mail', fakePopup));
		});

		it('routes to link_handler() for an undefined, "_self", or known-tab target', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'link_handler');
			env.stubs.link_app_list.returns({mail: 'Mail'});

			instance.open_link('https://example.test/x', 'mail');

			assert.isTrue((<sinon.SinonStub>instance.link_handler).calledOnceWith('https://example.test/x', 'mail'));
		});

		it('falls back to window.open() for an unrecognized target with no popup size', () =>
		{
			const instance = env.egw();

			instance.open_link('https://example.test/x', 'some_other_window');

			assert.isTrue((env.window.open as sinon.SinonStub).calledOnceWith('https://example.test/x', 'some_other_window'));
		});

		it('maps "_browser" target to "_blank" normally, and to "_self" on mobile', () =>
		{
			const instance = env.egw();

			instance.open_link('https://example.test/x', '_browser');
			assert.equal((env.window.open as sinon.SinonStub).firstCall.args[1], '_blank');

			(env.window as any).egwIsMobile = () => 'iOS';
			instance.open_link('https://example.test/x', '_browser');
			assert.equal((env.window.open as sinon.SinonStub).secondCall.args[1], '_self');
		});

		it('applies mime_info overrides (mime_url) by routing through egw.link', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'link_handler');
			env.stubs.get_mime_info.returns({menuaction: 'filemanager.x', mime_url: 'path'});

			instance.open_link('https://example.test/some/file.pdf', undefined, undefined, undefined, false, 'application/pdf');

			assert.isTrue(env.stubs.link.calledOnce);
			const [url, data] = env.stubs.link.firstCall.args;
			assert.equal(url, '/index.php');
			assert.equal(data.path, 'https://example.test/some/file.pdf');
		});

		/**
		 * Regression test (found live 2026-08-31, mail-compose-jmap-migration.md): a caller can
		 * already have built the correct, specific URL for its own context (eg. mail's own
		 * AttachmentJmap::createAttachmentBlock() resolving a proper mail_ui.displayMessage popup
		 * for a message/rfc822 attachment) - the OLD "already wrapped?" check only recognized a
		 * link matching THIS mime type's own registry entry, so a different, unrelated registry
		 * entry for the SAME mime type (eg. mail_hooks.inc.php's own message/rfc822 entry, meant
		 * for importing a VFS-stored .eml file, not viewing a mail attachment) silently overwrote
		 * an already-correct link for a completely different menuaction.
		 */
		it('leaves an already-resolved menuaction URL alone, even when the mime registry has an unrelated entry for the same type', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'link_handler');
			env.stubs.get_mime_info.returns({
				menuaction: 'mail.mail_ui.importMessageFromVFS2DraftAndDisplay',
				mime_id: 'formData[file]', mime_data: 'formData[data]',
			});

			instance.open_link(
				'https://example.test/index.php?menuaction=mail.mail_ui.displayMessage&mode=display&id=mail%3A%3A1%3A%3AINBOX%3A%3A42',
				undefined, undefined, undefined, false, 'message/rfc822');

			assert.isFalse(env.stubs.link.called, 'egw.link() must not be called - nothing to rebuild');
			const url = (<sinon.SinonStub>instance.link_handler).firstCall.args[0];
			assert.equal(url, 'https://example.test/index.php?menuaction=mail.mail_ui.displayMessage&mode=display&id=mail%3A%3A1%3A%3AINBOX%3A%3A42');
		});
	});

	describe('mailto: handling (via open_link)', () =>
	{
		it('parses to/cc/bcc, quoted-comma-aware, and forwards to openWithinWindow + a summary message', () =>
		{
			// mailto() does NOT URL-decode its query part - it expects literal
			// characters, with "__AMPERSAND__" as the only escape mechanism
			// (for a literal "&" that would otherwise look like a field
			// separator). Real callers construct the uri that way; percent
			// encoding here would just end up as one opaque, unsplittable token.
			const instance = env.egw();
			const openWithinWindowStub = sinon.stub(instance, 'openWithinWindow');

			instance.open_link('mailto:one@example.com?cc="Doe, Jane" <jane@example.com>,two@example.com');

			assert.isTrue(openWithinWindowStub.calledOnce);
			const [app, method, content] = openWithinWindowStub.firstCall.args;
			assert.equal(app, 'mail');
			assert.equal(method, 'setCompose');
			assert.deepEqual(content.to, ['one@example.com']);
			assert.deepEqual(content.cc, ['"Doe, Jane" <jane@example.com>', 'two@example.com']);
			assert.isTrue(env.stubs.message.calledOnce);
		});

		it('HTML-entity-encodes < and > in the preset[mailto] extra, guarding against server XSS filtering', () =>
		{
			const instance = env.egw();
			const openWithinWindowStub = sinon.stub(instance, 'openWithinWindow');

			instance.open_link('mailto:Mathias <mathias@example.com>');

			const extra = openWithinWindowStub.firstCall.args[3];
			assert.include(extra['preset[mailto]'], '&lt;');
			assert.include(extra['preset[mailto]'], '&gt;');
			assert.notInclude(extra['preset[mailto]'], '<mathias@example.com>');
		});
	});

	describe('open()', () =>
	{
		it('alerts and does nothing when the app is not in the link registry', () =>
		{
			const instance = env.egw();
			const alertStub = sinon.stub(env.window, 'alert');
			const openLinkStub = sinon.stub(instance, 'open_link');
			env.stubs.link_get_registry.returns(undefined);

			instance.open(123, 'nosuchapp');

			assert.isTrue(alertStub.calledOnce);
			assert.isFalse(openLinkStub.called);
		});

		it('falls back from "edit" to "view" when the registry has no edit entry, and alerts if neither exists', () =>
		{
			const instance = env.egw();
			const alertStub = sinon.stub(env.window, 'alert');
			sinon.stub(instance, 'open_link');
			env.stubs.link_get_registry.returns({view: {menuaction: 'infolog.view'}, view_id: 'info_id'});

			instance.open(123, 'infolog'); // no type given -> defaults to "edit", falls back to "view"

			assert.isFalse(alertStub.called);
			assert.isTrue((<sinon.SinonStub>instance.open_link).calledOnce);
		});

		it('returns {url} directly for target="_tab" without calling open_link', () =>
		{
			const instance = env.egw();
			const openLinkStub = sinon.stub(instance, 'open_link');
			env.stubs.link_get_registry.returns({
				view: 'javascript:app.infolog.view', view_id: 'info_id'
			});

			const result = instance.open(123, 'infolog', 'view', '', '_tab');

			assert.isFalse(openLinkStub.called);
			assert.isString(result.url);
		});

		it('routes an "app.xxx" registry entry through callFunc instead of open_link', () =>
		{
			const instance = env.egw();
			const openLinkStub = sinon.stub(instance, 'open_link');
			env.stubs.link_get_registry.returns({
				view: 'app.infolog.viewEntry', view_id: 'info_id'
			});

			instance.open(123, 'infolog', 'view');

			assert.isFalse(openLinkStub.called);
			assert.isTrue(env.stubs.callFunc.calledOnce);
			assert.equal(env.stubs.callFunc.firstCall.args[0], 'app.infolog.viewEntry');
		});

		it('delegates "file" app entries to mime_open and passes the resolved url to open_link', () =>
		{
			const instance = env.egw();
			const openLinkStub = sinon.stub(instance, 'open_link');
			env.stubs.mime_open.returns('/index.php?menuaction=filemanager.view');

			instance.open('/some/path', 'file');

			assert.isTrue(openLinkStub.calledOnce);
			assert.include(openLinkStub.firstCall.args[0], 'menuaction=filemanager.view');
		});
	});

	describe('openTab()', () =>
	{
		it('uses framework.tabLinkHandler with the url from open(..., "_tab") when a framework is present', () =>
		{
			const instance = env.egw();
			sinon.stub(instance, 'open').returns({url: 'https://example.test/x'} as any);
			const tabLinkHandler = sinon.stub().returns('infolog-tab');
			(env.window as any).framework = {tabLinkHandler};

			const result = instance.openTab(123, 'infolog', 'view', '', {title: 'Entry'});

			assert.isTrue(tabLinkHandler.calledOnceWith('https://example.test/x', {title: 'Entry'}));
			assert.equal(result, 'infolog-tab');
		});

		it('calls open() directly (no _tab target) when there is no framework', () =>
		{
			const instance = env.egw();
			const openStub = sinon.stub(instance, 'open');

			instance.openTab(123, 'infolog', 'view', '');

			assert.isTrue(openStub.calledOnceWith(123, 'infolog', 'view', ''));
		});
	});

	describe('openPopup()', () =>
	{
		it('delegates entirely to framework.openPopup when a framework is present', () =>
		{
			const instance = env.egw();
			const frameworkOpenPopup = sinon.stub().returns('framework-result');
			(env.window as any).framework = {openPopup: frameworkOpenPopup};

			const result = instance.openPopup('/x', 500, 400, 'name', 'infolog', true, 'no');

			assert.isTrue(frameworkOpenPopup.calledOnceWith('/x', 500, 400, 'name', 'infolog', true, 'no', env.window));
			assert.equal(result, 'framework-result');
			assert.isFalse((env.window.open as sinon.SinonStub).called);
		});

		it('opens a real window (via the stubbed window.open) and injects egw onto it, when there is no framework', () =>
		{
			const instance = env.egw();
			const fakeWindow : any = {};
			(env.window.open as sinon.SinonStub).returns(fakeWindow);

			const result = instance.openPopup('/x', 500, 400, 'popupname', false, true, 'no');

			assert.isTrue((env.window.open as sinon.SinonStub).calledOnce);
			const [url, name] = (env.window.open as sinon.SinonStub).firstCall.args;
			assert.equal(url, '/x');
			assert.equal(name, 'popupname');
			assert.strictEqual(fakeWindow.egw, env.window.egw, 'the popup must get the opener\'s egw object injected');
			assert.strictEqual(result, fakeWindow);
		});

		it('returns undefined when _returnID is false, even though window.open() still runs', () =>
		{
			const instance = env.egw();
			(env.window.open as sinon.SinonStub).returns({});

			const result = instance.openPopup('/x', 500, 400, 'name', false, false, 'no');

			assert.isTrue((env.window.open as sinon.SinonStub).called);
			assert.isUndefined(result);
		});
	});

	describe('availHeight()', () =>
	{
		it('matches the documented screen.height/availHeight formula', () =>
		{
			const instance = env.egw();
			const expected = screen.availHeight < screen.height
				? (navigator.userAgent.match(/windows/ig) ? screen.availHeight - 100 : screen.availHeight)
				: screen.height - 100;

			assert.equal(instance.availHeight(), expected);
		});
	});

	describe('link_handler()', () =>
	{
		it('rewrites an admin-targeted menuaction to go through admin_ui.index, then calls framework.linkHandler', () =>
		{
			const instance = env.egw();
			const linkHandler = sinon.stub();
			(env.window as any).framework = {linkHandler};

			instance.link_handler('/index.php?menuaction=admin.some_class.method', 'admin');

			assert.isTrue(linkHandler.calledOnce);
			assert.equal(linkHandler.firstCall.args[0],
				'/index.php?menuaction=admin.admin_ui.index&load=admin.some_class.method');
		});

		it('does not rewrite a link that already targets admin_ui.index', () =>
		{
			const instance = env.egw();
			const linkHandler = sinon.stub();
			(env.window as any).framework = {linkHandler};

			instance.link_handler('/index.php?menuaction=admin.admin_ui.index', 'admin');

			assert.equal(linkHandler.firstCall.args[0], '/index.php?menuaction=admin.admin_ui.index');
		});
	});

	describe('close()', () =>
	{
		it('uses framework.popup_close when available', () =>
		{
			const instance = env.egw();
			const popupClose = sinon.stub();
			(env.window as any).framework = {popup_close: popupClose};
			const windowClose = sinon.stub(env.window, 'close');

			instance.close();

			assert.isTrue(popupClose.calledOnceWith(env.window));
			assert.isFalse(windowClose.called);
		});

		it('falls back to window.close() without a framework', () =>
		{
			const instance = env.egw();
			const windowClose = sinon.stub(env.window, 'close');

			instance.close();

			assert.isTrue(windowClose.calledOnce);
		});
	});

	describe('_check_popupBlocker()', () =>
	{
		it('closes the probe popup and returns false when popups are allowed', () =>
		{
			const instance = env.egw();
			const probePopup = {close: sinon.stub()};
			(env.window.open as sinon.SinonStub).returns(probePopup);

			const blocked = instance._check_popupBlocker('/x');

			assert.isFalse(blocked);
			assert.isTrue(probePopup.close.calledOnce);
			assert.isFalse((env.window as any).Et2Dialog.show_dialog.called);
		});

		it('shows the warning dialog and returns true when the probe popup is blocked', () =>
		{
			const instance = env.egw();
			(env.window.open as sinon.SinonStub).returns(null);

			const blocked = instance._check_popupBlocker('/x');

			assert.isTrue(blocked);
			assert.isTrue((env.window as any).Et2Dialog.show_dialog.calledOnce);
		});
	});

	describe('openDialog()', () =>
	{
		it('resolves with the dialog DOM node when the response contains dialog HTML', async() =>
		{
			const instance = env.egw();

			const promise = instance.openDialog('infolog.infolog_ui.something');

			assert.equal(env.jsonCalls.length, 1);
			assert.equal(env.jsonCalls[0].menuaction,
				'infolog.jdots_framework.ajax_exec.template.infolog.infolog_ui.something');
			assert.deepEqual(env.jsonCalls[0].parameters, ['index.php?menuaction=infolog.infolog_ui.something', true]);

			env.jsonCalls[0].callback!(['<div class="my-dialog">Hi</div>']);

			const dialog : any = await promise;
			assert.equal(dialog.className, 'my-dialog');
			assert.isTrue(env.window.document.body.contains(dialog));
		});

		it('rejects when the response is not an array of [htmlString, ...]', async() =>
		{
			const instance = env.egw();
			const promise = instance.openDialog('infolog.infolog_ui.something');

			env.jsonCalls[0].callback!({not: 'an array'});

			let error : any;
			try { await promise; } catch (e) { error = e; }
			// not assert.instanceOf(error, Error): constructed inside the
			// iframe's realm - see EgwJson.test.ts's AbortSignal note for why
			// chai's instanceOf hangs on cross-realm objects.
			assert.include(error.message, 'Invalid response');
		});

		it('rejects when the html string does not produce any dialog element', async() =>
		{
			const instance = env.egw();
			const promise = instance.openDialog('infolog.infolog_ui.something');

			env.jsonCalls[0].callback!(['']);

			let error : any;
			try { await promise; } catch (e) { error = e; }
			assert.include(error.message, 'Unable to add dialog');
		});
	});

	describe('openWithinWindow()', () =>
	{
		it('opens a new entry directly via open() when there are no existing popups for the app', () =>
		{
			const instance = env.egw();
			const openStub = sinon.stub(instance, 'open');
			(env.window as any).framework = {popups_get: sinon.stub().returns([])};

			instance.openWithinWindow('mail', 'setCompose', {to: ['x@example.com']}, {'preset[mailto]': 'mailto:x@example.com'});

			assert.isTrue(openStub.calledOnceWith('', 'mail', 'add', {'preset[mailto]': 'mailto:x@example.com'}, 'mail', 'mail', undefined));
		});

		describe('dialog chooser (existing popups for the app)', () =>
		{
			function fakePopup(title : string)
			{
				return {
					closed: false,
					document: {title},
					app: {mail: {setCompose: sinon.stub()}}
				};
			}

			it('builds one option per existing popup plus a "new" option, defaulting to index 0 ("add")', () =>
			{
				const instance = env.egw();
				const popups = [fakePopup('Compose 1'), fakePopup('Compose 2')];
				(env.window as any).framework = {popups_get: sinon.stub().returns(popups)};
				env.stubs.preference.returns('add');

				instance.openWithinWindow('mail', 'setCompose', {to: ['x@example.com']}, {});

				const dialog : any = env.window.document.querySelector('et2-dialog-open-stub');
				assert.exists(dialog);
				const options = dialog.attrs.value.content.grid;
				// env.stubs.lang is an identity stub (returns its first arg
				// unchanged) - it doesn't do %1 placeholder substitution, so
				// this is genuinely "New %1" verbatim here, not a typo.
				assert.deepEqual(options.map((o : any) => o.label), ['Compose 1', 'Compose 2', 'New %1']);
				assert.equal(options.index, 0);
			});

			it('defaults the selection to the "new" option when the remembered preference is "new"', () =>
			{
				const instance = env.egw();
				(env.window as any).framework = {popups_get: sinon.stub().returns([fakePopup('Compose 1')])};
				env.stubs.preference.returns('new');

				instance.openWithinWindow('mail', 'setCompose', {}, {});

				const dialog : any = env.window.document.querySelector('et2-dialog-open-stub');
				assert.equal(dialog.attrs.value.content.grid.index, 'new');
			});

			it('runs popups_grabage_collector() for any closed popup before building the dialog', () =>
			{
				const instance = env.egw();
				const closedPopup = fakePopup('Closed');
				closedPopup.closed = true;
				const gc = sinon.stub();
				(env.window as any).framework = {popups_get: sinon.stub().returns([closedPopup]), popups_grabage_collector: gc};

				instance.openWithinWindow('mail', 'setCompose', {}, {});

				assert.isTrue(gc.calledOnce);
			});

			it('the dialog callback opens a new entry via open() when "new" is chosen, and remembers that choice', () =>
			{
				const instance = env.egw();
				const openStub = sinon.stub(instance, 'open');
				(env.window as any).framework = {popups_get: sinon.stub().returns([fakePopup('Compose 1')])};

				instance.openWithinWindow('mail', 'setCompose', {to: ['x@example.com']}, {extra: 1});

				const dialog : any = env.window.document.querySelector('et2-dialog-open-stub');
				dialog.attrs.callback('add', {grid: {index: 'new'}});

				assert.isTrue(openStub.calledOnceWith('', 'mail', 'add', {extra: 1}, 'mail', 'mail', undefined));
				assert.isTrue(env.stubs.set_preference.calledOnceWith('common', 'mail_add_address_new_popup', 'new'));
			});

			it('the dialog callback dispatches to the chosen existing popup\'s app method, and remembers "add"', () =>
			{
				const instance = env.egw();
				const popup = fakePopup('Compose 1');
				(env.window as any).framework = {popups_get: sinon.stub().returns([popup])};

				instance.openWithinWindow('mail', 'setCompose', {to: ['x@example.com']}, {extra: 1});

				const dialog : any = env.window.document.querySelector('et2-dialog-open-stub');
				dialog.attrs.callback('add', {grid: {index: 0}});

				assert.isTrue(popup.app.mail.setCompose.calledOnceWith(popup, {to: ['x@example.com']}));
				assert.isTrue(env.stubs.set_preference.calledOnceWith('common', 'mail_add_address_new_popup', 'add'));
			});

			it('the dialog callback does nothing further for "cancel"', () =>
			{
				const instance = env.egw();
				const popup = fakePopup('Compose 1');
				const openStub = sinon.stub(instance, 'open');
				(env.window as any).framework = {popups_get: sinon.stub().returns([popup])};

				instance.openWithinWindow('mail', 'setCompose', {}, {});

				const dialog : any = env.window.document.querySelector('et2-dialog-open-stub');
				dialog.attrs.callback('cancel', {grid: {index: 0}});

				assert.isFalse(openStub.called);
				assert.isFalse(popup.app.mail.setCompose.called);
				// the preference IS still recorded, even for cancel - the
				// callback persists it before the switch on _button_id
				assert.isTrue(env.stubs.set_preference.calledOnce);
			});
		});
	});
});
