import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import {JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Coverage for MailJmap's attachment upload/download/resolve surface - doc/ai/projects/
 * mail-test-coverage.md's priority-2 entry: uploadAttachment()/uploadVfsAttachment()/
 * downloadBlobUrl()/reuploadAttachmentForAccount()/resolveOutgoingInlineImages()/
 * fetchAttachmentsMetadata()/getAttachmentViewUrl() - previously untested.
 */

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
	link : (_path : string) => "https://example.com",
	open_link : () => {},
	webserverUrl : "/egroupware",
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels : () => ({})} as unknown as MailApp;
}

function primeToken(jmap : MailJmap, profileID : string, client : any, overrides : Record<string, any> = {}) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl : "https://example.com", accountId : "acc1", access_token : "tok",
		expires_at : Date.now() + 100000, isLocal : false, customLabels : {},
		...overrides,
	};
	(jmap as any).clients[profileID] = client;
}

/** Wraps URL.createObjectURL to capture the ACTUAL Blob passed to it (its .type in particular) - restored after each test. */
function captureCreatedObjectUrlBlob() : {get blob() : Blob | undefined, restore : () => void}
{
	const original = URL.createObjectURL;
	let captured : Blob | undefined;
	(URL as any).createObjectURL = (blob : Blob) => { captured = blob; return original.call(URL, blob); };
	return {get blob() { return captured; }, restore : () => { URL.createObjectURL = original; }};
}

describe("MailJmap.uploadAttachment()", () =>
{
	it("uploads the blob as-is when its own type already matches, and returns the JmapAttachment shape", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let uploadedBody : Blob | undefined;
		primeToken(jmap, "1", {
			uploadBlob : async(_accountId : string, body : Blob) => { uploadedBody = body; return {blobId : 'b1', size : 42}; },
		});

		const result = await jmap.uploadAttachment("1", new Blob(["hi"], {type : "text/plain"}), "note.txt", "text/plain");

		assert.equal(uploadedBody!.type, "text/plain");
		assert.deepEqual(result, {blobId : 'b1', name : 'note.txt', type : 'text/plain', size : 42});
	});

	it("re-tags the blob's type when it doesn't match the given type", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let uploadedBody : Blob | undefined;
		primeToken(jmap, "1", {
			uploadBlob : async(_accountId : string, body : Blob) => { uploadedBody = body; return {blobId : 'b1', size : 5}; },
		});

		await jmap.uploadAttachment("1", new Blob(["hi"], {type : "application/octet-stream"}), "note.txt", "text/plain");

		assert.equal(uploadedBody!.type, "text/plain", "the outgoing body must carry the REQUESTED type, not the blob's original one");
	});

	it("falls back to the blob's own size when the server response omits it", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {uploadBlob : async() => ({blobId : 'b1'})});

		const result = await jmap.uploadAttachment("1", new Blob(["12345"], {type : "text/plain"}), "note.txt", "text/plain");

		assert.equal(result.size, 5);
	});

	it("throws 'Account not reachable' when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let error : any = null;
		try
		{
			await jmap.uploadAttachment("1", new Blob(["hi"]), "note.txt", "text/plain");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
		assert.equal(error.message, "Account not reachable");
	});

	it("wraps a server-side upload failure into a JmapUserError", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {uploadBlob : async() => { throw new Error("network error"); }});

		let threw = false;
		try
		{
			await jmap.uploadAttachment("1", new Blob(["hi"]), "note.txt", "text/plain");
		}
		catch (e)
		{
			threw = true;
			assert.instanceOf(e, JmapUserError);
		}
		assert.isTrue(threw);
	});
});

