import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Found live 2026-11-08 (ralf, acc_id 89, folder "user/info/INBOX"): opening the 3rd message in a
 * shared account's folder showed the right header (subject/from/to, fetched correctly by
 * getRows() - see MailJmapGetRowsSharedAccount.test.ts) but a COMPLETELY UNRELATED body - a
 * decade-old message from the viewer's own mailbox. Root cause: getRows() already encodes the
 * message's REAL owning accountId into its row_id (SHARED_ACCOUNTS_ROOT_ID + accountId +
 * SHARED_ACCOUNT_SEP + realMailboxId, see decodeSharedMailboxId()'s own docblock - also confirmed
 * live by MailJmapGetRowsSharedAccount.test.ts's own "a downstream method that doesn't yet
 * understand this encoding must fail safely" comment), but every PER-ROW action (fetchBody(),
 * refreshRows(), fetchForReply(), verifyPgpSignature(), ...) just used `token.accountId` (the
 * VIEWER's own account) - and a JMAP Email id is only unique WITHIN its own account (no RFC 8621
 * global-id guarantee), so asking under the wrong account doesn't reliably fail, it can silently
 * return a real, unrelated message that happens to share the same id there.
 *
 * This covers the shared `rowAccountId()`/`accountIdForRow()` helpers directly, plus the two most
 * consequential per-row actions (fetchBody() - the exact bug reported; refreshRows() - the
 * identical bug in the row-refresh path) as representative coverage for the fix applied uniformly
 * across every per-row MailJmap method (fetchBodyFromMessagePart(), fetchAttachmentsMetadata(),
 * fetchForReply(), fetchForForwardAsAttachment(), importAttachedMessageToDrafts(),
 * verifyPgpSignature(), fetchRawSource()/fetchRawSourceBytesBase64(), repairAddressField(),
 * downloadAttachment(), getAttachmentViewUrl()).
 */

// \u0001/\u0002, NOT \u0000/\u0001 - see MailJmap.SHARED_ACCOUNTS_ROOT_ID's own docblock (the NUL
// byte this used to use got silently mangled into U+FFFD by the datagrid's own selection tracking)
const SHARED_ACCOUNTS_ROOT_ID = "\u0001shared-accounts\u0001";
const SHARED_ACCOUNT_SEP = "\u0002";

function sharedRowId(profileID : string, sharedAccountId : string, realMailboxId : string, emailId : string) : string
{
	return `mail::0::${profileID}::${SHARED_ACCOUNTS_ROOT_ID}${sharedAccountId}${SHARED_ACCOUNT_SEP}${realMailboxId}::${emailId}`;
}

function ordinaryRowId(profileID : string, mailboxId : string, emailId : string) : string
{
	return `mail::0::${profileID}::${mailboxId}::${emailId}`;
}

function createFakeEgw(overrides : Record<string, any> = {})
{
	return {
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
		link : (path : string) => path,
		...overrides,
	};
}

function createFakeApp(egwOverrides : Record<string, any> = {}) : MailApp
{
	return {
		egw : createFakeEgw(egwOverrides),
		getCustomLabels : () => ({}),
		getRowLabelTags : () => [],
	} as unknown as MailApp;
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "ca", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

describe("MailJmap.rowAccountId()/accountIdForRow() - shared-account row-id decoding", () =>
{
	it("decodes a shared row's REAL owning accountId instead of the viewer's own", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {});

		const accountId = await jmap.accountIdForRow(sharedRowId("1", "o", "real-inbox-id", "email1"));

		assert.equal(accountId, "o");
	});

	it("falls back to the viewer's own accountId for an ordinary (non-shared) row", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {});

		const accountId = await jmap.accountIdForRow(ordinaryRowId("1", "INBOX", "email1"));

		assert.equal(accountId, "ca");
	});
});

