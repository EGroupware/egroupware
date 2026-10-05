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
			// A single url-encoded openPopup() now covers both the real GET-path navigation
			// (non-empty url, fire-and-forget - the window itself does the rest) AND the
			// POST-fallback's own "give me a blank, named popup" call (empty url, _returnID=true) -
			// ticket #125621's fix deliberately moved that off egw.open(), which resolves the
			// mail/add app-registry entry into a REAL url and navigates there as a side effect,
			// racing the form POST below for control of the same window.
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

	it('posts a mail-sized preset body instead of opening it as a GET url', async() =>
	{
		app.composeWithPreset(preset(MAIL_SIZED_BODY));
		// composeWithPresetPost() is async (awaits egw.openPopup()) - let it settle
		await new Promise(resolve => setTimeout(resolve, 0));

		// a single openPopup() call reserves a blank, named popup for the form below to POST into -
		// its OWN url must be empty (ticket #125621: NOT a real url/menuaction resolved via
		// egw.open(), which would navigate the popup there as a side effect and race the form POST
		// for control of the window) - a GET url of the real preset's length is what the webserver
		// would otherwise answer with 414
		assert.isTrue(egw.openPopup.calledOnce);
		assert.strictEqual(egw.openPopup.firstCall.args[0], '', 'must open genuinely blank, not navigate anywhere itself');
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

	/**
	 * Regression coverage for ApiHandler.php's REST `replyEml` case (found live 2026-10-05: "REST
	 * mail-compose doesn't fill recipients"): replying to an imported eml needs BOTH the classic
	 * from/id pair (reply_id/from, previously only ever passed via the old, broken egw.open() push)
	 * AND a to/cc/bcc/subject/... preset in the SAME popup - composeWithPreset() now accepts both
	 * optional trailing params instead of hardcoding from/id to '', so a single call covers it.
	 */
	it('passes an explicit from/id pair through to the popup url alongside the preset', async() =>
	{
		app.composeWithPreset(preset(SHORT_BODY), 'reply', '42:INBOX:123');
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isTrue(egw.openPopup.calledOnce);
		const [url] = egw.openPopup.firstCall.args;
		const params = new URLSearchParams(url.split('?')[1]);
		assert.equal(params.get('from'), 'reply');
		assert.equal(params.get('id'), '42:INBOX:123');
		assert.equal(JSON.parse(params.get('preset')).subject, 'Team meeting', 'the preset itself must still be carried too');
	});

	it('defaults from/id to empty strings when not given, same as before this param existed', async() =>
	{
		app.composeWithPreset(preset(SHORT_BODY));
		await new Promise(resolve => setTimeout(resolve, 0));

		const [url] = egw.openPopup.firstCall.args;
		const params = new URLSearchParams(url.split('?')[1]);
		assert.equal(params.get('from'), '');
		assert.equal(params.get('id'), '');
	});

	it('also carries from/id through the POST fallback for a too-long preset', async() =>
	{
		app.composeWithPreset(preset(MAIL_SIZED_BODY), 'reply', '42:INBOX:123');
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isTrue(submitStub.calledOnce);
		const form = submitStub.firstCall.thisValue as HTMLFormElement;
		const params = new URLSearchParams(form.action.split('?')[1]);
		assert.equal(params.get('from'), 'reply');
		assert.equal(params.get('id'), '42:INBOX:123');
	});

	/**
	 * Regression coverage for ApiHandler.php's REST compose endpoint (found live 2026-10-05, ralf:
	 * "the REST API should NOT use the preference about which identity to use, but the specified
	 * identity ... as documented") - a REST caller's `ident_id` (explicit or defaulted to the
	 * first) belongs to a specific account, which must be composed from regardless of whatever
	 * account this user's own desktop session happens to have active right now. `egw.preference`
	 * is stubbed to always return account '42' above - these tests prove an explicit account
	 * overrides that, and that omitting it still falls back to the preference unchanged.
	 */
	it('uses an explicitly given account instead of the ActiveProfileID preference', async() =>
	{
		app.composeWithPreset(preset(SHORT_BODY), '', '', '7');
		await new Promise(resolve => setTimeout(resolve, 0));

		const [url] = egw.openPopup.firstCall.args;
		const params = new URLSearchParams(url.split('?')[1]);
		assert.equal(params.get('acc_id'), '7', "the explicitly given account must win, not the user's own ActiveProfileID (42)");
	});

	it('falls back to the ActiveProfileID preference when no explicit account is given', async() =>
	{
		app.composeWithPreset(preset(SHORT_BODY));
		await new Promise(resolve => setTimeout(resolve, 0));

		const [url] = egw.openPopup.firstCall.args;
		const params = new URLSearchParams(url.split('?')[1]);
		assert.equal(params.get('acc_id'), '42', 'every non-REST caller has no specified identity, so the preference is correct here');
	});

	it('carries an explicit account through the POST fallback too', async() =>
	{
		app.composeWithPreset(preset(MAIL_SIZED_BODY), '', '', '7');
		await new Promise(resolve => setTimeout(resolve, 0));

		const form = submitStub.firstCall.thisValue as HTMLFormElement;
		const params = new URLSearchParams(form.action.split('?')[1]);
		assert.equal(params.get('acc_id'), '7');
	});
});
