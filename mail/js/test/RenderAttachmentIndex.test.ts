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
});
