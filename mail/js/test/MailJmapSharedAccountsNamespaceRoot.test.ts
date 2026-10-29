import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for the "Other users" namespace-root synthesis added for ticket #126691 follow-up (see
 * doc/ai/projects/mail-jmap-shared-mailboxes.md): a real-JMAP (Stalwart) account has no NAMESPACE-
 * equivalent concept at all, so unlike the local shim (real IMAP underneath) the "user" namespace
 * root has to be synthesized client-side from the JMAP Session's own `accounts` map whenever any
 * non-personal (shared) account is present there.
 *
 * Tests the two private helpers directly (injectSharedAccountsRoot()/getSharedAccountPlaceholders())
 * rather than the full getMailboxChildren()/getRootFolders() call chain, matching this project's own
 * (jmap as any).<private member> convention for unit-testing MailJmap internals (see
 * MailJmapMailboxCrud.test.ts's primeToken()) - those two callers only ever glue this logic into the
 * right spot in an already-fetched list, which is a thin, low-risk wiring concern.
 */

const egw = {
	user : (_key : string) => 1,
	lang : (label : string) => String(label),
	preference : (_key : string, _app? : string) => null,
};

function createFakeApp() : MailApp
{
	return {egw} as unknown as MailApp;
}

function jmapWithSession(session : any) : MailJmap
{
	const jmap = new MailJmap(createFakeApp());
	(jmap as any).clients["89"] = {session};
	return jmap;
}

describe("MailJmap.injectSharedAccountsRoot()", () =>
{
	it("appends a synthetic 'user' root when the session has a shared account", async() =>
	{
		const jmap = jmapWithSession({
			accounts : {
				ca : {name : "ralf@example.invalid", isPersonal : true},
				u : {name : "info@example.invalid", isPersonal : false},
			},
		});
		const top : any[] = [{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null}];

		await (jmap as any).injectSharedAccountsRoot("89", {isLocal : false}, top);

		assert.lengthOf(top, 2);
		const root = top.find((m) => m.name === "user");
		assert.exists(root, "expected a synthetic 'user' namespace-root entry");
		assert.isTrue(root.hasChildren, "an empty root would be a dead end (ralf's own prior requirement)");
		assert.isNull(root.parentId);
		assert.isTrue(root.isSubscribed, "must survive a subscribedOnly filter pass");
	});

	it("does not inject a duplicate when a real mailbox is already literally named 'user'", async() =>
	{
		// found live, 2026-10-29 (boulder.egroupware.org acc_id=89): a genuine pre-existing
		// top-level mailbox named "user" - isNamespaceRootName() already treats it as the
		// namespace root via its own heuristic, so a second, synthetic one must never be added
		const jmap = jmapWithSession({
			accounts : {
				ca : {name : "ralf@example.invalid", isPersonal : true},
				u : {name : "info@example.invalid", isPersonal : false},
			},
		});
		const top : any[] = [
			{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null},
			{id : "q", name : "user", role : null, parentId : null, hasChildren : false},
		];

		await (jmap as any).injectSharedAccountsRoot("89", {isLocal : false}, top);

		assert.lengthOf(top, 2, "no second 'user' entry");
	});

	it("does nothing when every account is personal (the common case)", async() =>
	{
		const jmap = jmapWithSession({
			accounts : {ca : {name : "ralf@example.invalid", isPersonal : true}},
		});
		const top : any[] = [{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null}];

		await (jmap as any).injectSharedAccountsRoot("89", {isLocal : false}, top);

		assert.lengthOf(top, 1, "no empty/dead 'user' node when nothing is actually shared");
	});

	it("is a no-op for a local/shim account, even without reading the session at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		// no `.clients["89"]` registered at all - a real attempt to read `.session` would throw
		const top : any[] = [{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null}];

		await (jmap as any).injectSharedAccountsRoot("89", {isLocal : true}, top);

		assert.lengthOf(top, 1, "JmapShim accounts already get a real namespace root server-side");
	});

	it("degrades silently (no throw) if reading the session fails", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).clients["89"] = {
			get session() { return Promise.reject(new Error("boom")); },
		};
		const top : any[] = [{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null}];

		await (jmap as any).injectSharedAccountsRoot("89", {isLocal : false}, top);

		assert.lengthOf(top, 1);
	});
});

