import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Regression tests (help forum: "sent mail going to Send folder instead of the defined folder"):
 *
 * 1. MailJmap.resolveComposeContext() used to always pick the Sent mailbox by JMAP role='sent'
 *    unconditionally, silently ignoring the account's own optional acc_folder_sent override
 *    (exposed to the client as JmapToken.sentFolder, see ProfileHandler::jmapBootstrap()).
 * 2. MailCompose's own optional "folder" picker (compose.xet) - additional mailbox(es) to also
 *    file the sent copy into - was never read into currentEmailFields()/JmapNewEmail at all, so
 *    sendNewEmail() had no way to know it existed.
 *
 * Same "sequential requestMany() calls, counted by call order" harness as
 * MailJmapSendNewEmailRemembersIdentity.test.ts's own createFakeClient(), extended with an extra
 * call for each additional folder path resolveComposeContext()'s sibling mailboxId() resolves
 * (one Mailbox.query per path segment - these test folders are single-segment, so one call each).
 */

const EMAIL = {to : ["recipient@example.org"], subject : "Hello", body : "Hi there", isHtml : false};

function createFakeApp() : MailApp
{
	const egw = {
		user : (_key : string) => 1,
		lang : (label : string, ...args : string[]) =>
		{
			let i = 0;
			return String(label).replace(/%(\d+)/g, () => args[i++] ?? '');
		},
		preference : (_key : string, _app? : string) => null,
		config : (_name : string, _app? : string) => null,
		request : async() => ({}),
		message : (_msg : string, _type? : string) => {},
	};
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

/**
 * @param mailboxes Mailbox.get's own list (drafts/sent/any extra by-name candidates)
 * @param folderQueryResults keyed by the Mailbox.query 'name' filter mailboxId() looks up, for
 *  each extra folder path under test - {name: mailboxId}
 */
function createFakeClient(identity : {id : string, email : string}, mailboxes : any[], folderQueryResults : Record<string, string> = {}) :
	{client : any, submitCalls : any[], updateCalls : any[]}
{
	let call = 0;
	const submitCalls : any[] = [];
	const updateCalls : any[] = [];
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			call++;
			if (call === 1)
			{
				const t = {
					Mailbox : {get : (_args : any) => ({list : mailboxes})},
					Identity : {get : (_args : any) => ({list : [{id : 'stalwart-opaque-id', email : identity.email}]})},
				};
				return [buildFn(t)];
			}
			if (call === 2)
			{
				const t = {Email : {set : (_args : any) => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}};
				return [buildFn(t)];
			}
			// mailboxId()'s own per-path-segment Mailbox.query resolution for email.folder, if any
			const name = Object.keys(folderQueryResults)[call - 3];
			if (name !== undefined)
			{
				const t = {Mailbox : {query : (args : any) => ({ids : args.filter?.name === name ? [folderQueryResults[name]] : []})}};
				return [buildFn(t)];
			}
			if (submitCalls.length === 0)
			{
				const t = {
					EmailSubmission : {
						set : (args : any) =>
						{
							submitCalls.push(args);
							return {created : {sub1 : {id : 'sub-id', blobId : 'blob-id'}}, notCreated : {}};
						},
					},
				};
				return [buildFn(t)];
			}
			// sendNewEmail()'s own separate, explicit, notUpdated-checked follow-up Email/set call
			// that files the Drafts->Sent move (no longer riding along as EmailSubmission/set's
			// onSuccessUpdateEmail for a real-JMAP/non-local account - see that method's own
			// docblock on why)
			const t = {
				Email : {
					set : (args : any) =>
					{
						updateCalls.push(args);
						return {updated : {'draft-id' : {}}, notUpdated : {}};
					},
				},
			};
			return [buildFn(t)];
		},
	};
	return {client, submitCalls, updateCalls};
}

function primeToken(jmap : MailJmap, profileID : string, client : any, sentFolder? : string | null) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {}, sentFolder,
	};
	(jmap as any).clients[profileID] = client;
}

