import {assert} from "@open-wc/testing";
import {JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for the "shared account mailbox/message actions" fix (doc/ai/projects/
 * mail-jmap-shared-mailboxes.md follow-up, live-reported bug: setMailboxSubscribed() failed with
 * "Folder 'user/default' not found" against a shared folder) - every mailboxId()/mailboxIdOrNull()
 * call site now resolves a "user/<name>/..." path's REAL owning accountId via
 * resolveMailboxOwner() before issuing the JMAP call, a genuinely cross-account move/copy uses
 * Email/copy (RFC 8620 §5.4) instead of a same-account-only mailboxIds patch, and
 * updateIds()/destroyIds() decode a shared-account message reference's own row-id encoding
 * instead of assuming this profile's own token.accountId.
 *
 * Same fake-client/primeToken()/ref() conventions already established in
 * MailJmapGetRowsSharedAccount.test.ts (session + Mailbox.query/get) and
 * MailJmapBulkMoveCopyDelete.test.ts (Email.set/query), extended with Email.copy/Email.get.
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
	link : (_path : string, _params : Record<string, any>) => "https://example.com/",
	open_link : () => {},
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

function ref(profileID : string, mailboxId : string, emailId : string)
{
	return {profileID, mailboxId, emailId};
}

function primeToken(jmap : MailJmap, profileID : string, client : any,
	overrides : Record<string, any> = {}) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "ca", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		...overrides,
	};
	(jmap as any).clients[profileID] = client;
}

function seedMailboxId(jmap : MailJmap, profileID : string, folderPath : string, mailboxId : string) : void
{
	(jmap as any).mailboxIds[profileID + '::' + folderPath] = mailboxId;
}

const SHARED_SESSION = {
	accounts : {
		ca : {name : "ralf@example.invalid", isPersonal : true},
		o : {name : "teachers@example.invalid", isPersonal : false},
	},
};

/**
 * Full-surface fake client (Mailbox.set/query, Email.set/query/get/copy) - a single `session`
 * (plain object, not a Promise - resolveSharedAccountId()'s own `await client.session` works
 * fine either way) backs resolveMailboxOwner()'s "user/<name>/..." resolution.
 */
function createFakeClient(session : any, handlers : {
	mailboxSet? : (args : any) => any,
	mailboxQuery? : (args : any) => any,
	emailSet? : (args : any) => any,
	emailGet? : (args : any) => any,
	emailCopy? : (args : any) => any,
} = {}) : { client : any, captured : any }
{
	const captured : any = {mailboxSet : [], mailboxQuery : [], emailSet : [], emailGet : [], emailCopy : []};
	const client = {
		session,
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {
				Mailbox : {
					set : (args : any) =>
					{
						captured.mailboxSet.push(args);
						return handlers.mailboxSet?.(args) ??
							{updated : {[Object.keys(args.update ?? {})[0]] : null}, notUpdated : {}};
					},
					query : (args : any) =>
					{
						captured.mailboxQuery.push(args);
						return handlers.mailboxQuery?.(args) ?? {ids : ["mbx-" + JSON.stringify(args.filter)]};
					},
				},
				Email : {
					set : (args : any) =>
					{
						captured.emailSet.push(args);
						return handlers.emailSet?.(args) ??
							{updated : {}, notUpdated : {}, destroyed : args.destroy ?? [], notDestroyed : {}};
					},
					get : (args : any) =>
					{
						captured.emailGet.push(args);
						return handlers.emailGet?.(args) ?? {list : (args.ids ?? []).map((id : string) => ({id, keywords : {}}))};
					},
					copy : (args : any) =>
					{
						captured.emailCopy.push(args);
						return handlers.emailCopy?.(args) ?? {created : {c0 : {id : "copied-" + args.create.c0.id}}, notCreated : null};
					},
				},
			};
			const request = buildFn(t);
			return [request];
		},
	};
	return {client, captured};
}

describe("MailJmap.setMailboxSubscribed() - shared account", () =>
{
	it("resolves 'user/<name>/...' to the shared accountId and subscribes using it, not the viewer's own", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION);
		primeToken(jmap, "1", client);

		await jmap.setMailboxSubscribed("1", "user/teachers/INBOX", true);

		assert.equal(captured.mailboxQuery[0].accountId, "o", "mailboxId() resolution must use the shared accountId");
		assert.equal(captured.mailboxSet[0].accountId, "o",
			"Mailbox.set must use the shared accountId, not the viewer's own ('ca') - the originally-reported live bug");
	});

	it("surfaces the 'insufficient rights' message when the server reports a forbidden SetError", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient(SHARED_SESSION, {
			mailboxSet : (args) => ({updated : {}, notUpdated : {[Object.keys(args.update)[0]] : {type : "forbidden"}}}),
		});
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.setMailboxSubscribed("1", "user/teachers/INBOX", true);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message,
			"You do not have sufficient rights for this action in this shared folder - ask the folder owner to grant them");
	});

	it("still shows the ordinary failure message for a non-forbidden error against a shared folder", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient(SHARED_SESSION, {
			mailboxSet : (args) => ({updated : {}, notUpdated : {[Object.keys(args.update)[0]] : {type : "notFound"}}}),
		});
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.setMailboxSubscribed("1", "user/teachers/INBOX", true);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.notInclude(error.message, "sufficient rights");
	});

	it("never applies the shared-account framing to a failure on the viewer's OWN account", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient(SHARED_SESSION, {
			mailboxSet : (args) => ({updated : {}, notUpdated : {[Object.keys(args.update)[0]] : {type : "forbidden"}}}),
		});
		primeToken(jmap, "1", client);
		seedMailboxId(jmap, "1", "Archive", "mbx-archive");

		let error : any = null;
		try
		{
			await jmap.setMailboxSubscribed("1", "Archive", true);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.notInclude(error.message, "sufficient rights");
	});
});

