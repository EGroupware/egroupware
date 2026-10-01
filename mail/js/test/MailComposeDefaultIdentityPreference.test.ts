import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Tracker #124251 (a customer, via Birgit Becker/ralf): replying from a shared mailbox
 * (eg. standard@example.org, with alias@example.org as an alias) as a user whose own personal
 * identity also happens to match the ADDRESSED-TO alias picked that alias as the "From" - even
 * though the user's "Default identity for compose" preference (mail/defaultIdentity) was set to
 * "personal", which should always win for exactly this "answer a shared inbox with my own
 * personal address" use case. Root cause: MailCompose.selectIdentityForRecipients() (mail/js/
 * compose.ts) unconditionally matches the identity to the original message's To/Cc, with no
 * regard for that preference at all - the classic mail_compose.inc.php always had this preference
 * WIN over recipient-matching (its own get_preferred_identity(), deleted in 3bca66cf01
 * "mail: delete mail_compose::compose() and its exclusively-used helpers"), but the new
 * client-side JMAP reply path never reimplemented that precedence.
 *
 * Only the preference-precedence piece is under test here - selectIdentityForRecipients()'s own
 * recipient-matching behaviour (no preference set) is already covered by
 * MailComposeBootstrapRace.test.ts.
 *
 * Ticket #125092 (a customer via Birgit/Ingo): preferredIdentityFromPreference()'s 'default'
 * used to just mean "the account's own lowest ident_id", which isn't reliably the account's real
 * standard identity at all - see that method's own docblock. The real standard identity is
 * whatever Api\Mail\Jmap\Identity::synthesize() flags as `isStandard` (the account's own
 * `egw_ea_accounts.ident_id` column, admin-settable, independent of both acc_id and ident_id
 * ordering) - the fixture below deliberately keeps non-sequential, acc_id-unrelated ids to prove
 * the fix no longer depends on id===profileID or "lowest id" at all, only on this flag.
 */

function createEgw(preferenceValue : string | null) : any
{
	return {
		lang : (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference : (key : string, app? : string) => (key === 'defaultIdentity' && app === 'mail') ? preferenceValue : null,
		message : (_msg : string, _type? : string) => {},
	};
}

function createFakeApp(egw : any) : MailApp
{
	return {egw, _set_Window_title : () => {}} as unknown as MailApp;
}

function createFakeWidget(id : string, initial : any = '')
{
	return {
		id, _value : initial,
		get_value() { return this._value; },
		set_value(v : any) { this._value = v; },
		getValue() { return this._value; },
		set_disabled() {},
		getParent() { return null; },
		getDOMNode() { return null; },
	};
}

const WIDGET_IDS = ['mailaccount', 'mimeType', 'to', 'cc', 'subject', 'mail_htmltext', 'mail_plaintext'];

function createFakeEt2(initialMailaccount : string)
{
	const widgets : Record<string, any> = {};
	for (const id of WIDGET_IDS) widgets[id] = createFakeWidget(id);
	widgets.mailaccount.set_value(initialMailaccount);
	return {
		getWidgetById : (id : string) => widgets[id],
		getArrayMgr : (_name : string) => ({getEntry : (_key : string) => undefined, data : {}}),
		setArrayMgr : (_name : string, _mgr : any) => {},
		getInstanceManager : () => ({resetDirty : () => {}, etemplate_exec_id : 'test'}),
		widgets,
	};
}

function fakeContext(overrides : Partial<JmapReplyContext> = {}) : JmapReplyContext
{
	return {
		from : [{name : 'Sender', email : 'sender@example.com'}],
		to : [{name : 'Shared inbox', email : 'alias@example.org'}],
		cc : [],
		bcc : [],
		replyTo : null,
		subject : 'Original subject',
		date : '2026-01-01T00:00:00Z',
		mimeType : 'plain',
		body : 'the original body',
		profileID : '1',
		inReplyTo : ['msg1@example.com'],
		references : ['msg1@example.com'],
		attachments : [],
		threadTopic : null,
		threadIndex : null,
		listId : null,
		autocrypt : null,
		...overrides,
	};
}

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id : '1', name : 'Me', email : 'me@example.com', replyTo : null, bcc : null,
		textSignature : '', htmlSignature : '', mayDelete : false, isStandard : false, isPersonal : false,
		...overrides,
	};
}

/** example.org-shaped fixture: the account's own standard identity (flagged isStandard - its id
 *  (10) deliberately does NOT equal the profileID (1) used throughout this file, proving the fix
 *  no longer relies on that coincidence at all), a shared-mailbox alias that happens to match the
 *  reply target's To (also a valid identity on this account - the exact condition that lets
 *  recipient-matching win in the first place, and itself general/isPersonal:false - a shared alias
 *  usable by anyone with access to this mailbox, not personal to any one user), and the user's own
 *  genuinely personal identity (flagged isPersonal) - matching Birgit's own repro description. */
