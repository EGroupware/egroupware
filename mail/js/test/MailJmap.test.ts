import {assert} from "@open-wc/testing";
import {describeJmapError, describeSetError, isPreferenceOn, MailJmap} from "../jmap";
import type {MailApp} from "../app";

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	config: (_name : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {
		egw,
		getCustomLabels: () => ({}),
		updateCustomLabelStylesheet: () => {}
	} as unknown as MailApp;
}

function fakeEmail(overrides : Record<string, any> = {}) : Record<string, any>
{
	return {
		id: "email1",
		subject: "Test subject",
		from: [{name: "Sender", email: "sender@example.com"}],
		to: [],
		cc: [],
		bcc: [],
		keywords: {},
		sentAt: "2026-01-01T00:00:00Z",
		receivedAt: "2026-01-01T00:00:00Z",
		size: 100,
		preview: "a body snippet",
		hasAttachment: false,
		...overrides
	};
}

/**
 * Stub JamClient.requestMany(): captures the "properties" array the production code asks
 * for (the actual regression under test), then resolves with one fixed Email/get result -
 * a real JamClient batches/resolves JMAP method-call thunks, which is irrelevant to what
 * this test verifies, so it's not reimplemented here.
 */
function createFakeClient(email : Record<string, any>, capture : { properties? : string[] })
{
	return {
		requestMany: async(buildFn : (t : any) => any) =>
		{
			const t = {
				Email: {
					get: (args : any) =>
					{
						capture.properties = args.properties;
						return null;
					}
				}
			};
			buildFn(t);
			// A real JMAP server only returns the requested properties - filter the fixture the
			// same way, so a fix that (correctly) stops requesting 'preview' is actually exercised,
			// instead of the assertion passing/failing based on the fixture alone.
			const filtered : Record<string, any> = {};
			(capture.properties || []).forEach((property) => filtered[property] = email[property]);
			return [{emails: {list: [filtered]}}];
		}
	};
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com",
		accountId: "acc1",
		access_token: "tok",
		expires_at: Date.now() + 100000,
		isLocal: false,
		customLabels: {}
	};
	(jmap as any).clients[profileID] = client;
}

/**
 * jmap-jam's requestMany(callback) only assigns a resolvable callId - and only actually sends
 * as a method call at all - to an invocation that is a property of the object the callback
 * returns. getMailboxChildren() built a Mailbox/query invocation ("ids"), used it via
 * ids.$ref('/ids') inside the Mailbox/get call, but only `return {mailboxes}`, silently dropping
 * the Mailbox/query call from the outgoing batch entirely. The result: the server saw a
 * Mailbox/get with an unresolvable "#ids" result-reference - a real JMAP server (Stalwart)
 * rejected it with "missing field `resultOf`"; JmapShim.php (the local plain-IMAP shim) rejected
 * it with the less specific "Failed to resolve result reference for 'ids'" - both symptoms of
 * this one client-side bug, not a server-side issue.
 */
describe("MailJmap.getMailboxChildren() - jmap-jam requestMany() invocation shape", () =>
{
	function createMailboxProxyStub()
	{
		const invocation = () => ({$ref: (_path : string) => ({})});
		return {Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}};
	}

	function createCapturingClient(capture : { returned? : any })
	{
		return {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				capture.returned = buildFn(createMailboxProxyStub());
				return [{mailboxes: {list: []}}];
			}
		};
	}

	it("returns both the Mailbox/query and Mailbox/get invocations from the requestMany() callback", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { returned? : any } = {};
		primeToken(jmap, "1", createCapturingClient(capture));

		await jmap.getMailboxChildren("1", null, true);

		assert.property(capture.returned, "ids",
			"the Mailbox/query invocation must be a key of the returned object, or jmap-jam never " +
			"sends it as part of the batch and the '#ids' result-reference in Mailbox/get breaks");
		assert.property(capture.returned, "mailboxes");
	});
});

/**
 * Fixed top-level display order (ralf's explicit spec, confirmed independent of the folder's
 * actual name/translation): INBOX, Drafts, Templates, Sent, Trash, Junk, Outbox in that exact
 * sequence, then every other folder alphabetically, then the shared/other-users namespace root
 * ("user" on Dovecot/JmapShim's local shim, "shared" on a real JMAP server like Stalwart) always
 * last. Only applies at the top level (isTopLevel) - classic mail_tree.inc.php never reorders
 * anything at a deeper level either.
 */
describe("MailJmap.getMailboxChildren() - top-level sort order", () =>
{
	function createListClient(list : any[])
	{
		return {requestMany: async() => [{mailboxes: {list}}], session: Promise.resolve({accounts: {}})};
	}

	it("orders special-role folders first in the exact fixed sequence, then alphabetical, then the namespace root last", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createListClient([
			{id: "1", name: "user"},
			{id: "2", name: "Zebra"},
			{id: "3", name: "Junk", role: "junk"},
			{id: "4", name: "Posteingang", role: "inbox"},
			{id: "5", name: "Archives"},
			{id: "6", name: "Papierkorb", role: "trash"},
			{id: "7", name: "Vorlagen", role: "templates"},
			{id: "8", name: "Gesendet", role: "sent"},
			{id: "9", name: "Entwürfe", role: "drafts"},
			{id: "10", name: "Postausgang", role: "outbox"},
		]));

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.deepEqual(result.map((m : any) => m.id),
			["4", "9", "7", "8", "6", "3", "10", "5", "2", "1"]);
	});

	it("does not reorder a non-top-level fetch", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createListClient([{id: "a", name: "shared"}, {id: "b", name: "alice"}]));

		const result = await jmap.getMailboxChildren("1", "someParentId", false);

		assert.deepEqual(result.map((m : any) => m.name), ["shared", "alice"]);
	});
});

/**
 * RFC 8621's Mailbox object has no hasChildren property at all - unlike JmapShim's local IMAP
 * LIST attributes, a real JMAP server (Stalwart) never tells the client whether a mailbox has
 * children, so every node looked "expandable" until clicked once and found empty. Resolved via
 * one extra batched Mailbox/query{parentId, limit:1} per still-unknown node, in a single
 * additional round-trip (JMAP's own method-call batching) - deliberately skipped for
 * JmapShim/local-shim accounts, whose "list children" mode does a real per-call IMAP LIST and
 * would reintroduce the N+1 problem JmapShim::mailboxGetInternal()'s own batching fix just
 * solved (mail/src/JmapShim.php).
 */
