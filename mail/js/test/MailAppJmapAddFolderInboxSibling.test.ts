import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Coverage for jmapAddFolder()'s Inbox-sibling placement: Stalwart has no real nesting under
 * INBOX - Sent/Trash/Drafts/... and migrated folders are all top-level siblings of it (see
 * getRootFolders()'s parentId:null query) - so a folder added directly on the Inbox tree node
 * (the way users used to in Dovecot, where that meant "top level") must be created as a sibling
 * of Inbox, not a real child, and the correctly-refreshed tree level is the account root, not
 * Inbox's own children.
 */

function createMailApp(createMailbox : (profileID : string, parentPath : string, name : string) => Promise<any>)
{
	const calls : {createMailbox : any[], refreshFolderLevel : any[]} = {createMailbox : [], refreshFolderLevel : []};

	const app = Object.create(MailApp.prototype) as any;
	// "jmap" is a getter-only property on MailApp.prototype - defineProperty() bypasses that,
	// plain assignment/Object.assign() does not (TypeError: setting getter-only property)
	Object.defineProperty(app, "jmap", {
		configurable : true,
		value : {
			createMailbox : (profileID : string, parentPath : string, name : string) =>
			{
				calls.createMailbox.push({profileID, parentPath, name});
				return createMailbox(profileID, parentPath, name);
			},
		},
	});
	Object.assign(app, {
		refreshFolderLevel : (profileID : string, parentPath : string) =>
		{
			calls.refreshFolderLevel.push({profileID, parentPath});
			return Promise.resolve(true);
		},
		egw : {message : (_msg : string, _type? : string) => {}},
	});
	return {app, calls};
}

describe("MailApp.jmapAddFolder() - Inbox-sibling placement", () =>
{
	it("creates as a top-level sibling (parentPath '') when the target is the Inbox node itself", async() =>
	{
		const {app, calls} = createMailApp(async() => {});

		await app.jmapAddFolder("1::INBOX", "NewFolder");

		assert.deepEqual(calls.createMailbox[0], {profileID : "1", parentPath : "", name : "NewFolder"});
	});

	it("refreshes the account-root tree level, not Inbox's own children, after an Inbox-targeted add", async() =>
	{
		const {app, calls} = createMailApp(async() => {});

		await app.jmapAddFolder("1::INBOX", "NewFolder");

		assert.deepEqual(calls.refreshFolderLevel[0], {profileID : "1", parentPath : ""});
	});

	it("still nests under a real (non-Inbox) parent unchanged", async() =>
	{
		const {app, calls} = createMailApp(async() => {});

		await app.jmapAddFolder("1::Archive", "2026");

		assert.deepEqual(calls.createMailbox[0], {profileID : "1", parentPath : "Archive", name : "2026"});
		assert.deepEqual(calls.refreshFolderLevel[0], {profileID : "1", parentPath : "Archive"});
	});

	it("still creates at the true top level unchanged, when adding on the bare account-root node", async() =>
	{
		const {app, calls} = createMailApp(async() => {});

		await app.jmapAddFolder("1", "NewFolder");

		assert.deepEqual(calls.createMailbox[0], {profileID : "1", parentPath : "", name : "NewFolder"});
	});

	it("shows the error via egw.message() and never refreshes, when createMailbox() fails", async() =>
	{
		let shown : string | null = null;
		const {app, calls} = createMailApp(async() => { throw new Error("boom"); });
		app.egw.message = (msg : string) => { shown = msg; };

		await app.jmapAddFolder("1::INBOX", "NewFolder");

		assert.equal(shown, "boom");
		assert.equal(calls.refreshFolderLevel.length, 0);
	});
});