describe("MailJmap.moveMessages()/copyMessages() - cross-account via Email/copy", () =>
{
	it("moveMessages() copies into the shared target account, preserving keywords, then destroys the original only after the copy succeeds", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION, {
			emailGet : (args) => ({list : args.ids.map((id : string) => ({id, keywords : {"$seen" : true}}))}),
		});
		primeToken(jmap, "1", client);

		await jmap.moveMessages([ref("1", "mbx-inbox", "e1")], "1", "user/teachers/Archive");

		assert.equal(captured.emailCopy.length, 1, "a cross-account move must use Email/copy, not a mailboxIds patch");
		assert.equal(captured.emailCopy[0].fromAccountId, "ca");
		assert.equal(captured.emailCopy[0].accountId, "o");
		assert.equal(captured.emailCopy[0].create.c0.id, "e1");
		assert.deepEqual(captured.emailCopy[0].create.c0.keywords, {"$seen" : true},
			"the source message's own keywords/flags must be preserved on the copy");

		assert.equal(captured.emailSet.length, 1, "the original must be destroyed once the copy succeeds");
		assert.deepEqual(captured.emailSet[0].destroy, ["e1"]);
		assert.equal(captured.emailSet[0].accountId, "ca", "destroy must target the message's own (source) account");
	});

	it("moveMessages() does NOT destroy the original when the cross-account copy fails", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION, {
			emailCopy : () => ({created : null, notCreated : {c0 : {type : "forbidden"}}}),
		});
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.moveMessages([ref("1", "mbx-inbox", "e1")], "1", "user/teachers/Archive");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message,
			"You do not have sufficient rights for this action in this shared folder - ask the folder owner to grant them");
		assert.equal(captured.emailSet.length, 0, "never destroy the original when the copy failed - copy-then-destroy, never the reverse");
	});

	it("copyMessages() uses Email/copy for a cross-account target and never destroys the original", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION);
		primeToken(jmap, "1", client);

		await jmap.copyMessages([ref("1", "mbx-inbox", "e1")], "1", "user/teachers/Archive");

		assert.equal(captured.emailCopy.length, 1);
		assert.equal(captured.emailSet.length, 0, "a copy (not move) must never destroy the original");
	});

	it("moving WITHIN the same shared account still uses the ordinary mailboxIds patch, not Email/copy", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION);
		primeToken(jmap, "1", client);
		const sharedSourceMailboxId = (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "o" +
			(MailJmap as any).SHARED_ACCOUNT_SEP + "mbx-teachers-inbox";

		await jmap.moveMessages([ref("1", sharedSourceMailboxId, "e1")], "1", "user/teachers/Archive");

		assert.equal(captured.emailCopy.length, 0, "same resolved account (the SAME shared account) on both ends -> no Email/copy needed");
		assert.equal(captured.emailSet.length, 1);
		assert.equal(captured.emailSet[0].accountId, "o", "the patch itself must still target the shared account, not the viewer's own");
	});
});

describe("MailJmap.updateIds()/destroyIds() - decode a shared-account message reference", () =>
{
	function sharedMailboxId(realMailboxId : string) : string
	{
		return (MailJmap as any).SHARED_ACCOUNTS_ROOT_ID + "o" + (MailJmap as any).SHARED_ACCOUNT_SEP + realMailboxId;
	}

	it("setLabel() targets the DECODED shared accountId, not token.accountId, for an encoded reference", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION);
		primeToken(jmap, "1", client);

		await jmap.setLabel([ref("1", sharedMailboxId("mbx-teachers-inbox"), "e1")], "label3", true);

		assert.equal(captured.emailSet.length, 1);
		assert.equal(captured.emailSet[0].accountId, "o", "must use the message's real owning (shared) accountId");
		assert.deepEqual(captured.emailSet[0].update, {e1 : {"keywords/$label3" : true}});
	});

	it("setLabel() still uses this profile's own accountId for an ordinary (non-shared) reference", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION);
		primeToken(jmap, "1", client);

		await jmap.setLabel([ref("1", "mbx-inbox", "e1")], "label3", true);

		assert.equal(captured.emailSet[0].accountId, "ca");
	});

	it("destroyIds() (via deleteMessages('destroy')) targets the DECODED shared accountId", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client, captured} = createFakeClient(SHARED_SESSION);
		primeToken(jmap, "1", client);

		await jmap.deleteMessages([ref("1", sharedMailboxId("mbx-teachers-inbox"), "e1")], "destroy");

		assert.equal(captured.emailSet.length, 1);
		assert.equal(captured.emailSet[0].accountId, "o");
		assert.deepEqual(captured.emailSet[0].destroy, ["e1"]);
	});

	it("surfaces the 'insufficient rights' message when a shared-account update is forbidden", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const {client} = createFakeClient(SHARED_SESSION, {
			emailSet : (args) =>
			{
				const id = Object.keys(args.update ?? {})[0] ?? (args.destroy ?? [])[0];
				return {updated : {}, notUpdated : {[id] : {type : "forbidden"}}, destroyed : [], notDestroyed : {}};
			},
		});
		primeToken(jmap, "1", client);

		let error : any = null;
		try
		{
			await jmap.setLabel([ref("1", sharedMailboxId("mbx-teachers-inbox"), "e1")], "label3", true);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message,
			"You do not have sufficient rights for this action in this shared folder - ask the folder owner to grant them");
	});
});