describe("MailJmap.getMailboxChildren() - resolveHasChildren for real JMAP accounts", () =>
{
	function createSequencedClient(responses : any[])
	{
		let call = 0;
		return {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}});
				return [responses[call++]];
			}
		};
	}

	it("resolves hasChildren via one extra batched query for a real (non-local) JMAP account", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createSequencedClient([
			{mailboxes: {list: [{id: "a", name: "Leaf"}, {id: "b", name: "Parent"}]}},
			{c0: {ids: []}, c1: {ids: ["grandchild"]}},
		]));

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.isFalse(result.find((m : any) => m.id === "a").hasChildren);
		assert.isTrue(result.find((m : any) => m.id === "b").hasChildren);
	});

	it("does not issue the extra query for a local-shim account", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let calls = 0;
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				calls++;
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}});
				return [{mailboxes: {list: [{id: "a", name: "Leaf"}]}}];
			}
		};
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: true, customLabels: {},
		};
		(jmap as any).clients["1"] = client;

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.equal(calls, 1, "must not issue a second batch for a local-shim account");
		assert.isUndefined(result[0].hasChildren);
	});

	it("leaves an already-known hasChildren value alone (no extra query needed)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let calls = 0;
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				calls++;
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}});
				return [{mailboxes: {list: [{id: "a", name: "Leaf", hasChildren: false}]}}];
			}
		};
		primeToken(jmap, "1", client);

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.equal(calls, 1, "already-known hasChildren must not trigger the extra batch");
		assert.isFalse(result[0].hasChildren);
	});
});

/**
 * ralf's report: the "shared"/"user" namespace root must not look like a dead end in the main
 * index when something is shared with this user but they haven't subscribed to any of it yet -
 * see folderTree.ts's isVisibleNamespaceRoot(), which reads this hasSubscribedChildren field.
 */
describe("MailJmap.getMailboxChildren() - hasSubscribedChildren for a namespace root", () =>
{
	function createNamespaceRootClient(subscribedChildIds : string[])
	{
		let call = 0;
		return {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}});
				// call 0: the top-level Mailbox/query+get; call 1: resolveHasChildren's per-node
				// "any child" check; call 2: resolveHasChildren's namespace-root "subscribed child" check
				return [[
					{mailboxes: {list: [{id: "a", name: "shared", hasChildren: true}]}},
					{s0: {ids: subscribedChildIds}},
				][call++]];
			},
			session: Promise.resolve({accounts: {}}),
		};
	}

	it("sets hasSubscribedChildren:true when the root has at least one subscribed child", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createNamespaceRootClient(["c1"]));

		const result : any = await jmap.getMailboxChildren("1", null, true, true);

		assert.isTrue(result[0].hasSubscribedChildren);
	});

	it("sets hasSubscribedChildren:false when nothing under the root is subscribed", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createNamespaceRootClient([]));

		const result : any = await jmap.getMailboxChildren("1", null, true, true);

		assert.isFalse(result[0].hasSubscribedChildren);
	});

	it("never checks hasSubscribedChildren when subscribedOnly is off", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let calls = 0;
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				calls++;
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}});
				return [{mailboxes: {list: [{id: "a", name: "shared", hasChildren: true}]}}];
			},
			session: Promise.resolve({accounts: {}}),
		};
		primeToken(jmap, "1", client);

		const result : any = await jmap.getMailboxChildren("1", null, true, false);

		assert.equal(calls, 1, "no extra query when subscribedOnly is off");
		assert.isUndefined(result[0].hasSubscribedChildren);
	});
});

/**
 * ralf's report: the "Edit folder ACL" tree action vanished on master because nothing populated
 * the INBOX node's `data.acl` any more after the initial-tree eager-branch-load was removed for
 * performance (see MailApp.aclEnabled(), mail/js/app.ts, and folderTree.ts's buildNode()). For a
 * real JMAP account this is resolved client-side from the JMAP session's own accountCapabilities.
 */
describe("MailJmap.getMailboxChildren() - resolveAclCapable for real JMAP accounts", () =>
{
	function createInboxClient(accountCapabilities : Record<string, any>)
	{
		return {
			requestMany: async() => [{mailboxes: {list: [{id: "a", name: "Inbox", role: "inbox", hasChildren: false}]}}],
			session: Promise.resolve({accounts: {acc1: {accountCapabilities}}}),
		};
	}

	it("sets aclCapable:true when the session advertises mail:share for this account", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createInboxClient({"urn:ietf:params:jmap:mail:share": {}}));

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.isTrue(result.find((m : any) => m.role === "inbox").aclCapable);
	});

	it("sets aclCapable:false when mail:share isn't advertised", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createInboxClient({}));

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.isFalse(result.find((m : any) => m.role === "inbox").aclCapable);
	});

	it("never touches aclCapable for a non-top-level fetch, even if it somehow contains role:inbox", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let sessionRead = false;
		const client = {
			requestMany: async() => [{mailboxes: {list: [{id: "a", name: "Inbox", role: "inbox", hasChildren: false}]}}],
			get session()
			{
				sessionRead = true;
				return Promise.resolve({accounts: {acc1: {accountCapabilities: {}}}});
			},
		};
		primeToken(jmap, "1", client);

		const result : any = await jmap.getMailboxChildren("1", "someParentId", false);

		assert.isFalse(sessionRead, "resolveAclCapable must not run for a non-top-level fetch");
		assert.isUndefined(result[0].aclCapable);
	});

	it("does not issue an extra query for a local-shim account (aclCapable already arrives from JmapShim)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let sessionRead = false;
		const client = {
			requestMany: async() => [{mailboxes: {list: [{id: "a", name: "Inbox", role: "inbox", aclCapable: true}]}}],
			get session()
			{
				sessionRead = true;
				return Promise.resolve({accounts: {}});
			},
		};
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: true, customLabels: {},
		};
		(jmap as any).clients["1"] = client;

		const result : any = await jmap.getMailboxChildren("1", null, true);

		assert.isFalse(sessionRead, "resolveAclCapable must never run for a local-shim account");
		assert.isTrue(result[0].aclCapable);
	});
});

/**
 * Templates/Outbox have no IMAP SPECIAL-USE attribute or JMAP role at all - JmapShim already
 * resolves them server-side via acc_folder_template/acc_folder_outbox for the local shim, but a
 * real JMAP server (Stalwart) has no equivalent mechanism. getMailboxChildren() matches by the
 * account's own configured folder name instead (from the JMAP bootstrap payload, see
 * ProfileHandler::jmapBootstrap()), case-insensitively, so folderTree.ts's buildNode() sees an
 * already-correct mailbox.role regardless of backend.
 */
describe("MailJmap.getMailboxChildren() - templates/outbox role resolution by account-configured name", () =>
{
	function createListClient(list : any[])
	{
		return {requestMany: async() => [{mailboxes: {list}}], session: Promise.resolve({accounts: {}})};
	}

	it("assigns role='templates'/'outbox' by matching the account's configured folder name", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
			templatesFolder: "Vorlagen", outboxFolder: "Postausgang",
		};
		(jmap as any).clients["1"] = createListClient([
			{id: "a", name: "Vorlagen"}, {id: "b", name: "Postausgang"}, {id: "c", name: "INBOX", role: "inbox"},
		]);

		const result : any = await jmap.getMailboxChildren("1", "someParentId", true);

		assert.equal(result.find((m : any) => m.id === "a").role, "templates");
		assert.equal(result.find((m : any) => m.id === "b").role, "outbox");
		assert.equal(result.find((m : any) => m.id === "c").role, "inbox", "an already-set role must not be overwritten");
	});

	it("does not assign a role when the account has no configured templates/outbox folder", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createListClient([{id: "a", name: "Vorlagen"}]));

		const result : any = await jmap.getMailboxChildren("1", "someParentId", true);

		assert.isUndefined(result[0].role);
	});

	it("does not match at all when isTopLevel is false", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
			templatesFolder: "Vorlagen", outboxFolder: "Postausgang",
		};
		(jmap as any).clients["1"] = createListClient([{id: "a", name: "Vorlagen"}]);

		const result : any = await jmap.getMailboxChildren("1", "someDeepParentId", false);

		assert.isUndefined(result[0].role);
	});
});

