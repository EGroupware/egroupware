import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for ticket #125621's root cause: openComposePopupUrlPost() (the classic
 * forward-as-attachment POST fallback, for when settings.id - many comma-joined message ids - is
 * too long for a GET url) used to reserve its popup via egw.open('', 'mail', 'add', ...), which
 * resolves the mail/add app-registry entry into a REAL url (the classic mail.mail_hooks.compose
 * menuaction, itself redirecting back to compose.php with NO id at all) and navigates the popup
 * there as a side effect - racing the form POST below it for control of the same named window.
 * Confirmed live: a bare egw.open() call, with no form ever submitted afterward, still produced
 * its own empty GET hit on compose.php. Whichever navigation the browser applied last won, so this
 * intermittently opened an empty/default compose instead of the posted id, with nothing in any
 * log or console to point at why - composeWithPresetPost() (ComposeWithPresetLongBody.test.ts)
 * shared the identical bug/fix.
 *
 * Setup mirrors ComposeWithPresetLongBody.test.ts's own bare-prototype + stubbed-global-egw
 * pattern - openComposePopupUrlPost() only ever touches `this.egw`/the bare global `egw` and
 * document.createElement('form')/form.submit() against the real top-level test document.
 */
describe('MailApp.openComposePopupUrlPost()', () =>
{
	let app : MailApp;
	let egw : any;
	let MailAppClass : typeof MailApp;
	let submitStub : sinon.SinonStub;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		egw = {
			link: (path : string, params : any) => path + '?' + new URLSearchParams(params).toString(),
			getOpenWindows: () => [],
			// Same dual-purpose stub as ComposeWithPresetLongBody.test.ts's own egw.openPopup -
			// an empty url is the "give me a blank, named popup" call this method's own fix relies
			// on; a real url would mean something navigated the popup directly, which must never
			// happen here.
			openPopup: sinon.stub().callsFake((url : string) => url === '' ? {name : 'compose__'} : undefined),
		};
		(<any>window).egw = egw;

		submitStub = sinon.stub(HTMLFormElement.prototype, 'submit');

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw});
	});

	afterEach(() =>
	{
		submitStub.restore();
	});

	it('reserves a genuinely blank popup, never a real url, then posts the id into it', async() =>
	{
		await (app as any).openComposePopupUrlPost({id: 'mail::1::2::3,mail::1::2::4', from: 'forward'}, '1');

		assert.isTrue(egw.openPopup.calledOnce);
		assert.strictEqual(egw.openPopup.firstCall.args[0], '',
			'must open genuinely blank - a real url here would navigate the popup itself, racing the form POST below');

		assert.isTrue(submitStub.calledOnce);
		const form = submitStub.firstCall.thisValue as HTMLFormElement;
		assert.equal(form.method, 'post');
		assert.include(form.action, '/mail/compose.php');
		assert.equal(form.target, 'compose__');
		assert.equal((form.elements.namedItem('id') as HTMLInputElement).value, 'mail::1::2::3,mail::1::2::4');
	});

	it('does nothing further when the popup is blocked', async() =>
	{
		egw.openPopup = sinon.stub().returns(undefined);

		await (app as any).openComposePopupUrlPost({id: 'mail::1::2::3', from: 'forward'}, '1');

		assert.isFalse(submitStub.called, 'nothing to post into if the popup never opened');
	});

	/**
	 * Regression test: egw.openPopup() returns a Promise, not a Window, whenever the current
	 * window runs the full desktop framework (egw_open.ts's own openPopup() hands off to
	 * EgwFramework.openPopup(), an async method, any time window.framework exists - true for every
	 * normal desktop tab). Without awaiting it, `popup.name` reads undefined off the pending
	 * Promise object itself, silently falling back to the '_blank' target below - the form then
	 * posts into a brand new, unrelated tab instead of the window this call just reserved, which
	 * sits abandoned at about:blank forever. Found live in a customer environment.
	 */
	it('awaits a Promise-returning openPopup() (the real desktop-framework case) instead of reading .name off the pending Promise itself', async() =>
	{
		egw.openPopup = sinon.stub().callsFake((url : string) =>
			url === '' ? Promise.resolve({name : 'compose__'}) : Promise.resolve(undefined));

		await (app as any).openComposePopupUrlPost({id: 'mail::1::2::3', from: 'forward'}, '1');

		assert.isTrue(submitStub.calledOnce);
		const form = submitStub.firstCall.thisValue as HTMLFormElement;
		assert.equal(form.target, 'compose__',
			'must target the actually-reserved window, not fall back to _blank because .name was read off an unawaited Promise');
	});
});
