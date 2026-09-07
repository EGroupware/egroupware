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
 * dev-server transforms the .ts on the fly. MailApp itself is a named export too, but importing
 * it statically would trigger the whole transitive chain at module-evaluation time regardless.
 */
const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for MailApp.composeWithPreset()'s own GET-vs-POST split - the same class of
 * bug calendar/js/test/CustomMailLongBody.test.ts already covers for the classic menuaction path
 * (help.egroupware.org/t/78981, "414 Request-URI Too Large"): calendar's own meeting-invite
 * conversion (doc/ai/projects/mail-compose-jmap-migration.md, Step 10) moved the preset - and so
 * this exact GET-length concern - from that classic path onto composeWithPreset() instead, since
 * an event's own description can be a whole mail and the preset now also carries the full .ics
 * text.
 *
 * Setup: composeWithPreset()/composeWithPresetPost() only ever touch `this.egw`/the bare global
 * `egw` (same object here - real app code calls the bare global for urlParamsTooLong()/openPopup()/
 * open(), matching every other caller in this file), so the app object is a bare
 * Object.create(MailApp.prototype) - no EgwApp constructor, which would want a real framework,
 * sidebox and etemplate. `document.createElement('form')`/`form.submit()` run against the REAL top
 * -level test document (composeWithPresetPost() never goes through a captured popup window's own
 * document the way egw_open.ts's Open class does - this code always runs already inside the
 * window that's opening ANOTHER popup from itself), so HTMLFormElement.prototype.submit is stubbed
 * directly rather than via EgwOpenHarness's own iframe-scoped stub.
 *
 * Pass criteria: a mail-sized preset body must NOT reach openPopup() (that is the 414 risk), it
 * must be posted instead, with the preset surviving the switch to POST intact. A short preset must
 * keep taking the unchanged openPopup() path.
 */
const MAIL_SIZED_BODY = 'Sehr geehrte Damen und Herren, '.repeat(200);
const SHORT_BODY = 'Kurze Beschreibung';

function preset(body : string) : object
{
	return {
		subject: 'Team meeting',
		body,
		bodyMimeType: 'plain',
		bcc: ['A A <a@example.com>', 'B B <b@example.com>'],
		attachmentContents: [{name: 'event.ics', type: 'text/calendar', content: 'BEGIN:VCALENDAR...'}],
	};
}

describe('MailApp.composeWithPreset()', () =>
{
	let app : MailApp;
	let egw : any;
	let MailAppClass : typeof MailApp;
	let submitStub : sinon.SinonStub;

	before(async function()
	{
		// Chromium can need more than the global 3s timeout to load mail's complete widget tree
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		egw = {
			preference: () => '42',
			link: (path : string, params : any) => path + '?' + new URLSearchParams(params).toString(),
			// exact same formula egw_open.ts's own urlParamsLength()/urlParamsTooLong() use for a
			// single-key {preset: json} object, kept in sync deliberately rather than importing the
			// real Open module (a much heavier harness - see EgwOpenHarness.ts - for a single
			// arithmetic check this test only needs to trust, not re-verify).
			urlParamsTooLong: (extra : any) => ('preset=' + extra.preset + '&').length > 2083,
			openPopup: sinon.spy(),
			open: sinon.stub().resolves({name: 'compose__'}),
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

	it('posts a mail-sized preset body instead of opening it as a GET url', async() =>
	{
		app.composeWithPreset(preset(MAIL_SIZED_BODY));
		// composeWithPresetPost() is async (awaits egw.open()) - let it settle
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isFalse(egw.openPopup.called, 'a GET url of this length is what the webserver answers with 414');
		assert.isTrue(egw.open.calledOnce, 'a popup still has to be opened for the form to target');
		assert.isTrue(submitStub.calledOnce, 'preset has to be posted instead');

		const form = submitStub.firstCall.thisValue as HTMLFormElement;
		assert.equal(form.method, 'post');
		assert.include(form.action, '/mail/compose.php');
		assert.equal(form.target, 'compose__');
		const posted = JSON.parse((form.elements.namedItem('preset') as HTMLInputElement).value);
		assert.equal(posted.body, MAIL_SIZED_BODY, 'body must be posted in full, not truncated');
		assert.equal(posted.subject, 'Team meeting');
		assert.equal(posted.attachmentContents[0].name, 'event.ics', 'the ics attachment must survive the switch to POST');
	});

	it('keeps opening a short preset as a popup url, unchanged', async() =>
	{
		app.composeWithPreset(preset(SHORT_BODY));
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isFalse(submitStub.called, 'nothing to post for a preset of harmless length');
		assert.isTrue(egw.openPopup.calledOnce);
		const [url] = egw.openPopup.firstCall.args;
		assert.include(url, '/mail/compose.php');
		assert.include(decodeURIComponent(url).replace(/\+/g, ' '), SHORT_BODY, 'the short body itself must still be in the GET url');
	});
});
