import {assert} from "@open-wc/testing";
import {describeFailedRecipientsWarning} from "../jmap";

/**
 * Ticket #125201 (a real customer: sending to a ~300-address distribution list used to fail
 * entirely because one address's domain no longer exists): Api\Mailer::send() now retries once,
 * excluding whichever recipients the SMTP server rejected, instead of throwing - the message DOES
 * go out to everyone else. describeFailedRecipientsWarning() builds the one sentence that tells
 * the user which addresses did NOT get it, from Api\Mail\Jmap\Imap::emailSubmissionSet()'s own
 * shim-only "failedRecipients" extension (EmailSubmission/set's created.sub1) - see
 * MailJmap.sendNewEmail()'s own call site for how this map gets there in the first place.
 */
const egw = {
	lang : (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
};

describe("describeFailedRecipientsWarning()", () =>
{
	it("returns null when there is nothing to report (undefined - a real Stalwart account never sends this extension at all)", () =>
	{
		assert.isNull(describeFailedRecipientsWarning(egw, undefined));
	});

	it("returns null for an empty map (an ordinary, fully successful send)", () =>
	{
		assert.isNull(describeFailedRecipientsWarning(egw, {}));
	});

	it("names the address and reason for a single failed recipient", () =>
	{
		const message = describeFailedRecipientsWarning(egw, {
			'info@khami-fitness.de': 'Recipient address rejected: Domain not found',
		});

		assert.equal(message,
			'The mail was sent successfully to all recipients, except the following: ' +
			'info@khami-fitness.de (Recipient address rejected: Domain not found)');
	});

	it("lists every failed recipient, not just the first", () =>
	{
		const message = describeFailedRecipientsWarning(egw, {
			'one@example.org': 'Domain not found',
			'two@example.org': 'Mailbox unavailable',
		});

		assert.equal(message,
			'The mail was sent successfully to all recipients, except the following: ' +
			'one@example.org (Domain not found), two@example.org (Mailbox unavailable)');
	});
});
