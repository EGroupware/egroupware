import {assert} from "@open-wc/testing";
import {renderAttachmentIndex} from "../attachmentIndex";

/**
 * Test renderAttachmentIndex() - the "auto-index" that shows attachments directly in the body
 * area when a message has no visible text/html body (eg. a photo emailed with no comment),
 * instead of leaving it blank. Images are shown inline, PDFs embedded, everything else as a
 * clickable mime-icon that downloads/opens on click.
 *
 * Pure DOM manipulation against a real Document (no iframe needed for the test itself) plus a
 * minimal egw stub - no database/session/network involved.
 */

const egw = {
	link: (_path : string, params : Record<string, any>) => "https://example.com/index.php?" +
		new URLSearchParams(params).toString(),
	lang: (label : string) => label,
	image: (name : string) => name === "mime128_unknown" ? "https://example.com/unknown.svg" : null,
	open_link: () => {},
};

function emptyBodyDocument(bodyHtml : string = '<div dir="auto"></div>') : Document
{
	const doc = document.implementation.createHTMLDocument("");
	doc.body.innerHTML = bodyHtml;
	return doc;
}

describe("renderAttachmentIndex()", () =>
{
	it("does nothing when there are no attachments", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [], egw);

		assert.isNull(doc.body.querySelector(".mail_attachmentIndex"));
	});

	it("does nothing when the body is not actually empty", () =>
	{
		const doc = emptyBodyDocument("<p>Hello there</p>");
		renderAttachmentIndex(doc, [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		assert.isNull(doc.body.querySelector(".mail_attachmentIndex"),
			"a non-empty body must never get the auto-index appended");
	});

	it("treats whitespace-only markup (eg. an empty <div>) as an empty body", () =>
	{
		// exactly the real-world shape that triggered this feature: Gmail mobile's
		// <div dir="auto"></div> placeholder, with no actual visible text
		const doc = emptyBodyDocument('<div dir="auto">   </div>');
		renderAttachmentIndex(doc, [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		assert.isNotNull(doc.body.querySelector(".mail_attachmentIndex"));
	});

	it("renders a single image inline with no header, since it already fully represents the content", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		assert.isNull(doc.body.querySelector(".mail_attachmentIndexHeader"),
			"a single attachment needs no '---- filename ----' divider");
		const img = doc.body.querySelector(".mail_attachmentIndexItem img") as HTMLImageElement;
		assert.isNotNull(img);
		assert.equal(img.src, "https://example.com/photo.jpg");
		assert.equal(img.loading, "lazy");
	});

	it("adds a '---- filename ----' header per item and a download-all link when there are multiple attachments", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "a.jpg", type: "image/jpeg", mime_url: "https://example.com/a.jpg", mail_id: "mail::1::INBOX::1"},
			{filename: "b.jpg", type: "image/jpeg", mime_url: "https://example.com/b.jpg", mail_id: "mail::1::INBOX::1"},
		], egw);

		const headers = doc.body.querySelectorAll(".mail_attachmentIndexHeader");
		assert.equal(headers.length, 2);
		assert.equal(headers[0].textContent, "---- a.jpg ----");
		assert.equal(headers[1].textContent, "---- b.jpg ----");
		assert.isNotNull(doc.body.querySelector(".mail_attachmentIndexDownloadAll"),
			"multiple attachments should offer a single 'download all' action");
	});

	it("embeds a PDF in an iframe", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "invoice.pdf", type: "application/pdf", mime_url: "https://example.com/invoice.pdf"},
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		const iframe = doc.body.querySelector(".mail_attachmentIndexPdf") as HTMLIFrameElement;
		assert.isNotNull(iframe);
		assert.equal(iframe.src, "https://example.com/invoice.pdf");
	});

	it("shows a non-inlinable attachment (eg. a .docx) as a mime-icon that downloads on click, not inline content", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "report.docx", type: "application/msword", mime_url: "https://example.com/report.docx"},
		], egw);

		assert.isNull(doc.body.querySelector("img[loading='lazy']"),
			"a non-image/pdf attachment must not be rendered as an inline <img>/<iframe>");
		const link = doc.body.querySelector(".mail_attachmentIndexIcon") as HTMLAnchorElement;
		assert.isNotNull(link);
		assert.equal(link.href, "https://example.com/report.docx");
		assert.include(link.textContent, "report.docx");
	});

	it("falls back to the generic 'unknown' mime-icon when nothing else has a click-to-download URL", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "blob.bin", type: "application/octet-stream", mime_data: "sometoken"},
		], egw);

		const icon = doc.body.querySelector(".mail_attachmentIndexIcon img") as HTMLImageElement;
		assert.isNotNull(icon);
		assert.equal(icon.src, "https://example.com/unknown.svg");
	});

	it("is idempotent - calling it again does not duplicate the index", () =>
	{
		const doc = emptyBodyDocument();
		const attachmentsBlock = [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		];
		renderAttachmentIndex(doc, attachmentsBlock, egw);
		renderAttachmentIndex(doc, attachmentsBlock, egw);

		assert.equal(doc.body.querySelectorAll(".mail_attachmentIndex").length, 1);
	});

	it("sets width/height/alt on an inlined image", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		const img = doc.body.querySelector(".mail_attachmentIndexItem img") as HTMLImageElement;
		assert.equal(img.getAttribute("width"), "100%");
		assert.equal(img.getAttribute("height"), "auto");
		assert.equal(img.alt, "photo.jpg");
	});

	it("sets a title on the embedded PDF iframe", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "invoice.pdf", type: "application/pdf", mime_url: "https://example.com/invoice.pdf"},
		], egw);

		const iframe = doc.body.querySelector(".mail_attachmentIndexPdf") as HTMLIFrameElement;
		assert.equal(iframe.title, "invoice.pdf");
	});

	it("sets a title on a non-inlinable attachment's download link", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "report.docx", type: "application/msword", mime_url: "https://example.com/report.docx"},
		], egw);

		const link = doc.body.querySelector(".mail_attachmentIndexIcon") as HTMLAnchorElement;
		assert.equal(link.title, "report.docx");
	});

	it("builds the download-all link's href from the first attachment's mail_id via the existing zip-download action", () =>
	{
		const doc = emptyBodyDocument();
		renderAttachmentIndex(doc, [
			{filename: "a.jpg", type: "image/jpeg", mime_url: "https://example.com/a.jpg", mail_id: "mail::1::INBOX::42"},
			{filename: "b.jpg", type: "image/jpeg", mime_url: "https://example.com/b.jpg", mail_id: "mail::1::INBOX::42"},
		], egw);

		const downloadAll = doc.body.querySelector(".mail_attachmentIndexDownloadAll") as HTMLAnchorElement;
		const url = new URL(downloadAll.href);
		assert.equal(url.searchParams.get("menuaction"), "mail.EGroupware\\Mail\\Ui.download_zip");
		assert.equal(url.searchParams.get("id"), "mail::1::INBOX::42");
	});
});

