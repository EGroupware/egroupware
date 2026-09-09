import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Regression/gap coverage for MailApp.retryAttachmentIndexForRow() - the async retry path
 * renderMessageInto() uses once an on-demand JMAP attachmentsBlock fetch resolves (the message
 * body may have already finished loading, and found itself empty, before the attachments were
 * known). Extracted out of renderMessageInto() (2026-09-09) purely so this could be tested in
 * isolation - see that method's own docblock.
 *
 * This was previously the one piece of the auto-index feature (mail/js/attachmentIndex.ts) with
 * NO test coverage at all: RenderAttachmentIndex.test.ts only ever tested renderAttachmentIndex()
 * itself as a pure function, never MailApp's own wiring/race-guard around it - the actual
 * `iframeDoc?.documentElement?.dataset.rowId === rowId` check that decides whether a stale async
 * resolution is allowed to render into the CURRENT iframe at all.
 */

/** Object.create(MailApp.prototype), not `new MailApp()` - EgwApp's constructor wants a real
 * framework/sidebox/egw instance; retryAttachmentIndexForRow() only ever reads `this.et2`/
 * `this.egw`, same minimal-instance convention MailMobileViewFlag.test.ts already established. */
function createMailApp(iframeDoc : Document | null) : MailApp
{
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		egw : {lang : (label : string) => label, link : () => "#", image : () => null, open_link : () => {}},
		et2 : {
			getWidgetById : (id : string) => id === "messageIFRAME" ? {iframe : {contentDocument : iframeDoc}} : undefined,
		},
	});
	return app;
}

function emptyBodyDocument() : Document
{
	const doc = document.implementation.createHTMLDocument("");
	doc.body.innerHTML = '<div dir="auto"></div>';
	return doc;
}

describe("MailApp.retryAttachmentIndexForRow()", () =>
{
	it("renders the auto-index into the iframe when it still shows the same row", () =>
	{
		const doc = emptyBodyDocument();
		doc.documentElement.dataset.rowId = "rowA";
		const app = createMailApp(doc);

		(app as any).retryAttachmentIndexForRow("rowA", [
			{filename : "photo.jpg", type : "image/jpeg", mime_url : "https://example.com/photo.jpg"},
		]);

		assert.isNotNull(doc.body.querySelector(".mail_attachmentIndex"));
	});

	it("does NOT render when the iframe has since moved on to a different row (stale async resolution)", () =>
	{
		const doc = emptyBodyDocument();
		doc.documentElement.dataset.rowId = "rowB";
		const app = createMailApp(doc);

		(app as any).retryAttachmentIndexForRow("rowA", [
			{filename : "photo.jpg", type : "image/jpeg", mime_url : "https://example.com/photo.jpg"},
		]);

		assert.isNull(doc.body.querySelector(".mail_attachmentIndex"),
			"a stale row's attachments must never render into an iframe that has since moved on");
	});

	it("does NOT render when the iframe has no rowId marker at all yet", () =>
	{
		const doc = emptyBodyDocument();
		// deliberately no dataset.rowId set - loadMessageBody() hasn't marked it (yet, or ever)
		const app = createMailApp(doc);

		(app as any).retryAttachmentIndexForRow("rowA", [
			{filename : "photo.jpg", type : "image/jpeg", mime_url : "https://example.com/photo.jpg"},
		]);

		assert.isNull(doc.body.querySelector(".mail_attachmentIndex"));
	});

	it("does nothing, without throwing, when there is no messageIFRAME widget at all", () =>
	{
		const app = Object.create(MailApp.prototype) as MailApp;
		Object.assign(app, {egw : {}, et2 : {getWidgetById : () => undefined}});

		assert.doesNotThrow(() => (app as any).retryAttachmentIndexForRow("rowA", []));
	});

	it("does nothing, without throwing, when the iframe has no contentDocument yet (still loading)", () =>
	{
		const app = createMailApp(null);

		assert.doesNotThrow(() => (app as any).retryAttachmentIndexForRow("rowA", []));
	});
});
