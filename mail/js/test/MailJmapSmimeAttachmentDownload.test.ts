import {assert} from "@open-wc/testing";
import {JmapUserError, MailJmap} from "../jmap";
import type {MailApp} from "../app";

/**
 * Ticket #124661 (2026-09-22): an S/MIME-encrypted message's decrypted attachments now carry an
 * 'smime:' blobId (JmapShim::smimeAttachments(), PHP side) instead of the normal mailbox/uid or
 * Stalwart-native one. client.downloadBlob() can never resolve that scheme for a REAL Stalwart
 * account - it resolves against THAT session's own advertised downloadUrl, which points directly
 * at Stalwart's own blob endpoint, with no notion of our custom scheme. downloadAttachment()/
 * getAttachmentViewUrl() must instead route an 'smime:' blobId straight at our own
 * mail/jmap.php?download endpoint (fetchSmimeBlob()) - verified here at the fetch() level, same
 * approach as MailJmap.test.ts's "local shim uses a cacheable GET" describe block.
 */

/** One real, minimal, parseable PDF (ticket #125641: wrapPdfViewerWithDownload() now actually parses the bytes via pdfjs-dist) - see MailJmapAttachmentUploadResolve.test.ts's own identical constant for the full reasoning. */
const MINIMAL_VALID_PDF =
	"%PDF-1.4\n" +
	"1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" +
	"2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" +
	"3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n" +
	"4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n" +
	"5 0 obj\n<< /Length 44 >>\nstream\nBT /F1 24 Tf 20 100 Td (Test PDF) Tj ET\nendstream\nendobj\n" +
	"trailer\n<< /Size 6 /Root 1 0 R >>\n%%EOF";

const egw = {
	user: (_key : string) => 1,
	lang: (label : string) => label,
	preference: (_key : string, _app? : string) => null,
	config: (_name : string, _app? : string) => null,
	request: async() => ({}),
	message: (_msg : string, _type? : string) => {},
	webserverUrl: "/egroupware",
};

function createFakeApp() : MailApp
{
	return {egw, getCustomLabels: () => ({})} as unknown as MailApp;
}

/**
 * A client whose downloadBlob() fails the test if ever called - the whole point of the 'smime:'
 * bypass is that it must never be reached for that blobId.
 */
function primeTokenWithNoBlobDownloadClient(jmap : MailJmap, profileID : string) : void
{
	(jmap as any).tokens[profileID] = {
		sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
		expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
	};
	(jmap as any).clients[profileID] = {
		downloadBlob: async() => { throw new Error("must not be called for an smime: blobId"); },
	};
}

describe("MailJmap - 'smime:' blobId bypasses client.downloadBlob() (ticket #124661)", () =>
{
	const originalFetch = globalThis.fetch;

	afterEach(() =>
	{
		globalThis.fetch = originalFetch;
	});

	it("getAttachmentViewUrl() GETs mail/jmap.php?download directly, never client.downloadBlob()", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeTokenWithNoBlobDownloadClient(jmap, "1");

		let capturedUrl : string | undefined;
		let capturedInit : any;
		globalThis.fetch = (async(url : any, init? : any) =>
		{
			// a PDF attachment also triggers wrapPdfViewerWithDownload()'s own real pdfjs-dist
			// worker fetch (ticket #125641) - passed through to the REAL fetch() here (a genuine
			// network request for the real node_modules/pdfjs-dist/build/pdf.worker.mjs file,
			// same as every other PDF-wrapper test in MailJmapAttachmentUploadResolve.test.ts),
			// since this test's own concern is only the smime: blobId's OWN download URL below.
			if (String(url).includes("pdf.worker"))
			{
				return originalFetch(url, init);
			}
			capturedUrl = String(url);
			capturedInit = init;
			return {ok: true, blob: async() => new Blob([MINIMAL_VALID_PDF], {type: "application/pdf"})};
		}) as any;

		const blobId = "smime:cm93SWQ:dG9wTGV2ZWxUeXBl:ZnJvbUFkZHJlc3M:2";
		await jmap.getAttachmentViewUrl("row1", "1", blobId, "invoice.pdf", "application/pdf");

		assert.isTrue(capturedUrl?.startsWith("/egroupware/mail/jmap.php?"),
			"must GET our own mail/jmap.php, not a Stalwart-provided downloadUrl template");
		const params = new URLSearchParams(capturedUrl!.split("?")[1]);
		assert.strictEqual(params.get("download"), "1");
		assert.strictEqual(params.get("blobId"), blobId);
		assert.strictEqual(params.get("type"), "application/pdf");
		assert.strictEqual(params.get("name"), "invoice.pdf");
		assert.strictEqual(capturedInit.credentials, "same-origin");
	});

	it("downloadAttachment() also routes an smime: blobId to the direct URL, not client.downloadBlob()", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		primeTokenWithNoBlobDownloadClient(jmap, "1");

		let capturedUrl : string | undefined;
		// deliberately fail the fetch so downloadAttachment() throws before ever reaching its
		// DOM <a download> click step - this test only needs to prove the URL/dispatch, not
		// exercise a real browser download side effect.
		globalThis.fetch = (async(url : any) =>
		{
			capturedUrl = String(url);
			return {ok: false, status: 404, statusText: "Not Found"};
		}) as any;

		let error : any = null;
		try
		{
			await jmap.downloadAttachment("1", "smime:cm93SWQ:dG9wTGV2ZWxUeXBl:ZnJvbUFkZHJlc3M:2", "invoice.pdf", "application/pdf");
		}
		catch (e)
		{
			error = e;
		}

		assert.instanceOf(error, JmapUserError, "a failed download must surface as a JmapUserError, same as any other blobId scheme");
		assert.isTrue(capturedUrl?.includes("blobId=smime%3A"), "must have attempted the smime: direct URL");
	});

	it("a non-smime blobId is unaffected - still uses client.downloadBlob()", async() =>
	{
		const jmap = new MailJmap(createFakeApp());
		let downloadBlobCalled = false;
		(jmap as any).tokens["1"] = {
			sessionUrl: "https://example.com", accountId: "acc1", access_token: "tok",
			expires_at: Date.now() + 100000, isLocal: false, customLabels: {},
		};
		(jmap as any).clients["1"] = {
			downloadBlob: async() => { downloadBlobCalled = true; return {ok: true, blob: async() => new Blob(["hi"], {type: "text/plain"})}; },
		};
		globalThis.fetch = (async() =>
		{
			throw new Error("must not be called for a normal blobId");
		}) as any;

		await jmap.getAttachmentViewUrl("row1", "1", "TUJPWA:1:2", "a.txt", "text/plain");

		assert.isTrue(downloadBlobCalled);
	});
});