/**
 * INBOX is always auto-expanded on initial render (folderTree.ts's buildNode()), so without this,
 * Et2Tree would immediately fire its own separate, purely reactive lazy-load request for it right
 * after the root level renders - ralf's observation: an extra "2nd request" on every single page
 * load. getRootFolders() folds "find INBOX's id" into the very same request as the root-level
 * fetch (independent of it, so no extra round trip), then fires the children request immediately
 * once that resolves - still two requests (a JMAP result reference can't target a field nested
 * inside another argument, like filter.parentId, so the children query can't be chained into the
 * very first request), but both fire back-to-back in one code path instead of only the first
 * being followed by a full render + Et2Tree lazy-load-event round trip before the second starts.
 */
describe("MailJmap.getRootFolders() - combined root + INBOX-children fetch", () =>
{
	function createRootClient(rootResponse : any, childrenResponse : any)
	{
		let calls = 0;
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				calls++;
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({Mailbox: {query: (_args : any) => invocation(), get: (_args : any) => invocation()}});
				return [calls === 1 ? rootResponse : childrenResponse];
			},
		};
		return {client, calls: () => calls};
	}

	function primeLocalToken(jmap : MailJmap, profileID : string, client : any) : void
	{
		// isLocal:true so resolveHasChildren() never fires here - that mechanism (and its own
		// separate requestMany() call) is already covered by its own describe block above; this
		// suite only cares about the root+INBOX request-count/wiring, not hasChildren resolution
		(jmap as any).tokens[profileID] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: true, customLabels: {},
		};
		(jmap as any).clients[profileID] = client;
	}

	it("fetches the root level and INBOX's id in one request, then INBOX's children in a second", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, calls} = createRootClient(
			{
				topMailboxes: {list: [{id: "root1", name: "INBOX", role: "inbox"}, {id: "root2", name: "Archive"}]},
				inboxIds: {ids: ["root1"]},
			},
			{mailboxes: {list: [{id: "child1", name: "Sub"}]}}
		);
		primeLocalToken(jmap, "1", client);

		const result = await jmap.getRootFolders("1");

		assert.equal(calls(), 2, "must be exactly two requests: root+inboxId together, then inbox children");
		assert.deepEqual(result.top.map((m : any) => m.id), ["root1", "root2"]);
		assert.deepEqual(result.inboxChildren.map((m : any) => m.id), ["child1"]);
		assert.equal((jmap as any).mailboxIds["1::INBOX"], "root1",
			"resolved INBOX id must be cached so a later mailboxId('INBOX') lookup doesn't repeat it");
	});

	it("resolves inboxChildren: null and skips the second request when INBOX can't be found", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, calls} = createRootClient(
			{topMailboxes: {list: [{id: "root2", name: "Archive"}]}, inboxIds: {ids: []}},
			null
		);
		primeLocalToken(jmap, "1", client);

		const result = await jmap.getRootFolders("1");

		assert.equal(calls(), 1, "must not issue a second request when no INBOX id was found");
		assert.isNull(result.inboxChildren);
	});

	it("resolves null (same contract as getMailboxChildren) when the account has no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = await jmap.getRootFolders("nonexistent-profile");
		assert.isNull(result);
	});
});

/**
 * The folder-management dialog manages folders, including unsubscribed ones, so it must always
 * see every folder regardless of the showAllFoldersInFolderPane preference - matching classic
 * mail_tree.inc.php's own folderManagement()/ajax_folderMgmtTree_autoloading() calls, which
 * hardcoded $_subscribedOnly=false the same way. The main browsing tree passes no override at all
 * and keeps following that preference exactly as before.
 */
describe("MailJmap.getMailboxChildren()/getRootFolders() - subscribedOnly override", () =>
{
	function createFilterCapturingClient(capture : { filters : any[] })
	{
		return {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({
					Mailbox: {
						query: (args : any) => { capture.filters.push(args.filter); return invocation(); },
						get: (_args : any) => invocation(),
					}
				});
				return [{mailboxes: {list: []}}];
			}
		};
	}

	it("getMailboxChildren(): adds isSubscribed:true when subscribedOnly is explicitly true, ignoring preference", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { filters : any[] } = {filters: []};
		primeToken(jmap, "1", createFilterCapturingClient(capture));

		await jmap.getMailboxChildren("1", null, true, true);

		assert.isTrue(capture.filters[0].isSubscribed);
	});

	it("getMailboxChildren(): omits isSubscribed when subscribedOnly is explicitly false, ignoring preference", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { filters : any[] } = {filters: []};
		primeToken(jmap, "1", createFilterCapturingClient(capture));

		await jmap.getMailboxChildren("1", null, true, false);

		assert.isUndefined(capture.filters[0].isSubscribed);
	});

	it("getMailboxChildren(): falls back to the showAllFoldersInFolderPane preference when omitted", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { filters : any[] } = {filters: []};
		primeToken(jmap, "1", createFilterCapturingClient(capture));

		await jmap.getMailboxChildren("1", null, true);

		assert.isTrue(capture.filters[0].isSubscribed,
			"shared egw stub's preference() returns null -> showAllFoldersInFolderPane off -> subscribedOnly");
	});

	it("getRootFolders(): propagates subscribedOnly:false to both the root query and the INBOX-children fetch", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let call = 0;
		const filters : any[] = [];
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				call++;
				const invocation = () => ({$ref: (_path : string) => ({})});
				buildFn({
					Mailbox: {
						query: (args : any) => { filters.push(args.filter); return invocation(); },
						get: (_args : any) => invocation(),
					}
				});
				return call === 1
					? [{topMailboxes: {list: [{id: "root1", name: "INBOX", role: "inbox"}]}, inboxIds: {ids: ["root1"]}}]
					: [{mailboxes: {list: []}}];
			}
		};
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: true, customLabels: {},
		};
		(jmap as any).clients["1"] = client;

		await jmap.getRootFolders("1", false);

		// filters[0] = root query, filters[1] = INBOX-id lookup ({name:'INBOX'}, never has isSubscribed),
		// filters[2] = INBOX-children query
		assert.isUndefined(filters[0].isSubscribed, "root query must omit isSubscribed");
		assert.isUndefined(filters[2].isSubscribed, "INBOX-children query must also omit isSubscribed");
	});
});

