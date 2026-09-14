import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * MailJmap.resolveDistributionLists() (private, called from sendNewEmail() before building the
 * outgoing message) - the JMAP-native send path's own equivalent of classic createMessage()'s
 * ComposeMessageBuilder::resolveEmailAddressList() call, which never ran at all for a JMAP-native
 * send. Found live 2026-09-14 (ralf, relaying a real user's report the morning after this
 * feature's rollout): sending to a distribution list failed with "mail for
 * lists.egroupware.org loops back to myself" - the
 * addressbook-picker's own placeholder address (Et2Email's "Name" <listId@lists.egroupware.org>,
 * see mail/src/Compose.php's get_lists()) was submitted to the real MTA verbatim.
 */
describe('MailJmap.resolveDistributionLists()', () =>
{
	let jmap : MailJmap;
	let request : sinon.SinonSpy;

	beforeEach(() =>
	{
		request = sinon.spy(async(_menuaction : string, _params : any[]) => ({
			to : ['member1@example.org', 'member2@example.org'],
			cc : [],
			bcc : [],
		}));
		const egw = {request};
		jmap = new MailJmap({egw} as unknown as MailApp);
	});

	it('is a no-op (no server round trip) when no address is a distribution-list placeholder', async() =>
	{
		const email = {to : 'plain@example.org', cc : ['other@example.org'], subject : 's', body : 'b'};

		const result = await (jmap as any).resolveDistributionLists(email);

		assert.isFalse(request.called);
		assert.strictEqual(result, email, 'unchanged input returned as-is');
	});

	it('expands a "Name <id@lists.egroupware.org>" placeholder in to', async() =>
	{
		const email = {to : '"Some List" <5@lists.egroupware.org>', subject : 's', body : 'b'};

		const result = await (jmap as any).resolveDistributionLists(email);

		assert.isTrue(request.calledOnce);
		assert.equal(request.firstCall.args[0], 'mail.EGroupware\\Mail\\Compose.ajax_resolveDistributionLists');
		assert.deepEqual(request.firstCall.args[1], [{
			to : ['"Some List" <5@lists.egroupware.org>'], cc : [], bcc : [],
		}]);
		assert.deepEqual(result.to, ['member1@example.org', 'member2@example.org']);
	});

	it('also detects a bare numeric list id (no "Name <...>" wrapper)', async() =>
	{
		const email = {to : ['5'], subject : 's', body : 'b'};

		await (jmap as any).resolveDistributionLists(email);

		assert.isTrue(request.calledOnce);
	});

	it('detects a placeholder in cc/bcc too, not just to', async() =>
	{
		const email = {to : 'plain@example.org', cc : '"List" <-3@lists.egroupware.org>', subject : 's', body : 'b'};

		await (jmap as any).resolveDistributionLists(email);

		assert.isTrue(request.calledOnce);
	});

	it('preserves every other email property untouched', async() =>
	{
		const email = {
			to : '"List" <5@lists.egroupware.org>', subject : 'Subject', body : 'Body', isHtml : true,
			attachments : [{name : 'a.pdf'}],
		};

		const result = await (jmap as any).resolveDistributionLists(email);

		assert.equal(result.subject, 'Subject');
		assert.equal(result.body, 'Body');
		assert.isTrue(result.isHtml);
		assert.deepEqual(result.attachments, [{name : 'a.pdf'}]);
	});
});
