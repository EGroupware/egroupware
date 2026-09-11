import {assert} from "@open-wc/testing";
import {JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap.saveDraft() (and, along the way, the resolveComposeContext() private
 * helper it shares with sendNewEmail()) - doc/ai/projects/mail-test-coverage.md's priority-2
 * entry: "saveDraft() (only sendNewEmail() is exercised)". Its own distinguishing behaviour -
 * reimport-and-replace semantics (create the new draft first, only THEN best-effort-destroy the
 * previous one, a cleanup failure must never fail the save itself) - is exactly what's worth a
 * real integration test here, as opposed to draftEmailProperties() (already covered standalone in
 * MailJmapDraftEmailPropertiesHeaders.test.ts) or the full sendNewEmail() orchestration (still
 * too heavy to drive for a focused test, per that file's own docblock).
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

const IDENTITY = {id : "1", name : "Me", email : "me@example.org"};
const EMAIL = {to : ["recipient@example.org"], subject : "Draft", body : "Hello", isHtml : false};

interface Captured
{
	create : any;
	destroy : any;
}

/**
 * Sequential (not concurrent) call dispatch by order - resolveComposeContext()'s own combined
 * Mailbox.get+Identity.get call always happens first, createDraftEmail()'s Email/set create
 * second, and (only if existingEmailId was given) the old-draft destroy third - saveDraft() never
 * fires any of these concurrently with another, so counting calls is a safe, simple way to tell
 * them apart (unlike e.g. toggleForAll()'s own Promise.all() of two Email/query calls).
 */
function createFakeClient(opts : {
	identities? : any[],
	createResult? : any,
	destroyResult? : any,
	destroyThrows? : boolean,
} = {}) : {client : any, captured : Captured}
{
	const captured : Captured = {create : undefined, destroy : undefined};
	let call = 0;
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			call++;
			if (call === 1)
			{
				const t = {
					Mailbox : {get : (_args : any) => ({list : [{id : 'drafts-id', role : 'drafts'}]})},
					Identity : {get : (_args : any) => ({list : opts.identities ?? []})},
				};
				return [buildFn(t)];
			}
			if (call === 2)
			{
				const t = {Email : {set : (args : any) => { captured.create = args; return opts.createResult ?? {created : {s1 : {id : 'new-draft-id'}}, notCreated : {}}; }}};
				return [buildFn(t)];
			}
			const t = {
				Email : {
					set : (args : any) =>
					{
						captured.destroy = args;
						if (opts.destroyThrows) throw new Error('destroy failed');
						return opts.destroyResult ?? {destroyed : [args.destroy?.[0]], notDestroyed : {}};
					},
				},
			};
			return [buildFn(t)];
		},
	};
	return {client, captured};
}

function primeToken(jmap : MailJmap, profileID : string, client : any, overrides : Record<string, any> = {}) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		...overrides,
	};
	(jmap as any).clients[profileID] = client;
}

