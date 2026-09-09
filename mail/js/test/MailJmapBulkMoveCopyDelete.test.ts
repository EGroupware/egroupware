import {assert} from "@open-wc/testing";
import {describeSetError, JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's bulk move/copy/delete surface (moveMessages/copyMessages/
 * deleteMessages/destroyIds/queryAllIds/moveAllMatching/copyAllMatching/deleteAllMatching/
 * purgeFolder) - previously completely untested despite two prior real bugs in exactly this area
 * (see doc/ai/projects/mail-test-coverage.md's priority-1 entry): the optimistic-delete-before-
 * awaiting-JMAP race, and "delete all matching" racing the shim's own deferred IMAP move.
 *
 * Pure unit tests against a fake JamClient - no network/DB/IMAP involved. Follows the same
 * primeToken()/fake-client conventions already established in MailJmap.test.ts.
 */

const egw = {
	user : (_key : string) => 1,
	lang : (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
	preference : (_key : string, _app? : string) => null,
	config : (_name : string, _app? : string) => null,
	request : async() => ({}),
	message : (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

interface Captured
{
	emailSet : any[];
	emailQuery : any[];
	mailboxQuery : any[];
}

/**
 * A fake JamClient covering Email.set/Email.query/Mailbox.query - buildFn(t) mirrors the real
 * jmap-jam shape (t.X.method(args) returns a plain value synchronously, requestMany() wraps the
 * whole object literal the caller built in an array), same convention as MailJmap.test.ts's own
 * createFakeClient()/createSequencedClient().
 */
function createFakeClient(handlers : {
	emailSet? : (args : any) => any,
	emailQuery? : (args : any, callIndex : number) => any,
} = {}) : {client : any, captured : Captured}
{
	const captured : Captured = {emailSet : [], emailQuery : [], mailboxQuery : []};
	let emailQueryCalls = 0;
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {
				Email : {
					set : (args : any) =>
					{
						captured.emailSet.push(args);
						return handlers.emailSet?.(args) ?? {updated : {}, notUpdated : {}, destroyed : [], notDestroyed : {}};
					},
					query : (args : any) =>
					{
						captured.emailQuery.push(args);
						const result = handlers.emailQuery?.(args, emailQueryCalls) ?? {ids : [], total : 0};
						emailQueryCalls++;
						return result;
					},
				},
				Mailbox : {
					query : (args : any) =>
					{
						captured.mailboxQuery.push(args);
						return {ids : ['mbx-' + JSON.stringify(args.filter)]};
					},
				},
			};
			const request = buildFn(t);
			return [request];
		},
	};
	return {client, captured};
}

function primeToken(jmap : MailJmap, profileID : string, client : any,
	overrides : Record<string, any> = {}) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		...overrides,
	};
	(jmap as any).clients[profileID] = client;
}

/** Pre-seed mailboxId()'s cache, skipping Mailbox/query entirely for tests not about folder resolution. */
function seedMailboxId(jmap : MailJmap, profileID : string, folderPath : string, mailboxId : string) : void
{
	(jmap as any).mailboxIds[profileID + '::' + folderPath] = mailboxId;
}

function ref(profileID : string, mailboxId : string, emailId : string)
{
	return {profileID, mailboxId, emailId};
}

