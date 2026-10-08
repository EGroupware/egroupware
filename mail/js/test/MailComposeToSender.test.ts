import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {Et2UrlEmail} from "../../../api/js/etemplate/Et2Url/Et2UrlEmail";
import type {MailApp} from "../app";

/**
 * app.ts has to be loaded through its explicit source path - see ComposeMessageAccId.test.ts's
 * own comment on APP_SOURCE for why a plain `import ... from "../app"` doesn't work here.
 */
const APP_SOURCE = '/mail/js/app.ts';

const REAL_ROW_ID = 'mail::502::18::SU5CT1g=::574501';

/**
 * Ticket #126151: a mail list row's own sender/address chip (et2-url-email) used to always
 * mailto:-open a compose on a plain click/tap - "You cannot teach users to tap only in specific
 * places", so a row click/tap must only open the message for reading. The row templates now set
 * disableClickAction="true" to suppress that widget default (see
 * Et2UrlEmailReadonly.transformAttributes() and its own test coverage for why a plain
 * onclick="..." XET attribute does NOT reliably work for a nextmatch row widget); "compose a new
 * mail to the sender" moved to a new context-menu action, composeToSender(), covered here.
 */
describe('MailApp composeToSender() (ticket #126151)', () =>
{
	let app : MailApp;
	let egw : any;
	let MailAppClass : typeof MailApp;
	let actionStub : sinon.SinonStub;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		actionStub = sinon.stub(Et2UrlEmail, 'action');
		egw = {
			dataGetUIDdata: sinon.stub().returns(undefined),
		};
		(<any>window).egw = egw;

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw, isMainWindow: true, currentlyFocussed: ''});
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	it('composeToSender() opens a blank compose to the row\'s fromaddress, not egw.dataGetUIDdata()\'s own address field', () =>
	{
		egw.dataGetUIDdata.withArgs(REAL_ROW_ID).returns({
			data: {address: 'Wrong Field <wrong@example.org>', fromaddress: 'Jane Doe <jane@example.org>'}
		});

		app.composeToSender({id: 'composetosender'}, [{id: REAL_ROW_ID}]);

		assert.isTrue(actionStub.calledOnceWith('Jane Doe <jane@example.org>'));
	});

	it('composeToSender() falls back to the currently-focussed row when invoked with no explicit selection', () =>
	{
		app.currentlyFocussed = REAL_ROW_ID;
		egw.dataGetUIDdata.withArgs(REAL_ROW_ID).returns({data: {fromaddress: 'jane@example.org'}});

		app.composeToSender({id: 'composetosender'}, []);

		assert.isTrue(actionStub.calledOnceWith('jane@example.org'));
	});

	it('composeToSender() does nothing when the row has no known fromaddress', () =>
	{
		egw.dataGetUIDdata.withArgs(REAL_ROW_ID).returns({data: {}});

		app.composeToSender({id: 'composetosender'}, [{id: REAL_ROW_ID}]);

		assert.isFalse(actionStub.called);
	});
});