describe("MailJmap.saveDraft() - first save (no existingEmailId)", () =>
{
	it("creates the draft in the Drafts mailbox with $draft/$seen keywords, and destroys nothing", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [IDENTITY];
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1:1", client);

		const result = await jmap.saveDraft("1:1", EMAIL);

		assert.equal(result.emailId, 'new-draft-id');
		assert.equal(result.mailboxId, 'drafts-id');
		assert.deepEqual(captured.create.create.s1.mailboxIds, {'drafts-id' : true});
		assert.deepEqual(captured.create.create.s1.keywords, {'$draft' : true, '$seen' : true});
		assert.isUndefined(captured.destroy, "no existingEmailId given - nothing to clean up");
	});

	it("matches the identity by the profileID's own ident_id suffix, not just the first identity", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [
			{id : "1", name : "First", email : "first@example.org"},
			{id : "2", name : "Second", email : "second@example.org"},
		];
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1:2", client);

		await jmap.saveDraft("1:2", EMAIL);

		assert.equal(captured.create.create.s1.from[0].email, "second@example.org");
	});

	it("throws when no identity matches and there is no fallback at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [];
		const {client} = createFakeClient();
		primeToken(jmap, "1:1", client);

		let error : any = null;
		try
		{
			await jmap.saveDraft("1:1", EMAIL);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});

	it("throws when the account has no Drafts mailbox", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [IDENTITY];
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Mailbox : {get : () => ({list : []})}, Identity : {get : () => ({list : []})}};
				return [buildFn(t)];
			},
		};
		primeToken(jmap, "1:1", client);

		let error : any = null;
		try
		{
			await jmap.saveDraft("1:1", EMAIL);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});

	it("throws a JmapUserError when the server refuses to create the draft", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [IDENTITY];
		const {client} = createFakeClient({createResult : {created : {}, notCreated : {s1 : {type : 'invalidProperties'}}}});
		primeToken(jmap, "1:1", client);

		let error : any = null;
		try
		{
			await jmap.saveDraft("1:1", EMAIL);
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});
});

describe("MailJmap.saveDraft() - subsequent save (existingEmailId given) - reimport-and-replace", () =>
{
	it("creates the new draft FIRST, then destroys the previous copy", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [IDENTITY];
		const {client, captured} = createFakeClient();
		primeToken(jmap, "1:1", client);

		const result = await jmap.saveDraft("1:1", EMAIL, "old-draft-id");

		assert.equal(result.emailId, 'new-draft-id', "the NEW draft's id must be returned, not the old one");
		assert.deepEqual(captured.destroy.destroy, ["old-draft-id"]);
	});

	it("includes the shim's mailboxId extension on destroy only for a local (isLocal) account", async() =>
	{
		const jmapLocal = new MailJmap(createFakeApp());
		(jmapLocal as any).getIdentities = async() => [IDENTITY];
		const {client : localClient, captured : localCaptured} = createFakeClient();
		primeToken(jmapLocal, "1:1", localClient, {isLocal : true});
		await jmapLocal.saveDraft("1:1", EMAIL, "old-draft-id");
		assert.equal(localCaptured.destroy.mailboxId, 'drafts-id');

		const jmapReal = new MailJmap(createFakeApp());
		(jmapReal as any).getIdentities = async() => [IDENTITY];
		const {client : realClient, captured : realCaptured} = createFakeClient();
		primeToken(jmapReal, "1:1", realClient, {isLocal : false});
		await jmapReal.saveDraft("1:1", EMAIL, "old-draft-id");
		assert.isUndefined(realCaptured.destroy.mailboxId);
	});

	it("a cleanup (destroy) failure is swallowed - the save itself still succeeds, since it already did", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [IDENTITY];
		const {client} = createFakeClient({destroyThrows : true});
		primeToken(jmap, "1:1", client);

		const result = await jmap.saveDraft("1:1", EMAIL, "old-draft-id");

		assert.equal(result.emailId, 'new-draft-id', "must resolve successfully despite the cleanup failure");
	});

	/**
	 * Characterization, not necessarily desired: the destroy call's own response is never
	 * inspected for `notDestroyed` at all (unlike destroyIds()'s bulk-delete counterpart, which
	 * does check it) - only an actual thrown/rejected requestMany() is caught. A server reporting
	 * "couldn't destroy it" via a normal (non-throwing) response is silently treated as success.
	 */
	it("does not even notice a server-reported notDestroyed response (only a thrown/rejected call is caught)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [IDENTITY];
		const {client} = createFakeClient({destroyResult : {destroyed : [], notDestroyed : {'old-draft-id' : {type : 'notFound'}}}});
		primeToken(jmap, "1:1", client);

		const result = await jmap.saveDraft("1:1", EMAIL, "old-draft-id");

		assert.equal(result.emailId, 'new-draft-id');
	});
});