describe("MailJmap.downloadBlobUrl() - the SVG+xml mime-type-enforcement fix", () =>
{
	it("forces the object URL's blob to the REQUESTED mime type, even when the download response reports a different one", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {
			downloadBlob : async() => ({blob : async() => new Blob(["<svg/>"], {type : "image/svg xml"})}),
		});
		const capture = captureCreatedObjectUrlBlob();

		try
		{
			await jmap.downloadBlobUrl("1", "blob1", "icon.svg", "image/svg+xml");
			assert.equal(capture.blob!.type, "image/svg+xml",
				"a mismatched/mangled response Content-Type (eg. the '+' -> space query-string decode bug) must not leak into the object URL's own blob type");
		}
		finally
		{
			capture.restore();
		}
	});

	it("returns a real blob: object URL", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {downloadBlob : async() => ({blob : async() => new Blob(["hi"], {type : "text/plain"})})});

		const url = await jmap.downloadBlobUrl("1", "blob1", "note.txt", "text/plain");

		assert.match(url, /^blob:/);
		URL.revokeObjectURL(url);
	});

	it("throws 'Account not reachable' when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let error : any = null;
		try
		{
			await jmap.downloadBlobUrl("1", "blob1", "note.txt", "text/plain");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});

	/**
	 * Ticket #125092 (2026-09-24): compose's own "view a just-uploaded attachment" path
	 * (compose.ts's displayJmapBlobAttachment()) calls this method directly, never
	 * getAttachmentViewUrl() - it had never received the tracker #124541 fix at all, so a
	 * freshly-uploaded PDF opened with the browser's own native viewer chrome and the blob:
	 * URL's opaque UUID as filename, unlike the same PDF once the message was actually sent.
	 */
	it("names the created object URL's blob as a real File, so a browser save dialog offers the real name", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {downloadBlob : async() => ({blob : async() => new Blob(["hi"], {type : "text/plain"})})});
		const capture = captureCreatedObjectUrlBlob();

		try
		{
			await jmap.downloadBlobUrl("1", "blob1", "note.txt", "text/plain");
			assert.instanceOf(capture.blob, File);
			assert.equal((capture.blob as File).name, "note.txt");
		}
		finally
		{
			capture.restore();
		}
	});

	/**
	 * Ticket #125641: a PDF no longer goes through any client-side fetch/blob/pdf.js render at all -
	 * just the same real, session-authenticated mail/jmap.php?download=1 URL fetchSmimeBlob() (and
	 * getAttachmentViewUrl()'s own 'smime:' branch) already build. The browser's own native viewer
	 * renders it directly - a real Content-Disposition filename, fillable forms, working print -
	 * none of which the old pdf.js-rendered-to-<img> wrapper could ever provide.
	 */
	it("returns a real mail/jmap.php?download=1 URL for a PDF, without ever priming a token/fetching anything", async() =>
	{
		const jmap = new MailJmap(createFakeApp());

		const url = await jmap.downloadBlobUrl("1", "blob1", "Invoice RE-2026-200.pdf", "application/pdf");

		assert.equal(url, "/egroupware/mail/jmap.php?download=1&accountId=1&blobId=blob1&type=application%2Fpdf&name=Invoice+RE-2026-200.pdf");
	});

	it("does NOT wrap a non-PDF type - still returns the plain named-File content url directly", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {downloadBlob : async() => ({blob : async() => new Blob(["hi"], {type : "text/plain"})})});

		const url = await jmap.downloadBlobUrl("1", "blob1", "note.txt", "text/plain");

		assert.match(url, /^blob:/);
	});
});

describe("MailJmap.reuploadAttachmentForAccount()", () =>
{
	it("downloads from the SOURCE account and uploads to the TARGET account, returning the target's new blobId", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let downloadedFrom : string | null = null;
		let uploadedTo : string | null = null;
		primeToken(jmap, "source", {
			downloadBlob : async(args : any) => { downloadedFrom = args.accountId; return {blob : async() => new Blob(["hi"], {type : "text/plain"})}; },
		});
		primeToken(jmap, "target", {
			uploadBlob : async(accountId : string) => { uploadedTo = accountId; return {blobId : 'new-blob-id', size : 2}; },
		});
		(jmap as any).tokens["source"].accountId = "source-acc";
		(jmap as any).tokens["target"].accountId = "target-acc";

		const result = await jmap.reuploadAttachmentForAccount("source", "old-blob-id", "note.txt", "text/plain", "target");

		assert.equal(downloadedFrom, "source-acc");
		assert.equal(uploadedTo, "target-acc");
		assert.equal(result.blobId, "new-blob-id", "must return the TARGET account's own new blobId, not the source one");
	});

	it("throws 'Account not reachable' when the source account has no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let error : any = null;
		try
		{
			await jmap.reuploadAttachmentForAccount("source", "old-blob-id", "note.txt", "text/plain", "target");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});
});

