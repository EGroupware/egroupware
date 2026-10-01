import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";

/**
 * Ticket #125161 (a customer, forwarding from a function mailbox): trySendViaJmap() never checked
 * for at least one recipient before actually sending - an empty envelope silently went all the way
 * to the server, which only rejected it deep inside the real SMTP transaction ("valid RCPT command
 * must precede DATA" - a PHP-error-log-only failure, the browser showed nothing specific at all).
 * hasNoRecipientsAtAll() is the new guard trySendViaJmap() now checks first; this file covers it
 * directly (that method itself has too many unrelated preconditions - composeToolbar/mailvelope/
 * currentEmailFields() - to drive in a focused test, see MailComposeSourceMessageFlags.test.ts's
 * own docblock for the same reasoning applied elsewhere).
 */
describe("MailCompose.hasNoRecipientsAtAll()", () =>
{
	const call = (email : any) : boolean => (MailCompose as any).hasNoRecipientsAtAll(email);

	it("true when to/cc/bcc are all entirely missing", () =>
	{
		assert.isTrue(call({}));
	});

	it("true when to/cc/bcc are all empty strings", () =>
	{
		assert.isTrue(call({to : '', cc : '', bcc : ''}));
	});

	it("true when to/cc/bcc are all empty arrays", () =>
	{
		assert.isTrue(call({to : [], cc : [], bcc : []}));
	});

	it("true when to/cc/bcc only contain blank/whitespace entries - a comma with nothing real either side", () =>
	{
		assert.isTrue(call({to : ' , ,  ', cc : [' ', ''], bcc : undefined}));
	});

	it("false when 'to' has a real address, as a plain string", () =>
	{
		assert.isFalse(call({to : 'someone@example.org', cc : '', bcc : ''}));
	});

	it("false when 'to' has a real address, as an array", () =>
	{
		assert.isFalse(call({to : ['someone@example.org'], cc : [], bcc : []}));
	});

	it("false when ONLY 'cc' has a real address - to/bcc empty", () =>
	{
		assert.isFalse(call({to : '', cc : 'someone@example.org', bcc : ''}));
	});

	it("false when ONLY 'bcc' has a real address - to/cc empty", () =>
	{
		assert.isFalse(call({to : [], cc : [], bcc : ['someone@example.org']}));
	});
});