function sharedMailboxIdentities() : JmapIdentity[]
{
	return [
		fakeIdentity({id : '10', email : 'standard@example.org', name : 'Shared mailbox standard', isStandard : true}),
		fakeIdentity({id : '20', email : 'personal@example.org', name : 'Users own personal identity', isPersonal : true}),
		fakeIdentity({id : '30', email : 'alias@example.org', name : 'Shared mailbox alias'}),
	];
}

function createComposeForReply(egw : any, context : JmapReplyContext, identities : JmapIdentity[], initialMailaccount = '1:10')
{
	const app = createFakeApp(egw);
	const jmap = new MailJmap(app);
	(jmap as any).fetchForReply = async() => context;
	(jmap as any).getIdentities = async() => identities;
	(app as any).jmap = jmap;

	const compose = new MailCompose(app);
	(compose as any).isJmapMode = true;
	const et2 = createFakeEt2(initialMailaccount);
	(compose as any).et2 = et2;
	return {compose, et2};
}

describe("MailCompose.selectIdentityForRecipients() - defaultIdentity preference precedence (tracker #124251)", () =>
{
	it("without the preference set (unset/'last-used'), still matches the addressed-to alias - unchanged existing behaviour", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw(null), fakeContext(), sharedMailboxIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:30',
			"no preference set - the addressed-to alias (alias@example.org, id 30) wins, matching pre-fix behaviour");
	});

	it("explicit 'last-used' preference also still matches the addressed-to alias", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('last-used'), fakeContext(), sharedMailboxIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:30');
	});

	it("'personal' preference wins over the addressed-to alias - the actual tracker #124251 scenario", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('personal'), fakeContext(), sharedMailboxIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:20',
			"'personal' = the identity flagged isPersonal (20, the user's own personal identity), ignoring that the alias also matched the recipient");
	});

	it("'personal' preference does NOT match a general/shared additional identity (account_id=0) - only a genuinely personal one", async() =>
	{
		// a shared account with a standard identity and ONE additional identity that's also
		// general (eg. a second shared alias) - nobody's own personal identity exists here at all
		const noPersonalIdentities = [
			fakeIdentity({id : '10', email : 'standard@example.org', name : 'Shared mailbox standard', isStandard : true}),
			fakeIdentity({id : '30', email : 'alias@example.org', name : 'Shared mailbox alias'}),
		];
		const {compose, et2} = createComposeForReply(createEgw('personal'), fakeContext(), noPersonalIdentities);

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:10',
			"no identity is flagged isPersonal - falls back to the standard one, never the general additional alias");
	});

	it("'default' preference wins too, picking the account's own flagged STANDARD identity instead of the matched alias", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('default'), fakeContext(), sharedMailboxIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:10',
			"'default' = whichever identity is flagged isStandard (10), not merely the lowest id present");
	});

	it("'personal' preference with only one identity falls back to that single (=default) identity, same as get_preferred_identity()'s own fallback", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('personal'), fakeContext(), [fakeIdentity({id : '5'})], '1:5');

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:5');
	});

	it("'default' preference falls back to the lowest-id identity when NONE is flagged isStandard at all (defensive - shouldn't normally happen)", async() =>
	{
		const context = fakeContext({profileID : '1'});
		const oddIdentities = [fakeIdentity({id : '50'}), fakeIdentity({id : '20'})];
		const {compose, et2} = createComposeForReply(createEgw('default'), context, oddIdentities, '1:20');

		await (compose as any).selectIdentityForRecipients(context);

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:20',
			"no identity is flagged isStandard - falls back to the lowest-id one (20) rather than crashing or picking nothing");
	});

	it("the preference applies even when there is no recipient match at all (a genuinely new default, not just an override)", async() =>
	{
		const context = fakeContext({to : [{email : 'nobody-matches@example.com'}]});
		const {compose, et2} = createComposeForReply(createEgw('personal'), context, sharedMailboxIdentities());

		await (compose as any).selectIdentityForRecipients(context);

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:20');
	});

	it("'default' resolves each account's OWN flagged standard identity independently - not cross-contaminated between accounts", async() =>
	{
		const accountTwoIdentities = [
			fakeIdentity({id : '2', email : 'extra@other.example', name : 'Other account extra'}),
			fakeIdentity({id : '99', email : 'standard@other.example', name : 'Other account standard', isStandard : true}),
		];
		const contextOne = fakeContext({profileID : '1'});
		const contextTwo = fakeContext({profileID : '2', to : [{email : 'nobody-matches@example.com'}]});

		const {compose : composeOne, et2 : et2One} = createComposeForReply(createEgw('default'), contextOne, sharedMailboxIdentities());
		await (composeOne as any).selectIdentityForRecipients(contextOne);
		assert.strictEqual(et2One.widgets.mailaccount.get_value(), '1:10', "account 1's own flagged standard identity (id 10)");

		const {compose : composeTwo, et2 : et2Two} = createComposeForReply(createEgw('default'), contextTwo, accountTwoIdentities, '2:2');
		await (composeTwo as any).selectIdentityForRecipients(contextTwo);
		assert.strictEqual(et2Two.widgets.mailaccount.get_value(), '2:99',
			"account 2's own flagged standard identity (id 99, deliberately NOT the lowest id) - not account 1's");
	});
});
