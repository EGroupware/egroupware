import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";

/**
 * Ticket #125161 follow-up (ralf, same ticket as hasNoRecipientsAtAll(): "I believe in the old
 * app we had a guard against no recipient or empty subject, and refused to send in both cases") -
 * confirmed in the deleted mail_compose.inc.php's own compose() (git show 3bca66cf01):
 * `strlen(trim($_content['subject']))==0` blocked the send entirely, same hard-block precedent
 * hasNoRecipientsAtAll() already restored. hasNoSubject() is trySendViaJmap()'s own equivalent
 * check; covered directly here for the same reason as that one (too many unrelated preconditions
 * on trySendViaJmap() itself to drive in a focused test).
 */
describe("MailCompose.hasNoSubject()", () =>
{
	const call = (email : any) : boolean => (MailCompose as any).hasNoSubject(email);

	it("true when subject is entirely missing", () =>
	{
		assert.isTrue(call({}));
	});

	it("true when subject is an empty string", () =>
	{
		assert.isTrue(call({subject : ''}));
	});

	it("true when subject is only whitespace", () =>
	{
		assert.isTrue(call({subject : '   '}));
	});

	it("false when subject has real text", () =>
	{
		assert.isFalse(call({subject : 'Hello'}));
	});

	it("false when subject has real text surrounded by whitespace", () =>
	{
		assert.isFalse(call({subject : '  Hello  '}));
	});
});
