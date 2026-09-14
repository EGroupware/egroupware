import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

/**
 * app.ts has to be loaded through its explicit source path: a plain `import ... from "../app"`
 * resolves to mail/js/app.js, a gitignored tsc output nothing rebuilds any more (only the
 * app.min.js bundle is refreshed), so the test would silently run against stale code. The
 * specifier is kept in a variable so TypeScript treats it as a dynamic module, while the
 * dev-server transforms the .ts on the fly.
 */
const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for a real tester report (pole.egroupware.org): replying to/forwarding a
 * message opened compose.php with `acc_id=mail` - obviously wrong, and sometimes surfaced
 * downstream as mail_wizard's own "add account" dialog opening empty (an invalid acc_id reads as
 * "no account configured" rather than a parsing bug).
 *
 * Root cause: composeMessage()'s own accId computation read `settings.id.split('::')[0]` - but a
 * mail row id is `mail::<accountID>::<profileID>::<folderID>::<emailID>` (Api\Mail::splitRowID()'s
 * own canonical shape), so index 0 is always the literal string "mail" (the app-name segment), not
 * an account id at all. The correct index is 2. Introduced in 7ec1eefa56 (the original Step 10
 * commit) and copied into the batch-forwardasattach branch (43c0f9384f) - neither ever got a real
 * reply/forward row id in testing, only synthetic acc_id-already-known URLs.
 *
 * Setup: composeMessage() only ever touches `this.egw`/the bare global `egw`, so the app object is
 * a bare Object.create(MailApp.prototype) - no EgwApp constructor, which would want a real
 * framework, sidebox and etemplate.
 */
const REAL_ROW_ID = 'mail::502::18::SU5CT1g=::574501';

describe('MailApp.composeMessage() acc_id resolution', () =>
{
	let app : MailApp;
	let egw : any;
	let MailAppClass : typeof MailApp;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		// openComposePopupUrl() (mail/js/app.ts) routes to the mobile inline-dialog path instead
		// of a real popup when matchMedia('(max-width: 800px)') matches - true by default in this
		// headless test browser's own viewport, which broke every test below (egw.link() never
		// called at all) until stubbed false here to keep exercising the real-popup path these
		// tests are actually about.
		sinon.stub(window, 'matchMedia').returns({matches: false} as MediaQueryList);

		egw = {
			preference: () => '',
			link: sinon.spy((path : string, params : any) => path + '?' + new URLSearchParams(params).toString()),
			getOpenWindows: () => [],
			dataGetUIDdata: () => undefined,
			openPopup: sinon.spy(),
			urlParamsTooLong: () => false,
			openWithinWindow: sinon.spy(),
		};
		(<any>window).egw = egw;

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw, isMainWindow: true});
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	it('resolves the real profileID (not the literal "mail" app-name segment) for a single forward', () =>
	{
		app.composeMessage({id: 'forwardinline'}, [{id: REAL_ROW_ID}]);

		assert.isTrue(egw.link.calledOnce);
		const [, params] = egw.link.firstCall.args;
		assert.equal(params.acc_id, '18');
		assert.notEqual(params.acc_id, 'mail');
	});

	it('resolves the real profileID for a single reply', () =>
	{
		app.composeMessage({id: 'reply'}, [{id: REAL_ROW_ID}]);

		const [, params] = egw.link.firstCall.args;
		assert.equal(params.acc_id, '18');
	});

	it('resolves the real profileID for batch forward-as-attachment too', () =>
	{
		app.composeMessage({id: 'forwardasattach'}, [{id: REAL_ROW_ID}, {id: 'mail::502::18::SU5CT1g=::574502'}]);

		// the batch case goes through openWithinWindow()'s own `_open_new` override (the "nothing
		// to reuse" case) rather than calling egw.link() directly - invoke it ourselves, as
		// openWithinWindow() itself would if it found no existing popup to reuse.
		assert.isTrue(egw.openWithinWindow.calledOnce);
		const openNew = egw.openWithinWindow.firstCall.args[6];
		assert.equal(typeof openNew, 'function');
		openNew();

		const [, params] = egw.link.firstCall.args;
		assert.equal(params.acc_id, '18');
	});
});

/**
 * Regression coverage for a real customer report (2026-09-14, my1155pm.egroupware.de, relayed by
 * ralf via a screenshot): the "New message" toolbar button (composeMessage(false, ...) - a
 * genuinely blank compose, no source row) hung the popup with `acc_id=9::INBOX/006-IF-ORGA-
 * LEITUNG` in its own url.
 *
 * Root cause: for that blank-compose case, composeMessage() reads the user's `ActiveProfileID`
 * mail preference directly as accId - but that preference can still hold a stale pre-JMAP
 * `acc_id::folder` combined value on a long-running instance (nothing migrates an already-stored
 * preference; Api\Mail's own restoreSessionData() still has to tolerate that legacy format
 * server-side). composeWithPreset() (used by several other apps' "email this" actions, eg.
 * addressbook's own addEmail()) reads the exact same preference the exact same unsplit way.
 *
 * Fix: split off everything after '::' before using it as accId, same as refreshQuotaDisplay()
 * already does for the identical reason a few hundred lines away in this same file.
 */
describe('MailApp blank-compose accId sanitizes a legacy-format ActiveProfileID preference', () =>
{
	let app : MailApp;
	let egw : any;
	let MailAppClass : typeof MailApp;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	function setup(activeProfileID : string)
	{
		// See the matching comment in the describe block above - keeps these tests on the
		// real-popup path regardless of this headless browser's own default (narrow) viewport.
		sinon.stub(window, 'matchMedia').returns({matches: false} as MediaQueryList);

		egw = {
			preference: () => activeProfileID,
			link: sinon.spy((path : string, params : any) => path + '?' + new URLSearchParams(params).toString()),
			getOpenWindows: () => [],
			dataGetUIDdata: () => undefined,
			openPopup: sinon.spy(),
			urlParamsTooLong: () => false,
			openWithinWindow: sinon.spy(),
		};
		(<any>window).egw = egw;

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw, isMainWindow: true});
	}

	it('composeMessage(false, []) strips a legacy acc_id::folder preference down to a bare acc_id', () =>
	{
		setup('9::INBOX/006-IF-ORGA-LEITUNG');

		app.composeMessage(false, []);

		assert.isTrue(egw.link.calledOnce);
		const [, params] = egw.link.firstCall.args;
		assert.equal(params.acc_id, '9');
	});

	it('composeMessage(false, []) leaves an already-clean acc_id preference untouched', () =>
	{
		setup('9');

		app.composeMessage(false, []);

		const [, params] = egw.link.firstCall.args;
		assert.equal(params.acc_id, '9');
	});

	it('composeWithPreset() (addressbook\'s "Add to Bcc" etc.) also strips a legacy preference', () =>
	{
		setup('9::INBOX/006-IF-ORGA-LEITUNG');

		app.composeWithPreset({bcc: ['someone@example.org']});

		assert.isTrue(egw.link.calledOnce);
		const [, params] = egw.link.firstCall.args;
		assert.equal(params.acc_id, '9');
	});
});
