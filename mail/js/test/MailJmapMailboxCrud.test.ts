import {assert} from "@open-wc/testing";
import {describeSetError, JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's mailbox/folder CRUD surface (createMailbox/renameMailbox/moveMailbox/
 * deleteMailbox/setMailboxSubscribed/getAllMailboxes/resolveMailboxId) - previously completely
 * untested (doc/ai/projects/mail-test-coverage.md's priority-2 entry: "JMAP (and shim) path...
 * take precedence over the classic Api\Mail class"). These are the JMAP-native paths behind
 * mail/js/app.ts's addFolder()/renameFolder()/moveFolder()/deleteFolder()/subscribeFolder()/
 * unsubscribeFolder() (that UI wiring itself is a separate, lower-priority gap - see the project
 * doc's priority-4 entry).
 *
 * Pure unit tests against a fake JamClient - no network/DB/IMAP involved. Follows the same
 * primeToken()/fake-client conventions already established in MailJmap.test.ts and
 * MailJmapBulkMoveCopyDelete.test.ts.
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
	// popupCheckCert()'s own dependencies - getAllMailboxes()'s generic-failure branch calls it;
	// stubbed so that path is exercised without depending on its own (unrelated) implementation.
	link : (_path : string, _params : Record<string, any>) => "https://example.com/",
	open_link : () => {},
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

interface Captured
{
	mailboxSet : any[];
	mailboxQuery : any[];
	mailboxGet : any[];
}

function createFakeClient(handlers : {
	mailboxSet? : (args : any) => any,
	mailboxQuery? : (args : any) => any,
	mailboxGet? : (args : any) => any,
} = {}) : {client : any, captured : Captured}
{
	const captured : Captured = {mailboxSet : [], mailboxQuery : [], mailboxGet : []};
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {
				Mailbox : {
					set : (args : any) =>
					{
						captured.mailboxSet.push(args);
						return handlers.mailboxSet?.(args) ?? {updated : {}, notUpdated : {}, created : {}, notCreated : {}, destroyed : [], notDestroyed : {}};
					},
					query : (args : any) =>
					{
						captured.mailboxQuery.push(args);
						return handlers.mailboxQuery?.(args) ?? {ids : []};
					},
					get : (args : any) =>
					{
						captured.mailboxGet.push(args);
						return handlers.mailboxGet?.(args) ?? {list : []};
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

function seedMailboxId(jmap : MailJmap, profileID : string, folderPath : string, mailboxId : string) : void
{
	(jmap as any).mailboxIds[profileID + '::' + folderPath] = mailboxId;
}

describe("MailJmap.createMailbox()", () =>
{
	it("creates at the top level (parentId null) when parentPath is empty", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : () => ({created : {c0 : {id : "mbx-new"}}, notCreated : {}})});
		primeToken(jmap, "1", client);

		await jmap.createMailbox("1", "", "NewFolder");

		assert.deepEqual(captured.mailboxSet[0].create, {c0 : {name : "NewFolder", parentId : null}});
	});

	it("creates under a resolved parent when parentPath is non-empty", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : () => ({created : {c0 : {id : "mbx-new"}}, notCreated : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.createMailbox("1", "Archive", "2026");

		assert.deepEqual(captured.mailboxSet[0].create, {c0 : {name : "2026", parentId : "mbx-archive"}});
	});

	it("throws a JmapUserError (via describeSetError()) when the server reports notCreated", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const notCreated = {c0 : {type : "invalidProperties"}};
		const {client} = createFakeClient({mailboxSet : () => ({created : {}, notCreated})});
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.createMailbox("1", "", "Bad Name");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message, describeSetError(notCreated));
	});

	it("throws 'Account not reachable' when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let error : any = null;
		try
		{
			await jmap.createMailbox("1", "", "NewFolder");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message, "Account not reachable");
	});
});

describe("MailJmap.renameMailbox()", () =>
{
	it("sends the new name and invalidates the old path's cache on success", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : (args) => ({updated : {[Object.keys(args.update)[0]] : null}, notUpdated : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Old", "mbx-old");
		seedMailboxId(jmap, "1", "Old/Sub", "mbx-old-sub");

		await jmap.renameMailbox("1", "Old", "New");

		assert.deepEqual(captured.mailboxSet[0].update, {"mbx-old" : {name : "New"}});
		assert.isUndefined((jmap as any).mailboxIds["1::Old"], "the renamed path's own cache entry must be dropped");
		assert.isUndefined((jmap as any).mailboxIds["1::Old/Sub"], "a descendant's cache entry must be dropped too");
	});

	it("throws and does NOT invalidate the cache when the server rejects the rename", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const notUpdated = {"mbx-old" : {type : "invalidProperties"}};
		const {client} = createFakeClient({mailboxSet : () => ({updated : {}, notUpdated})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Old", "mbx-old");

		let error : any = null;
		try
		{
			await jmap.renameMailbox("1", "Old", "New");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal((jmap as any).mailboxIds["1::Old"], "mbx-old", "a failed rename must leave the cache untouched");
	});
});

describe("MailJmap.moveMailbox()", () =>
{
	it("patches parentId to the resolved new parent and invalidates the old path's cache", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : (args) => ({updated : {[Object.keys(args.update)[0]] : null}, notUpdated : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Sub", "mbx-sub");
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.moveMailbox("1", "Sub", "Archive");

		assert.deepEqual(captured.mailboxSet[0].update, {"mbx-sub" : {parentId : "mbx-archive"}});
		assert.isUndefined((jmap as any).mailboxIds["1::Sub"]);
	});

	it("moves to the top level (parentId null) when newParentPath is empty", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : (args) => ({updated : {[Object.keys(args.update)[0]] : null}, notUpdated : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive/Sub", "mbx-sub");

		await jmap.moveMailbox("1", "Archive/Sub", "");

		assert.deepEqual(captured.mailboxSet[0].update, {"mbx-sub" : {parentId : null}});
	});

	it("throws when the server rejects the move", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient({mailboxSet : () => ({updated : {}, notUpdated : {"mbx-sub" : {type : "invalidProperties"}}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Sub", "mbx-sub");

		let threw = false;
		try
		{
			await jmap.moveMailbox("1", "Sub", "Archive");
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});
});

describe("MailJmap.deleteMailbox()", () =>
{
	it("destroys the resolved id and invalidates its cache on success", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : () => ({destroyed : ["mbx-old"], notDestroyed : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Old", "mbx-old");

		await jmap.deleteMailbox("1", "Old");

		assert.deepEqual(captured.mailboxSet[0].destroy, ["mbx-old"]);
		assert.isUndefined((jmap as any).mailboxIds["1::Old"]);
	});

	it("throws when the server refuses to destroy it (e.g. non-empty folder)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const notDestroyed = {"mbx-old" : {type : "mailboxHasEmail"}};
		const {client} = createFakeClient({mailboxSet : () => ({destroyed : [], notDestroyed})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Old", "mbx-old");

		let error : any = null;
		try
		{
			await jmap.deleteMailbox("1", "Old");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message, describeSetError(notDestroyed));
	});
});

describe("MailJmap.setMailboxSubscribed()", () =>
{
	it("subscribes", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : (args) => ({updated : {[Object.keys(args.update)[0]] : null}, notUpdated : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.setMailboxSubscribed("1", "Archive", true);

		assert.deepEqual(captured.mailboxSet[0].update, {"mbx-archive" : {isSubscribed : true}});
	});

	it("unsubscribes", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient({mailboxSet : (args) => ({updated : {[Object.keys(args.update)[0]] : null}, notUpdated : {}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		await jmap.setMailboxSubscribed("1", "Archive", false);

		assert.deepEqual(captured.mailboxSet[0].update, {"mbx-archive" : {isSubscribed : false}});
	});

	it("throws when the server rejects the (un)subscribe", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient({mailboxSet : () => ({updated : {}, notUpdated : {"mbx-archive" : {type : "serverFail"}}})});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		let threw = false;
		try
		{
			await jmap.setMailboxSubscribed("1", "Archive", true);
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});
});

describe("MailJmap.resolveMailboxId()", () =>
{
	it("returns null for the top level ('') without ever querying JMAP", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1", client);

		const id = await jmap.resolveMailboxId("1", "");

		assert.isNull(id);
		assert.equal(captured.mailboxQuery.length, 0, "an empty path must never trigger a Mailbox/query");
	});

	it("resolves a non-empty path to its cached mailbox id", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient();
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		const id = await jmap.resolveMailboxId("1", "Archive");

		assert.equal(id, "mbx-archive");
	});

	it("returns null when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());

		const id = await jmap.resolveMailboxId("1", "Archive");

		assert.isNull(id);
	});
});

/**
 * getAllMailboxes() chains a Mailbox/query -> Mailbox/get via jmap-jam's $ref() result-reference
 * mechanism (RFC 8620 §3.7) within one requestMany() call - unlike every other method above, whose
 * fake just returns real data straight from t.Mailbox.set()/query(). Real jmap-jam: t.X.method(args)
 * returns an opaque "invocation" thunk (only .$ref() is ever called on it), and the ACTUAL resolved
 * data comes back separately, as requestMany()'s own return value - same convention already
 * established in MailJmap.test.ts's "jmap-jam requestMany() invocation shape" tests.
 */
describe("MailJmap.getAllMailboxes()", () =>
{
	function createChainedFakeClient(mailboxes : {list : any[]} = {list : []}) : {client : any, captured : {filter? : any}}
	{
		const captured : {filter? : any} = {};
		const invocation = () => ({$ref : (_path : string) => ({})});
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {
					Mailbox : {
						query : (args : any) => { captured.filter = args.filter; return invocation(); },
						get : (_args : any) => invocation(),
					},
				};
				buildFn(t);
				return [{mailboxes}];
			},
		};
		return {client, captured};
	}

	it("returns null immediately, with no client calls, when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const result = await jmap.getAllMailboxes("1");
		assert.isNull(result);
	});

	it("filters to subscribed-only when explicitly requested, and returns the resolved list", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createChainedFakeClient({list : [{id : "a"}]});
		primeToken(jmap, "1", client);

		const result = await jmap.getAllMailboxes("1", true);

		assert.deepEqual(captured.filter, {isSubscribed : true});
		assert.deepEqual(result, [{id : "a"}]);
	});

	it("does not filter by subscription when explicitly asked for everything", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createChainedFakeClient();
		primeToken(jmap, "1", client);

		await jmap.getAllMailboxes("1", false);

		assert.deepEqual(captured.filter, {});
	});

	it("re-throws a JmapUserError instead of swallowing it into a null result", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client = {requestMany : async() => { throw new JmapUserError("boom"); }};
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.getAllMailboxes("1", true);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});

	it("resolves to null (not a rejected promise) for a generic/unexpected failure - the classic-fallback-friendly contract", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client = {requestMany : async() => { throw new Error("network hiccup"); }};
		primeToken(jmap, "1", client);

		const result = await jmap.getAllMailboxes("1", true);

		assert.isNull(result, "a generic failure must resolve null so callers keep falling back silently, not throw");
	});
});
