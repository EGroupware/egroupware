import {assert} from "@open-wc/testing";
import {MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Tickets #128171 (and others, multiple independently-reported Stalwart-migrated instances),
 * 2026-11-23/24: sending intermittently failed with Stalwart's own "One or more blobIds do not
 * exist on this server." - traced to resolveOutgoingInlineImages()'s `inlineImageUploads` cache
 * (jmap.ts) outliving a single compose session (it's keyed by the body's literal `src` string and
 * lives as long as this MailJmap instance does, effectively the whole browser tab). An HTML
 * signature with an embedded logo re-inserts the SAME `src` on every brand-new compose, so after
 * the first upload every later send just replayed that one cached blobId forever - until
 * Stalwart's own blob-store GC reclaimed it, at which point every subsequent send using that
 * signature started failing (plain-text compose skips resolveOutgoingInlineImages() entirely,
 * matching the reported workaround).
 *
 * Covers the fix: createDraftEmail() now detects this specific SetError (isBlobNotFoundProblem(),
 * matched on description text since Stalwart gives it no dedicated JMAP-spec type), evicts the
 * stale cache entry by forcing a fresh re-upload, and retries the Email/set create exactly once -
 * same "detect, self-heal, retry once" shape as sendNewEmail()'s own overQuota handling.
 */

const IDENTITY = {id : '15', email : 'me@example.org', name : 'Me'};
const BLOB_NOT_FOUND = {type : 'invalidProperties', description : 'One or more blobIds do not exist on this server.'};

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

function createJmap() : MailJmap
{
	return new MailJmap(createFakeApp());
}

function emailWithInlineImage() : any
{
	return {
		to : ["recipient@example.org"], subject : "Hello", isHtml : true,
		body : '<p>Hi</p><img src="blob:signature-logo">',
	};
}

describe("MailJmap.createDraftEmail()'s blobId-not-found self-heal", () =>
{
	it("evicts the stale cached inline-image blobId, re-uploads fresh, and retries the create exactly once", async() =>
	{
		const jmap = createJmap();
		const fakeBlob = new Blob(['logo bytes'], {type : 'image/png'});
		(jmap as any).inlineImageBlobs.set('blob:signature-logo', fakeBlob);
		// pre-seed the cache exactly like a PRIOR compose session would have left it - the bug's
		// own trigger condition, not something this test's own upload call created
		(jmap as any).inlineImageUploads.set('blob:signature-logo',
			{blobId : 'stale-blob-id', type : 'image/png', name : 'inline-image-1.png', size : 10, cid : 'old@host'});

		const uploadBlobCalls : any[] = [];
		const client : any = {
			uploadBlob : async(accountId : string, blob : Blob) =>
			{
				uploadBlobCalls.push({accountId, blob});
				return {blobId : 'fresh-blob-id', size : 10};
			},
		};
		let setCall = 0;
		const createArgsByAttempt : any[] = [];
		client.requestMany = async(buildFn : (t : any) => any) =>
		{
			setCall++;
			return [buildFn({
				Email : {
					set : (args : any) =>
					{
						createArgsByAttempt.push(args);
						return setCall === 1 ?
							{created : {}, notCreated : {s1 : BLOB_NOT_FOUND}} :
							{created : {s1 : {id : 'draft-id'}}, notCreated : {}};
					},
				},
			})];
		};

		const token = {accountId : 'acc1'};
		const result = await (jmap as any).createDraftEmail(token, client, IDENTITY, 'drafts-id', emailWithInlineImage());

		assert.strictEqual(result, 'draft-id', "must succeed after the self-heal retry");
		assert.strictEqual(setCall, 2, "exactly one retry - not an unbounded loop");
		assert.strictEqual(uploadBlobCalls.length, 1,
			"the first attempt must have used the cache (no upload); only the forced retry re-uploads");

		const firstBodyStructure = createArgsByAttempt[0].create.s1.bodyStructure;
		const firstInlinePart = firstBodyStructure.subParts.find((p : any) => p.disposition === 'inline');
		assert.strictEqual(firstInlinePart.blobId, 'stale-blob-id', "first attempt must have used the (stale) cached blobId");

		const retryBodyStructure = createArgsByAttempt[1].create.s1.bodyStructure;
		const retryInlinePart = retryBodyStructure.subParts.find((p : any) => p.disposition === 'inline');
		assert.strictEqual(retryInlinePart.blobId, 'fresh-blob-id', "retry must use the freshly re-uploaded blobId");

		assert.strictEqual((jmap as any).inlineImageUploads.get('blob:signature-logo').blobId, 'fresh-blob-id',
			"the cache itself must be updated, so the NEXT send in this tab doesn't hit the same stale id again");
	});

	it("gives up after exactly one retry and throws when the blobId-not-found error persists", async() =>
	{
		const jmap = createJmap();
		(jmap as any).inlineImageBlobs.set('blob:signature-logo', new Blob(['logo bytes'], {type : 'image/png'}));
		(jmap as any).inlineImageUploads.set('blob:signature-logo',
			{blobId : 'stale-blob-id', type : 'image/png', name : 'inline-image-1.png', size : 10, cid : 'old@host'});

		let uploadCount = 0;
		let setCall = 0;
		const client : any = {
			uploadBlob : async() => { uploadCount++; return {blobId : `fresh-${uploadCount}`, size : 10}; },
			requestMany : async(buildFn : (t : any) => any) =>
			{
				setCall++;
				return [buildFn({Email : {set : () => ({created : {}, notCreated : {s1 : BLOB_NOT_FOUND}})}})];
			},
		};

		let threw = false;
		try
		{
			await (jmap as any).createDraftEmail({accountId : 'acc1'}, client, IDENTITY, 'drafts-id', emailWithInlineImage());
		}
		catch (e)
		{
			threw = true;
		}

		assert.isTrue(threw, "must still fail once the self-heal retry also hits the same error");
		assert.strictEqual(setCall, 2, "exactly one retry, no unbounded loop");
		assert.strictEqual(uploadCount, 1, "only the one retry re-uploads, never more");
	});

	it("does not retry for any other notCreated error (no stale blobId cache involved)", async() =>
	{
		const jmap = createJmap();
		let setCall = 0;
		let uploadCount = 0;
		const client : any = {
			uploadBlob : async() => { uploadCount++; return {blobId : 'irrelevant', size : 0}; },
			requestMany : async(buildFn : (t : any) => any) =>
			{
				setCall++;
				return [buildFn({Email : {set : () => ({created : {}, notCreated : {s1 : {type : 'invalidProperties', description : 'Missing subject'}}})}})];
			},
		};

		let threw = false;
		try
		{
			await (jmap as any).createDraftEmail({accountId : 'acc1'}, client, IDENTITY, 'drafts-id',
				{to : ["recipient@example.org"], subject : "", isHtml : false, body : "Hi"});
		}
		catch (e)
		{
			threw = true;
		}

		assert.isTrue(threw);
		assert.strictEqual(setCall, 1, "no retry for an unrelated invalidProperties error");
		assert.strictEqual(uploadCount, 0);
	});
});
