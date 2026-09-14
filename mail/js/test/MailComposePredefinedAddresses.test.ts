import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Regression coverage for a real bug found live 2026-09-14 (ralf, relaying a real user's report,
 * Sebastian): the account's own "predefined compose addresses" preference (an admin/user-configured
 * default Bcc added to every outgoing compose, mail/src/Compose.php's own
 * ajax_getComposeToolbarData()) correctly applied to a genuinely blank new compose, but was shown
 * briefly then cleared for reply/reply-all.
 *
 * Root cause: bootstrapReply()/bootstrapComposeAsNew() both unconditionally clear cc/bcc before
 * repopulating from the source message - a real fix for a real 2026-08-27 bug (a stale value from a
 * PREVIOUS reply/reopened draft otherwise leaking into an unrelated one) - but that also wiped out
 * the predefined-address baseline every time, since it only ever survived in the widget from the
 * server-rendered initial content, never re-applied afterward. Classic mail_compose.inc.php's own
 * compose() merged this preference in unconditionally for every `from=` mode alike, so this is a
 * real behaviour regression, not an intentional scope narrowing.
 *
 * Same "real MailJmap, fake widgets" harness as MailComposeSourceMessageFlags.test.ts's own
 * "MailCompose bootstrap populates sourceMessagesToFlag" describe block (see that file's own
 * docblock) - reused here (not imported, kept self-contained) since quoteOriginalMessage()/
 * composeBodyWithSignature() etc. need to run for real to exercise bootstrapReply()/
 * bootstrapComposeAsNew() end to end.
 */

const PREDEFINED_BCC = ['bcc1@example.org', 'bcc2@example.org'];

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
		from : [{name : 'Sender', email : 'sender@example.com'}],
		to : [{name : 'Me', email : 'me@example.com'}],
		cc : [], bcc : [], replyTo : null,
		subject : 'Original subject', date : '2026-01-01T00:00:00Z',
		mimeType : 'plain', body : 'the original body', profileID : '1',
		inReplyTo : ['msg1@example.com'], references : ['msg1@example.com'],
		attachments : [],
		threadTopic : null, threadIndex : null, listId : null, autocrypt : null,
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

function createComposeForReply(context : JmapReplyContext, identities : JmapIdentity[], preference : (key : string, app? : string) => any)
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

describe('MailCompose applies predefined compose addresses on reply/composeasnew', () =>
{
	function predefinedBccPreference(key : string) : any
	{
		return key === '1_predefined_compose_addresses' ? {bcc : PREDEFINED_BCC} : null;
	}

	it('a plain reply gets the predefined Bcc even though bootstrapReply() clears bcc first', async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()], predefinedBccPreference);
		await (compose as any).bootstrapReply('1::1::INBOX::42', 'reply');

		assert.deepEqual((compose as any).et2.getWidgetById('bcc').getValue(), PREDEFINED_BCC);
	});

	it('reply_all also gets the predefined Bcc', async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()], predefinedBccPreference);
		await (compose as any).bootstrapReply('1::1::INBOX::42', 'reply_all');

		assert.deepEqual((compose as any).et2.getWidgetById('bcc').getValue(), PREDEFINED_BCC);
	});

	it('does not duplicate an already-identical (bare, no display name) address the reply itself already put in cc', async() =>
	{
		// formatJmapAddress() returns the bare email unchanged when the address has no display
		// name - an exact string match against the predefined preference's own bare-email entry
		const context = fakeContext({cc : [{email : 'bcc1@example.org'}]});
		const compose = createComposeForReply(context, [fakeIdentity()], (key) =>
			key === '1_predefined_compose_addresses' ? {cc : ['bcc1@example.org']} : null);
		await (compose as any).bootstrapReply('1::1::INBOX::42', 'reply_all');

		const cc = (compose as any).et2.getWidgetById('cc').getValue();
		assert.deepEqual(cc, ['bcc1@example.org']);
	});

	it('is a no-op when the account has no predefined-address preference set', async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()], () => null);
		await (compose as any).bootstrapReply('1::1::INBOX::42', 'reply');

		assert.deepEqual((compose as any).et2.getWidgetById('bcc').getValue(), []);
	});

	it('bootstrapComposeAsNew() also gets the predefined Bcc merged in', async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()], predefinedBccPreference);
		await (compose as any).bootstrapComposeAsNew('1::1::INBOX::42');

		assert.deepEqual((compose as any).et2.getWidgetById('bcc').getValue(), PREDEFINED_BCC);
	});
});