describe("MailJmap.getSharedAccountPlaceholders()", () =>
{
	it("returns one non-selectable, expandable-into-real-folders placeholder per shared account", async() =>
	{
		const jmap = jmapWithSession({
			accounts : {
				ca : {name : "ralf@example.invalid", isPersonal : true},
				u : {name : "info@example.invalid", isPersonal : false},
				l : {name : "birgit@example.invalid", isPersonal : false},
			},
		});

		const placeholders = await (jmap as any).getSharedAccountPlaceholders("89");

		assert.lengthOf(placeholders, 2, "the personal account must never appear as a shared placeholder");
		const names = placeholders.map((p : any) => p.name).sort();
		assert.deepEqual(names, ["birgit@example.invalid", "info@example.invalid"]);
		for (const p of placeholders)
		{
			assert.isTrue(p.hasChildren, "expanding it must list the account's real folders");
			assert.isTrue(p.noSelect, "a grouping node, not itself a real mailbox");
			assert.equal(p.parentId, (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID);
			assert.isTrue(p.id.startsWith((MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "u") ||
				p.id.startsWith((MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "l"),
				"id must encode the shared accountId for getSharedAccountMailboxChildren() to recover");
		}
	});
});

describe("MailJmap.getSharedAccountMailboxChildren()", () =>
{
	// same jmap-jam invocation-proxy convention as MailJmap.test.ts's own createMailboxProxyStub():
	// requestMany()'s fake ignores buildFn's return value (only real jmap-jam cares about which
	// keys were assigned), but still actually CALLS buildFn so Mailbox.query()'s real args get
	// captured - the resolved data comes from this fake's own canned `resolvedList` instead.
	function jmapWithFakeClient(resolvedList : any[])
	{
		const invocation = () => ({$ref : (_path : string) => ({})});
		const captured : {mailboxQuery : any[]} = {mailboxQuery : []};
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {
					Mailbox : {
						query : (args : any) => { captured.mailboxQuery.push(args); return invocation(); },
						get : (_args : any) => invocation(),
					},
				};
				buildFn(t);
				return [{mailboxes : {list : resolvedList}}];
			},
		};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).clients["89"] = client;
		(jmap as any).tokens["89"] = {
			sessionUrl : "https://example.com", accountId : "ca", access_token : "tok",
			expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		};
		return {jmap, captured};
	}

	it("queries Mailbox/query against the SHARED accountId, not the primary one", async() =>
	{
		const {jmap, captured} = jmapWithFakeClient(
			[{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null, isSubscribed : true, hasChildren : false}]);
		const placeholderId = (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "u" + (MailJmap as any).SHARED_ACCOUNT_SEP;

		const children = await (jmap as any).getSharedAccountMailboxChildren("89", placeholderId);

		assert.equal(captured.mailboxQuery[0].accountId, "u", "must use the shared accountId, not the primary account's own ('ca')");
		assert.isNull(captured.mailboxQuery[0].filter.parentId, "top level of the shared account's own hierarchy");
		assert.lengthOf(children, 1);
		assert.equal(children[0].name, "Inbox");
		assert.isTrue(children[0].noSelect, "not yet a real, openable mailbox - see the method's own docblock");
	});

	it("re-encodes each returned mailbox's own id so a deeper expand still carries the shared accountId", async() =>
	{
		const {jmap} = jmapWithFakeClient(
			[{id : "real-inbox-id", name : "Inbox", role : "inbox", parentId : null, isSubscribed : true, hasChildren : false}]);
		const placeholderId = (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "u" + (MailJmap as any).SHARED_ACCOUNT_SEP;

		const children = await (jmap as any).getSharedAccountMailboxChildren("89", placeholderId);

		assert.equal(children[0].id,
			(MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "u" + (MailJmap as any).SHARED_ACCOUNT_SEP + "real-inbox-id");
	});

	it("never filters by isSubscribed, unlike the primary account's own getMailboxChildren()", async() =>
	{
		// found live (2026-10-29, boulder.egroupware.org acc_id=1, "teachers@..." shared account):
		// every mailbox in a shared account is typically isSubscribed:false (the viewer never
		// subscribed to something they don't own) - a subscribedOnly fetch came back empty, and
		// Et2Tree's own self-correction then made a real, populated shared account look like an
		// empty dead end
		const {jmap, captured} = jmapWithFakeClient([
			{id : "inbox-id", name : "Inbox", role : "inbox", parentId : null, isSubscribed : false, hasChildren : false},
		]);
		const placeholderId = (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "u" + (MailJmap as any).SHARED_ACCOUNT_SEP;

		const children = await (jmap as any).getSharedAccountMailboxChildren("89", placeholderId);

		assert.notProperty(captured.mailboxQuery[0].filter, "isSubscribed");
		assert.lengthOf(children, 1, "an unsubscribed shared mailbox must still show up");
	});

	it("descends a further level using the real parent id recovered from the encoded parentId", async() =>
	{
		const {jmap, captured} = jmapWithFakeClient([]);
		const encodedInboxId = (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "u" + (MailJmap as any).SHARED_ACCOUNT_SEP + "real-inbox-id";

		await (jmap as any).getSharedAccountMailboxChildren("89", encodedInboxId);

		assert.equal(captured.mailboxQuery[0].accountId, "u");
		assert.equal(captured.mailboxQuery[0].filter.parentId, "real-inbox-id");
	});
});

describe("MailJmap.getMailboxChildren() - shared-accounts root sentinel", () =>
{
	it("routes to getSharedAccountPlaceholders() instead of a real Mailbox/query when expanding the synthetic root", async() =>
	{
		const jmap = jmapWithSession({
			accounts : {
				ca : {name : "ralf@example.invalid", isPersonal : true},
				u : {name : "info@example.invalid", isPersonal : false},
			},
		});
		(jmap as any).tokens["89"] = {
			sessionUrl : "https://example.com", accountId : "ca", access_token : "tok",
			expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		};
		let realQueryMade = false;
		(jmap as any).clients["89"].requestMany = async() => { realQueryMade = true; return [{mailboxes : {list : []}}]; };

		const children = await jmap.getMailboxChildren("89", (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID, false);

		assert.isFalse(realQueryMade, "must never make a real JMAP call for the synthetic sentinel parentId");
		assert.lengthOf(children, 1);
		assert.equal(children[0].name, "info@example.invalid");
	});
});