describe("MailJmap.isLocalAccount()", () =>
{
	it("reflects the primed token's own isLocal flag", async() =>
	{
		const jmapLocal = new MailJmap(createFakeApp());
		primeToken(jmapLocal, "1", {}, {isLocal : true});
		assert.isTrue(await jmapLocal.isLocalAccount("1"));

		const jmapReal = new MailJmap(createFakeApp());
		primeToken(jmapReal, "1", {}, {isLocal : false});
		assert.isFalse(await jmapReal.isLocalAccount("1"));
	});

	it("is false when there is no usable token at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.isFalse(await jmap.isLocalAccount("1"));
	});
});

describe("MailJmap.fetchAttachmentsMetadata()", () =>
{
	function primeWithAttachments(jmap : MailJmap, profileID : string, attachments : any[] | undefined) : void
	{
		primeToken(jmap, profileID, {
			requestMany : async(buildFn : (t : any) => any) =>
			{
				const t = {Email : {get : (_args : any) => null}};
				buildFn(t);
				return [{emails : {list : attachments === undefined ? [] : [{attachments}]}}];
			},
		});
	}

	it("returns the attachments array for the message", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithAttachments(jmap, "1", [{blobId : 'b1', name : 'a.pdf'}]);

		const result = await jmap.fetchAttachmentsMetadata("1::1::INBOX::42");

		assert.deepEqual(result, [{blobId : 'b1', name : 'a.pdf'}]);
	});

	it("returns an empty array (not null) when the message resolves but has no attachments property", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeWithAttachments(jmap, "1", undefined);

		assert.deepEqual(await jmap.fetchAttachmentsMetadata("1::1::INBOX::42"), []);
	});

	it("returns null (not throw) when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.isNull(await jmap.fetchAttachmentsMetadata("1::1::INBOX::42"));
	});

	it("returns null on a genuine fetch failure, for the caller's server-side fallback", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {requestMany : async() => { throw new Error("network error"); }});

		assert.isNull(await jmap.fetchAttachmentsMetadata("1::1::INBOX::42"));
	});
});

describe("MailJmap.getAttachmentViewUrl() / revokeAttachmentViewUrls()", () =>
{
	it("enforces the requested mime type on the created object URL's blob, same as downloadBlobUrl()", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {downloadBlob : async() => ({blob : async() => new Blob(["<svg/>"], {type : "image/svg xml"})})});
		const capture = captureCreatedObjectUrlBlob();

		try
		{
			await jmap.getAttachmentViewUrl("row1", "1", "blob1", "icon.svg", "image/svg+xml");
			assert.equal(capture.blob!.type, "image/svg+xml");
		}
		finally
		{
			capture.restore();
		}
	});

	it("names the created object URL's blob as a real File (non-PDF types), so a browser save dialog offers the real name", async() =>
	{
		// ralf, 2026-09-15: opening an attachment then saving from the browser's native viewer
		// (not our own "Download" action, which already worked - it sets an <a download>
		// attribute explicitly) offered the blob: URL's own opaque UUID as the suggested
		// filename instead - a plain Blob carries no name at all, only a File does. PDF never
		// reaches this function at all any more (ticket #125641) - it keeps a real,
		// session-authenticated server URL instead of a blob: one, see
		// MailJmap.downloadBlobUrl()'s own tests for that URL's shape.
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {downloadBlob : async() => ({blob : async() => new Blob(["\x89PNG"], {type : "image/png"})})});
		const capture = captureCreatedObjectUrlBlob();

		try
		{
			await jmap.getAttachmentViewUrl("row1", "1", "blob1", "photo.png", "image/png");
			assert.instanceOf(capture.blob, File);
			assert.equal((capture.blob as File).name, "photo.png");
		}
		finally
		{
			capture.restore();
		}
	});

	it("tracks every created url under its rowId, and revokeAttachmentViewUrls() clears them all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeToken(jmap, "1", {downloadBlob : async() => ({blob : async() => new Blob(["hi"], {type : "text/plain"})})});

		await jmap.getAttachmentViewUrl("row1", "1", "blob1", "a.txt", "text/plain");
		await jmap.getAttachmentViewUrl("row1", "1", "blob2", "b.txt", "text/plain");

		assert.equal((jmap as any).attachmentViewUrls["row1"].length, 2);

		jmap.revokeAttachmentViewUrls("row1");

		assert.isUndefined((jmap as any).attachmentViewUrls["row1"]);
	});

	it("revoking an unknown/never-tracked rowId is a harmless no-op", () =>
	{
		const jmap = new MailJmap(createFakeApp());
		assert.doesNotThrow(() => jmap.revokeAttachmentViewUrls("never-seen-row"));
	});

	it("throws when there is no usable token", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let error : any = null;
		try
		{
			await jmap.getAttachmentViewUrl("row1", "1", "blob1", "a.txt", "text/plain");
		}
		catch (e)
		{
			error = e;
		}
		assert.instanceOf(error, JmapUserError);
	});
});

