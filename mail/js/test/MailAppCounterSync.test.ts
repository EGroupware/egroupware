import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * MailApp's side of the client-side unread-counter sync of real JMAP accounts
 * (MailJmap.requestCounterSync(), see MailJmapCounterSync.test.ts): who may write a badge, and
 * which tree node a count belongs to. IMAP accounts must behave exactly as before in every test.
 *
 * Setup: a MailApp without constructor, a fake folder-tree holding plain option objects, and a
 * fake `jmap` saying profile "2" is a counter-managed JMAP account and "7" is not (IMAP).
 */

const TREE = [
	{value: "2", children: [
		{value: "2::INBOX", jmapId: "inbox", badge: 5, children: [
			{value: "2::INBOX/Sub", jmapId: "sub", badge: 2, children: []},
		]},
		{value: "2::Trash", jmapId: "trash", children: []},
	]},
	{value: "7", children: [
		{value: "7::INBOX", jmapId: "SU5CT1g=", badge: 4, children: []},
	]},
];

function createMailApp(selected = "2::INBOX")
{
	const find = (id: string, items: any[] = TREE): any =>
		items.reduce((found, item) => found ?? (item.value === id ? item : find(id, item.children)), null);
	const foldertree = {
		select_options: TREE,
		getValue: () => selected,
		getNode: find,
		getSelectedItem: () => find(selected),
		setClass: sinon.spy(),
		set_badge: sinon.spy(),
	};
	const nm = {getValue: () => ({selectedFolder: selected}), refresh: sinon.spy()};
	const requestCounterSync = sinon.stub().resolves();
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		appname: 'mail',
		nm_index: 'nm',
		egw: {preference: () => 'never'},
		et2: {getWidgetById: (id: string) => id === 'nm[foldertree]' ? foldertree : id === 'nm' ? nm : null},
		push_active: {},
		unlockTree: sinon.spy(),
	});
	Object.defineProperty(app, 'jmap', {value: {
		countersManaged: (profileID: string) => profileID === "2",
		requestCounterSync,
		messageReference: (rowId: string) =>
		{
			const parts = rowId.split('::');
			return {profileID: parts[2], mailboxId: parts[3], emailId: parts[4]};
		},
	}, configurable: true});
	return {app, foldertree, requestCounterSync};
}

const push = (profileID: string, folder: string, unseen: number) => ({
	app: 'mail', id: '1::' + profileID + '::' + btoa(folder), type: 'update',
	acl: {folder, unseen}, account_id: 0
});

describe("MailApp - unread counters of JMAP accounts", () =>
{
	it("ignores a pushed counter for a JMAP account we sync ourselves, not for an IMAP one", () =>
	{
		const {app} = createMailApp();
		const setFolderStatus = sinon.stub(app, "setFolderStatus");

		app.push(<any>push("2", "INBOX", 9));
		assert.isFalse(setFolderStatus.called, "a relayed push could bring back an older value");

		app.push(<any>push("7", "INBOX", 9));
		assert.deepEqual(setFolderStatus.firstCall.args[0], {"7::INBOX": 9});
	});

	it("finds the loaded tree nodes of one account by their JMAP Mailbox id", () =>
	{
		const {app} = createMailApp();

		assert.deepEqual(app.folderNodesByJmapId("2"), {inbox: "2::INBOX", sub: "2::INBOX/Sub", trash: "2::Trash"});
		assert.deepEqual(app.folderNodesByJmapId("7"), {"SU5CT1g=": "7::INBOX"});
		assert.deepEqual(app.folderNodesByJmapId("99"), {});
	});

	it("refreshes JMAP folders from the JMAP server, and only IMAP folders via our server", () =>
	{
		const clock = sinon.useFakeTimers();
		const jsonq = sinon.stub();
		const hadEgw = 'egw' in window;
		const oldEgw = (window as any).egw;
		(window as any).egw = {jsonq};
		try
		{
			const {app, requestCounterSync} = createMailApp();

			const queued = Date.now();
			app.queueRefreshFolderList(["2::INBOX", "2::INBOX/Sub", "7::INBOX"]);
			assert.isFalse(requestCounterSync.called, "queued behind the folder action, which usually brings the counters itself");
			clock.tick(500);
			assert.deepEqual(requestCounterSync.args, [["2", queued]],
				"once per account, not per folder - and only if nothing synced since it was queued");
			assert.deepEqual(jsonq.firstCall.args[1], [["7::INBOX"]]);

			jsonq.resetHistory();
			app.queueRefreshFolderList(["2::INBOX"]);
			clock.tick(500);
			assert.isFalse(jsonq.called, "no request to our server for nothing but JMAP folders");
			assert.isTrue((app as any).unlockTree.calledOnce, "the tree must get unlocked without the request too");
		}
		finally
		{
			clock.restore();
			if (hadEgw) (window as any).egw = oldEgw; else delete (window as any).egw;
		}
	});

	it("reduces the counter of the read message's own folder for a JMAP account", () =>
	{
		const {app, foldertree} = createMailApp("2::Trash");

		// eg. found by a search over all folders: the message is not in the selected folder
		app.reduceCounterWithoutServerRoundtrip("mail::1::2::sub::e1");

		assert.deepEqual(foldertree.set_badge.firstCall.args, ["2::INBOX/Sub", "1"]);
	});

	it("keeps reducing the selected folder for an IMAP account, or without a row", () =>
	{
		const {app, foldertree} = createMailApp("7::INBOX");

		app.reduceCounterWithoutServerRoundtrip("mail::1::7::SU5CT1g=::42");
		app.reduceCounterWithoutServerRoundtrip();

		assert.deepEqual(foldertree.set_badge.args, [["7::INBOX", "3"], ["7::INBOX", "3"]]);
	});
});
