import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";
import {MailJmap} from "../jmap";

/**
 * Ticket #126161: a PDF unpacked from a winmail.dat opened in the browsers own PDF viewer, while every other PDF of
 * a mail is shown in the pdf.js viewer MailJmap wraps it in. That one needs a JMAP blobId to fetch the PDF, which a file
 * inside of a winmail.dat does not have - its bytes come from the classic getAttachment URL instead, which the download
 * already uses, and are wrapped by the same MailJmap.wrapPdfViewerWithDownload().
 */
describe("MailApp.resolveAttachmentViewUrls() - PDF unpacked from a winmail.dat", () =>
{
	const rowId = 'mail::5::42::SU5CT1gvRHJhZnRz::30125';
	let fetchStub : sinon.SinonStub;
	let wrapStub : sinon.SinonStub;
	let getAttachmentViewUrl : sinon.SinonStub;
	let revokeObjectURL : sinon.SinonStub;
	let app : any;

	const unpackedPdf = () => ({filename : 'Rechnung 174977.pdf', type : 'application/pdf', mail_id : rowId, partID : '2',
		winmailFlag : '30125@2@1', blobId : null, mime_url : 'classic-url'});

	beforeEach(() =>
	{
		// a fresh Response for every call, a body can only be read once
		fetchStub = sinon.stub(window, 'fetch').callsFake(() =>
			Promise.resolve(new Response(new Blob(['%PDF-1.4 test'], {type : 'application/pdf'}), {status : 200})));
		wrapStub = sinon.stub(<any>MailJmap, 'wrapPdfViewerWithDownload').resolves('blob:viewer-page');
		revokeObjectURL = sinon.stub(URL, 'revokeObjectURL');
		getAttachmentViewUrl = sinon.stub().resolves('blob:jmap-viewer');
		app = Object.create(MailApp.prototype);
		Object.assign(app, {egw : {webserverUrl : '/egroupware'}, tnefViewUrls : {}});
		// jmap is a getter of MailApp
		Object.defineProperty(app, 'jmap', {value : {
			messageReference : () => ({profileID : '42'}), revokeAttachmentViewUrls : sinon.spy(), getAttachmentViewUrl,
		}});
	});

	afterEach(() => sinon.restore());

	it("shows it in the pdf.js viewer, with the bytes of the classic getAttachment URL", async() =>
	{
		const item = unpackedPdf();

		const changed = await app.resolveAttachmentViewUrls(rowId, [item]);

		assert.isTrue(changed);
		assert.equal(item.mime_url, 'blob:viewer-page');
		const url = fetchStub.firstCall.args[0] as string;
		assert.include(url, '/egroupware/index.php?');
		assert.include(url, 'Ui.getAttachment');
		assert.include(decodeURIComponent(url), 'is_winmail=30125@2@1', 'the fingerprint of the unpacked file');
		assert.deepEqual(fetchStub.firstCall.args[1], {credentials : 'same-origin'});
		// wrapped with the real filename, type and a blob: URL of the content
		const [blob, contentUrl, filename, type] = wrapStub.firstCall.args;
		assert.instanceOf(blob, Blob);
		assert.match(contentUrl, /^blob:/);
		assert.equal(filename, 'Rechnung 174977.pdf');
		assert.equal(type, 'application/pdf');
		assert.isFalse(getAttachmentViewUrl.called, 'no JMAP blob to ask for');
	});

	it("keeps the classic URL (the browsers viewer) if fetching fails, and says so", async() =>
	{
		fetchStub.resolves(new Response('nope', {status : 404, statusText : 'Not Found'}));
		const consoleError = sinon.stub(console, 'error');
		const item = unpackedPdf();

		const changed = await app.resolveAttachmentViewUrls(rowId, [item]);

		assert.isFalse(changed);
		assert.equal(item.mime_url, 'classic-url');
		assert.isFalse(wrapStub.called);
		assert.isTrue(consoleError.called);
	});

	it("leaves other unpacked files and PDFs of a JMAP blob alone, those use their own path", async() =>
	{
		const doc = {filename : 'Brief.doc', type : 'application/msword', mail_id : rowId, partID : '2', winmailFlag : '30125@2@2', blobId : null, mime_url : 'classic-doc'};
		const jmapPdf = {filename : 'other.pdf', type : 'application/pdf', mail_id : rowId, partID : '3', blobId : 'blob-9', mime_url : 'classic-other'};

		const changed = await app.resolveAttachmentViewUrls(rowId, [doc, jmapPdf]);

		assert.isTrue(changed);
		assert.equal(doc.mime_url, 'classic-doc');
		assert.equal(jmapPdf.mime_url, 'blob:jmap-viewer');
		assert.isFalse(fetchStub.called, 'only the unpacked PDF is fetched');
		assert.isTrue(getAttachmentViewUrl.calledOnceWith(rowId, '42', 'blob-9', 'other.pdf', 'application/pdf'));
	});

	it("revokes the object URLs of the last resolve of the same row, before building new ones", async() =>
	{
		await app.resolveAttachmentViewUrls(rowId, [unpackedPdf()]);
		const first = [...app.tnefViewUrls[rowId]];
		assert.lengthOf(first, 2, 'content and viewer URL');

		await app.resolveAttachmentViewUrls(rowId, [unpackedPdf()]);

		first.forEach(url => assert.isTrue(revokeObjectURL.calledWith(url), 'revoked ' + url));
		assert.lengthOf(app.tnefViewUrls[rowId], 2, 'only the new ones are kept');
	});

	it("does nothing for a message without a PDF to view", async() =>
	{
		const changed = await app.resolveAttachmentViewUrls(rowId, [{filename : 'a.doc', type : 'application/msword', blobId : 'b'}]);

		assert.isFalse(changed);
		assert.isFalse(fetchStub.called);
	});
});
