import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity} from "../jmap";

/**
 * Ticket #125092 (a customer via Birgit/Ingo): "Bei ihm ist eingestellt, dass die
 * Standard-Identität verwendet wird, es ist aber immer eine andere gesetzt" - the mail/
 * defaultIdentity preference's 'default'/'personal' values were never even consulted for a
 * genuinely NEW, blank compose window's own INITIAL identity selection - only
 * selectIdentityForRecipients() (reply/forward, see MailComposeDefaultIdentityPreference.test.ts,
 * tracker #124251) applied this preference at all, until #124821's own fix added
 * applyPreferredIdentityForNewCompose() for the new-compose case too. This file enumerates the
 * SAME three preference values again, but through THAT entry point (bootstrapSignature() ->
 * applyPreferredIdentityForNewCompose()), across more than one account - the "use the standard
 * identity of acc_id=X, not some other account's" requirement ralf described directly.
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

function fakeIdentity(overrides : Partial<JmapIdentity> = {}) : JmapIdentity
{
	return {
		id : '1', name : 'Me', email : 'me@example.com', replyTo : null, bcc : null,
		textSignature : '', htmlSignature : '', mayDelete : false, isStandard : false, isPersonal : false,
		...overrides,
	};
}

function createComposeForNew(egw : any, initialMailaccount : string, identitiesByAccount : Record<string, JmapIdentity[]>)
{
	const app = {egw} as unknown as MailApp;
	const jmap = new MailJmap(app);
	(jmap as any).getIdentities = async(accId : string) => identitiesByAccount[accId] ?? [];
	(app as any).jmap = jmap;

	const compose = new MailCompose(app);
	(compose as any).isJmapMode = true;
	const et2 = createFakeEt2(initialMailaccount);
	(compose as any).et2 = et2;
	return {compose, et2};
}

/** account 1's own flagged standard identity, one genuinely personal one (isPersonal), and one
 *  general/shared additional identity that is NEITHER (proving 'personal' doesn't just mean "not
 *  standard") - ordered as the server would return them (standard first - deliberately id===acc_id
 *  here too, to prove the fix reads the flags, not a coincidental id match). */
function accountOneIdentities() : JmapIdentity[]
{
	return [
		fakeIdentity({id : '1', email : 'standard@acc1.example', name : 'Account 1 standard', isStandard : true}),
		fakeIdentity({id : '15', email : 'personal@acc1.example', name : 'Account 1 personal', isPersonal : true}),
		fakeIdentity({id : '22', email : 'other@acc1.example', name : 'Account 1 general extra'}),
	];
}

/** account 2's own identities - the flagged standard one deliberately has the HIGHER id (3, not
 *  2), disproving BOTH the old "id===acc_id" assumption AND "lowest id" heuristic at once. */
function accountTwoIdentities() : JmapIdentity[]
{
	return [
		fakeIdentity({id : '2', email : 'personal@acc2.example', name : 'Account 2 personal', isPersonal : true}),
		fakeIdentity({id : '3', email : 'standard@acc2.example', name : 'Account 2 standard', isStandard : true}),
	];
}

describe("MailCompose.applyPreferredIdentityForNewCompose() - defaultIdentity preference (ticket #125092)", () =>
{
	it("'last-used'/unset leaves the server-rendered initial selection untouched", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw(null), '1:15', {'1' : accountOneIdentities()});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:15', "nothing should change for 'last-used'");
	});

	it("explicit 'last-used' also leaves the initial selection untouched", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('last-used'), '1:15', {'1' : accountOneIdentities()});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:15');
	});

	it("'default' selects the account's own flagged STANDARD identity, overriding whatever was initially selected", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('default'), '1:22', {'1' : accountOneIdentities()});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:1');
	});

	it("'personal' selects the identity flagged isPersonal, not the standard one or a general additional one", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('personal'), '1:22', {'1' : accountOneIdentities()});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:15',
			"id 15 is flagged isPersonal - id 22 (a general additional identity) must never be picked here");
	});

	it("'default-matching' resolves to the account's own flagged STANDARD identity, same as 'default', for a brand-new compose", async() =>
	{
		// ticket #125092 follow-up (ralf+Birgit): "use the standard identity of the active
		// account for new compose, reply/forward uses the first identity matching the mail
		// replied/forwarded" - the reply/forward-only matching half is covered by
		// MailComposeDefaultIdentityPreference.test.ts; this new-compose half has no recipient to
		// match against in the first place, so it must behave exactly like 'default'.
		const {compose, et2} = createComposeForNew(createEgw('default-matching'), '1:22', {'1' : accountOneIdentities()});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:1');
	});

	it("resolves the CORRECT account's flagged standard identity when the compose was opened for account 2, not account 1's", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('default'), '2:2', {
			'1' : accountOneIdentities(), '2' : accountTwoIdentities(),
		});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '2:3',
			"must use account 2's own flagged standard identity (id 3, the HIGHER one here) - never account 1's (id 1), even though both exist");
	});

	it("'personal' also stays scoped to account 2 when that's the open mailbox", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('personal'), '2:3', {
			'1' : accountOneIdentities(), '2' : accountTwoIdentities(),
		});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '2:2');
	});

	it("does nothing (no widget value) when the mailaccount widget has no value at all yet", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('default'), '', {'1' : accountOneIdentities()});

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '');
	});

	it("silently does nothing if getIdentities() fails - never blocks opening a new compose", async() =>
	{
		const {compose, et2} = createComposeForNew(createEgw('default'), '1:22', {});
		(compose as any).app.jmap.getIdentities = async() => { throw new Error('account not reachable'); };

		await (compose as any).applyPreferredIdentityForNewCompose();

		assert.strictEqual(et2.widgets.mailaccount.get_value(), '1:22', "untouched - the preference lookup itself failed");
	});
});
