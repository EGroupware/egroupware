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
 * Regression coverage for MailApp.display()'s message/rfc822 sub-part envelope override (ticket
 * found live 2026-09-25, ralf: a forward-as-attachment popup's From/To/Subject/Date showed HIS OWN
 * envelope instead of the ATTACHED message's own - renderPopupMessage() only ever knows the
 * CONTAINING row, see its own docblock). Only applies when content.part is set (a message/rfc822
 * sub-part is being viewed) and the account is a local-shim one (mail/src/Ui/
 * MessageDisplayHandler::fetchMessagePartEnvelope()'s own docblock explains why real-JMAP accounts
 * are deliberately left alone here).
 *
 * Setup: display() touches a lot (toolbar actions, renderPopupMessage(), loadMessageBody()) that
 * isn't this override's concern - renderPopupMessage()/loadMessageBody() are stubbed out on the
 * instance (same "shadow the prototype method" trick already used elsewhere in this test suite),
 * so only the new envelope-fetch/override logic actually runs.
 */
describe('MailApp.display() - message/rfc822 sub-part envelope override', () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;
	let details : {set_value : sinon.SinonSpy};
	let toolbar : any;
	let isLocalAccount : sinon.SinonStub;
	let request : sinon.SinonStub;
	let cachedRowData : any;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		details = {set_value : sinon.spy()};
		toolbar = {actions : undefined};
		cachedRowData = {uid : 'mail::5::42::b:1', subject : 'Old subject', fromaddress : ['Ralf <ralf@egroupware.org>']};
		isLocalAccount = sinon.stub().resolves(true);
		request = sinon.stub().resolves({
			subject : 'Real subject from GitHub', from : '"GitHub" <notifications@github.com>',
			to : 'someone@example.com', cc : '', bcc : '', date : 1758802736,
		});

		(<any>window).egw = {
			preference : () => '', lang : (s : string) => s, debug : () => {},
			dataGetUIDdata : (_uid : string) => ({data : cachedRowData}),
		};

		const widgets : any = {displayToolbar : toolbar, mailDisplayDetails : details, mailDisplayBodySrc : {iframe : {}}};
		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname : 'mail',
			egw : (<any>window).egw,
			et2 : {
				getArrayMgr : (_name : string) => ({data : {mail_id : 'mail::5::42::b:1', part : '2'}}),
				getWidgetById : (id : string) => widgets[id],
			},
			wireLabelFlagDropdowns : sinon.spy(),
			renderPopupMessage : sinon.spy(),
			loadMessageBody : sinon.spy(),
		});
		// `jmap` is a getter-only accessor on the real MailApp.prototype (lazily constructs the real
		// MailJmap) - shadow it with an own data property instead of a plain assignment, which would
		// throw "setting getter-only property" under strict mode.
		Object.defineProperty(app, 'jmap', {
			value : {isLocalAccount, messageReference : (_rowId : string) => ({profileID : '42'})},
			writable : true, configurable : true,
		});
		(<any>app).egw.request = request;
	});

	it("overrides subject/date/from/to/cc/bcc from the attached message's own envelope for a local account", async() =>
	{
		app.display();
		// isLocalAccount()/egw.request() are both async - let their promise chains settle
		await new Promise((resolve) => setTimeout(resolve, 0));
		await new Promise((resolve) => setTimeout(resolve, 0));

		assert.isTrue(isLocalAccount.calledOnceWith('42'));
		assert.isTrue(request.calledOnceWith('mail.EGroupware\\Mail\\Ui.ajax_fetchMessagePartEnvelope', ['mail::5::42::b:1', '2']));
		assert.isTrue(details.set_value.calledOnce);
		const rendered = details.set_value.firstCall.args[0].content;
		assert.equal(rendered.subject, 'Real subject from GitHub');
		assert.equal(rendered.date, 1758802736);
		assert.equal(rendered.fromaddress, '"GitHub" <notifications@github.com>');
		assert.equal(rendered.additionalfromaddress, '"GitHub" <notifications@github.com>');
		assert.equal(rendered.toaddress, 'someone@example.com');
		// the containing row's own cached fields not touched by the override survive untouched
		assert.equal(rendered.uid, 'mail::5::42::b:1');
	});

	it('never fetches the envelope for a real JMAP (non-local) account', async() =>
	{
		isLocalAccount.resolves(false);

		app.display();
		await new Promise((resolve) => setTimeout(resolve, 0));
		await new Promise((resolve) => setTimeout(resolve, 0));

		assert.isTrue(isLocalAccount.calledOnce);
		assert.isFalse(request.called);
		assert.isFalse(details.set_value.called);
	});

	it('never even checks account locality when content.part is not set (a normal top-level message)', async() =>
	{
		(<any>app).et2.getArrayMgr = (_name : string) => ({data : {mail_id : 'mail::5::42::b:1'}});

		app.display();
		await new Promise((resolve) => setTimeout(resolve, 0));

		assert.isFalse(isLocalAccount.called);
		assert.isFalse(request.called);
	});

	it('leaves the rendered row alone when the server has nothing to resolve the envelope to', async() =>
	{
		request.resolves(null);

		app.display();
		await new Promise((resolve) => setTimeout(resolve, 0));
		await new Promise((resolve) => setTimeout(resolve, 0));

		assert.isFalse(details.set_value.called);
	});
});