describe("MailJmap.moveMessages()", () =>
{
	it("patches mailboxIds to the target (full replace) for a single-mailbox selection", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.moveMessages([ref("1", "mbx-inbox", "e1"), ref("1", "mbx-inbox", "e2")], "1", "Archive");

		assert.equal(captured.emailSet.length, 1, "same source mailbox -> one grouped Email/set call");
		assert.deepEqual(captured.emailSet[0].update, {
			e1 : {mailboxIds : {"mbx-archive" : true}},
			e2 : {mailboxIds : {"mbx-archive" : true}},
		});
	});

	it("groups references from different source mailboxes into separate Email/set calls", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.moveMessages([ref("1", "mbx-inbox", "e1"), ref("1", "mbx-work", "e2")], "1", "Archive");

		assert.equal(captured.emailSet.length, 2, "two distinct source mailboxes -> two separate calls");
		const allIds = captured.emailSet.flatMap(args => Object.keys(args.update));
		assert.sameMembers(allIds, ["e1", "e2"]);
	});

	it("throws for a cross-account move (references not all on targetProfileID)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.moveMessages([ref("1", "mbx-inbox", "e1")], "2", "Archive");
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});

	/**
	 * Documents CURRENT behaviour, found while writing this test - not necessarily desired:
	 * an empty selection hits the exact same `!references.length` branch as an actual
	 * cross-account mismatch, so the caller gets "cross-account move not supported" for a
	 * plain empty selection too. Flagged back to ralf rather than silently "fixed" here.
	 */
	it("(characterization) also throws the cross-account error for an empty selection", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let message : string | null = null;
		try
		{
			await jmap.moveMessages([], "1", "Archive");
		}
		catch (e)
		{
			message = e.message;
		}
		assert.equal(message, "MailJmap.moveMessages(): cross-account move not supported");
	});
});

describe("MailJmap.copyMessages()", () =>
{
	it("patches mailboxIds/<id> (add-only PatchObject), not a full replace", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.copyMessages([ref("1", "mbx-inbox", "e1")], "1", "Archive");

		assert.deepEqual(captured.emailSet[0].update, {e1 : {"mailboxIds/mbx-archive" : true}});
	});

	it("throws for a cross-account copy", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.copyMessages([ref("1", "mbx-inbox", "e1")], "2", "Archive");
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});
});

describe("MailJmap.deleteMessages()", () =>
{
	it("is a no-op for an empty selection (unlike moveMessages(), this one early-returns cleanly)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client);

		await jmap.deleteMessages([], "trash");

		assert.equal(captured.emailSet.length, 0);
	});

	it("'trash' moves a message to the account's trash folder", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client, {trashFolder : "Trash"});
		seedMailboxId(jmap, "1", "Trash", "mbx-trash");

		await jmap.deleteMessages([ref("1", "mbx-inbox", "e1")], "trash");

		assert.deepEqual(captured.emailSet[0].update, {e1 : {mailboxIds : {"mbx-trash" : true}}});
	});

	/**
	 * The exact regression found live 2026-08-27 (ralf: "no need to copy/move them to Trash,
	 * just expunge would be enough" - confirmed via Dovecot push showing a real MessageAppend
	 * into Trash followed by a MessageExpunge for a message ALREADY in Trash). Deleting something
	 * already in Trash must destroy it directly, not move-into-itself.
	 */
	it("'trash' destroys directly (not move-into-itself) when the message is ALREADY in Trash", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client, {trashFolder : "Trash"});
		seedMailboxId(jmap, "1", "Trash", "mbx-trash");

		await jmap.deleteMessages([ref("1", "mbx-trash", "e1")], "trash");

		assert.equal(captured.emailSet.length, 1);
		assert.deepEqual(captured.emailSet[0].destroy, ["e1"],
			"must destroy directly, never patch mailboxIds onto the SAME mailbox it's already in");
	});

	it("'trash' throws when the account has no trash folder configured", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient();
		primeToken(jmap, "1", client, {trashFolder : undefined});

		let threw = false;
		try
		{
			await jmap.deleteMessages([ref("1", "mbx-inbox", "e1")], "trash");
		}
		catch (e)
		{
			threw = true;
			assert.instanceOf(e, JmapUserError);
		}
		assert.isTrue(threw);
	});

	it("'destroy' permanently removes the messages directly, without ever resolving a trash folder", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		// deliberately no trashFolder configured - 'destroy' must never need it
		primeToken(jmap, "1", client, {trashFolder : undefined});

		await jmap.deleteMessages([ref("1", "mbx-inbox", "e1"), ref("1", "mbx-inbox", "e2")], "destroy");

		assert.equal(captured.emailSet.length, 1);
		assert.sameMembers(captured.emailSet[0].destroy, ["e1", "e2"]);
	});

	it("includes the shim's mailboxId extension only for a local (isLocal) account, never for real JMAP", async() =>
	{
		const jmapLocal = new MailJmap(createFakeApp());
		const {client : localClient, captured : localCaptured} = createFakeClient();
		primeToken(jmapLocal, "1", localClient, {isLocal : true});
		await jmapLocal.deleteMessages([ref("1", "mbx-inbox", "e1")], "destroy");
		assert.equal(localCaptured.emailSet[0].mailboxId, "mbx-inbox");

		const jmapReal = new MailJmap(createFakeApp());
		const {client : realClient, captured : realCaptured} = createFakeClient();
		primeToken(jmapReal, "1", realClient, {isLocal : false});
		await jmapReal.deleteMessages([ref("1", "mbx-inbox", "e1")], "destroy");
		assert.isUndefined(realCaptured.emailSet[0].mailboxId);
	});

	it("throws a JmapUserError (via describeSetError()) when the server reports notDestroyed entries", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const notDestroyed = {e1 : {type : "notFound"}};
		const {client} = createFakeClient({emailSet : () => ({destroyed : [], notDestroyed})});
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.deleteMessages([ref("1", "mbx-inbox", "e1")], "destroy");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message, describeSetError(notDestroyed));
	});

	it("paginates destroy across more than one Email/set call for a large selection", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client);
		// QUERY_PAGE_SIZE is 500 - 501 ids must span exactly 2 Email/set calls
		const references = Array.from({length : 501}, (_, i) => ref("1", "mbx-inbox", "e" + i));

		await jmap.deleteMessages(references, "destroy");

		assert.equal(captured.emailSet.length, 2);
		assert.equal(captured.emailSet[0].destroy.length, 500);
		assert.equal(captured.emailSet[1].destroy.length, 1);
		const allDestroyed = captured.emailSet.flatMap(args => args.destroy);
		assert.sameMembers(allDestroyed, references.map(r => r.emailId), "every id must be destroyed exactly once, none dropped/duplicated");
	});
});

