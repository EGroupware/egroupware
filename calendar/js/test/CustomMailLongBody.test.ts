import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./CalendarAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {CalendarApp} from "../app";
import {createEgwOpenEnv, EgwOpenEnv} from "../../../api/js/jsapi/test/EgwOpenHarness";

/**
 * app.ts has to be imported through its explicit source path: a plain `import ... from "../app"`
 * resolves to calendar/js/app.js, a leftover tsc output nothing rebuilds any more (the build only
 * refreshes the app.min.js bundle), so the test would silently run against months-old code. The
 * specifier is kept in a variable so TypeScript treats it as a dynamic module (`import type` above
 * provides the types), while the dev-server transforms the .ts on the fly.
 */
const APP_SOURCE = '/calendar/js/app.ts';

/**
 * Regression coverage for "Kalender: Termin aus Mail -> Mail an Teilnehmer -> 414 Request-URI
 * Too Large" (help.egroupware.org/t/78981).
 *
 * An event created from a mail gets that whole mail as its description, and
 * calendar_uiforms::ajax_custom_mail() presets it as the compose body, then hands the compose
 * parameters to app.calendar.custom_mail(). Opening those as a GET url made the webserver answer
 * with "414 Request-URI Too Large" as soon as the originating mail was of any real length
 * (nginx/Apache cap the request line at 4k), so the compose popup came up empty with an error.
 *
 * Setup: custom_mail() only ever touches `this.egw`, so the app object is a bare
 * Object.create(CalendarApp.prototype) - no EgwApp constructor, which would want a real framework,
 * sidebox and etemplate. The `egw` it gets is the REAL open module from EgwOpenHarness (not a
 * fake), so the branch, the length threshold and the form it posts are all the production code;
 * only window.open() and form.submit() are stubbed out by that harness.
 *
 * Pass criteria: a mail-sized preset body must NOT reach open_link() (that is the 414), it must be
 * posted instead, with the participant list still arriving as separate recipients. A short body
 * must keep taking the unchanged GET path.
 */

/** What ajax_custom_mail() sends, shortened to the parts custom_mail() has to carry */
function composeVars(body : string) : object
{
	return {
		menuaction: 'mail.mail_compose.compose',
		mimeType: 'html',
		'preset[subject]': 'Team meeting',
		'preset[body]': body,
		'preset[bcc]': ['A A <a@example.com>', 'B B <b@example.com>'],
		'preset[name]': 'event.ics',
		'preset[file]': '/tmp/ics4711',
		'preset[type]': 'text/calendar',
		'preset[size]': 1234
	};
}

/** A mail body of the size that used to break: <pre>-wrapped description of a real mail */
const MAIL_SIZED_BODY = '<pre>' + 'Sehr geehrte Damen und Herren, '.repeat(200) + '</pre>';
const SHORT_BODY = '<pre>Kurze Beschreibung</pre>';

describe('CalendarApp.custom_mail()', () =>
{
	let env : EgwOpenEnv;
	let app : CalendarApp;
	let egw : any;
	let CalendarAppClass : typeof CalendarApp;

	before(async() =>
	{
		CalendarAppClass = (await import(APP_SOURCE)).CalendarApp;
	});

	beforeEach(async() =>
	{
		env = await createEgwOpenEnv();
		egw = env.egw();
		// the (blank) compose popup openComposePost() posts its form into - the harness'
		// window.open stub returns undefined, and the form needs a target window name
		sinon.stub(egw, 'open').returns({name: 'compose__'});

		app = Object.create(CalendarAppClass.prototype);
		Object.assign(app, {appname: 'calendar', egw: egw});
	});

	afterEach(() =>
	{
		env.destroy();
	});

	it('posts a mail-sized preset body instead of opening it as a GET url', () =>
	{
		const open_link = sinon.spy(egw, 'open_link');
		const vars = composeVars(MAIL_SIZED_BODY);

		app.custom_mail(vars);

		assert.isFalse(open_link.called, 'a GET url of this length is what the webserver answers with 414');
		assert.equal(env.formSubmits.length, 1, 'compose parameters have to be posted instead');

		const submit = env.formSubmits[0];
		assert.equal(submit.method, 'post');
		assert.equal(submit.action, 'index.php?menuaction=mail.mail_compose.compose');
		const params = new Map(submit.params);
		assert.equal(params.get('preset[body]'), MAIL_SIZED_BODY, 'body must be posted in full, not truncated');
		assert.equal(params.get('preset[subject]'), 'Team meeting');
		// the ics attachment is passed by temp-file path and must survive the switch to POST
		assert.equal(params.get('preset[file]'), '/tmp/ics4711');
		assert.equal(params.get('preset[type]'), 'text/calendar');
	});

	it('posts each participant as its own recipient input, so they do not arrive as one bogus address', () =>
	{
		app.custom_mail(composeVars(MAIL_SIZED_BODY));

		assert.deepEqual(env.formSubmits[0].params.filter(([name]) => name === 'preset[bcc][]'), [
			['preset[bcc][]', 'A A <a@example.com>'],
			['preset[bcc][]', 'B B <b@example.com>']
		]);
	});

	it('keeps opening a short body as a popup url, unchanged', () =>
	{
		const open_link = sinon.stub(egw, 'open_link');
		const vars = composeVars(SHORT_BODY);

		app.custom_mail(vars);

		assert.equal(env.formSubmits.length, 0, 'nothing to post for a url of harmless length');
		assert.isTrue(open_link.calledOnce);
		const [url, target, popup] = open_link.firstCall.args;
		assert.include(url, 'menuaction=mail.mail_compose.compose');
		assert.equal(target, '_blank');
		assert.equal(popup, '700x700');
	});
});
