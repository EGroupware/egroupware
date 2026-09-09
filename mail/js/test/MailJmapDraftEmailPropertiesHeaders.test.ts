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