describe("MailJmap.fetchRows() held-back push refresh - preview snippet", () =>
{
	/**
	 * Contract: fetchRows()'s {refresh: [...]} branch (used both for egw.dataRefreshUID() and
	 * for a push 'add'/'update' NextMatch held back while this browser tab wasn't active, then
	 * applied on return - see Et2Datagrid's refresh handling) must respect the same "Sneak
	 * preview in list" (filter2) setting getRows() (the normal listing fetch) already does -
	 * not fetch/include a body-preview snippet when that setting is off.
	 *
	 * Setup: fetchRows() with a {refresh: [rowId]} range and filter2 falsy, against a stubbed
	 * JMAP client that captures the requested Email/get properties and returns a fixed Email
	 * with a non-empty "preview" field (as a real server might, if asked for it).
	 *
	 * Pass: 'preview' is NOT in the requested properties, and the resulting row's
	 * "bodypreview" field is empty - proving the client discarded/never asked for the snippet.
	 */
	it("does not request or include the preview snippet when the setting is off", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		primeToken(jmap, "1", createFakeClient(fakeEmail(), capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.isOk(result, "fetchRows() should resolve a real result, not false");
		assert.notInclude(capture.properties, "preview",
			"Email/get must not request 'preview' when filter2 is falsy");
		assert.equal(result.data["1::1::mbox1::email1"].bodypreview, "",
			"row must have no snippet when the setting is off, even if the server sent one back");
	});

	/**
	 * Contract: the same {refresh: [...]} branch DOES request/include the preview snippet when
	 * the "Sneak preview in list" setting is on - this is the positive-case counterpart, so a
	 * future change that stops requesting 'preview' unconditionally (over-fixing this bug in
	 * the other direction) also fails a test.
	 */
	it("requests and includes the preview snippet when the setting is on", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		primeToken(jmap, "1", createFakeClient(fakeEmail(), capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: "on"}, "widget", [], 0);

		assert.include(capture.properties, "preview",
			"Email/get must request 'preview' when filter2 is truthy");
		assert.equal(result.data["1::1::mbox1::email1"].bodypreview, "a body snippet",
			"row must carry the server's snippet when the setting is on");
	});
});

/**
 * Contract: mail no longer registers a server-side 'get_rows' callback (see
 * ProfileHandler::jmapBootstrap()'s docblock: "there is no server-side row-fetch fallback
 * anymore, the client surfaces this as an error") - egw.dataFetch() (api/js/jsapi/egw_data.ts)
 * only falls back to the classic (dead) ajax_get_rows POST when a registered fetch callback
 * resolves/returns a falsy value, so fetchRows()/refreshRows() must never do that: every decline
 * is answered directly, with a real error message for the cases that are genuine failures.
 */
describe("MailJmap.fetchRows() - never falls back to the dead classic ajax_get_rows endpoint", () =>
{
	it("resolves a real (empty) result and surfaces an error when the account has no JMAP token", async() =>
	{
		const messages : Array<[string, string]> = [];
		const app = createFakeApp();
		(app as any).egw = {...egw, message: (msg : string, type : string) => messages.push([msg, type])};
		const jmap = new MailJmap(app);

		const result : any = await jmap.fetchRows("exec", {start: 0, num_rows: 50},
			{selectedFolder: "1::INBOX"}, "widget", [], 0);

		assert.isOk(result, "fetchRows() must resolve a real result, never false");
		assert.deepEqual(result.order, []);
		assert.equal(messages.length, 1, "a genuine failure must surface exactly one error message");
		assert.equal(messages[0][1], "error");
	});

	it("resolves a real (empty) result without an error when selectedFolder can't be determined yet", async() =>
	{
		const messages : Array<[string, string]> = [];
		const app = createFakeApp();
		(app as any).egw = {...egw, message: (msg : string, type : string) => messages.push([msg, type])};
		const jmap = new MailJmap(app);

		const result : any = await jmap.fetchRows("exec", {start: 0, num_rows: 50}, {}, "widget", [], 0);

		assert.isOk(result, "fetchRows() must resolve a real result, never false");
		assert.deepEqual(result.order, []);
		assert.equal(messages.length, 0, "a one-time startup race is not a failure worth surfacing");
	});

	it("resolves a real (empty) result for the unused parent/children (csv_export) branch", async() =>
	{
		const jmap = new MailJmap(createFakeApp());

		const result : any = await jmap.fetchRows("exec", {parent_id: "1"}, {}, "widget", [], 0);

		assert.isOk(result, "fetchRows() must resolve a real result, never false");
		assert.deepEqual(result.order, []);
	});

	it("resolves a real (empty) result when every row id in a refresh batch is malformed", async() =>
	{
		const jmap = new MailJmap(createFakeApp());

		const result : any = await jmap.fetchRows("exec", {refresh: ["bogus"]}, {}, "widget", [], 0);

		assert.isOk(result, "fetchRows() must resolve a real result, never false");
		assert.deepEqual(result.order, []);
	});
});

/**
 * Contract: MailJmap.getQuota() fetches quota directly via JMAP, never falling back to the
 * classic ajax_refreshQuotaDisplay() round-trip for a local/shim account (JmapShim implements
 * Quota/get by wrapping the exact same classic IMAP lookup that fallback would otherwise use -
 * see mail/src/JmapShim.php's quotaGet()/quotaFromImap()) - only a real JMAP server without the
 * Quota extension is worth falling back for.
 */
