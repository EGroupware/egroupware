import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

// see ComposeMessageAccId.test.ts for why app.ts is loaded through its explicit source path
const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for setCompose() reusing an already-open compose popup with a mailto:
 * link's content (egw_open.ts's mailto(), ticket #125211): the generic per-field widget lookup
 * (`getWidgetById(field)`) has no way to handle 'body' - the compose body's real widget id is
 * 'mail_htmltext', not 'body' - so it threw, got silently swallowed by setCompose()'s own
 * try/catch, and dropped. 'subject' needs no special case (compose.xet's textbox really is
 * id="subject"); see EgwOpen.test.ts for mailto()'s own parsing/decoding coverage.
 *
 * Setup: same bare Object.create(MailApp.prototype) + hand-built widget/window stubs
 * MailAppSetComposeVfsFiles.test.ts already established for setCompose().
 */
describe('MailApp.setCompose() with mailto: subject/body', () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;
	let bodyWidget : {getValue : sinon.SinonStub, set_value : sinon.SinonSpy};
	let subjectWidget : {getValue : sinon.SinonStub, set_value : sinon.SinonSpy};
	let composeWindow : any;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		(<any>window).egw = {preference: () => '', lang: (s : string) => s, debug: () => {}};
		bodyWidget = {getValue: sinon.stub().returns(''), set_value: sinon.spy()};
		subjectWidget = {getValue: sinon.stub().returns(''), set_value: sinon.spy()};
		const widgets : any = {mail_htmltext: bodyWidget, subject: subjectWidget};
		composeWindow = {
			closed: false,
			etemplate2: {getByApplication: () => [{
				widgetContainer: {getWidgetById: (id : string) => widgets[id]},
			}]},
		};

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw: (<any>window).egw});
	});

	it('routes a plain-text body to the mail_htmltext widget, HTML-escaped with <br/> for newlines', () =>
	{
		const result = app.setCompose(composeWindow, {
			subject: 'Hello There', body: 'Line one\nLine <two>', bodyMimeType: 'plain',
		});

		assert.isTrue(result);
		assert.isTrue(subjectWidget.set_value.calledOnceWith('Hello There'));
		assert.isTrue(bodyWidget.set_value.calledOnce);
		assert.equal(bodyWidget.set_value.firstCall.args[0], 'Line one<br/>Line &lt;two&gt;');
	});

	it('prepends to, rather than replacing, an already-non-empty body', () =>
	{
		bodyWidget.getValue.returns('<p>Existing signature</p>');

		app.setCompose(composeWindow, {body: 'New text', bodyMimeType: 'plain'});

		assert.equal(bodyWidget.set_value.firstCall.args[0], 'New text<p>Existing signature</p>');
	});

	it('passes an HTML body through unescaped', () =>
	{
		app.setCompose(composeWindow, {body: '<p>Already HTML</p>', bodyMimeType: 'html'});

		assert.equal(bodyWidget.set_value.firstCall.args[0], '<p>Already HTML</p>');
	});

	it('never looks up a widget literally called "body" or "bodyMimeType"', () =>
	{
		const widgets : any = {mail_htmltext: bodyWidget, subject: subjectWidget};
		const getWidgetById = sinon.spy((id : string) => widgets[id]);
		composeWindow.etemplate2.getByApplication = () => [{widgetContainer: {getWidgetById}}];

		app.setCompose(composeWindow, {body: 'text', bodyMimeType: 'plain'});

		assert.isFalse(getWidgetById.calledWith('body'));
		assert.isFalse(getWidgetById.calledWith('bodyMimeType'));
	});
});
