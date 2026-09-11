import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Regression coverage for the read side of the bug found live 2026-09-09 (ralf: "Does that mean
 * they [Thread-Topic/Thread-Index/List-Id] are lost when replying to a mail? ... it would be a
 * real regression we need to fix"): MailJmap.fetchForReply() never requested or read the original
 * message's own Thread-Topic/Thread-Index/List-Id headers at all - classic getReplyData()'s
 * equivalent propagation (2014 commit 2172fc769d) had silently regressed in the JMAP-native path.
 * See api/tests/Mail/Jmap/ImapBuildMailerTest.php and MailJmapDraftEmailPropertiesHeaders.test.ts
 * for the write-side counterpart (fixed alongside this), and
 * mail/tests/JmapShimReplyThreadHeadersTest.php for the shim's own read-side fetch.
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

/** Plain-text-only fixture - deliberately no htmlBody, so useHtml stays false and no inline-image resolution is ever triggered. */
function fixtureEmail(overrides : Record<string, any> = {}) : any
{
	return {
		from : [], to : [], cc : [], bcc : [], replyTo : null,
		subject : "Original subject", sentAt : "2026-01-01T00:00:00Z",
		messageId : ["msg1@example.com"], references : [],
		textBody : [{partId : "p1", type : "text/plain"}],
		bodyValues : {p1 : {value : "the original body"}},
		attachments : [],
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

describe("MailJmap.fetchForReply() - Thread-Topic/Thread-Index/List-Id reply propagation", () =>
{
	it("requests all three header properties from the server", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const captured = primeWithEmail(jmap, "1", fixtureEmail());

		await jmap.fetchForReply("1::1::INBOX::42");

		assert.include(captured.capturedArgs.properties, "header:thread-topic:asText");
		assert.include(captured.capturedArgs.properties, "header:thread-index:asText");
		assert.include(captured.capturedArgs.properties, "header:list-id:asText");
	});

	it("maps all three into the returned JmapReplyContext when the original message has them", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail({
			"header:thread-topic:asText" : "Original subject",
			"header:thread-index:asText" : "AQHTest1234567890abcdefg==",
			"header:list-id:asText" : "My List <mylist.example.org>",
		}));

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.equal(context.threadTopic, "Original subject");
		assert.equal(context.threadIndex, "AQHTest1234567890abcdefg==");
		assert.equal(context.listId, "My List <mylist.example.org>");
	});

	it("maps all three to null when the original message has none of them", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithEmail(jmap, "1", fixtureEmail());

		const context = await jmap.fetchForReply("1::1::INBOX::42");

		assert.isNull(context.threadTopic);
		assert.isNull(context.threadIndex);
		assert.isNull(context.listId);
	});
});
