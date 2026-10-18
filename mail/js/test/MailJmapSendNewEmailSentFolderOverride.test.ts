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
	{client : any, submitCalls : any[]}
{
	let call = 0;
	const submitCalls : any[] = [];
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
	return {client, submitCalls};
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
		const {client, submitCalls} = createFakeClient(identity, mailboxes);
		primeToken(jmap, "1:15", client, 'Sent Archive');

		await jmap.sendNewEmail("1:15", EMAIL);

		const patch = submitCalls[0].onSuccessUpdateEmail['#sub1'];
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
		const {client, submitCalls} = createFakeClient(identity, mailboxes);
		primeToken(jmap, "1:15", client, null);

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.isTrue(submitCalls[0].onSuccessUpdateEmail['#sub1']['mailboxIds/sent-id']);
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
		const {client, submitCalls} = createFakeClient(identity, mailboxes);
		primeToken(jmap, "1:15", client, 'Deleted Folder');

		await jmap.sendNewEmail("1:15", EMAIL);

		assert.isTrue(submitCalls[0].onSuccessUpdateEmail['#sub1']['mailboxIds/sent-id']);
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
		const {client, submitCalls} = createFakeClient(identity, mailboxes, {Archive : 'archive-id'});
		primeToken(jmap, "1:15", client, null);

		await jmap.sendNewEmail("1:15", {...EMAIL, folder : ['Archive']});

		const patch = submitCalls[0].onSuccessUpdateEmail['#sub1'];
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
		const {client, submitCalls} = createFakeClient(identity, mailboxes, {Gone : undefined as any});
		primeToken(jmap, "1:15", client, null);

		const result = await jmap.sendNewEmail("1:15", {...EMAIL, folder : ['Gone']});

		assert.isOk(result.emailId, "the send itself must still succeed");
		const patch = submitCalls[0].onSuccessUpdateEmail['#sub1'];
		assert.isTrue(patch['mailboxIds/sent-id']);
		assert.isUndefined(Object.keys(patch).find((k) => k === 'mailboxIds/undefined'));
	});
});
