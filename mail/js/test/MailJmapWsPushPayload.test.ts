import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's WebSocket push-payload building (buildWsPushPayload()/buildEmailPush()/
 * buildMailboxPush()/buildEmailDeletePush()) - doc/ai/projects/mail-test-coverage.md's
 * priority-2 entry. Client-side mirror of Api\Mail\Imap\Jmap::pushCallback()'s "StateChange" case,
 * translating JMAP Email/Mailbox changes into classic egw_app.push() envelopes - previously
 * untested.
 *
 * folderId2path() (a separate, already-nontrivial chained-$ref() lookup) is stubbed directly in
 * these tests rather than faked at the requestMany() level - it has its own scope, and stubbing
 * it keeps these tests focused on the push-envelope-building logic itself.
 */

function createFakeApp() : MailApp
{
	return {egw : {user : (_key : string) => 1, lang : (l : string) => l}} as unknown as MailApp;
}

function stubFolderId2path(jmap : MailJmap, resolver : (folderId : string) => string | null) : void
{
	(jmap as any).folderId2path = async(_client : any, _profileID : string, _accountId : string, folderId : string) => resolver(folderId);
}

describe("MailJmap.buildEmailDeletePush()", () =>
{
	it("builds a wildcard-folder delete envelope keyed by the EGroupware account_id", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const push = (jmap as any).buildEmailDeletePush("1", "email42");

		assert.deepEqual(push, {app : 'mail', id : '1::1::*::email42', type : 'delete', acl : {}});
	});
});

describe("MailJmap.buildEmailPush()", () =>
{
	it("returns null when the email has no mailboxIds at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const push = await (jmap as any).buildEmailPush({}, "1", "acc1", {id : "e1", mailboxIds : {}}, "add");

		assert.isNull(push);
	});

	it("returns null when the folder can no longer be resolved (a destroyed-mailbox race)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, () => null);

		const push = await (jmap as any).buildEmailPush({}, "1", "acc1", {id : "e1", mailboxIds : {mbx1 : true}}, "add");

		assert.isNull(push);
	});

	it("'add' sets a MessageNew acl with from/subject/trimmed-snippet", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, () => "INBOX");

		const push = await (jmap as any).buildEmailPush({}, "1", "acc1", {
			id : "e1", mailboxIds : {mbx1 : true},
			from : [{email : "sender@example.org", name : "Sender"}],
			subject : "Hello", preview : "  a preview  ",
		}, "add");

		assert.equal(push.acl.event, 'MessageNew');
		assert.equal(push.acl.from, 'Sender <sender@example.org>');
		assert.equal(push.acl.subject, 'Hello');
		assert.equal(push.acl.snippet, 'a preview');
	});

	it("'update' sets a Flags acl listing the email's current keywords, not any prior state", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, () => "INBOX");

		const push = await (jmap as any).buildEmailPush({}, "1", "acc1", {
			id : "e1", mailboxIds : {mbx1 : true}, keywords : {'$seen' : true, '$flagged' : true},
		}, "update");

		assert.equal(push.acl.event, 'Flags');
		assert.sameMembers(push.acl.flags, ['$seen', '$flagged']);
	});

	it("uses the EGroupware account_id in the pushed row id, NOT the JMAP accountId parameter", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, () => "INBOX");

		const push = await (jmap as any).buildEmailPush({}, "1", "stalwart-opaque-id-b", {
			id : "e1", mailboxIds : {mbx1 : true},
		}, "add");

		assert.equal(push.id, '1::1::mbx1::e1', "must be EGroupware account_id (1, from the fake egw.user()) + profileID + folderId + email id - never the JMAP accountId param");
	});
});

describe("MailJmap.buildMailboxPush()", () =>
{
	it("returns null when the folder can no longer be resolved", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, () => null);

		const push = await (jmap as any).buildMailboxPush({}, "1", "acc1", {id : "mbx1"}, "add");

		assert.isNull(push);
	});

	it("'update' includes the unseen count, 'add' does not", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, () => "INBOX");

		const updatePush = await (jmap as any).buildMailboxPush({}, "1", "acc1", {id : "mbx1", unreadEmails : 7}, "update");
		assert.equal(updatePush.acl.unseen, 7);

		const addPush = await (jmap as any).buildMailboxPush({}, "1", "acc1", {id : "mbx1", unreadEmails : 7}, "add");
		assert.notProperty(addPush.acl, 'unseen');
	});
});

/**
 * requestMany()'s own $ref()-chained shape - t.X.method(args) returns an opaque invocation thunk,
 * the ACTUAL resolved data comes back as requestMany()'s own return value - same convention
 * established in MailJmap.test.ts's "jmap-jam requestMany() invocation shape" tests and
 * MailJmapMailboxCrud.test.ts's getAllMailboxes() tests.
 */
