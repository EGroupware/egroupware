import {assert} from "@open-wc/testing";
import {MailCompose} from "../compose";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";
import type {JmapIdentity, JmapReplyContext} from "../jmap";

/**
 * Regression coverage for the answered/forwarded status-icon regression found live 2026-09-09
 * (ralf, relaying a tester report: "the icon showing an email was replied-to/forwarded is no
 * longer shown ... in old mails it's still shown, only for newly answered ones"). Root cause:
 * MailJmap.sendNewEmail() only ever creates+submits the NEW message - unlike the retired classic
 * Send::send() (mail/src/Send.php), it never touches the message being replied to/forwarded, so
 * nothing ever marked it $answered/$forwarded again once compose's send path went client-side-
 * JMAP. See MailCompose.sourceMessagesToFlag's own docblock for the full picture.
 *
 * These tests exercise:
 * - MailCompose.flagSourceMessagesAfterSend() directly (trySendViaJmap() itself has too many
 *   unrelated preconditions - S/MIME toolbar widgets, mailvelope, currentEmailFields() - to drive
 *   in a focused test, see that method's own docblock), with a minimal fully-fake app/jmap.
 * - bootstrapReply()/mergeForwardAttachments() actually populating sourceMessagesToFlag, using a
 *   real MailJmap with only fetchForReply()/getIdentities()/fetchForForwardAsAttachment()
 *   monkey-patched, same established pattern as MailComposeBootstrapRace.test.ts.
 *
 * The shim-side counterpart (Api\Mail\Jmap\Imap::writableKeywords() now allowing Email/set to
 * actually persist $answered/$forwarded, previously rejected) is covered separately in
 * mail/tests/JmapTest.php - PHP-side keyword-mapping/dispatch logic has no meaningful client-side
 * equivalent to test here.
 */

const egw : any = {
	lang : (label : string, ...args : string[]) =>
	{
		let i = 0;
		return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
	},
	preference : (_key : string, _app? : string) => null,
	message : (_msg : string, _type? : string) => {},
};

/** rowId shape MailJmap.messageReference() parses: accountId::profileID::mailboxId::emailId. */
const SOURCE_ROW_ID = '1::1::INBOX::42';
const OTHER_SOURCE_ROW_ID = '1::1::INBOX::43';

