import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #124821 (2026-09-22, a real customer via Ingo): replying from mail_ui::displayMessage()'s
 * "view an attached message" popup silently replied to the OUTER/carrying message instead of the
 * attached one - MailApp.composeMessage()'s own backfill only ever read content.mail_id, never
 * content.part (see mail/js/app.ts). Fixed (ralf's own suggestion) by materializing the attached
 * message as a real Drafts entry first via MailJmap.importAttachedMessageToDrafts(), then replying
 * to THAT through the completely normal, already-well-formatted reply path - rather than teaching
 * every layer of the row-id/reply-addressing scheme about a nested sub-part directly.
 */

const egw = {
	user : (_key : string) => 5,
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

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

const ROW_ID = 'mail::5::42::b2xkZXJib3g=::12345'; // profileID=42, mailboxId=<b64>, emailId=12345
const PART_ID = '2';

interface Captured
{
	emailGetArgs : any;
	mailboxGetArgs : any;
	importArgs : any;
}

/**
 * Sequential call dispatch by order, same convention MailJmapSaveDraft.test.ts's own
 * createFakeClient() already establishes: Email/get (attachments lookup) first, Mailbox/get
 * (drafts lookup) second, Email/import third - importAttachedMessageToDrafts() never fires any of
 * these concurrently.
 */
function createFakeClient(opts : {
	attachments? : any[],
	draftsId? : string | null,
	importResult? : any,
} = {}) : {client : any, captured : Captured}
{
	const captured : Captured = {emailGetArgs : undefined, mailboxGetArgs : undefined, importArgs : undefined};
	let call = 0;
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			call++;
			if (call === 1)
			{
				const t = {
					Email : {
						get : (args : any) =>
						{
							captured.emailGetArgs = args;
							return {list : [{id : args.ids[0], attachments : opts.attachments ?? []}]};
						},
					},
				};
				return [buildFn(t)];
			}
			if (call === 2)
			{
				const t = {
					Mailbox : {
						get : (args : any) =>
						{
							captured.mailboxGetArgs = args;
							const list = opts.draftsId === null ? [] : [{id : opts.draftsId ?? 'drafts-id', role : 'drafts'}];
							return {list};
						},
					},
				};
				return [buildFn(t)];
			}
			const t = {
				Email : {
					import : (args : any) =>
					{
						captured.importArgs = args;
						return opts.importResult ?? {created : {s1 : {id : 'new-draft-email-id'}}, notCreated : {}};
					},
				},
			};
			return [buildFn(t)];
		},
	};
	return {client, captured};
}

function createJmap(clientOpts : Parameters<typeof createFakeClient>[0] = {}) : {jmap : MailJmap, captured : Captured}
{
	const app = createFakeApp();
	const jmap = new MailJmap(app);
	const {client, captured} = createFakeClient(clientOpts);
	(jmap as any).clients = {'42' : client};
	(jmap as any).ensureToken = async() => ({accountId : 'stalwart-account-id', isLocal : false});
	return {jmap, captured};
}

describe("MailJmap.importAttachedMessageToDrafts()", () =>
{
	it("imports the attached message's own blobId into Drafts and returns the new message's row id", async() =>
	{
		const {jmap, captured} = createJmap({
			attachments : [
				{partId : '1', blobId : 'other-blob', type : 'application/pdf'},
				{partId : PART_ID, blobId : 'b2xkZXJib3g=:12345:2', type : 'message/rfc822'},
			],
			draftsId : 'drafts-mailbox-id',
			importResult : {created : {s1 : {id : 'new-draft-email-id'}}, notCreated : {}},
		});

		const result = await jmap.importAttachedMessageToDrafts(ROW_ID, PART_ID);

		assert.equal(result, 'mail::5::42::drafts-mailbox-id::new-draft-email-id');
		assert.deepEqual(captured.importArgs.emails.s1.blobId, 'b2xkZXJib3g=:12345:2',
			"must import the ATTACHED message's own blobId, not the containing message's");
		assert.deepEqual(captured.importArgs.emails.s1.mailboxIds, {'drafts-mailbox-id' : true});
	});

	it("throws when no attachment matches the given partID", async() =>
	{
		const {jmap} = createJmap({
			attachments : [{partId : '1', blobId : 'other-blob', type : 'application/pdf'}],
			draftsId : 'drafts-mailbox-id',
		});

		let threw = false;
		try
		{
			await jmap.importAttachedMessageToDrafts(ROW_ID, PART_ID);
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw, "must throw rather than silently importing the wrong/no content");
	});

	it("throws when the account has no Drafts mailbox", async() =>
	{
		const {jmap} = createJmap({
			attachments : [{partId : PART_ID, blobId : 'b2xkZXJib3g=:12345:2', type : 'message/rfc822'}],
			draftsId : null,
		});

		let threw = false;
		try
		{
			await jmap.importAttachedMessageToDrafts(ROW_ID, PART_ID);
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw, "must throw rather than importing into an unresolvable mailbox");
	});

	it("throws when there's no JMAP session for the account", async() =>
	{
		const app = createFakeApp();
		const jmap = new MailJmap(app);
		(jmap as any).ensureToken = async() => null;

		let threw = false;
		try
		{
			await jmap.importAttachedMessageToDrafts(ROW_ID, PART_ID);
		}
		catch (e)
		{
			threw = true;
		}
		assert.isTrue(threw);
	});
});