/**
 * The DOM-insertion-target fallback chain (attachmentIndex.ts's own docblock at lines 106-113):
 * .mailDisplayBody .td_display -> .mailDisplayBody -> doc.body. Every test above uses a bare
 * doc.body with neither wrapper present, so they only ever exercise the LAST tier - not the
 * actual production DOM shape (jmap.ts's wrapDocument() always produces .mailDisplayBody
 * .td_display), which was previously completely untested.
 */
describe("renderAttachmentIndex() - DOM insertion target fallback chain", () =>
{
	it("inserts into .mailDisplayBody .td_display when present - the real production shape", () =>
	{
		const doc = document.implementation.createHTMLDocument("");
		doc.body.innerHTML = '<div class="mailDisplayBody"><div class="td_display"><div dir="auto"></div></div></div>';

		renderAttachmentIndex(doc, [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		const tdDisplay = doc.querySelector(".td_display");
		const index = doc.querySelector(".mail_attachmentIndex");
		assert.isNotNull(index);
		assert.equal(index.parentElement, tdDisplay, "must land inside .td_display specifically, not some other ancestor");
	});

	it("falls back to .mailDisplayBody itself when it has no .td_display child", () =>
	{
		const doc = document.implementation.createHTMLDocument("");
		doc.body.innerHTML = '<div class="mailDisplayBody"><div dir="auto"></div></div>';

		renderAttachmentIndex(doc, [
			{filename: "photo.jpg", type: "image/jpeg", mime_url: "https://example.com/photo.jpg"},
		], egw);

		const wrapper = doc.querySelector(".mailDisplayBody");
		const index = doc.querySelector(".mail_attachmentIndex");
		assert.isNotNull(index);
		assert.equal(index.parentElement, wrapper);
	});
});

/**
 * The mime-icon fallback chain (attachmentIndex.ts lines 92-95): mime128_<main>_<sub> ->
 * mime128_<main> -> mime128_unknown. The original test file's egw.image() stub only ever
 * returned a real icon for "mime128_unknown", so only the last tier was ever exercised.
 */
describe("renderAttachmentIndex() - mime-icon fallback chain", () =>
{
	function docxAttachment()
	{
		return [{filename: "report.docx", type: "application/msword", mime_url: "https://example.com/report.docx"}];
	}

	it("uses the exact mime128_<main>_<sub> icon when egw.image() has one", () =>
	{
		const doc = emptyBodyDocument();
		const testEgw = {...egw, image: (name : string) => name === "mime128_application_msword" ? "https://example.com/word.svg" : null};

		renderAttachmentIndex(doc, docxAttachment(), testEgw);

		const icon = doc.body.querySelector(".mail_attachmentIndexIcon img") as HTMLImageElement;
		assert.equal(icon.src, "https://example.com/word.svg");
	});

	it("falls back to the mime128_<main>-only icon when the exact sub-type icon is unavailable", () =>
	{
		const doc = emptyBodyDocument();
		const testEgw = {...egw, image: (name : string) => name === "mime128_application" ? "https://example.com/application.svg" : null};

		renderAttachmentIndex(doc, docxAttachment(), testEgw);

		const icon = doc.body.querySelector(".mail_attachmentIndexIcon img") as HTMLImageElement;
		assert.equal(icon.src, "https://example.com/application.svg");
	});

	it("falls all the way back to the generic unknown icon when neither tier has one", () =>
	{
		const doc = emptyBodyDocument();

		renderAttachmentIndex(doc, docxAttachment(), egw);

		const icon = doc.body.querySelector(".mail_attachmentIndexIcon img") as HTMLImageElement;
		assert.equal(icon.src, "https://example.com/unknown.svg");
	});
});

/**
 * The click-to-open handler (attachmentIndex.ts lines 84-91) - for an attachment with mime_data
 * but no directly embeddable url, clicking the mime-icon must call egw.open_link() with the
 * attachment's own mime_data/type rather than navigating to the placeholder "#" href. Previously
 * untested - the existing "falls back to unknown mime-icon" test never actually simulated a
 * click.
 */
describe("renderAttachmentIndex() - click-to-open for a mime_data-only attachment", () =>
{
	it("calls egw.open_link() with the attachment's mime_data/type and prevents the default '#' navigation", () =>
	{
		const doc = emptyBodyDocument();
		let openLinkArgs : any[] | null = null;
		const testEgw = {...egw, open_link: (...args : any[]) => { openLinkArgs = args; }};

		renderAttachmentIndex(doc, [
			{filename: "blob.bin", type: "application/octet-stream", mime_data: "sometoken"},
		], testEgw);

		const link = doc.body.querySelector(".mail_attachmentIndexIcon") as HTMLAnchorElement;
		const event = new MouseEvent("click", {bubbles: true, cancelable: true});
		link.dispatchEvent(event);

		assert.isTrue(event.defaultPrevented, "must not navigate to the '#' placeholder href");
		assert.deepEqual(openLinkArgs, ["sometoken", "_blank", undefined, undefined, "application/octet-stream"]);
	});
});