describe("MailJmap.getQuota()", () =>
{
	function primeQuotaToken(jmap : MailJmap, profileID : string, isLocal : boolean, client : any) : void
	{
		(jmap as any).tokens[profileID] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal, customLabels: {},
		};
		(jmap as any).clients[profileID] = client;
	}

	function fakeQuotaClient(capabilities : Record<string, any>, list : any[], requestCalls : any[]) : any
	{
		return {
			session: Promise.resolve({capabilities}),
			request: async([method, args] : any) =>
			{
				requestCalls.push([method, args]);
				return [{list}, {}];
			},
		};
	}

	it("returns formatted data from a real JMAP server that advertises the Quota extension", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const requestCalls : any[] = [];
		const client = fakeQuotaClient(
			{"urn:ietf:params:jmap:quota": {}},
			[{id: "mail", resourceType: "octets", scope: "account", used: 100 * 1024, hardLimit: 1000 * 1024}],
			requestCalls);
		primeQuotaToken(jmap, "1", false, client);

		const result : any = await jmap.getQuota("1");

		assert.equal(requestCalls.length, 1);
		assert.equal(requestCalls[0][0], "Quota/get");
		assert.equal(result.data.profileid, "1");
		assert.equal(result.data.quotainpercent, "10");
		assert.equal(result.data.quotaclass, "mail-index_QuotaGreen");
	});

	it("resolves null (caller falls back to classic) for a real JMAP server without the Quota extension", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client = fakeQuotaClient({}, [], []);
		primeQuotaToken(jmap, "1", false, client);

		assert.isNull(await jmap.getQuota("1"));
	});

	it("never falls back for a local/shim account - answers 'not supported' directly instead", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client = fakeQuotaClient({}, [], []);
		primeQuotaToken(jmap, "1", true, client);

		const result : any = await jmap.getQuota("1");

		assert.isOk(result, "must resolve a real result, never null, for a local/shim account");
		assert.equal(result.data.quotaclass, "mail_DisplayNone");
	});

	it("caches the result so a second call within the TTL doesn't re-fetch", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const requestCalls : any[] = [];
		const client = fakeQuotaClient(
			{"urn:ietf:params:jmap:quota": {}},
			[{id: "mail", resourceType: "octets", scope: "account", used: 1024, hardLimit: 1024 * 1024}],
			requestCalls);
		primeQuotaToken(jmap, "1", false, client);

		await jmap.getQuota("1");
		await jmap.getQuota("1");

		assert.equal(requestCalls.length, 1, "second call within the TTL must be served from cache");
	});

	it("invalidateQuota() forces the next call to re-fetch instead of serving the cache", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const requestCalls : any[] = [];
		const client = fakeQuotaClient(
			{"urn:ietf:params:jmap:quota": {}},
			[{id: "mail", resourceType: "octets", scope: "account", used: 1024, hardLimit: 1024 * 1024}],
			requestCalls);
		primeQuotaToken(jmap, "1", false, client);

		await jmap.getQuota("1");
		jmap.invalidateQuota("1");
		await jmap.getQuota("1");

		assert.equal(requestCalls.length, 2, "invalidateQuota() must clear the cached entry");
	});
});

/**
 * Contract: MailJmap.email2row() flags a From/To/Cc/Bcc field as "suspect" when the server's
 * own address-list parsing left an entry with no usable email address - the shape a real JMAP
 * server's (eg. Stalwart's) address parser produces when it isn't RFC 2047-aware and mis-splits
 * a sending MUA's malformed encoded-word (a literal, unencoded comma inside a quoted display
 * name - valid per RFC 2047, but breaks a naive comma-split). MailApp.renderMessageInto()
 * (mail/js/app.ts) uses this flag to trigger an on-demand repair - see
 * mail_ui::ajax_parseAddressList()'s docblock for the full story.
 */
describe("MailJmap.email2row() - suspect address field detection", () =>
{
	it("flags a field whose address list has an entry with no usable email", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		const email = fakeEmail({
			cc: [{name: "Jane Doe", email: "jane.doe@example.com"}, {name: "broken fragment", email: ""}],
		});
		primeToken(jmap, "1", createFakeClient(email, capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.include(result.data["1::1::mbox1::email1"].suspectAddressFields, "cc");
	});

	/**
	 * Real-world shape found live against Stalwart: the address boundary is found correctly
	 * (a valid, complete email address), but the display-name decode leaves stray backslashes/
	 * quotes behind - eg. name: '\\"Example_Corp", " Consulting\\"' for what should have been
	 * "Example Corp, Consulting". A literal backslash never legitimately appears in a decoded
	 * display name, so this is a reliable "something went wrong" signal on its own, even though
	 * the email address itself is completely valid.
	 */
	it("flags a field whose entry has a valid email but a backslash-mangled display name", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		const email = fakeEmail({
			cc: [{name: "Jane Doe", email: "jane.doe@example.com"},
				{name: '\\"Example Corp", " Consulting\\"', email: "info@example.com"}],
		});
		primeToken(jmap, "1", createFakeClient(email, capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.include(result.data["1::1::mbox1::email1"].suspectAddressFields, "cc");
	});

	it("does not flag a well-formed address list", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { properties? : string[] } = {};
		const email = fakeEmail({
			cc: [{name: "Jane Doe", email: "jane.doe@example.com"}, {name: "John Smith", email: "john.smith@example.com"}],
		});
		primeToken(jmap, "1", createFakeClient(email, capture));

		const result : any = await jmap.fetchRows("exec", {refresh: ["1::1::mbox1::email1"]},
			{filter2: ""}, "widget", [], 0);

		assert.deepEqual(result.data["1::1::mbox1::email1"].suspectAddressFields, []);
	});
});

/** {$ref} invocation stub shared by the threaded-view fakes below - see createMailboxProxyStub(). */
function invocationStub()
{
	return {$ref: (_path : string) => ({})};
}

/**
 * Stub JamClient.requestMany() for MailJmap.getThreadedRows()/getThreadMemberRows() (doc/ai/
 * projects/mail-threaded-view.md, Phase 1) - also has to answer mailboxId()'s own, earlier
 * Mailbox/query call (getRows()/getThreadMemberRows() both resolve the folder path before
 * branching into threading logic at all), so responses are picked by which entity/method the
 * production code actually invoked in a given call, not by call order.
 */
function createThreadedFakeClient(representatives : any[], membersByThread : Record<string, any[]>)
{
	return {
		requestMany: async(buildFn : (t : any) => any) =>
		{
			let calledMailboxQuery = false, calledEmailQuery = false, calledThreadGet = false;
			const t = {
				Mailbox: {query: (_args : any) => { calledMailboxQuery = true; return invocationStub(); }},
				Email: {
					query: (_args : any) => { calledEmailQuery = true; return invocationStub(); },
					get: (_args : any) => invocationStub(),
				},
				Thread: {get: (_args : any) => { calledThreadGet = true; return invocationStub(); }},
			};
			buildFn(t);
			if (calledMailboxQuery)
			{
				return [{ids: {ids: ["mbox1"]}}];
			}
			if (calledEmailQuery)
			{
				return [{
					ids: {ids: representatives.map((r) => r.id), total: representatives.length},
					emails: {list: representatives},
				}];
			}
			if (calledThreadGet)
			{
				// getThreadedRows() destructures {members}, getThreadMemberRows() destructures
				// {emails} from this same first tuple element - both keys point at the same list
				// so either caller's destructuring picks up what it needs.
				const list = Object.values(membersByThread).flat();
				return [{members: {list}, emails: {list}}];
			}
			throw new Error("createThreadedFakeClient(): unexpected requestMany() call shape in test");
		}
	};
}

/**
 * doc/ai/projects/mail-threaded-view.md, Phase 1 - dead code in production until
 * ProfileHandler::THREADING_ENABLED ships (nothing sets query.threaded, and no token reports
 * supportsThreading:true yet either way), but exercised directly here via getRows()/fetchRows()'s
 * public surface, same as the rest of this file does for row-building logic.
 */
describe("MailJmap.getRows() - threaded view (Phase 1)", () =>
{
	it("renders a single-message thread via the ordinary row shape, no is_parent/thread_id", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const email = fakeEmail({id: "email1", threadId: "t1"});
		primeToken(jmap, "1", createThreadedFakeClient([email], {}));
		(jmap as any).tokens["1"].supportsThreading = true;

		const result = await jmap.getRows({selectedFolder: "1::INBOX", threaded: true});

		assert.equal(result!.rows.length, 1);
		assert.isUndefined(result!.rows[0].is_parent);
		assert.notInclude(result!.rows[0].row_id, "thread:");
	});

	it("aggregates a multi-message thread: unseen if any member is unseen, is_parent, thread_count", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const representative = fakeEmail({id: "email1", threadId: "t1", keywords: {"$seen": true}});
		const members = {
			t1: [
				{id: "email1", threadId: "t1", keywords: {"$seen": true}},
				{id: "email2", threadId: "t1", keywords: {}},	// unseen
			],
		};
		primeToken(jmap, "1", createThreadedFakeClient([representative], members));
		(jmap as any).tokens["1"].supportsThreading = true;

		const result = await jmap.getRows({selectedFolder: "1::INBOX", threaded: true});

		const row = result!.rows[0];
		assert.isTrue(row.is_parent);
		assert.equal(row.thread_id, "t1");
		assert.equal(row.thread_count, 2);
		assert.include(row.class, "unseen",
			"the representative alone is $seen, but the thread as a whole must show unseen " +
			"because a member isn't - AND-folded, not just copied from the representative");
		assert.include(row.row_id, "thread:t1");
	});

	it("marks a thread read only once every member is $seen", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const representative = fakeEmail({id: "email1", threadId: "t1", keywords: {"$seen": true}});
		const members = {
			t1: [
				{id: "email1", threadId: "t1", keywords: {"$seen": true}},
				{id: "email2", threadId: "t1", keywords: {"$seen": true}},
			],
		};
		primeToken(jmap, "1", createThreadedFakeClient([representative], members));
		(jmap as any).tokens["1"].supportsThreading = true;

		const result = await jmap.getRows({selectedFolder: "1::INBOX", threaded: true});

		assert.notInclude(result!.rows[0].class, "unseen");
	});

	it("falls back to the ordinary flat list when the profile doesn't support threading, even if asked for", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const email = fakeEmail({id: "email1", threadId: "t1"});
		let threadGetCalled = false;
		const client = {
			requestMany: async(buildFn : (t : any) => any) =>
			{
				let calledMailboxQuery = false;
				const t = {
					Mailbox: {query: (_args : any) => { calledMailboxQuery = true; return invocationStub(); }},
					Email: {query: (_args : any) => invocationStub(), get: (_args : any) => invocationStub()},
					Thread: {get: (_args : any) => { threadGetCalled = true; return invocationStub(); }},
				};
				buildFn(t);
				if (calledMailboxQuery)
				{
					return [{ids: {ids: ["mbox1"]}}];
				}
				return [{ids: {ids: [email.id], total: 1}, emails: {list: [email]}}];
			}
		};
		primeToken(jmap, "1", client);	// primeToken()'s default supportsThreading is falsy

		const result = await jmap.getRows({selectedFolder: "1::INBOX", threaded: true});

		assert.isFalse(threadGetCalled, "threaded: true must be ignored when the profile can't support it");
		assert.isUndefined(result!.rows[0].is_parent);
	});

	it("fetchRows()'s parent_id branch expands a thread row back into its plain member rows", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const members = [
			fakeEmail({id: "email1", subject: "First"}),
			fakeEmail({id: "email2", subject: "Second"}),
		];
		primeToken(jmap, "1", createThreadedFakeClient([], {t1: members}));
		(jmap as any).tokens["1"].supportsThreading = true;

		const result : any = await jmap.fetchRows("exec", {parent_id: "1::1::mbox1::thread:t1"},
			{selectedFolder: "1::INBOX"}, "widget", [], 0);

		assert.equal(result.order.length, 2);
		assert.notInclude(result.order.join(), "thread:",
			"expanded rows are plain messages, not nested thread rows - RFC 8621 threads are flat");
	});

	it("still answers empty for a parent_id that isn't one of this feature's thread rows", async() =>
	{
		const jmap = new MailJmap(createFakeApp());

		const result : any = await jmap.fetchRows("exec", {parent_id: "1::1::mbox1::email1"},
			{selectedFolder: "1::INBOX"}, "widget", [], 0);

		assert.deepEqual(result.order, []);
	});
});