describe("MailJmap.queryAllIds() - via moveAllMatching()/deleteAllMatching()", () =>
{
	it("accumulates ids across multiple pages until the reported total is reached", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const pages = [
			{ids : ["a", "b"], total : 5},
			{ids : ["c", "d"], total : 5},
			{ids : ["e"], total : 5},
		];
		const {client, captured} = createFakeClient({
			emailQuery : (_args, callIndex) => pages[callIndex],
		});
		primeToken(jmap, "1", client, {trashFolder : "Trash"});
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");
		seedMailboxId(jmap, "1", "Trash", "mbx-trash");

		await jmap.deleteAllMatching({selectedFolder : "1::INBOX"}, "destroy");

		assert.equal(captured.emailQuery.length, 3, "must keep paging until it has all 5 reported ids");
		assert.sameMembers(captured.emailSet[0].destroy, ["a", "b", "c", "d", "e"]);
		// each page asks for the next slice, not the same one again
		assert.deepEqual(captured.emailQuery.map(q => q.position), [0, 2, 4]);
	});

	it("stops paging if a page comes back empty, even if the reported total was never reached", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const pages = [
			{ids : ["a"], total : 100},
			{ids : [], total : 100},
		];
		const {client, captured} = createFakeClient({emailQuery : (_args, callIndex) => pages[callIndex]});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");

		await jmap.deleteAllMatching({selectedFolder : "1::INBOX"}, "destroy");

		assert.equal(captured.emailQuery.length, 2, "must not loop forever/keep querying once a page comes back empty");
		assert.sameMembers(captured.emailSet[0].destroy, ["a"]);
	});
});

