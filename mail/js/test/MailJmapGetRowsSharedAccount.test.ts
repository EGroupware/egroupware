import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for getRows() reading a shared account's own folder (doc/ai/projects/
 * mail-jmap-shared-mailboxes.md's "browsing into a shared account" follow-up, the getRows()/
 * fetchRows() increment) - a "user/<name>/<path>" selectedFolder must resolve <name> back to its
 * real JMAP accountId (via the session's own `accounts` map) and use THAT accountId for every
 * per-message JMAP call, instead of this profile's own token.accountId.
 *
 * Same fake-client dispatch-by-invoked-method convention as MailJmap.test.ts's own
 * createThreadedFakeClient() - captures the `accountId` each call shape was given, since that's
 * exactly what this fix changes.
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
	return {egw, getCustomLabels : () => ({}), updateCustomLabelStylesheet : () => {}, getRowLabelTags : () => []} as unknown as MailApp;
}

function invocationStub()
{
	return {$ref : (_path : string) => ({})};
}

function fakeEmail(overrides : Record<string, any> = {}) : Record<string, any>
{
	return {
		id : "email1", subject : "Test subject",
		from : [{name : "Sender", email : "sender@example.com"}], to : [], cc : [], bcc : [],
		keywords : {}, sentAt : "2026-01-01T00:00:00Z", receivedAt : "2026-01-01T00:00:00Z",
		size : 100, preview : "a body snippet", hasAttachment : false,
		...overrides,
	};
}

interface Captured
{
	mailboxQueryAccountId? : string;
	mailboxGetAccountId? : string;
	emailQueryAccountId? : string;
	emailGetAccountId? : string;
	calls : number;
}

function fakeClient(session : any, emails : any[]) : {client : any, captured : Captured}
{
	const captured : Captured = {calls : 0};
	const client = {
		session,
		requestMany : async(buildFn : (t : any) => any) =>
		{
			captured.calls++;
			let calledMailboxQuery = false, calledMailboxGet = false, calledEmailQuery = false;
			const t = {
				Mailbox : {
					query : (args : any) => { calledMailboxQuery = true; captured.mailboxQueryAccountId = args.accountId; return invocationStub(); },
					get : (args : any) => { calledMailboxGet = true; captured.mailboxGetAccountId = args.accountId; return invocationStub(); },
				},
				Email : {
					query : (args : any) => { calledEmailQuery = true; captured.emailQueryAccountId = args.accountId; return invocationStub(); },
					get : (args : any) => { captured.emailGetAccountId = args.accountId; return invocationStub(); },
				},
			};
			buildFn(t);
			if (calledMailboxGet) return [{mailbox : {list : [{role : null}]}}];
			if (calledMailboxQuery) return [{ids : {ids : ["real-inbox-id"]}}];
			if (calledEmailQuery) return [{ids : {ids : emails.map((e) => e.id), total : emails.length}, emails : {list : emails}}];
			throw new Error("fakeClient(): unexpected requestMany() call shape");
		},
	};
	return {client, captured};
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "ca", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

const SHARED_SESSION = {
	accounts : {
		ca : {name : "ralf@example.invalid", isPersonal : true},
		o : {name : "teachers@example.invalid", isPersonal : false},
	},
};

describe("MailJmap.getRows() - shared account folder", () =>
{
	it("resolves 'user/<name>/<path>' to the real shared accountId and uses it for every call", async() =>
	{
		const {client, captured} = fakeClient(SHARED_SESSION, [fakeEmail()]);
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", client);

		const result = await jmap.getRows({selectedFolder : "1::user/teachers/INBOX"});

		assert.equal(captured.mailboxQueryAccountId, "o", "mailboxId() resolution must use the shared accountId");
		assert.equal(captured.mailboxGetAccountId, "o", "mailboxRole() must use the shared accountId");
		assert.equal(captured.emailQueryAccountId, "o", "Email/query must use the shared accountId, not the primary ('ca')");
		assert.equal(captured.emailGetAccountId, "o", "Email/get must use the shared accountId");
		assert.lengthOf(result.rows, 1);
	});

	it("encodes the shared accountId into the row's own row_id (mailboxId component)", async() =>
	{
		const {client} = fakeClient(SHARED_SESSION, [fakeEmail()]);
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", client);

		const result = await jmap.getRows({selectedFolder : "1::user/teachers/INBOX"});

		assert.include(result.rows[0].row_id, "o\u0001real-inbox-id",
			"a downstream method that doesn't yet understand this encoding must fail safely (not found), never silently collide with this profile's own real mailbox ids");
	});

	it("redirects the bare owner-placeholder path to that account's own INBOX", async() =>
	{
		// ralf, 2026-10-29 (live report): clicking the owner node itself (eg. "default") used to
		// silently revert to whatever was previously selected, since the placeholder was noSelect
		// and app.ts's changeFolder() refuses to select a noSelect node at all. It's no longer
		// noSelect (see getSharedAccountPlaceholders()'s own docblock) - a click now reaches here
		// and must behave like clicking a real account's own root node: land on its INBOX, not an
		// empty synthetic result.
		const {client, captured} = fakeClient(SHARED_SESSION, [fakeEmail()]);
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", client);

		const result = await jmap.getRows({selectedFolder : "1::user/teachers"});

		assert.equal(captured.mailboxQueryAccountId, "o", "must resolve INBOX within the shared account, not the primary one");
		assert.lengthOf(result.rows, 1);
	});

	it("throws a friendly error when the named owner is no longer shared with this user", async() =>
	{
		const {client} = fakeClient(SHARED_SESSION, []);
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", client);

		let thrown : any;
		try
		{
			await jmap.getRows({selectedFolder : "1::user/revoked/INBOX"});
		}
		catch (e)
		{
			thrown = e;
		}
		assert.exists(thrown);
		assert.include(thrown.message, "user/revoked/INBOX");
	});

	it("leaves an ordinary (non-shared) folder completely unaffected - uses this profile's own accountId", async() =>
	{
		const {client, captured} = fakeClient(SHARED_SESSION, [fakeEmail()]);
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", client);

		await jmap.getRows({selectedFolder : "1::INBOX"});

		assert.equal(captured.emailQueryAccountId, "ca", "an ordinary folder must still use this profile's own accountId");
	});
});