/**
 * doc/ai/projects/mail-threaded-view.md, "Bulk actions on collapsed thread rows" - ralf's
 * decision: a bulk action on a thread row applies to every member message. This is the one place
 * that expansion actually happens (app.ts's expandedSelectionCount() only mirrors the *counting*
 * half, off the already-cached thread_count, no round trip needed for that).
 */
describe("MailJmap.expandThreadRowIds() - bulk-action expansion (Phase 1)", () =>
{
	function primeRowData(jmap : MailJmap, rowsById : Record<string, any>) : void
	{
		const app = (jmap as any).app;
		app.egw = {...egw, dataGetUIDdata: (id : string) => rowsById[id] ? {data: rowsById[id]} : undefined};
	}

	it("passes plain message row ids through unchanged (no thread rows involved)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeRowData(jmap, {});

		const result = await jmap.expandThreadRowIds(["1::1::mbox1::email1", "1::1::mbox1::email2"]);

		assert.deepEqual(result, ["1::1::mbox1::email1", "1::1::mbox1::email2"]);
	});

	it("expands a thread-parent row id into its real member row ids", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const threadRowId = "1::1::mbox1::thread:t1";
		primeRowData(jmap, {[threadRowId]: {is_parent: true, thread_id: "t1", thread_count: 2}});
		primeToken(jmap, "1", createThreadedFakeClient([], {t1: [{id: "email1"}, {id: "email2"}]}));

		const result = await jmap.expandThreadRowIds([threadRowId]);

		assert.deepEqual(result, ["1::1::mbox1::email1", "1::1::mbox1::email2"]);
	});

	it("expands a thread row in place, keeping ordinary ids around it in order", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const threadRowId = "1::1::mbox1::thread:t1";
		primeRowData(jmap, {[threadRowId]: {is_parent: true, thread_id: "t1", thread_count: 2}});
		primeToken(jmap, "1", createThreadedFakeClient([], {t1: [{id: "email2"}, {id: "email3"}]}));

		const result = await jmap.expandThreadRowIds(["1::1::mbox1::email1", threadRowId, "1::1::mbox1::email4"]);

		assert.deepEqual(result, ["1::1::mbox1::email1", "1::1::mbox1::email2", "1::1::mbox1::email3", "1::1::mbox1::email4"]);
	});

	it("leaves a thread row id unchanged if it can't be expanded (no usable token)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const threadRowId = "1::1::mbox1::thread:t1";
		primeRowData(jmap, {[threadRowId]: {is_parent: true, thread_id: "t1", thread_count: 2}});
		// no primeToken() call - ensureToken("1") resolves null (no cached token, no server to ask)

		const result = await jmap.expandThreadRowIds([threadRowId]);

		assert.deepEqual(result, [threadRowId]);
	});

	it("does not touch a row whose is_parent is set but with no cached thread_id", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const rowId = "1::1::mbox1::thread:t1";
		primeRowData(jmap, {[rowId]: {is_parent: true}});	// no thread_id - malformed/unexpected shape

		const result = await jmap.expandThreadRowIds([rowId]);

		assert.deepEqual(result, [rowId]);
	});
});