describe("MailJmap.sendNewEmail() honours the account's configured Sent-folder override", () =>
{
	it("files the sent copy into the configured folder (by name) instead of the role='sent' mailbox", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		const mailboxes = [
			{id : 'drafts-id', role : 'drafts', name : 'Drafts'},
			{id : 'sent-id', role : 'sent', name : 'Sent'},
			{id : 'custom-sent-id', role : null, name : 'Sent Archive'},
		];
		const {client, updateCalls} = createFakeClient(identity, mailboxes);
		primeToken(jmap, "1:15", client, 'Sent Archive');

		await jmap.sendNewEmail("1:15", EMAIL);

		const patch = updateCalls[0].update['draft-id'];
		assert.isTrue(patch['mailboxIds/custom-sent-id']);
		assert.isUndefined(patch['mailboxIds/sent-id']);
	});

	it("falls back to role='sent' when no override is configured", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		const mailboxes = [
			{id : 'drafts-id', role : 'drafts', name : 'Drafts'},
			{id : 'sent-id', role : 'sent', name : 'Sent'},
		];
		const {client, updateCalls} = createFakeClient(identity, mailboxes);
		primeToken(jmap, "1:15", client, null);

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.isTrue(updateCalls[0].update['draft-id']['mailboxIds/sent-id']);
	});

	it("falls back to role='sent' when the configured folder no longer exists", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		const mailboxes = [
			{id : 'drafts-id', role : 'drafts', name : 'Drafts'},
			{id : 'sent-id', role : 'sent', name : 'Sent'},
		];
		const {client, updateCalls} = createFakeClient(identity, mailboxes);
		primeToken(jmap, "1:15", client, 'Deleted Folder');

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.isTrue(updateCalls[0].update['draft-id']['mailboxIds/sent-id']);
	});

	it("prefers the TOP-LEVEL mailbox when the configured name also matches a nested, unrelated personal folder (Ingo, acc_id=18: 'privat/Sent' vs the real top-level 'Sent')", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		const mailboxes = [
			{id : 'drafts-id', role : 'drafts', name : 'Drafts', parentId : null},
			// the account's real Sent mailbox - top-level, role=sent, name happens to be the bare
			// configured override value
			{id : 'real-sent-id', role : 'sent', name : 'Sent', parentId : null},
			{id : 'privat-id', role : null, name : 'privat', parentId : null},
			// Ingo's own personal subfolder, unrelated to the account's Sent folder, which just
			// happens to ALSO be named "Sent" - found live to wrongly win a bare name-only match
			{id : 'privat-sent-id', role : null, name : 'Sent', parentId : 'privat-id'},
		];
		// list order deliberately puts the nested, WRONG candidate first - a plain Array.find()
		// would pick it, same as the live bug did
		const {client, updateCalls} = createFakeClient(identity, [mailboxes[0], mailboxes[3], mailboxes[1], mailboxes[2]]);
		primeToken(jmap, "1:15", client, 'Sent');

		await jmap.sendNewEmail("1:15", EMAIL);

		const patch = updateCalls[0].update['draft-id'];
		assert.isTrue(patch['mailboxIds/real-sent-id'], "must file into the real, top-level Sent mailbox");
		assert.isUndefined(patch['mailboxIds/privat-sent-id'], "must NOT file into the unrelated nested 'privat/Sent' folder");
	});
});

describe("MailJmap.sendNewEmail() files the sent copy into compose's optional additional folder(s) too", () =>
{
	it("adds the resolved mailboxId to the submission patch, alongside Sent", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		const mailboxes = [
			{id : 'drafts-id', role : 'drafts', name : 'Drafts'},
			{id : 'sent-id', role : 'sent', name : 'Sent'},
		];
		const {client, updateCalls} = createFakeClient(identity, mailboxes, {Archive : 'archive-id'});
		primeToken(jmap, "1:15", client, null);

		await jmap.sendNewEmail("1:15", {...EMAIL, folder : ['Archive']});

		const patch = updateCalls[0].update['draft-id'];
		assert.isTrue(patch['mailboxIds/sent-id']);
		assert.isTrue(patch['mailboxIds/archive-id']);
	});

	it("skips a folder path that no longer resolves instead of failing the whole send", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		const mailboxes = [
			{id : 'drafts-id', role : 'drafts', name : 'Drafts'},
			{id : 'sent-id', role : 'sent', name : 'Sent'},
		];
		// 'Gone' resolves to no ids at all (ids: []) - mailboxId() throws, sendNewEmail() must
		// swallow it and keep going rather than letting the whole send fail over one stale folder
		const {client, updateCalls} = createFakeClient(identity, mailboxes, {Gone : undefined as any});
		primeToken(jmap, "1:15", client, null);

		const result = await jmap.sendNewEmail("1:15", {...EMAIL, folder : ['Gone']});

		assert.isOk(result.emailId, "the send itself must still succeed");
		const patch = updateCalls[0].update['draft-id'];
		assert.isTrue(patch['mailboxIds/sent-id']);
		assert.isUndefined(Object.keys(patch).find((k) => k === 'mailboxIds/undefined'));
	});
});

/**
 * Regression tests (help forum/Ingo, acc_id shared with ralf - "sent mail does NOT end up in his
 * Sent folder", worked for ralf, not for Ingo on the SAME account config): the Drafts->Sent move
 * used to ride along as EmailSubmission/set's own "onSuccessUpdateEmail" implicit follow-up call -
 * jmap-jam's requestMany() only returns data for the invocations the caller explicitly drafted, so
 * that implicit call's own result (including any notUpdated) was silently discarded. If Stalwart
 * ever rejected it, the message still sent fine but the Sent-copy filing failed with ZERO visible
 * error. sendNewEmail() now issues it as its own separate, explicit, named Email/set call instead
 * (real-JMAP/non-local accounts only - see below for why the IMAP-shim backend is deliberately
 * untouched) and surfaces a warning instead of silently losing the copy.
 */
