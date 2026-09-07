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
 * dev-server transforms the .ts on the fly. EgwApp._mergeEmail() is tested via a MailApp instance
 * since MailApp is the only concrete EgwApp subclass this test infra already knows how to load -
 * the method itself is app-agnostic (addressbook's own single-contact "insert into email
 * document" action is the real caller).
 */
const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for EgwApp._mergeEmail()'s single-recipient branch (doc/ai/projects/
 * mail-compose-jmap-migration.md, Step 10 - the 3rd/last classic-postback caller of
 * mail_compose::compose() found auditing its remaining callers, 2026-09-07).
 *
 * Before: a single recipient went through `egw.open(id, 'mail', 'edit', {from:'merge', ...})`,
 * a classic full-page postback resolving through mail's own Link registry to
 * mail_compose::compose(). Now: the merge itself still happens server-side
 * (mail.mail_compose.ajax_mergeSingle - merges into a NEW draft, does not send), but opening the
 * result is the same client-side-only "reopen a draft" MailApp.composeMessage() already does for
 * any other draft - no classic postback render at all.
 *
 * Setup: _mergeEmail() only ever touches `this.egw` and the global `window.app.mail`, so the app
 * object is a bare Object.create(MailApp.prototype) - no EgwApp constructor, which would want a
 * real framework, sidebox and etemplate (same pattern ComposeMessageAccId.test.ts already uses).
 */
describe('EgwApp._mergeEmail() single recipient', () =>
{
	let app : MailApp;
	let egw : any;
	let MailAppClass : typeof MailApp;
	let composeMessage : sinon.SinonSpy;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		egw = {
			link_get_registry: () => undefined,
			lang: (msg : string, ...args : any[]) => msg.replace('%1', args[0]),
			request: sinon.stub(),
			message: sinon.spy(),
		};
		(<any>window).egw = egw;

		composeMessage = sinon.spy();
		(<any>window).app = {mail: {composeMessage}};

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw, isMainWindow: true});
	});

	/** let egw.request()'s .then()/.catch() chain inside _mergeEmail() settle */
	async function mergeSingle(id : number, document : string, merge : string) : Promise<void>
	{
		(<any>app)._mergeEmail(null, {}, {id: [id], document, merge});
		await new Promise(resolve => setTimeout(resolve, 0));
	}

	it('merges into a draft then reopens it client-side, without a classic postback', async() =>
	{
		egw.request.resolves({id: 'mail::5::1::RHJhZnRz::abc123'});

		await mergeSingle(46, '/home/ralf/Serienbrief.eml', 'EGroupware\\Api\\Contacts\\Merge');

		assert.isTrue(egw.request.calledOnceWith('mail.mail_compose.ajax_mergeSingle',
			[46, '/home/ralf/Serienbrief.eml', 'EGroupware\\Api\\Contacts\\Merge']));
		assert.isTrue(composeMessage.calledOnceWith(
			{id: 'composefromdraft'}, [{id: 'mail::5::1::RHJhZnRz::abc123'}]));
		assert.isFalse(egw.message.called);
	});

	it('shows the server-side error message instead of opening compose, if the merge fails', async() =>
	{
		egw.request.resolves({msg: "Document 'x' does not exist or is not readable for you!"});

		await mergeSingle(46, '/home/ralf/missing.eml', '');

		assert.isTrue(egw.message.calledOnceWith("Document 'x' does not exist or is not readable for you!", 'error'));
		assert.isFalse(composeMessage.called);
	});

	it('shows an error message if the request itself rejects', async() =>
	{
		egw.request.rejects(new Error('network error'));

		await mergeSingle(46, '/home/ralf/Serienbrief.eml', '');

		assert.isTrue(egw.message.calledOnceWith('network error', 'error'));
		assert.isFalse(composeMessage.called);
	});
});