/**
 * A checkbox-style egw preference's stored value is the server's raw string, often literally "0"
 * for "off" - a non-empty JS string is otherwise always truthy, so a plain `!egw.preference(...)`
 * silently treats a "0"-stored (off) preference as on. Bit both MailJmap.getMailboxChildren()'s
 * isSubscribed filter and MailApp.buildFolderLevelData()'s display filter, which both used
 * the preference value directly - confirmed live against a real "showAllFoldersInFolderPane"
 * preference stored as "0".
 */
describe("isPreferenceOn() - PHP-style boolean preference string", () =>
{
	it("is false for a '0'-stored (off) preference, unlike plain JS truthiness", () =>
	{
		assert.isFalse(isPreferenceOn("0"));
	});

	it("is true for a '1'-stored (on) preference", () =>
	{
		assert.isTrue(isPreferenceOn("1"));
	});

	it("is false for an empty/missing preference", () =>
	{
		assert.isFalse(isPreferenceOn(""));
		assert.isFalse(isPreferenceOn(undefined));
		assert.isFalse(isPreferenceOn(null));
	});
});

describe("describeJmapError() - classify a caught jmap-jam rejection", () =>
{
	it("formats a single {type, description} error object using description", () =>
	{
		assert.equal(describeJmapError({type: "invalidArguments", description: "bad name"}), "bad name");
	});

	it("falls back to type when description is missing", () =>
	{
		assert.equal(describeJmapError({type: "accountNotFound"}), "accountNotFound");
	});

	it("joins an array of error objects (requestMany()'s shape) with '; '", () =>
	{
		assert.equal(
			describeJmapError([{type: "invalidArguments", description: "bad name"}, {type: "forbidden"}]),
			"bad name; forbidden");
	});

	it("returns null for a plain network-failure Error/TypeError (no .type) - keeps silent fallback", () =>
	{
		assert.isNull(describeJmapError(new TypeError("Failed to fetch")));
		assert.isNull(describeJmapError(new Error("boom")));
	});

	it("returns null for a non-object/string throw", () =>
	{
		assert.isNull(describeJmapError("plain string error"));
		assert.isNull(describeJmapError(undefined));
	});

	it("returns null for an empty array", () =>
	{
		assert.isNull(describeJmapError([]));
	});
});

describe("describeSetError() - format a Mailbox/set or Email/set per-item SetError map", () =>
{
	it("formats a single SetError entry", () =>
	{
		assert.equal(describeSetError({c0: {type: "invalidProperties", description: "name already exists"}}),
			"name already exists");
	});

	it("joins multiple SetError entries with '; '", () =>
	{
		assert.equal(
			describeSetError({id1: {type: "notFound"}, id2: {type: "forbidden", description: "no access"}}),
			"notFound; no access");
	});

	it("returns null for undefined or an empty map", () =>
	{
		assert.isNull(describeSetError(undefined));
		assert.isNull(describeSetError({}));
	});
});

/**
 * assembleBodyHtml() must never leave a real "cid:..." value in `src` - the browser attempts
 * (and CSP-blocks, since no img-src can allow the "cid:" scheme) every `src` the moment srcdoc
 * HTML is parsed, well before the 'load' event resolveInlineImages() waits for, regardless of
 * how quickly it's swapped out afterward. deferCidImages() moves it to `data-cid` (clearing
 * `src`) so the browser never attempts it in the first place. Real bug found live against a
 * Stalwart account: the CSP violation was logged even though the image visibly loaded moments
 * later once resolveInlineImages() ran.
 */
describe("MailJmap.deferCidImages() - move cid: out of src before the iframe ever sees it", () =>
{
	function defer(html : string) : string
	{
		return (MailJmap as any).deferCidImages(html);
	}

	it("moves a cid: src to data-cid and clears src", () =>
	{
		const out = defer('<img src="cid:checkmk_logo.png">');
		const img = new DOMParser().parseFromString(out, "text/html").querySelector("img");

		assert.equal(img.getAttribute("data-cid"), "checkmk_logo.png");
		assert.isNull(img.getAttribute("src"));
	});

	it("leaves a non-cid img untouched", () =>
	{
		const out = defer('<img src="https://example.com/x.png">');
		const img = new DOMParser().parseFromString(out, "text/html").querySelector("img");

		assert.equal(img.getAttribute("src"), "https://example.com/x.png");
		assert.isNull(img.getAttribute("data-cid"));
	});
});

/**
 * RFC 2045's Content-ID header value is conventionally written wrapped in angle brackets (eg.
 * "<checkmk_logo.png>"), and a real JMAP server (Stalwart) can return EmailBodyPart.cid as that
 * raw header value verbatim - but the "cid:" URI scheme (RFC 2392, and this widget's own
 * data-cid attribute) never includes the brackets, so an unstripped "<checkmk_logo.png>" key
 * here would never match. Real bug found live against a Stalwart account.
 */
describe("MailJmap.resolveInlineImages() - cid: matching", () =>
{
	function createDoc(html : string) : Document
	{
		return new DOMParser().parseFromString(html, "text/html");
	}

	function bodyResult(attachments : any[]) : any
	{
		return {special: false, html: "", profileID: "1", accountId: "acc1", isLocal: false, attachments};
	}

	it("resolves an <img data-cid=...> even when the attachment's own cid is wrapped in angle brackets", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const doc = createDoc('<img data-cid="checkmk_logo.png">');
		(jmap as any).clients["1"] = {downloadBlob: async(_args : any) => ({blob: async() => new Blob(["x"])})};

		await jmap.resolveInlineImages(doc, "row1",
			bodyResult([{cid: "<checkmk_logo.png>", blobId: "b1", type: "image/png", name: "checkmk_logo.png"}]));

		assert.match(doc.querySelector("img").src, /^blob:/);
	});

	it("still resolves when neither side has angle brackets", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const doc = createDoc('<img data-cid="checkmk_logo.png">');
		(jmap as any).clients["1"] = {downloadBlob: async() => ({blob: async() => new Blob(["x"])})};

		await jmap.resolveInlineImages(doc, "row1",
			bodyResult([{cid: "checkmk_logo.png", blobId: "b1", type: "image/png", name: "checkmk_logo.png"}]));

		assert.match(doc.querySelector("img").src, /^blob:/);
	});

	it("leaves the img unresolved when there is genuinely no matching attachment", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const doc = createDoc('<img data-cid="unknown.png">');
		let called = false;
		(jmap as any).clients["1"] = {
			downloadBlob: async() => { called = true; return {blob: async() => new Blob(["x"])}; }
		};

		await jmap.resolveInlineImages(doc, "row1",
			bodyResult([{cid: "checkmk_logo.png", blobId: "b1", type: "image/png", name: "checkmk_logo.png"}]));

		assert.isFalse(called, "must not attempt a download for a cid with no matching attachment");
		assert.equal(doc.querySelector("img").getAttribute("src"), null);
	});
});

