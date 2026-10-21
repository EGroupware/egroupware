import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailCompose} from "../compose";
import type {MailApp} from "../app";

/**
 * Ticket #126711 (2026-10-20, a real customer, relayed by Birgit): composing/sending a mail on a
 * narrow viewport (or with the "open_popups_in: same_window" preference) left the compose dialog
 * open after a successful send, with no error - the user had to check manually whether it sent.
 *
 * Root cause: trySendViaJmap()'s own close call site was a plain, unconditional `window.close()` -
 * correct for a real popup window (script-opened, so script-closable), but MailApp.
 * openComposeDialog() (mail/js/app.ts) builds this compose as an inline <et2-dialog> in the SAME
 * window on a narrow viewport instead, where `window` is the main app window itself - browsers
 * correctly refuse to let script close a window/tab it didn't open, so nothing visibly happened at
 * all. Live-verified (claude-in-chrome against boulder.egroupware.org, narrow viewport forced via
 * a matchMedia() override): a real send genuinely succeeded, but the dialog never closed.
 *
 * Extracted into closeComposeWindow() purely for unit testability, same reasoning
 * flagSourceMessagesAfterSend() documents - trySendViaJmap() itself has too many unrelated
 * preconditions to drive directly.
 */
describe("MailCompose.closeComposeWindow()", () =>
{
	function createCompose(domContainer : HTMLElement) : MailCompose
	{
		const app = {egw : {lang : (s : string) => s}} as unknown as MailApp;
		const compose = new MailCompose(app);
		(compose as any).et2 = {
			getInstanceManager : () => ({DOMContainer : domContainer}),
		};
		return compose;
	}

	let windowCloseStub : sinon.SinonStub;

	beforeEach(() =>
	{
		windowCloseStub = sinon.stub(window, 'close');
	});

	afterEach(() =>
	{
		windowCloseStub.restore();
	});

	it("calls window.close() for a real popup (no <et2-dialog> ancestor)", () =>
	{
		const container = document.createElement('div');
		document.body.append(container);
		try
		{
			const compose = createCompose(container);
			(compose as any).closeComposeWindow();

			assert.isTrue(windowCloseStub.calledOnce);
		}
		finally
		{
			container.remove();
		}
	});

	it("closes the <et2-dialog> ancestor instead of calling window.close(), for an inline mobile/narrow-viewport compose", () =>
	{
		const dialog = document.createElement('et2-dialog') as any;
		const closeSpy = sinon.spy();
		dialog.close = closeSpy;
		const content = document.createElement('div');
		content.className = 'dialog_content';
		const container = document.createElement('div');
		dialog.append(content);
		content.append(container);
		document.body.append(dialog);
		try
		{
			const compose = createCompose(container);
			(compose as any).closeComposeWindow();

			assert.isTrue(closeSpy.calledOnce, "the dialog's own close() must be called");
			assert.isFalse(windowCloseStub.called, "window.close() must never be called when an <et2-dialog> ancestor exists");
		}
		finally
		{
			dialog.remove();
		}
	});

	it("finds the <et2-dialog> ancestor through multiple levels of wrapper elements - the exact DOM shape live-verified for ticket #126711 (<et2-dialog><div class=\"dialog_content\"><et2-template>...), no <form> anywhere", () =>
	{
		const dialog = document.createElement('et2-dialog') as any;
		const closeSpy = sinon.spy();
		dialog.close = closeSpy;
		const content = document.createElement('div');
		content.className = 'dialog_content dialog--has_template';
		const template = document.createElement('et2-template');
		const container = document.createElement('div');
		dialog.append(content);
		content.append(template);
		template.append(container);
		document.body.append(dialog);
		try
		{
			const compose = createCompose(container);
			(compose as any).closeComposeWindow();

			assert.isTrue(closeSpy.calledOnce);
			assert.isFalse(windowCloseStub.called);
		}
		finally
		{
			dialog.remove();
		}
	});
});
