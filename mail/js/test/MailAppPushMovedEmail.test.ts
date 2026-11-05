import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {EgwApp} from "../../../api/js/jsapi/egw_app";
import {MailApp} from "../app";

/**
 * Ralf, 2026-11-05: "if I delete a single mail, the counter change on the folder is pushed and
 * updated, but the delete of the mail itself is not and therefore other view did not delete that
 * mail". Root cause: Email/changes has no per-folder scope (mailboxIds is just a property of the
 * one Email object) - a plain "delete" in the UI moves the message to Trash rather than destroying
 * it, so JMAP reports that as an "updated" change, not "destroyed" (the latter already has its own
 * wildcard-delete handling). The "updated" push's id/acl.folder only ever reflect the email's
 * CURRENT (post-move) mailbox, so a still-open view of the folder it was moved OUT OF never heard
 * about it at all - only the folder-tree unseen counters (pushed independently, via the mailbox
 * "updated" change) looked right.
 *
 * Fixed by having both push producers (Api\Mail\Imap\Jmap::pushCallback(), MailJmap.buildEmailPush())
 * send acl.mailboxIds - every mailbox the email CURRENTLY belongs to - so MailApp.push() can find any
 * OTHER cached copy of the row (same wildcard egw.data search the "destroyed" case already uses) and
 * delete it client-side.
 */
function createMailApp(dataSearchUIDs : Record<string, unknown>)
{
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		appname: "mail",
		egw: {
			preference: () => "never",
			dataSearchUIDs: sinon.stub().returns(dataSearchUIDs),
		},
		et2: {getWidgetById: () => null},
		push_active: {},
	});
	return app;
}

const updatePush = (folderId : string, mailboxIds : string[]) => ({
	app: "mail", id: `acc::7::${folderId}::email1`, type: "update",
	acl: {folder: "INBOX/Trash", event: "Flags", flags: [], mailboxIds},
});

describe("MailApp.push() - a moved/deleted email's stale copy in other folders", () =>
{
	let superPush : sinon.SinonStub;

	beforeEach(() => superPush = sinon.stub(EgwApp.prototype, "push"));
	afterEach(() => superPush.restore());

	it("deletes the row cached under a folder the email no longer belongs to", () =>
	{
		const app = createMailApp({
			"mail::acc::7::inbox-id::email1": true,	// stale: where the row used to be shown
			"mail::acc::7::trash-id::email1": true,	// current: where it actually is now
		});

		app.push(<any>updatePush("trash-id", ["trash-id"]));

		assert.equal(superPush.callCount, 1, "only the stale copy must be deleted");
		assert.deepEqual(superPush.firstCall.args[0], {
			app: "mail", id: "acc::7::inbox-id::email1", type: "delete",
			acl: {folder: "INBOX/Trash", event: "Flags", flags: [], mailboxIds: ["trash-id"]},
		});
	});

	it("does nothing when no other cached copy exists", () =>
	{
		const app = createMailApp({"mail::acc::7::trash-id::email1": true});

		app.push(<any>updatePush("trash-id", ["trash-id"]));

		assert.equal(superPush.callCount, 0);
	});

	it("is a no-op without acl.mailboxIds (eg. a plain flags-only update)", () =>
	{
		const app = createMailApp({"mail::acc::7::inbox-id::email1": true});
		const pushData = updatePush("trash-id", ["trash-id"]);
		delete (pushData.acl as any).mailboxIds;

		app.push(<any>pushData);

		assert.equal(superPush.callCount, 0);
	});
});
