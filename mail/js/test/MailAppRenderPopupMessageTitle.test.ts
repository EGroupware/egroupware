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
 * Regression coverage: the mail display/print popup's window title (and so the printed page's
 * title, and the browser tab's) stayed stuck on blank/stale instead of the message's subject.
 *
 * Root cause: et2_ready()'s own super.et2_ready() call (base egw_app.ts) already runs
 * _set_Window_title() for every popup - but for 'mail.display' that happens BEFORE display()/
 * renderPopupMessage() ever gets to actually populate the subject widget
 * (#mail-display_mailDisplayDetails_subject), which getWindowTitle() reads from - so the title it
 * computes at that point is always empty. Nothing re-ran _set_Window_title() once the real data
 * arrived. The exact same gap was already found and fixed for compose popups (see compose.ts's own
 * bootstrapReply()/etc., ending in a `this.app._set_Window_title()` call) but was never ported to
 * display/print.
 *
 * renderPopupMessage() has two paths depending on whether the opener window/its cached row data is
 * still reachable - both now call _set_Window_title() once renderMessageInto() has actually filled
 * the subject widget.
 */
describe("MailApp.renderPopupMessage() - window title refreshed once the subject is actually known", () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;
	let template : any;
	let setWindowTitle : sinon.SinonSpy;
	let renderMessageInto : sinon.SinonStub;
	let registerForDrag : sinon.SinonSpy;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		template = {};
		setWindowTitle = sinon.spy();
		renderMessageInto = sinon.stub().returns({attachmentsBlock : 'some-block'});
		registerForDrag = sinon.spy();

		// app.ts's ajax-fallback branch calls the bare global `egw.dataStoreUID(...)` (not
		// `this.egw...`) - same object as app.egw below, like MailAppDisplayPartEnvelope.test.ts
		(<any>window).egw = {preference : () => '', lang : (s : string) => s, debug : () => {}, dataStoreUID : sinon.spy()};

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {
			appname : 'mail',
			egw : (<any>window).egw,
			renderMessageInto,
			registerForDrag,
			_set_Window_title : setWindowTitle,
		});
	});

	afterEach(() =>
	{
		// a per-test (<any>window).opener override must not leak into unrelated later tests
		delete (<any>window).opener;
	});

	it("refreshes the title once renderMessageInto() fills it in, when the opener's cached row data is available", () =>
	{
		(<any>window).opener = {
			closed : false,
			egw : {dataGetUIDdata : (_rowId : string) => ({data : {subject : 'Real subject'}})},
		};

		(<any>app).renderPopupMessage(template, 'mail::5::42::b:1', undefined);

		assert.isTrue(renderMessageInto.calledOnceWith(template, 'mail::5::42::b:1', {subject : 'Real subject'}, undefined));
		assert.isTrue(registerForDrag.calledOnceWith('mail::5::42::b:1', 'some-block'));
		assert.isTrue(setWindowTitle.calledOnce,
			"the title has to be refreshed synchronously once the opener-cached data is actually rendered");
	});

	it("refreshes the title once the ajax fallback resolves, when no opener data is available", async() =>
	{
		// no window.opener at all - a bookmarked/direct link, or the opener was closed
		const request : any = Promise.resolve({uid : 'mail::5::42::b:1', subject : 'Fetched subject'});
		request.abort = sinon.spy();
		(<any>app).egw.request = sinon.stub().returns(request);

		(<any>app).renderPopupMessage(template, 'mail::5::42::b:1', undefined);

		assert.isFalse(setWindowTitle.called, "must not fire before the ajax fallback has actually resolved");

		await request;
		// let the .then() callback's own synchronous body finish running
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isTrue(renderMessageInto.calledOnceWith(template, 'mail::5::42::b:1',
			{uid : 'mail::5::42::b:1', subject : 'Fetched subject'}, undefined));
		assert.isTrue(setWindowTitle.calledOnce);
	});

	it("does not refresh the title when the ajax fallback resolves with nothing to render", async() =>
	{
		const request : any = Promise.resolve(null);
		request.abort = sinon.spy();
		(<any>app).egw.request = sinon.stub().returns(request);

		(<any>app).renderPopupMessage(template, 'mail::5::42::b:1', undefined);

		await request;
		await new Promise(resolve => setTimeout(resolve, 0));

		assert.isFalse(renderMessageInto.called);
		assert.isFalse(setWindowTitle.called);
	});
});