describe("MailJmap.moveAllMatching()/copyAllMatching()/deleteAllMatching()", () =>
{
	it("moveAllMatching() throws for a cross-account move", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.moveAllMatching({selectedFolder : "1::INBOX"}, "2", "Archive");
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});

	it("copyAllMatching() throws for a cross-account copy", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let threw = false;
		try
		{
			await jmap.copyAllMatching({selectedFolder : "1::INBOX"}, "2", "Archive");
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});

	it("moveAllMatching() moves every matching id with a full mailboxIds replace", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({emailQuery : () => ({ids : ["a", "b"], total : 2})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.moveAllMatching({selectedFolder : "1::INBOX"}, "1", "Archive");

		assert.deepEqual(captured.emailSet[0].update, {
			a : {mailboxIds : {"mbx-archive" : true}},
			b : {mailboxIds : {"mbx-archive" : true}},
		});
	});

	it("copyAllMatching() copies every matching id with an add-only PatchObject", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({emailQuery : () => ({ids : ["a"], total : 1})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.copyAllMatching({selectedFolder : "1::INBOX"}, "1", "Archive");

		assert.deepEqual(captured.emailSet[0].update, {a : {"mailboxIds/mbx-archive" : true}});
	});

	it("deleteAllMatching('trash') delegates to moveAllMatching() against the account's trash folder", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({emailQuery : () => ({ids : ["a"], total : 1})});
		primeToken(jmap, "1", client, {trashFolder : "Trash"});
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");
		seedMailboxId(jmap, "1", "Trash", "mbx-trash");

		await jmap.deleteAllMatching({selectedFolder : "1::INBOX"}, "trash");

		assert.deepEqual(captured.emailSet[0].update, {a : {mailboxIds : {"mbx-trash" : true}}});
	});

	it("deleteAllMatching('trash') throws when there is no trash folder configured", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient();
		primeToken(jmap, "1", client, {trashFolder : undefined});

		let threw = false;
		try
		{
			await jmap.deleteAllMatching({selectedFolder : "1::INBOX"}, "trash");
		}
		catch (e)
		{
			threw = true;
			assert.instanceOf(e, JmapUserError);
		}
		assert.isTrue(threw);
	});

	it("deleteAllMatching('destroy') permanently removes every matching id directly", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({emailQuery : () => ({ids : ["a", "b"], total : 2})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "INBOX", "mbx-inbox");

		await jmap.deleteAllMatching({selectedFolder : "1::INBOX"}, "destroy");

		assert.sameMembers(captured.emailSet[0].destroy, ["a", "b"]);
	});
});

describe("MailJmap.purgeFolder() - empty trash/junk", () =>
{
	it("resolves the trash folder from the token and destroys everything in it", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({emailQuery : () => ({ids : ["a"], total : 1})});
		primeToken(jmap, "1", client, {trashFolder : "Trash"});
		seedMailboxId(jmap, "1", "Trash", "mbx-trash");

		const selectedFolder = await jmap.purgeFolder("1", "trash");

		assert.equal(selectedFolder, "1::Trash");
		assert.sameMembers(captured.emailSet[0].destroy, ["a"]);
	});

	it("resolves the junk folder for 'junk', independently of trashFolder", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({emailQuery : () => ({ids : ["a"], total : 1})});
		primeToken(jmap, "1", client, {junkFolder : "Junk", trashFolder : undefined});
		seedMailboxId(jmap, "1", "Junk", "mbx-junk");

		const selectedFolder = await jmap.purgeFolder("1", "junk");

		assert.equal(selectedFolder, "1::Junk");
		assert.sameMembers(captured.emailSet[0].destroy, ["a"]);
	});

	it("throws when the account has no such folder configured", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient();
		primeToken(jmap, "1", client, {trashFolder : undefined});

		let threw = false;
		try
		{
			await jmap.purgeFolder("1", "trash");
		}
		catch (e)
		{
			threw = true;
			assert.instanceOf(e, JmapUserError);
		}
		assert.isTrue(threw);
	});
});
