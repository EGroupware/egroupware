import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Tracker #124251 (Sebastian Ender, via Birgit Becker/ralf): replying from a shared mailbox
 * (eg. fm@hmsender.de, with hausverwaltung@hmsender.de as an alias) as a user whose own personal
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
		to : [{name : 'Shared inbox', email : 'hausverwaltung@hmsender.de'}],
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
		textSignature : '', htmlSignature : '', mayDelete : false,
		...overrides,
	};
}

/** hmsender.de-shaped fixture: the account's own default identity (lowest id), a shared-mailbox
 *  alias that happens to match the reply target's To (also a valid identity on this account -
 *  the exact condition that lets recipient-matching win in the first place), and the user's own
 *  personal identity (second-lowest id) - matching Birgit's own repro description. */
function hmsenderIdentities() : JmapIdentity[]
{
	return [
		fakeIdentity({id : '10', email : 'fm@hmsender.de', name : 'fm default'}),
		fakeIdentity({id : '20', email : 't.seemann@hmsender.de', name : 'Tamara personal'}),
		fakeIdentity({id : '30', email : 'hausverwaltung@hmsender.de', name : 'Hausverwaltung alias'}),
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
		const {compose, et2} = createComposeForReply(createEgw(null), fakeContext(), hmsenderIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:30',
			"no preference set - the addressed-to alias (hausverwaltung@hmsender.de, id 30) wins, matching pre-fix behaviour");
	});

	it("explicit 'last-used' preference also still matches the addressed-to alias", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('last-used'), fakeContext(), hmsenderIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:30');
	});

	it("'personal' preference wins over the addressed-to alias - the actual tracker #124251 scenario", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('personal'), fakeContext(), hmsenderIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:20',
			"'personal' = second-lowest ident_id (20, Tamara's own personal identity), ignoring that the alias also matched the recipient");
	});

	it("'default' preference wins too, picking the account's own lowest ident_id instead of the matched alias", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('default'), fakeContext(), hmsenderIdentities());

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:10',
			"'default' = lowest ident_id (10), the account's own primary identity");
	});

	it("'personal' preference with only one identity falls back to that single (=default) identity, same as get_preferred_identity()'s own fallback", async() =>
	{
		const {compose, et2} = createComposeForReply(createEgw('personal'), fakeContext(), [fakeIdentity({id : '5'})], '1:5');

		await (compose as any).selectIdentityForRecipients(fakeContext());

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:5');
	});

	it("the preference applies even when there is no recipient match at all (a genuinely new default, not just an override)", async() =>
	{
		const context = fakeContext({to : [{email : 'nobody-matches@example.com'}]});
		const {compose, et2} = createComposeForReply(createEgw('personal'), context, hmsenderIdentities());

		await (compose as any).selectIdentityForRecipients(context);

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:20');
	});
});
