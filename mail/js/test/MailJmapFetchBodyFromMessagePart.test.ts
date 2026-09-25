import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * MailJmap.fetchBodyFromMessagePart() - ticket found live 2026-09-25 (ralf, a real forward-as-
 * attachment .eml that stopped rendering when its attached message finally had real content):
 *
 * 1. A local-shim account (token.isLocal) must skip the raw-dump-as-plain-text shortcut entirely
 *    and fall back to the classic renderer, which already recurses into a message/rfc822 part's
 *    own structure to find ITS text/html body (Api\Mail::getMessageBody()'s 'message'/'rfc822'
 *    case) - unlike a real JMAP-native (Stalwart) row, a local-shim row's classic fallback costs
 *    nothing extra (it's already a real IMAP UID), so there's no reason to ever raw-dump for one.
 * 2. Even for a real JMAP-native account, a downloaded-but-empty part (found live: the local
 *    shim's own BODYSTRUCTURE->JMAP translation mis-reporting a message/rfc822 attachment as
 *    {type: 'application/octet-stream', size: 0}) must fall back rather than silently returning
 *    a "successful" empty body.
 */

const egw = {
	user : (_key : string) => 5,
	lang : (label : string) => String(label),
	link : (path : string, params? : any) => path + '?' + new URLSearchParams(params ?? {}).toString(),
};

function createFakeApp() : MailApp
{
	return {egw} as unknown as MailApp;
}

const ROW_ID = 'mail::5::42::b2xkZXJib3g=::12345';
const PART_ID = '2';

function createJmap(opts : {isLocal : boolean, attachments? : any[], downloadText? : string}) : MailJmap
{
	const app = createFakeApp();
	const jmap = new MailJmap(app);
	(jmap as any).ensureToken = async() => ({accountId : 'account-id', isLocal : opts.isLocal});
	const client = {
		requestMany : async(buildFn : (t : any) => any) =>
		{
			const t = {
				Email : {
					get : (_args : any) => ({list : [{id : '12345', attachments : opts.attachments ?? []}]}),
				},
			};
			return [buildFn(t)];
		},
		downloadBlob : async(_args : any) => ({text : async() => opts.downloadText ?? ''}),
	};
	(jmap as any).clients = {'42' : client};
	return jmap;
}

describe('MailJmap.fetchBodyFromMessagePart()', () =>
{
	it('skips the raw-dump shortcut entirely for a local-shim account, always falling back to classic', async() =>
	{
		const jmap = createJmap({isLocal : true, attachments : [{partId : PART_ID, blobId : 'b:12345:2', type : 'message/rfc822'}]});
		let requestManyCalled = false;
		(jmap as any).clients['42'].requestMany = async() =>
		{
			requestManyCalled = true;
			return [{emails : {list : []}}];
		};

		const result = await (jmap as any).fetchBodyFromMessagePart(ROW_ID, PART_ID);

		assert.deepEqual(result, {special : true});
		assert.isFalse(requestManyCalled, "must never even look up the attachment for a local-shim account");
	});

	it('renders a non-empty part for a real JMAP-native account', async() =>
	{
		const jmap = createJmap({
			isLocal : false,
			attachments : [{partId : PART_ID, blobId : 'b:12345:2', type : 'message/rfc822'}],
			downloadText : 'From: someone@example.com\nSubject: Test\n\nHello',
		});

		const result = await (jmap as any).fetchBodyFromMessagePart(ROW_ID, PART_ID);

		assert.isFalse(result.special);
		assert.include(result.html, 'Hello');
	});

	it('falls back when the downloaded part is empty (the JMAP-shim mis-reported size:0 case)', async() =>
	{
		const jmap = createJmap({
			isLocal : false,
			attachments : [{partId : PART_ID, blobId : 'b:12345:2', type : 'message/rfc822'}],
			downloadText : '',
		});

		const result = await (jmap as any).fetchBodyFromMessagePart(ROW_ID, PART_ID);

		assert.deepEqual(result, {special : true});
	});

	it('falls back when the downloaded part is whitespace-only', async() =>
	{
		const jmap = createJmap({
			isLocal : false,
			attachments : [{partId : PART_ID, blobId : 'b:12345:2', type : 'message/rfc822'}],
			downloadText : '   \n\t  ',
		});

		const result = await (jmap as any).fetchBodyFromMessagePart(ROW_ID, PART_ID);

		assert.deepEqual(result, {special : true});
	});

	it('falls back when no attachment matches the given partID', async() =>
	{
		const jmap = createJmap({isLocal : false, attachments : [{partId : '1', blobId : 'other', type : 'application/pdf'}]});

		const result = await (jmap as any).fetchBodyFromMessagePart(ROW_ID, PART_ID);

		assert.deepEqual(result, {special : true});
	});

	it('falls back when there is no JMAP session for the account', async() =>
	{
		const jmap = createJmap({isLocal : false});
		(jmap as any).ensureToken = async() => null;

		const result = await (jmap as any).fetchBodyFromMessagePart(ROW_ID, PART_ID);

		assert.deepEqual(result, {special : true});
	});
});
