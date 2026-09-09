import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Regression coverage for the bug found live 2026-09-09 (ralf, relaying a tester report: "the
 * selected ReplyTo is NOT send with the mail"): MailJmap.draftEmailProperties() - the shared
 * Email property-set builder behind both sendNewEmail() and saveDraft() - never carried
 * replyTo/priority/disposition-notification-to through at all, on either JMAP backend, so every
 * JMAP-native send silently dropped them. See JmapNewEmail's own docblock (mail/js/jmap.ts) for
 * the full story, and api/tests/Mail/Jmap/ImapBuildMailerTest.php for the shim-side counterpart
 * (Api\Mail\Jmap\Imap::buildMailerFromEmailProperties(), fixed alongside this).
 *
 * draftEmailProperties() is a pure, synchronous function of its (identity, email) params - no
 * token/client/network involved - so it's exercised directly here (bypassing its `private`
 * modifier, same technique already used for retryAttachmentIndexForRow() this session) rather
 * than driving the much heavier sendNewEmail()/resolveComposeContext() orchestration.
 */

const egw = {
	lang : (label : string) => label,
};

function createFakeApp() : MailApp
{
	return {egw} as unknown as MailApp;
}

const IDENTITY = {id : "1", email : "sender@example.org", name : "Sender"};

function baseEmail(overrides : Record<string, any> = {}) : any
{
	return {to : ["recipient@example.org"], subject : "Test", body : "Hello", isHtml : false, ...overrides};
}

describe("MailJmap.draftEmailProperties() - replyTo/priority/disposition headers", () =>
{
	it("includes replyTo, converted the same way as to/cc/bcc, when provided", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY,
			baseEmail({replyTo : ["Reply Here <reply-here@example.org>"]}));

		assert.deepEqual(properties.replyTo, [{email : "reply-here@example.org", name : "Reply Here"}]);
	});

	it("omits replyTo entirely when not provided", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail());

		assert.notProperty(properties, "replyTo");
	});

	it("sets header:X-Priority from the raw priority value when provided", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail({priority : "1"}));

		assert.equal(properties["header:X-Priority"], "1");
	});

	it("omits header:X-Priority when priority is not provided", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail());

		assert.notProperty(properties, "header:X-Priority");
	});

	it("sets header:Disposition-Notification-To to the SENDING identity's own address when a read receipt is requested", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail({requestReadReceipt : true}));

		assert.equal(properties["header:Disposition-Notification-To"], "sender@example.org");
	});

	it("omits header:Disposition-Notification-To when no read receipt is requested", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail({requestReadReceipt : false}));

		assert.notProperty(properties, "header:Disposition-Notification-To");
	});
});

/**
 * Follow-up regression coverage (2026-09-09, ralf, after the fixes above: "Does that mean they
 * [Thread-Topic/Thread-Index/List-Id] are lost when replying to a mail? ... it would be a real
 * regression we need to fix"): classic getReplyData()'s own propagation of these three headers
 * from the original message onto a reply (2014 commit 2172fc769d) had no JMAP-native equivalent -
 * MailCompose.bootstrapReply() now sets them from MailJmap.fetchForReply()'s result (see
 * JmapReplyContext's own docblock), and draftEmailProperties() carries them through here.
 */
describe("MailJmap.draftEmailProperties() - Thread-Topic/Thread-Index/List-Id reply propagation", () =>
{
	it("sets all three raw header properties when propagated from a reply", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail({
			threadTopic : "Original subject",
			threadIndex : "AQHTest1234567890abcdefg==",
			listId : "My List <mylist.example.org>",
		}));

		assert.equal(properties["header:Thread-Topic"], "Original subject");
		assert.equal(properties["header:Thread-Index"], "AQHTest1234567890abcdefg==");
		assert.equal(properties["header:List-Id"], "My List <mylist.example.org>");
	});

	it("omits all three when the original message had none (a plain new message, or a forward)", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail());

		assert.notProperty(properties, "header:Thread-Topic");
		assert.notProperty(properties, "header:Thread-Index");
		assert.notProperty(properties, "header:List-Id");
	});

	it("resolves each of the three independently - only threadTopic given, the other two stay omitted", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		const properties = (jmap as any).draftEmailProperties(IDENTITY, baseEmail({threadTopic : "Only this one"}));

		assert.equal(properties["header:Thread-Topic"], "Only this one");
		assert.notProperty(properties, "header:Thread-Index");
		assert.notProperty(properties, "header:List-Id");
	});
});