describe("MailCompose.flagSourceMessagesAfterSend()", () =>
{
	function createFakeApp(dataStore : Record<string, any> = {}) :
		{app : MailApp, setSystemFlagCalls : any[], patchRowCalls : string[]}
	{
		const setSystemFlagCalls : any[] = [];
		const patchRowCalls : string[] = [];
		const jmap = {
			// real parsing logic (MailJmap.messageReference() is pure, no I/O) - reimplemented
			// here rather than constructing a real MailJmap purely to keep this fake app minimal;
			// kept byte-identical to the real method.
			messageReference : (rowId : string) =>
			{
				let parts = String(rowId || '').split('::');
				if (parts[0] === 'mail') parts = parts.slice(1);
				if (parts.length !== 4 || !parts[1] || !parts[2] || !parts[3])
				{
					throw new Error(`Invalid Mail row id '${rowId}'`);
				}
				return {profileID : parts[1], mailboxId : parts[2], emailId : parts[3]};
			},
			setSystemFlag : async(references : any[], keyword : string, set : boolean) =>
			{
				setSystemFlagCalls.push({references, keyword, set});
			},
		};
		const app = {
			egw : {...egw, dataGetUIDdata : (uid : string) => dataStore[uid]},
			jmap,
			patchRow : (uid : string) => { patchRowCalls.push(uid); },
		} as unknown as MailApp;
		return {app, setSystemFlagCalls, patchRowCalls};
	}

	it("is a no-op when sourceMessagesToFlag is unset (a genuinely new, non-reply/forward message)", async() =>
	{
		const {app, setSystemFlagCalls, patchRowCalls} = createFakeApp();
		const compose = new MailCompose(app);

		await (compose as any).flagSourceMessagesAfterSend();

		assert.strictEqual(setSystemFlagCalls.length, 0);
		assert.strictEqual(patchRowCalls.length, 0);
	});

	it("marks a reply's source $answered only - not $forwarded", async() =>
	{
		const dataStore : Record<string, any> = {[SOURCE_ROW_ID] : {data : {flags : {}, class : 'mail unseen'}}};
		const {app, setSystemFlagCalls, patchRowCalls} = createFakeApp(dataStore);
		const compose = new MailCompose(app);
		(compose as any).sourceMessagesToFlag = {rowIds : [SOURCE_ROW_ID], forwarded : false};

		await (compose as any).flagSourceMessagesAfterSend();

		assert.deepEqual(setSystemFlagCalls.map((c) => c.keyword), ['$answered'],
			"only $answered must be set for a plain reply");
		assert.isTrue(setSystemFlagCalls[0].set);
		assert.deepEqual(setSystemFlagCalls[0].references, [{profileID : '1', mailboxId : 'INBOX', emailId : '42'}]);

		const row = dataStore[SOURCE_ROW_ID].data;
		assert.strictEqual(row.flags.replied, 'replied');
		assert.isUndefined(row.flags.forwarded, "a reply must never set the forwarded flag");
		assert.include(row.class.split(' '), 'replied');
		assert.notInclude(row.class.split(' '), 'forwarded');
		assert.deepEqual(patchRowCalls, [SOURCE_ROW_ID]);
	});

	it("marks a forward's source BOTH $answered and $forwarded (matches classic Send::send()'s own fallthrough behaviour)", async() =>
	{
		const dataStore : Record<string, any> = {[SOURCE_ROW_ID] : {data : {flags : {}, class : 'mail unseen'}}};
		const {app, setSystemFlagCalls, patchRowCalls} = createFakeApp(dataStore);
		const compose = new MailCompose(app);
		(compose as any).sourceMessagesToFlag = {rowIds : [SOURCE_ROW_ID], forwarded : true};

		await (compose as any).flagSourceMessagesAfterSend();

		assert.deepEqual(setSystemFlagCalls.map((c) => c.keyword), ['$answered', '$forwarded']);
		assert.isTrue(setSystemFlagCalls.every((c) => c.set === true));

		const row = dataStore[SOURCE_ROW_ID].data;
		assert.strictEqual(row.flags.replied, 'replied');
		assert.strictEqual(row.flags.forwarded, 'forwarded');
		assert.include(row.class.split(' '), 'replied');
		assert.include(row.class.split(' '), 'forwarded');
		assert.deepEqual(patchRowCalls, [SOURCE_ROW_ID]);
	});

	it("flags every accumulated source (forwardasattach can carry more than one) and preserves existing classes/flags", async() =>
	{
		const dataStore : Record<string, any> = {
			[SOURCE_ROW_ID] : {data : {flags : {flagged : 'flagged'}, class : 'mail flagged'}},
			[OTHER_SOURCE_ROW_ID] : {data : {flags : {}, class : 'mail unseen'}},
		};
		const {app, setSystemFlagCalls, patchRowCalls} = createFakeApp(dataStore);
		const compose = new MailCompose(app);
		(compose as any).sourceMessagesToFlag = {rowIds : [SOURCE_ROW_ID, OTHER_SOURCE_ROW_ID], forwarded : true};

		await (compose as any).flagSourceMessagesAfterSend();

		// one setSystemFlag call per keyword, covering BOTH references at once - not one call per row
		assert.strictEqual(setSystemFlagCalls.length, 2);
		assert.strictEqual(setSystemFlagCalls[0].references.length, 2);

		assert.strictEqual(dataStore[SOURCE_ROW_ID].data.flags.flagged, 'flagged', "pre-existing flags must survive untouched");
		assert.include(dataStore[SOURCE_ROW_ID].data.class.split(' '), 'flagged');
		assert.include(dataStore[SOURCE_ROW_ID].data.class.split(' '), 'forwarded');
		assert.strictEqual(dataStore[OTHER_SOURCE_ROW_ID].data.flags.replied, 'replied');
		assert.deepEqual(patchRowCalls, [SOURCE_ROW_ID, OTHER_SOURCE_ROW_ID]);
	});

	it("skips a row that isn't currently loaded in egw's data cache, without throwing", async() =>
	{
		const {app, setSystemFlagCalls, patchRowCalls} = createFakeApp({});
		const compose = new MailCompose(app);
		(compose as any).sourceMessagesToFlag = {rowIds : [SOURCE_ROW_ID], forwarded : false};

		await (compose as any).flagSourceMessagesAfterSend();

		assert.strictEqual(setSystemFlagCalls.length, 1, "the server-side flag call must still happen");
		assert.strictEqual(patchRowCalls.length, 0, "nothing to patch client-side for an unloaded row");
	});

	it("swallows a setSystemFlag() failure - never reported/thrown as a send failure", async() =>
	{
		const dataStore : Record<string, any> = {[SOURCE_ROW_ID] : {data : {flags : {}, class : 'mail unseen'}}};
		const {app} = createFakeApp(dataStore);
		(app as any).jmap.setSystemFlag = async() => { throw new Error('server unreachable'); };
		const compose = new MailCompose(app);
		(compose as any).sourceMessagesToFlag = {rowIds : [SOURCE_ROW_ID], forwarded : false};

		let threw = false;
		try
		{
			await (compose as any).flagSourceMessagesAfterSend();
		}
		catch (e)
		{
			threw = true;
		}

		assert.isFalse(threw, "a failure here must never surface as a send failure - the mail already went out");
	});
});

