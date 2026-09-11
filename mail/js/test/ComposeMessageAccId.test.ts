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
