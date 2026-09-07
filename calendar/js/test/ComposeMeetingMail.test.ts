import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./CalendarAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {CalendarApp} from "../app";

/**
 * app.ts has to be imported through its explicit source path: a plain `import ... from "../app"`
 * resolves to calendar/js/app.js, a leftover tsc output nothing rebuilds any more (the build only
 * refreshes the app.min.js bundle), so the test would silently run against months-old code. The
 * specifier is kept in a variable so TypeScript treats it as a dynamic module (`import type` above
 * provides the types), while the dev-server transforms the .ts on the fly.
 */
const APP_SOURCE = '/calendar/js/app.ts';

/**
 * Regression coverage for CalendarApp.composeMeetingMail() - replaced the classic custom_mail(vars)/
 * menuaction-url mechanism (doc/ai/projects/mail-compose-jmap-migration.md, Step 10). The url-length
 * ("414 Request-URI Too Large", help.egroupware.org/t/78981) concern that mechanism used to guard
 * against directly has moved WITH the preset onto MailApp.composeWithPreset() instead - see
 * mail/js/test/ComposeWithPresetLongBody.test.ts for that half. This file only covers
 * composeMeetingMail()'s own, much smaller job: call calendar_uiforms::ajax_custom_mail() (which
 * computes the whole preset server-side - recipients, subject, body, the event's own .ics text),
 * then hand its result, unmodified, to MailApp.composeWithPreset().
 *
 * Setup: composeMeetingMail() only ever touches `this.egw` (for the ajax round-trip) and
 * `window.app.mail`, so the app object is a bare Object.create(CalendarApp.prototype) - no EgwApp
 * constructor, which would want a real framework, sidebox and etemplate.
 */
describe('CalendarApp.composeMeetingMail()', () =>
{
	let app : CalendarApp;
	let egw : any;
	let composeWithPresetSpy : sinon.SinonSpy;
	let CalendarAppClass : typeof CalendarApp;

	before(async function()
	{
		// Chromium can need more than the global 3s timeout to load calendar's complete widget tree
		this.timeout(10000);
		CalendarAppClass = (await import(APP_SOURCE)).CalendarApp;
	});

	beforeEach(() =>
	{
		composeWithPresetSpy = sinon.spy();
		(<any>window).app.mail = {composeWithPreset: composeWithPresetSpy};

		app = Object.create(CalendarAppClass.prototype);
		Object.assign(app, {appname: 'calendar'});
	});

	afterEach(() =>
	{
		delete (<any>window).app.mail;
	});

	it('hands ajax_custom_mail()\'s own preset, unmodified, to MailApp.composeWithPreset()', async() =>
	{
		const preset = {
			subject: 'Team meeting',
			body: 'Please join us',
			bodyMimeType: 'plain',
			bcc: ['A A <a@example.com>'],
			attachmentContents: [{name: 'event.ics', type: 'text/calendar', content: 'BEGIN:VCALENDAR...'}],
		};
		egw = {request: sinon.stub().resolves(preset)};
		app.egw = egw;

		const event = {id: 42, title: 'Team meeting'};
		await app.composeMeetingMail(event, false, false);

		assert.isTrue(egw.request.calledOnceWith('calendar.calendar_uiforms.ajax_custom_mail', [event, false, false]));
		assert.isTrue(composeWithPresetSpy.calledOnceWith(preset));
	});

	it('passes the added/asrequest flags through for a meeting request on a brand-new (unsaved) event', async() =>
	{
		egw = {request: sinon.stub().resolves({})};
		app.egw = egw;

		// no id yet - this also has to work for a not-yet-saved event's own "sendrequest" action
		const event = {title: 'New meeting'};
		await app.composeMeetingMail(event, true, true);

		assert.isTrue(egw.request.calledOnceWith('calendar.calendar_uiforms.ajax_custom_mail', [event, true, true]));
	});
});
