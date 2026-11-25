import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Ticket #128441 (RBZ Schützenpark, 2026-11-25: "Anhänge von Apple-Geräten werden nicht
 * angezeigt"): a real JMAP server (Stalwart) computes `hasAttachment` (RFC 8621 §4.1.6) as false
 * whenever every non-body part of a message is marked `Content-Disposition: inline` - eg. a PDF
 * an iPad's Mail app attaches inline - even though RFC 8621 still lists it in Email.attachments.
 * MailJmap.email2row()'s own `hasAttachment` (the row-list paperclip / the gate that decides
 * whether MailApp.renderMessageInto() ever fetches the real attachmentsBlock at all) is built
 * purely from that flag, so such a message never showed its real attachment anywhere.
 *
 * MailApp.shouldRevealLateAttachments() was factored out of loadMessageBody() so this is testable
 * without the surrounding iframe/DOM machinery - it only reads the global egw.dataGetUIDdata(),
 * same minimal-instance convention MailAttachmentIndexRetry.test.ts already established.
 */

/** The real Dienstreiseantrag PDF part from ticket #128441's own attached test .eml
 * (A-umbrochen.eml) - Apple Mail/iPad, no Content-ID at all, just an inline-dispositioned PDF. */
const APPLE_INLINE_PDF_ATTACHMENT = {
	partId: '2', type: 'application/pdf', name: 'Dienstreiseantrag T.Test, Leadership Modul 8.pdf',
	disposition: 'inline', size: 905,
};

/** A genuinely embedded signature-logo-style inline image, referenced by the HTML body via cid: -
 * must NOT count as a real attachment. */
const CID_INLINE_IMAGE = {partId: '2', type: 'image/png', name: 'logo.png', disposition: 'inline', cid: '<logo@example.org>', size: 2000};

function createMailApp() : MailApp
{
	return Object.create(MailApp.prototype) as MailApp;
}

describe("MailApp.shouldRevealLateAttachments() (ticket #128441)", () =>
{
	const originalEgw = (<any>window).egw;

	afterEach(() =>
	{
		(<any>window).egw = originalEgw;
	});

	function stubEgw(cachedData : any)
	{
		(<any>window).egw = {dataGetUIDdata : () => cachedData ? {data : cachedData} : undefined};
	}

	it("reveals an Apple/iPad inline-disposition PDF when hasAttachment came back false", () =>
	{
		stubEgw({attachments : ''}); // hasAttachment was false - email2row()'s own empty-string convention
		const app = createMailApp();

		const result = (app as any).shouldRevealLateAttachments('row1',
			{special : false, attachments : [APPLE_INLINE_PDF_ATTACHMENT]} as any);

		assert.isTrue(result);
	});

	it("does NOT re-trigger when the row already shows the paperclip (hasAttachment was already true)", () =>
	{
		stubEgw({attachments : 'attach'});
		const app = createMailApp();

		const result = (app as any).shouldRevealLateAttachments('row1',
			{special : false, attachments : [APPLE_INLINE_PDF_ATTACHMENT]} as any);

		assert.isFalse(result, "the ordinary resolveJmapAttachmentsBlock() gate already handles this case - no redundant second fetch");
	});

	it("does NOT trigger for a message whose only attachments are cid-referenced inline images", () =>
	{
		stubEgw({attachments : ''});
		const app = createMailApp();

		const result = (app as any).shouldRevealLateAttachments('row1',
			{special : false, attachments : [CID_INLINE_IMAGE]} as any);

		assert.isFalse(result);
	});

	it("still triggers for a cid-carrying part explicitly marked disposition=attachment", () =>
	{
		stubEgw({attachments : ''});
		const app = createMailApp();
		const explicitAttachment = {...CID_INLINE_IMAGE, disposition : 'attachment'};

		const result = (app as any).shouldRevealLateAttachments('row1',
			{special : false, attachments : [explicitAttachment]} as any);

		assert.isTrue(result);
	});

	it("always triggers for the S/MIME fast path, regardless of the cached row data", () =>
	{
		stubEgw({attachments : 'attach'}); // would otherwise short-circuit the ordinary case above
		const app = createMailApp();

		const result = (app as any).shouldRevealLateAttachments('row1',
			{special : false, smime : {verified : true}, attachments : [APPLE_INLINE_PDF_ATTACHMENT]} as any);

		assert.isTrue(result);
	});

	it("never triggers when fast.attachments is empty", () =>
	{
		stubEgw({attachments : ''});
		const app = createMailApp();

		const result = (app as any).shouldRevealLateAttachments('row1', {special : false, attachments : []} as any);

		assert.isFalse(result);
	});
});