function createChainedClient(result : Record<string, any>) : any
{
	const invocation = () => ({$ref : (_path : string) => ({})});
	return {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {
				Mailbox : {changes : (_args : any) => invocation(), get : (_args : any) => invocation()},
				Email : {changes : (_args : any) => invocation(), get : (_args : any) => invocation()},
			};
			buildFn(t);
			return [result];
		},
	};
}

describe("MailJmap.buildWsPushPayload()", () =>
{
	function primeJmap(sinceStates : Record<string, string>) : {jmap : MailJmap, run : (client : any) => Promise<any[]>}
	{
		const jmap = new MailJmap(createFakeApp());
		stubFolderId2path(jmap, (folderId) => folderId === 'gone' ? null : 'INBOX/' + folderId);
		return {
			jmap,
			run : (client : any) => (jmap as any).buildWsPushPayload(client, "1", "acc1", sinceStates),
		};
	}

	it("builds add/update push envelopes for both created and updated emails", async() =>
	{
		const {run} = primeJmap({Email : 'old-state'});
		const client = createChainedClient({
			emailCreated : {list : [{id : 'e1', mailboxIds : {f1 : true}, subject : 'New'}]},
			emailUpdated : {list : [{id : 'e2', mailboxIds : {f1 : true}, keywords : {'$seen' : true}}]},
			emailChanges : {destroyed : []},
		});

		const payload = await run(client);

		assert.equal(payload.length, 2);
		assert.equal(payload.find((p) => p.id.endsWith('e1')).type, 'add');
		assert.equal(payload.find((p) => p.id.endsWith('e2')).type, 'update');
	});

	it("builds add/update push envelopes for both created and updated mailboxes", async() =>
	{
		const {run} = primeJmap({Mailbox : 'old-state'});
		const client = createChainedClient({
			mailboxCreated : {list : [{id : 'mbx1'}]},
			mailboxUpdated : {list : [{id : 'mbx2', unreadEmails : 3}]},
			mailboxChanges : {destroyed : []},
		});

		const payload = await run(client);

		assert.equal(payload.length, 2);
		assert.equal(payload.find((p) => p.id.endsWith('mbx1')).type, 'add');
		assert.equal(payload.find((p) => p.id.endsWith('mbx2')).type, 'update');
	});

	it("skips mailbox handling entirely when only Email changed", async() =>
	{
		const {run} = primeJmap({Email : 'old-state'});
		let mailboxCalled = false;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const invocation = () => ({$ref : () => ({})});
				const t = {
					Mailbox : {changes : () => { mailboxCalled = true; return invocation(); }, get : () => invocation()},
					Email : {changes : () => invocation(), get : () => invocation()},
				};
				buildFn(t);
				return [{emailCreated : {list : []}, emailUpdated : {list : []}, emailChanges : {destroyed : []}}];
			},
		};

		await run(client);

		assert.isFalse(mailboxCalled, "Mailbox/changes must never be called when sinceStates has no Mailbox entry");
	});

	it("pushes a wildcard-folder delete envelope for every destroyed email id", async() =>
	{
		const {run} = primeJmap({Email : 'old-state'});
		const client = createChainedClient({
			emailCreated : {list : []}, emailUpdated : {list : []},
			emailChanges : {destroyed : ['deleted-e1', 'deleted-e2']},
		});

		const payload = await run(client);

		assert.equal(payload.length, 2);
		assert.isTrue(payload.every((p) => p.type === 'delete' && p.id.includes('::*::')));
	});

	it("only pushes a destroyed mailbox's delete envelope when its path was already cached, skips it otherwise", async() =>
	{
		const {jmap, run} = primeJmap({Mailbox : 'old-state'});
		(jmap as any).folderPaths['1'] = {'known-mbx' : 'INBOX/Known'};
		const client = createChainedClient({
			mailboxCreated : {list : []}, mailboxUpdated : {list : []},
			mailboxChanges : {destroyed : ['known-mbx', 'unknown-mbx']},
		});

		const payload = await run(client);

		assert.equal(payload.length, 1, "only the cached one produces a delete envelope");
		assert.equal(payload[0].acl.folder, 'INBOX/Known');
	});

	it("filters out null results (e.g. a folder that no longer resolves) from the final payload", async() =>
	{
		const {run} = primeJmap({Email : 'old-state'});
		const client = createChainedClient({
			emailCreated : {list : [{id : 'gone-email', mailboxIds : {gone : true}}]},
			emailUpdated : {list : []},
			emailChanges : {destroyed : []},
		});

		const payload = await run(client);

		assert.deepEqual(payload, [], "buildEmailPush() resolving null for the unresolvable folder must not produce a broken entry");
	});
});
