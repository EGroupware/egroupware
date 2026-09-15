import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Coverage for tryJmapMove()/tryJmapCopy()'s success-message side effect (showMoveOrCopyMessage())
 * - tracker report (ralf, 2026-09-15): "beim Verschieben von E-Mails in andere Ordner erscheint
 * rechts unten nicht mehr der Hinweis wohin es verschoben wurde" (moving mail no longer shows the
 * bottom-right hint of where it went). Root cause: the classic server call
 * (MessageActionHandler::copyMessages()) has always shown its own success message, embedded in
 * its JSON response - but the fast client-side JMAP move/copy path (tryJmapMove()/tryJmapCopy())
 * resolves silently, skipping it entirely for what's now the common case.
 *
 * Two things matter here: the message actually gets shown for the JMAP path, AND it must NOT be
 * shown a second time when JMAP fails and the classic fallback (which shows its own) runs instead.
 */

const ROW_ID = 'mail::1::42::SU5CT1g=::7';

function createMailApp(folderTreeValue : string)
{
	const messages : {text : string, type : string}[] = [];
	const langCalls : any[][] = [];

	const app = Object.create(MailApp.prototype) as MailApp;
	// class field default (nm_index = 'nm') only runs via the real constructor - Object.create()
	// bypasses it, and buildJmapQuery() (moveAllMatching()'s caller) needs it to find the same
	// 'nm[foldertree]' widget showMoveOrCopyMessage() looks up below
	(app as any).nm_index = 'nm';

	Object.defineProperty(app, 'egw', {value: {
		lang : (...args : any[]) => { langCalls.push(args); return args.join('|'); },
		message : (text : string, type : string) => void messages.push({text, type}),
	}});
	Object.defineProperty(app, 'et2', {value: {
		getWidgetById : (id : string) => id === 'nm[foldertree]' ? {getValue : () => folderTreeValue} : null,
	}});

	return {app, messages, langCalls};
}

function stubJmap(app : MailApp, overrides : any)
{
	Object.defineProperty(app, 'jmap', {value: {
		expandThreadRowIds : (ids : string[]) => Promise.resolve(ids),
		messageReference : (id : string) => ({profileID : '42', mailboxId : 'INBOX', emailId : id}),
		moveMessages : () => Promise.resolve(),
		moveAllMatching : () => Promise.resolve(),
		copyMessages : () => Promise.resolve(),
		copyAllMatching : () => Promise.resolve(),
		...overrides,
	}});
}

function tryJmapMove(app : MailApp, target : string, messages : any, classicMove : () => Promise<any>) : Promise<any>
{
	return (app as any).tryJmapMove(target, messages, false, classicMove);
}

function tryJmapCopy(app : MailApp, target : string, messages : any, classicCopy : () => Promise<any>) : Promise<any>
{
	return (app as any).tryJmapCopy(target, messages, classicCopy);
}

describe("MailApp move/copy success message (tracker: missing hint after moving mail)", () =>
{
	it("shows the same 'moved %1 message(s) from %2 to %3' phrase the classic path always used, once the fast JMAP move actually succeeds", async() =>
	{
		const {app, messages} = createMailApp('42::INBOX');
		stubJmap(app, {});

		await tryJmapMove(app, '42::Archive', {msg : [ROW_ID], all : false}, () => Promise.reject('classic should not run'));

		assert.equal(messages.length, 1);
		assert.equal(messages[0].type, 'success');
		assert.include(messages[0].text, 'moved %1 message(s) from %2 to %3');
	});

	it("shows the copy phrase (not the move one) for tryJmapCopy()", async() =>
	{
		const {app, messages} = createMailApp('42::INBOX');
		stubJmap(app, {});

		await tryJmapCopy(app, '42::Archive', {msg : [ROW_ID], all : false}, () => Promise.reject('classic should not run'));

		assert.equal(messages.length, 1);
		assert.include(messages[0].text, 'copied %1 message(s) from %2 to %3');
	});

	it("also shows the message for the 'select all matching' case (moveAllMatching)", async() =>
	{
		const {app, messages} = createMailApp('42::INBOX');
		stubJmap(app, {});

		await tryJmapMove(app, '42::Archive', {all : true}, () => Promise.reject('classic should not run'));

		assert.equal(messages.length, 1);
		assert.equal(messages[0].type, 'success');
	});

	it("does NOT show a message when the JMAP move fails and falls back to the classic call - the classic response already shows its own", async() =>
	{
		const {app, messages} = createMailApp('42::INBOX');
		stubJmap(app, {moveMessages : () => Promise.reject(new Error('jmap down'))});
		let classicCalled = false;

		const result = await tryJmapMove(app, '42::Archive', {msg : [ROW_ID], all : false}, () =>
		{
			classicCalled = true;
			return Promise.resolve('classic result');
		});

		assert.isTrue(classicCalled, "classic fallback must still run on JMAP failure");
		assert.equal(result, 'classic result');
		assert.equal(messages.length, 0,
			"showMoveOrCopyMessage() must not fire for a classic-fallback resolution - that response already carries its own message");
	});

	it("passes the current folder (from nm[foldertree]) as the source, and the move target as the destination", async() =>
	{
		const {app, langCalls} = createMailApp('42::Posteingang/Archives');
		stubJmap(app, {});

		await tryJmapMove(app, '42::Some/Target', {msg : [ROW_ID], all : false}, () => Promise.reject('classic should not run'));

		const phraseCall = langCalls.find(args => args[0] === 'moved %1 message(s) from %2 to %3');
		assert.exists(phraseCall);
		assert.equal(phraseCall[1], 1, "one explicit message selected");
		// args[2]/args[3] are themselves the result of nested egw.lang(folderPath) calls (the stub
		// just joins its own args with '|'), so check the RAW folder paths were looked up at all
		assert.isTrue(langCalls.some(args => args[0] === 'Posteingang/Archives'));
		assert.isTrue(langCalls.some(args => args[0] === 'Some/Target'));
	});
});
