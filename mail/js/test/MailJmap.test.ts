import {assert} from "@open-wc/testing";
import {describeJmapError, describeSetError, isPreferenceOn, MailJmap} from "../jmap";
import type {MailApp} from "../app";

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {
		egw,
		mail_getCustomLabels: () => ({}),
		mail_updateCustomLabelStylesheet: () => {}
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
		return {requestMany: async() => [{mailboxes: {list}}]};
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
		return {requestMany: async() => [{mailboxes: {list}}]};
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

/**
 * A checkbox-style egw preference's stored value is the server's raw string, often literally "0"
 * for "off" - a non-empty JS string is otherwise always truthy, so a plain `!egw.preference(...)`
 * silently treats a "0"-stored (off) preference as on. Bit both MailJmap.getMailboxChildren()'s
 * isSubscribed filter and MailApp.mail_buildFolderLevelData()'s display filter, which both used
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
