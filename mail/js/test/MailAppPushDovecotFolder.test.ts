import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * help.egroupware.org "Mail Push funktioniert nur teilweise": Dovecot's push names the mailbox with the server's own
 * hierarchy delimiter ("INBOX.Einkauf"), the folder-tree uses "/"-joined paths ("INBOX/Einkauf"), so the unseen counter
 * of folders below INBOX was never updated, while INBOX and top-level folders worked.
 */
function createMailApp(tree : Record<string, boolean>)
{
	const setFolderStatus = sinon.spy();
	const foldertree = {getValue: () => '7::INBOX', getNode: (id : string) => tree[id] ? {id} : null};
	const nm = {getValue: () => ({selectedFolder: '7::Other'}), refresh: sinon.spy()};
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		appname: 'mail',
		egw: {preference: () => 'never'},
		et2: {getWidgetById: (id : string) => id === 'nm[foldertree]' ? foldertree : id === 'nm' ? nm : null},
		push_active: {},
		setFolderStatus,
	});
	return {app, setFolderStatus};
}

const push = (folder : string, unseen = 5) => ({
	app: 'mail', id: 'mail::7::' + btoa(folder) + '::42', type: 'update',
	acl: {folder, unseen, event: 'FlagsSet', flags: []}, account_id: 0
});

describe("MailApp.push() - Dovecot mailbox names", () =>
{
	it("maps INBOX.Einkauf to the tree's INBOX/Einkauf", () =>
	{
		const {app, setFolderStatus} = createMailApp({'7::INBOX': true, '7::INBOX/Einkauf': true});
		app.push(<any>push('INBOX.Einkauf'));
		assert.deepEqual(setFolderStatus.firstCall.args[0], {'7::INBOX/Einkauf': 5});
	});

	it("maps deeper levels too", () =>
	{
		const {app, setFolderStatus} = createMailApp({'7::INBOX/A/B': true});
		app.push(<any>push('INBOX.A.B', 2));
		assert.deepEqual(setFolderStatus.firstCall.args[0], {'7::INBOX/A/B': 2});
	});

	it("keeps a name the tree knows, eg. a top-level folder containing a dot", () =>
	{
		const {app, setFolderStatus} = createMailApp({'7::a.b': true, '7::a/b': true});
		app.push(<any>push('a.b'));
		assert.deepEqual(setFolderStatus.firstCall.args[0], {'7::a.b': 5});
	});

	it("keeps a name the tree does not know at all, and plain names", () =>
	{
		const {app, setFolderStatus} = createMailApp({'7::INBOX': true});
		app.push(<any>push('INBOX.Unknown'));
		app.push(<any>push('INBOX', 3));
		assert.deepEqual(setFolderStatus.firstCall.args[0], {'7::INBOX.Unknown': 5});
		assert.deepEqual(setFolderStatus.secondCall.args[0], {'7::INBOX': 3});
	});
});
