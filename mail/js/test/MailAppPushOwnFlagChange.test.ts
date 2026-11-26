import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";
import {MailJmap} from "../jmap";

/**
 * Every flag change made in the list was followed by an Email/get to the JMAP server ~300ms later:
 * the server reports our own change back as a "Flags" push, and MailApp.push() refreshed the row
 * for it - although the row was patched before the server was even asked. Traced live 2026-11-26.
 *
 * Fix under test: a "Flags" push of a real JMAP account names ALL keywords the message has now,
 * so the refresh is skipped if the list row shows exactly these already
 * (MailJmap.rowShowsKeywords()). Everything else must still refresh: a change made elsewhere,
 * an IMAP account (whose "FlagsSet"/"FlagsClear" pushes only name the changed flags), a row we
 * know nothing about.
 */

const ROW_ID = "6::2::inbox::e1";

/**
 * A MailJmap for real-JMAP profile "2" and IMAP profile "7", whose egw data cache holds one list
 * row with the given flags - in the shape both a fetched row (keywordsToRowFlags()) and a locally
 * patched one (MailApp.patchRow()) carry them.
 */
function createJmap(flags: Record<string, any> | null)
{
	const app = {
		egw: {
			user: () => 6, lang: (label: string) => label, preference: (): any => null,
			dataGetUIDdata: (uid: string) => flags && uid === "mail::" + ROW_ID ? {data: {flags}} : null,
		},
		getCustomLabels: () => ({Projects: {name: "Projects", color: "#00f"}}),
		getRowLabelTags: (): any[] => [],
	} as unknown as MailApp;
	const jmap = new MailJmap(app);
	(jmap as any).tokens["2"] = {accountId: "own", isLocal: false};
	(jmap as any).tokens["7"] = {accountId: "imap", isLocal: true};
	return jmap;
}

describe("MailJmap.rowShowsKeywords()", () =>
{
	it("is true if the row shows exactly the pushed keywords", () =>
	{
		const jmap = createJmap({read: "read", flagged: "flagged", label2: "label2", Projects: "Projects"});

		// keywords the list does not render ($hasattachment, a foreign one) are no difference
		assert.isTrue(jmap.rowShowsKeywords(ROW_ID, ["$seen", "$flagged", "$label2", "$projects", "$hasattachment", "foreign"]));
		assert.isTrue(jmap.rowShowsKeywords("mail::" + ROW_ID, ["$flagged", "$seen", "$label2", "$projects"]), "with prefix, any order");
	});

	it("treats an unread row as showing no $seen, however the local patch left the flag", () =>
	{
		assert.isTrue(createJmap({}).rowShowsKeywords(ROW_ID, []));
		assert.isTrue(createJmap({read: undefined, flagged: "flagged"}).rowShowsKeywords(ROW_ID, ["$flagged"]));
	});

	it("is false for any difference, in either direction", () =>
	{
		const jmap = createJmap({read: "read"});

		assert.isFalse(jmap.rowShowsKeywords(ROW_ID, []), "marked unread elsewhere");
		assert.isFalse(jmap.rowShowsKeywords(ROW_ID, ["$seen", "$flagged"]), "flagged elsewhere");
		assert.isFalse(jmap.rowShowsKeywords(ROW_ID, ["$seen", "$answered"]), "answered elsewhere");
		assert.isFalse(jmap.rowShowsKeywords(ROW_ID, ["$seen", "$customflag3"]), "custom flag set elsewhere");
	});

	it("is false whenever in doubt, so the row gets fetched", () =>
	{
		assert.isFalse(createJmap(null).rowShowsKeywords(ROW_ID, ["$seen"]), "row not in the list");
		assert.isFalse(createJmap({read: "read"}).rowShowsKeywords("6::7::SU5CT1g=::42", ["$seen"]), "IMAP account");
		assert.isFalse(createJmap({read: "read"}).rowShowsKeywords("6::99::inbox::e1", ["$seen"]), "unknown account");
		assert.isFalse(createJmap({read: "read"}).rowShowsKeywords("not-a-row-id", ["$seen"]));
		assert.isFalse(createJmap({read: "read"}).rowShowsKeywords(ROW_ID, <any>undefined), "push without flags");
	});
});

describe("MailApp.push() - flag change reported back by the server", () =>
{
	function createMailApp(rowShowsKeywords: boolean)
	{
		const refresh = sinon.spy();
		const foldertree = {getValue: () => "2::INBOX", getNode: (): any => null};
		const nm = {getValue: () => ({selectedFolder: "2::INBOX"}), refresh};
		const app = Object.create(MailApp.prototype) as MailApp;
		Object.assign(app, {
			appname: "mail",
			egw: {preference: () => "never"},
			et2: {getWidgetById: (id: string) => id === "nm[foldertree]" ? foldertree : id === "nm" ? nm : null},
			push_active: {},
		});
		const asked = sinon.stub().returns(rowShowsKeywords);
		Object.defineProperty(app, "jmap", {value: {rowShowsKeywords: asked, countersManaged: () => true}, configurable: true});
		return {app, refresh, asked};
	}
	const push = (event: string, flags: string[]) => ({
		app: "mail", id: ROW_ID, type: "update", acl: {folder: "INBOX", event, flags}, account_id: 0
	});

	it("does not refresh the row, if it shows the pushed keywords already", () =>
	{
		const {app, refresh, asked} = createMailApp(true);

		app.push(<any>push("Flags", ["$seen"]));

		assert.deepEqual(asked.args, [[ROW_ID, ["$seen"]]]);
		assert.isFalse(refresh.called, "our own change needs no Email/get");
	});

	it("refreshes the row for a change it does not show yet", () =>
	{
		const {app, refresh} = createMailApp(false);

		app.push(<any>push("Flags", ["$seen", "$flagged"]));

		assert.equal(refresh.callCount, 1);
		assert.equal(refresh.firstCall.args[0], ROW_ID);
	});

	it("always refreshes for the pushes of an IMAP server, which only name the changed flags", () =>
	{
		const {app, refresh, asked} = createMailApp(true);

		app.push(<any>push("FlagsSet", ["\\Seen"]));
		app.push(<any>push("FlagsClear", ["\\Seen"]));

		assert.isFalse(asked.called, "IMAP pushes must not even be compared");
		assert.equal(refresh.callCount, 2);
	});
});
