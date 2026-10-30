import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Ticket #126191 follow-up (ralf, 2026-10-30): "Open as new should also select the identity
 * matching the opened mail, if one exists" - bootstrapComposeAsNew() previously only ever ran
 * applyPreferredIdentityForNewCompose() (the generic `defaultIdentity` preference), completely
 * ignoring which of the account's OWN identities the original message was actually sent/drafted
 * as. Reopening a message you sent under a non-default alias silently switched you to whatever the
 * generic preference happened to prefer instead - and, as a side effect, made
 * lastKnownSignatureIdentity's own best-effort old-signature match (see
 * MailComposeEditAsNewSignatureReplace.test.ts) far less likely to actually find anything, since
 * the presumed identity no longer matched the body's own real, inherited signature.
 *
 * Same "real MailJmap, fake widgets" harness as MailComposePredefinedAddresses.test.ts's own
 * createComposeForReply() (reused here, not imported, kept self-contained).
 */

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

const WIDGET_IDS = ['mailaccount', 'mimeType', 'to', 'cc', 'bcc', 'subject', 'mail_htmltext', 'mail_plaintext'];

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
		from : [{name : 'Me (alias)', email : 'alias@example.com'}],
		to : [{name : 'Someone', email : 'someone@example.com'}],
		cc : [], bcc : [], replyTo : null,
		subject : 'Original subject', date : '2026-01-01T00:00:00Z',
		mimeType : 'plain', body : 'the original body', profileID : '1',
		inReplyTo : null, references : null,
		attachments : [],
		threadTopic : null, threadIndex : null, listId : null, autocrypt : null,
		...overrides,
	};
}

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id : '0', name : 'Standard', email : 'standard@example.com', replyTo : null, bcc : null,
		textSignature : '', htmlSignature : '', mayDelete : false, isStandard : true, isPersonal : false,
		...overrides,
	};
}

const STANDARD_IDENTITY = fakeIdentity({id : '0', email : 'standard@example.com', isStandard : true});
// isPersonal:true here too (not just a non-standard alias) - lets the 'personal' test below prove
// the generic preferred-identity fallback actually ran, by landing on THIS identity instead of
// createFakeEt2()'s own initial '1:0' value, rather than a same-as-default id that can't tell
// "ran and happened to re-pick the same one" apart from "never ran at all".
const ALIAS_IDENTITY = fakeIdentity({id : '3', name : 'Me (alias)', email : 'alias@example.com', isStandard : false, isPersonal : true});

function createComposeForNew(context : JmapReplyContext, identities : JmapIdentity[], preference : (key : string, app? : string) => any = () => null)
{
	const egw : any = {
		lang : (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference,
		message : (_msg : string, _type? : string) => {},
	};
	const app = {egw, _set_Window_title : () => {}} as unknown as MailApp;
	const jmap = new MailJmap(app);
	(jmap as any).fetchForReply = async() => context;
	(jmap as any).getIdentities = async() => identities;
	(app as any).jmap = jmap;

	const compose = new MailCompose(app);
	(compose as any).isJmapMode = true;
	(compose as any).et2 = createFakeEt2('1:0');
	return compose;
}

describe("MailCompose.bootstrapComposeAsNew() selects the identity matching the original From (ticket #126191 follow-up)", () =>
{
	it("selects the alias identity the original message was actually sent as, not the standard one", async() =>
	{
		const compose = createComposeForNew(fakeContext(), [STANDARD_IDENTITY, ALIAS_IDENTITY]);
		await (compose as any).bootstrapComposeAsNew('1::1::INBOX::42');

		assert.strictEqual((compose as any).et2.getWidgetById('mailaccount').getValue(), '1:3');
	});

	it("also feeds lastKnownSignatureIdentity with the matched identity, not whatever was there before", async() =>
	{
		const compose = createComposeForNew(fakeContext(), [STANDARD_IDENTITY, ALIAS_IDENTITY]);
		await (compose as any).bootstrapComposeAsNew('1::1::INBOX::42');

		assert.strictEqual((compose as any).lastKnownSignatureIdentity, '1:3');
	});

	it("falls back to the generic preferred-identity logic when the From address matches none of the account's own identities", async() =>
	{
		const context = fakeContext({from : [{name : 'Someone else', email : 'nobody@example.org'}]});
		const compose = createComposeForNew(context, [STANDARD_IDENTITY, ALIAS_IDENTITY],
			(key) => key === 'defaultIdentity' ? 'personal' : null);
		await (compose as any).bootstrapComposeAsNew('1::1::INBOX::42');

		// 'personal' preference picks the account's own isPersonal identity (ALIAS_IDENTITY here) -
		// landing on it instead of createFakeEt2()'s own initial '1:0' proves the generic
		// preferred-identity fallback really ran, not just "nothing changed".
		assert.strictEqual((compose as any).et2.getWidgetById('mailaccount').getValue(), '1:3');
	});

	it("leaves the pre-selected identity alone when there's no From match AND no defaultIdentity preference set", async() =>
	{
		const context = fakeContext({from : [{name : 'Someone else', email : 'nobody@example.org'}]});
		const compose = createComposeForNew(context, [STANDARD_IDENTITY, ALIAS_IDENTITY]);
		await (compose as any).bootstrapComposeAsNew('1::1::INBOX::42');

		assert.strictEqual((compose as any).et2.getWidgetById('mailaccount').getValue(), '1:0',
			"unchanged from createFakeEt2()'s own initial value - neither match nor preference fired");
	});
});
