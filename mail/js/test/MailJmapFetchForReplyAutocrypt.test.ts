import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * MailJmap.fetchForReply()'s new `context.autocrypt` field (Autocrypt Phase 5 item 4's remaining
 * wiring, doc/ai/projects/mail-pgp-signature-verification.md, 2026-09-09) - requests ALL
 * `Autocrypt:` header values (`header:Autocrypt:all`) plus the top-level Content-Type, and applies
 * Level 1's own "skip peer-state-relevant processing entirely" guards (multipart/report, more than
 * one From address) on top of parseAutocryptHeaders()'s already-covered multiple-valid-header rule
 * (PgpAutocryptHeaderParsing.test.ts) before handing anything to MailCompose.bootstrapReply()
 * (`mail/js/compose.ts`).
 */
const egw = {
	user : (_key : string) => 1,
	lang : (label : string) => label,
	preference : (_key : string, _app? : string) => null,
	config : (_name : string, _app? : string) => null,
	request : async() => ({}),
	message : (_msg : string, _type? : string) => {},
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

function primeToken(jmap : MailJmap, profileID : string, client : any) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
	};
	(jmap as any).clients[profileID] = client;
}

function fixtureEmail(overrides : Record<string, any> = {}) : any
{
	return {
		from : [{email : "sender@example.invalid"}], to : [], cc : [], bcc : [], replyTo : null,
		subject : "Original subject", sentAt : "2026-01-01T00:00:00Z",
		messageId : ["msg1@example.com"], references : [],
		textBody : [{partId : "p1", type : "text/plain"}],
		bodyValues : {p1 : {value : "the original body"}},
		attachments : [],
		"header:content-type" : "text/plain",
		...overrides,
	};
}

function primeWithEmail(jmap : MailJmap, profileID : string, email : any) : {capturedArgs : any}
{
	const captured : {capturedArgs : any} = {capturedArgs : undefined};
	primeToken(jmap, profileID, {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {Email : {get : (args : any) => { captured.capturedArgs = args; return {list : [email]}; }}};
			return [buildFn(t)];
		},
	});
	return captured;
}

const VALID_HEADER = "addr=sender@example.invalid; keydata=AAAABASE64KEYDATA====";

describe("MailJmap.fetchForReply() - Autocrypt header propagation", () =>
{
	it("requests the Autocrypt header (all instances) and top-level content-type", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const captured = primeWithEmail(jmap, "1", fixtureEmail());

		await jmap.fetchForReply("1::1::INBOX::42");

		assert.include(captured.capturedArgs.properties, "header:Autocrypt:all");
		assert.include(captured.capturedArgs.properties, "header:content-type");
	});

	it("parses a single valid Autocrypt header into context.autocrypt", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({"header:Autocrypt:all" : [VALID_HEADER]}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.deepEqual(context!.autocrypt, {
			addr : "sender@example.invalid", keydata : "AAAABASE64KEYDATA====", preferEncrypt : "nopreference",
		});
	});

	it("is null when there's no Autocrypt header at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail());

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.isNull(context!.autocrypt);
	});

	it("is null (all discarded) when MORE THAN ONE Autocrypt header is present, even if each "
		+ "individually parses", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			"header:Autocrypt:all" : [VALID_HEADER, "addr=sender@example.invalid; keydata=OTHERKEY"],
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.isNull(context!.autocrypt);
	});

	it("is null for a message with more than one From address (Level 1's own skip rule)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			from : [{email : "sender@example.invalid"}, {email : "other@example.invalid"}],
			"header:Autocrypt:all" : [VALID_HEADER],
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.isNull(context!.autocrypt);
	});

	it("is null for a multipart/report message (eg. an MDN), even with an otherwise-valid header", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			"header:content-type" : "multipart/report; report-type=disposition-notification",
			"header:Autocrypt:all" : [VALID_HEADER],
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.isNull(context!.autocrypt);
	});
});