describe("MailJmap.resolveOutgoingInlineImages()", () =>
{
	function primeUploadClient(jmap : MailJmap, uploadResult : (blob : Blob) => any = () => ({blobId : 'new-blob', size : 3}))
	{
		return {uploadBlob : async(_accountId : string, blob : Blob) => uploadResult(blob)};
	}

	it("returns the body unchanged, with no upload attempted, when there are no blob: src URLs at all", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client : any = primeUploadClient(jmap);
		const token : any = {accountId : 'acc1'};

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, '<p>Hello</p>', true);

		assert.equal(result.body, '<p>Hello</p>');
		assert.deepEqual(result.inlineImages, []);
	});

	it("uploads a known inline blob: image, rewrites its src to cid:, and returns the JmapInlineImage entry", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).inlineImageBlobs.set('blob:http://x/1', new Blob(['img'], {type : 'image/png'}));
		const client : any = primeUploadClient(jmap);
		const token : any = {accountId : 'acc1'};

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, '<img src="blob:http://x/1">', true);

		assert.notInclude(result.body, 'blob:http://x/1');
		assert.match(result.body, /src="cid:[^"]+"/);
		assert.equal(result.inlineImages.length, 1);
		assert.equal(result.inlineImages[0].blobId, 'new-blob');
		assert.equal(result.inlineImages[0].type, 'image/png');
	});

	it("leaves a blob: src untouched when this instance has no matching Blob for it (nothing else could plausibly put one there)", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client : any = primeUploadClient(jmap);
		const token : any = {accountId : 'acc1'};

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, '<img src="blob:http://x/unknown">', true);

		assert.equal(result.body, '<img src="blob:http://x/unknown">');
		assert.deepEqual(result.inlineImages, []);
	});

	it("reuses an already-uploaded image's cid instead of uploading it again", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let uploadCount = 0;
		(jmap as any).inlineImageBlobs.set('blob:http://x/1', new Blob(['img'], {type : 'image/png'}));
		(jmap as any).inlineImageUploads.set('blob:http://x/1', {blobId : 'cached-blob', type : 'image/png', name : 'x.png', size : 3, cid : 'cached-cid@host'});
		const client : any = {uploadBlob : async() => { uploadCount++; return {blobId : 'should-not-be-used'}; }};
		const token : any = {accountId : 'acc1'};

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, '<img src="blob:http://x/1">', true);

		assert.equal(uploadCount, 0, "an already-cached upload must never be re-uploaded");
		assert.include(result.body, 'cid:cached-cid@host');
	});

	it("the SAME url appearing more than once in the body is only uploaded once", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).inlineImageBlobs.set('blob:http://x/1', new Blob(['img'], {type : 'image/png'}));
		let uploadCount = 0;
		const client : any = {uploadBlob : async() => { uploadCount++; return {blobId : 'new-blob', size : 3}; }};
		const token : any = {accountId : 'acc1'};

		await (jmap as any).resolveOutgoingInlineImages(token, client,
			'<img src="blob:http://x/1"><img src="blob:http://x/1">', true);

		assert.equal(uploadCount, 1);
	});

	it("an upload failure for one image leaves its src unresolved, without throwing or dropping other images", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		(jmap as any).inlineImageBlobs.set('blob:http://x/bad', new Blob(['img'], {type : 'image/png'}));
		(jmap as any).inlineImageBlobs.set('blob:http://x/good', new Blob(['img'], {type : 'image/png'}));
		const client : any = {
			uploadBlob : async(_accountId : string, blob : Blob) =>
			{
				if (blob === (jmap as any).inlineImageBlobs.get('blob:http://x/bad')) throw new Error('upload failed');
				return {blobId : 'good-blob', size : 3};
			},
		};
		const token : any = {accountId : 'acc1'};

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client,
			'<img src="blob:http://x/bad"><img src="blob:http://x/good">', true);

		assert.include(result.body, 'blob:http://x/bad', "the failed one keeps its original src");
		assert.include(result.body, 'cid:', "the other image still resolves normally");
		assert.equal(result.inlineImages.length, 1);
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	/**
	 * Ticket #125961: a `webdav.php`-referenced src must never be auto-embedded any more - such
	 * a URL only ever works for whoever has this exact account's own session, which is exactly
	 * why an auto-embedded one used to break the moment the message was forwarded. Compose no
	 * longer produces one at all (paste/drop/"insert from VFS" all insert a `data:` URI directly
	 * instead, Mail\Compose::ajax_uploadInlineImage()/ajax_resizeVfsImageForCompose()), so one
	 * reaching this far - quoted or not - is leftover/foreign content, left as a plain,
	 * unconverted src.
	 */
	it("leaves a webdav.php src completely untouched, never fetches it, quoted or not", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client : any = primeUploadClient(jmap);
		const token : any = {accountId : 'acc1'};
		const fetchStub = sinon.stub(globalThis, 'fetch');

		const body = '<p>my reply</p><img src="https://example.com/egroupware/webdav.php/home/me/signature.png">' +
			'<blockquote type="cite"><img src="https://example.com/egroupware/webdav.php/home/victim/secret.xlsx"></blockquote>';
		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, body, true);

		assert.isTrue(fetchStub.notCalled, "a webdav.php src must never be fetched any more, quoted or not");
		assert.equal(result.body, body, "the body must be returned completely unchanged");
		assert.deepEqual(result.inlineImages, []);
	});

	it("uploads a base64 data: image, rewrites to cid:, deriving the type from the data URI itself", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client : any = primeUploadClient(jmap);
		const token : any = {accountId : 'acc1'};
		// 1x1 transparent PNG
		const dataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, `<img src="${dataUri}">`, true);

		assert.notInclude(result.body, 'data:image');
		assert.match(result.body, /src="cid:[^"]+"/);
		assert.equal(result.inlineImages.length, 1);
		assert.equal(result.inlineImages[0].type, 'image/png');
	});

	it("GHSA-j936-xcgg-f6vj follow-up: a plain-text compose (isHtml=false) never fetches ANY src, even one that's literally webdav.php-shaped text carried in verbatim from a quoted plain-text original", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		const client : any = primeUploadClient(jmap);
		const token : any = {accountId : 'acc1'};
		const fetchStub = sinon.stub(globalThis, 'fetch');
		// quoteOriginalMessage()'s plain-plain branch quotes the ORIGINAL plain-text body verbatim,
		// '>' prefixed - if that original literally contained this exact text (attacker-crafted,
		// not real markup), it must stay inert in a plain-text reply/draft
		const body = '> <img src="https://example.com/egroupware/webdav.php/home/victim/secret.xlsx">';

		const result = await (jmap as any).resolveOutgoingInlineImages(token, client, body, false);

		assert.isTrue(fetchStub.notCalled, "plain-text mode must never even attempt a fetch");
		assert.equal(result.body, body, "the body must be returned completely unchanged");
		assert.deepEqual(result.inlineImages, []);
	});
});