/**
 * fetchBody()'s optional `signal` (added so MailApp.preview() can cancel a body-fetch
 * once the user has already selected a different message, instead of letting it run to
 * completion on the network for nothing). Verifies the signal actually reaches the
 * transport (requestMany()'s fetchInit - jmap-jam spreads that straight into its fetch()
 * call) and that an intentional abort is handled differently from a genuine failure: both
 * fall back to {special:true} (the caller decides whether to act on it via signal.aborted),
 * but only a genuine failure should be logged.
 */
describe("MailJmap.fetchBody() - AbortSignal handling", () =>
{
	function createStubbedClient(capture : { options? : any }, behavior : "resolve" | "throw-abort" | "throw-error")
	{
		return {
			requestMany : async(buildFn : (t : any) => any, options? : any) =>
			{
				capture.options = options;
				buildFn({Email: {get: (_args : any) => null}});
				if (behavior === "throw-abort")
				{
					throw new DOMException("The operation was aborted.", "AbortError");
				}
				if (behavior === "throw-error")
				{
					throw new Error("network error");
				}
				return [{emails: {list: []}}];
			}
		};
	}

	it("forwards its signal into requestMany()'s fetchInit, so the underlying fetch() can be cancelled", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const capture : { options? : any } = {};
		primeToken(jmap, "1", createStubbedClient(capture, "resolve"));
		const controller = new AbortController();

		await jmap.fetchBody("mail::0::1::MBOX::1", undefined, controller.signal);

		assert.strictEqual(capture.options?.fetchInit?.signal, controller.signal,
			"fetchBody() must pass its signal through to requestMany()'s fetchInit, or a superseded " +
			"selection's HTTP request keeps running instead of being cancelled");
	});

	it("resolves {special:true} without logging when the request was aborted", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createStubbedClient({}, "throw-abort"));
		const controller = new AbortController();
		controller.abort();

		const originalConsoleError = console.error;
		let loggedCalls = 0;
		console.error = () => { loggedCalls++; };
		let result : any;
		try
		{
			result = await jmap.fetchBody("mail::0::1::MBOX::1", undefined, controller.signal);
		}
		finally
		{
			console.error = originalConsoleError;
		}

		assert.deepEqual(result, {special: true});
		assert.strictEqual(loggedCalls, 0,
			"an intentional abort (the user already moved on) must not be logged as a failure");
	});

	it("still logs and falls back to {special:true} on a genuine (non-abort) failure", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", createStubbedClient({}, "throw-error"));

		const originalConsoleError = console.error;
		let loggedCalls = 0;
		console.error = () => { loggedCalls++; };
		let result : any;
		try
		{
			result = await jmap.fetchBody("mail::0::1::MBOX::1");
		}
		finally
		{
			console.error = originalConsoleError;
		}

		assert.deepEqual(result, {special: true});
		assert.strictEqual(loggedCalls, 1,
			"a genuine failure (no signal involved) must still be logged, unlike an intentional abort");
	});
});

/**
 * fetchBody()'s local-shim path (mail/src/JmapShim.php via mail/jmap.php) - unlike a real JMAP
 * server, the shim answers a plain GET with Cache-Control/ETag headers (see jmap.php), so the
 * browser's own HTTP cache can serve a re-opened message without another round trip. jmap-jam's
 * request()/requestMany() always POST, so this path bypasses them and calls fetch() directly -
 * verified here at the fetch() level, since there is no client.requestMany() call to stub.
 */
describe("MailJmap.fetchBody() - local shim uses a cacheable GET, not requestMany()", () =>
{
	const originalFetch = globalThis.fetch;

	afterEach(() =>
	{
		globalThis.fetch = originalFetch;
	});

	function primeLocalTokenWithSession(jmap : MailJmap, profileID : string, apiUrl : string) : void
	{
		(jmap as any).tokens[profileID] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: true, customLabels: {},
		};
		(jmap as any).clients[profileID] = {session: Promise.resolve({apiUrl})};
	}

	it("GETs the shim's own apiUrl with methodCalls/using in the query string, never a POST body", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeLocalTokenWithSession(jmap, "1", "https://example.com/mail/jmap.php");

		let capturedUrl : string | undefined;
		let capturedInit : any;
		globalThis.fetch = (async(url : any, init? : any) =>
		{
			capturedUrl = String(url);
			capturedInit = init;
			return {
				ok: true,
				json: async() => ({
					methodResponses: [["Email/get", {accountId: "acc1", list: [], notFound: ["1"]}, "emails"]],
					sessionState: "0",
				}),
			};
		}) as any;

		await jmap.fetchBody("mail::0::1::MBOX::1");

		assert.isTrue(capturedUrl?.startsWith("https://example.com/mail/jmap.php?"),
			"must GET the shim's own apiUrl, not jmap-jam's POST transport");
		assert.strictEqual(capturedInit.method, "GET");
		assert.isUndefined(capturedInit.body, "a GET must never carry a body");

		// PHP http_build_query()-style bracket keys, not JSON - see phpBuildQuery()'s docblock.
		// URLSearchParams decodes percent-encoding on both keys and values, so the literal
		// bracket-key strings below can be looked up directly.
		const params = new URLSearchParams(capturedUrl!.split("?")[1]);
		assert.strictEqual(params.get("methodCalls[0][0]"), "Email/get");
		assert.strictEqual(params.get("methodCalls[0][1][accountId]"), "acc1");
		assert.deepEqual(params.getAll("methodCalls[0][1][ids][]"), ["1"],
			"a scalar-only array (ids) must use PHP's bare '[]', not an explicit index");
		assert.strictEqual(params.get("methodCalls[0][1][mailboxId]"), "MBOX",
			"local-shim requests need the mailboxId extension JmapShim::emailGet() requires");
		assert.strictEqual(params.get("methodCalls[0][2]"), "emails");
		assert.isAbove(params.getAll("using[]").length, 0,
			"using must be sent too, for parity with a real JMAP POST body");
	});

	it("falls back to {special:true} when the GET response carries a JMAP error", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeLocalTokenWithSession(jmap, "1", "https://example.com/mail/jmap.php");

		globalThis.fetch = (async() => ({
			ok: true,
			json: async() => ({
				methodResponses: [["error", {type: "serverFail", description: "boom"}, "emails"]],
				sessionState: "0",
			}),
		})) as any;

		const originalConsoleError = console.error;
		console.error = () => {};
		let result : any;
		try
		{
			result = await jmap.fetchBody("mail::0::1::MBOX::1");
		}
		finally
		{
			console.error = originalConsoleError;
		}

		assert.deepEqual(result, {special: true});
	});
});