describe("MailJmap.sendNewEmail() surfaces a failed Sent-folder copy instead of silently dropping it", () =>
{
	const mailboxes = [
		{id : 'drafts-id', role : 'drafts', name : 'Drafts'},
		{id : 'sent-id', role : 'sent', name : 'Sent'},
	];

	it("warns (does not throw) and reports the Drafts mailbox when the server rejects the mailboxIds patch", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const app = createFakeApp();
		const messages : {msg : string, type? : string}[] = [];
		(app as any).egw.message = (msg : string, type? : string) => messages.push({msg, type});
		const jmap = new MailJmap(app);
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1)
				{
					const t = {
						Mailbox : {get : () => ({list : mailboxes})},
						Identity : {get : () => ({list : [{id : 'stalwart-opaque-id', email : identity.email}]})},
					};
					return [buildFn(t)];
				}
				if (call === 2)
				{
					const t = {Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}};
					return [buildFn(t)];
				}
				if (call === 3)
				{
					const t = {EmailSubmission : {set : () => ({created : {sub1 : {id : 'sub-id', blobId : 'blob-id'}}, notCreated : {}})}};
					return [buildFn(t)];
				}
				// the follow-up Email/set call - server rejects the mailboxIds patch
				const t = {Email : {set : () => ({updated : {}, notUpdated : {'draft-id' : {type : 'forbidden'}}})}};
				return [buildFn(t)];
			},
		};
		primeToken(jmap, "1:15", client, null);

		const result = await jmap.sendNewEmail("1:15", EMAIL);

		assert.strictEqual(result.mailboxId, 'drafts-id', "message is still wherever createDraftEmail() put it, NOT the (never actually reached) Sent folder");
		assert.strictEqual(messages.length, 1);
		assert.include(messages[0].msg, 'Sent folder');
		assert.strictEqual(messages[0].type, 'warning');
	});

	it("warns (does not throw) when the follow-up call itself fails outright (network/HTTP error)", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const app = createFakeApp();
		const messages : {msg : string, type? : string}[] = [];
		(app as any).egw.message = (msg : string, type? : string) => messages.push({msg, type});
		const jmap = new MailJmap(app);
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		let call = 0;
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				call++;
				if (call === 1)
				{
					const t = {
						Mailbox : {get : () => ({list : mailboxes})},
						Identity : {get : () => ({list : [{id : 'stalwart-opaque-id', email : identity.email}]})},
					};
					return [buildFn(t)];
				}
				if (call === 2)
				{
					const t = {Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}};
					return [buildFn(t)];
				}
				if (call === 3)
				{
					const t = {EmailSubmission : {set : () => ({created : {sub1 : {id : 'sub-id', blobId : 'blob-id'}}, notCreated : {}})}};
					return [buildFn(t)];
				}
				throw new Error("network error");
			},
		};
		primeToken(jmap, "1:15", client, null);

		const result = await jmap.sendNewEmail("1:15", EMAIL);

		assert.isOk(result.emailId, "the send itself must still be reported as successful");
		assert.strictEqual(result.mailboxId, 'drafts-id');
		assert.strictEqual(messages.length, 1);
		assert.include(messages[0].msg, 'Sent folder');
		assert.strictEqual(messages[0].type, 'warning');
	});

	it("does NOT issue a separate follow-up call for an IMAP-shim (local) account - onSuccessUpdateEmail stays embedded", async() =>
	{
		const identity = {id : '15', email : 'me@example.org'};
		const app = createFakeApp();
		const messages : any[] = [];
		(app as any).egw.message = (msg : string, type? : string) => messages.push({msg, type});
		const jmap = new MailJmap(app);
		(jmap as any).getIdentities = async() => [identity];
		(jmap as any).rememberLastUsedIdentity = () => {};
		let callCount = 0;
		const submitCalls : any[] = [];
		const client = {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				callCount++;
				if (callCount === 1)
				{
					const t = {
						Mailbox : {get : () => ({list : mailboxes})},
						Identity : {get : () => ({list : [{id : 'stalwart-opaque-id', email : identity.email}]})},
					};
					return [buildFn(t)];
				}
				if (callCount === 2)
				{
					const t = {Email : {set : () => ({created : {s1 : {id : 'draft-id'}}, notCreated : {}})}};
					return [buildFn(t)];
				}
				const t = {
					EmailSubmission : {
						set : (args : any) =>
						{
							submitCalls.push(args);
							return {created : {sub1 : {id : 'sub-id', blobId : 'blob-id'}}, notCreated : {}};
						},
					},
				};
				return [buildFn(t)];
			},
		};
		(jmap as any).tokens["1:15"] = {
			sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
			expires_at : Date.now() + 100000, isLocal : true, customLabels : {}, sentFolder : null,
		};
		(jmap as any).clients["1:15"] = client;

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.strictEqual(callCount, 3, "no extra follow-up call for a local/shim account");
		assert.isTrue(submitCalls[0].onSuccessUpdateEmail['#sub1']['mailboxIds/sent-id']);
		assert.strictEqual(messages.length, 0, "nothing to warn about - the shim's own emailSubmissionSet() reports its own failures separately");
	});
});