/**
 * bootstrapReply()/mergeForwardAttachments() actually populating sourceMessagesToFlag - same
 * "real MailJmap, fake widgets" harness as MailComposeBootstrapRace.test.ts (see that file's own
 * docblock), reused here since quoteOriginalMessage()/composeBodyWithSignature() etc. need to run
 * for real, not be reimplemented in yet another fake.
 */
describe("MailCompose bootstrap populates sourceMessagesToFlag", () =>
{
	function createFakeApp() : MailApp
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
			threadTopic : null, threadIndex : null, listId : null,
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

	function createComposeForReply(context : JmapReplyContext, identities : JmapIdentity[])
	{
		const app = createFakeApp();
		const jmap = new MailJmap(app);
		(jmap as any).fetchForReply = async() => context;
		(jmap as any).getIdentities = async() => identities;
		(app as any).jmap = jmap;

		const compose = new MailCompose(app);
		(compose as any).isJmapMode = true;
		(compose as any).et2 = createFakeEt2('1:0');
		return compose;
	}

	it("a plain reply records forwarded:false against the reply's own rowId", async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()]);
		await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'reply');
		assert.deepEqual((compose as any).sourceMessagesToFlag, {rowIds : [SOURCE_ROW_ID], forwarded : false});
	});

	it("reply_all also records forwarded:false", async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()]);
		await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'reply_all');
		assert.deepEqual((compose as any).sourceMessagesToFlag, {rowIds : [SOURCE_ROW_ID], forwarded : false});
	});

	it("reply_attachments also records forwarded:false (it's a reply variant, not a forward)", async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()]);
		await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'reply_attachments');
		assert.deepEqual((compose as any).sourceMessagesToFlag, {rowIds : [SOURCE_ROW_ID], forwarded : false});
	});

	it("an inline forward records forwarded:true", async() =>
	{
		const compose = createComposeForReply(fakeContext(), [fakeIdentity()]);
		await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'forward');
		assert.deepEqual((compose as any).sourceMessagesToFlag, {rowIds : [SOURCE_ROW_ID], forwarded : true});
	});

	describe("mergeForwardAttachments() accumulates sourceMessagesToFlag", () =>
	{
		function createComposeForForwardAsAttachment(results : Record<string, any>)
		{
			const app = createFakeApp();
			const jmap = new MailJmap(app);
			(jmap as any).fetchForForwardAsAttachment = async(id : string) => results[id] ?? null;
			(app as any).jmap = jmap;

			const compose = new MailCompose(app);
			const contentMgr : any = {data : {}, getEntry : (_key : string) => undefined};
			(compose as any).et2 = {
				// mergeAttachmentEntries()'s own needs (carryForwardAttachments() ->
				// mergeAttachmentEntries(), called unconditionally by mergeForwardAttachments())
				getArrayMgr : (_name : string) => contentMgr,
				setArrayMgr : (_name : string, mgr : any) => { Object.assign(contentMgr, mgr); },
				getWidgetById : (id : string) => id === 'attachments' ?
					{set_value : () => {}, getParent : () => undefined} : undefined,
			};
			return compose;
		}

		it("a single forward-as-attachment source is recorded with forwarded:true", async() =>
		{
			const compose = createComposeForForwardAsAttachment({
				[SOURCE_ROW_ID] : {blobId : 'b1', subject : 'Msg 1', sourceRowId : SOURCE_ROW_ID, profileID : '1'},
			});

			await compose.mergeForwardAttachments([SOURCE_ROW_ID]);

			assert.deepEqual((compose as any).sourceMessagesToFlag, {rowIds : [SOURCE_ROW_ID], forwarded : true});
		});

		it("a second call into an already-open compose UNIONS in the new source(s), never dropping earlier ones", async() =>
		{
			const compose = createComposeForForwardAsAttachment({
				[SOURCE_ROW_ID] : {blobId : 'b1', subject : 'Msg 1', sourceRowId : SOURCE_ROW_ID, profileID : '1'},
				[OTHER_SOURCE_ROW_ID] : {blobId : 'b2', subject : 'Msg 2', sourceRowId : OTHER_SOURCE_ROW_ID, profileID : '1'},
			});

			await compose.mergeForwardAttachments([SOURCE_ROW_ID]);
			await compose.mergeForwardAttachments([OTHER_SOURCE_ROW_ID]);

			assert.deepEqual((compose as any).sourceMessagesToFlag,
				{rowIds : [SOURCE_ROW_ID, OTHER_SOURCE_ROW_ID], forwarded : true});
		});

		it("re-merging the SAME source id again does not duplicate it", async() =>
		{
			const compose = createComposeForForwardAsAttachment({
				[SOURCE_ROW_ID] : {blobId : 'b1', subject : 'Msg 1', sourceRowId : SOURCE_ROW_ID, profileID : '1'},
			});

			await compose.mergeForwardAttachments([SOURCE_ROW_ID]);
			await compose.mergeForwardAttachments([SOURCE_ROW_ID]);

			assert.deepEqual((compose as any).sourceMessagesToFlag, {rowIds : [SOURCE_ROW_ID], forwarded : true});
		});
	});

	/**
	 * Regression coverage (2026-09-09, ralf: "Does that mean they [Thread-Topic/Thread-Index/
	 * List-Id] are lost when replying to a mail? ... it would be a real regression we need to
	 * fix") - classic getReplyData()'s propagation of these three headers from the original
	 * message onto a reply (2014 commit 2172fc769d) had no JMAP-native equivalent at all;
	 * bootstrapReply() now captures them into replyThreadingHeaders alongside inReplyTo/
	 * references, reply-only (null for a forward), same convention as those two.
	 */
	describe("bootstrapReply() populates replyThreadingHeaders' threadTopic/threadIndex/listId", () =>
	{
		it("a plain reply captures all three from the original message", async() =>
		{
			const compose = createComposeForReply(fakeContext({
				threadTopic : "Original subject", threadIndex : "AQHTest==", listId : "mylist.example.org",
			}), [fakeIdentity()]);

			await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'reply');

			assert.deepEqual((compose as any).replyThreadingHeaders, {
				inReplyTo : ['msg1@example.com'], references : ['msg1@example.com'],
				threadTopic : "Original subject", threadIndex : "AQHTest==", listId : "mylist.example.org",
			});
		});

		it("stays null (the whole replyThreadingHeaders object) for an inline forward - a forward starts a new thread", async() =>
		{
			const compose = createComposeForReply(fakeContext({
				threadTopic : "Original subject", threadIndex : "AQHTest==", listId : "mylist.example.org",
			}), [fakeIdentity()]);

			await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'forward');

			assert.isNull((compose as any).replyThreadingHeaders);
		});

		it("stays null per-field when the original message had none of these headers", async() =>
		{
			const compose = createComposeForReply(fakeContext(), [fakeIdentity()]);

			await (compose as any).bootstrapReply(SOURCE_ROW_ID, 'reply');

			assert.isNull((compose as any).replyThreadingHeaders.threadTopic);
			assert.isNull((compose as any).replyThreadingHeaders.threadIndex);
			assert.isNull((compose as any).replyThreadingHeaders.listId);
		});
	});
});