describe("MailJmap.fetchBody() - shared-account row", () =>
{
	it("fetches the message under its REAL owning account, not the viewer's own", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const email = {id : "email1", bodyStructure : {type : "text/plain", partId : "1"}, textBody : [{partId : "1", type : "text/plain"}],
			htmlBody : [], attachments : [], bodyValues : {1 : {value : "hi"}}};
		const captured : {accountId? : string} = {};
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Email : {get : (args : any) => { captured.accountId = args.accountId; return null; }}};
				buildFn(t);
				return [{emails : {list : [email]}}];
			},
		};
		primeToken(jmap, "1", client);

		const result : any = await jmap.fetchBody(sharedRowId("1", "o", "real-inbox-id", "email1"));

		assert.equal(captured.accountId, "o", "Email/get must use the shared account, not this profile's own token.accountId");
		assert.isFalse(result.special);
		assert.equal(result.accountId, "o", "the returned accountId (used by resolveInlineImages() etc.) must match too");
	});

	it("leaves an ordinary (non-shared) row's fetch on the viewer's own account", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const email = {id : "email1", bodyStructure : {type : "text/plain", partId : "1"}, textBody : [{partId : "1", type : "text/plain"}],
			htmlBody : [], attachments : [], bodyValues : {1 : {value : "hi"}}};
		const captured : {accountId? : string} = {};
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Email : {get : (args : any) => { captured.accountId = args.accountId; return null; }}};
				buildFn(t);
				return [{emails : {list : [email]}}];
			},
		};
		primeToken(jmap, "1", client);

		await jmap.fetchBody(ordinaryRowId("1", "INBOX", "email1"));

		assert.equal(captured.accountId, "ca");
	});

	it("shows a clear message instead of silently-wrong content for an S/MIME/TNEF/bare message in a shared folder", async() =>
	{
		let requestedServerResolve = false;
		const jmap = new MailJmap(createFakeApp({
			request : async(...args : any[]) => { requestedServerResolve = true; return {}; },
		}));
		// application/pkcs7-mime (SPECIAL_CASE_TYPES) at top level - triggers isSpecialCase()
		const email = {id : "email1", bodyStructure : {type : "application/pkcs7-mime"}, attachments : []};
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Email : {get : (_args : any) => null}};
				buildFn(t);
				return [{emails : {list : [email]}}];
			},
		};
		primeToken(jmap, "1", client);

		const result : any = await jmap.fetchBody(sharedRowId("1", "o", "real-inbox-id", "email1"));

		assert.isFalse(requestedServerResolve,
			"must never call the server's ajax_resolveSpecialCaseBody() for a shared-account row - " +
			"that PHP endpoint has no notion of the client-only shared-account row-id encoding at all, " +
			"and would otherwise resolve the row against the viewer's OWN account");
		assert.isFalse(result.special);
		assert.include(result.html, "not supported yet");
	});
});

describe("MailJmap refreshRows()/fetchRealRows() - shared-account row", () =>
{
	it("refreshes a shared row under its REAL owning account for both Email/get and mailboxRole()", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const email = {id : "email1", subject : "s", keywords : {}, size : 1, receivedAt : "2026-01-01T00:00:00Z",
			sentAt : "2026-01-01T00:00:00Z", from : [], to : [], cc : [], bcc : [], hasAttachment : false};
		const captured : {emailGetAccountId? : string, mailboxGetAccountId? : string} = {};
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {
					Email : {get : (args : any) => { captured.emailGetAccountId = args.accountId; return null; }},
					Mailbox : {get : (args : any) => { captured.mailboxGetAccountId = args.accountId; return null; }},
				};
				const result = buildFn(t);
				// dispatch by which shape this particular call built, same convention as
				// MailJmapGetRowsSharedAccount.test.ts's own fakeClient()
				if ('emails' in (result ?? {})) return [{emails : {list : [email]}}];
				return [{mailbox : {list : [{role : null}]}}];
			},
		};
		primeToken(jmap, "1", client);

		const rowId = sharedRowId("1", "o", "real-inbox-id", "email1");
		const result : any = await (jmap as any).fetchRealRows([rowId], false);

		assert.equal(captured.emailGetAccountId, "o", "refreshing a shared row's Email/get must use its real owning account");
		assert.equal(captured.mailboxGetAccountId, "o", "mailboxRole() must use the real owning account too");
		assert.lengthOf(result.order, 1);
	});
});
