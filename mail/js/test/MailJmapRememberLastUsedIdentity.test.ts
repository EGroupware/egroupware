import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #125092 (Ingo/Birgit Becker): the classic, now-deleted
 * mail_compose::compose()'s own LastSignatureIDUsed write-back (commit 0fcea2103a, "remember last
 * used Signature on Compose, and try to use it on subsequent compose sessions") was deleted along
 * with that whole method (3bca66cf01) and never reimplemented for the new client-side JMAP send -
 * "use last used signature" (mail/defaultIdentity unset/'last-used') silently never remembered
 * anything, no matter how many times the user switched identity and sent. MailJmap.
 * rememberLastUsedIdentity() (called from sendNewEmail() right after a successful send) is the
 * reimplementation - see that method's own docblock.
 *
 * mail/LastSignatureIDUsed is an object keyed by acc_id (classic Compose::setDefaults()'s own
 * `$sigPref[$this->mail_bo->profileID]`), NOT a single scalar - a user with several mail accounts
 * has one independently-remembered identity per account. Every test below exercises that directly:
 * updating one account's entry must never disturb any other account's own, already-stored value.
 */

function createFakeApp(initialPreference : any) : {app : MailApp, setPreferenceCalls : {app : string, name : string, val : any}[]}
{
	const setPreferenceCalls : {app : string, name : string, val : any}[] = [];
	let stored = initialPreference;
	const egw = {
		preference : (name : string, appName? : string) =>
			(name === 'LastSignatureIDUsed' && appName === 'mail') ? stored : null,
		set_preference : (appName : string, name : string, val : any) =>
		{
			setPreferenceCalls.push({app : appName, name, val});
			if (name === 'LastSignatureIDUsed') stored = val;
		},
	};
	const app = {egw} as unknown as MailApp;
	return {app, setPreferenceCalls};
}

describe("MailJmap.rememberLastUsedIdentity()", () =>
{
	it("creates a brand-new preference object when none existed before", () =>
	{
		const {app, setPreferenceCalls} = createFakeApp(null);
		const jmap = new MailJmap(app);

		(jmap as any).rememberLastUsedIdentity('1', '15');

		assert.strictEqual(setPreferenceCalls.length, 1);
		assert.deepEqual(setPreferenceCalls[0], {app : 'mail', name : 'LastSignatureIDUsed', val : {'1' : '15'}});
	});

	it("updates an existing account's entry while preserving every OTHER account's own entry untouched", () =>
	{
		const {app, setPreferenceCalls} = createFakeApp({'1' : '15', '42' : '999', '85' : '3'});
		const jmap = new MailJmap(app);

		(jmap as any).rememberLastUsedIdentity('1', '22');

		assert.deepEqual(setPreferenceCalls[0].val, {'1' : '22', '42' : '999', '85' : '3'},
			"account 1's own entry changed, accounts 42 and 85 must be byte-identical to before");
	});

	it("adds a NEW account's entry alongside already-existing OTHER accounts, without dropping any of them", () =>
	{
		const {app, setPreferenceCalls} = createFakeApp({'42' : '999', '85' : '3'});
		const jmap = new MailJmap(app);

		(jmap as any).rememberLastUsedIdentity('1', '15');

		assert.deepEqual(setPreferenceCalls[0].val, {'42' : '999', '85' : '3', '1' : '15'});
	});

	it("two sequential sends from two different accounts both end up correctly represented in the end", () =>
	{
		const {app, setPreferenceCalls} = createFakeApp(null);
		const jmap = new MailJmap(app);

		(jmap as any).rememberLastUsedIdentity('1', '15');
		(jmap as any).rememberLastUsedIdentity('42', '999');

		assert.strictEqual(setPreferenceCalls.length, 2, "one set_preference call per send, not batched/merged client-side");
		assert.deepEqual(setPreferenceCalls[1].val, {'1' : '15', '42' : '999'},
			"the second call's own read-modify-write must see the first call's already-stored result");
	});

	it("sending again from the SAME account with a DIFFERENT identity overwrites only that account's own value", () =>
	{
		const {app, setPreferenceCalls} = createFakeApp({'1' : '15', '42' : '999'});
		const jmap = new MailJmap(app);

		(jmap as any).rememberLastUsedIdentity('1', '22');
		(jmap as any).rememberLastUsedIdentity('1', '15');

		assert.deepEqual(setPreferenceCalls[1].val, {'1' : '15', '42' : '999'},
			"account 1 flip-flopped back to 15, account 42 was never touched by either call");
	});

	it("re-sending with the SAME account/identity as already stored still calls set_preference (no short-circuit needed here - egw.set_preference() itself already no-ops on an unchanged value)", () =>
	{
		const {app, setPreferenceCalls} = createFakeApp({'1' : '15'});
		const jmap = new MailJmap(app);

		(jmap as any).rememberLastUsedIdentity('1', '15');

		assert.deepEqual(setPreferenceCalls[0].val, {'1' : '15'});
	});
});
